<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * One membership row of a curated bundle. Used as a HasMany target by
 * the admin repeater, so it needs an incrementing key and no timestamps
 * (the `collection_component` table has neither).
 */
class CollectionComponent extends Pivot
{
    public $incrementing = true;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'collection_id',
        'component_id',
        'sort_order',
    ];

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }
}
