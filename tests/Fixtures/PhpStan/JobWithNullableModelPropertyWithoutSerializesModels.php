<?php

namespace BoringO11y\Skystan\Tests\Fixtures\PhpStan;

use Illuminate\Contracts\Queue\ShouldQueue;

class JobWithNullableModelPropertyWithoutSerializesModels implements ShouldQueue
{
    public ?Product $product = null;

    public function handle(): void {}
}
