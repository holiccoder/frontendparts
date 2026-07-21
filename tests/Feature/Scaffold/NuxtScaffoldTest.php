<?php

namespace Tests\Feature\Scaffold;

use App\Enums\ComponentEventType;
use App\Enums\ComponentLevel;
use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Enums\ProjectExportKind;
use App\Enums\ProjectExportStatus;
use App\Jobs\BuildProjectScaffold;
use App\Models\Category;
use App\Models\Component;
use App\Models\ComponentEvent;
use App\Models\Order;
use App\Models\Project;
use App\Models\User;
use App\Services\Scaffold\NuxtScaffoldZipper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Library\Concerns\BuildsLibraryFixtures;
use Tests\TestCase;
use ZipArchive;

/**
 * Nuxt scaffolding (SPEC §6.3, FR-5): a project exports as a complete
 * runnable Nuxt 4 + Vue 3 + Tailwind 4 starter — closure `components/` +
 * `data/` assembled by the shared ClosureZip kernel (Vue SFC sources, imports
 * rewritten into the zip layout), page-level components as file-based routes
 * under `app/pages/`, loose selected sections assembled into the index page
 * in selection order, sample images left as remote URLs. Pro-only (§7.1);
 * queued server-side assembly → zip download, with a `scaffold` component
 * event per exported component recorded at download time (§8.6, mirroring
 * the pack zip's download-event convention). Assembly is pure PHP — no npm.
 */
class NuxtScaffoldTest extends TestCase
{
    use BuildsLibraryFixtures;
    use RefreshDatabase;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLibraryFixtures();

        $this->libraryComponent('sections/hero-01', data: [
            'heading' => 'Build faster',
            'image' => 'https://images.example.com/hero.png',
        ]);
        $this->libraryComponent('sections/feature-01');
        $this->libraryComponent('sections/cta-01');
        $this->libraryComponent('pages/landing-page-01');
        $this->libraryComponent('pages/pricing-page-01');
        $this->libraryComponent('blocks/demo-01');
        $this->libraryComponent('sections/demo-01');

