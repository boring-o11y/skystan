<?php

namespace BoringO11y\Skystan\Tests\Fixtures\PhpStan;

use Illuminate\Bus\Batchable;

class BatchableNonQueuedClass
{
    use Batchable;

    public function handle(): void
    {
        // Not a queued job (no ShouldQueue) — outside the rule's scope.
    }
}
