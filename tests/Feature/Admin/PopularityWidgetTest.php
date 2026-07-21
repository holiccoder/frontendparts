<?php

namespace Tests\Feature\Admin;

use App\Enums\ComponentEventType;
use App\Enums\ProjectExportKind;
use App\Filament\Widgets\PopularityStatsWidget;
use App\Filament\Widgets\TopComponentsWidget;
use App\Models\Admin;
use App\Models\Component;
use App\Models\ComponentEvent;
use App\Models\Project;
use App\Models\ProjectExport;
use App\Services\Admin\PopularityStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * P2 downloads & popularity widgets (SPEC §8.6 rows 1 + 5): the counting
 * rules are tested against PopularityStats directly; each widget then
 * proves it renders the service data.
 */
class PopularityWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_downloads_30d_aggregation()
    {
        $this->travelTo(Carbon::parse('2026-07-15 12:00:00'));

        $component = Component::factory()->create();

        // Inside the window: every take-away kind counts.
        ComponentEvent::factory()->count(2)->create([
            'component_id' => $component->id,
            'type' => ComponentEventType::Copy,
            'created_at' => now()->subDays(10),
        ]);
        ComponentEvent::factory()->count(2)->create([
            'component_id' => $component->id,
            'type' => ComponentEventType::Download,
            'created_at' => now()->subDays(5),
        ]);
        // The window edge is inclusive.
        ComponentEvent::factory()->create([
            'component_id' => $component->id,
            'type' => ComponentEventType::Download,
            'created_at' => now()->subDays(30),
        ]);
        // Scaffold exports count as their own kind (SPEC §8.6 "+ scaffolds").
        ComponentEvent::factory()->create([
            'component_id' => $component->id,
            'type' => ComponentEventType::Scaffold,
            'created_at' => now()->subDays(3),
        ]);

        // Views and gate hits are not downloads.
        ComponentEvent::factory()->create([
            'component_id' => $component->id,
            'type' => ComponentEventType::View,
            'created_at' => now()->subDays(2),
        ]);
        ComponentEvent::factory()->create([
            'component_id' => $component->id,
            'type' => ComponentEventType::GateHit,
            'created_at' => now()->subDays(2),
        ]);

        // Outside the 30-day window: excluded regardless of kind.
        foreach ([ComponentEventType::Copy, ComponentEventType::Download, ComponentEventType::Scaffold] as $type) {
            ComponentEvent::factory()->create([
                'component_id' => $component->id,
                'type' => $type,
                'created_at' => now()->subDays(31),
            ]);
        }

        $downloads = app(PopularityStats::class)->downloads30d();

        $this->assertSame(2, $downloads['copies']);
        $this->assertSame(3, $downloads['zips']);
        $this->assertSame(1, $downloads['scaffolds']);
        $this->assertSame(6, $downloads['total']);
    }

    public function test_top_components_from_component_events()
    {
        $this->travelTo(Carbon::parse('2026-07-15 12:00:00'));

        $alpha = Component::factory()->create(['name' => 'Alpha']);
        $beta = Component::factory()->create(['name' => 'Beta']);
        $gamma = Component::factory()->create(['name' => 'Gamma']);
        $quiet = Component::factory()->create(['name' => 'Quiet']);
        $stale = Component::factory()->create(['name' => 'Stale']);

        // Beta: 6 views, no downloads (6 activity).
        ComponentEvent::factory()->count(6)->create([
            'component_id' => $beta->id,
            'type' => ComponentEventType::View,
            'created_at' => now()->subDays(4),
        ]);

        // Alpha: 3 views + 2 zip downloads (5 activity).
        ComponentEvent::factory()->count(3)->create([
            'component_id' => $alpha->id,
            'type' => ComponentEventType::View,
            'created_at' => now()->subDays(4),
        ]);
        ComponentEvent::factory()->count(2)->create([
            'component_id' => $alpha->id,
            'type' => ComponentEventType::Download,
            'created_at' => now()->subDays(4),
        ]);

        // Gamma: 1 view + 4 downloads across the take-away kinds (5 activity).
        ComponentEvent::factory()->create([
            'component_id' => $gamma->id,
            'type' => ComponentEventType::View,
            'created_at' => now()->subDays(4),
        ]);
        ComponentEvent::factory()->count(2)->create([
            'component_id' => $gamma->id,
            'type' => ComponentEventType::Download,
            'created_at' => now()->subDays(4),
        ]);
        ComponentEvent::factory()->create([
            'component_id' => $gamma->id,
            'type' => ComponentEventType::Copy,
            'created_at' => now()->subDays(4),
        ]);
        ComponentEvent::factory()->create([
            'component_id' => $gamma->id,
            'type' => ComponentEventType::Scaffold,
            'created_at' => now()->subDays(4),
        ]);

        // Gate hits never count toward popularity.
        ComponentEvent::factory()->count(10)->create([
            'component_id' => $quiet->id,
            'type' => ComponentEventType::GateHit,
            'created_at' => now()->subDays(4),
        ]);

        // Stale: busy, but everything is outside the window.
        ComponentEvent::factory()->count(9)->create([
            'component_id' => $stale->id,
            'type' => ComponentEventType::View,
            'created_at' => now()->subDays(40),
        ]);
        ComponentEvent::factory()->count(9)->create([
            'component_id' => $stale->id,
            'type' => ComponentEventType::Download,
            'created_at' => now()->subDays(40),
        ]);

        $top = app(PopularityStats::class)->topComponents();

        // Beta leads on activity; Alpha and Gamma tie at 5, so the view
        // count breaks the tie (3 > 1). Quiet and Stale have no in-window
        // activity and drop out entirely.
        $this->assertSame([$beta->id, $alpha->id, $gamma->id], $top->pluck('id')->all());

        $this->assertSame(6, (int) $top[0]->views_30d);
        $this->assertSame(0, (int) $top[0]->downloads_30d);
        $this->assertSame(3, (int) $top[1]->views_30d);
        $this->assertSame(2, (int) $top[1]->downloads_30d);
        $this->assertSame(1, (int) $top[2]->views_30d);
        $this->assertSame(4, (int) $top[2]->downloads_30d);
    }

    public function test_project_tracking_counts_projects_and_exports()
    {
        $this->travelTo(Carbon::parse('2026-07-15 12:00:00'));

        $recent = Project::factory()->count(2)->create(['created_at' => now()->subDays(5)]);
        Project::factory()->create(['created_at' => now()->subDays(40)]);

        $project = $recent->first();

        ProjectExport::factory()->count(2)->create([
            'project_id' => $project->id,
            'kind' => ProjectExportKind::Pack,
            'created_at' => now()->subDays(2),
        ]);
        ProjectExport::factory()->scaffold()->create([
            'project_id' => $project->id,
            'created_at' => now()->subDays(2),
        ]);
        // An old export falls out of the 30-day window.
        ProjectExport::factory()->create([
            'project_id' => $project->id,
            'kind' => ProjectExportKind::Pack,
            'created_at' => now()->subDays(45),
        ]);

        $tracking = app(PopularityStats::class)->projectTracking();

        $this->assertSame(3, $tracking['projects_total']);
        $this->assertSame(2, $tracking['projects_30d']);
        $this->assertSame(3, $tracking['exports_30d']);
        $this->assertSame(2, $tracking['packs_30d']);
        $this->assertSame(1, $tracking['scaffolds_30d']);
    }

    public function test_popularity_stats_widget_renders_kpis()
    {
        $this->travelTo(Carbon::parse('2026-07-15 12:00:00'));
        $admin = Admin::factory()->create();

        $component = Component::factory()->create();
        ComponentEvent::factory()->create([
            'component_id' => $component->id,
            'type' => ComponentEventType::Copy,
            'created_at' => now()->subDay(),
        ]);
        ComponentEvent::factory()->create([
            'component_id' => $component->id,
            'type' => ComponentEventType::Download,
            'created_at' => now()->subDay(),
        ]);
        ComponentEvent::factory()->create([
            'component_id' => $component->id,
            'type' => ComponentEventType::Scaffold,
            'created_at' => now()->subDay(),
        ]);

        $project = Project::factory()->create(['created_at' => now()->subDays(3)]);
        ProjectExport::factory()->scaffold()->create([
            'project_id' => $project->id,
            'created_at' => now()->subDays(3),
        ]);

        $this->actingAs($admin, 'admin');

        Livewire::test(PopularityStatsWidget::class)
            ->assertSee('Downloads · 30d')
            ->assertSee('1 copies · 1 zips · 1 scaffolds')
            ->assertSee('Projects')
            ->assertSee('+1 last 30 days')
            ->assertSee('Project exports · 30d')
            ->assertSee('0 packs · 1 scaffolds');
    }

    public function test_top_components_widget_lists_ranked_components()
    {
        $this->travelTo(Carbon::parse('2026-07-15 12:00:00'));
        $admin = Admin::factory()->create();

        $alpha = Component::factory()->create(['name' => 'Alpha table']);
        $beta = Component::factory()->create(['name' => 'Beta table']);
        $inactive = Component::factory()->create(['name' => 'Invisible table']);

        // Alpha: 3 activity (2 views + 1 download); Beta: 5 views. Beta wins.
        ComponentEvent::factory()->count(2)->create([
            'component_id' => $alpha->id,
            'type' => ComponentEventType::View,
            'created_at' => now()->subDays(2),
        ]);
        ComponentEvent::factory()->create([
            'component_id' => $alpha->id,
            'type' => ComponentEventType::Download,
            'created_at' => now()->subDays(2),
        ]);
        ComponentEvent::factory()->count(5)->create([
            'component_id' => $beta->id,
            'type' => ComponentEventType::View,
            'created_at' => now()->subDays(2),
        ]);

        $this->actingAs($admin, 'admin');

        Livewire::test(TopComponentsWidget::class)
            ->assertSee('Top components · last 30 days')
            ->assertCanSeeTableRecords([$beta, $alpha], inOrder: true)
            ->assertCanNotSeeTableRecords([$inactive]);
    }
}
