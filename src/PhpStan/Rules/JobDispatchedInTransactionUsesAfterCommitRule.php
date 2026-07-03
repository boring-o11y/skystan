<?php

declare(strict_types=1);

namespace BoringO11y\Skystan\PhpStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use BoringO11y\Skystan\PhpStan\DispatchedJobReflectionDetector;

/**
 * A queued job dispatched inside a `DB::transaction(...)` closure must defer its
 * dispatch until the transaction commits — either by chaining `->afterCommit()`
 * on the dispatch, or by declaring `public bool $afterCommit = true;` on the job.
 *
 * A queued job pushed during an open transaction can be picked up by a worker
 * before the transaction commits (a fast worker racing the still-open DB
 * connection) — the job then loads rows that aren't visible yet and fails, or
 * silently operates on a half-written state. Worse, if the transaction rolls
 * back the job still runs against data that no longer exists. `afterCommit`
 * holds the dispatch until the outermost transaction commits and drops it
 * entirely on rollback.
 *
 * Scope and limits:
 *   - Only the `DB::transaction(Closure)` / arrow-fn form is inspected — the
 *     manual `DB::beginTransaction()` ... `DB::commit()` form has no closure to
 *     bound the analysis and is not covered.
 *   - Only the chainable dispatch forms are flagged: `Job::dispatch(...)` and the
 *     `dispatch(new Job)` helper. Synchronous dispatch (`dispatchSync`,
 *     `dispatch_sync`) runs inline within the transaction by design, and the
 *     `Bus`/`Queue` facade entry points are a different mechanism — both are left
 *     alone.
 *   - Non-queued dispatchables are ignored: they run synchronously, so the
 *     commit race does not apply.
 *   - `->afterCommit()` only counts as protection when it is syntactically
 *     chained on the dispatch (`Job::dispatch()->afterCommit()`); splitting it
 *     across statements (`$p = Job::dispatch(); $p->afterCommit();`) is not
 *     recognised and is reported.
 *   - The walk descends into nested closures, so a dispatch inside a callback
 *     that is merely *registered* within the transaction rather than run
 *     synchronously (`DB::afterCommit(fn () => Job::dispatch())`,
 *     `Event::listen(...)`) is reported even though it doesn't race the commit.
 *     These are rare; chain `->afterCommit()` or move the dispatch to silence it.
 *   - The rule assumes the default queue config (`after_commit` not globally
 *     enabled); a project that turns that on globally does not need this rule.
 *
 * @implements Rule<StaticCall>
 */
final class JobDispatchedInTransactionUsesAfterCommitRule implements Rule
{
    private const DB_FACADE = 'DB';

    private const TRANSACTION_METHOD = 'transaction';

    private const DISPATCH_METHOD = 'dispatch';

    private const AFTER_COMMIT_METHOD = 'afterCommit';

    /**
     * Facades whose `dispatch` is a different mechanism than the job's own
     * `Job::dispatch()` static helper and is therefore not flagged here.
     */
    private const NON_JOB_FACADES = ['Bus', 'Queue'];

    /**
     * Static-call class names that don't name a concrete, resolvable job class.
     */
    private const NON_RESOLVABLE_CLASS_NAMES = ['self', 'static', 'parent'];

    public function __construct(
        private readonly DispatchedJobReflectionDetector $detector,
        private readonly ReflectionProvider $reflectionProvider,
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
        if (! $this->isTransactionCall($node)) {
            return [];
        }

        $callback = $node->getArgs()[0] ?? null;
        if ($callback === null) {
            return [];
        }

        $bodyNodes = $this->closureBody($callback->value);
        if ($bodyNodes === []) {
            return [];
        }

        $dispatches = [];
        $protected = [];
        foreach ($bodyNodes as $bodyNode) {
            $this->visit($bodyNode, $dispatches, $protected);
        }

        $errors = [];
        foreach ($dispatches as $dispatch) {
            if (isset($protected[\spl_object_id($dispatch)])) {
                continue;
            }

            $job = $this->dispatchedJobNeedingAfterCommit($dispatch, $scope);
            if ($job === null) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(\sprintf(
                'Job %s is dispatched inside DB::transaction() without ->afterCommit(). A worker can pick it up before the transaction commits — so it runs against rows not yet visible — and if the transaction rolls back the job still runs against data that never existed. Chain ->afterCommit() on the dispatch, or declare `public bool $afterCommit = true;` on the job.',
                $job->getDisplayName(),
            ))
                ->identifier('boringO11ySkystan.dispatchInTransactionAfterCommit')
                ->line($dispatch->getStartLine())
                ->build();
        }

        return $errors;
    }

