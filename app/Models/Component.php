<?php

namespace App\Models;

use App\Enums\AccessLevel;
use App\Enums\ComponentEventType;
use App\Enums\ComponentLevel;
use App\Enums\ComponentStatus;
use Database\Factories\ComponentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Scout\Searchable;

class Component extends Model
{
    /** @use HasFactory<ComponentFactory> */
    use HasFactory, Searchable;

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
        'qa_checklist',
        'review_note',
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
            'qa_checklist' => 'array',
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
     * Public URL segment: URLs use the basename of the stored full slug
     * (`elements/section-title-01` → `section-title-01`).
     */
    protected function basename(): Attribute
    {
        return Attribute::get(fn (): string => str($this->slug)->afterLast('/')->toString());
    }

    /**
     * Canonical public URL `/components/{usage}/{basename}`.
     */
    public function publicUrl(): string
    {
        return route('components.show', [
            'usage' => $this->usageCategory->slug,
            'slug' => $this->basename,
        ]);
    }

    /**
     * Public iframe URL for one framework's prebuilt preview; null when the
     * preview was never built (fail-soft — SPEC §5.3, §15.6).
     */
    public function previewUrl(string $framework): ?string
    {
        if ($this->previewPath($framework) === null) {
            return null;
        }

        return route('previews.show', [
            'component' => $this->slug,
            'version' => $this->version,
            'framework' => $framework,
        ]);
    }

    /**
     * Public URL for one viewport screenshot when the shot exists on the
     * preview disk; null when previews were never built (fail-soft).
     */
    public function screenshotUrl(string $framework, int $width): ?string
    {
        $path = $this->previewPath($framework);

        if ($path === null) {
            return null;
        }

        $file = "{$framework}-{$width}.png";
        $disk = Storage::disk((string) config('library.preview_disk', 'previews'));

        if (! $disk->exists(dirname($path)."/shots/{$file}")) {
            return null;
        }

        return route('previews.shots', [
            'component' => $this->slug,
            'version' => $this->version,
            'file' => $file,
        ]);
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

    /**
     * The searchable payload (SPEC §15.1, FR-1.3): name + slug plus the
     * flattened relation names the launch LIKE search matched — tags, usage
     * category and industry categories. Relation names ride along so the
     * same document serves Meilisearch in production.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'tags' => $this->tags->pluck('name')->all(),
            'usage_category' => $this->usageCategory?->name,
            'industries' => $this->industries->pluck('name')->all(),
        ];
    }

    /**
     * Only published components are searchable (mirrors scopePublished):
     * drafts and in-review components never enter the Meilisearch index,
     * and the collection engine filters them out at query time.
     */
    public function shouldBeSearchable(): bool
    {
        return $this->status === ComponentStatus::Published;
    }

    /**
     * Eager-load the relations the searchable payload reads, so collection
     * filtering and Meilisearch imports don't N+1.
     *
     * @param  EloquentCollection<int, static>  $models
     * @return EloquentCollection<int, static>
     */
    public function makeSearchableUsing(EloquentCollection $models): EloquentCollection
    {
        return $models->load(['tags', 'usageCategory', 'industries']);
    }
}
