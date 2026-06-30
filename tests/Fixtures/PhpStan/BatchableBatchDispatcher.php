<?php

namespace BoringO11y\Skystan\Tests\Fixtures\PhpStan;

use Illuminate\Support\Facades\Bus;

class BatchableBatchDispatcher
{
    public function batchesNonBatchableJob(): void
    {
        Bus::batch([
            new BatchableJobWithCancellationCheck,
            new RegularJob,
        ]);
    }

    public function batchesNonBatchableJobInNestedChain(): void
    {
        Bus::batch([
            [
                new BatchableJobWithCancellationCheck,
                new RegularJob,
            ],
        ]);
    }

    public function batchesOnlyBatchableJobs(): void
    {
        Bus::batch([
            new BatchableJobWithCancellationCheck,
        ]);
    }
}
