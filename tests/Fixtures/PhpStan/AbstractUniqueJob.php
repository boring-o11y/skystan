<?php

namespace BoringO11y\Skystan\Tests\Fixtures\PhpStan;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;

abstract class AbstractUniqueJob implements ShouldQueue, ShouldBeUnique
{
    abstract public function handle(): void;
}
