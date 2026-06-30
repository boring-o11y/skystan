<?php

declare(strict_types=1);

namespace Illuminate\Bus;

/**
 * Minimal stub of the Batchable trait so fixture jobs that
 * `use Illuminate\Bus\Batchable` resolve during reflection-based rule tests
 * without pulling in laravel/framework.
 */
trait Batchable
{
    public function batch(): ?Batch
    {
        return null;
    }
}
