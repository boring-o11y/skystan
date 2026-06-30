<?php

declare(strict_types=1);

$autoloads = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../../vendor/autoload.php',
];

foreach ($autoloads as $autoload) {
    if (is_file($autoload)) {
        require $autoload;
        break;
    }
}

// Register the Illuminate stubs (ShouldBeUnique / ShouldQueue contracts and the
// Bus / Queue facades) as if they were the installed framework, so fixture jobs
// that `use Illuminate\Contracts\Queue\ShouldBeUnique` resolve during
// reflection-based tests without pulling in laravel/framework.
spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'Illuminate\\')) {
        $relative = substr($class, \strlen('Illuminate\\'));
        $path = __DIR__ . '/Stubs/Illuminate/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($path)) {
            require $path;
        }
    }
});

// Register the test-fixture namespace. The package's autoload-dev mapping covers
// tests/ at composer-install time, but PHPStan's RuleTestCase boots a separate
// container that doesn't see the consumer project's autoload-dev.
spl_autoload_register(function (string $class): void {
    $prefix = 'BoringO11y\\Skystan\\Tests\\';
    if (! str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, \strlen($prefix));
    $path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});
