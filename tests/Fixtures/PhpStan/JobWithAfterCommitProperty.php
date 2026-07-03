<?php

namespace BoringO11y\Skystan\Tests\Fixtures\PhpStan;

use Illuminate\Contracts\Queue\ShouldQueue;

class JobWithAfterCommitProperty implements ShouldQueue
{
    public bool $afterCommit = true;

    public function handle(): void {}
}
