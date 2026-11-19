<?php

use Baspa\ZipCodeLocationLookup\ZipCodeLocationLookup;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function geocodeResponse(array $components, float $lat = 52.373169, float $lng = 4.893379): array
{
    return [
        'status' => 'OK',
        'results' => [[
            'formatted_address' => 'Dam 1, 1012 JS Amsterdam, Netherlands',
            'geometry' => ['location' => ['lat' => $lat, 'lng' => $lng]],
            'address_components' => $components,
        ]],
    ];
}

function addressComponent(string $type, string $name): array
{
    return ['long_name' => $name, 'short_name' => $name, 'types' => [$type]];
}

beforeEach(function () {
    config()->set('services.postcode_tech.api_key', 'postcode-tech-key');
    config()->set('services.google.api_key', 'google-key');

    Http::preventStrayRequests();
});

it('looks up an address with the Geocoding API', function () {
    Http::fake([
        'maps.googleapis.com/maps/api/geocode/json*' => Http::response(geocodeResponse([
            addressComponent('street_number', '1'),
            addressComponent('route', 'Dam'),
            addressComponent('locality', 'Amsterdam'),
            addressComponent('administrative_area_level_2', 'Amsterdam'),
            addressComponent('administrative_area_level_1', 'Noord-Holland'),
            addressComponent('country', 'Netherlands'),
            addressComponent('postal_code', '1012 JS'),
        ])),
    ]);

    expect((new ZipCodeLocationLookup)->lookup('1012JS', 1))->toBe([
        'street' => 'Dam',
        'houseNumber' => 1,
        'postcode' => '1012JS',
        'city' => 'Amsterdam',
        'municipality' => 'Amsterdam',
        'province' => 'Noord-Holland',
        'country' => 'NLD',
        'lat' => 52.373169,
        'lng' => 4.893379,
    ]);
});

it('uses the Places API when the Geocoding API returns no street', function () {
    Http::fake([
        'maps.googleapis.com/maps/api/geocode/json*' => Http::response(geocodeResponse([
            addressComponent('locality', 'Amsterdam'),
            addressComponent('country', 'Netherlands'),
            addressComponent('postal_code', '1012 JS'),
        ], 52.37, 4.89)),
        'maps.googleapis.com/maps/api/place/findplacefromtext/json*' => Http::response([
            'status' => 'OK',
            'candidates' => [['place_id' => 'place-1']],
        ]),
        'maps.googleapis.com/maps/api/place/details/json*' => Http::response([
            'status' => 'OK',
            'result' => [
                'formatted_address' => 'Dam 1, 1012 JS Amsterdam, Netherlands',
                'geometry' => ['location' => ['lat' => 52.373169, 'lng' => 4.893379]],
                'address_components' => [
                    addressComponent('street_number', '1'),
                    addressComponent('route', 'Dam'),
                    addressComponent('locality', 'Amsterdam'),
                    addressComponent('administrative_area_level_2', 'Amsterdam'),
                    addressComponent('administrative_area_level_1', 'Noord-Holland'),
                    addressComponent('country', 'Netherlands'),
                    addressComponent('postal_code', '1012 JS'),
                ],
            ],
        ]),
    ]);

    $result = (new ZipCodeLocationLookup)->lookup('1012JS', 1);

    expect($result)
        ->street->toBe('Dam')
        ->lat->toBe(52.373169)
        ->lng->toBe(4.893379);
});

it('throws when the zip code cannot be geocoded', function () {
    Http::fake([
        'maps.googleapis.com/maps/api/geocode/json*' => Http::response(['status' => 'ZERO_RESULTS', 'results' => []]),
    ]);

    (new ZipCodeLocationLookup)->lookup('0000XX', 1);
})->throws(InvalidArgumentException::class, 'Unable to geocode the provided zip code');

it('requires Google Maps for a lookup', function () {
    (new ZipCodeLocationLookup(false))->lookup('1012JS', 1);
})->throws(InvalidArgumentException::class, 'Google Maps is required for address lookup');

it('throws when the Postcode.tech API key is missing', function () {
    config()->set('services.postcode_tech.api_key', null);

    new ZipCodeLocationLookup;
})->throws(InvalidArgumentException::class, 'Postcode.tech API key must be configured in services config');

