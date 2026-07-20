<?php

namespace App\Models;

use Database\Factories\BlogCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Blog-only category (SPEC §13.1). Kept separate from the catalog
 * `categories` table on purpose: that table carries component taxonomy
 * semantics (industry/usage type, zone, the ≥3-published visibility rule
 * from SPEC §4.3) that do not apply to editorial content.
 */
class BlogCategory extends Model
{
    /** @use HasFactory<BlogCategoryFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Blog::class, 'blog_category');
    }
}
