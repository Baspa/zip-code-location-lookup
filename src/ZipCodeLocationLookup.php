<?php

namespace Baspa\ZipCodeLocationLookup;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class ZipCodeLocationLookup
{
    protected string $googleMapsApiKey;

    protected string $postcodeTechApiKey;

    public function __construct()
    {
        $this->googleMapsApiKey = config('services.google.api_key');
        $this->postcodeTechApiKey = config('services.postcode_tech.api_key');

        if (empty($this->googleMapsApiKey) || empty($this->postcodeTechApiKey)) {
            throw new InvalidArgumentException('API keys must be configured in services config');
        }
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

        $postcodeTechResponse = $this->getPostcodeTechResponse($zipCode, $number);
        $googleMapsResponse = $this->getGoogleMapsResponse($postcodeTechResponse, $zipCode, $number);

        if ($googleMapsResponse === null) {
            throw new InvalidArgumentException('Unable to geocode the provided zip code');
        }

        return $this->mergeResponses($postcodeTechResponse, $googleMapsResponse);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getPostcodeTechResponse(string $zipCode, int $number): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->postcodeTechApiKey,
        ])->get('https://postcode.tech/api/v1/postcode?postcode=' . urlencode($zipCode) . '&number=' . $number);

        return $this->parsePostcodeTechResponse($response);
    }

    /**
     * @return array<string, float>|null
     */
    protected function getGoogleMapsResponse(array $address, string $zipCode, int $number): ?array
    {
        $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
            'key' => $this->googleMapsApiKey,
            'address' => urlencode($address['street'] . ' ' . $number . ' ' . $zipCode . ' ' . $address['city']),
        ]);

        return $this->parseGoogleMapsResponse($response);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    protected function parsePostcodeTechResponse(Response $response): array
    {
        if (! $response->successful()) {
            throw new InvalidArgumentException(
                'Failed to fetch data from Postcode.tech API: ' . $response->body(),
                $response->status()
            );
        }

        return $response->json();
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

        if ($data['status'] === 'OK' && ! empty($data['results'][0])) {
            $result = $data['results'][0];
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
     * @param array<int, array<string, mixed>> $components
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

    /**
     * @param  array<string, mixed>  $postcodeTechResponse
     * @param  array<string, float>  $googleMapsResponse
     * @return array<string, mixed>
     */
    protected function mergeResponses(array $postcodeTechResponse, array $googleMapsResponse): array
    {
        return array_merge($postcodeTechResponse, $googleMapsResponse);
    }

    protected function formatResponse($response)
    {
        return json_encode($response);
    }
}