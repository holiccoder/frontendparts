<?php

namespace Tests\Feature\Library;

use App\Models\Component;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Library\Concerns\RunsRealPreviewBuilds;
use Tests\TestCase;

/**
 * AST instrumentation (SPEC §2.3): data-fp-* attributes are injected into
 * preview builds only. Runs the REAL build of the composite fixture
 * component sections/title-showcase-01, which renders
 * elements/section-title-01 twice with different props.
 * Skips when npm is unavailable.
 */
class InstrumentationTest extends TestCase
{
    use RefreshDatabase;
    use RunsRealPreviewBuilds;

    private Component $composite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessNpmAvailable();

        Storage::fake('previews');

        $this->syncRealLibrary();

        $this->composite = $this->componentBySlug('sections/title-showcase-01');
    }

    public function test_preview_html_contains_fp_attributes()
    {
        foreach (['react', 'vue'] as $framework) {
            $html = $this->buildCompositeHtml($framework);

            $this->assertMatchesRegularExpression(
                '/data-fp-c["\']?\s*:\s*["\']elements\/section-title-01["\']/',
                $html,
                "{$framework}: data-fp-c for elements/section-title-01 missing",
            );

            $this->assertSame(
                ['1', '2'],
                $this->childInstances($html, 'elements/section-title-01'),
                "{$framework}: expected deterministic data-fp-i 1 then 2 for the two SectionTitle01 occurrences",
            );
        }
    }

    public function test_authored_source_file_has_no_fp_attributes()
    {
        $react = (string) file_get_contents(base_path('library/react/src/components/sections/title-showcase-01/index.tsx'));
        $vue = (string) file_get_contents(base_path('library/vue/src/components/sections/title-showcase-01/index.vue'));

        $this->assertStringNotContainsString('data-fp', $react);
        $this->assertStringNotContainsString('data-fp', $vue);
    }

    public function test_instance_numbers_stable_across_rebuilds()
    {
        foreach (['react', 'vue'] as $framework) {
            $first = $this->attributeSequence($this->buildCompositeHtml($framework));
            $second = $this->attributeSequence($this->buildCompositeHtml($framework));

            $this->assertSame($first, $second, "{$framework}: attribute sequence changed across rebuilds");
            $this->assertNotSame([], $first);
        }
    }

    private function buildCompositeHtml(string $framework): string
    {
        $this->runBuildJob($this->composite, [$framework]);

        return (string) Storage::disk('previews')->get($this->composite->fresh()->previewPath($framework));
    }

    /**
     * Ordered (data-fp-c, data-fp-i) pairs as they appear in the bundle.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function attributeSequence(string $html): array
    {
        preg_match_all(
            '/data-fp-c["\']?\s*:\s*["\']([^"\']+)["\']\s*,\s*["\']?data-fp-i["\']?\s*:\s*["\'](\d+)["\']/',
            $html,
            $matches,
            PREG_SET_ORDER,
        );

        return array_map(
            fn (array $match): array => [$match[1], $match[2]],
            $matches,
        );
    }

    /**
     * Ordered data-fp-i values for one child slug.
     *
     * @return list<string>
     */
    private function childInstances(string $html, string $slug): array
    {
        return array_map(
            fn (array $pair): string => $pair[1],
            array_filter(
                $this->attributeSequence($html),
                fn (array $pair): bool => $pair[0] === $slug,
            ),
        );
    }
}
