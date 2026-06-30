<?php

namespace BoringO11y\Skystan\Tests\Fixtures\PhpStan;

use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;

class BatchableJobWithCancellationCheck implements ShouldQueue
{
    use Batchable;

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }
    }
}
