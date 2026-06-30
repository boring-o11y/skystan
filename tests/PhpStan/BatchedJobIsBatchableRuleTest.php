<?php

declare(strict_types=1);

namespace BoringO11y\Skystan\Tests\PhpStan;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use BoringO11y\Skystan\PhpStan\BatchableReflectionDetector;
use BoringO11y\Skystan\PhpStan\Rules\BatchedJobIsBatchableRule;

/**
 * @extends RuleTestCase<BatchedJobIsBatchableRule>
 */
final class BatchedJobIsBatchableRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new BatchedJobIsBatchableRule(new BatchableReflectionDetector);
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/phpstan-test.neon'];
    }

    public function test_flags_non_batchable_jobs_dispatched_via_bus_batch(): void
    {
        $message = 'Job BoringO11y\Skystan\Tests\Fixtures\PhpStan\RegularJob is dispatched in Bus::batch() but does not use the Batchable trait. The job then has no $this->batch() accessor (a fatal call to an undefined method as soon as it reads the batch) and the batch cannot track it. Add `use Illuminate\Bus\Batchable;` to the job.';

        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/BatchableBatchDispatcher.php'],
            [
                [$message, 13],
                [$message, 22],
            ],
        );
    }
}
