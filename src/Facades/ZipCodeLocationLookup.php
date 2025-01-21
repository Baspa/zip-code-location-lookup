<?php

namespace Baspa\ZipCodeLocationLookup\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Baspa\ZipCodeLocationLookup\ZipCodeLocationLookup
 */
class ZipCodeLocationLookup extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Baspa\ZipCodeLocationLookup\ZipCodeLocationLookup::class;
    }
}
