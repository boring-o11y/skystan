<?php

declare(strict_types=1);

namespace BoringO11y\Skystan\Tests\PhpStan;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use BoringO11y\Skystan\PhpStan\Rules\UniqueJobDeclaresUniqueForRule;
use BoringO11y\Skystan\PhpStan\ShouldBeUniqueReflectionDetector;

/**
 * @extends RuleTestCase<UniqueJobDeclaresUniqueForRule>
 */
final class UniqueJobDeclaresUniqueForRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new UniqueJobDeclaresUniqueForRule(new ShouldBeUniqueReflectionDetector);
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/phpstan-test.neon'];
    }

    public function test_flags_should_be_unique_job_without_unique_for(): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/UniqueJobWithoutUniqueFor.php'],
            [
                [
                    'Job BoringO11y\Skystan\Tests\Fixtures\PhpStan\UniqueJobWithoutUniqueFor implements ShouldBeUnique but does not declare uniqueFor. Add a `public int $uniqueFor` property or a `uniqueFor()` method so the uniqueness lock expires — otherwise a worker that dies mid-job leaks the lock and the job can never be re-dispatched.',
                    8,
                ],
            ],
        );
    }

    public function test_does_not_flag_when_unique_for_is_a_property(): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/UniqueJobWithUniqueForProperty.php'],
            [],
        );
    }

    public function test_does_not_flag_when_unique_for_is_a_method(): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/UniqueJobWithUniqueForMethod.php'],
            [],
        );
    }

    public function test_does_not_flag_jobs_that_are_not_should_be_unique(): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/RegularJob.php'],
            [],
        );
    }

    public function test_does_not_flag_abstract_jobs(): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/AbstractUniqueJob.php'],
            [],
        );
    }
}
