# Changelog

All notable changes to `zip-code-location-lookup` will be documented in this file.

## v0.3.1 - 2026-11-19

### Fixes

- **Missing API keys throw the intended `InvalidArgumentException`** ("… API key must be configured in services config") instead of a `TypeError`.
- **Addresses are sent to Google encoded once.** Every Geocoding request used to encode the address twice, so Google received literal `+` and `%2C` in the address and street queries. Lookups that needed a second geocoding step for the exact house number benefit most.
- **README:** describes the Google-only lookup, drops the "Without Google Maps" example (`lookup()` has thrown in that mode since v0.1.5), and shows the array `lookup()` actually returns, including `houseNumber` and `country`.

### What's Changed

* fix: clear missing-key errors and single-encoded Google addresses by @Baspa in https://github.com/Baspa/zip-code-location-lookup/pull/19

**Full Changelog**: https://github.com/Baspa/zip-code-location-lookup/compare/v0.3.0...v0.3.1

## v0.3.0 - 2026-11-19

### Breaking changes

- **Requires Laravel 13 and PHP 8.3 or newer.** Laravel 9–12 and PHP 8.1–8.2 are no longer supported. On older versions, stay on `^0.2`.

### What's new

- **PHP 8.6 support.** Tested on PHP 8.3, 8.4, 8.5 and 8.6, on Ubuntu and Windows, with lowest and stable dependencies.
- The lookup itself is now covered by tests with faked Google responses.

### Development

- PHPStan 2.3.6, Larastan 3.15 and Pest 4/5 are required.

### What's Changed

* feat!: require Laravel 13 and support PHP 8.6 by @Baspa in https://github.com/Baspa/zip-code-location-lookup/pull/18

**Full Changelog**: https://github.com/Baspa/zip-code-location-lookup/compare/v0.2.0...v0.3.0

## v0.2.0 - 2026-04-07

### What's new

- **Laravel 12 support.** Laravel 9, 10, 11 and 12 are supported.
- **Every lookup goes through the Google Geocoding API** (first released in v0.1.5–v0.1.7). `lookup()` no longer queries Postcode.tech, although the constructor still requires its API key. With `useGoogleMaps: false`, `lookup()` throws an `InvalidArgumentException`.
- **More accurate street names and coordinates.** When geocoding only finds the postcode area, the address is looked up through the Google Places API and only accepted if the postcode matches. If the house number differs, the street is geocoded again with the requested number. Reverse geocoding is the last fallback, so a key without the Places API enabled still works.
- `country` is returned as `NLD`, `BEL`, `DEU` or `LUX` for those four countries.

### What's Changed

* Bump dependabot/fetch-metadata from 2.2.0 to 2.3.0 by @dependabot[bot] in https://github.com/Baspa/zip-code-location-lookup/pull/1
* Bump aglipanci/laravel-pint-action from 2.4 to 2.5 by @dependabot[bot] in https://github.com/Baspa/zip-code-location-lookup/pull/2
* Bump dependabot/fetch-metadata from 2.3.0 to 2.4.0 by @dependabot[bot] in https://github.com/Baspa/zip-code-location-lookup/pull/3
* Bump aglipanci/laravel-pint-action from 2.5 to 2.6 by @dependabot[bot] in https://github.com/Baspa/zip-code-location-lookup/pull/5
* Bump actions/checkout from 4 to 5 by @dependabot[bot] in https://github.com/Baspa/zip-code-location-lookup/pull/6
* Bump stefanzweifel/git-auto-commit-action from 5 to 7 by @dependabot[bot] in https://github.com/Baspa/zip-code-location-lookup/pull/7
* Bump actions/checkout from 5 to 6 by @dependabot[bot] in https://github.com/Baspa/zip-code-location-lookup/pull/8
* Bump dependabot/fetch-metadata from 2.4.0 to 2.5.0 by @dependabot[bot] in https://github.com/Baspa/zip-code-location-lookup/pull/9
* Bump ramsey/composer-install from 3 to 4 by @dependabot[bot] in https://github.com/Baspa/zip-code-location-lookup/pull/10
* Bump dependabot/fetch-metadata from 2.5.0 to 3.0.0 by @dependabot[bot] in https://github.com/Baspa/zip-code-location-lookup/pull/11

### New Contributors

* @dependabot[bot] made their first contribution in https://github.com/Baspa/zip-code-location-lookup/pull/1

**Full Changelog**: https://github.com/Baspa/zip-code-location-lookup/compare/v0.1.0...v0.2.0

## v0.1.0 - 2025-01-28

**Full Changelog**: https://github.com/Baspa/zip-code-location-lookup/commits/v0.1.0
