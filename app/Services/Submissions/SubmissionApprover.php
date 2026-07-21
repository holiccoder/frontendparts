<?php

namespace App\Services\Submissions;

use App\Enums\AccessLevel;
use App\Enums\ComponentStatus;
use App\Enums\SubmissionStatus;
use App\Models\Component;
use App\Models\ComponentSubmission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Approval pipeline for community submissions (task 5.3, PRD §4.2 P3).
 *
 * Approving a submission does two things:
 *
 * 1. Writes the submitted source into the library tree in the exact layout
 *    ComponentScanner discovers — `{level-dir}/{slug}/index.tsx|index.vue`
 *    plus `params.json` and `data.json` — with a generated annotation
 *    docblock carrying the submission metadata. The params schema is
 *    inferred from the sample data so the tree stays sync-valid.
 * 2. Creates the `components` row immediately (status in_review, access
 *    free, source_name credited to the submitter, citation URL carried
 *    over) so it shows in the review queue before the next sync.
 *
 * A follow-up `library:sync` run then owns the component: it validates
 * (twin presence, taxonomy, JSON), re-upserts the same row by slug and
 * queues preview builds; publishing goes through the normal QA gate. A
 * single-framework submission deliberately surfaces the "missing twin"
 * sync error — the dual-framework rule still applies before publishing.
 */
class SubmissionApprover
{
    /**
     * Approve a pending submission: land the code in the library tree and
     * create the in-review component row. Returns the created component.
     *
     * @throws RuntimeException when the submission is not pending, declared
     *                          code is missing, or the slug collides
     */
    public function approve(ComponentSubmission $submission): Component
    {
        if ($submission->status !== SubmissionStatus::Pending) {
            throw new RuntimeException('Only pending submissions can be approved.');
        }

        $basename = $this->uniqueBasename($submission);

        $this->writeLibraryFiles($submission, $basename);

        return DB::transaction(function () use ($submission, $basename): Component {
            $component = Component::query()->create([
                'slug' => $submission->level->directory().'/'.$basename,
                'name' => $submission->name,
                'level' => $submission->level,
                'usage_category_id' => $submission->usage_category_id,
                'access_level' => AccessLevel::Free,
                'status' => ComponentStatus::InReview,
                'version' => '1.0.0',
                'source_name' => $submission->user->name,
                'source_url' => $submission->source_url,
            ]);

            $submission->update([
                'status' => SubmissionStatus::Approved,
                'component_id' => $component->id,
                'review_note' => null,
            ]);

            return $component;
        });
    }

