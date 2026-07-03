<?php

namespace BoringO11y\Skystan\Tests\Fixtures\PhpStan;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

class TransactionDispatcher
{
    public function dispatchesQueuedJobWithoutAfterCommit(): void
    {
        DB::transaction(function () {
            RegularJob::dispatch();
        });
    }

    public function dispatchesViaHelperWithoutAfterCommit(): void
    {
        DB::transaction(function () {
            dispatch(new RegularJob);
        });
    }

    public function arrowFnDispatchWithoutAfterCommit(): void
    {
        DB::transaction(fn () => RegularJob::dispatch());
    }

    public function dispatchesInsideNestedTransaction(): void
    {
        // Reported exactly once — the inner transaction's own pass flags it and
        // the outer pass prunes the nested transaction to avoid a double report.
        DB::transaction(function () {
            DB::transaction(function () {
                RegularJob::dispatch();
            });
        });
    }

    public function dispatchesWithAfterCommit(): void
    {
        DB::transaction(function () {
            RegularJob::dispatch()->afterCommit();
        });
    }

    public function dispatchesWithAfterCommitDeeperInChain(): void
    {
        DB::transaction(function () {
            RegularJob::dispatch()->onQueue('emails')->afterCommit();
        });
    }

    public function dispatchesHelperWithAfterCommit(): void
    {
        DB::transaction(function () {
            dispatch(new RegularJob)->afterCommit();
        });
    }

    public function dispatchesWithNullsafeAfterCommit(): void
    {
        DB::transaction(function () {
            dispatch(new RegularJob)?->afterCommit();
        });
    }

    public function arrowFnDispatchWithAfterCommit(): void
    {
        DB::transaction(fn () => RegularJob::dispatch()->afterCommit());
    }

    public function dispatchesJobThatDeclaresAfterCommit(): void
    {
        DB::transaction(function () {
            JobWithAfterCommitProperty::dispatch();
        });
    }

    public function dispatchesJobInheritingAfterCommit(): void
    {
        DB::transaction(function () {
            JobInheritingAfterCommit::dispatch();
        });
    }

    public function dispatchesViaBusFacadeInsideTransaction(): void
    {
        DB::transaction(function () {
            Bus::dispatch(new RegularJob);
        });
    }

    public function dispatchesSyncInsideTransaction(): void
    {
        DB::transaction(function () {
            RegularJob::dispatchSync();
        });
    }

    public function dispatchesSelfReferencingInsideTransaction(): void
    {
        DB::transaction(function () {
            self::dispatch();
        });
    }

    public function dispatchesNonQueuedInsideTransaction(): void
    {
        DB::transaction(function () {
            PlainDispatchable::dispatch();
        });
    }

    public function dispatchesOutsideTransaction(): void
    {
        RegularJob::dispatch();
    }
}
