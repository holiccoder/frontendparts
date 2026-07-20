<?php

namespace App\Models;

use Database\Factories\BlogTagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Blog-only tag (SPEC §13.1), separate from the component `tags` table so
 * editorial tags never leak into the catalog's component-tag pool.
 */
class BlogTag extends Model
{
    /** @use HasFactory<BlogTagFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
    ];

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Blog::class, 'blog_tag');
    }
}
