<?php

namespace Baspa\ZipCodeLocationLookup;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;
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
     * @param string $zipCode
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     */
    public function lookup(string $zipCode): array
    {
        if (empty($zipCode)) {
            throw new InvalidArgumentException('Zip code cannot be empty');
        }

        $postcodeTechResponse = $this->getPostcodeTechResponse($zipCode);
        $googleMapsResponse = $this->getGoogleMapsResponse($postcodeTechResponse['postcode']);

        if ($googleMapsResponse === null) {
            throw new InvalidArgumentException('Unable to geocode the provided zip code');
        }

        return $this->mergeResponses($postcodeTechResponse, $googleMapsResponse);
    }

    /**
     * @param string $zipCode
     * @return array<string, mixed>
     */
    protected function getPostcodeTechResponse(string $zipCode): array
    {
        $response = Http::get('https://api.postcode.tech/v1/postcode?postcode=' . urlencode($zipCode), [
            'Authorization' => 'Bearer ' . $this->postcodeTechApiKey,
        ]);

        return $this->parsePostcodeTechResponse($response);
    }

    /**
     * @param string $postcode
     * @return array<string, float>|null
     */
    protected function getGoogleMapsResponse(string $postcode): ?array
    {
        $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
            'key' => $this->googleMapsApiKey,
            'address' => urlencode($postcode),
        ]);

        return $this->parseGoogleMapsResponse($response);
    }

    /**
     * @param Response $response
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     */
    protected function parsePostcodeTechResponse(Response $response): array
    {
        if (!$response->successful()) {
            throw new InvalidArgumentException('Failed to fetch data from Postcode.tech API');
        }
        return $response->json();
    }

    /**
     * @param Response $response
     * @return array<string, float>|null
     */
    protected function parseGoogleMapsResponse(Response $response): ?array
    {
        if (!$response->successful()) {
            return null;
        }

        $data = $response->json();

        if ($data['status'] === 'OK' && !empty($data['results'][0]['geometry']['location'])) {
            $location = $data['results'][0]['geometry']['location'];
            return [
                'lat' => (float)$location['lat'],
                'lng' => (float)$location['lng']
            ];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $postcodeTechResponse
     * @param array<string, float> $googleMapsResponse
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