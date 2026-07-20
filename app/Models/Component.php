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

    public function recordEvent(ComponentEventType $type, ?User $user = null): ComponentEvent
    {
        return $this->events()->create([
            'type' => $type,
            'user_id' => $user?->id,
        ]);
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
