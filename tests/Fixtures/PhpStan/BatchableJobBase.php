<?php

namespace BoringO11y\Skystan\Tests\Fixtures\PhpStan;

use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;

class BatchableJobBase implements ShouldQueue
{
    use Batchable;

    public function handle(): void
    {
        // Concrete base that introduces Batchable but never checks cancellation.
    }
}
