<?php

namespace Baspa\ZipCodeLocationLookup;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class ZipCodeLocationLookup
{
    protected string $googleMapsApiKey;

    public function __construct()
    {
        $apiKey = config('services.google.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new InvalidArgumentException('Google Maps API key must be configured in services config');
        }

        $this->googleMapsApiKey = $apiKey;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    public function lookup(string $zipCode, int $number): array
    {
        if (empty($zipCode)) {
            throw new InvalidArgumentException('Zip code cannot be empty');
        }

        $googleMapsResponse = $this->getGoogleMapsResponse($zipCode, $number);

        if ($googleMapsResponse === null) {
            throw new InvalidArgumentException('Unable to geocode the provided zip code');
        }

        $country = $googleMapsResponse['address_components']['country'];

        if ($country === 'Netherlands' || $country === 'NL') {
            $country = 'NLD';
        }
        if ($country === 'Belgium' || $country === 'BE') {
            $country = 'BEL';
        }
        if ($country === 'Germany' || $country === 'DE') {
            $country = 'DEU';
        }
        if ($country === 'Luxembourg' || $country === 'LU') {
            $country = 'LUX';
        }

        return [
            'street' => $googleMapsResponse['address_components']['street_name'],
            'houseNumber' => $number,
            'postcode' => $zipCode,
            'city' => $googleMapsResponse['address_components']['city'],
            'municipality' => $googleMapsResponse['address_components']['municipality'],
            'province' => $googleMapsResponse['address_components']['province'],
            'country' => $country,
            'lat' => $googleMapsResponse['lat'],
            'lng' => $googleMapsResponse['lng'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getGoogleMapsResponse(string $zipCode, int $number): ?array
    {
        $query = $zipCode.' '.$number.', Netherlands';

        $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
            'key' => $this->googleMapsApiKey,
            'address' => $query,
        ]);

        $result = $this->parseGoogleMapsResponse($response);

        if ($result !== null && empty($result['address_components']['street_name'])) {
            $foundViaPlaces = false;

            // Strategy 1: Google Places API (Find Place From Text)
            // This is more accurate for text-based queries where Geocoding API returns a postcode centroid
            $placesResponse = Http::get('https://maps.googleapis.com/maps/api/place/findplacefromtext/json', [
                'key' => $this->googleMapsApiKey,
                'input' => $query,
                'inputtype' => 'textquery',
                'fields' => 'place_id',
            ]);

            $placesData = $placesResponse->json();

            if ($placesData['status'] === 'OK' && ! empty($placesData['candidates'][0]['place_id'])) {
                $placeId = $placesData['candidates'][0]['place_id'];

                // Fetch Place Details to get address components
                $detailsResponse = Http::get('https://maps.googleapis.com/maps/api/place/details/json', [
                    'key' => $this->googleMapsApiKey,
                    'place_id' => $placeId,
                    'fields' => 'address_component,formatted_address,geometry',
                ]);

                $detailsResult = $this->parseGoogleMapsResponse($detailsResponse);

                if ($detailsResult !== null && ! empty($detailsResult['address_components']['street_name'])) {
                    // Verify if the returned postcode matches the input postcode (ignoring spaces)
                    $returnedZip = str_replace(' ', '', $detailsResult['address_components']['postal_code'] ?? '');
                    $inputZip = str_replace(' ', '', $zipCode);

                    if (stripos($returnedZip, $inputZip) !== false || stripos($inputZip, $returnedZip) !== false) {
                        $result = $detailsResult;
                        $foundViaPlaces = true;

                        // Check if the house number matches
                        $returnedNumber = $detailsResult['address_components']['street_number'] ?? '';
                        if ($returnedNumber != $number) {
                            // If house number mismatches, try to geocode specifically with the found street name
                            // This ensures we get the location of the requested number, not the one Places API snapped to.
                            $streetQuery = $detailsResult['address_components']['street_name'].' '.$number.', '.$detailsResult['address_components']['city'].', Netherlands';

                            $streetGeocodeResponse = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
                                'key' => $this->googleMapsApiKey,
                                'address' => $streetQuery,
                            ]);

                            $streetGeocodeResult = $this->parseGoogleMapsResponse($streetGeocodeResponse);

                            if ($streetGeocodeResult !== null && ! empty($streetGeocodeResult['lat'])) {
                                $result = $streetGeocodeResult;
                            }
                        }
                    }
                }
            }

            // Strategy 2: Reverse Geocoding (Fallback if Places failed or mismatched)
            // This snaps to the nearest street from the centroid coordinates.
            if (! $foundViaPlaces && ! empty($result['lat']) && ! empty($result['lng'])) {
                $reverseResponse = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
                    'key' => $this->googleMapsApiKey,
                    'latlng' => $result['lat'].','.$result['lng'],
                ]);

                $reverseResult = $this->parseGoogleMapsResponse($reverseResponse);

                if ($reverseResult !== null && ! empty($reverseResult['address_components']['street_name'])) {
                    // We found a street name via reverse geocoding
                    // Now geocode with "street + number + city" to get the exact house location
                    $streetQuery = $reverseResult['address_components']['street_name'].' '.$number.', '.
                                   ($reverseResult['address_components']['city'] ?? 'Netherlands');

                    $streetGeocodeResponse = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
                        'key' => $this->googleMapsApiKey,
                        'address' => $streetQuery,
                    ]);

                    $streetGeocodeResult = $this->parseGoogleMapsResponse($streetGeocodeResponse);

                    if ($streetGeocodeResult !== null && ! empty($streetGeocodeResult['lat'])) {
                        // Use the precise coordinates from the street-based geocoding
                        $result = $streetGeocodeResult;
                    } else {
                        // Fallback: at least update the street name from reverse geocoding
                        $result['address_components']['street_name'] = $reverseResult['address_components']['street_name'];
                    }
                }
            }
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function parseGoogleMapsResponse(Response $response): ?array
    {
        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        // Handle both Geocoding API (results) and Place Details API (result) formats
        $result = null;
        if (isset($data['result'])) {
            $result = $data['result'];
        } elseif (isset($data['results'][0])) {
            $result = $data['results'][0];
        }

        if ($result && $data['status'] === 'OK') {
            $location = $result['geometry']['location'];
            $addressComponents = $this->parseAddressComponents($result['address_components']);

            return [
                'lat' => (float) $location['lat'],
                'lng' => (float) $location['lng'],
                'formatted_address' => $result['formatted_address'],
                'address_components' => $addressComponents,
            ];
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $components
     * @return array<string, string>
     */
    protected function parseAddressComponents(array $components): array
    {
        $address = [
            'street_number' => '',
            'street_name' => '',
            'city' => '',
            'municipality' => '',
            'province' => '',
            'country' => '',
            'postal_code' => '',
        ];

        foreach ($components as $component) {
            $type = $component['types'][0] ?? '';

            switch ($type) {
                case 'street_number':
                    $address['street_number'] = $component['long_name'];
                    break;
                case 'route':
                    $address['street_name'] = $component['long_name'];
                    break;
                case 'locality':
                    $address['city'] = $component['long_name'];
                    break;
                case 'administrative_area_level_2':
                    $address['municipality'] = $component['long_name'];
                    break;
                case 'administrative_area_level_1':
                    $address['province'] = $component['long_name'];
                    break;
                case 'country':
                    $address['country'] = $component['long_name'];
                    break;
                case 'postal_code':
                    $address['postal_code'] = $component['long_name'];
                    break;
            }
        }

        return $address;
    }
}
