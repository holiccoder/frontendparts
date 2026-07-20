<?php

namespace Tests\Feature\Library\Concerns;

use App\Enums\CategoryType;
use App\Jobs\BuildComponentPreview;
use App\Models\Category;
use App\Models\Component;
use App\Services\Library\LibrarySync;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Process\Process;

/**
 * Runs the REAL preview pipeline against the REAL component library
 * (library/react + library/vue). Skips gracefully when npm is not
 * resolvable from PHP (LIBRARY_NPM_BINARY).
 */
trait RunsRealPreviewBuilds
{
    protected function skipUnlessNpmAvailable(): void
    {
        $npm = (string) config('library.npm_binary', 'npm');

        $process = new Process([$npm, '--version'], null, null, null, 20);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->markTestSkipped('npm is not resolvable from PHP (set LIBRARY_NPM_BINARY).');
        }
    }

    /**
     * Import the real library into the test database without letting the
     * sync-dispatched preview jobs execute (they are faked; builds are run
     * explicitly per test).
     */
    protected function syncRealLibrary(): void
    {
        Category::query()->firstOrCreate(
            ['type' => CategoryType::Usage->value, 'slug' => 'feature-grid'],
            ['name' => 'Feature Grid', 'zone' => 'Content', 'sort_order' => 0],
        );

        Queue::fake();

        $result = app(LibrarySync::class)->run();

        $this->assertFalse($result->hasErrors(), 'library:sync failed: '.json_encode($result->failures()));

        Queue::assertPushed(BuildComponentPreview::class);
    }

    protected function componentBySlug(string $slug): Component
    {
        return Component::query()->where('slug', $slug)->sole();
    }

    /**
     * Run the build job synchronously (inner screenshot dispatch stays faked).
     *
     * @param  list<string>  $frameworks
     */
    protected function runBuildJob(Component $component, array $frameworks = ['react', 'vue']): void
    {
        app()->call([(new BuildComponentPreview($component->id, $frameworks)), 'handle']);
    }
}
