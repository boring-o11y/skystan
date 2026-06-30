<?php

declare(strict_types=1);

namespace Illuminate\Support\Facades;

/**
 * Minimal stub of the Bus facade so fixture call sites (`Bus::batch([...])`,
 * `Bus::bulk([...])`) resolve during rule tests.
 */
class Bus
{
    public static function batch(mixed $jobs): mixed
    {
        return null;
    }

    public static function bulk(mixed $jobs, mixed $data = '', mixed $queue = null): void {}
}
