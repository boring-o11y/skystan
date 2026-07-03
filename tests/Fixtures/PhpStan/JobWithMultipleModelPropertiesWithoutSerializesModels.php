<?php

namespace BoringO11y\Skystan\Tests\Fixtures\PhpStan;

use Illuminate\Contracts\Queue\ShouldQueue;

class JobWithMultipleModelPropertiesWithoutSerializesModels implements ShouldQueue
{
    public function __construct(
        public Product $product,
        public ?Product $replacement,
        public int $companyId,
    ) {}

    public function handle(): void {}
}
