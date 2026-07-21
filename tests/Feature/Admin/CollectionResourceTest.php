<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Collections\Pages\CreateCollection;
use App\Filament\Resources\Collections\Pages\EditCollection;
use App\Models\Admin;
use App\Models\Collection;
use App\Models\Component;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CollectionResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_saves_collection_with_ordered_components()
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin');

        $first = Component::factory()->create();
        $second = Component::factory()->create();

        Livewire::test(CreateCollection::class)
            ->fillForm([
                'name' => 'Restaurant Landing Kit',
                'slug' => 'restaurant-landing-kit',
                'description' => 'Everything a restaurant landing page needs.',
                'status' => 'published',
                'sort_order' => 1,
                'collectionComponents' => [
                    ['component_id' => $second->id],
                    ['component_id' => $first->id],
                ],
                'meta_title' => 'Restaurant landing kit',
                'meta_description' => 'A curated restaurant bundle.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $collection = Collection::query()->where('slug', 'restaurant-landing-kit')->sole();

        $this->assertSame('published', $collection->status);
        $this->assertSame(1, $collection->sort_order);
        $this->assertSame('Restaurant landing kit', $collection->meta_title);

        // The picker order is the curated order (1-based pivot sort_order).
        $this->assertSame([$second->id, $first->id], $collection->components->modelKeys());
        $this->assertSame(1, (int) $collection->collectionComponents()->where('component_id', $second->id)->value('sort_order'));
        $this->assertSame(2, (int) $collection->collectionComponents()->where('component_id', $first->id)->value('sort_order'));
    }

    public function test_edit_reorders_and_replaces_components()
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin');

        $collection = Collection::factory()->create();
        $keep = Component::factory()->create();
        $drop = Component::factory()->create();
        $new = Component::factory()->create();

        $collection->components()->attach($keep->id, ['sort_order' => 1]);
        $collection->components()->attach($drop->id, ['sort_order' => 2]);

        Livewire::test(EditCollection::class, ['record' => $collection->id])
            ->fillForm([
                'collectionComponents' => [
                    ['component_id' => $new->id],
                    ['component_id' => $keep->id],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        // The repeater syncs the pivot: dropped rows are removed, the
        // remaining order follows the picker.
        $this->assertSame([$new->id, $keep->id], $collection->fresh()->components->modelKeys());
        $this->assertSame(1, (int) $collection->collectionComponents()->where('component_id', $new->id)->value('sort_order'));
        $this->assertSame(2, (int) $collection->collectionComponents()->where('component_id', $keep->id)->value('sort_order'));
    }
}
