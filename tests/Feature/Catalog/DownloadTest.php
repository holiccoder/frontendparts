<?php

namespace Tests\Feature\Catalog;

use App\Enums\ComponentEventType;
use App\Enums\ComponentStatus;
use App\Models\Category;
use App\Models\Component;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Library\Concerns\RunsRealPreviewBuilds;
use Tests\TestCase;
use ZipArchive;

/**
 * Single-component zip export (SPEC §2.4, §6.1): accountless for free
 * components; closure organized by level + `data/` modules + README; sources
 * verbatim (no `data-fp-*` instrumentation, which only exists in preview
 * build artifacts). Uses the REAL synced library trees — zip assembly is
 * pure PHP (ZipArchive) and never needs npm.
 */
class DownloadTest extends TestCase
{
    use RefreshDatabase;
    use RunsRealPreviewBuilds;

    protected function setUp(): void
    {
        parent::setUp();

        $this->syncRealLibrary();

        // library:sync imports components as drafts; downloads are published-only.
        Component::query()->update(['status' => ComponentStatus::Published]);
    }

    public function test_guest_can_download_free_component_zip()
    {
        $response = $this->get('/components/feature-grid/title-showcase-01/download');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/zip');

        $this->assertStringContainsString(
            'attachment; filename=title-showcase-01-react.zip',
            (string) $response->headers->get('Content-Disposition')
        );

        $this->assertDatabaseHas('component_events', [
            'component_id' => $this->componentBySlug('sections/title-showcase-01')->id,
            'type' => ComponentEventType::Download->value,
            'user_id' => null,
        ]);
    }

    public function test_zip_structure_levels_plus_data_folder()
    {
        $entries = $this->zipEntries($this->get('/components/feature-grid/title-showcase-01/download'));

        $names = array_keys($entries);
        sort($names);

        $this->assertSame([
            'README.md',
            'components/elements/SectionTitle01.tsx',
            'components/sections/TitleShowcase01.tsx',
            'data/section-title-01.ts',
            'data/title-showcase-01.ts',
        ], $names);

        // Sources are the authored library files, byte for byte — the
        // element has no closure imports, so it is fully verbatim.
        $this->assertSame(
            file_get_contents(config('library.react_path').'/elements/section-title-01/index.tsx'),
            $entries['components/elements/SectionTitle01.tsx']
        );

        // The composite differs only by its rewritten child import specifier.
        $this->assertSame(
            str_replace(
                '../../elements/section-title-01',
                '../elements/SectionTitle01',
                (string) file_get_contents(config('library.react_path').'/sections/title-showcase-01/index.tsx')
            ),
            $entries['components/sections/TitleShowcase01.tsx']
        );

        // Data modules export the component's data.json as a const default export.
        $this->assertStringStartsWith('export default {', $entries['data/section-title-01.ts']);
        $this->assertStringEndsWith('} as const;'."\n", $entries['data/section-title-01.ts']);
        $this->assertStringContainsString('"Everything you need to ship faster"', $entries['data/section-title-01.ts']);
        $this->assertStringContainsString('"section-title-01"', $entries['data/title-showcase-01.ts']);

        // README: name, citation, file map, deps + Tailwind 4 + import-order notes.
        $readme = $entries['README.md'];

        $this->assertStringContainsString('# Title Showcase 01', $readme);
        $this->assertStringContainsString('https://tailwindcss.com', $readme);
        $this->assertStringContainsString('components/elements/SectionTitle01.tsx', $readme);
        $this->assertStringContainsString('data/section-title-01.ts', $readme);
        $this->assertStringContainsString('zero-dep', $readme);
        $this->assertStringContainsString('Tailwind CSS 4', $readme);
        $this->assertStringContainsString('elements → blocks → sections → pages', $readme);
    }

    public function test_zip_sources_have_no_instrumentation()
    {
        foreach (['react', 'vue'] as $framework) {
            $entries = $this->zipEntries($this->get("/components/feature-grid/title-showcase-01/download?framework={$framework}"));

            foreach ($entries as $name => $contents) {
                $this->assertStringNotContainsString('data-fp-', $contents, "{$name} ({$framework}) contains preview instrumentation");
            }
        }
    }

