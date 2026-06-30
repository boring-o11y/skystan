<?php

namespace BoringO11y\Skystan\Tests\Fixtures\PhpStan;

use Illuminate\Contracts\Queue\ShouldQueue;

class JobWithProtectedModelProperty implements ShouldQueue
{
    protected Yacht $yacht;

    public function handle(): void {}
}
