<?php

namespace Tests\Feature\Library;

use App\Services\Library\ComponentScanner;
use App\Services\Library\CompositionGraph;
use App\Services\Library\ParsedComponent;
use Tests\Feature\Library\Concerns\BuildsLibraryFixtures;
use Tests\TestCase;

class CompositionGraphTest extends TestCase
{
    use BuildsLibraryFixtures;

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

    /**
     * @return array{0: array<string, ParsedComponent>, 1: CompositionGraph, 2: string}
     */
    private function scanReact(): array
    {
        $root = config('library.react_path');

        return [(new ComponentScanner)->scan($root, 'react'), new CompositionGraph, $root];
    }

    public function test_imports_register_child_edges()
    {
        $this->libraryComponent('elements/button-01');
        $this->libraryComponent('blocks/pricing-card-01', imports: [
            '../../elements/button-01',
            'lucide-react',
            './styles.css',
        ]);
        $this->libraryComponent('sections/pricing-section-01', imports: [
            '../../blocks/pricing-card-01',
            '@/components/elements/button-01',
        ]);

        [$components, $graph, $root] = $this->scanReact();
        $edges = $graph->edges($components, $root);

        $this->assertSame(['elements/button-01'], $edges['blocks/pricing-card-01']);
        $this->assertSame(
            ['blocks/pricing-card-01', 'elements/button-01'],
            $edges['sections/pricing-section-01'],
        );
        $this->assertSame([], $edges['elements/button-01']);
    }

    public function test_cycle_a_b_a_fails_with_precise_error()
    {
        $this->libraryComponent('blocks/comp-a', imports: ['../../blocks/comp-b']);
        $this->libraryComponent('blocks/comp-b', imports: ['../../blocks/comp-a']);

        [$components, $graph, $root] = $this->scanReact();
        $errors = $graph->validate($graph->edges($components, $root));

        $this->assertNotEmpty($errors['blocks/comp-a']);
        $this->assertNotEmpty($errors['blocks/comp-b']);
        $this->assertStringContainsString('Composition cycle detected', $errors['blocks/comp-a'][0]);
        $this->assertStringContainsString('blocks/comp-a → blocks/comp-b → blocks/comp-a', $errors['blocks/comp-a'][0]);
        $this->assertSame($errors['blocks/comp-a'][0], $errors['blocks/comp-b'][0]);
    }

    public function test_depth_11_rejected()
    {
        for ($i = 1; $i <= 11; $i++) {
            $imports = $i < 11 ? [sprintf('../../sections/chain-%02d', $i + 1)] : [];
            $this->libraryComponent(sprintf('sections/chain-%02d', $i), imports: $imports);
        }

        [$components, $graph, $root] = $this->scanReact();
        $errors = $graph->validate($graph->edges($components, $root));

        $this->assertNotEmpty($errors['sections/chain-01']);
        $this->assertStringContainsString('depth 11', $errors['sections/chain-01'][0]);
        $this->assertStringContainsString('maximum of 10', $errors['sections/chain-01'][0]);
    }

    public function test_shared_child_deduplicated()
    {
        $this->libraryComponent('blocks/shared-card-01');
        $this->libraryComponent('sections/pricing-section-01', imports: ['../../blocks/shared-card-01']);
        $this->libraryComponent('sections/feature-section-01', imports: ['../../blocks/shared-card-01']);

        [$components, $graph, $root] = $this->scanReact();
        $edges = $graph->edges($components, $root);

        $this->assertCount(3, $components);
        $this->assertSame(['blocks/shared-card-01'], $edges['sections/pricing-section-01']);
        $this->assertSame(['blocks/shared-card-01'], $edges['sections/feature-section-01']);
        $this->assertEmpty($graph->validate($edges));
    }
}
