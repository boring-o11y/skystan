<?php

declare(strict_types=1);

namespace BoringO11y\Skystan\Tests\PhpStan;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use BoringO11y\Skystan\PhpStan\DispatchedJobReflectionDetector;
use BoringO11y\Skystan\PhpStan\Rules\JobDispatchedInTransactionUsesAfterCommitRule;

/**
 * @extends RuleTestCase<JobDispatchedInTransactionUsesAfterCommitRule>
 */
final class JobDispatchedInTransactionUsesAfterCommitRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new JobDispatchedInTransactionUsesAfterCommitRule(
            new DispatchedJobReflectionDetector,
            $this->createReflectionProvider(),
        );
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/phpstan-test.neon'];
    }

    public function test_flags_queued_jobs_dispatched_in_transaction_without_after_commit(): void
    {
        $message = 'Job BoringO11y\Skystan\Tests\Fixtures\PhpStan\RegularJob is dispatched inside DB::transaction() without ->afterCommit(). A worker can pick it up before the transaction commits — so it runs against rows not yet visible — and if the transaction rolls back the job still runs against data that never existed. Chain ->afterCommit() on the dispatch, or declare `public bool $afterCommit = true;` on the job.';

        // The fixture also exercises every non-flagged path — `->afterCommit()`
        // (chained, deeper-in-chain, nullsafe, on the helper, in an arrow fn), a
        // job declaring/inheriting `$afterCommit`, `Bus::dispatch()`,
        // `dispatchSync()`, `self::dispatch()`, a non-queued dispatchable and a
        // dispatch outside any transaction — none of which may be reported. The
        // nested-transaction dispatch (line 35) must be reported exactly once.
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/TransactionDispatcher.php'],
            [
                [$message, 13],
                [$message, 20],
                [$message, 26],
                [$message, 35],
            ],
        );
    }
}
