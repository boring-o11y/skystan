<?php

declare(strict_types=1);

namespace BoringO11y\Skystan\PhpStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use BoringO11y\Skystan\PhpStan\EloquentModelReflectionDetector;

/**
 * A queued job (implements Illuminate\Contracts\Queue\ShouldQueue) that holds an
 * Eloquent model in a public property must use the
 * Illuminate\Queue\SerializesModels trait.
 *
 * A queued job is serialized to the queue store at dispatch and unserialized in
 * the worker. Without SerializesModels an Eloquent model property is serialized
 * whole: the full attribute set, loaded relations and casts go onto the wire —
 * bloating the payload — and the job runs against a frozen snapshot taken at
 * dispatch time, so any change made between dispatch and execution is silently
 * lost. SerializesModels instead stores just the class name + primary key (and
 * the loaded relation names) and re-resolves the model fresh from the database
 * when the job runs, keeping the payload small and the data current. A model
 * that was deleted in the meantime then surfaces as a ModelNotFoundException
 * instead of operating on stale data.
 *
 * The rule fires only for public properties — Livewire/queue serialization and
 * the client/worker boundary make public state the concern; private/protected
 * model state is the class's own business. Properties typed against a model
 * (including nullable/`Model|null` unions) count; an inherited SerializesModels
 * (used by the class, a parent, or another trait) satisfies the rule.
 *
 * Abstract classes are skipped: they aren't dispatched directly, and a concrete
 * subclass is expected to supply the trait (which it may inherit from the base).
 *
 * @implements Rule<InClassNode>
 */
final class JobWithModelPropertyDeclaresSerializesModelsRule implements Rule
{
    private const SHOULD_QUEUE_FQCN = 'Illuminate\\Contracts\\Queue\\ShouldQueue';

    private const SERIALIZES_MODELS_FQCN = 'Illuminate\\Queue\\SerializesModels';

    public function __construct(
        private readonly EloquentModelReflectionDetector $detector,
        private readonly ReflectionProvider $reflectionProvider,
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
        if ($this->usesSerializesModels($classReflection)) {
            return [];
        }

        $modelProperties = $this->modelTypedPublicProperties($classReflection);
        if ($modelProperties === []) {
            return [];
        }

        return [
            RuleErrorBuilder::message(\sprintf(
                'Job %s holds Eloquent model%s in public propert%s (%s) but does not use the SerializesModels trait. The model is then serialized whole onto the queue — a bloated payload rehydrated from a stale dispatch-time snapshot. Add `use Illuminate\Queue\SerializesModels;` so each model is stored as a class+id reference and reloaded fresh when the job runs.',
                $classReflection->getDisplayName(),
                \count($modelProperties) === 1 ? '' : 's',
                \count($modelProperties) === 1 ? 'y' : 'ies',
                implode(', ', array_map(static fn (string $name): string => '$' . $name, $modelProperties)),
            ))
                ->identifier('boringO11ySkystan.jobSerializesModels')
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

    private function usesSerializesModels(ClassReflection $classReflection): bool
    {
        // getTraits(true) resolves traits used by the class, its parent classes,
        // and traits used by those traits — so an inherited trait counts.
        foreach ($classReflection->getTraits(true) as $trait) {
            if ($trait->getName() === self::SERIALIZES_MODELS_FQCN) {
                return true;
            }
        }

        return false;
    }

    /**
     * Public, non-static properties declared on this class whose type references
     * an Eloquent model. Only properties declared here are considered — an
     * inherited property is the declaring class's responsibility, so each missing
     * trait is reported once at its source rather than again on every subclass.
     *
     * @return list<string>
     */
    private function modelTypedPublicProperties(ClassReflection $classReflection): array
    {
        $names = [];

        foreach ($classReflection->getNativeReflection()->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }
            if ($property->getDeclaringClass()->getName() !== $classReflection->getName()) {
                continue;
            }

            $type = $classReflection->getNativeProperty($property->getName())->getReadableType();
            if ($this->typeReferencesModel($type)) {
                $names[] = $property->getName();
            }
        }

        return $names;
    }

    private function typeReferencesModel(Type $type): bool
    {
        // A union carrying null reports no object class names, so strip it first;
        // getObjectClassNames() then flattens `Model` / `Foo|Bar` member by member.
        foreach (TypeCombinator::removeNull($type)->getObjectClassNames() as $className) {
            if ($this->reflectionProvider->hasClass($className)
                && $this->detector->isModel($this->reflectionProvider->getClass($className))) {
                return true;
            }
        }

        return false;
    }
}
