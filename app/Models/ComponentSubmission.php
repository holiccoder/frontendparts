<?php

namespace App\Models;

use App\Enums\ComponentLevel;
use App\Enums\SubmissionFramework;
use App\Enums\SubmissionStatus;
use Database\Factories\ComponentSubmissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Community component submission (task 5.3, PRD §4.2 P3): a single-file
 * component paste (React and/or Vue) plus sample data and the real-world
 * citation link, sent in by a logged-in user. Admins review in Filament;
 * approval creates an in-review component credited to the submitter and
 * lands the source in the library tree (see SubmissionApprover), rejection
 * stores a review note. Status flow: pending → approved | rejected.
 */
class ComponentSubmission extends Model
{
    /** @use HasFactory<ComponentSubmissionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'level',
        'usage_category_id',
        'framework',
        'description',
        'react_code',
        'vue_code',
        'sample_data',
        'source_url',
        'status',
        'review_note',
        'component_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => ComponentLevel::class,
            'framework' => SubmissionFramework::class,
            'status' => SubmissionStatus::class,
            'sample_data' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function usageCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'usage_category_id');
    }

    /**
     * The library component created on approval.
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }
}