        $this->user = User::factory()->create();
        $this->project = Project::factory()->for($this->user)->named('Marketing site')->create();
    }

    protected function tearDown(): void
    {
        $this->tearDownLibraryFixtures();
        parent::tearDown();
    }

    public function test_zip_contains_full_starter_structure()
    {
        $this->add('sections/hero-01');
        $this->add('pages/landing-page-01');

        $entries = $this->scaffoldEntries();

        $names = array_keys($entries);
        sort($names);

        $this->assertSame([
            '.gitignore',
            'README.md',
            'app/app.vue',
            'app/assets/css/main.css',
            'app/pages/index.vue',
            'app/pages/landing-page-01.vue',
            'components/pages/LandingPage01.vue',
            'components/sections/Hero01.vue',
            'data/hero-01.ts',
            'data/landing-page-01.ts',
            'nuxt.config.ts',
            'package.json',
            'public/',
            'tsconfig.json',
        ], $names);

        // Tailwind 4 is pre-wired: the Vite plugin + the single CSS import
        // (the documented Nuxt setup, docs/content/install/nuxt.md).
        $this->assertSame("@import 'tailwindcss';\n", $entries['app/assets/css/main.css']);
        $this->assertStringContainsString('@tailwindcss/vite', $entries['nuxt.config.ts']);
        $this->assertStringContainsString("css: ['~/assets/css/main.css']", $entries['nuxt.config.ts']);

        // Nuxt 4 app shell: app.vue carries the project name + NuxtPage.
        $this->assertStringContainsString("title: 'Marketing site'", $entries['app/app.vue']);
        $this->assertStringContainsString('<NuxtPage />', $entries['app/app.vue']);
        $this->assertStringContainsString('.nuxt', $entries['.gitignore']);
        $this->assertStringContainsString('./.nuxt/tsconfig.json', $entries['tsconfig.json']);

        // Closure sources keep the shared kernel's rewritten imports.
        $this->assertStringContainsString('<template><div /></template>', $entries['components/sections/Hero01.vue']);
        $this->assertStringContainsString('# Marketing site — Nuxt starter', $entries['README.md']);
    }

    public function test_page_components_become_routes()
    {
        $this->add('pages/landing-page-01');
        $this->add('pages/pricing-page-01');

        $entries = $this->scaffoldEntries();

        // Each page-level component becomes a file-based route module that
        // renders the component with its sample-data module bound in.
        $route = $entries['app/pages/landing-page-01.vue'];

        $this->assertStringContainsString("import LandingPage01 from '../../components/pages/LandingPage01.vue';", $route);
        $this->assertStringContainsString("import landingPage01Data from '../../data/landing-page-01';", $route);
        $this->assertStringContainsString('<LandingPage01 v-bind="landingPage01Data" />', $route);

        $this->assertStringContainsString(
            "import PricingPage01 from '../../components/pages/PricingPage01.vue';",
            $entries['app/pages/pricing-page-01.vue'],
        );

        $this->assertArrayHasKey('components/pages/PricingPage01.vue', $entries);

        // The README lists the assembled routes.
        $this->assertStringContainsString('`/landing-page-01`', $entries['README.md']);
        $this->assertStringContainsString('`/pricing-page-01`', $entries['README.md']);
    }

    public function test_loose_sections_assembled_into_index_in_order()
    {
        // Selection order — the order picks were added to the project — not
        // slug order (cta-01 sorts first alphabetically but was added last).
        $this->add('sections/hero-01');
        $this->add('sections/feature-01');
        $this->add('sections/cta-01');
        $this->add('pages/landing-page-01');

        $index = $this->scaffoldEntries()['app/pages/index.vue'];

        $this->assertStringContainsString('<script setup lang="ts">', $index);
        $this->assertStringContainsString("import Hero01 from '../../components/sections/Hero01.vue';", $index);
        $this->assertStringContainsString("import hero01Data from '../../data/hero-01';", $index);

        $hero = strpos($index, '<Hero01 v-bind="hero01Data" />');
        $feature = strpos($index, '<Feature01 v-bind="feature01Data" />');
        $cta = strpos($index, '<Cta01 v-bind="cta01Data" />');

        $this->assertNotFalse($hero);
        $this->assertNotFalse($feature);
        $this->assertNotFalse($cta);
        $this->assertTrue($hero < $feature && $feature < $cta, 'index page must render loose selections in selection order');

        // Page-level picks become routes, not index sections.
        $this->assertStringNotContainsString('LandingPage01', $index);
    }

    public function test_remote_image_urls_preserved()
    {
        $this->add('sections/hero-01');

        $entries = $this->scaffoldEntries();

        // The sample image stays a remote URL in the data module — nothing
        // is downloaded or rewritten into public/ (FR-5.4).
        $this->assertStringContainsString('https://images.example.com/hero.png', $entries['data/hero-01.ts']);

        $this->assertSame(['public/'], array_filter(
            array_keys($entries),
            fn (string $name): bool => str_starts_with($name, 'public'),
        ));
    }

    public function test_merged_package_json()
    {
        // Two picks declaring the same logical dep resolve it exactly once.
        $this->add('sections/hero-01', ['deps' => ['lucide']]);
        $this->add('sections/feature-01', ['deps' => ['lucide']]);

        $package = json_decode($this->scaffoldEntries()['package.json'], true);

        $this->assertSame('marketing-site', $package['name']);
        $this->assertTrue($package['private']);
        $this->assertSame('nuxt dev', $package['scripts']['dev']);

        // Nuxt 4 + Vue 3 baseline with the closure dep merged at its
        // registry-pinned version (vue column), deduped by package name.
        $this->assertSame([
            'lucide-vue-next' => '^1.0.0',
            'nuxt' => '^4.1.0',
            'vue' => '^3.5.0',
        ], $package['dependencies']);

        // Tailwind 4 + TS toolchain on the dev side.
        $this->assertSame('^4.1.0', $package['devDependencies']['tailwindcss']);
        $this->assertSame('^4.1.0', $package['devDependencies']['@tailwindcss/vite']);
        $this->assertArrayHasKey('typescript', $package['devDependencies']);
    }

    public function test_pro_only_gate_403_for_starter()
    {
        Queue::fake();

        $this->add('sections/hero-01');

        // Free plan → 403 upgrade payload, nothing queued.
        $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/scaffold", ['framework' => 'nuxt'])
            ->assertForbidden()
            ->assertExactJson([
                'error' => 'upgrade_required',
                'upgrade' => [
                    'cta' => 'Upgrade to Pro',
                    'pricing_url' => '/pricing',
                ],
            ]);

        $this->assertDatabaseCount('project_exports', 0);
        Queue::assertNotPushed(BuildProjectScaffold::class);

        // Starter covers the full library but not scaffolding (SPEC §7.1).
        Order::factory()->create([
            'user_id' => $this->user->id,
            'plan' => OrderPlan::Starter,
            'status' => OrderStatus::Active,
        ]);

        $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/scaffold", ['framework' => 'nuxt'])
            ->assertForbidden()
            ->assertJsonPath('error', 'upgrade_required');

        $this->assertDatabaseCount('project_exports', 0);

        // The Inertia form post gets a field error instead of the payload.
        $this->actingAs($this->user)
            ->post("/dashboard/projects/{$this->project->id}/scaffold", ['framework' => 'nuxt'])
            ->assertSessionHasErrors('scaffold');

        // Pro proceeds: 202 + pending scaffold export + queued build.
        Order::factory()->create([
            'user_id' => $this->user->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Active,
        ]);

        $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/scaffold", ['framework' => 'nuxt'])
            ->assertAccepted()
            ->assertJsonPath('export.status', 'pending')
            ->assertJsonPath('export.framework', 'nuxt')
            ->assertJsonPath('export.download_url', null);

        $export = $this->project->exports()->sole();

        $this->assertSame(ProjectExportKind::Scaffold, $export->kind);
        $this->assertSame(ProjectExportStatus::Pending, $export->status);

        Queue::assertPushed(BuildProjectScaffold::class, fn (BuildProjectScaffold $job): bool => $job->exportId === $export->id);

        // An unknown scaffold framework never queues a build.
        $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/scaffold", ['framework' => 'astro'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('framework');

        // Only the owner may scaffold.
        $this->actingAs(User::factory()->create())
            ->postJson("/dashboard/projects/{$this->project->id}/scaffold", ['framework' => 'nuxt'])
            ->assertForbidden();
    }

    public function test_scaffold_event_recorded()
    {
        Storage::fake('exports');
        Queue::fake();

        Order::factory()->create([
            'user_id' => $this->user->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Active,
        ]);

        $this->add('sections/hero-01');
        $this->add('pages/landing-page-01');

        $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/scaffold", ['framework' => 'nuxt'])
            ->assertAccepted();

        $export = $this->project->exports()->sole();

        // Run the queued job: the starter zip lands on the exports disk.
        (new BuildProjectScaffold($export->id))->handle();

        $export->refresh();

        $this->assertSame(ProjectExportStatus::Ready, $export->status);
        $this->assertSame("project-exports/{$export->id}-scaffold-nuxt.zip", $export->path);
        Storage::disk('exports')->assertExists($export->path);

        // Scaffolding alone records nothing — events fire on download,
        // mirroring the pack zip's download-event convention (§8.6).
        $this->assertDatabaseCount('component_events', 0);

        // The project page exposes the ready starter for polling.
        $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/projects/show')
                ->where('scaffold.available', true)
                ->where('scaffold.latest.id', $export->id)
                ->where('scaffold.latest.status', 'ready')
                ->where('scaffold.latest.download_url', route('dashboard.projects.export.download', [$this->project, $export]))
            );

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/export/{$export->id}/download");

        $response->assertOk();

        $this->assertStringContainsString(
            'attachment; filename=marketing-site-nuxt.zip',
            (string) $response->headers->get('Content-Disposition')
        );

        $entries = $this->zipEntriesFromString($response->streamedContent());

        $this->assertArrayHasKey('app/pages/index.vue', $entries);
        $this->assertArrayHasKey('app/pages/landing-page-01.vue', $entries);

        // One scaffold event per exported component, attributed to the user.
        foreach (['sections/hero-01', 'pages/landing-page-01'] as $slug) {
            $this->assertDatabaseHas('component_events', [
                'component_id' => $this->componentBySlug($slug)->id,
                'type' => ComponentEventType::Scaffold->value,
                'user_id' => $this->user->id,
            ]);
        }

        $this->assertSame(2, ComponentEvent::query()->where('type', ComponentEventType::Scaffold->value)->count());
        $this->assertSame(0, ComponentEvent::query()->where('type', ComponentEventType::Download->value)->count());
    }

    public function test_project_with_only_pages_has_valid_index()
    {
        $this->add('pages/landing-page-01');

        $entries = $this->scaffoldEntries();

        $this->assertStringContainsString('<main />', $entries['app/pages/index.vue']);
        $this->assertStringNotContainsString('<script', $entries['app/pages/index.vue']);
        $this->assertArrayHasKey('app/pages/landing-page-01.vue', $entries);
    }

    public function test_index_imports_dedupe_shared_basenames()
    {
        // Same basename at two levels: data modules get the level prefix
        // (ClosureZip convention) and the index's import locals follow it.
        $this->add('sections/demo-01');
        $this->add('blocks/demo-01');

        $index = $this->scaffoldEntries()['app/pages/index.vue'];

        $this->assertStringContainsString("import Demo01 from '../../components/sections/Demo01.vue';", $index);
        $this->assertStringContainsString("import BlocksDemo01 from '../../components/blocks/Demo01.vue';", $index);
        // Closure members are zipped blocks-first, so the block's data
        // module keeps the bare name and the section's gets the prefix.
        $this->assertStringContainsString("import blocksDemo01Data from '../../data/demo-01';", $index);
        $this->assertStringContainsString("import demo01Data from '../../data/sections-demo-01';", $index);
        $this->assertStringContainsString('<BlocksDemo01 v-bind="blocksDemo01Data" />', $index);
    }

    /**
     * Add a fixture component to the test project via the dashboard
     * endpoint, so the auto-add closure (SPEC §6.1) populates the set.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function add(string $slug, array $attributes = []): void
    {
        $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/components", [
                'component_id' => $this->publish($slug, $attributes)->id,
            ])
            ->assertCreated();
    }

    /**
     * Publish a fixture component into the test database (its files already
     * exist in the fixture library tree).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function publish(string $slug, array $attributes = []): Component
    {
        $usage = Category::query()->where('slug', 'pricing')->first()
            ?? Category::factory()->usage()->create(['slug' => 'pricing']);

        return Component::factory()->published()->free()->create([
            'slug' => $slug,
            'level' => ComponentLevel::fromDirectory(str($slug)->before('/')->toString()),
            'usage_category_id' => $usage->id,
            ...$attributes,
        ]);
    }

    private function componentBySlug(string $slug): Component
    {
        return Component::query()->where('slug', $slug)->sole();
    }

    /**
     * Zip entry name → contents map of the starter built directly by the
     * zipper service (the queued job's input; structure tests need no queue).
     *
     * @return array<string, string>
     */
    private function scaffoldEntries(): array
    {
        $path = app(NuxtScaffoldZipper::class)->build($this->project->refresh());

        $entries = $this->readZip($path);
        @unlink($path);

        return $entries;
    }

    /**
     * @return array<string, string>
     */
    private function zipEntriesFromString(string $contents): array
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'fp-scaffold-test-');
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
