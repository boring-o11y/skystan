<?php

declare(strict_types=1);

namespace Illuminate\Queue\Middleware;

/**
 * Minimal stub of the SkipIfBatchCancelled job middleware so fixture jobs that
 * register it from middleware() resolve during reflection-based rule tests
 * without pulling in laravel/framework.
 */
class SkipIfBatchCancelled
{
    public function handle(mixed $job, callable $next): mixed
    {
        return $next($job);
    }
}