it('throws when the Google Maps API key is missing', function () {
    config()->set('services.google.api_key', null);

    new ZipCodeLocationLookup;
})->throws(InvalidArgumentException::class, 'Google Maps API key must be configured in services config');

it('sends the address to the Geocoding API encoded once', function () {
    Http::fake([
        'maps.googleapis.com/maps/api/geocode/json*' => Http::response(geocodeResponse([
            addressComponent('street_number', '1'),
            addressComponent('route', 'Dam'),
            addressComponent('locality', 'Amsterdam'),
            addressComponent('country', 'Netherlands'),
            addressComponent('postal_code', '1012 JS'),
        ])),
    ]);

    (new ZipCodeLocationLookup)->lookup('1012JS', 1);

    Http::assertSent(fn (Request $request) => $request['address'] === '1012JS 1, Netherlands');
});

it('geocodes the requested house number on the street the Places API found', function () {
    Http::fake([
        'maps.googleapis.com/maps/api/geocode/json*' => fn (Request $request) => $request['address'] === 'Dam 1, Amsterdam, Netherlands'
            ? Http::response(geocodeResponse([
                addressComponent('street_number', '1'),
                addressComponent('route', 'Dam'),
                addressComponent('locality', 'Amsterdam'),
                addressComponent('country', 'Netherlands'),
                addressComponent('postal_code', '1012 JS'),
            ], 52.373169, 4.893379))
            : Http::response(geocodeResponse([
                addressComponent('locality', 'Amsterdam'),
                addressComponent('country', 'Netherlands'),
                addressComponent('postal_code', '1012 JS'),
            ], 52.37, 4.89)),
        'maps.googleapis.com/maps/api/place/findplacefromtext/json*' => Http::response([
            'status' => 'OK',
            'candidates' => [['place_id' => 'place-1']],
        ]),
        'maps.googleapis.com/maps/api/place/details/json*' => Http::response([
            'status' => 'OK',
            'result' => [
                'formatted_address' => 'Dam 2, 1012 JS Amsterdam, Netherlands',
                'geometry' => ['location' => ['lat' => 52.3735, 'lng' => 4.8935]],
                'address_components' => [
                    addressComponent('street_number', '2'),
                    addressComponent('route', 'Dam'),
                    addressComponent('locality', 'Amsterdam'),
                    addressComponent('country', 'Netherlands'),
                    addressComponent('postal_code', '1012 JS'),
                ],
            ],
        ]),
    ]);

    expect((new ZipCodeLocationLookup)->lookup('1012JS', 1))
        ->street->toBe('Dam')
        ->lat->toBe(52.373169)
        ->lng->toBe(4.893379);
});

it('geocodes the requested house number on the street found by reverse geocoding', function () {
    Http::fake([
        'maps.googleapis.com/maps/api/geocode/json*' => fn (Request $request) => match (true) {
            isset($request['latlng']) => Http::response(geocodeResponse([
                addressComponent('route', 'Dam'),
                addressComponent('locality', 'Amsterdam'),
                addressComponent('country', 'Netherlands'),
            ], 52.37, 4.89)),
            $request['address'] === 'Dam 1, Amsterdam' => Http::response(geocodeResponse([
                addressComponent('street_number', '1'),
                addressComponent('route', 'Dam'),
                addressComponent('locality', 'Amsterdam'),
                addressComponent('country', 'Netherlands'),
                addressComponent('postal_code', '1012 JS'),
            ], 52.373169, 4.893379)),
            default => Http::response(geocodeResponse([
                addressComponent('locality', 'Amsterdam'),
                addressComponent('country', 'Netherlands'),
                addressComponent('postal_code', '1012 JS'),
            ], 52.37, 4.89)),
        },
        'maps.googleapis.com/maps/api/place/findplacefromtext/json*' => Http::response([
            'status' => 'ZERO_RESULTS',
            'candidates' => [],
        ]),
    ]);

    expect((new ZipCodeLocationLookup)->lookup('1012JS', 1))
        ->street->toBe('Dam')
        ->lat->toBe(52.373169)
        ->lng->toBe(4.893379);
});
