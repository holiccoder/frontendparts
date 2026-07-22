<?php

namespace Tests\Feature\Security;

use App\Models\GithubConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TokenEncryptionAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_github_token_is_encrypted_at_rest(): void
    {
        $plaintextToken = 'gho_supersecrettokenvalue123456';

        $connection = GithubConnection::factory()->create([
            'token' => $plaintextToken,
        ]);

        $this->assertSame($plaintextToken, $connection->fresh()->token);

        $raw = DB::table('github_connections')->value('token');

        $this->assertNotSame($plaintextToken, $raw, 'GitHub token must not be stored as plaintext');
        $this->assertFalse(str_contains((string) $raw, $plaintextToken), 'Raw DB value must not contain the plaintext token');
    }
}
