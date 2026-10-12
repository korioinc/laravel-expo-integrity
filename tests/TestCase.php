<?php

declare(strict_types=1);

namespace Korioinc\ExpoIntegrity\Tests;

use Korioinc\ExpoIntegrity\ExpoIntegrityServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            ExpoIntegrityServiceProvider::class,
        ];
    }
}
