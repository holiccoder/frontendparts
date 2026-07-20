<?php

namespace App\Models;

use App\Enums\CategoryType;
use App\Enums\ComponentStatus;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'zone',
        'name',
        'slug',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CategoryType::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * Components carrying this category as an industry tag.
     */
    public function components(): BelongsToMany
    {
        return $this->belongsToMany(Component::class, 'component_industry');
    }

    /**
     * Components using this category as their usage pattern.
     */
    public function usageComponents(): HasMany
    {
        return $this->hasMany(Component::class, 'usage_category_id');
    }

    /**
     * Categories shown in the UI once they hold at least 3 published
     * components (SPEC §4.3), via either the industry pivot or the
     * usage_category foreign key.
     */
    public function scopeVisible(Builder $query): void
    {
        $published = function (Builder $query): void {
            $query->where('status', ComponentStatus::Published);
        };

        $query->where(function (Builder $query) use ($published): void {
            $query->whereHas('components', $published, '>=', 3)
                ->orWhereHas('usageComponents', $published, '>=', 3);
        });
    }
}
