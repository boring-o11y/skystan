<?php

namespace BoringO11y\Skystan\Tests\Fixtures\PhpStan;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;

class UniqueJobWithUniqueForMethod implements ShouldQueue, ShouldBeUnique
{
    public function uniqueId(): string
    {
        return 'fixed';
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    public function handle(): void {}
}
