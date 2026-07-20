<?php

namespace App\Models;

use App\Enums\AccessLevel;
use App\Enums\ComponentEventType;
use App\Enums\ComponentLevel;
use App\Enums\ComponentStatus;
use Database\Factories\ComponentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Component extends Model
{
    /** @use HasFactory<ComponentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'name',
        'level',
        'usage_category_id',
        'access_level',
        'status',
        'version',
        'source_name',
        'source_url',
        'deps',
        'source_hash',
        'preview_paths',
        'preview_built_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => ComponentLevel::class,
            'access_level' => AccessLevel::class,
            'status' => ComponentStatus::class,
            'deps' => 'array',
            'preview_paths' => 'array',
            'preview_built_at' => 'datetime',
        ];
    }

    public function usageCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'usage_category_id');
    }

    public function industries(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'component_industry');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'component_tag');
    }

    public function children(): BelongsToMany
    {
        return $this->belongsToMany(Component::class, 'component_children', 'parent_id', 'child_id')
            ->withPivot('slot', 'sort_order')
            ->orderBy('component_children.sort_order');
    }

    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(Component::class, 'component_children', 'child_id', 'parent_id')
            ->withPivot('slot', 'sort_order');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ComponentEvent::class);
    }

    public function previewBuildFailures(): HasMany
    {
        return $this->hasMany(PreviewBuildFailure::class);
    }

    public function recordEvent(ComponentEventType $type, ?User $user = null): ComponentEvent
    {
        return $this->events()->create([
            'type' => $type,
            'user_id' => $user?->id,
        ]);
    }

    /**
     * Transitive child closure from the component_children graph (SPEC §2.2),
     * breadth-first, deduplicated. Used by the preview build to verify every
     * descendant's source still exists in the library apps.
     *
     * @return list<int>
     */
    public function descendantIds(): array
    {
        $ids = [];
        $frontier = [$this->id];

        while ($frontier !== []) {
            $children = DB::table('component_children')
                ->whereIn('parent_id', $frontier)
                ->pluck('child_id')
                ->all();

            $children = array_values(array_diff($children, $ids, [$this->id]));
            $ids = [...$ids, ...$children];
            $frontier = $children;
        }

        return $ids;
    }

    /**
     * Disk-relative preview artifact path for one framework
     * (e.g. `elements/section-title-01/1.0.0/react.html`).
     */
    public function previewPath(string $framework): ?string
    {
        $path = $this->preview_paths[$framework] ?? null;

        return is_string($path) ? $path : null;
    }

    /**
     * QA gate (SPEC §5.2, §8.5): publishable only when both-framework
     * previews exist AND 3-width screenshots exist for both frameworks.
     */
    public function canPublish(): bool
    {
        $disk = Storage::disk((string) config('library.preview_disk', 'previews'));
        $widths = (array) config('library.screenshot_widths', [375, 768, 1280]);

        foreach (['react', 'vue'] as $framework) {
            $path = $this->previewPath($framework);

            if ($path === null || ! $disk->exists($path)) {
                return false;
            }

            foreach ($widths as $width) {
                if (! $disk->exists(dirname($path)."/shots/{$framework}-{$width}.png")) {
                    return false;
                }
            }
        }

        return true;
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', ComponentStatus::Published);
    }

    public function scopeFree(Builder $query): void
    {
        $query->where('access_level', AccessLevel::Free);
    }
}
