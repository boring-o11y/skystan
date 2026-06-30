<?php

namespace BoringO11y\Skystan\Tests\Fixtures\PhpStan;

use Illuminate\Contracts\Queue\ShouldQueue;

class RegularJob implements ShouldQueue
{
    public function handle(): void {}
}
