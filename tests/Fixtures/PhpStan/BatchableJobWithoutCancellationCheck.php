<?php

namespace BoringO11y\Skystan\Tests\Fixtures\PhpStan;

use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;

class BatchableJobWithoutCancellationCheck implements ShouldQueue
{
    use Batchable;

    public function handle(): void
    {
        // Runs its full body even after the batch is cancelled.
    }
}
