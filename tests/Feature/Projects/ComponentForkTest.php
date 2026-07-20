<?php

namespace Tests\Feature\Projects;

use App\Enums\AccessLevel;
use App\Enums\ComponentForkStatus;
use App\Enums\ComponentLevel;
use App\Jobs\BuildComponentForkPreview;
use App\Models\Category;
use App\Models\Component;
use App\Models\ComponentFork;
use App\Models\Project;
use App\Models\User;
use App\Services\Library\ForkPreviewBuilder;
use App\Services\Library\PreviewScreenshotter;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Save to Project forks (SPEC §5.6): the live-edit tab's edited sources are
 * persisted as a customized fork linked to one of the user's projects; a
 * queued rebuild (same preview-build steps as the library pipeline) produces
 * the fork's prebuilt preview + screenshots under `forks/{id}/`; the project
 * page polls the fork's status and serves the rebuilt preview from an
 * owner-only route. The original library component is never modified.
 *
 * The rebuild's node/chrome steps are stubbed (fake builder writes a canned
 * artifact, fake screenshotter writes a placeholder png — the Phase 1.5
 * pattern: the job's own orchestration runs for real via app()->call).
 */
class ComponentForkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(Settings::class)->set('features.live_edit', true);
    }

    public function test_save_creates_fork_linked_to_project()
    {
        Queue::fake();

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $component = $this->publish('sections/pricing-01');

        $files = [
            ['path' => 'sections/pricing-01/index.tsx', 'code' => "export default function Pricing01() { return <section>EDITED</section>; }\n"],
            ['path' => 'sections/pricing-01/data.json', 'code' => '{"heading":"Edited"}'],
        ];

        $response = $this->actingAs($user)
            ->postJson('/components/pricing/pricing-01/forks', [
                'project_id' => $project->id,
                'framework' => 'react',
                'files' => $files,
            ])
            ->assertAccepted()
            ->assertJsonPath('fork.status', 'pending')
            ->assertJsonPath('fork.preview_url', null)
            ->assertJsonPath('fork.project_url', route('dashboard.projects.show', $project));

        $fork = ComponentFork::query()->sole();

        $this->assertSame($response->json('fork.id'), $fork->id);
        $this->assertSame($project->id, $fork->project_id);
        $this->assertSame($component->id, $fork->component_id);
        $this->assertSame('react', $fork->framework);
        $this->assertSame(ComponentForkStatus::Pending, $fork->status);
        $this->assertSame([
            'sections/pricing-01/index.tsx' => $files[0]['code'],
            'sections/pricing-01/data.json' => $files[1]['code'],
        ], $fork->files);

        // Saving also adds the component to the project's set (direct pick).
        $this->assertDatabaseHas('project_components', [
            'project_id' => $project->id,
            'component_id' => $component->id,
            'is_dependency' => false,
        ]);
    }

    public function test_rebuild_job_queued_with_progress_state()
    {
        Queue::fake();

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $this->publish('sections/pricing-01');

        $this->actingAs($user)
            ->postJson('/components/pricing/pricing-01/forks', [
                'project_id' => $project->id,
                'framework' => 'react',
                'files' => [['path' => 'sections/pricing-01/index.tsx', 'code' => 'edited']],
            ])
            ->assertAccepted();

        $fork = ComponentFork::query()->sole();

        // Progress state at queue time: pending, no artifact yet.
        $this->assertSame(ComponentForkStatus::Pending, $fork->status);
        $this->assertNull($fork->preview_paths);
        $this->assertNull($fork->preview_built_at);
        $this->assertNull($fork->previewUrl());

        Queue::assertPushed(BuildComponentForkPreview::class, fn (BuildComponentForkPreview $job): bool => $job->forkId === $fork->id);

        // Running the job inline (build steps stubbed) flips the state to
        // ready with the fork-scoped artifact paths.
        $this->fakeBuildSteps();
        Storage::fake('previews');

        app()->call([(new BuildComponentForkPreview($fork->id)), 'handle']);

        $fresh = $fork->fresh();

        $this->assertSame(ComponentForkStatus::Ready, $fresh->status);
        $this->assertSame(['react' => "forks/{$fork->id}/react.html"], $fresh->preview_paths);
        $this->assertNotNull($fresh->preview_built_at);
        $this->assertNull($fresh->error);
        $this->assertNotNull($fresh->previewUrl());

        // A failed rebuild records the state + error on the row.
        $this->instance(ForkPreviewBuilder::class, new class extends ForkPreviewBuilder
        {
            public function build(ComponentFork $fork): string
            {
                throw new \RuntimeException('vite exploded');
            }
        });

        app()->call([(new BuildComponentForkPreview($fork->id)), 'handle']);

        $failed = $fork->fresh();

        $this->assertSame(ComponentForkStatus::Failed, $failed->status);
        $this->assertStringContainsString('vite exploded', (string) $failed->error);
    }

    public function test_fork_preview_served_after_rebuild()
    {
        Queue::fake();
        Storage::fake('previews');
        $this->fakeBuildSteps();

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $this->publish('sections/pricing-01');

        $this->actingAs($user)
            ->postJson('/components/pricing/pricing-01/forks', [
                'project_id' => $project->id,
                'framework' => 'react',
                'files' => [['path' => 'sections/pricing-01/index.tsx', 'code' => 'edited']],
            ])
            ->assertAccepted();

        $fork = ComponentFork::query()->sole();

        // Not served before the rebuild completes.
        $this->actingAs($user)
            ->get("/dashboard/projects/{$project->id}/forks/{$fork->id}/preview")
            ->assertNotFound();

        app()->call([(new BuildComponentForkPreview($fork->id)), 'handle']);

        $this->assertSame(ComponentForkStatus::Ready, $fork->fresh()->status);

        $this->actingAs($user)
            ->get("/dashboard/projects/{$project->id}/forks/{$fork->id}/preview")
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertHeader('Content-Security-Policy', 'sandbox allow-scripts')
            ->assertSee('fp-fork-preview-stub', false);

        // Owner-only: another user cannot read the fork preview.
        $other = User::factory()->create();

        $this->actingAs($other)
            ->get("/dashboard/projects/{$project->id}/forks/{$fork->id}/preview")
            ->assertForbidden();

        // The rebuilt screenshots are served from the same authorized route family.
        $this->actingAs($user)
            ->get("/dashboard/projects/{$project->id}/forks/{$fork->id}/shots/react-375.png")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_original_component_untouched()
    {
        Queue::fake();
        Storage::fake('previews');
        $this->fakeBuildSteps();

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $component = $this->publish('sections/pricing-01');

        $before = $component->fresh()->toArray();

        $this->actingAs($user)
            ->postJson('/components/pricing/pricing-01/forks', [
                'project_id' => $project->id,
                'framework' => 'react',
                'files' => [['path' => 'sections/pricing-01/index.tsx', 'code' => 'edited']],
            ])
            ->assertAccepted();

        app()->call([(new BuildComponentForkPreview(ComponentFork::query()->sole()->id)), 'handle']);

        // The library component row is byte-identical — no preview paths, no
        // rebuild timestamps, no version bump: forks never write back.
        $this->assertSame($before, $component->fresh()->toArray());

        // The fork's artifact lives under its own fork-scoped path.
        $this->assertTrue(Storage::disk('previews')->exists('forks/'.ComponentFork::query()->sole()->id.'/react.html'));
        $this->assertFalse(Storage::disk('previews')->exists('sections/pricing-01/1.0.0/react.html'));
    }

    public function test_save_requires_ownership_entitlement_and_flag()
    {
        Queue::fake();

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $this->publish('sections/pricing-01');

        $files = [['path' => 'sections/pricing-01/index.tsx', 'code' => 'edited']];

        // Guests: unauthenticated.
        $this->postJson('/components/pricing/pricing-01/forks', [
            'project_id' => $project->id,
            'framework' => 'react',
            'files' => $files,
        ])->assertUnauthorized();

        // Another user's project: forbidden.
        $other = User::factory()->create();

        $this->actingAs($other)
            ->postJson('/components/pricing/pricing-01/forks', [
                'project_id' => $project->id,
                'framework' => 'react',
                'files' => $files,
            ])->assertForbidden();

        // Paid component without a full-library plan: the download gate.
        $this->publish('sections/paid-01', ['access_level' => AccessLevel::Paid]);

        $this->actingAs($user)
            ->postJson('/components/pricing/paid-01/forks', [
                'project_id' => $project->id,
                'framework' => 'react',
                'files' => [['path' => 'sections/paid-01/index.tsx', 'code' => 'edited']],
            ])
            ->assertForbidden()
            ->assertJsonPath('error', 'upgrade_required');

        // Feature flag off: the endpoint 404s.
        app(Settings::class)->set('features.live_edit', false);

        $this->actingAs($user)
            ->postJson('/components/pricing/pricing-01/forks', [
                'project_id' => $project->id,
                'framework' => 'react',
                'files' => $files,
            ])->assertNotFound();

        $this->assertDatabaseCount('component_forks', 0);
        Queue::assertNothingPushed();
    }

    public function test_save_validates_files_and_vue_entry()
    {
        Queue::fake();

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $this->publish('sections/pricing-01');

        // Unsafe paths are rejected (same guard as edit-download).
        $this->actingAs($user)
            ->postJson('/components/pricing/pricing-01/forks', [
                'project_id' => $project->id,
                'framework' => 'react',
                'files' => [['path' => '../evil.tsx', 'code' => 'edited']],
            ])->assertUnprocessable();

        // The react entry source must be among the posted files.
        $this->actingAs($user)
            ->postJson('/components/pricing/pricing-01/forks', [
                'project_id' => $project->id,
                'framework' => 'react',
                'files' => [['path' => 'sections/pricing-01/styles.css', 'code' => 'edited']],
            ])->assertUnprocessable();

        // Vue: entry_file must name one of the posted repl files.
        $this->actingAs($user)
            ->postJson('/components/pricing/pricing-01/forks', [
                'project_id' => $project->id,
                'framework' => 'vue',
                'entry_file' => 'src/Missing01.vue',
                'files' => [['path' => 'src/Pricing01.vue', 'code' => 'edited']],
            ])->assertUnprocessable();

        $this->actingAs($user)
            ->postJson('/components/pricing/pricing-01/forks', [
                'project_id' => $project->id,
                'framework' => 'vue',
                'entry_file' => 'src/Pricing01.vue',
                'files' => [
                    ['path' => 'src/Pricing01.vue', 'code' => '<template><section>EDITED</section></template>'],
                    ['path' => 'src/data.ts', 'code' => 'export default {} as const;'],
                ],
            ])->assertAccepted();

        $fork = ComponentFork::query()->sole();

        $this->assertSame('vue', $fork->framework);
        $this->assertSame('src/Pricing01.vue', $fork->entry_file);

        Queue::assertPushed(BuildComponentForkPreview::class);
    }

    /**
     * Published component (no library tree needed — forks never read one),
     * under the shared `pricing` usage category the routes resolve against.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function publish(string $slug, array $attributes = []): Component
    {
        $usage = Category::query()->where('slug', 'pricing')->first()
            ?? Category::factory()->usage()->create(['slug' => 'pricing']);

        return Component::factory()->published()->free()->create([
            'slug' => $slug,
            'level' => ComponentLevel::fromDirectory(str($slug)->before('/')->toString()),
            'usage_category_id' => $usage->id,
            ...$attributes,
        ]);
    }

    /**
     * Stub the rebuild's node/chrome steps: the builder writes a canned
     * self-contained artifact to the fork's preview path; the screenshotter
     * writes a placeholder png. The job's orchestration runs for real.
     */
    private function fakeBuildSteps(): void
    {
        $this->instance(ForkPreviewBuilder::class, new class extends ForkPreviewBuilder
        {
            public function build(ComponentFork $fork): string
            {
                $path = "forks/{$fork->id}/{$fork->framework}.html";

                Storage::disk('previews')->put($path, '<!doctype html><html><body><div id="root"><!-- fp-fork-preview-stub --></div></body></html>');

                return $path;
            }
        });

        $this->instance(PreviewScreenshotter::class, new class extends PreviewScreenshotter
        {
            public function capture(string $htmlPath, string $outPath, int $width, int $height = 800): void
            {
                File::ensureDirectoryExists(dirname($outPath));
                File::put($outPath, 'png-stub');
            }
        });
    }
}
