<?php

declare(strict_types=1);

namespace BoringO11y\Skystan\PhpStan;

use PHPStan\Reflection\ClassReflection;

/**
 * Reflection-based detector. Determines whether a class is an Eloquent model —
 * i.e. it is Illuminate\Database\Eloquent\Model itself or extends it (so the
 * project's App\Model / App\BaseModel and every concrete domain model qualify).
 * Cached per class name (PHPStan reuses ClassReflection instances within a run).
 */
final class EloquentModelReflectionDetector
{
    public const MODEL_FQCN = 'Illuminate\\Database\\Eloquent\\Model';

    /**
     * @var array<string, bool>
     */
    private array $cache = [];

    public function isModel(ClassReflection $classReflection): bool
    {
        $name = $classReflection->getName();

        if (\array_key_exists($name, $this->cache)) {
            return $this->cache[$name];
        }

        if ($classReflection->isInterface() || $classReflection->isTrait()) {
            return $this->cache[$name] = false;
        }

        if ($name === self::MODEL_FQCN) {
            return $this->cache[$name] = true;
        }

        return $this->cache[$name] = $classReflection->isSubclassOf(self::MODEL_FQCN);
    }
}
