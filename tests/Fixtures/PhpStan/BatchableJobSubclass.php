<?php

namespace BoringO11y\Skystan\Tests\Fixtures\PhpStan;

class BatchableJobSubclass extends BatchableJobBase
{
    public function handle(): void
    {
        // Inherits Batchable from a concrete ancestor — the cancellation guard
        // is that ancestor's responsibility, so this subclass is not re-flagged.
    }
}
