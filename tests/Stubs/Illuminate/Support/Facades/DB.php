<?php

declare(strict_types=1);

namespace Illuminate\Support\Facades;

/**
 * Minimal stub of the DB facade so fixture call sites (`DB::transaction(...)`)
 * resolve during rule tests.
 */
class DB
{
    public static function transaction(\Closure $callback, int $attempts = 1): mixed
    {
        return $callback();
    }

    public static function beginTransaction(): void {}

    public static function commit(): void {}
}
