<?php

declare(strict_types=1);

use Korioinc\ExpoIntegrity\ExpoIntegrity;

it('resolves the singleton', function () {
    expect(app(ExpoIntegrity::class))->toBeInstanceOf(ExpoIntegrity::class);
});

it('returns the same instance from the container', function () {
    expect(app(ExpoIntegrity::class))->toBe(app(ExpoIntegrity::class));
});

it('merges the package config', function () {
    expect(config('laravel-expo-integrity.placeholder'))->toBe('default');
});
