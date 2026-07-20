<?php

namespace Tests\Feature\Catalog;

use App\Enums\ComponentEventType;
use App\Models\Category;
use App\Models\Component;
use App\Models\ComponentEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ValueError;

class ComponentEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_event_with_nullable_user()
    {
        $event = ComponentEvent::factory()->create([
            'type' => ComponentEventType::View,
            'user_id' => null,
        ]);

        $this->assertNull($event->user_id);
        $this->assertSame(ComponentEventType::View, $event->type);
        $this->assertNotNull($event->created_at);
        $this->assertDatabaseHas('component_events', [
            'id' => $event->id,
            'user_id' => null,
            'type' => 'view',
        ]);
    }

    public function test_type_enum_validated()
    {
        $this->expectException(ValueError::class);

        ComponentEvent::factory()->create(['type' => 'not-a-real-type']);
    }

    public function test_helper_records_event_for_authenticated_user()
    {
        $user = User::factory()->create();
        $component = Component::factory()->create();

        $event = $component->recordEvent(ComponentEventType::Download, $user);

        $this->assertSame($user->id, $event->user_id);
        $this->assertSame(ComponentEventType::Download, $event->type);
        $this->assertDatabaseHas('component_events', [
            'component_id' => $component->id,
            'user_id' => $user->id,
            'type' => 'download',
        ]);

        $guestEvent = $component->recordEvent(ComponentEventType::View);

        $this->assertNull($guestEvent->user_id);
    }

    public function test_copy_event_recorded_via_endpoint()
    {
        $usage = Category::factory()->usage()->create(['slug' => 'hero']);
        $component = Component::factory()->published()->free()->create([
            'slug' => 'elements/copy-01',
            'usage_category_id' => $usage->id,
        ]);

        $this->post('/components/hero/copy-01/copy')->assertNoContent();

        $this->assertDatabaseHas('component_events', [
            'component_id' => $component->id,
            'type' => ComponentEventType::Copy->value,
            'user_id' => null,
        ]);
    }

    public function test_download_event_recorded()
    {
        $usage = Category::factory()->usage()->create(['slug' => 'hero']);
        $component = Component::factory()->published()->free()->create([
            'slug' => 'elements/download-01',
            'usage_category_id' => $usage->id,
        ]);

        $this->get('/components/hero/download-01/download')->assertOk();

        $this->assertDatabaseHas('component_events', [
            'component_id' => $component->id,
            'type' => ComponentEventType::Download->value,
            'user_id' => null,
        ]);
    }

    public function test_events_linked_to_user_when_authenticated()
    {
        $usage = Category::factory()->usage()->create(['slug' => 'hero']);
        $component = Component::factory()->published()->free()->create([
            'slug' => 'elements/authed-01',
            'usage_category_id' => $usage->id,
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)->post('/components/hero/authed-01/copy')->assertNoContent();
        $this->actingAs($user)->get('/components/hero/authed-01/download')->assertOk();

        $this->assertDatabaseHas('component_events', [
            'component_id' => $component->id,
            'type' => ComponentEventType::Copy->value,
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('component_events', [
            'component_id' => $component->id,
            'type' => ComponentEventType::Download->value,
            'user_id' => $user->id,
        ]);
    }

    public function test_download_endpoint_rate_limited()
    {
        $usage = Category::factory()->usage()->create(['slug' => 'hero']);
        Component::factory()->published()->free()->create([
            'slug' => 'elements/limited-01',
            'usage_category_id' => $usage->id,
        ]);

        // throttle:10,1 on the download route — the 11th hit within the
        // minute is rejected before another zip is built.
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->get('/components/hero/limited-01/download')->assertOk();
        }

        $this->get('/components/hero/limited-01/download')->assertTooManyRequests();

        $this->assertDatabaseCount('component_events', 10);
    }

    public function test_no_events_recorded_for_drafts()
    {
        $usage = Category::factory()->usage()->create(['slug' => 'hero']);
        Component::factory()->draft()->create([
            'slug' => 'elements/draft-01',
            'usage_category_id' => $usage->id,
        ]);

        $this->post('/components/hero/draft-01/copy')->assertNotFound();
        $this->get('/components/hero/draft-01/download')->assertNotFound();

        $this->assertDatabaseCount('component_events', 0);
    }
}
