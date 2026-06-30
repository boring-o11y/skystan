<?php

namespace BoringO11y\Skystan\Tests\Fixtures\PhpStan;

use Illuminate\Contracts\Queue\ShouldQueue;

class JobWithNullableModelPropertyWithoutSerializesModels implements ShouldQueue
{
    public ?Yacht $yacht = null;

    public function handle(): void {}
}
