# skystan

[![Latest Version](https://img.shields.io/packagist/v/boring-o11y/skystan.svg)](https://packagist.org/packages/boring-o11y/skystan)
[![License](https://img.shields.io/packagist/l/boring-o11y/skystan.svg)](LICENSE)

PHPStan / [Larastan](https://github.com/larastan/larastan) rules for **queued Laravel jobs**.

Laravel's queue contracts (`ShouldBeUnique`, `Batchable`, `SerializesModels`) are
easy to declare half-way and get subtly wrong. The failures are nasty precisely
because they're *silent*: a uniqueness lock that never releases, distinct jobs
collapsing into one and being dropped at dispatch, a batch that hangs forever
"pending", heavy work running for a batch the caller already cancelled, a stale
model rehydrated from a bloated payload. None of these throw at dispatch — they
surface in production, in the worker, hours later.

`skystan` encodes those contracts as static-analysis rules so the mistakes are
caught in CI instead.

## Requirements

- PHP `^8.2`
- PHPStan `^2.0` (works great alongside [Larastan](https://github.com/larastan/larastan))

## Installation

```sh
composer require --dev boring-o11y/skystan
```

### Registering the rules

If you use [`phpstan/extension-installer`](https://github.com/phpstan/phpstan-extension-installer)
(recommended), there is **nothing else to do** — the rules register
automatically.

Otherwise, include the bundled `extension.neon` from your `phpstan.neon`
(or `phpstan.neon.dist`):

```neon
includes:
    - vendor/boring-o11y/skystan/extension.neon
```

That's it — the rules now run as part of your normal analysis:

```sh
vendor/bin/phpstan analyse
```

## Rules

All rules are enabled once the extension is registered. Each emits a stable
[identifier](https://phpstan.org/user-guide/ignoring-errors#ignoring-by-identifier)
you can use to ignore individual findings.

| Rule | Identifier | In one line |
| --- | --- | --- |
| [UniqueJobDeclaresUniqueForRule](#uniquejobdeclaresuniqueforrule) | `boringO11ySkystan.uniqueJobUniqueFor` | `ShouldBeUnique` jobs must declare `uniqueFor` |
| [UniqueJobDeclaresUniqueIdRule](#uniquejobdeclaresuniqueidrule) | `boringO11ySkystan.uniqueJobUniqueId` | Parameterized `ShouldBeUnique` jobs must declare `uniqueId` |
| [NoBatchedUniqueJobRule](#nobatcheduniquejobrule) | `boringO11ySkystan.noBatchedUniqueJob` | `ShouldBeUnique` jobs must not be batched / bulk-dispatched |
| [JobWithModelPropertyDeclaresSerializesModelsRule](#jobwithmodelpropertydeclaresserializesmodelsrule) | `boringO11ySkystan.jobSerializesModels` | Jobs with a public model property must use `SerializesModels` |
| [BatchedJobIsBatchableRule](#batchedjobisbatchablerule) | `boringO11ySkystan.batchedJobIsBatchable` | Jobs added to `Bus::batch()` must use `Batchable` |
| [BatchableJobChecksCancellationRule](#batchablejobcheckscancellationrule) | `boringO11ySkystan.batchableJobChecksCancellation` | `Batchable` jobs must honour batch cancellation |

---

### UniqueJobDeclaresUniqueForRule

`boringO11ySkystan.uniqueJobUniqueFor`

Every job implementing `Illuminate\Contracts\Queue\ShouldBeUnique` (including
`ShouldBeUniqueUntilProcessing`, which extends it) must declare `uniqueFor` —
either as a property (`public int $uniqueFor = 3600;`) or a method
(`public function uniqueFor(): int`).

Without `uniqueFor` the uniqueness lock is held until the job finishes
processing. If a worker dies mid-job (OOM, deploy, fatal) the lock is never
released and the job can never be dispatched again until the cache key is
cleared by hand — a silent deadlock. `uniqueFor` bounds the lock so a stuck job
self-heals after the timeout.

Abstract classes are skipped — they aren't dispatched directly, and a concrete
subclass supplies (or inherits) `uniqueFor`.

```php
// flagged
class FetchSocialAvatar implements ShouldQueue, ShouldBeUnique
{
    public function uniqueId(): string { return (string) $this->userId; }
}

// ok
class FetchSocialAvatar implements ShouldQueue, ShouldBeUnique
{
    public int $uniqueFor = 3600;
    public function uniqueId(): string { return (string) $this->userId; }
}
```

---

### UniqueJobDeclaresUniqueIdRule

`boringO11ySkystan.uniqueJobUniqueId`

A **parameterized** `ShouldBeUnique` job (one whose constructor takes arguments)
must declare `uniqueId` — a method (`public function uniqueId(): string`) or a
property (`public $uniqueId`).

Laravel builds the lock key as `laravel_unique_job:<class>:<uniqueId>` and falls
back to an empty `uniqueId` when neither is declared
(`Illuminate\Bus\UniqueLock::getKey`). For a parameterized job the empty key
collapses *every* dispatch into one unique job regardless of its arguments, so
legitimately-distinct jobs (per-company, per-yacht, …) are silently dropped at
dispatch with no error — a lost-work failure that's harder to spot than a leaked
lock.

The rule fires only when the constructor has at least one parameter: a
parameterless job is a legitimate singleton whose class-name-only key is correct.
A job that is intentionally class-wide satisfies the rule by declaring
`uniqueId()` returning a constant, making that intent explicit. Abstract classes
are skipped.

```php
// flagged — parameterized, no uniqueId, so all companies share one lock
class SyncCompany implements ShouldQueue, ShouldBeUnique
{
    public int $uniqueFor = 3600;
    public function __construct(public int $companyId) {}
}

// ok — uniqueId scopes the lock per company
class SyncCompany implements ShouldQueue, ShouldBeUnique
{
    public int $uniqueFor = 3600;
    public function __construct(public int $companyId) {}
    public function uniqueId(): string { return (string) $this->companyId; }
}
```

---

### NoBatchedUniqueJobRule

`boringO11ySkystan.noBatchedUniqueJob`

A `ShouldBeUnique` job must not be dispatched through the bulk / batch entry
points — `Bus::batch([...])`, `Bus::bulk([...])` or the equivalent
`Queue::bulk([...])`. Both bypass the per-job uniqueness guarantee:

- `Queue::bulk()` / `Bus::bulk()` push raw payloads straight onto the queue,
  skipping the dispatcher path that acquires the unique lock — so duplicates are
  queued and `ShouldBeUnique` silently does nothing.
- Batching a unique job means a duplicate is dropped at dispatch, but the batch's
  job count is computed up-front, so the batch's progress and `then`/`finally`
  callbacks never reconcile and the batch can hang as "pending".

Dispatch unique jobs individually (`Foo::dispatch(...)`). The rule recurses into
nested arrays (chains within a batch) and reports each offending job.

```php
// flagged — UniqueJobWithUniqueForProperty is ShouldBeUnique
Bus::batch([
    new UniqueJobWithUniqueForProperty,
    new RegularJob,
]);
```

---

### JobWithModelPropertyDeclaresSerializesModelsRule

`boringO11ySkystan.jobSerializesModels`

A queued job (implements `Illuminate\Contracts\Queue\ShouldQueue`) that holds an
Eloquent model in a **public** property must use the
`Illuminate\Queue\SerializesModels` trait.

A queued job is serialized to the queue store at dispatch and unserialized in the
worker. Without `SerializesModels` an Eloquent model property is serialized
whole: the full attribute set, loaded relations and casts go onto the wire —
bloating the payload — and the job runs against a frozen snapshot taken at
dispatch time, so any change made between dispatch and execution is silently
lost. `SerializesModels` instead stores just the class name + primary key (and
the loaded relation names) and re-resolves the model fresh from the database when
the job runs, keeping the payload small and the data current. A model that was
deleted in the meantime then surfaces as a `ModelNotFoundException` instead of
operating on stale data.

The rule fires only for public properties — the queue serialization boundary
makes public state the concern; private/protected model state is the class's own
business. Properties typed against a model (including nullable / `Model|null`
unions) count; an inherited `SerializesModels` (used by the class, a parent, or
another trait) satisfies the rule. Abstract classes are skipped.

```php
// flagged — public model property, no SerializesModels
class SendInvoice implements ShouldQueue
{
    public function __construct(public Invoice $invoice) {}
    public function handle(): void { /* ... */ }
}

// ok — model is stored as class+id and reloaded fresh
class SendInvoice implements ShouldQueue
{
    use SerializesModels;

    public function __construct(public Invoice $invoice) {}
    public function handle(): void { /* ... */ }
}
```

---

### BatchedJobIsBatchableRule

`boringO11ySkystan.batchedJobIsBatchable`

Every job dispatched through `Bus::batch([...])` must use the
`Illuminate\Bus\Batchable` trait. The batch wires each job back to its parent
batch so the job can read progress and short-circuit
(`$this->batch()->cancelled()`), and so the batch can reconcile its job count and
fire `then`/`catch`/`finally`. All of that lives in `Batchable`. A job added to a
batch without it has no `batch()` method — `$this->batch()` is a fatal "call to
undefined method" the moment the job touches it. The framework does not validate
this at dispatch, so the breakage only surfaces in the worker.

The rule inspects the array literal passed to `Bus::batch()` and flags every
element that is a queued job (`ShouldQueue`) but does not use `Batchable`,
recursing into nested arrays (chains within a batch).

```php
// flagged — RegularJob is ShouldQueue but does not use Batchable
Bus::batch([
    new BatchableJob,
    new RegularJob,
]);
```

---

### BatchableJobChecksCancellationRule

`boringO11ySkystan.batchableJobChecksCancellation`

A queued job that uses the `Illuminate\Bus\Batchable` trait must respect early
batch cancellation — either by checking `$this->batch()?->cancelled()` at the
start of `handle()`, or by registering the
`Illuminate\Queue\Middleware\SkipIfBatchCancelled` middleware from `middleware()`.

Cancelling a batch (`$batch->cancel()`, or the automatic cancel on first failure
when the batch is not `allowFailures`) only stops *future* dispatches from
running their body — Laravel does not forcibly kill jobs already on the queue.
Each queued job still wakes up and, unless it checks `cancelled()`, runs its full
body: wasted work at best, and at worst it keeps mutating state (writing files,
calling external APIs, charging cards) for a batch the caller has already
abandoned.

To report the requirement once per hierarchy at its source, the rule fires on the
first concrete class in the chain that carries `Batchable` — a concrete subclass
whose parent already has the trait is skipped (the guard belongs on, or is
inherited from, that ancestor). Abstract classes are skipped. The guard is
detected by inspecting the class under analysis for a `cancelled()` call or a
`SkipIfBatchCancelled` reference, so centralising the skip middleware on a
concrete base satisfies the whole hierarchy.

```php
// flagged — uses Batchable but handle() ignores cancellation
class GenerateReport implements ShouldQueue
{
    use Batchable;
    public function handle(): void { /* ... heavy work ... */ }
}

// ok — guards at the start of handle()
class GenerateReport implements ShouldQueue
{
    use Batchable;
    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }
        // ... heavy work ...
    }
}

// ok — skip middleware short-circuits cancelled batches
class GenerateReport implements ShouldQueue
{
    use Batchable;
    public function middleware(): array { return [new SkipIfBatchCancelled]; }
    public function handle(): void { /* ... heavy work ... */ }
}
```

## Ignoring a finding

Every rule emits a stable identifier, so you can silence a one-off without
turning the rule off globally:

```php
class LegacyJob implements ShouldQueue, ShouldBeUnique
{
    /** @phpstan-ignore boringO11ySkystan.uniqueJobUniqueFor */
    public function uniqueId(): string { return (string) $this->id; }
}
```

or project-wide in `phpstan.neon`:

```neon
parameters:
    ignoreErrors:
        - identifier: boringO11ySkystan.uniqueJobUniqueFor
          path: app/Jobs/Legacy/*
```

## Testing

The test suite is built on PHPStan's `RuleTestCase`. Minimal stubs for the
framework contracts and the `Bus` / `Queue` facades live under `tests/Stubs`, so
the rules are exercised without pulling in `laravel/framework`.

```sh
composer install
composer test
# or: vendor/bin/phpunit
```

## Contributing

Issues and pull requests are welcome at
<https://github.com/boring-o11y/skystan>. Please add or update a `RuleTestCase`
test for any behaviour change.

## License

[MIT](LICENSE) © Boring Observability
