<?php

namespace BoringO11y\Skystan\Tests\Fixtures\PhpStan;

use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;

class BatchableJobWithSkipMiddleware implements ShouldQueue
{
    use Batchable;

    public function middleware(): array
    {
        return [new SkipIfBatchCancelled];
    }

    public function handle(): void
    {
        // The middleware short-circuits this job when the batch is cancelled.
    }
}
