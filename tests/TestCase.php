<?php

namespace Baspa\ZipCodeLocationLookup\Tests;

use Baspa\ZipCodeLocationLookup\ZipCodeLocationLookupServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Baspa\\ZipCodeLocationLookup\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            ZipCodeLocationLookupServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        /*
        $migration = include __DIR__.'/../database/migrations/create_zip-code-location-lookup_table.php.stub';
        $migration->up();
        */
    }
}
