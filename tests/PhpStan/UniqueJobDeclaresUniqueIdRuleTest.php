<?php

declare(strict_types=1);

namespace BoringO11y\Skystan\Tests\PhpStan;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use BoringO11y\Skystan\PhpStan\Rules\UniqueJobDeclaresUniqueIdRule;
use BoringO11y\Skystan\PhpStan\ShouldBeUniqueReflectionDetector;

/**
 * @extends RuleTestCase<UniqueJobDeclaresUniqueIdRule>
 */
final class UniqueJobDeclaresUniqueIdRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new UniqueJobDeclaresUniqueIdRule(new ShouldBeUniqueReflectionDetector);
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/phpstan-test.neon'];
    }

    public function test_flags_parameterized_unique_job_without_unique_id(): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/ParameterizedUniqueJobWithoutUniqueId.php'],
            [
                [
                    'Job BoringO11y\Skystan\Tests\Fixtures\PhpStan\ParameterizedUniqueJobWithoutUniqueId implements ShouldBeUnique and is parameterized but does not declare uniqueId. The unique lock key then falls back to the class name alone, so every dispatch — whatever the constructor arguments — collapses into one unique job and distinct jobs are silently dropped. Add a uniqueId() method (or $uniqueId property) derived from the distinguishing arguments; for an intentionally class-wide job, return a constant to make that explicit.',
                    8,
                ],
            ],
        );
    }

    public function test_does_not_flag_parameterless_singleton_unique_job(): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/SingletonUniqueJobWithoutUniqueId.php'],
            [],
        );
    }

    public function test_does_not_flag_when_unique_id_is_declared(): void
    {
        // UniqueJobWithoutUniqueFor is parameterized and declares uniqueId().
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/UniqueJobWithoutUniqueFor.php'],
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
