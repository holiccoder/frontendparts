<?php

namespace App\Enums;

enum ComponentLevel: string
{
    case Element = 'element';
    case Block = 'block';
    case Section = 'section';
    case Page = 'page';

    /**
     * The library directory holding components of this level
     * (library/{app}/src/components/{directory}/{slug}).
     */
    public function directory(): string
    {
        return match ($this) {
            self::Element => 'elements',
            self::Block => 'blocks',
            self::Section => 'sections',
            self::Page => 'pages',
        };
    }

    public static function fromDirectory(string $directory): ?self
    {
        return match ($directory) {
            'elements' => self::Element,
            'blocks' => self::Block,
            'sections' => self::Section,
            'pages' => self::Page,
            default => null,
        };
    }
}
