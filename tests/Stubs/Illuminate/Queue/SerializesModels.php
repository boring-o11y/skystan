<?php

declare(strict_types=1);

namespace Illuminate\Queue;

/**
 * Minimal stub of the SerializesModels trait so fixture jobs that
 * `use Illuminate\Queue\SerializesModels` resolve during reflection-based rule
 * tests without pulling in laravel/framework.
 */
trait SerializesModels {}
