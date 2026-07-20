<?php

namespace Tests\Feature\Projects;

use App\Enums\AccessLevel;
use App\Enums\ComponentEventType;
use App\Enums\ComponentStatus;
use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Enums\ProjectExportStatus;
use App\Jobs\BuildProjectPackZip;
use App\Models\Component;
use App\Models\ComponentEvent;
use App\Models\Order;
use App\Models\Project;
use App\Models\User;
use App\Services\Projects\ProjectPackZipper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Library\Concerns\RunsRealPreviewBuilds;
use Tests\TestCase;
use ZipArchive;

/**
 * Pack zip export (SPEC §6.2): the project's full transitive closure
 * organized `components/` by level + `data/` sample-data modules + merged
 * `package.json` dependency snippet (deduped via the registry, SPEC §2.5) +
 * Tailwind setup notes + README, for the framework chosen at export
 * (SPEC §6.1). The build is queued (NFR-4), stored on the exports disk,
 * streamed from an owner-only route, and records `download` events (§8.6).
 * Uses the REAL synced library trees — zip assembly is pure PHP (ZipArchive)
 * and never needs npm.
 */
class PackZipTest extends TestCase
{
    use RefreshDatabase;
    use RunsRealPreviewBuilds;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->syncRealLibrary();

        // library:sync imports components as drafts.
        Component::query()->update(['status' => ComponentStatus::Published]);

