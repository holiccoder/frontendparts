<?php

namespace App\Services\Library;

use RuntimeException;

class PreviewBuildException extends RuntimeException
{
    public static function appNotBuildable(string $framework, string $appPath): self
    {
        return new self("{$framework}: library app at {$appPath} is not buildable (missing package.json or vite.build.config.ts)");
    }

    public static function missingSource(string $framework, string $slug): self
    {
        return new self("{$framework}: component source or data.json missing for {$slug}");
    }

    public static function unsafePath(string $framework, string $path): self
    {
        return new self("{$framework}: refusing to materialize unsafe file path {$path}");
    }

    public static function buildFailed(string $framework, string $slug, string $output): self
    {
        return new self("{$framework}: vite build failed for {$slug}: {$output}");
    }

    public static function noBundle(string $framework, string $slug, string $outDir): self
    {
        return new self("{$framework}: vite build for {$slug} produced no JS bundle in {$outDir}");
    }
}
