<?php

declare(strict_types=1);

namespace Illuminate\Support\Facades;

/**
 * Minimal stub of the Queue facade so fixture call sites (`Queue::bulk([...])`)
 * resolve during rule tests.
 */
class Queue
{
    public static function bulk(mixed $jobs, mixed $data = '', mixed $queue = null): void {}
}
