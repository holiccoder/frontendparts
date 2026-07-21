<?php

namespace Tests\Feature\Integrations;

use App\Models\GithubConnection;
use App\Models\User;
use App\Notifications\GithubConnectedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

/**
 * GitHub OAuth connection (SPEC §6.4, §16.1): Socialite GitHub driver with
 * the `repo` scope; the access token is stored encrypted (model cast — the
 * raw database value is ciphertext); `/settings/connections` connects and
 * disconnects; connecting queues the GitHub-connected security notice.
 */
class GithubConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_oauth_callback_stores_encrypted_token()
    {
        Notification::fake();

        $user = User::factory()->create();

        Socialite::fake('github', (new SocialiteUser)->map([
            'id' => 'github-123',
            'nickname' => 'octocat',
            'name' => 'The Octocat',
            'email' => 'octocat@example.com',
        ])->setToken('gho_plaintext-token-123'));

        $this->actingAs($user)
            ->get('/settings/connections/github/callback')
            ->assertRedirect(route('connections.edit'));

        $connection = $user->fresh()->githubConnection;

        $this->assertNotNull($connection);
        $this->assertSame('github-123', $connection->github_id);
        $this->assertSame('octocat', $connection->github_login);
        $this->assertSame('gho_plaintext-token-123', $connection->token);

        // Reconnecting rotates the token on the same row — one connection
        // per user, never a duplicate.
        Socialite::fake('github', (new SocialiteUser)->map([
            'id' => 'github-123',
            'nickname' => 'octocat-renamed',
            'name' => 'The Octocat',
            'email' => 'octocat@example.com',
        ])->setToken('gho_rotated-token-456'));

        $this->actingAs($user)
            ->get('/settings/connections/github/callback')
            ->assertRedirect(route('connections.edit'));

        $this->assertDatabaseCount('github_connections', 1);

        $connection = $user->fresh()->githubConnection;

        $this->assertSame('octocat-renamed', $connection->github_login);
        $this->assertSame('gho_rotated-token-456', $connection->token);
    }

    public function test_token_not_readable_as_plaintext_in_db()
    {
        Notification::fake();

        $user = User::factory()->create();

        Socialite::fake('github', (new SocialiteUser)->map([
            'id' => 'github-123',
            'nickname' => 'octocat',
            'name' => 'The Octocat',
            'email' => 'octocat@example.com',
        ])->setToken('gho_plaintext-token-123'));

        $this->actingAs($user)->get('/settings/connections/github/callback');

        // The raw column value is ciphertext — Laravel's encrypted payload
        // is base64 JSON carrying iv/value/mac, never the plaintext token.
        $raw = DB::table('github_connections')->value('token');

        $this->assertIsString($raw);
        $this->assertStringNotContainsString('gho_plaintext-token-123', $raw);

        $payload = json_decode((string) base64_decode($raw, true), true);

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('iv', $payload);
        $this->assertArrayHasKey('value', $payload);
        $this->assertArrayHasKey('mac', $payload);

        // …while the model cast transparently decrypts it for the app.
        $this->assertSame('gho_plaintext-token-123', $user->fresh()->githubConnection->token);
    }

    public function test_disconnect_clears_token()
    {
        $user = User::factory()->create();

        GithubConnection::factory()->for($user)->create();

        $this->assertDatabaseCount('github_connections', 1);

        $this->actingAs($user)
            ->from('/settings/connections')
            ->delete('/settings/connections/github')
            ->assertRedirect('/settings/connections');

        $this->assertDatabaseCount('github_connections', 0);
        $this->assertNull($user->fresh()->githubConnection);
    }

    public function test_connected_notification_queued()
    {
        Notification::fake();

        $user = User::factory()->create();

        Socialite::fake('github', (new SocialiteUser)->map([
            'id' => 'github-123',
            'nickname' => 'octocat',
            'name' => 'The Octocat',
            'email' => 'octocat@example.com',
        ])->setToken('gho_plaintext-token-123'));

        $this->actingAs($user)->get('/settings/connections/github/callback');

        Notification::assertSentTo(
            $user,
            GithubConnectedNotification::class,
            fn (GithubConnectedNotification $notification): bool => $notification->githubLogin === 'octocat',
        );
    }

    public function test_redirect_sends_user_to_github_authorize()
    {
        Socialite::fake('github');

        $this->actingAs(User::factory()->create())
            ->get('/settings/connections/github/redirect')
            ->assertRedirect('https://socialite.fake/github/authorize');
    }

    public function test_connections_page_shows_connection_state()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/settings/connections')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/connections')
                ->where('github.connected', false)
                ->where('github.login', null)
                ->where('github.connected_at', null)
            );

        GithubConnection::factory()->for($user)->create(['github_login' => 'octocat']);

        $this->actingAs($user)
            ->get('/settings/connections')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/connections')
                ->where('github.connected', true)
                ->where('github.login', 'octocat')
            );
    }

    public function test_guest_cannot_reach_connection_routes()
    {
        $this->get('/settings/connections')->assertRedirect('/login');
        $this->get('/settings/connections/github/redirect')->assertRedirect('/login');
        $this->get('/settings/connections/github/callback')->assertRedirect('/login');
        $this->delete('/settings/connections/github')->assertRedirect('/login');
    }
}
