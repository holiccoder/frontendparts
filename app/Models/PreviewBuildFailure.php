<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One recorded preview-pipeline failure per component + framework, surfaced
 * by the admin system-health widget (SPEC §8.6). Rows are cleared when the
 * component builds (or screenshots) successfully again.
 */
class PreviewBuildFailure extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'component_id',
        'framework',
        'error',
    ];

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }
}
