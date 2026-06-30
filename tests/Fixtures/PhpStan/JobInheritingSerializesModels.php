<?php

namespace BoringO11y\Skystan\Tests\Fixtures\PhpStan;

class JobInheritingSerializesModels extends BaseJobWithSerializesModels
{
    public Yacht $yacht;

    public function handle(): void {}
}
