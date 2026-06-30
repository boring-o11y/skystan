<?php

namespace BoringO11y\Skystan\Tests\Fixtures\PhpStan;

use Illuminate\Contracts\Queue\ShouldQueue;

abstract class AbstractJobWithModelProperty implements ShouldQueue
{
    public Yacht $yacht;

    abstract public function handle(): void;
}
