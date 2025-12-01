<?php

declare(strict_types=1);

use Laravel\Mcp\Server\Support\LoggingManager;
use Laravel\Mcp\Server\Support\SessionStoreManager;

it('resolves LoggingManager from container without producing output', function (): void {
    // Capture any output that might be produced during resolution
    ob_start();

    // Capture any errors/warnings
    $errors = [];
    set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) use (&$errors): bool {
        $errors[] = [
            'type' => $errno,
            'message' => $errstr,
            'file' => $errfile,
            'line' => $errline,
        ];

        return true; // Don't execute PHP's internal error handler
    });

    try {
        // First, bind LoggingManager in the container (simulating what we want to test)
        app()->bind(LoggingManager::class, fn ($app): LoggingManager => new LoggingManager(
            $app->make(SessionStoreManager::class)
        ));

        // Now resolve it
        $manager = app(LoggingManager::class);

        expect($manager)->toBeInstanceOf(LoggingManager::class);
    } finally {
        restore_error_handler();
    }

    $output = ob_get_clean();

    // If there was any output, fail with diagnostic info
    if ($output !== '' && $output !== false) {
        $hexDump = bin2hex(substr($output, 0, 200));
        throw new \RuntimeException(sprintf(
            "Unexpected output during LoggingManager resolution!\nLength: %d bytes\nContent: %s\nHex: %s",
            strlen($output),
            substr($output, 0, 500),
            $hexDump
        ));
    }

    // If there were any errors/warnings, fail with diagnostic info
    if ($errors !== []) {
        throw new \RuntimeException(sprintf(
            "Errors/warnings during LoggingManager resolution:\n%s",
            json_encode($errors, JSON_PRETTY_PRINT)
        ));
    }

    expect($output)->toBe('');
    expect($errors)->toBe([]);
});

it('resolves SessionStoreManager from container without producing output', function (): void {
    ob_start();

    $errors = [];
    set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) use (&$errors): bool {
        $errors[] = [
            'type' => $errno,
            'message' => $errstr,
            'file' => $errfile,
            'line' => $errline,
        ];

        return true;
    });

    try {
        $manager = app(SessionStoreManager::class);
        expect($manager)->toBeInstanceOf(SessionStoreManager::class);
    } finally {
        restore_error_handler();
    }

    $output = ob_get_clean();

    if ($output !== '' && $output !== false) {
        throw new \RuntimeException(sprintf(
            "Unexpected output during SessionStoreManager resolution!\nLength: %d bytes\nContent: %s",
            strlen($output),
            substr($output, 0, 500)
        ));
    }

    if ($errors !== []) {
        throw new \RuntimeException(sprintf(
            "Errors/warnings during SessionStoreManager resolution:\n%s",
            json_encode($errors, JSON_PRETTY_PRINT)
        ));
    }

    expect($output)->toBe('');
});

it('resolves Cache repository without producing output', function (): void {
    ob_start();

    $errors = [];
    set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) use (&$errors): bool {
        $errors[] = [
            'type' => $errno,
            'message' => $errstr,
            'file' => $errfile,
            'line' => $errline,
        ];

        return true;
    });

    try {
        $cache = app(\Illuminate\Contracts\Cache\Repository::class);
        expect($cache)->toBeInstanceOf(\Illuminate\Contracts\Cache\Repository::class);
    } finally {
        restore_error_handler();
    }

    $output = ob_get_clean();

    if ($output !== '' && $output !== false) {
        throw new \RuntimeException(sprintf(
            "Unexpected output during Cache resolution!\nLength: %d bytes\nContent: %s",
            strlen($output),
            substr($output, 0, 500)
        ));
    }

    if ($errors !== []) {
        throw new \RuntimeException(sprintf(
            "Errors/warnings during Cache resolution:\n%s",
            json_encode($errors, JSON_PRETTY_PRINT)
        ));
    }

    expect($output)->toBe('');
});
