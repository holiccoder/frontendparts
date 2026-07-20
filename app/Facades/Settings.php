<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array<string, mixed> registered()
 * @method static bool has(string $key)
 * @method static mixed get(string $key)
 * @method static void set(string $key, mixed $value)
 * @method static void forget(string $key)
 *
 * @see \App\Support\Settings
 */
class Settings extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \App\Support\Settings::class;
    }
}
