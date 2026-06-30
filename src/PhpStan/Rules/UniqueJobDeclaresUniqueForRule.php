<?php

declare(strict_types=1);

namespace BoringO11y\Skystan\PhpStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use BoringO11y\Skystan\PhpStan\ShouldBeUniqueReflectionDetector;

/**
 * Every job implementing Illuminate\Contracts\Queue\ShouldBeUnique must declare
 * `uniqueFor` — either as a property (`public int $uniqueFor`) or a method
 * (`public function uniqueFor(): int`).
 *
 * Without `uniqueFor`, Laravel holds the uniqueness lock until the job finishes
 * processing. If the worker dies mid-job (OOM, deploy, fatal) the lock is never
 * released and the job can never be dispatched again until the cache key is
 * cleared by hand — a silent, hard-to-diagnose deadlock. Declaring `uniqueFor`
 * bounds the lock so a stuck job self-heals after the timeout.
 *
 * Abstract classes are skipped: they aren't dispatched directly, and a concrete
 * subclass is expected to supply `uniqueFor` (which it may inherit from the base).
 *
 * @implements Rule<InClassNode>
 */
final class UniqueJobDeclaresUniqueForRule implements Rule
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
        if ($classReflection->hasNativeProperty('uniqueFor') || $classReflection->hasNativeMethod('uniqueFor')) {
            return [];
        }

        return [
            RuleErrorBuilder::message(\sprintf(
                'Job %s implements ShouldBeUnique but does not declare uniqueFor. Add a `public int $uniqueFor` property or a `uniqueFor()` method so the uniqueness lock expires — otherwise a worker that dies mid-job leaks the lock and the job can never be re-dispatched.',
                $classReflection->getDisplayName(),
            ))
                ->identifier('boringO11ySkystan.uniqueJobUniqueFor')
                ->line($node->getStartLine())
                ->build(),
        ];
    }
}
