<?php

namespace BoringO11y\Skystan\Tests\Fixtures\PhpStan;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;

class UniqueJobWithoutUniqueFor implements ShouldQueue, ShouldBeUnique
{
    public function __construct(public int $userId) {}

    public function uniqueId(): string
    {
        return (string) $this->userId;
    }

    public function handle(): void {}
}
