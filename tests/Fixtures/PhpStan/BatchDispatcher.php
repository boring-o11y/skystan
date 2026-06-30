<?php

namespace BoringO11y\Skystan\Tests\Fixtures\PhpStan;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;

class BatchDispatcher
{
    public function batchesUniqueJob(): void
    {
        Bus::batch([
            new UniqueJobWithUniqueForProperty,
            new RegularJob,
        ]);
    }

    public function bulksUniqueJobViaBus(): void
    {
        Bus::bulk([
            new UniqueJobWithUniqueForProperty,
        ]);
    }

    public function bulksUniqueJobViaQueue(): void
    {
        Queue::bulk([
            new UniqueJobWithUniqueForProperty,
        ]);
    }

    public function batchesNestedChainWithUniqueJob(): void
    {
        Bus::batch([
            [
                new RegularJob,
                new UniqueJobWithUniqueForProperty,
            ],
        ]);
    }

    public function batchesOnlyRegularJobs(): void
    {
        Bus::batch([
            new RegularJob,
            new RegularJob,
        ]);
    }
}