    public function test_zip_child_imports_are_rewritten_to_export_layout()
    {
        // React: extensionless directory specifier → zip-relative path.
        $entries = $this->zipEntries($this->get('/components/feature-grid/title-showcase-01/download'));

        $this->assertStringContainsString(
            "import SectionTitle01 from '../elements/SectionTitle01';",
            $entries['components/sections/TitleShowcase01.tsx']
        );
        $this->assertStringNotContainsString('../../elements/section-title-01', $entries['components/sections/TitleShowcase01.tsx']);

        // Vue: explicit entry-file specifier (`…/index.vue`) → same rewritten target.
        $entries = $this->zipEntries($this->get('/components/feature-grid/title-showcase-01/download?framework=vue'));

        $this->assertStringContainsString(
            "import SectionTitle01 from '../elements/SectionTitle01';",
            $entries['components/sections/TitleShowcase01.vue']
        );
        $this->assertStringNotContainsString('../../elements/section-title-01', $entries['components/sections/TitleShowcase01.vue']);
    }

    public function test_npm_imports_left_untouched()
    {
        // The real closure imports npm packages: react (type import) on the
        // element, vue on both vue sources — specifiers must stay as authored.
        $entries = $this->zipEntries($this->get('/components/feature-grid/title-showcase-01/download'));

        $this->assertStringContainsString("from 'react'", $entries['components/elements/SectionTitle01.tsx']);

        $entries = $this->zipEntries($this->get('/components/feature-grid/title-showcase-01/download?framework=vue'));

        $this->assertStringContainsString("from 'vue'", $entries['components/sections/TitleShowcase01.vue']);
    }

    public function test_vue_download_uses_vue_sources()
    {
        $response = $this->get('/components/feature-grid/title-showcase-01/download?framework=vue');

        $this->assertStringContainsString(
            'attachment; filename=title-showcase-01-vue.zip',
            (string) $response->headers->get('Content-Disposition')
        );

        $entries = $this->zipEntries($response);

        $this->assertArrayNotHasKey('components/elements/SectionTitle01.tsx', $entries);
        $this->assertSame(
            str_replace(
                '../../elements/section-title-01/index.vue',
                '../elements/SectionTitle01',
                (string) file_get_contents(config('library.vue_path').'/sections/title-showcase-01/index.vue')
            ),
            $entries['components/sections/TitleShowcase01.vue']
        );

        // Data modules are framework-agnostic TS.
        $this->assertArrayHasKey('data/title-showcase-01.ts', $entries);
    }

    public function test_invalid_framework_param_rejected()
    {
        $this->getJson('/components/feature-grid/title-showcase-01/download?framework=svelte')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('framework');
    }

    public function test_paid_component_guest_gets_403_with_upgrade_payload()
    {
        $usage = Category::factory()->usage()->create(['slug' => 'hero']);
        $paid = Component::factory()->published()->paid()->create([
            'slug' => 'elements/paid-01',
            'usage_category_id' => $usage->id,
        ]);

        $this->getJson('/components/hero/paid-01/download')
            ->assertForbidden()
            ->assertExactJson([
                'error' => 'upgrade_required',
                'upgrade' => [
                    'cta' => 'Upgrade to download',
                    'pricing_url' => '/pricing',
                ],
            ]);

        $this->assertDatabaseMissing('component_events', [
            'component_id' => $paid->id,
            'type' => ComponentEventType::Download->value,
        ]);
    }

    public function test_authenticated_user_passes_paid_gate_until_phase_2()
    {
        $usage = Category::factory()->usage()->create(['slug' => 'hero']);
        Component::factory()->published()->paid()->create([
            'slug' => 'elements/paid-01',
            'usage_category_id' => $usage->id,
        ]);

        // Phase 2 placeholder: plan entitlements do not exist yet, so any
        // authenticated user may download paid components.
        $this->actingAs(User::factory()->create())
            ->get('/components/hero/paid-01/download')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/zip');
    }

    public function test_draft_download_404_and_records_nothing()
    {
        Component::query()->update(['status' => ComponentStatus::Draft]);

        $this->get('/components/feature-grid/section-title-01/download')->assertNotFound();

        $this->assertDatabaseCount('component_events', 0);
    }

    /**
     * Zip entry name → contents map read from the streamed temp file. The
     * response is never "sent" in tests, so deleteFileAfterSend has not
     * removed it yet; the temp file is unlinked after reading.
     *
     * @return array<string, string>
     */
    private function zipEntries(TestResponse $response): array
    {
        $response->assertOk();

        $path = $response->baseResponse->getFile()->getPathname();

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path), "could not open zip at {$path}");

        $entries = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
        }

        $zip->close();
        @unlink($path);

        return $entries;
    }
}
