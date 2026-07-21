<?php

namespace Tests\Feature\Integrations;

use App\Enums\ComponentLevel;
use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Component;
use App\Models\GithubConnection;
use App\Models\Order;
use App\Models\Project;
use App\Models\User;
use App\Services\Scaffold\ScaffoldZipper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Library\Concerns\BuildsLibraryFixtures;
use Tests\TestCase;

/**
 * GitHub repo export (SPEC §6.4): pick framework → name repo + visibility →
 * the GitHub API creates the repo → every scaffold file is committed in a
 * single commit via the Git Trees API (tree with all blobs → commit →
 * branch ref) → the repo URL comes back. Pro-only (§7.1); requires a
 * connected GitHub account; API failures surface as user-facing errors.
 * Every GitHub call runs through the GithubClient so Http::fake covers the
 * whole flow.
 */
class GithubExportTest extends TestCase
{
    use BuildsLibraryFixtures;
    use RefreshDatabase;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLibraryFixtures();

        $this->libraryComponent('sections/hero-01', data: [
            'heading' => 'Build faster',
            'image' => 'https://images.example.com/hero.png',
        ]);
        $this->libraryComponent('sections/cta-01');
        $this->libraryComponent('pages/landing-page-01');

        $this->user = User::factory()->create();
        $this->project = Project::factory()->for($this->user)->named('Marketing site')->create();
    }

    protected function tearDown(): void
    {
        $this->tearDownLibraryFixtures();
        parent::tearDown();
    }

    public function test_repo_created_and_tree_committed()
    {
        $this->fakeGithub();
        $this->proWithConnection();

        $this->add('sections/hero-01');
        $this->add('pages/landing-page-01');

        $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/github-export", [
                'framework' => 'next',
                'name' => 'marketing-site',
                'visibility' => 'private',
            ])
            ->assertCreated();

        // Exactly four API calls: create repo → create tree → create commit
        // → create the default-branch ref (SPEC §6.4 single-commit flow).
        Http::assertSentCount(4);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.github.com/user/repos'
            && $request->hasHeader('Authorization', 'Bearer gho_test-token')
            && $request->hasHeader('X-GitHub-Api-Version')
            && $request['name'] === 'marketing-site'
            && $request['private'] === true
            && $request['auto_init'] === false);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.github.com/repos/octocat/marketing-site/git/trees');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.github.com/repos/octocat/marketing-site/git/commits'
            && $request['tree'] === 'tree-sha-1'
            && $request['parents'] === []);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.github.com/repos/octocat/marketing-site/git/refs'
            && $request['ref'] === 'refs/heads/main'
            && $request['sha'] === 'commit-sha-1');
    }

    public function test_single_commit_contains_all_scaffold_files()
    {
        $this->fakeGithub();
        $this->proWithConnection();

        $this->add('sections/hero-01');
        $this->add('sections/cta-01');
        $this->add('pages/landing-page-01');

        $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/github-export", [
                'framework' => 'next',
                'name' => 'marketing-site',
                'visibility' => 'public',
            ])
            ->assertCreated();

        // The tree payload carries exactly the files the scaffold zipper
        // would zip — same entries accessor, so zip and repo never drift.
        $entries = ScaffoldZipper::for('next')->entries($this->project->refresh());

        $treeRequest = collect(Http::recorded())
            ->map(fn (array $pair): Request => $pair[0])
            ->first(fn (Request $request): bool => str_ends_with($request->url(), '/git/trees'));

        $this->assertNotNull($treeRequest);

        $tree = collect($treeRequest['tree']);

        $this->assertSame(
            collect($entries)->keys()->sort()->values()->all(),
            $tree->pluck('path')->sort()->values()->all(),
        );

        // Every entry is a full-content blob — one tree call, no separate
        // blob uploads.
        foreach ($tree as $entry) {
            $this->assertSame('100644', $entry['mode']);
            $this->assertSame('blob', $entry['type']);
            $this->assertSame($entries[$entry['path']], $entry['content']);
        }

        // A single root commit points at that tree…
        $commitRequests = collect(Http::recorded())
            ->map(fn (array $pair): Request => $pair[0])
            ->filter(fn (Request $request): bool => str_ends_with($request->url(), '/git/commits'));

        $this->assertCount(1, $commitRequests);
        $this->assertSame([], $commitRequests->first()['parents']);

        // …and no further commits touch the repo.
        $this->assertSame(4, Http::recorded()->count());
    }

    public function test_returns_repo_url()
    {
        $this->fakeGithub();
        $this->proWithConnection();

        $this->add('sections/hero-01');

        $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/github-export", [
                'framework' => 'nuxt',
                'name' => 'marketing-site',
                'visibility' => 'private',
            ])
            ->assertCreated()
            ->assertJsonPath('repo.url', 'https://github.com/octocat/marketing-site')
            ->assertJsonPath('repo.full_name', 'octocat/marketing-site')
            ->assertJsonPath('repo.framework', 'nuxt')
            ->assertJsonPath('repo.visibility', 'private');

        // The Nuxt starter was the exported tree.
        $treeRequest = collect(Http::recorded())
            ->map(fn (array $pair): Request => $pair[0])
            ->first(fn (Request $request): bool => str_ends_with($request->url(), '/git/trees'));

        $paths = collect($treeRequest['tree'])->pluck('path');

        $this->assertTrue($paths->contains('nuxt.config.ts'));
        $this->assertTrue($paths->contains('app/app.vue'));
    }

    public function test_pro_gated()
    {
        Http::fake();

        // A connected account doesn't help — the plan gate comes first.
        GithubConnection::factory()->for($this->user)->create();

        $this->add('sections/hero-01');

        // Free plan → 403 upgrade payload, no GitHub call made.
        $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/github-export", [
                'framework' => 'next',
                'name' => 'marketing-site',
                'visibility' => 'private',
            ])
            ->assertForbidden()
            ->assertExactJson([
                'error' => 'upgrade_required',
                'upgrade' => [
                    'cta' => 'Upgrade to Pro',
                    'pricing_url' => '/pricing',
                ],
            ]);

        // Starter covers the full library but not exports (SPEC §7.1).
        Order::factory()->create([
            'user_id' => $this->user->id,
            'plan' => OrderPlan::Starter,
            'status' => OrderStatus::Active,
        ]);

        $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/github-export", [
                'framework' => 'next',
                'name' => 'marketing-site',
                'visibility' => 'private',
            ])
            ->assertForbidden()
            ->assertJsonPath('error', 'upgrade_required');

        // The Inertia form post gets a field error instead of the payload.
        $this->actingAs($this->user)
            ->post("/dashboard/projects/{$this->project->id}/github-export", [
                'framework' => 'next',
                'name' => 'marketing-site',
                'visibility' => 'private',
            ])
            ->assertSessionHasErrors('github');

        Http::assertNothingSent();

        // Only the owner may export.
        Order::factory()->create([
            'user_id' => $this->user->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Active,
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson("/dashboard/projects/{$this->project->id}/github-export", [
                'framework' => 'next',
                'name' => 'marketing-site',
                'visibility' => 'private',
            ])
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_api_failure_surfaces_error()
    {
        $this->proWithConnection();
        $this->add('sections/hero-01');

        // One closure fake for the whole test (stubs resolve
        // first-registered-wins, so layered Http::fake() calls would shadow
        // each other); the failure modes flip between requests.
        $repoFailsWith = null;
        $treeFailsWith = null;

        Http::fake(function (Request $request) use (&$repoFailsWith, &$treeFailsWith) {
            if (str_ends_with($request->url(), '/user/repos')) {
                return $repoFailsWith ?? Http::response([
                    'name' => 'marketing-site',
                    'full_name' => 'octocat/marketing-site',
                    'html_url' => 'https://github.com/octocat/marketing-site',
                    'default_branch' => 'main',
                    'owner' => ['login' => 'octocat'],
                ], 201);
            }

            if (str_ends_with($request->url(), '/git/trees')) {
                return $treeFailsWith ?? Http::response(['sha' => 'tree-sha-1'], 201);
            }

            if (str_ends_with($request->url(), '/git/commits')) {
                return Http::response(['sha' => 'commit-sha-1'], 201);
            }

            return Http::response(['ref' => 'refs/heads/main'], 201);
        });

        // Not connected → prompt to connect, no GitHub call made.
        $this->user->githubConnection()->delete();

        $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/github-export", [
                'framework' => 'next',
                'name' => 'marketing-site',
                'visibility' => 'private',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'github_not_connected')
            ->assertJsonPath('connect_url', route('connections.edit'));

        Http::assertNothingSent();

        // Reconnect — now a GitHub 422 on repo creation surfaces its
        // message instead of failing silently.
        GithubConnection::factory()->for($this->user)->create(['token' => 'gho_test-token']);

        $repoFailsWith = Http::response(['message' => 'Repository creation failed.'], 422);

        $response = $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/github-export", [
                'framework' => 'next',
                'name' => 'marketing-site',
                'visibility' => 'private',
            ])
            ->assertStatus(502)
            ->assertJsonPath('error', 'github_api_failed');

        $this->assertStringContainsString('Repository creation failed.', $response->json('message'));

        // The Inertia form post surfaces the same message as a field error.
        $this->actingAs($this->user)
            ->post("/dashboard/projects/{$this->project->id}/github-export", [
                'framework' => 'next',
                'name' => 'marketing-site',
                'visibility' => 'private',
            ])
            ->assertSessionHasErrors('github');

        // A mid-flow failure (tree creation 500s after the repo exists)
        // surfaces too — no partial silent success.
        $repoFailsWith = null;
        $treeFailsWith = Http::response(['message' => 'Server Error'], 500);

        $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/github-export", [
                'framework' => 'next',
                'name' => 'marketing-site',
                'visibility' => 'private',
            ])
            ->assertStatus(502)
            ->assertJsonPath('error', 'github_api_failed');
    }

    /**
     * GitHub API fake for the happy path: repo created under `octocat`,
     * tree/commit/ref accepted with deterministic SHAs.
     */
    private function fakeGithub(): void
    {
        Http::fake([
            'https://api.github.com/user/repos' => Http::response([
                'name' => 'marketing-site',
                'full_name' => 'octocat/marketing-site',
                'html_url' => 'https://github.com/octocat/marketing-site',
                'default_branch' => 'main',
                'owner' => ['login' => 'octocat'],
            ], 201),
            'https://api.github.com/repos/octocat/marketing-site/git/trees' => Http::response([
                'sha' => 'tree-sha-1',
            ], 201),
            'https://api.github.com/repos/octocat/marketing-site/git/commits' => Http::response([
                'sha' => 'commit-sha-1',
            ], 201),
            'https://api.github.com/repos/octocat/marketing-site/git/refs' => Http::response([
                'ref' => 'refs/heads/main',
            ], 201),
        ]);
    }

    /**
     * Entitle the user to Pro and connect a GitHub account with a known
     * token, so the only variable left is the scenario under test.
     */
    private function proWithConnection(): void
    {
        Order::factory()->create([
            'user_id' => $this->user->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Active,
        ]);

        GithubConnection::factory()->for($this->user)->create([
            'github_login' => 'octocat',
            'token' => 'gho_test-token',
        ]);
    }

    /**
     * Add a fixture component to the test project via the dashboard
     * endpoint, so the auto-add closure (SPEC §6.1) populates the set.
     */
    private function add(string $slug): void
    {
        $usage = Category::query()->where('slug', 'pricing')->first()
            ?? Category::factory()->usage()->create(['slug' => 'pricing']);

        $component = Component::factory()->published()->free()->create([
            'slug' => $slug,
            'level' => ComponentLevel::fromDirectory(str($slug)->before('/')->toString()),
            'usage_category_id' => $usage->id,
        ]);

        $this->actingAs($this->user)
            ->postJson("/dashboard/projects/{$this->project->id}/components", [
                'component_id' => $component->id,
            ])
            ->assertCreated();
    }
}
