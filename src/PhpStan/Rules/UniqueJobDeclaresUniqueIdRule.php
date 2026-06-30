<?php

declare(strict_types=1);

namespace BoringO11y\Skystan\PhpStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use BoringO11y\Skystan\PhpStan\ShouldBeUniqueReflectionDetector;

/**
 * A *parameterized* job implementing ShouldBeUnique must declare `uniqueId` —
 * either as a method (`public function uniqueId(): string`) or a property
 * (`public $uniqueId`).
 *
 * Laravel builds the uniqueness lock key as
 * `laravel_unique_job:<class>:<uniqueId>` and falls back to an empty `uniqueId`
 * when neither is declared (see Illuminate\Bus\UniqueLock::getKey). For a job
 * that carries no distinguishing state that empty key is correct — the class is
 * a singleton, only one may run at a time. But for a job whose constructor takes
 * arguments (per-company, per-yacht, …) the empty key collapses *every* dispatch
 * into one unique job regardless of those arguments, so legitimately-distinct
 * jobs are silently dropped at dispatch with no error. That lost-work failure is
 * harder to spot than a leaked lock.
 *
 * The rule therefore fires only when the job has a constructor with at least one
 * parameter. A genuinely class-wide unique job satisfies it by declaring
 * `uniqueId()` returning a constant, which makes the class-wide intent explicit.
 *
 * Abstract classes are skipped — a concrete subclass supplies (or inherits) the
 * constructor and uniqueId.
 *
 * @implements Rule<InClassNode>
 */
final class UniqueJobDeclaresUniqueIdRule implements Rule
{
    public function __construct(
        private readonly ShouldBeUniqueReflectionDetector $detector,
    ) {}

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $node->getClassReflection();

        if ($classReflection->isAbstract()) {
            return [];
        }
        if (! $this->detector->isUniqueJob($classReflection)) {
            return [];
        }
        if (! $this->hasConstructorParameters($classReflection)) {
            // Parameterless job: the class-name-only lock key is correct.
            return [];
        }
        if ($classReflection->hasNativeMethod('uniqueId') || $classReflection->hasNativeProperty('uniqueId')) {
            return [];
        }

        return [
            RuleErrorBuilder::message(\sprintf(
                'Job %s implements ShouldBeUnique and is parameterized but does not declare uniqueId. The unique lock key then falls back to the class name alone, so every dispatch — whatever the constructor arguments — collapses into one unique job and distinct jobs are silently dropped. Add a uniqueId() method (or $uniqueId property) derived from the distinguishing arguments; for an intentionally class-wide job, return a constant to make that explicit.',
                $classReflection->getDisplayName(),
            ))
                ->identifier('boringO11ySkystan.uniqueJobUniqueId')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    private function hasConstructorParameters(ClassReflection $classReflection): bool
    {
        if (! $classReflection->hasConstructor()) {
            return false;
        }

        $variants = $classReflection->getConstructor()->getVariants();

        return $variants !== [] && $variants[0]->getParameters() !== [];
    }
}
