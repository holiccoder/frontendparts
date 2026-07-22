<?php

namespace Tests\Feature\Security;

use App\Enums\ComponentForkStatus;
use App\Models\Component;
use App\Models\ComponentFork;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SandboxAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_iframe_markup_has_no_allow_same_origin(): void
    {
        Storage::fake('previews');

        $component = $this->publishedComponent();

        Storage::disk('previews')->put('elements/demo-01/1.0.0/react.html', '<html><body>preview</body></html>');

        $response = $this->get('/previews/elements/demo-01/1.0.0/react.html');

        $response->assertOk();

        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('sandbox allow-scripts', $csp);
        $this->assertStringNotContainsString('allow-same-origin', $csp);
    }

    public function test_fork_preview_iframe_markup_has_no_allow_same_origin(): void
    {
        Storage::fake('previews');

        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $fork = ComponentFork::factory()->create([
            'project_id' => $project->id,
            'framework' => 'react',
            'status' => ComponentForkStatus::Ready,
            'preview_paths' => ['react' => 'forks/1/react.html'],
        ]);

        Storage::disk('previews')->put('forks/1/react.html', '<html><body>fork preview</body></html>');

        $this->actingAs($user);

        $response = $this->get("/dashboard/projects/{$project->id}/forks/{$fork->id}/preview");

        $response->assertOk();

        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('sandbox allow-scripts', $csp);
        $this->assertStringNotContainsString('allow-same-origin', $csp);
    }

    private function publishedComponent(): Component
    {
        return Component::factory()->published()->create([
            'slug' => 'elements/demo-01',
            'version' => '1.0.0',
            'preview_paths' => [
                'react' => 'elements/demo-01/1.0.0/react.html',
                'vue' => 'elements/demo-01/1.0.0/vue.html',
            ],
        ]);
    }
}
