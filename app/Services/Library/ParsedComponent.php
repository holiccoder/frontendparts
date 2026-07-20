<?php

namespace App\Services\Library;

use App\Enums\AccessLevel;
use App\Enums\ComponentLevel;

/**
 * One library component as scanned from disk (one framework side).
 * The full slug is `{level-directory}/{slug}`, e.g. `elements/section-title-01`.
 */
class ParsedComponent
{
    /**
     * @param  list<string>  $industries
     * @param  list<string>  $tags
     * @param  list<string>  $deps
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $data
     * @param  list<string>  $errors
     */
    public function __construct(
        public string $slug,
        public string $name,
        public ComponentLevel $level,
        public string $usage,
        public array $industries,
        public array $tags,
        public AccessLevel $access,
        public ?string $sourceUrl,
        public array $deps,
        public string $version,
        public string $filePath,
        public string $source,
        public array $params,
        public array $data,
        public string $framework,
        public ?string $paramsJson = null,
        public ?string $dataJson = null,
        public array $errors = [],
    ) {}

    public function fullSlug(): string
    {
        return $this->level->directory().'/'.$this->slug;
    }

    /**
     * Hash of everything that should trigger a rebuild when it changes:
     * source file, params.json, data.json (which embeds the annotation-affecting
     * sample data) and the raw annotation docblock inside the source.
     */
    public function sourceHash(): string
    {
        return hash('sha256', implode("\n", [
            $this->source,
            $this->paramsJson ?? '',
            $this->dataJson ?? '',
        ]));
    }
}
