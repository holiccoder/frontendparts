<?php

namespace Tests\Feature\Library;

use App\Enums\AccessLevel;
use App\Enums\ComponentLevel;
use App\Models\Category;
use App\Models\Component;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Library\Concerns\BuildsLibraryFixtures;
use Tests\TestCase;
use ZipArchive;

/**
 * Live edit mode (SPEC §5.6, §2.5): the Edit tab payload rides the
 * preview-modal JSON only while features.live_edit is on AND the reader is
 * entitled to sources; it carries the full composition closure's sources +
 * sample-data modules + registry-pinned dependency versions for the
 * in-browser compilers — React (Phase 3.1: esbuild-wasm; sources keep
 * library-relative paths) and Vue (Phase 3.2: @vue/repl; flat `src/` file
 * map with rewritten imports). Download-of-edits zips the user's posted
 * sources back verbatim — no server-side build, ever.
 */
class LiveEditTest extends TestCase
{
    use BuildsLibraryFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLibraryFixtures();
    }

    protected function tearDown(): void
    {
        $this->tearDownLibraryFixtures();
        parent::tearDown();
    }

    public function test_edit_tab_payload_only_when_feature_flag_on()
    {
        app(Settings::class)->set('features.live_edit', true);

        $this->libraryComponent('elements/demo-01');
        $this->publish('elements/demo-01');

        $this->getJson('/api/components/pricing/demo-01')
            ->assertOk()
            ->assertJsonPath('features.live_edit', true)
            ->assertJsonPath('edit.react.entry', 'elements/demo-01')
            ->assertJsonStructure([
                'edit' => [
                    'react' => [
                        'entry',
                        'files' => [['path', 'code']],
                        'data',
                        'deps',
                    ],
                ],
            ]);
    }

    public function test_payload_contains_closure_files_and_pinned_dep_versions()
    {
        app(Settings::class)->set('features.live_edit', true);

        $this->libraryComponent('elements/button-01', data: ['label' => 'Click me']);
        $this->libraryComponent(
            'sections/pricing-01',
            source: "import Button01 from '../../elements/button-01';\nexport default function Pricing01() { return <Button01 />; }\n",
            data: ['heading' => 'Pricing'],
        );

        $child = $this->publish('elements/button-01');
        $parent = $this->publish('sections/pricing-01', ['deps' => ['lucide']]);

        DB::table('component_children')->insert([
            'parent_id' => $parent->id,
            'child_id' => $child->id,
            'slot' => 'default',
            'sort_order' => 0,
        ]);

        $response = $this->getJson('/api/components/pricing/pricing-01')->assertOk();

        $response->assertJsonPath('edit.react.entry', 'sections/pricing-01');

        // The full closure, multi-file: deterministic elements → sections
        // order (SPEC §2.4), sources verbatim from the library tree.
        $files = collect($response->json('edit.react.files'))->keyBy('path');

        $this->assertSame(
            ['elements/button-01/index.tsx', 'sections/pricing-01/index.tsx'],
            $files->keys()->all(),
        );

        $this->assertSame(
            (string) file_get_contents(config('library.react_path').'/elements/button-01/index.tsx'),
            $files->get('elements/button-01/index.tsx')['code'],
        );
        $this->assertSame(
            (string) file_get_contents(config('library.react_path').'/sections/pricing-01/index.tsx'),
            $files->get('sections/pricing-01/index.tsx')['code'],
        );

        // Sample-data modules keyed by component slug (SPEC §2.4).
        $response->assertJsonPath('edit.react.data.elements/button-01.label', 'Click me');
        $response->assertJsonPath('edit.react.data.sections/pricing-01.heading', 'Pricing');

        // Deps pinned from the registry (SPEC §2.5): the exact package@version
        // the client fetches from esm.sh — never an invented package name.
        $response->assertJsonPath('edit.react.deps.lucide', 'lucide-react@^1.25.0');
    }

    /**
     * Vue twin of the react payload (Phase 3.2, SPEC §5.6): keyed for direct
     *
     * @vue/repl store consumption — flat `src/{PascalName}.vue` file map
     * (the Repl resolves `./` imports against its src/ root), generated
     * wrapper main file, per-slug data, vue-pinned deps.
     */
    public function test_vue_edit_payload_uses_repl_structure()
    {
        app(Settings::class)->set('features.live_edit', true);

        $this->libraryComponent('elements/button-01', data: ['label' => 'Click me']);
        $this->libraryComponent(
            'sections/pricing-01',
            source: "import Button01 from '../../elements/button-01/index.vue';\n",
            data: ['heading' => 'Pricing'],
        );

        $child = $this->publish('elements/button-01');
        $parent = $this->publish('sections/pricing-01', ['deps' => ['lucide']]);

        DB::table('component_children')->insert([
            'parent_id' => $parent->id,
            'child_id' => $child->id,
            'slot' => 'default',
            'sort_order' => 0,
        ]);

        $response = $this->getJson('/api/components/pricing/pricing-01')->assertOk();

        // The Repl store contract: entry slug, the generated wrapper's
        // main-file name, and the entry SFC's repl filename.
        $response->assertJsonPath('edit.vue.entry', 'sections/pricing-01');
        $response->assertJsonPath('edit.vue.mainFile', 'src/App.vue');
        $response->assertJsonPath('edit.vue.entryFile', 'src/Pricing01.vue');

        // Files keyed in the @vue/repl structure — a flat map, deterministic
        // elements → sections order (SPEC §2.4).
        $response->assertJsonStructure([
            'edit' => [
                'vue' => [
                    'entry',
                    'entryFile',
                    'mainFile',
                    'files' => ['src/Button01.vue', 'src/Pricing01.vue'],
                    'data',
                    'deps',
                ],
            ],
        ]);

        $files = $response->json('edit.vue.files');
        $this->assertSame(['src/Button01.vue', 'src/Pricing01.vue'], array_keys($files));

        // Child SFC verbatim from the library tree; the parent's in-closure
        // import is rewritten to the Repl's src/-rooted sibling form.
        $this->assertSame(
            (string) file_get_contents(config('library.vue_path').'/elements/button-01/index.vue'),
            $files['src/Button01.vue'],
        );
        $this->assertStringContainsString("from './Button01.vue'", $files['src/Pricing01.vue']);
        $this->assertStringNotContainsString('../../elements/button-01', $files['src/Pricing01.vue']);

        // Sample-data modules keyed by component slug (SPEC §2.4).
        $response->assertJsonPath('edit.vue.data.elements/button-01.label', 'Click me');
        $response->assertJsonPath('edit.vue.data.sections/pricing-01.heading', 'Pricing');

        // Deps pinned from the registry's VUE column (SPEC §2.5) — never
        // the react package, never an invented name.
        $response->assertJsonPath('edit.vue.deps.lucide', 'lucide-vue-next@^1.0.0');
    }

    /**
     * Outlines capability contract (SPEC §5.6, Phase 3.3): each framework's
     * edit payload declares how structure-tree outlines behave in edit mode —
     * 'client-injected' (the runtime injects the data-fp-* attributes in the
     * browser, mirroring the server-side preview build) or 'unavailable'
     * (the tab renders the documented no-outlines fallback). The Vue payload
     * additionally carries the slug → PascalName map the Repl compile hook
     * tags component usages against.
     */
    public function test_payload_declares_client_side_outlines_contract()
    {
        app(Settings::class)->set('features.live_edit', true);

        $this->libraryComponent('elements/button-01');
        $this->libraryComponent(
            'sections/pricing-01',
            source: "import Button01 from '../../elements/button-01';\nexport default function Pricing01() { return <Button01 />; }\n",
        );

        $child = $this->publish('elements/button-01');
        $parent = $this->publish('sections/pricing-01');

        DB::table('component_children')->insert([
            'parent_id' => $parent->id,
            'child_id' => $child->id,
            'slot' => 'default',
            'sort_order' => 0,
        ]);

        $response = $this->getJson('/api/components/pricing/pricing-01')->assertOk();

        $response->assertJsonPath('edit.react.outlines', 'client-injected');
        $response->assertJsonPath('edit.vue.outlines', 'client-injected');

        // The Vue compile hook tags `<PascalName>` template usages with the
        // matching slug's data-fp-* attributes — the map covers the closure.
        $response->assertJsonPath('edit.vue.names.elements/button-01', 'Button01');
        $response->assertJsonPath('edit.vue.names.sections/pricing-01', 'Pricing01');
    }

    public function test_vue_edit_payload_absent_when_flag_off()
    {
        $this->libraryComponent('elements/demo-01');
        $this->publish('elements/demo-01');

        $this->getJson('/api/components/pricing/demo-01')
            ->assertOk()
            ->assertJsonPath('features.live_edit', false)
            ->assertJsonMissingPath('edit.vue');
    }

    public function test_vue_edit_payload_absent_without_entitlement()
    {
        app(Settings::class)->set('features.live_edit', true);

        $this->libraryComponent('sections/pricing-01');
        $this->publish('sections/pricing-01', ['access_level' => AccessLevel::Paid]);

        $this->getJson('/api/components/pricing/pricing-01')
            ->assertOk()
            ->assertJsonPath('features.live_edit', true)
            ->assertJsonPath('entitled', false)
            ->assertJsonMissingPath('edit.vue');
    }

    public function test_download_edits_accepts_vue_framework()
    {
        app(Settings::class)->set('features.live_edit', true);

        $this->libraryComponent('sections/pricing-01');
        $this->publish('sections/pricing-01');

        $files = [
            ['path' => 'src/Pricing01.vue', 'code' => "<script setup lang=\"ts\">\n</script>\n\n<template><section>EDITED</section></template>\n"],
            ['path' => 'src/data.ts', 'code' => "export default { heading: 'Edited' } as const;\n"],
        ];

        $response = $this->postJson('/components/pricing/pricing-01/edit-download', [
            'framework' => 'vue',
            'files' => $files,
        ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/zip');
        $this->assertStringContainsString(
            'attachment; filename=pricing-01-vue-edited.zip',
            (string) $response->headers->get('Content-Disposition'),
        );

        // Posted sources zip back verbatim — no server-side build.
        $entries = $this->zipEntries($response);
        $this->assertSame($files[0]['code'], $entries['src/Pricing01.vue']);
        $this->assertSame($files[1]['code'], $entries['src/data.ts']);
    }

    public function test_download_edits_returns_sources_without_build()
    {
        app(Settings::class)->set('features.live_edit', true);

        $this->libraryComponent('sections/pricing-01');
        $this->publish('sections/pricing-01');

        $librarySource = (string) file_get_contents(config('library.react_path').'/sections/pricing-01/index.tsx');

        $files = [
            ['path' => 'sections/pricing-01/index.tsx', 'code' => "export default function Pricing01() { return <section>EDITED</section>; }\n"],
            ['path' => 'elements/button-01/index.tsx', 'code' => "export default function Button01() { return <button>EDITED</button>; }\n"],
            ['path' => 'sections/pricing-01/data.json', 'code' => (string) json_encode(['heading' => 'Edited heading'], JSON_PRETTY_PRINT)],
        ];

        $response = $this->postJson('/components/pricing/pricing-01/edit-download', [
            'framework' => 'react',
            'files' => $files,
        ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/zip');
        $this->assertStringContainsString(
            'attachment; filename=pricing-01-react-edited.zip',
            (string) $response->headers->get('Content-Disposition'),
        );

        $entries = $this->zipEntries($response);
        $names = array_keys($entries);
        sort($names);

        $this->assertSame([
            'elements/button-01/index.tsx',
            'sections/pricing-01/data.json',
            'sections/pricing-01/index.tsx',
        ], $names);

        // The zip carries the POSTED sources verbatim — nothing compiled,
        // rewritten, or added.
        $this->assertSame($files[0]['code'], $entries['sections/pricing-01/index.tsx']);
        $this->assertSame($files[1]['code'], $entries['elements/button-01/index.tsx']);
        $this->assertSame($files[2]['code'], $entries['sections/pricing-01/data.json']);

        // No server-side build: the library tree is untouched.
        $this->assertSame(
            $librarySource,
            (string) file_get_contents(config('library.react_path').'/sections/pricing-01/index.tsx'),
        );
    }

    public function test_edit_tab_absent_when_flag_off()
    {
        $this->libraryComponent('elements/demo-01');
        $this->publish('elements/demo-01');

        $this->getJson('/api/components/pricing/demo-01')
            ->assertOk()
            ->assertJsonPath('features.live_edit', false)
            ->assertJsonMissingPath('edit');
    }

    /**
     * Gating consistency (SPEC §5.4): a locked component shows the Edit
     * tab's blur-gate state — flag on, entitled false — but the closure
     * payload is never shipped to the client.
     */
    public function test_edit_payload_absent_for_paid_component_without_entitlement()
    {
        app(Settings::class)->set('features.live_edit', true);

        $this->libraryComponent('sections/pricing-01');
        $this->publish('sections/pricing-01', ['access_level' => AccessLevel::Paid]);

        $this->getJson('/api/components/pricing/pricing-01')
            ->assertOk()
            ->assertJsonPath('features.live_edit', true)
            ->assertJsonPath('entitled', false)
            ->assertJsonMissingPath('edit');
    }

    public function test_download_edits_absent_when_flag_off()
    {
        $this->libraryComponent('elements/demo-01');
        $this->publish('elements/demo-01');

        $this->postJson('/components/pricing/demo-01/edit-download', [
            'framework' => 'react',
            'files' => [['path' => 'elements/demo-01/index.tsx', 'code' => 'edited']],
        ])->assertNotFound();
    }

    public function test_download_edits_requires_entitlement_for_paid_component()
    {
        app(Settings::class)->set('features.live_edit', true);

        $this->libraryComponent('sections/pricing-01');
        $this->publish('sections/pricing-01', ['access_level' => AccessLevel::Paid]);

        $this->postJson('/components/pricing/pricing-01/edit-download', [
            'framework' => 'react',
            'files' => [['path' => 'sections/pricing-01/index.tsx', 'code' => 'edited']],
        ])
            ->assertForbidden()
            ->assertJsonPath('error', 'upgrade_required');
    }

    public function test_download_edits_rejects_unsafe_paths()
    {
        app(Settings::class)->set('features.live_edit', true);

        $this->libraryComponent('elements/demo-01');
        $this->publish('elements/demo-01');

        foreach (['../evil.tsx', '/absolute.tsx', 'a/../../b.tsx', 'back\\slash.tsx'] as $path) {
            $this->postJson('/components/pricing/demo-01/edit-download', [
                'framework' => 'react',
                'files' => [['path' => $path, 'code' => 'edited']],
            ])->assertUnprocessable();
        }
    }

    /**
     * Published component backed by the fixture tree, under the shared
     * `pricing` usage category.
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

    /**
     * Read a streamed zip response into a name → contents map.
     *
     * @return array<string, string>
     */
    private function zipEntries(TestResponse $response): array
    {
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
