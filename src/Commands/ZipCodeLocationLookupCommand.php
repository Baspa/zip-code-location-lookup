<?php

namespace Baspa\ZipCodeLocationLookup\Commands;

use Illuminate\Console\Command;

class ZipCodeLocationLookupCommand extends Command
{
    public $signature = 'zip-code-location-lookup';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