    /**
     * URL-safe folder name derived from the submission name, suffixed
     * (`-2`, `-3`, …) until it collides with neither an existing component
     * slug nor a folder on disk in either library tree.
     */
    private function uniqueBasename(ComponentSubmission $submission): string
    {
        $base = Str::slug($submission->name) ?: 'component';
        $levelDir = $submission->level->directory();

        $candidate = $base;
        $suffix = 2;

        while ($this->slugTaken($levelDir, $candidate)) {
            $candidate = "{$base}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }

    private function slugTaken(string $levelDir, string $basename): bool
    {
        if (Component::query()->where('slug', "{$levelDir}/{$basename}")->exists()) {
            return true;
        }

        foreach (['react', 'vue'] as $framework) {
            if (is_dir($this->libraryDir($framework, $levelDir, $basename))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Write index + params.json + data.json for every framework the
     * submission declared (frameworks without pasted code cannot happen
     * through the validated form; the guard keeps direct service calls
     * honest).
     */
    private function writeLibraryFiles(ComponentSubmission $submission, string $basename): void
    {
        [$params, $data] = $this->inferParamsAndData($submission->sample_data ?? []);
        $levelDir = $submission->level->directory();

        foreach ($submission->framework->frameworks() as $framework) {
            $code = $framework === 'react' ? $submission->react_code : $submission->vue_code;

            if ($code === null || trim($code) === '') {
                throw new RuntimeException("Submission has no {$framework} code to publish.");
            }

            $directory = $this->libraryDir($framework, $levelDir, $basename);
            File::ensureDirectoryExists($directory);

            $indexFile = $framework === 'react' ? 'index.tsx' : 'index.vue';
            File::put($directory.DIRECTORY_SEPARATOR.$indexFile, $this->annotatedSource($submission, $basename, $framework, $code));
            File::put($directory.DIRECTORY_SEPARATOR.'params.json', $this->encodeObject($params));
            File::put($directory.DIRECTORY_SEPARATOR.'data.json', $this->encodeObject($data));
        }
    }

    private function libraryDir(string $framework, string $levelDir, string $basename): string
    {
        return rtrim((string) config("library.{$framework}_path"), '/\\')
            .DIRECTORY_SEPARATOR.$levelDir
            .DIRECTORY_SEPARATOR.$basename;
    }

    /**
     * Source with the generated annotation as the first docblock, so the
     * sync AnnotationParser reads our metadata. React takes a plain prepend;
     * Vue SFCs get the block injected after the first `<script>` tag (a
     * leading JS docblock would not be valid SFC), or a synthesized
     * `<script setup>` wrapper when the paste has no script block.
     */
    private function annotatedSource(ComponentSubmission $submission, string $basename, string $framework, string $code): string
    {
        $annotation = $this->annotation($submission, $basename);
        $code = rtrim($code)."\n";

        if ($framework === 'react') {
            return $annotation.$code;
        }

        if (preg_match('/<script\b[^>]*>/i', $code, $matches, PREG_OFFSET_CAPTURE) === 1) {
            $insertAt = $matches[0][1] + strlen($matches[0][0]);

            return substr($code, 0, $insertAt)."\n".$annotation.substr($code, $insertAt);
        }

        return "<script setup lang=\"ts\">\n{$annotation}</script>\n\n{$code}";
    }

    /**
     * Generated annotation docblock (SPEC §8.2 fields). Access is always
     * free and version always starts at 1.0.0 — curators adjust both in the
     * library files if the component graduates to paid.
     */
    private function annotation(ComponentSubmission $submission, string $basename): string
    {
        $name = str_replace(['*/', "\r", "\n"], '', $submission->name);
        $source = $submission->source_url ?? '';

        return implode("\n", [
            '/**',
            " * @component  {$basename}",
            " * @name       {$name}",
            " * @level      {$submission->level->value}",
            " * @usage      {$submission->usageCategory->slug}",
            ' * @industries',
            ' * @tags',
            ' * @access     free',
            " * @source     {$source}",
            ' * @deps',
            ' * @version    1.0.0',
            ' */',
        ])."\n";
    }

    /**
     * Derive a params.json schema from the sample data keys so the written
     * tree passes sync validation (every data key must exist in params).
     * Types map onto the ParamsValidator set; the sample value becomes the
     * documented default. Null samples are normalized to empty strings —
     * null satisfies no param type.
     *
     * @param  array<string, mixed>  $sampleData
     * @return array{0: array<string, array{type: string, default: mixed, description: string}>, 1: array<string, mixed>}
     */
    private function inferParamsAndData(array $sampleData): array
    {
        $params = [];
        $data = [];

        foreach ($sampleData as $key => $value) {
            $type = match (true) {
                is_bool($value) => 'boolean',
                is_int($value), is_float($value) => 'number',
                is_array($value) && array_is_list($value) => 'array',
                is_array($value) => 'object',
                default => 'string',
            };

            $data[$key] = $value ?? '';
            $params[$key] = [
                'type' => $type,
                'default' => $value ?? '',
                'description' => 'Submitted sample value.',
            ];
        }

        return [$params, $data];
    }

    /**
     * Pretty-printed JSON that keeps empty schemas/data as `{}` (an empty
     * PHP array would encode as `[]`, which fails the scanner's object
     * check).
     *
     * @param  array<string, mixed>  $value
     */
    private function encodeObject(array $value): string
    {
        return json_encode($value === [] ? new \stdClass : $value, JSON_PRETTY_PRINT)."\n";
    }
}
