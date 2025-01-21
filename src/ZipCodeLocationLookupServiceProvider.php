<?php

namespace Baspa\ZipCodeLocationLookup;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Baspa\ZipCodeLocationLookup\Commands\ZipCodeLocationLookupCommand;

class ZipCodeLocationLookupServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('zip-code-location-lookup')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_zip_code_location_lookup_table')
            ->hasCommand(ZipCodeLocationLookupCommand::class);
    }
}
