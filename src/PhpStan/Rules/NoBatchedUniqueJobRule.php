<?php

declare(strict_types=1);

namespace BoringO11y\Skystan\PhpStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use BoringO11y\Skystan\PhpStan\ShouldBeUniqueReflectionDetector;

/**
 * A job implementing ShouldBeUnique must not be dispatched through the bulk /
 * batch entry points — `Bus::batch([...])`, `Bus::bulk([...])` or the
 * equivalent `Queue::bulk([...])`.
 *
 * Both bypass the per-job uniqueness guarantee:
 *   - `Queue::bulk()` / `Bus::bulk()` push raw payloads straight onto the
 *     queue, skipping the dispatcher path that acquires the unique lock — so
 *     duplicates are queued and ShouldBeUnique silently does nothing.
 *   - Batching a unique job means a duplicate is dropped at dispatch, but the
 *     batch's job count is computed up-front, so the batch's progress and
 *     then/finally callbacks never reconcile and the batch can hang as "pending".
 *
 * Dispatch unique jobs individually (`Foo::dispatch(...)`).
 *
 * @implements Rule<StaticCall>
 */
final class NoBatchedUniqueJobRule implements Rule
{
    /**
     * Facade short names (alias-proof: matches both the imported
     * Illuminate\Support\Facades\Bus|Queue and a root-namespace alias).
     */
    private const FACADE_NAMES = ['Bus', 'Queue'];

    private const BULK_METHODS = ['batch', 'bulk'];

    public function __construct(
        private readonly ShouldBeUniqueReflectionDetector $detector,
    ) {}

    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (! $this->isBulkOrBatchCall($node)) {
            return [];
        }

        $jobsArg = $node->getArgs()[0] ?? null;
        if ($jobsArg === null) {
            return [];
        }

        $method = $node->name instanceof Node\Identifier ? $node->name->toString() : 'batch';

        // Array literal: inspect each element so the error points at the exact
        // offending `new Job()` and names it.
        if ($jobsArg->value instanceof Array_) {
            return $this->checkJobExpressions($jobsArg->value, $scope, $method);
        }

        // Non-literal (variable / collection): fall back to the iterable value
        // type and report once at the call site.
        return $this->checkIterableType($scope->getType($jobsArg->value), $node, $method);
    }

    private function isBulkOrBatchCall(StaticCall $node): bool
    {
        if (! $node->class instanceof Name) {
            return false;
        }
        if (! $node->name instanceof Node\Identifier
            || ! \in_array($node->name->toString(), self::BULK_METHODS, true)) {
            return false;
        }

        $shortName = $node->class->getLast();

        return \in_array($shortName, self::FACADE_NAMES, true);
    }

    /**
     * Walk an array of jobs (recursing into nested arrays, which represent
     * chains within a batch) and flag every ShouldBeUnique element.
     *
     * @return list<IdentifierRuleError>
     */
    private function checkJobExpressions(Array_ $array, Scope $scope, string $method): array
    {
        $errors = [];

        foreach ($array->items as $item) {
            if ($item === null) {
                continue;
            }

            if ($item->value instanceof Array_) {
                foreach ($this->checkJobExpressions($item->value, $scope, $method) as $error) {
                    $errors[] = $error;
                }

                continue;
            }

            $type = $scope->getType($item->value);
            if (! $this->isUniqueJobType($type)) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(\sprintf(
                'Job %s implements ShouldBeUnique and must not be dispatched via %s(). Bulk/batch dispatch bypasses the uniqueness lock — dispatch the job individually instead.',
                $this->describeJobType($type),
                $method,
            ))
                ->identifier('boringO11ySkystan.noBatchedUniqueJob')
                ->line($item->value->getStartLine())
                ->build();
        }

        return $errors;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function checkIterableType(Type $jobsType, StaticCall $node, string $method): array
    {
        if (! $jobsType->isIterable()->yes()) {
            return [];
        }

        $valueType = $jobsType->getIterableValueType();
        if (! $this->isUniqueJobType($valueType)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(\sprintf(
                'Job %s implements ShouldBeUnique and must not be dispatched via %s(). Bulk/batch dispatch bypasses the uniqueness lock — dispatch the job individually instead.',
                $this->describeJobType($valueType),
                $method,
            ))
                ->identifier('boringO11ySkystan.noBatchedUniqueJob')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    private function isUniqueJobType(Type $type): bool
    {
        $shouldBeUnique = new ObjectType(ShouldBeUniqueReflectionDetector::SHOULD_BE_UNIQUE_FQCN);

        foreach ($type->getObjectClassReflections() as $classReflection) {
            if ($this->detector->isUniqueJob($classReflection)) {
                return true;
            }
        }

        // Fallback for types that carry the interface without a resolvable
        // ClassReflection (e.g. an interface-typed variable).
        return $shouldBeUnique->isSuperTypeOf($type)->yes();
    }

    private function describeJobType(Type $type): string
    {
        $classNames = $type->getObjectClassNames();

        return $classNames === [] ? 'dispatched here' : implode('|', $classNames);
    }
}
