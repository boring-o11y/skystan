<?php

namespace BoringO11y\Skystan\Tests\Fixtures\PhpStan;

/**
 * A dispatchable that is NOT queued — it runs synchronously, so dispatching it
 * inside a transaction carries no commit race.
 */
class PlainDispatchable
{
    public function handle(): void {}
}
