<?php

declare(strict_types=1);

namespace Illuminate\Database\Eloquent;

/**
 * Minimal stub of the Eloquent base model so fixture model classes
 * (`class Yacht extends Model`) resolve during reflection-based rule tests
 * without pulling in laravel/framework.
 */
abstract class Model {}
