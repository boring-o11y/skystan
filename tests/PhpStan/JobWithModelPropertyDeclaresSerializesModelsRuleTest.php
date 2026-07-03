<?php

declare(strict_types=1);

namespace BoringO11y\Skystan\Tests\PhpStan;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use BoringO11y\Skystan\PhpStan\EloquentModelReflectionDetector;
use BoringO11y\Skystan\PhpStan\Rules\JobWithModelPropertyDeclaresSerializesModelsRule;

/**
 * @extends RuleTestCase<JobWithModelPropertyDeclaresSerializesModelsRule>
 */
final class JobWithModelPropertyDeclaresSerializesModelsRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new JobWithModelPropertyDeclaresSerializesModelsRule(
            new EloquentModelReflectionDetector,
            $this->createReflectionProvider(),
        );
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/phpstan-test.neon'];
    }

    public function test_flags_job_with_public_model_property_without_serializes_models(): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/JobWithModelPropertyWithoutSerializesModels.php'],
            [
                [
                    'Job BoringO11y\Skystan\Tests\Fixtures\PhpStan\JobWithModelPropertyWithoutSerializesModels holds Eloquent model in public property ($product) but does not use the SerializesModels trait. The model is then serialized whole onto the queue — a bloated payload rehydrated from a stale dispatch-time snapshot. Add `use Illuminate\Queue\SerializesModels;` so each model is stored as a class+id reference and reloaded fresh when the job runs.',
                    7,
                ],
            ],
        );
    }

    public function test_flags_nullable_model_property(): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/JobWithNullableModelPropertyWithoutSerializesModels.php'],
            [
                [
                    'Job BoringO11y\Skystan\Tests\Fixtures\PhpStan\JobWithNullableModelPropertyWithoutSerializesModels holds Eloquent model in public property ($product) but does not use the SerializesModels trait. The model is then serialized whole onto the queue — a bloated payload rehydrated from a stale dispatch-time snapshot. Add `use Illuminate\Queue\SerializesModels;` so each model is stored as a class+id reference and reloaded fresh when the job runs.',
                    7,
                ],
            ],
        );
    }

    public function test_flags_only_model_properties_and_lists_them_all(): void
    {
        // Promoted public model properties are flagged; the scalar $companyId is not.
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/JobWithMultipleModelPropertiesWithoutSerializesModels.php'],
            [
                [
                    'Job BoringO11y\Skystan\Tests\Fixtures\PhpStan\JobWithMultipleModelPropertiesWithoutSerializesModels holds Eloquent models in public properties ($product, $replacement) but does not use the SerializesModels trait. The model is then serialized whole onto the queue — a bloated payload rehydrated from a stale dispatch-time snapshot. Add `use Illuminate\Queue\SerializesModels;` so each model is stored as a class+id reference and reloaded fresh when the job runs.',
                    7,
                ],
            ],
        );
    }

    public function test_does_not_flag_when_serializes_models_is_used(): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/JobWithModelPropertyWithSerializesModels.php'],
            [],
        );
    }

    public function test_does_not_flag_when_serializes_models_is_inherited(): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/JobInheritingSerializesModels.php'],
            [],
        );
    }

    public function test_does_not_flag_jobs_without_model_properties(): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/JobWithoutModelProperty.php'],
            [],
        );
    }

    public function test_does_not_flag_protected_model_property(): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/JobWithProtectedModelProperty.php'],
            [],
        );
    }

    public function test_does_not_flag_non_queued_classes_holding_models(): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/ModelHolderNotQueued.php'],
            [],
        );
    }

    public function test_does_not_flag_abstract_jobs(): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/AbstractJobWithModelProperty.php'],
            [],
        );
    }
}
