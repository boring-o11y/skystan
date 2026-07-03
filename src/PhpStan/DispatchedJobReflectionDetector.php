<?php

declare(strict_types=1);

namespace BoringO11y\Skystan\PhpStan;

use PHPStan\Reflection\ClassReflection;

/**
 * Reflection-based detector for the two facts the afterCommit-in-transaction
 * rule needs about a dispatched job:
 *   - whether it is a queued job (implements
 *     Illuminate\Contracts\Queue\ShouldQueue, directly or via an ancestor), and
 *   - whether it opts into afterCommit dispatch itself by declaring a
 *     `public $afterCommit = true;` property (which Laravel honours per-job and
 *     which makes an explicit `->afterCommit()` on the dispatch redundant).
 *
 * Both answers are cached per class name (PHPStan reuses ClassReflection
 * instances within a run).
 */
final class DispatchedJobReflectionDetector
{
    public const SHOULD_QUEUE_FQCN = 'Illuminate\\Contracts\\Queue\\ShouldQueue';

    private const AFTER_COMMIT_PROPERTY = 'afterCommit';

    /**
     * @var array<string, bool>
     */
    private array $queuedCache = [];

    /**
     * @var array<string, bool>
     */
    private array $afterCommitCache = [];

    public function isQueuedJob(ClassReflection $classReflection): bool
    {
        $name = $classReflection->getName();

        if (\array_key_exists($name, $this->queuedCache)) {
            return $this->queuedCache[$name];
        }

        if ($classReflection->isInterface() || $classReflection->isTrait()) {
            return $this->queuedCache[$name] = false;
        }

        foreach ($classReflection->getInterfaces() as $interface) {
            if ($interface->getName() === self::SHOULD_QUEUE_FQCN) {
                return $this->queuedCache[$name] = true;
            }
        }

        return $this->queuedCache[$name] = false;
    }

    /**
     * True when the job (or an ancestor) declares `$afterCommit = true`, so every
     * dispatch of it is already deferred until after the surrounding transaction
     * commits and no per-call `->afterCommit()` is needed.
     */
    public function declaresAfterCommit(ClassReflection $classReflection): bool
    {
        $name = $classReflection->getName();

        if (\array_key_exists($name, $this->afterCommitCache)) {
            return $this->afterCommitCache[$name];
        }

        $native = $classReflection->getNativeReflection();
        if (! $native->hasProperty(self::AFTER_COMMIT_PROPERTY)) {
            return $this->afterCommitCache[$name] = false;
        }

        // getProperty() resolves an inherited property too, so a base job that
        // sets the flag covers its subclasses.
        $default = $native->getProperty(self::AFTER_COMMIT_PROPERTY)->getDefaultValue();

        return $this->afterCommitCache[$name] = $default === true;
    }
}
