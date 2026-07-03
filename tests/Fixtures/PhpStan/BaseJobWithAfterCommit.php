<?php

namespace BoringO11y\Skystan\Tests\Fixtures\PhpStan;

use Illuminate\Contracts\Queue\ShouldQueue;

abstract class BaseJobWithAfterCommit implements ShouldQueue
{
    public bool $afterCommit = true;
}
