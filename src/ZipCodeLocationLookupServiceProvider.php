<?php

namespace Baspa\ZipCodeLocationLookup;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ZipCodeLocationLookupServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('zip-code-location-lookup');
    }
}
