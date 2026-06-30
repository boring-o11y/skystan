<?php

declare(strict_types=1);

namespace BoringO11y\Skystan\PhpStan;

use PHPStan\Reflection\ClassReflection;

/**
 * Reflection-based detector. Determines whether a class is a "unique job" —
 * i.e. implements Illuminate\Contracts\Queue\ShouldBeUnique (directly or via
 * an ancestor, and including ShouldBeUniqueUntilProcessing, which extends it).
 * Cached per class name (PHPStan reuses ClassReflection instances within a run).
 */
final class ShouldBeUniqueReflectionDetector
{
    public const SHOULD_BE_UNIQUE_FQCN = 'Illuminate\\Contracts\\Queue\\ShouldBeUnique';

    /**
     * @var array<string, bool>
     */
    private array $cache = [];

    public function isUniqueJob(ClassReflection $classReflection): bool
    {
        $name = $classReflection->getName();

        if (\array_key_exists($name, $this->cache)) {
            return $this->cache[$name];
        }

        if ($classReflection->isInterface() || $classReflection->isTrait()) {
            return $this->cache[$name] = false;
        }

        foreach ($classReflection->getInterfaces() as $interface) {
            if ($interface->getName() === self::SHOULD_BE_UNIQUE_FQCN) {
                return $this->cache[$name] = true;
            }
        }

        return $this->cache[$name] = false;
    }
}
