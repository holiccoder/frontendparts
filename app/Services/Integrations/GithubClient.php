<?php

namespace App\Services\Integrations;

use App\Models\GithubConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The GitHub REST API surface used by the repo export (SPEC §6.4). Every
 * call goes through this class so tests can `Http::fake()` the whole flow;
 * the connected account's access token is decrypted (model cast) only here,
 * inside the client — controllers never touch it.
 *
 * Export call sequence (all `https://api.github.com`):
 *
 * 1. `POST /user/repos` — create the repo (empty: `auto_init: false`).
 * 2. `POST /repos/{owner}/{repo}/git/trees` — one tree whose inline `content`
 *    entries make GitHub create every file's blob in a single call.
 * 3. `POST /repos/{owner}/{repo}/git/commits` — one commit pointing at that
 *    tree with no parents.
 * 4. `POST /repos/{owner}/{repo}/git/refs` — create `refs/heads/{branch}` at
 *    the commit (a fresh repo has no ref to update yet).
 *
 * Result: every scaffold file lands in exactly one commit (§6.4).
 */
class GithubClient
{
    private const string BASE_URL = 'https://api.github.com';

    private const string API_VERSION = '2022-11-28';

    public function __construct(
        private readonly GithubConnection $connection,
    ) {}

    /**
     * Create an empty repository on the connected account.
     *
     * @return array{owner: string, name: string, full_name: string, html_url: string, default_branch: string}
     *
     * @throws GithubApiException
     */
    public function createRepository(string $name, bool $private, ?string $description = null): array
    {
        $response = $this->request()->post(self::BASE_URL.'/user/repos', [
            'name' => $name,
            'private' => $private,
            'description' => $description,
            'auto_init' => false,
        ]);

        $this->throwIfFailed($response, 'create the repository');

        return [
            'owner' => (string) $response->json('owner.login'),
            'name' => (string) $response->json('name'),
            'full_name' => (string) $response->json('full_name'),
            'html_url' => (string) $response->json('html_url'),
            'default_branch' => (string) ($response->json('default_branch') ?? 'main'),
        ];
    }

    /**
     * Commit every file in a single root commit on the given branch via the
     * Git Trees API (SPEC §6.4): tree with all blobs → commit → branch ref.
     *
     * @param  array<string, string>  $files  repo path → contents
     *
     * @throws GithubApiException
     */
    public function pushInitialCommit(string $owner, string $repo, string $branch, array $files, string $message): void
    {
        $tree = $this->request()->post(self::BASE_URL."/repos/{$owner}/{$repo}/git/trees", [
            'tree' => collect($files)
                ->map(fn (string $contents, string $path): array => [
                    'path' => $path,
                    'mode' => '100644',
                    'type' => 'blob',
                    'content' => $contents,
                ])
                ->values()
                ->all(),
        ]);

        $this->throwIfFailed($tree, 'write the scaffold files');

        $commit = $this->request()->post(self::BASE_URL."/repos/{$owner}/{$repo}/git/commits", [
            'message' => $message,
            'tree' => $tree->json('sha'),
            'parents' => [],
        ]);

        $this->throwIfFailed($commit, 'commit the scaffold files');

        $ref = $this->request()->post(self::BASE_URL."/repos/{$owner}/{$repo}/git/refs", [
            'ref' => "refs/heads/{$branch}",
            'sha' => $commit->json('sha'),
        ]);

        $this->throwIfFailed($ref, 'publish the default branch');
    }

    /**
     * Authenticated request for the connected account — the only place the
     * stored token is decrypted.
     */
    private function request(): PendingRequest
    {
        return Http::withToken($this->connection->token)
            ->acceptJson()
            ->withHeaders(['X-GitHub-Api-Version' => self::API_VERSION]);
    }

    /**
     * @throws GithubApiException
     */
    private function throwIfFailed(Response $response, string $action): void
    {
        if ($response->failed()) {
            throw GithubApiException::fromResponse($response, $action);
        }
    }
}
