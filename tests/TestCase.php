<?php

namespace Baspa\ZipCodeLocationLookup\Tests;

use Baspa\ZipCodeLocationLookup\ZipCodeLocationLookupServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            ZipCodeLocationLookupServiceProvider::class,
        ];
    }
}
