<?php

declare(strict_types=1);

namespace BoringO11y\Skystan\Tests\PhpStan;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use BoringO11y\Skystan\PhpStan\Rules\NoBatchedUniqueJobRule;
use BoringO11y\Skystan\PhpStan\ShouldBeUniqueReflectionDetector;

/**
 * @extends RuleTestCase<NoBatchedUniqueJobRule>
 */
final class NoBatchedUniqueJobRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoBatchedUniqueJobRule(new ShouldBeUniqueReflectionDetector);
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/phpstan-test.neon'];
    }

    public function test_flags_unique_jobs_dispatched_via_batch_or_bulk(): void
    {
        $batch = 'Job BoringO11y\Skystan\Tests\Fixtures\PhpStan\UniqueJobWithUniqueForProperty implements ShouldBeUnique and must not be dispatched via batch(). Bulk/batch dispatch bypasses the uniqueness lock — dispatch the job individually instead.';
        $bulk = 'Job BoringO11y\Skystan\Tests\Fixtures\PhpStan\UniqueJobWithUniqueForProperty implements ShouldBeUnique and must not be dispatched via bulk(). Bulk/batch dispatch bypasses the uniqueness lock — dispatch the job individually instead.';

        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/BatchDispatcher.php'],
            [
                [$batch, 13],
                [$bulk, 21],
                [$bulk, 28],
                [$batch, 37],
            ],
        );
    }
}