        $this->user = User::factory()->create();
        $this->project = Project::factory()->for($this->user)->named('Marketing site')->create();
    }

    public function test_zip_contains_full_closure_by_level()
    {
        // Two sections sharing one element child: the closure is the union,
        // the shared child appears exactly once (SPEC §2.2, §6.1).
        $this->add('sections/feature-grid-01');
        $this->add('sections/title-showcase-01');

        $entries = $this->packEntries();

        $names = array_keys($entries);
        sort($names);

        $this->assertSame([
            'README.md',
            'TAILWIND.md',
            'components/blocks/FeatureCard01.tsx',
            'components/elements/SectionTitle01.tsx',
            'components/sections/FeatureGrid01.tsx',
            'components/sections/TitleShowcase01.tsx',
            'data/feature-card-01.ts',
            'data/feature-grid-01.ts',
            'data/section-title-01.ts',
            'data/title-showcase-01.ts',
            'package.json',
        ], $names);

        // Cross-component imports resolve inside the pack: library specifiers
        // are rewritten into the zip layout, npm imports stay untouched.
        $section = $entries['components/sections/FeatureGrid01.tsx'];

        $this->assertStringContainsString("import FeatureCard01 from '../blocks/FeatureCard01';", $section);
        $this->assertStringContainsString("import SectionTitle01 from '../elements/SectionTitle01';", $section);
        $this->assertStringNotContainsString('../../blocks/feature-card-01', $section);
        $this->assertStringNotContainsString('../../elements/section-title-01', $section);
        $this->assertStringContainsString("from 'lucide-react'", $entries['components/blocks/FeatureCard01.tsx']);
    }

    public function test_data_folder_present()
    {
        $this->add('sections/title-showcase-01');

        $entries = $this->packEntries();

        // Each closure member with a data.json ships it as an importable TS
        // module — sample data stays separate from the sources (SPEC §2.4).
        $this->assertArrayHasKey('data/title-showcase-01.ts', $entries);
        $this->assertArrayHasKey('data/section-title-01.ts', $entries);

        $this->assertStringStartsWith('export default {', $entries['data/section-title-01.ts']);
        $this->assertStringEndsWith('} as const;'."\n", $entries['data/section-title-01.ts']);
        $this->assertStringContainsString('"Everything you need to ship faster"', $entries['data/section-title-01.ts']);
    }

    public function test_merged_package_json_dedupes_closure_deps()
    {
        // Both the section and its block child declare `lucide` — the merged
        // snippet resolves it via the registry exactly once (SPEC §2.5).
        $this->add('sections/feature-grid-01');
        $this->add('sections/title-showcase-01');

        $entries = $this->packEntries();

        $package = json_decode($entries['package.json'], true);

        $this->assertSame(['lucide-react' => '^1.25.0'], $package['dependencies']);
        $this->assertStringContainsString('npm install lucide-react@^1.25.0', $entries['README.md']);

        // The vue export resolves the same logical dep against the vue side
        // of the registry.
        $vuePackage = json_decode($this->packEntries('vue')['package.json'], true);

        $this->assertSame(['lucide-vue-next' => '^1.0.0'], $vuePackage['dependencies']);

        // A zero-dep pack ships an empty dependencies map + the zero-dep note.
        $zeroDepProject = Project::factory()->for(User::factory())->create();
        $zeroDepProject->components()->attach($this->componentBySlug('sections/title-showcase-01')->id, ['is_dependency' => false]);
        $zeroDepProject->components()->attach($this->componentBySlug('elements/section-title-01')->id, ['is_dependency' => true]);

        $path = app(ProjectPackZipper::class)->build($zeroDepProject, 'react');
        $entries = $this->readZip($path);
        @unlink($path);

        $this->assertSame([], json_decode($entries['package.json'], true)['dependencies']);
        $this->assertStringContainsString('zero-dep', $entries['README.md']);
    }

    public function test_readme_and_tailwind_notes_present()
    {
        $this->add('sections/feature-grid-01');
        $this->add('sections/title-showcase-01');

        $entries = $this->packEntries();

        $readme = $entries['README.md'];

        $this->assertStringContainsString('# Marketing site', $readme);
        $this->assertStringContainsString('components/sections/FeatureGrid01.tsx', $readme);
        $this->assertStringContainsString('data/section-title-01.ts', $readme);
        $this->assertStringContainsString('npm install lucide-react@^1.25.0', $readme);
        $this->assertStringContainsString('Tailwind CSS 4', $readme);
        $this->assertStringContainsString('TAILWIND.md', $readme);
        $this->assertStringContainsString('elements → blocks → sections → pages', $readme);

        $tailwind = $entries['TAILWIND.md'];

        $this->assertStringContainsString('Tailwind CSS 4', $tailwind);
        $this->assertStringContainsString('npm install tailwindcss @tailwindcss/vite', $tailwind);
        $this->assertStringContainsString('@import "tailwindcss";', $tailwind);
    }

    public function test_framework_chosen_at_export()
    {
        $this->add('sections/feature-grid-01');

        // React: .tsx sources only.
        $react = $this->packEntries('react');

        $this->assertArrayHasKey('components/sections/FeatureGrid01.tsx', $react);
        $this->assertArrayHasKey('components/blocks/FeatureCard01.tsx', $react);

        foreach (array_keys($react) as $name) {
            $this->assertStringEndsNotWith('.vue', $name);
        }

        // Vue: .vue sources only, with the explicit `…/index.vue` specifier
        // rewritten into the zip layout; data modules stay TS.
        $vue = $this->packEntries('vue');

        $this->assertArrayHasKey('components/sections/FeatureGrid01.vue', $vue);
        $this->assertArrayHasKey('components/blocks/FeatureCard01.vue', $vue);
        $this->assertArrayNotHasKey('components/sections/FeatureGrid01.tsx', $vue);
        $this->assertArrayHasKey('data/feature-grid-01.ts', $vue);

        foreach (array_keys($vue) as $name) {
            $this->assertStringEndsNotWith('.tsx', $name);
        }

        $this->assertStringContainsString("import FeatureCard01 from '../blocks/FeatureCard01';", $vue['components/sections/FeatureGrid01.vue']);
        $this->assertStringContainsString("from 'vue'", $vue['components/sections/FeatureGrid01.vue']);
    }

    public function test_export_dispatches_job_and_streams_zip()
    {
        Storage::fake('exports');

        $this->add('sections/feature-grid-01');
        $this->add('sections/title-showcase-01');

        Queue::fake();

        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/export", ['framework' => 'react']);

        $response->assertAccepted()
            ->assertJsonPath('export.status', 'pending')
            ->assertJsonPath('export.framework', 'react')
            ->assertJsonPath('export.download_url', null);

        $export = $this->project->exports()->sole();

        $this->assertSame(ProjectExportStatus::Pending, $export->status);
        $this->assertSame($this->user->id, $export->user_id);

        Queue::assertPushed(BuildProjectPackZip::class, fn (BuildProjectPackZip $job): bool => $job->exportId === $export->id);

        // Only the owner may export or download.
        $other = User::factory()->create();

        $this->actingAs($other)->postJson("/dashboard/projects/{$this->project->id}/export")->assertForbidden();
        $this->actingAs($other)->get("/dashboard/projects/{$this->project->id}/export/{$export->id}/download")->assertForbidden();

        // An invalid framework choice never queues a build.
        $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/export", ['framework' => 'svelte'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('framework');

        // Run the queued job: the zip lands on the exports disk and the row
        // flips ready with its path.
        app()->call([(new BuildProjectPackZip($export->id)), 'handle']);

        $export->refresh();

        $this->assertSame(ProjectExportStatus::Ready, $export->status);
        $this->assertSame("project-exports/{$export->id}-react.zip", $export->path);
        Storage::disk('exports')->assertExists($export->path);

        // The dashboard page exposes the ready download URL for polling.
        $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/projects/show')
                ->where('export.latest.id', $export->id)
                ->where('export.latest.status', 'ready')
                ->where('export.latest.download_url', route('dashboard.projects.export.download', [$this->project, $export]))
            );

        // The download route streams the stored zip.
        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/export/{$export->id}/download");

        $response->assertOk();

        $this->assertStringContainsString(
            'attachment; filename=marketing-site-react.zip',
            (string) $response->headers->get('Content-Disposition')
        );

        $entries = $this->zipEntriesFromString($response->streamedContent());

        $this->assertArrayHasKey('components/sections/FeatureGrid01.tsx', $entries);
        $this->assertArrayHasKey('package.json', $entries);

        // Scoped bindings: another project's export does not resolve under
        // this project's URL.
        $otherExport = Project::factory()->for($this->user)->create()->exports()->create([
            'user_id' => $this->user->id,
            'framework' => 'react',
        ]);

        $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/export/{$otherExport->id}/download")
            ->assertNotFound();
    }

    public function test_download_event_recorded()
    {
        Storage::fake('exports');

        $this->add('sections/feature-grid-01');
        $this->add('sections/title-showcase-01');

        $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/export", ['framework' => 'vue']);

        $export = $this->project->exports()->sole();

        app()->call([(new BuildProjectPackZip($export->id)), 'handle']);

        // Exporting alone records nothing — the event fires on download (§8.6).
        $this->assertDatabaseCount('component_events', 0);

        $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/export/{$export->id}/download")
            ->assertOk();

        // One download event per pack component, attributed to the user —
        // the same license tracking as single-component downloads.
        foreach (['sections/feature-grid-01', 'blocks/feature-card-01', 'elements/section-title-01', 'sections/title-showcase-01'] as $slug) {
            $this->assertDatabaseHas('component_events', [
                'component_id' => $this->componentBySlug($slug)->id,
                'type' => ComponentEventType::Download->value,
                'user_id' => $this->user->id,
            ]);
        }

        $this->assertSame(4, ComponentEvent::query()->where('type', ComponentEventType::Download->value)->count());
    }

    public function test_free_user_blocked_when_project_contains_paid_components()
    {
        // Plan expired after the paid component was added: the entitlement is
        // re-resolved at export time and no longer covers the pack (SPEC §7.1).
        $this->add('sections/feature-grid-01');
        $this->componentBySlug('sections/feature-grid-01')->update(['access_level' => AccessLevel::Paid]);

        Order::factory()->create([
            'user_id' => $this->user->id,
            'plan' => OrderPlan::Starter,
            'status' => OrderStatus::Expired,
        ]);

        $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/export", ['framework' => 'react'])
            ->assertForbidden()
            ->assertExactJson([
                'error' => 'upgrade_required',
                'upgrade' => [
                    'cta' => 'Upgrade to download',
                    'pricing_url' => '/pricing',
                ],
            ]);

        $this->assertDatabaseCount('project_exports', 0);
        Queue::assertNotPushed(BuildProjectPackZip::class);

        // The Inertia form post gets a field error instead of the payload.
        $this->actingAs($this->user)
            ->post("/dashboard/projects/{$this->project->id}/export")
            ->assertSessionHasErrors('export');

        $this->assertDatabaseCount('project_exports', 0);

        // A full-library plan (Starter/Pro) passes the gate.
        $subscriber = User::factory()->create();
        Order::factory()->create([
            'user_id' => $subscriber->id,
            'plan' => OrderPlan::Starter,
            'status' => OrderStatus::Active,
        ]);
        $project = Project::factory()->for($subscriber)->create();
        $project->components()->attach($this->componentBySlug('sections/feature-grid-01')->id, ['is_dependency' => false]);

        $this->actingAs($subscriber)
            ->postJson("/dashboard/projects/{$project->id}/export")
            ->assertAccepted();
    }

    /**
     * Add a real library component to the test project via the dashboard
     * endpoint, so the auto-add closure (SPEC §6.1) populates the set.
     */
    private function add(string $slug): void
    {
        $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/components", [
                'component_id' => $this->componentBySlug($slug)->id,
            ])
            ->assertCreated();
    }

    /**
     * Zip entry name → contents map of the pack built directly by the
     * zipper service (the queued job's input; structure tests need no queue).
     *
     * @return array<string, string>
     */
    private function packEntries(string $framework = 'react'): array
    {
        $path = app(ProjectPackZipper::class)->build($this->project->refresh(), $framework);

        $entries = $this->readZip($path);
        @unlink($path);

        return $entries;
    }

    /**
     * @return array<string, string>
     */
    private function zipEntriesFromString(string $contents): array
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'fp-pack-test-');
        file_put_contents($path, $contents);

        $entries = $this->readZip($path);
        @unlink($path);

        return $entries;
    }

    /**
     * @return array<string, string>
     */
    private function readZip(string $path): array
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path), "could not open zip at {$path}");

        $entries = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
        }

        $zip->close();

        return $entries;
    }
}
