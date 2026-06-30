<?php

namespace BoringO11y\Skystan\Tests\Fixtures\PhpStan;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

abstract class BaseJobWithSerializesModels implements ShouldQueue
{
    use SerializesModels;
}
