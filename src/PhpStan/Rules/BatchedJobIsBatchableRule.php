<?php

declare(strict_types=1);

namespace BoringO11y\Skystan\PhpStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Type;
use BoringO11y\Skystan\PhpStan\BatchableReflectionDetector;

/**
 * Every job dispatched through `Bus::batch([...])` must use the
 * Illuminate\Bus\Batchable trait.
 *
 * The batch wires each job back to its parent batch so the job can read
 * progress and short-circuit (`$this->batch()->cancelled()`), and so the batch
 * can reconcile its job count and fire then/catch/finally callbacks. All of that
 * lives in the Batchable trait. A job added to a batch without it has no
 * `batch()` method — `$this->batch()` is a fatal "call to undefined method" the
 * moment the job touches it — and the batch cannot account for it. The framework
 * does not validate this at dispatch, so the breakage only surfaces in the
 * worker.
 *
 * The rule inspects the array literal passed to `Bus::batch()` and flags every
 * element that is a queued job (implements ShouldQueue) but does not use
 * Batchable. It recurses into nested arrays, which represent chains within a
 * batch — chained jobs need the trait too.
 *
 * @implements Rule<StaticCall>
 */
final class BatchedJobIsBatchableRule implements Rule
{
    private const SHOULD_QUEUE_FQCN = 'Illuminate\\Contracts\\Queue\\ShouldQueue';

    /**
     * Facade short name (alias-proof: matches both the imported
     * Illuminate\Support\Facades\Bus and a root-namespace alias).
     */
    private const FACADE_NAME = 'Bus';

    public function __construct(
        private readonly BatchableReflectionDetector $detector,
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
        if (! $this->isBusBatchCall($node)) {
            return [];
        }

        $jobsArg = $node->getArgs()[0] ?? null;
        if ($jobsArg === null || ! $jobsArg->value instanceof Array_) {
            // Only array literals can be inspected element by element; a
            // variable / collection is left to the cancellation rule on the
            // job class itself.
            return [];
        }

        return $this->checkJobExpressions($jobsArg->value, $scope);
    }

    private function isBusBatchCall(StaticCall $node): bool
    {
        if (! $node->class instanceof Name) {
            return false;
        }
        if (! $node->name instanceof Node\Identifier || $node->name->toString() !== 'batch') {
            return false;
        }

        return $node->class->getLast() === self::FACADE_NAME;
    }

    /**
     * Walk an array of jobs (recursing into nested arrays, which represent
     * chains within a batch) and flag every queued job that lacks Batchable.
     *
     * @return list<IdentifierRuleError>
     */
    private function checkJobExpressions(Array_ $array, Scope $scope): array
    {
        $errors = [];

        foreach ($array->items as $item) {
            if ($item === null) {
                continue;
            }

            if ($item->value instanceof Array_) {
                foreach ($this->checkJobExpressions($item->value, $scope) as $error) {
                    $errors[] = $error;
                }

                continue;
            }

            $type = $scope->getType($item->value);
            if (! $this->isNonBatchableQueuedJob($type)) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(\sprintf(
                'Job %s is dispatched in Bus::batch() but does not use the Batchable trait. The job then has no $this->batch() accessor (a fatal call to an undefined method as soon as it reads the batch) and the batch cannot track it. Add `use Illuminate\Bus\Batchable;` to the job.',
                $this->describeJobType($type),
            ))
                ->identifier('boringO11ySkystan.batchedJobIsBatchable')
                ->line($item->value->getStartLine())
                ->build();
        }

        return $errors;
    }

    private function isNonBatchableQueuedJob(Type $type): bool
    {
        $reflections = $type->getObjectClassReflections();
        if ($reflections === []) {
            return false;
        }

        foreach ($reflections as $classReflection) {
            // Only constrain queued jobs — a non-job object in the array is not
            // this rule's concern.
            if (! $this->isQueuedJob($classReflection)) {
                return false;
            }
            if ($this->detector->usesBatchable($classReflection)) {
                return false;
            }
        }

        return true;
    }

    private function isQueuedJob(ClassReflection $classReflection): bool
    {
        foreach ($classReflection->getInterfaces() as $interface) {
            if ($interface->getName() === self::SHOULD_QUEUE_FQCN) {
                return true;
            }
        }

        return false;
    }

    private function describeJobType(Type $type): string
    {
        $classNames = $type->getObjectClassNames();

        return $classNames === [] ? 'dispatched here' : implode('|', $classNames);
    }
}
