<?php

namespace Tests\Feature\Catalog;

use App\Enums\ComponentEventType;
use App\Models\Category;
use App\Models\Component;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ComponentPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('previews');
    }

    public function test_200_with_full_modal_payload()
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

        $this->get('/components/hero/demo-01')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('catalog/component')
                ->where('component.name', 'Demo Hero 01')
                ->where('component.slug', 'elements/demo-01')
                ->where('component.basename', 'demo-01')
                ->where('component.usage.slug', 'hero')
                ->where('component.access', $component->access_level->value)
                ->where('component.version', '1.0.0')
                ->has('component.files.react', 1)
                ->has('component.files.vue', 1)
                ->where('component.data.heading', 'Hello world')
                ->where('component.params.heading.type', 'string')
                ->where('component.previews.react', route('previews.show', [
                    'component' => 'elements/demo-01',
                    'version' => '1.0.0',
                    'framework' => 'react',
                ]))
                ->where('component.previews.vue', route('previews.show', [
                    'component' => 'elements/demo-01',
                    'version' => '1.0.0',
                    'framework' => 'vue',
                ]))
                // No screenshots on disk → fail-soft null URLs (SPEC §5.2).
                ->where('component.og_image', null)
                ->where('component.screenshots.react.375', null)
                ->has('component.tree')
                ->has('component.related')
                ->where('framework', 'react')
            );

        $this->assertDatabaseHas('component_events', [
            'component_id' => $component->id,
            'type' => ComponentEventType::View->value,
            'user_id' => null,
        ]);
    }

    public function test_citation_prop_present()
    {
        $usage = Category::factory()->usage()->create(['slug' => 'hero']);

        Component::factory()->published()->create([
            'slug' => 'elements/cited-01',
            'usage_category_id' => $usage->id,
            'source_name' => 'tailwindcss.com',
            'source_url' => 'https://tailwindcss.com',
        ]);

        $this->get('/components/hero/cited-01')
            ->assertInertia(fn (Assert $page) => $page
                ->where('component.citation.source_name', 'tailwindcss.com')
                ->where('component.citation.source_url', 'https://tailwindcss.com')
            );
    }

    public function test_canonical_and_og_point_to_screenshot()
    {
        $usage = Category::factory()->usage()->create(['slug' => 'hero']);

        Component::factory()->published()->create([
            'slug' => 'elements/shots-01',
            'usage_category_id' => $usage->id,
            'preview_paths' => [
                'react' => 'elements/shots-01/1.0.0/react.html',
                'vue' => 'elements/shots-01/1.0.0/vue.html',
            ],
        ]);

        Storage::disk('previews')->put('elements/shots-01/1.0.0/react.html', '<html>preview</html>');
        Storage::disk('previews')->put('elements/shots-01/1.0.0/shots/react-1280.png', 'png-bytes');

        $shotUrl = route('previews.shots', [
            'component' => 'elements/shots-01',
            'version' => '1.0.0',
            'file' => 'react-1280.png',
        ]);

        $this->get('/components/hero/shots-01')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('meta.canonical', route('components.show', ['usage' => 'hero', 'slug' => 'shots-01']))
                ->where('meta.og_image', $shotUrl)
                ->where('component.og_image', $shotUrl)
                ->where('component.screenshots.react.1280', $shotUrl)
            );

        // The shot itself is served by the previews disk route.
        $this->get($shotUrl)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_draft_404()
    {
        $usage = Category::factory()->usage()->create(['slug' => 'hero']);

        Component::factory()->draft()->create([
            'slug' => 'elements/draft-01',
            'usage_category_id' => $usage->id,
        ]);

        $this->get('/components/hero/draft-01')->assertNotFound();
    }

    public function test_related_components_same_usage()
    {
        $usage = Category::factory()->usage()->create(['slug' => 'hero']);
        $other = Category::factory()->usage()->create(['slug' => 'pricing']);

        $component = Component::factory()->published()->create([
            'slug' => 'elements/main-01',
            'usage_category_id' => $usage->id,
        ]);

        Component::factory()->count(8)->published()->create(['usage_category_id' => $usage->id]);
        Component::factory()->count(2)->published()->create(['usage_category_id' => $other->id]);

        $response = $this->get('/components/hero/main-01');

        $response->assertInertia(fn (Assert $page) => $page
            ->component('catalog/component')
            ->has('component.related', 6)
            ->where('component.related.0.usage.slug', 'hero')
        );

        $relatedIds = collect($response->viewData('page')['props']['component']['related'])->pluck('id');

        $this->assertNotContains($component->id, $relatedIds);
    }

    public function test_ambiguous_basename_404()
    {
        $usage = Category::factory()->usage()->create(['slug' => 'hero']);

        Component::factory()->published()->create(['slug' => 'elements/shared-01', 'usage_category_id' => $usage->id]);
        Component::factory()->published()->create(['slug' => 'sections/shared-01', 'usage_category_id' => $usage->id]);

        $this->get('/components/hero/shared-01')->assertNotFound();
    }

    public function test_view_event_linked_to_authenticated_user()
    {
        $usage = Category::factory()->usage()->create(['slug' => 'hero']);

        $component = Component::factory()->published()->create([
            'slug' => 'elements/viewed-01',
            'usage_category_id' => $usage->id,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)->get('/components/hero/viewed-01')->assertOk();

        $this->assertDatabaseHas('component_events', [
            'component_id' => $component->id,
            'type' => ComponentEventType::View->value,
            'user_id' => $user->id,
        ]);
    }

    public function test_dark_toggle_included_only_when_feature_flag_on()
    {
        $usage = Category::factory()->usage()->create(['slug' => 'hero']);

        Component::factory()->published()->create([
            'slug' => 'elements/flag-01',
            'usage_category_id' => $usage->id,
        ]);

        $settings = app(Settings::class);

        $settings->set('features.preview_dark_toggle', true);

        $this->get('/components/hero/flag-01')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('component.features.dark_toggle', true)
            );

        $settings->set('features.preview_dark_toggle', false);

        $this->get('/components/hero/flag-01')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('component.features.dark_toggle', false)
            );
    }

    public function test_tree_interactions_flag_present()
    {
        $usage = Category::factory()->usage()->create(['slug' => 'hero']);

        Component::factory()->published()->create([
            'slug' => 'elements/tree-flag-01',
            'usage_category_id' => $usage->id,
        ]);

        $settings = app(Settings::class);

        $settings->set('features.tree_interactions', true);

        $this->get('/components/hero/tree-flag-01')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('component.features.tree_interactions', true)
            );

        $settings->set('features.tree_interactions', false);

        $this->get('/components/hero/tree-flag-01')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('component.features.tree_interactions', false)
            );
    }

    public function test_entitled_placeholder_guest_locked_authed_passes()
    {
        $usage = Category::factory()->usage()->create(['slug' => 'hero']);

        Component::factory()->published()->paid()->create([
            'slug' => 'elements/paid-01',
            'usage_category_id' => $usage->id,
        ]);

        Component::factory()->published()->free()->create([
            'slug' => 'elements/free-01',
            'usage_category_id' => $usage->id,
        ]);

        // Phase 2 placeholder: guests are locked out of paid components…
        $this->get('/components/hero/paid-01')
            ->assertInertia(fn (Assert $page) => $page
                ->where('component.access', 'paid')
                ->where('component.entitled', false)
            );

        // …but any authenticated user passes until real gating exists, and
        // free components are always entitled.
        $user = User::factory()->create();

        $this->actingAs($user)->get('/components/hero/paid-01')
            ->assertInertia(fn (Assert $page) => $page
                ->where('component.entitled', true)
            );

        $this->get('/components/hero/free-01')
            ->assertInertia(fn (Assert $page) => $page
                ->where('component.entitled', true)
            );
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
