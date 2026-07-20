<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PreviewLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_saves_layout_preference()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/components')
            ->patch('/settings/preview-layout', ['side' => 'left', 'split' => 35])
            ->assertRedirect('/components');

        $this->assertSame(['side' => 'left', 'split' => 35], $user->fresh()->preview_layout);

        // Shared with Inertia so the modal can initialize from it.
        $this->actingAs($user)
            ->get('/components')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.preview_layout.side', 'left')
                ->where('auth.preview_layout.split', 35)
            );
    }

    public function test_validation_rejects_invalid_layout_payload()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/components')
            ->patch('/settings/preview-layout', ['side' => 'center', 'split' => 10])
            ->assertSessionHasErrors(['side', 'split']);

        $this->actingAs($user)
            ->from('/components')
            ->patch('/settings/preview-layout', ['side' => 'right', 'split' => 90])
            ->assertSessionHasErrors('split');

        $this->actingAs($user)
            ->from('/components')
            ->patch('/settings/preview-layout', ['split' => 50])
            ->assertSessionHasErrors('side');

        $this->assertNull($user->fresh()->preview_layout);
    }

    public function test_guest_401_or_419_redirect()
    {
        $this->from('/components')
            ->patch('/settings/preview-layout', ['side' => 'left', 'split' => 50])
            ->assertRedirect('/login');

        $this->patchJson('/settings/preview-layout', ['side' => 'left', 'split' => 50])
            ->assertUnauthorized();
    }
}
