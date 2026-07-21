<?php

namespace App\Enums;

/**
 * Framework coverage of a community submission (task 5.3): which library
 * tree(s) the pasted source targets. `Both` means the submitter pasted a
 * React and a Vue implementation (the library's dual-framework rule).
 */
enum SubmissionFramework: string
{
    case React = 'react';
    case Vue = 'vue';
    case Both = 'both';

    /**
     * Library tree names the submission carries code for.
     *
     * @return list<string>
     */
    public function frameworks(): array
    {
        return match ($this) {
            self::React => ['react'],
            self::Vue => ['vue'],
            self::Both => ['react', 'vue'],
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $framework): array => [$framework->value => ucfirst($framework->value)])
            ->all();
    }
}
