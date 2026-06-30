<?php

declare(strict_types=1);

namespace BoringO11y\Skystan\Tests\PhpStan;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use BoringO11y\Skystan\PhpStan\BatchableReflectionDetector;
use BoringO11y\Skystan\PhpStan\Rules\BatchableJobChecksCancellationRule;

/**
 * @extends RuleTestCase<BatchableJobChecksCancellationRule>
 */
final class BatchableJobChecksCancellationRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new BatchableJobChecksCancellationRule(new BatchableReflectionDetector);
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/phpstan-test.neon'];
    }

    public function test_flags_batchable_job_without_cancellation_check(): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/BatchableJobWithoutCancellationCheck.php'],
            [
                [
                    'Job BoringO11y\Skystan\Tests\Fixtures\PhpStan\BatchableJobWithoutCancellationCheck uses the Batchable trait but never checks whether its batch has been cancelled. A cancelled batch does not kill jobs already queued — each still runs its full body, wasting work and mutating state for an abandoned batch. Guard the work with `if ($this->batch()?->cancelled()) { return; }` at the start of handle(), or register the Illuminate\Queue\Middleware\SkipIfBatchCancelled middleware from middleware().',
                    8,
                ],
            ],
        );
    }

    public function test_does_not_flag_when_handle_checks_cancellation(): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/BatchableJobWithCancellationCheck.php'],
            [],
        );
    }

    public function test_does_not_flag_when_skip_middleware_is_registered(): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/BatchableJobWithSkipMiddleware.php'],
            [],
        );
    }

    public function test_does_not_flag_abstract_batchable_job(): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/AbstractBatchableJob.php'],
            [],
        );
    }

    public function test_flags_concrete_batchable_base_once_at_its_source(): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/BatchableJobBase.php'],
            [
                [
                    'Job BoringO11y\Skystan\Tests\Fixtures\PhpStan\BatchableJobBase uses the Batchable trait but never checks whether its batch has been cancelled. A cancelled batch does not kill jobs already queued — each still runs its full body, wasting work and mutating state for an abandoned batch. Guard the work with `if ($this->batch()?->cancelled()) { return; }` at the start of handle(), or register the Illuminate\Queue\Middleware\SkipIfBatchCancelled middleware from middleware().',
                    8,
                ],
            ],
        );
    }

    public function test_does_not_reflag_subclass_inheriting_batchable_from_concrete_ancestor(): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/BatchableJobSubclass.php'],
            [],
        );
    }

    public function test_does_not_flag_non_queued_class_using_batchable(): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/BatchableNonQueuedClass.php'],
            [],
        );
    }

    public function test_does_not_flag_non_batchable_queued_job(): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/RegularJob.php'],
            [],
        );
    }
}
