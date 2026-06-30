<?php

namespace BoringO11y\Skystan\Tests\Fixtures\PhpStan;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;

class ParameterizedUniqueJobWithoutUniqueId implements ShouldQueue, ShouldBeUnique
{
    public int $uniqueFor = 3600;

    public function __construct(public int $companyId) {}

    public function handle(): void {}
}
