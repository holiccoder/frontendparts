<?php

namespace App\Console\Commands;

use App\Enums\ComponentStatus;
use App\Jobs\BuildComponentPreview;
use App\Models\Component;
use App\Models\PreviewBuildFailure;
use App\Services\Library\LibrarySync;
use App\Support\Settings;
use Database\Seeders\CategorySeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Browser-test fixture seeder (task 5.7): rebuilds the dedicated
 * browser.sqlite database the Playwright suite serves against — production
 * taxonomy, a REAL library sync (build jobs stay parked on the database
 * queue), REAL vite preview builds for the composite fixture component,
 * published statuses and the live-edit feature flag. Playwright's global
 * setup invokes this command before booting the PHP dev server; it refuses
 * to run outside the dedicated `browser` environment.
 */
#[Signature('browser:fixtures {--slug=* : Composite components to build real preview artifacts for}')]
#[Description('Reset the browser-test database and seed the served fixture set (taxonomy, library sync, preview builds, feature flags)')]
class BrowserFixturesCommand extends Command
{
    public function handle(LibrarySync $sync): int
    {
        if (! app()->environment('browser')) {
            $this->error('browser:fixtures only runs with APP_ENV=browser (it wipes the browser-test database).');

            return self::FAILURE;
        }

        if (! str_contains((string) config('database.connections.sqlite.database'), 'browser.sqlite')) {
            $this->error('DB_DATABASE must point at the dedicated browser.sqlite file (the Playwright runner injects it).');

            return self::FAILURE;
        }

        /** @var list<string> $slugs */
        $slugs = $this->option('slug') !== [] ? $this->option('slug') : ['sections/hero-01', 'sections/feature-grid-01'];

        $this->callSilently('migrate:fresh', ['--force' => true]);
        $this->callSilently('db:seed', ['--class' => CategorySeeder::class, '--force' => true]);
        $this->line('Database migrated and taxonomy seeded.');

        $this->info('Syncing the component library (preview jobs park on the database queue)…');

        $result = $sync->run();

        if ($result->hasErrors()) {
            $this->error('library sync failed: '.json_encode($result->failures()));

            return self::FAILURE;
        }

        $this->line("Synced {$result->upserted} components.");

        $fixtures = [];

        foreach ($slugs as $slug) {
            $this->info("Building real react+vue previews for {$slug} (vite, FP_INSTRUMENT=1)…");

            $component = Component::query()->where('slug', $slug)->sole();

            app()->call([(new BuildComponentPreview($component->id, ['react', 'vue'])), 'handle']);

            $component->refresh();

            $missing = collect(['react', 'vue'])
                ->reject(fn (string $framework): bool => ($component->preview_paths[$framework] ?? null) !== null)
                ->values();

            if ($missing->isNotEmpty()) {
                $error = (string) PreviewBuildFailure::query()
                    ->where('component_id', $component->id)
                    ->value('error');

                $this->error("preview build failed for {$slug} ({$missing->implode(', ')}): {$error}");

                return self::FAILURE;
            }

            $fixtures[] = [
                'slug' => $component->slug,
                'url' => $component->publicUrl(),
                'previews' => $component->preview_paths,
            ];

            $this->mirrorInstanceEdges($component);
        }

        Component::query()->update(['status' => ComponentStatus::Published]);

        app(Settings::class)->set('features.live_edit', true);

        $this->output->writeln('BROWSER_FIXTURES_READY '.json_encode([
            'components' => Component::query()->count(),
            'fixtures' => $fixtures,
        ]));

        return self::SUCCESS;
    }

    /**
     * Align the component_children pivot rows with the data.json `children`
     * slice counts (SPEC §5.5): the sync records one edge per child slug,
     * but the structure tree's ×n instance chips (and the preview's
     * data-fp-i instrumentation) count RENDERED instances — one pivot row
     * per slice keeps the tree and the built artifact in lock-step, the
     * same way feature tests insert the rows by hand.
     */
    private function mirrorInstanceEdges(Component $component): void
    {
        $dataPath = config('library.react_path').'/'.$component->slug.'/data.json';
        $data = is_file($dataPath) ? json_decode((string) file_get_contents($dataPath), true) : null;
        $children = is_array($data) && is_array($data['children'] ?? null) ? $data['children'] : [];

        if ($children === []) {
            return;
        }

        $rows = [];
        $order = 0;

        // data.json keys children by BASENAME (authoring convention, SPEC
        // §2.2); the sync edges carry the full slugs — resolve through them.
        $childrenByBasename = $component->children()->get()->keyBy('basename');

        foreach ($children as $childSlug => $slices) {
            $child = $childrenByBasename->get((string) $childSlug);

            if ($child === null) {
                continue;
            }

            $count = is_array($slices) && array_is_list($slices) ? count($slices) : 1;

            // (parent, child, slot) is unique — each rendered slice instance
            // gets its own slot name, mirroring the hand-built multi-row
            // fixtures in the feature suite.
            for ($instance = 0; $instance < $count; $instance++) {
                $rows[] = [
                    'parent_id' => $component->id,
                    'child_id' => $child->id,
                    'slot' => $count === 1 ? 'default' : 'slice-'.($instance + 1),
                    'sort_order' => $order++,
                ];
            }
        }

        if ($rows !== []) {
            DB::table('component_children')->where('parent_id', $component->id)->delete();
            DB::table('component_children')->insert($rows);
        }
    }
}
