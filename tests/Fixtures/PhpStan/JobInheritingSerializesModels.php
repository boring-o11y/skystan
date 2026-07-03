<?php

namespace BoringO11y\Skystan\Tests\Fixtures\PhpStan;

class JobInheritingSerializesModels extends BaseJobWithSerializesModels
{
    public Product $product;

    public function handle(): void {}
}
