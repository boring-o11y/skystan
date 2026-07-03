<?php

namespace BoringO11y\Skystan\Tests\Fixtures\PhpStan;

use Illuminate\Contracts\Queue\ShouldQueue;

class JobWithModelPropertyWithoutSerializesModels implements ShouldQueue
{
    public Product $product;

    public function handle(): void {}
}
