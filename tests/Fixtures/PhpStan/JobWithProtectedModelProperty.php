<?php

namespace BoringO11y\Skystan\Tests\Fixtures\PhpStan;

use Illuminate\Contracts\Queue\ShouldQueue;

class JobWithProtectedModelProperty implements ShouldQueue
{
    protected Product $product;

    public function handle(): void {}
}
