<?php

namespace App\Services\Library;

/**
 * Outcome of one library:sync run.
 */
class SyncResult
{
    /**
     * @param  array<string, list<string>>  $errors  full slug => error messages (empty list = ok)
     * @param  list<int>  $rebuiltComponentIds
     */
    public function __construct(
        public readonly int $scanned,
        public readonly int $upserted,
        public readonly array $errors,
        public readonly array $rebuiltComponentIds = [],
    ) {}

    public function hasErrors(): bool
    {
        foreach ($this->errors as $messages) {
            if ($messages !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, list<string>> only the components that failed
     */
    public function failures(): array
    {
        return array_filter($this->errors, fn (array $messages): bool => $messages !== []);
    }
}
