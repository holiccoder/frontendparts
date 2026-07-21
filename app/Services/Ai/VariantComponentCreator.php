<?php

namespace App\Services\Ai;

use App\Enums\AccessLevel;
use App\Enums\ComponentStatus;
use App\Models\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

/**
 * Lands an AI-generated component variation in the library (task 5.4,
 * features.ai_variants). Mirrors the submission-approval precedent
 * (SubmissionApprover): the generated sources are written into both library
 * trees in the scanner's layout — with the params.json / data.json contract
 * copied verbatim from the original so the API is unchanged — and the
 * components row is created immediately (status in_review, access free,
 * version 1.0.0) linked to the original via variant_of. source_name marks
 * the component as AI-generated per the catalog citation convention.
 *
 * A follow-up library:sync then owns the component exactly like an approved
 * submission; publishing still goes through the human QA gate — variants
 * are never auto-published.
 */
class VariantComponentCreator
{
    /**
     * Attribution written to source_name for AI variants.
     */
    public static function sourceName(Component $original): string
    {
        return "AI-generated variant of {$original->name}";
    }

    /**
     * Create the in-review variant row and its library files. Returns the
     * new component. On failure no component row survives and any written
     * library folders are removed again.
     *
     * @param  array<string, mixed>  $params  params.json contract copied from the original
     * @param  array<string, mixed>  $data  data.json sample copied from the original
     *
     * @throws Throwable when the row cannot be created
     */
    public function create(Component $original, GeneratedVariant $variant, array $params, array $data): Component
    {
        $basename = $this->uniqueBasename($original, $variant);

        $this->writeLibraryFiles($original, $variant, $basename, $params, $data);

        try {
            return DB::transaction(function () use ($original, $variant, $basename): Component {
                $component = Component::query()->create([
                    'slug' => $original->level->directory().'/'.$basename,
                    'name' => $variant->name,
                    'level' => $original->level,
                    'usage_category_id' => $original->usage_category_id,
                    'access_level' => AccessLevel::Free,
                    'status' => ComponentStatus::InReview,
                    'version' => '1.0.0',
                    'source_name' => self::sourceName($original),
                    'source_url' => null,
                    'variant_of' => $original->id,
                ]);

                // The variant serves the same purpose, so it inherits the
                // original's taxonomy relations (the written annotation
                // carries the same slugs so sync keeps them).
                $component->industries()->sync($original->industries()->pluck('categories.id'));
                $component->tags()->sync($original->tags()->pluck('tags.id'));

                return $component;
            });
        } catch (Throwable $exception) {
            foreach (['react', 'vue'] as $framework) {
                File::deleteDirectory($this->libraryDir($original, $framework, $basename));
            }

            throw $exception;
        }
    }

    /**
     * URL-safe folder name derived from the variant name, suffixed
     * (`-2`, `-3`, …) until it collides with neither an existing component
     * slug nor a folder on disk in either library tree.
     */
    private function uniqueBasename(Component $original, GeneratedVariant $variant): string
    {
        $base = Str::slug($variant->name) ?: 'variant';
        $levelDir = $original->level->directory();

        $candidate = $base;
        $suffix = 2;

        while ($this->slugTaken($original, $levelDir, $candidate)) {
            $candidate = "{$base}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }

    private function slugTaken(Component $original, string $levelDir, string $basename): bool
    {
        if (Component::query()->where('slug', "{$levelDir}/{$basename}")->exists()) {
            return true;
        }

        foreach (['react', 'vue'] as $framework) {
            if (is_dir($this->libraryDir($original, $framework, $basename))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Write the generated entry sources plus the original's params/data
     * contract for both frameworks, in the exact layout ComponentScanner
     * discovers.
     *
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $data
     */
    private function writeLibraryFiles(Component $original, GeneratedVariant $variant, string $basename, array $params, array $data): void
    {
        foreach (['react', 'vue'] as $framework) {
            $code = $framework === 'react' ? $variant->reactCode : $variant->vueCode;

            $directory = $this->libraryDir($original, $framework, $basename);
            File::ensureDirectoryExists($directory);

            $indexFile = $framework === 'react' ? 'index.tsx' : 'index.vue';
            File::put($directory.DIRECTORY_SEPARATOR.$indexFile, $this->annotatedSource($original, $variant, $basename, $framework, $code));
            File::put($directory.DIRECTORY_SEPARATOR.'params.json', $this->encodeObject($params));
            File::put($directory.DIRECTORY_SEPARATOR.'data.json', $this->encodeObject($data));
        }
    }

    private function libraryDir(Component $original, string $framework, string $basename): string
    {
        return rtrim((string) config("library.{$framework}_path"), '/\\')
            .DIRECTORY_SEPARATOR.$original->level->directory()
            .DIRECTORY_SEPARATOR.$basename;
    }

    /**
     * Source with the generated annotation as the first docblock, so the
     * sync AnnotationParser reads our metadata. React takes a plain prepend;
     * Vue SFCs get the block injected after the first `<script>` tag, or a
     * synthesized `<script setup>` wrapper when the model returned markup
     * without a script block.
     */
    private function annotatedSource(Component $original, GeneratedVariant $variant, string $basename, string $framework, string $code): string
    {
        $annotation = $this->annotation($original, $variant, $basename);
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
     * Generated annotation docblock (SPEC §8.2 fields). Usage, industries,
     * tags and deps carry over from the original; access is always free and
     * version always starts at 1.0.0 — curators adjust both in the library
     * files if the variant graduates to paid.
     */
    private function annotation(Component $original, GeneratedVariant $variant, string $basename): string
    {
        $name = str_replace(['*/', "\r", "\n"], '', $variant->name);
        $industries = $original->industries()->pluck('slug')->implode(' ');
        $tags = $original->tags()->pluck('slug')->implode(' ');

        return implode("\n", [
            '/**',
            " * @component  {$basename}",
            " * @name       {$name}",
            " * @level      {$original->level->value}",
            " * @usage      {$original->usageCategory->slug}",
            " * @industries {$industries}",
            " * @tags       {$tags}",
            ' * @access     free',
            ' * @source',
            ' * @deps',
            ' * @version    1.0.0',
            ' */',
        ])."\n";
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
