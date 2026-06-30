<?php

namespace BoringO11y\Skystan\Tests\Fixtures\PhpStan;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;

class UniqueJobWithUniqueForProperty implements ShouldQueue, ShouldBeUnique
{
    public int $uniqueFor = 3600;

    public function uniqueId(): string
    {
        return 'fixed';
    }

    public function handle(): void {}
}
