<?php

namespace Tests\Feature\Catalog;

use App\Enums\ComponentEventType;
use App\Models\Category;
use App\Models\Component;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ComponentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('previews');
    }

    public function test_returns_full_payload_json()
    {
        $library = $this->makeLibraryTree('demo-01');
        config(['library.react_path' => "{$library}/react", 'library.vue_path' => "{$library}/vue"]);

        $usage = Category::factory()->usage()->create(['slug' => 'hero']);
        $component = Component::factory()->published()->create([
            'slug' => 'elements/demo-01',
            'name' => 'Demo Hero 01',
            'usage_category_id' => $usage->id,
            'preview_paths' => [
                'react' => 'elements/demo-01/1.0.0/react.html',
                'vue' => 'elements/demo-01/1.0.0/vue.html',
            ],
        ]);

        $this->getJson('/api/components/hero/demo-01')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('name', 'Demo Hero 01')
            ->assertJsonPath('slug', 'elements/demo-01')
            ->assertJsonPath('basename', 'demo-01')
            ->assertJsonPath('usage.slug', 'hero')
            ->assertJsonPath('access', $component->access_level->value)
            ->assertJsonPath('version', '1.0.0')
            ->assertJsonPath('data.heading', 'Hello world')
            ->assertJsonPath('params.heading.type', 'string')
            ->assertJsonCount(1, 'files.react')
            ->assertJsonCount(1, 'files.vue')
            ->assertJsonStructure([
                'features' => ['dark_toggle', 'tree_interactions'],
                'entitled',
                'tree',
                'related',
                'previews' => ['react', 'vue'],
                'screenshots' => ['react', 'vue'],
                'citation' => ['source_name', 'source_url'],
            ]);

        // The overlay endpoint records NO view event (SPEC §8.6 stays page-only).
        $this->assertDatabaseMissing('component_events', [
            'component_id' => $component->id,
            'type' => ComponentEventType::View->value,
        ]);
    }

    public function test_404_for_draft()
    {
        $usage = Category::factory()->usage()->create(['slug' => 'hero']);

        Component::factory()->draft()->create([
            'slug' => 'elements/draft-01',
            'usage_category_id' => $usage->id,
        ]);

        $this->getJson('/api/components/hero/draft-01')->assertNotFound();
    }

    public function test_rate_limited()
    {
        $usage = Category::factory()->usage()->create(['slug' => 'hero']);

        Component::factory()->published()->create([
            'slug' => 'elements/demo-01',
            'usage_category_id' => $usage->id,
        ]);

        for ($attempt = 0; $attempt < 60; $attempt++) {
            $this->getJson('/api/components/hero/demo-01')->assertOk();
        }

        $this->getJson('/api/components/hero/demo-01')->assertTooManyRequests();
    }

    /**
     * Minimal library tree on disk (SPEC §3.1): both framework sides with
     * entry source plus shared params.json / data.json.
     */
    private function makeLibraryTree(string $basename): string
    {
        $base = sys_get_temp_dir().'/fp-library-'.uniqid();

        foreach (['react' => 'index.tsx', 'vue' => 'index.vue'] as $framework => $entry) {
            $directory = "{$base}/{$framework}/elements/{$basename}";

            mkdir($directory, 0777, true);

            file_put_contents("{$directory}/{$entry}", "export default function {$basename}() {}");
        }

        file_put_contents(
            "{$base}/react/elements/{$basename}/params.json",
            (string) json_encode([
                'heading' => [
                    'type' => 'string',
                    'default' => 'Heading',
                    'description' => 'Main heading text.',
                ],
            ])
        );

        file_put_contents(
            "{$base}/react/elements/{$basename}/data.json",
            (string) json_encode(['heading' => 'Hello world'])
        );

        return $base;
    }
}
