<?php

declare(strict_types=1);

namespace BoringO11y\Skystan\PhpStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use BoringO11y\Skystan\PhpStan\BatchableReflectionDetector;

/**
 * A queued job (implements ShouldQueue) that uses the Illuminate\Bus\Batchable
 * trait must respect early batch cancellation — either by checking
 * `$this->batch()?->cancelled()` at the start of handle(), or by registering the
 * Illuminate\Queue\Middleware\SkipIfBatchCancelled middleware from middleware().
 *
 * Cancelling a batch (`$batch->cancel()`, or the automatic cancel on first
 * failure when the batch is not `allowFailures`) only stops *future* dispatches
 * from running their body — Laravel does not forcibly kill jobs already on the
 * queue. Each queued job still wakes up and, unless it checks `cancelled()`,
 * runs its full body: wasted work at best, and at worst it keeps mutating state
 * (writing files, calling external APIs, charging cards) for a batch the caller
 * has already abandoned. The check turns "stop the batch" into "stop the batch
 * promptly".
 *
 * To report the requirement once per hierarchy at its source, the rule fires on
 * the first concrete class in the chain that carries Batchable — a concrete
 * subclass whose parent already has the trait is skipped (the guard belongs on,
 * or is inherited from, that ancestor). Abstract classes are skipped: they are
 * not dispatched directly, and a concrete subclass supplies (or inherits) the
 * guard.
 *
 * @implements Rule<InClassNode>
 */
final class BatchableJobChecksCancellationRule implements Rule
{
    private const SHOULD_QUEUE_FQCN = 'Illuminate\\Contracts\\Queue\\ShouldQueue';

    /**
     * Short name of Illuminate\Queue\Middleware\SkipIfBatchCancelled — matched
     * alias-proof against both the imported class and a root-namespace alias.
     */
    private const SKIP_MIDDLEWARE_SHORT = 'SkipIfBatchCancelled';

    public function __construct(
        private readonly BatchableReflectionDetector $detector,
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
        if (! $this->isQueuedJob($classReflection)) {
            return [];
        }
        if (! $this->detector->usesBatchable($classReflection)) {
            return [];
        }
        if ($this->concreteAncestorUsesBatchable($classReflection)) {
            // A concrete ancestor already carries Batchable and owns the guard —
            // report there, not again on every subclass.
            return [];
        }
        if ($this->guardsCancellation($node)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(\sprintf(
                'Job %s uses the Batchable trait but never checks whether its batch has been cancelled. A cancelled batch does not kill jobs already queued — each still runs its full body, wasting work and mutating state for an abandoned batch. Guard the work with `if ($this->batch()?->cancelled()) { return; }` at the start of handle(), or register the Illuminate\Queue\Middleware\SkipIfBatchCancelled middleware from middleware().',
                $classReflection->getDisplayName(),
            ))
                ->identifier('boringO11ySkystan.batchableJobChecksCancellation')
                ->line($node->getStartLine())
                ->build(),
        ];
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

    private function concreteAncestorUsesBatchable(ClassReflection $classReflection): bool
    {
        foreach ($classReflection->getParents() as $parent) {
            if (! $parent->isAbstract() && $this->detector->usesBatchable($parent)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The class satisfies the requirement when its own body either calls
     * `cancelled()` (the `$this->batch()?->cancelled()` guard in handle()) or
     * references the SkipIfBatchCancelled middleware (registered from
     * middleware()). Inspecting the AST of the class under analysis keeps the
     * check local and deterministic.
     */
    private function guardsCancellation(InClassNode $node): bool
    {
        $finder = new NodeFinder;
        $statements = $node->getOriginalNode()->stmts;

        $cancelledCall = $finder->findFirst($statements, static fn (Node $n): bool => ($n instanceof MethodCall || $n instanceof NullsafeMethodCall)
            && $n->name instanceof Node\Identifier
            && $n->name->toString() === 'cancelled');
        if ($cancelledCall !== null) {
            return true;
        }

        $skipMiddleware = $finder->findFirst($statements, static fn (Node $n): bool => $n instanceof Name && $n->getLast() === self::SKIP_MIDDLEWARE_SHORT);

        return $skipMiddleware !== null;
    }
}
