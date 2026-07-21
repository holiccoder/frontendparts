<?php

namespace App\Models;

use Database\Factories\BlogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravel\Scout\Searchable;

class Blog extends Model
{
    /** @use HasFactory<BlogFactory> */
    use HasFactory, Searchable;

    /**
     * Words-per-minute used for the reading-time estimate (SPEC §13.1).
     */
    public const READING_WORDS_PER_MINUTE = 200;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'excerpt',
        'body',
        'featured_image',
        'status',
        'published_at',
        'meta_title',
        'meta_description',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'reading_time',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(BlogCategory::class, 'blog_category');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(BlogTag::class, 'blog_tag');
    }

    /**
     * Catalog components cross-linked from the article (SPEC §13.1 — the
     * core SEO interlinking mechanic between blog and catalog).
     */
    public function relatedComponents(): BelongsToMany
    {
        return $this->belongsToMany(Component::class, 'blog_component');
    }

    /**
     * Reading-time estimate in whole minutes, derived from the body so it
     * can never drift out of sync with the content (SPEC §13.1).
     */
    protected function readingTime(): Attribute
    {
        return Attribute::get(function (): int {
            $words = str_word_count(strip_tags((string) $this->body));

            return max(1, (int) ceil($words / self::READING_WORDS_PER_MINUTE));
        });
    }

    /**
     * Canonical public URL `/blog/{slug}`.
     */
    public function publicUrl(): string
    {
        return route('blog.show', ['slug' => $this->slug]);
    }

    /**
     * Publicly visible posts (SPEC §13.1): the published status flag plus a
     * publication timestamp that has actually passed — a future
     * `published_at` is a scheduled post and stays hidden until then.
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * The searchable payload (SPEC §15.1, FR-1.3): the same fields the
     * launch LIKE search matched — title, excerpt and body.
     *
     * @return array<string, string|null>
     */
    public function toSearchableArray(): array
    {
        return [
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'body' => $this->body,
        ];
    }

    /**
     * Only live posts are searchable (mirrors scopePublished): drafts,
     * archived and scheduled posts never enter the Meilisearch index, and
     * the collection engine filters them out at query time.
     */
    public function shouldBeSearchable(): bool
    {
        return $this->status === 'published'
            && $this->published_at !== null
            && $this->published_at->lessThanOrEqualTo(now());
    }
}
