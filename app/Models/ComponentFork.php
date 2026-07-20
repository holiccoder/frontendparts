<?php

namespace App\Models;

use App\Enums\ComponentForkStatus;
use Database\Factories\ComponentForkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customized fork of a library component, saved from the live-edit tab
 * into one of the user's projects (SPEC §5.6 Save to Project). `files` is
 * the edited source map (path → code, framework layout — library-relative
 * for react, flat `src/` repl layout for vue; `entry_file` names the vue
 * entry SFC). The original library component is never modified; a queued
 * rebuild (BuildComponentForkPreview) produces the fork's own prebuilt
 * preview + screenshots under `forks/{id}/` on the preview disk, tracked by
 * the pending → building → ready/failed status the project page polls.
 */
class ComponentFork extends Model
{
    /** @use HasFactory<ComponentForkFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'component_id',
        'framework',
        'entry_file',
        'files',
        'status',
        'error',
        'preview_paths',
        'preview_built_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ComponentForkStatus::class,
            'files' => 'array',
            'preview_paths' => 'array',
            'preview_built_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    /**
     * Disk-relative preview artifact path (e.g. `forks/12/react.html`).
     */
    public function previewPath(): ?string
    {
        $path = $this->preview_paths[$this->framework] ?? null;

        return is_string($path) ? $path : null;
    }

    /**
     * Authorized preview URL once the queued rebuild is ready; null while
     * pending/building or after a failed rebuild.
     */
    public function previewUrl(): ?string
    {
        if ($this->status !== ComponentForkStatus::Ready || $this->previewPath() === null) {
            return null;
        }

        return route('dashboard.projects.forks.preview', [$this->project_id, $this->id]);
    }
}
