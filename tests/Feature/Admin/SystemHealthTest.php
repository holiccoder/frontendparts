<?php

namespace Tests\Feature\Admin;

use App\Filament\Widgets\SystemHealthWidget;
use App\Models\Admin;
use App\Models\Component;
use App\Models\LibrarySyncRun;
use App\Models\PreviewBuildFailure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class SystemHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_jobs_visible_in_widget(): void
    {
        $admin = Admin::factory()->create();

        DB::table('failed_jobs')->insert([
            'uuid' => 'job-uuid-1',
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'RuntimeException: Something went wrong',
            'failed_at' => now(),
        ]);

        $this->actingAs($admin, 'admin');

        Livewire::test(SystemHealthWidget::class)
            ->assertSee('1')
            ->assertSee('Needs attention')
            ->assertSee('RuntimeException: Something went wrong');
    }

    public function test_failed_builds_visible_in_widget(): void
    {
        $admin = Admin::factory()->create();
        $component = Component::factory()->create(['slug' => 'sections/pricing-section-01']);

        PreviewBuildFailure::query()->create([
            'component_id' => $component->id,
            'framework' => 'react',
            'error' => 'Vite exploded while bundling pricing-section-01',
        ]);

        $this->actingAs($admin, 'admin');

        Livewire::test(SystemHealthWidget::class)
            ->assertSee('sections/pricing-section-01')
            ->assertSee('Vite exploded while bundling pricing-section-01');
    }

    public function test_last_sync_visible_in_widget(): void
    {
        $admin = Admin::factory()->create();

        LibrarySyncRun::query()->create([
            'scanned' => 11,
            'upserted' => 9,
            'errors' => ['elements/button-01' => ['Missing vue twin']],
        ]);

        $this->actingAs($admin, 'admin');

        Livewire::test(SystemHealthWidget::class)
            ->assertSee('11')
            ->assertSee('9')
            ->assertSee('1');
    }
}
