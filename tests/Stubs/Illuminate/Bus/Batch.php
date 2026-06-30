<?php

declare(strict_types=1);

namespace Illuminate\Bus;

/**
 * Minimal stub of the Batch value object so fixture jobs that call
 * `$this->batch()->cancelled()` resolve during reflection-based rule tests
 * without pulling in laravel/framework.
 */
class Batch
{
    public function cancelled(): bool
    {
        return false;
    }
}
