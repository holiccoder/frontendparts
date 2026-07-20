<?php

namespace Tests\Feature\Admin;

use App\Enums\ComponentStatus;
use App\Filament\Widgets\CatalogStatsWidget;
use App\Filament\Widgets\CoverageMatrixWidget;
use App\Filament\Widgets\DraftsReviewWidget;
use App\Filament\Widgets\SystemHealthWidget;
use App\Jobs\BuildComponentPreview;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Component;
use App\Models\LibrarySyncRun;
use App\Models\PreviewBuildFailure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_stats_counts()
    {
        $admin = Admin::factory()->create();

        Component::factory()->count(2)->published()->free()->create();
        Component::factory()->published()->free()->create([
            'created_at' => now()->subDays(10),
        ]);
        Component::factory()->count(2)->inReview()->paid()->create();
        Component::factory()->draft()->paid()->create();

        LibrarySyncRun::query()->create([
            'scanned' => 7,
            'upserted' => 5,
            'errors' => [],
        ]);

        $this->actingAs($admin, 'admin');

        Livewire::test(CatalogStatsWidget::class)
            ->assertSee('Published components')
            ->assertSee('+2 this week')
            ->assertSee('Awaiting review')
            ->assertSee('Needs attention')
            ->assertSee('3 / 3')
            ->assertSee('Scanned 7');
    }

    public function test_drafts_queue_lists_in_review()
    {
        $admin = Admin::factory()->create();

        $first = Component::factory()->inReview()->create(['name' => 'First in queue']);
        $second = Component::factory()->inReview()->create(['name' => 'Second in queue']);
        $draft = Component::factory()->draft()->create(['name' => 'Still a draft']);
        $published = Component::factory()->published()->create(['name' => 'Already live']);

        $this->actingAs($admin, 'admin');

        Livewire::test(DraftsReviewWidget::class)
            ->assertCanSeeTableRecords([$first, $second])
            ->assertCanNotSeeTableRecords([$draft, $published]);

        // Inline Reject reuses the resource workflow action.
        Livewire::test(DraftsReviewWidget::class)
            ->callTableAction('reject', $first, data: ['reason' => 'Needs responsive fixes.'])
            ->assertHasNoTableActionErrors();

        $first->refresh();

        $this->assertSame(ComponentStatus::Draft, $first->status);
        $this->assertSame('Needs responsive fixes.', $first->review_note);
    }

    public function test_coverage_matrix_flags_cells_below_3()
    {
        $admin = Admin::factory()->create();

        $industry = Category::factory()->industry()->create(['name' => 'SaaS & Software']);
        $thinUsage = Category::factory()->usage()->create(['name' => 'Navbar']);
        $coveredUsage = Category::factory()->usage()->create(['name' => 'Hero']);

        $thin = Component::factory()->count(2)->published()->create([
            'usage_category_id' => $thinUsage->id,
        ]);
        $covered = Component::factory()->count(6)->published()->create([
            'usage_category_id' => $coveredUsage->id,
        ]);
        // Drafts must not count toward coverage.
        $drafts = Component::factory()->count(4)->draft()->create([
            'usage_category_id' => $thinUsage->id,
        ]);

        foreach ([...$thin, ...$covered, ...$drafts] as $component) {
            $component->industries()->attach($industry->id);
        }

        $this->actingAs($admin, 'admin');

        $matrix = Livewire::test(CoverageMatrixWidget::class)->instance()->matrixData();

        $this->assertSame(2, $matrix['cells'][$industry->id][$thinUsage->id]);
        $this->assertSame(6, $matrix['cells'][$industry->id][$coveredUsage->id]);

        $this->assertSame('critical', CoverageMatrixWidget::cellTone(2), 'cells below 3 must be flagged');
        $this->assertSame('warning', CoverageMatrixWidget::cellTone(4));
        $this->assertSame('ok', CoverageMatrixWidget::cellTone(6));

        $this->assertSame(1, $matrix['covered'], 'only the 6-count cell is covered');
        $this->assertSame(2, $matrix['total']);
    }

    public function test_system_health_shows_failed_builds_and_last_sync()
    {
        Bus::fake();

        $admin = Admin::factory()->create();

        $component = Component::factory()->create(['slug' => 'sections/pricing-section-01']);

        $failure = PreviewBuildFailure::query()->create([
            'component_id' => $component->id,
            'framework' => 'react',
            'error' => 'Vite exploded while bundling pricing-section-01',
        ]);

        LibrarySyncRun::query()->create([
            'scanned' => 11,
            'upserted' => 9,
            'errors' => ['elements/button-01' => ['Missing vue twin']],
        ]);

        $this->actingAs($admin, 'admin');

        Livewire::test(SystemHealthWidget::class)
            ->assertSee('sections/pricing-section-01')
            ->assertSee('Vite exploded while bundling pricing-section-01')
            ->assertSee('11')
            ->assertSee('9');

        // Retry re-dispatches the build job for the failed framework.
        Livewire::test(SystemHealthWidget::class)
            ->call('retryBuild', $failure->id);

        Bus::assertDispatched(
            BuildComponentPreview::class,
            fn (BuildComponentPreview $job): bool => $job->componentId === $component->id
                && $job->frameworks === ['react'],
        );
    }
}
