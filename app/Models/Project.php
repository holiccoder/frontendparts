<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A user's project: a framework-agnostic set of catalog components
 * (SPEC §6.1). Direct picks (`is_dependency = false`) are what the user
 * added; dependency rows come from the auto-added descendant closure and are
 * pruned when no remaining direct pick needs them. React/Vue + Next/Nuxt are
 * chosen at export time, not on the project.
 */
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Pack zip exports of this project (SPEC §6.2), newest first.
     */
    public function exports(): HasMany
    {
        return $this->hasMany(ProjectExport::class);
    }

    /**
     * Customized component forks saved from live edit (SPEC §5.6).
     */
    public function forks(): HasMany
    {
        return $this->hasMany(ComponentFork::class);
    }

    public function components(): BelongsToMany
    {
        return $this->belongsToMany(Component::class, 'project_components')
            ->withPivot('is_dependency')
            ->withTimestamps();
    }

    /**
     * Components the user picked directly (not auto-added closure members).
     */
    public function directComponents(): BelongsToMany
    {
        return $this->components()->wherePivot('is_dependency', false);
    }

    /**
     * Auto-added descendant-closure members (SPEC §6.1 auto-add closure).
     */
    public function dependencyComponents(): BelongsToMany
    {
        return $this->components()->wherePivot('is_dependency', true);
    }
}
