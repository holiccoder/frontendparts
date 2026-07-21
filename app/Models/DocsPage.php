<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

/**
 * Search projection of one file-based docs page (SPEC §13.2 — "search
 * (basic at launch → Meilisearch at P3)"). Markdown stays the source of
 * truth in `docs/content/`; DocsRepository syncs rows from disk before
 * searching, so the configured Scout engine (collection locally,
 * Meilisearch in production) serves docs search from this table.
 */
class DocsPage extends Model
{
    use Searchable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'section',
        'page',
        'title',
        'description',
        'body',
    ];

    /**
     * The searchable payload: plain-text page fields. Matching against
     * section/page keys keeps queries like "api" useful even when the word
     * never appears in prose.
     *
     * @return array<string, string>
     */
    public function toSearchableArray(): array
    {
        return [
            'section' => $this->section,
            'page' => $this->page,
            'title' => $this->title,
            'description' => $this->description,
            'body' => $this->body,
        ];
    }

    /**
     * Canonical public URL `/docs/{section}/{page}`.
     */
    public function publicUrl(): string
    {
        return route('docs.show', ['section' => $this->section, 'page' => $this->page]);
    }
}
