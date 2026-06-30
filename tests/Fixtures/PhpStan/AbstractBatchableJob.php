<?php

namespace BoringO11y\Skystan\Tests\Fixtures\PhpStan;

use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;

abstract class AbstractBatchableJob implements ShouldQueue
{
    use Batchable;

    public function handle(): void
    {
        // Abstract base — not dispatched directly; a concrete subclass guards.
    }
}
