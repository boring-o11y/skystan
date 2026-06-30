<?php

declare(strict_types=1);

namespace BoringO11y\Skystan\PhpStan;

use PHPStan\Reflection\ClassReflection;

/**
 * Reflection-based detector. Determines whether a class is "batchable" — i.e.
 * uses the Illuminate\Bus\Batchable trait (directly, via a parent class, or via
 * another trait). A job must use this trait to be added to a batch: without it
 * `$this->batch()` is undefined and the batch machinery cannot track the job.
 * Cached per class name (PHPStan reuses ClassReflection instances within a run).
 */
final class BatchableReflectionDetector
{
    public const BATCHABLE_FQCN = 'Illuminate\\Bus\\Batchable';

    /**
     * @var array<string, bool>
     */
    private array $cache = [];

    public function usesBatchable(ClassReflection $classReflection): bool
    {
        $name = $classReflection->getName();

        if (\array_key_exists($name, $this->cache)) {
            return $this->cache[$name];
        }

        // getTraits(true) resolves traits used by the class, its parent classes,
        // and traits used by those traits — so an inherited Batchable counts.
        foreach ($classReflection->getTraits(true) as $trait) {
            if ($trait->getName() === self::BATCHABLE_FQCN) {
                return $this->cache[$name] = true;
            }
        }

        return $this->cache[$name] = false;
    }
}
