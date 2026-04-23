<?php

declare(strict_types=1);

it('wires sentry exception handling in bootstrap', function () {
    $bootstrapPath = dirname(__DIR__, 2).'/bootstrap/app.php';
    $bootstrapFile = file_get_contents($bootstrapPath);

    expect($bootstrapFile)->toContain('Integration::handles($exceptions);');
});

it('prefers the laravel dsn env variable when resolving sentry dsn', function () {
    $originalLaravelDsn = getenv('SENTRY_LARAVEL_DSN');
    $originalFallbackDsn = getenv('SENTRY_DSN');
    $originalLaravelDsnEnv = $_ENV['SENTRY_LARAVEL_DSN'] ?? null;
    $originalLaravelDsnServer = $_SERVER['SENTRY_LARAVEL_DSN'] ?? null;
    $originalFallbackDsnEnv = $_ENV['SENTRY_DSN'] ?? null;
    $originalFallbackDsnServer = $_SERVER['SENTRY_DSN'] ?? null;

    putenv('SENTRY_LARAVEL_DSN=https://laravel.example/1');
    putenv('SENTRY_DSN=https://fallback.example/1');
    $_ENV['SENTRY_LARAVEL_DSN'] = 'https://laravel.example/1';
    $_SERVER['SENTRY_LARAVEL_DSN'] = 'https://laravel.example/1';
    $_ENV['SENTRY_DSN'] = 'https://fallback.example/1';
    $_SERVER['SENTRY_DSN'] = 'https://fallback.example/1';

    $configPath = dirname(__DIR__, 2).'/config/sentry.php';
    $config = require $configPath;

    expect($config['dsn'])->toBe('https://laravel.example/1');

    if ($originalLaravelDsn === false) {
        putenv('SENTRY_LARAVEL_DSN');
    } else {
        putenv("SENTRY_LARAVEL_DSN=$originalLaravelDsn");
    }
    if ($originalLaravelDsnEnv === null) {
        unset($_ENV['SENTRY_LARAVEL_DSN']);
    } else {
        $_ENV['SENTRY_LARAVEL_DSN'] = $originalLaravelDsnEnv;
    }
    if ($originalLaravelDsnServer === null) {
        unset($_SERVER['SENTRY_LARAVEL_DSN']);
    } else {
        $_SERVER['SENTRY_LARAVEL_DSN'] = $originalLaravelDsnServer;
    }

    if ($originalFallbackDsn === false) {
        putenv('SENTRY_DSN');
    } else {
        putenv("SENTRY_DSN=$originalFallbackDsn");
    }
    if ($originalFallbackDsnEnv === null) {
        unset($_ENV['SENTRY_DSN']);
    } else {
        $_ENV['SENTRY_DSN'] = $originalFallbackDsnEnv;
    }
    if ($originalFallbackDsnServer === null) {
        unset($_SERVER['SENTRY_DSN']);
    } else {
        $_SERVER['SENTRY_DSN'] = $originalFallbackDsnServer;
    }
});
