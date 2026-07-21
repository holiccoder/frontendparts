<?php

namespace App\Models;

use Database\Factories\CollectionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Curated component bundle (SPEC §15.1 — e.g. "restaurant landing kit").
 * Publicly visible once `status` is `published`; drafts 404 and stay out
 * of the sitemap. Bundle members ride the `collection_component` pivot
 * and render in pivot `sort_order`.
 */
class Collection extends Model
{
    /** @use HasFactory<CollectionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'cover',
        'status',
        'sort_order',
        'meta_title',
        'meta_description',
    ];

    /**
     * Bundle members in curated (pivot) order — the order the public
     * bundle page renders the grid.
     */
    public function components(): BelongsToMany
    {
        return $this->belongsToMany(Component::class, 'collection_component')
            ->withPivot('sort_order')
            ->orderBy('collection_component.sort_order');
    }

    /**
     * Pivot rows as a HasMany so the admin form can pick and drag-order
     * components through a repeater (Filament requires a pivot model
     * with an incrementing key for that).
     */
    public function collectionComponents(): HasMany
    {
        return $this->hasMany(CollectionComponent::class);
    }

    /**
     * Canonical public URL `/collections/{slug}`.
     */
    public function publicUrl(): string
    {
        return route('collections.show', ['slug' => $this->slug]);
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published');
    }
}
