<?php

namespace Tests\Feature\Catalog;

use App\Enums\ComponentEventType;
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
}
