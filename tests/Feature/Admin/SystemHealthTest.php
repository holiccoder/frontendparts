<?php

namespace Tests\Feature\Admin;

use App\Filament\Widgets\SystemHealthWidget;
use App\Models\Admin;
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
}
