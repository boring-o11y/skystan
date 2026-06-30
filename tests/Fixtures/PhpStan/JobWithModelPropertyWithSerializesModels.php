<?php

namespace BoringO11y\Skystan\Tests\Fixtures\PhpStan;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

class JobWithModelPropertyWithSerializesModels implements ShouldQueue
{
    use SerializesModels;

    public Yacht $yacht;

    public function handle(): void {}
}
