<?php

namespace App\Models;

use App\Enums\ProjectExportStatus;
use Database\Factories\ProjectExportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One pack zip export of a project (SPEC §6.2): queued on POST export,
 * assembled by BuildProjectPackZip onto the `exports` disk (NFR-4 queued
 * heavy work), then streamed from the authorized download route. `path` is
 * the disk-relative zip location once status is `ready`.
 */
class ProjectExport extends Model
{
    /** @use HasFactory<ProjectExportFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'framework',
        'status',
        'path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectExportStatus::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Download URL once the queued build has stored the zip; null while
     * pending or after a failed build.
     */
    public function downloadUrl(): ?string
    {
        if ($this->status !== ProjectExportStatus::Ready || $this->path === null) {
            return null;
        }

        return route('dashboard.projects.export.download', [$this->project_id, $this->id]);
    }
}