    private function isTransactionCall(StaticCall $node): bool
    {
        if (! $node->class instanceof Name) {
            return false;
        }
        if (! $node->name instanceof Node\Identifier || $node->name->toString() !== self::TRANSACTION_METHOD) {
            return false;
        }

        return $node->class->getLast() === self::DB_FACADE;
    }

    /**
     * The statements/expression that make up the transaction callback's body, or
     * an empty list when the callback isn't an inspectable closure literal.
     *
     * @return list<Node>
     */
    private function closureBody(Expr $callback): array
    {
        if ($callback instanceof Closure) {
            return array_values($callback->stmts);
        }
        if ($callback instanceof ArrowFunction) {
            return [$callback->expr];
        }

        return [];
    }

    /**
     * Walk the callback body, recording every dispatch call and the object ids of
     * those already guarded by a `->afterCommit()` in their method chain. Nested
     * `DB::transaction()` calls are pruned — their own dispatches are reported by
     * that inner call's own analysis pass, so descending here would double-report.
     *
     * @param  list<Node>  $dispatches
     * @param  array<int, true>  $protected
     */
    private function visit(Node $node, array &$dispatches, array &$protected): void
    {
        if ($node instanceof StaticCall && $this->isTransactionCall($node)) {
            return;
        }

        if ($this->isAfterCommitCall($node)) {
            $guarded = $this->dispatchInReceiverChain($node);
            if ($guarded !== null) {
                $protected[\spl_object_id($guarded)] = true;
            }
        }

        if ($this->isDispatchCall($node)) {
            $dispatches[] = $node;
        }

        foreach ($node->getSubNodeNames() as $subNodeName) {
            $sub = $node->{$subNodeName};
            $children = \is_array($sub) ? $sub : [$sub];

            foreach ($children as $child) {
                if ($child instanceof Node) {
                    $this->visit($child, $dispatches, $protected);
                }
            }
        }
    }

    private function isAfterCommitCall(Node $node): bool
    {
        return ($node instanceof MethodCall || $node instanceof NullsafeMethodCall)
            && $node->name instanceof Node\Identifier
            && $node->name->toString() === self::AFTER_COMMIT_METHOD;
    }

    private function isDispatchCall(Node $node): bool
    {
        if ($node instanceof FuncCall) {
            return $node->name instanceof Name && $node->name->getLast() === self::DISPATCH_METHOD;
        }

        if ($node instanceof StaticCall) {
            if (! $node->name instanceof Node\Identifier || $node->name->toString() !== self::DISPATCH_METHOD) {
                return false;
            }
            if (! $node->class instanceof Name) {
                return false;
            }

            return ! \in_array($node->class->getLast(), self::NON_JOB_FACADES, true);
        }

        return false;
    }

    /**
     * Descend a method chain ending in `->afterCommit()` and return the dispatch
     * call it is applied to, e.g. the `Job::dispatch()` in
     * `Job::dispatch()->onQueue('x')->afterCommit()`.
     */
    private function dispatchInReceiverChain(Node $afterCommitCall): ?Node
    {
        $current = $afterCommitCall;
        while ($current instanceof MethodCall || $current instanceof NullsafeMethodCall) {
            $receiver = $current->var;
            if ($this->isDispatchCall($receiver)) {
                return $receiver;
            }
            $current = $receiver;
        }

        return null;
    }

    /**
     * Resolve the dispatched job and return its reflection when it is a queued
     * job that does not already opt into afterCommit — i.e. when the dispatch
     * needs an explicit `->afterCommit()`. Returns null when the job can't be
     * resolved, isn't queued, or already declares `$afterCommit = true`.
     */
    private function dispatchedJobNeedingAfterCommit(Node $dispatch, Scope $scope): ?ClassReflection
    {
        foreach ($this->dispatchedJobReflections($dispatch, $scope) as $reflection) {
            if ($this->detector->isQueuedJob($reflection) && ! $this->detector->declaresAfterCommit($reflection)) {
                return $reflection;
            }
        }

        return null;
    }

    /**
     * @return list<ClassReflection>
     */
    private function dispatchedJobReflections(Node $dispatch, Scope $scope): array
    {
        if ($dispatch instanceof StaticCall && $dispatch->class instanceof Name) {
            $className = $dispatch->class->toString();
            if (\in_array(\strtolower($className), self::NON_RESOLVABLE_CLASS_NAMES, true)) {
                return [];
            }
            if (! $this->reflectionProvider->hasClass($className)) {
                return [];
            }

            return [$this->reflectionProvider->getClass($className)];
        }

        if ($dispatch instanceof FuncCall) {
            $jobArg = $dispatch->getArgs()[0] ?? null;
            if ($jobArg === null) {
                return [];
            }

            return $scope->getType($jobArg->value)->getObjectClassReflections();
        }

        return [];
    }
}
