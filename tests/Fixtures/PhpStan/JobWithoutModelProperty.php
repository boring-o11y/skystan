<?php

namespace BoringO11y\Skystan\Tests\Fixtures\PhpStan;

use Illuminate\Contracts\Queue\ShouldQueue;

class JobWithoutModelProperty implements ShouldQueue
{
    public int $productId;

    public string $reason = '';

    public function handle(): void {}
}
