<?php

namespace Tests\Feature\Library;

use App\Models\Component;
use App\Services\Library\LibrarySync;
use App\Services\Library\SyncResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Library\Concerns\BuildsLibraryFixtures;
use Tests\TestCase;

class LibrarySyncValidationTest extends TestCase
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

    private function sync(): SyncResult
    {
        return app(LibrarySync::class)->run();
    }

    public function test_missing_vue_twin_fails()
    {
        $this->seedTaxonomy();
        $this->libraryComponent('sections/pricing-section-01', frameworks: ['react']);

        $result = $this->sync();

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('Missing vue twin', implode(' ', $result->errors['sections/pricing-section-01']));
        $this->assertSame(0, $result->upserted);
        $this->assertDatabaseCount('components', 0);
    }

    public function test_unknown_usage_category_fails()
    {
        $this->libraryComponent('sections/pricing-section-01');

        $result = $this->sync();

        $this->assertStringContainsString(
            "Unknown usage category 'pricing'",
            implode(' ', $result->errors['sections/pricing-section-01']),
        );
        $this->assertDatabaseCount('components', 0);
    }

    public function test_unknown_industry_fails()
    {
        $this->seedTaxonomy();
        $this->libraryComponent('sections/pricing-section-01', annotation: ['industries' => 'saas, fintech']);

        $result = $this->sync();

        $this->assertStringContainsString("Unknown industry 'saas'", implode(' ', $result->errors['sections/pricing-section-01']));
        $this->assertDatabaseCount('components', 0);
    }

    public function test_invalid_params_json_fails()
    {
        $this->seedTaxonomy();
        $this->libraryComponent('sections/pricing-section-01', params: [
            'heading' => ['type' => 'string', 'description' => 'No default here.'],
        ]);

        $result = $this->sync();

        $errors = implode(' ', $result->errors['sections/pricing-section-01']);
        $this->assertStringContainsString("param 'heading'", $errors);
        $this->assertStringContainsString('must define a default', $errors);
        $this->assertDatabaseCount('components', 0);
    }

    public function test_data_slice_mismatching_child_schema_fails()
    {
        $this->seedTaxonomy();
        $this->libraryComponent('elements/button-01', params: [
            'label' => ['type' => 'string', 'default' => 'Buy now', 'description' => 'Button label.'],
        ]);
        $this->libraryComponent('sections/pricing-section-01',
            imports: ['../../elements/button-01'],
            data: [
                'heading' => 'Pricing',
                'children' => [
                    'button-01' => ['label' => 123],
                ],
            ],
        );

        $result = $this->sync();

        $errors = implode(' ', $result->errors['sections/pricing-section-01']);
        $this->assertStringContainsString('children.button-01', $errors);
        $this->assertStringContainsString("param 'label'", $errors);
        $this->assertStringContainsString('must be of type string', $errors);
    }

    public function test_off_registry_dep_fails()
    {
        $this->seedTaxonomy();
        $this->libraryComponent('sections/pricing-section-01', annotation: ['deps' => 'evil-package']);

        $result = $this->sync();

        $this->assertStringContainsString(
            "Dep 'evil-package' is not in deps.registry.json",
            implode(' ', $result->errors['sections/pricing-section-01']),
        );
        $this->assertDatabaseCount('components', 0);
    }

    public function test_valid_component_passes_all_validations()
    {
        $this->seedTaxonomy(usage: ['pricing'], industries: ['saas']);
        $this->libraryComponent('elements/button-01', annotation: ['tags' => 'minimal'], params: [
            'label' => ['type' => 'string', 'default' => 'Buy now', 'description' => 'Button label.'],
        ], data: ['label' => 'Start free']);
        $this->libraryComponent('sections/pricing-section-01',
            annotation: ['industries' => 'saas', 'tags' => 'dark, gradient', 'deps' => 'lucide', 'access' => 'pro'],
            imports: ['../../elements/button-01'],
            data: [
                'heading' => 'Pricing',
                'children' => [
                    'button-01' => ['label' => 'Go'],
                ],
            ],
        );

        $result = $this->sync();

        $this->assertFalse($result->hasErrors(), json_encode($result->errors));
        $this->assertSame(2, $result->scanned);
        $this->assertSame(2, $result->upserted);

        $section = Component::query()->where('slug', 'sections/pricing-section-01')->sole();
        $button = Component::query()->where('slug', 'elements/button-01')->sole();

        $this->assertSame('Pricing Section 01', $section->name);
        $this->assertSame('draft', $section->status->value);
        $this->assertSame('paid', $section->access_level->value);
        $this->assertNotNull($section->source_hash);
        $this->assertTrue($section->children->contains($button));
        $this->assertTrue($section->industries->contains('slug', 'saas'));
        $this->assertSame(['dark', 'gradient'], $section->tags->pluck('slug')->sort()->values()->all());
        $this->assertTrue($button->parents->contains($section));
    }
}
