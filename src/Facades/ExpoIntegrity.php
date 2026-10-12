<?php

declare(strict_types=1);

namespace Korioinc\ExpoIntegrity\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Korioinc\ExpoIntegrity\ExpoIntegrity
 */
class ExpoIntegrity extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Korioinc\ExpoIntegrity\ExpoIntegrity::class;
    }
}
