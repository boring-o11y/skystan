<?php

namespace BoringO11y\Skystan\Tests\Fixtures\PhpStan;

use Illuminate\Contracts\Queue\ShouldQueue;

abstract class AbstractJobWithModelProperty implements ShouldQueue
{
    public Product $product;

    abstract public function handle(): void;
}
