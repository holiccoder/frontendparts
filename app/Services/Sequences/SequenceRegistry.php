<?php

namespace App\Services\Sequences;

/**
 * Registry of all lifecycle sequence definitions (SPEC §16.2), wired in
 * AppServiceProvider. The daily `mail:run-sequences` command iterates this
 * list; adding a sequence (B5–B8, dunning, win-back) means registering one
 * more definition here — no engine changes.
 */
class SequenceRegistry
{
    /**
     * @param  list<SequenceDefinition>  $definitions
     */
    public function __construct(
        private readonly array $definitions,
    ) {}

    /**
     * @return list<SequenceDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    public function find(string $key): ?SequenceDefinition
    {
        foreach ($this->definitions as $definition) {
            if ($definition->key() === $key) {
                return $definition;
            }
        }

        return null;
    }
}
