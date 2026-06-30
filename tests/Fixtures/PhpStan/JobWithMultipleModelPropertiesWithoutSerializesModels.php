<?php

namespace BoringO11y\Skystan\Tests\Fixtures\PhpStan;

use Illuminate\Contracts\Queue\ShouldQueue;

class JobWithMultipleModelPropertiesWithoutSerializesModels implements ShouldQueue
{
    public function __construct(
        public Yacht $yacht,
        public ?Yacht $replacement,
        public int $companyId,
    ) {}

    public function handle(): void {}
}
