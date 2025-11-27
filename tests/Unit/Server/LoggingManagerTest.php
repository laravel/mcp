<?php

declare(strict_types=1);

use Laravel\Mcp\Server\Enums\LogLevel;
use Laravel\Mcp\Server\LoggingManager;
use Laravel\Mcp\Server\Store\SessionStoreManager;

beforeEach(function (): void {
    LoggingManager::setDefaultLevel(LogLevel::Info);
});

test('it returns the default level for the new session', function (): void {
    $manager = new LoggingManager(new SessionStoreManager(Cache::driver(), 'session-1'));

    expect($manager->getLevel())->toBe(LogLevel::Info);
});

test('it can set and get log level for a session', function (): void {
    $manager = new LoggingManager(new SessionStoreManager(Cache::driver(), 'session-1'));
    $manager->setLevel(LogLevel::Debug);

    expect($manager->getLevel())->toBe(LogLevel::Debug);
});

test('it maintains separate levels for different sessions', function (): void {
    $manager1 = new LoggingManager(new SessionStoreManager(Cache::driver(), 'session-1'));
    $manager2 = new LoggingManager(new SessionStoreManager(Cache::driver(), 'session-2'));

    $manager1->setLevel(LogLevel::Debug);
    $manager2->setLevel(LogLevel::Error);

    expect($manager1->getLevel())->toBe(LogLevel::Debug)
        ->and($manager2->getLevel())->toBe(LogLevel::Error);
});

test('it correctly determines if a log should be sent', function (): void {
    $manager = new LoggingManager(new SessionStoreManager(Cache::driver(), 'session-1'));
    $manager->setLevel(LogLevel::Info);

    expect($manager->shouldLog(LogLevel::Emergency))->toBeTrue()
        ->and($manager->shouldLog(LogLevel::Error))->toBeTrue()
        ->and($manager->shouldLog(LogLevel::Info))->toBeTrue()
        ->and($manager->shouldLog(LogLevel::Debug))->toBeFalse();
});

test('it uses default level for null session id', function (): void {
    $manager = new LoggingManager(new SessionStoreManager(Cache::driver()));

    expect($manager->getLevel())->toBe(LogLevel::Info)
        ->and($manager->shouldLog(LogLevel::Info))->toBeTrue()
        ->and($manager->shouldLog(LogLevel::Debug))->toBeFalse();
});

test('it can change the default level', function (): void {
    LoggingManager::setDefaultLevel(LogLevel::Warning);

    $manager1 = new LoggingManager(new SessionStoreManager(Cache::driver(), 'new-session'));
    $manager2 = new LoggingManager(new SessionStoreManager(Cache::driver()));

    expect($manager1->getLevel())->toBe(LogLevel::Warning)
        ->and($manager2->getLevel())->toBe(LogLevel::Warning);
});

test('setLevel ignores null session id', function (): void {
    $manager = new LoggingManager(new SessionStoreManager(Cache::driver()));
    $manager->setLevel(LogLevel::Debug);

    expect($manager->getLevel())->toBe(LogLevel::Info);
});

test('it can get default level', function (): void {
    LoggingManager::setDefaultLevel(LogLevel::Warning);

    expect(LoggingManager::getDefaultLevel())->toBe(LogLevel::Warning);
});
