<?php

namespace App\Http\Traits;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Response;

trait HospitableTrait
{
    /**
     * Execute an API call to the Hospitable API.
     *
     * @param string $method The HTTP method (GET, POST, PUT, DELETE, etc.)
     * @param string $endpoint The API endpoint to call (without base URL)
     * @param array $data Optional data for POST/PUT requests
     * @param array $queryParams Optional query parameters for GET requests
     * @return array|null The API response as an associative array, or null on failure
     */
    protected function executeApiCall( string $method, string $endpoint, array $data = [], array $queryParams = []) {
        $apiUrl = config('services.hospitable.api_url', env('HOSPITABLE_API_URL'));
        $apiKey = config('services.hospitable.api_key', env('HOSPITABLE_API_KEY'));

        if (!$apiUrl || !$apiKey) {
            Log::error('Hospitable API credentials not configured', [
                'api_url' => $apiUrl ? 'set' : 'missing',
                'api_key' => $apiKey ? 'set' : 'missing',
            ]);
            return null;
        }

        $url = rtrim($apiUrl, '/') . '/' . ltrim($endpoint, '/');

        $headers = [
            'Authorization' => 'Bearer ' . $apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        $response = match (strtoupper($method)) {
            'GET' => Http::withHeaders($headers)->get($url, $queryParams),
            'POST' => Http::withHeaders($headers)->post($url, $data),
            'PUT' => Http::withHeaders($headers)->put($url, $data),
            'PATCH' => Http::withHeaders($headers)->patch($url, $data),
            'DELETE' => Http::withHeaders($headers)->delete($url, $data),
            default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };

        if ($response->successful()) {
            return $response->json();
        }

        Log::error('Hospitable API request failed', [
            'method' => $method,
            'url' => $url,
            'status' => $response->status(),
            'body' => $response->body(),
            'headers' => $response->headers(),
        ]);

        return null;
    }

    /**
     * Get the raw HTTP response from an API call.
     * Useful when you need to access response headers or status code directly.
     *
     * @param string $method The HTTP method
     * @param string $endpoint The API endpoint
     * @param array $data Optional data for POST/PUT requests
     * @param array $queryParams Optional query parameters for GET requests
     * @return Response|null The HTTP response object, or null on configuration error
     */
    protected function executeApiCallRaw(
        string $method,
        string $endpoint,
        array $data = [],
        array $queryParams = []
    ): ?Response {
        $apiUrl = config('services.hospitable.api_url', env('HOSPITABLE_API_URL'));
        $apiKey = config('services.hospitable.api_key', env('HOSPITABLE_API_KEY'));

        if (!$apiUrl || !$apiKey) {
            Log::error('Hospitable API credentials not configured');
            return null;
        }

        $url = rtrim($apiUrl, '/') . '/' . ltrim($endpoint, '/');
        $headers = [
            'Authorization' => 'Bearer ' . $apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        return match (strtoupper($method)) {
            'GET' => Http::withHeaders($headers)->get($url, $queryParams),
            'POST' => Http::withHeaders($headers)->post($url, $data),
            'PUT' => Http::withHeaders($headers)->put($url, $data),
            'PATCH' => Http::withHeaders($headers)->patch($url, $data),
            'DELETE' => Http::withHeaders($headers)->delete($url, $data),
            default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };
    }

    /**
     * Get properties from the Hospitable API.
     * 
     * Documentation: https://developer.hospitable.com/docs/public-api-docs/qc4x36uhxinx3-get-properties
     *
     * @param array $queryParams Optional query parameters:
     *   - page (int): Page number for pagination
     *   - per_page (int): Number of results per page
     *   - include (string|array): Related resources to include
     * @return array|null The list of properties or null on failure
     */
    public function getProperties(array $queryParams = []): ?array
    {
        return $this->executeApiCall('GET', 'properties', [], $queryParams);
    }

    /**
     * Get images for a specific property from the Hospitable API.
     * 
     * Documentation: https://developer.hospitable.com/docs/public-api-docs/qpa4niiposx20-get-property-images
     *
     * @param string $propertyId The UUID of the property
     * @param array $queryParams Optional query parameters:
     *   - page (int): Page number for pagination
     *   - per_page (int): Number of results per page
     * @return array|null The list of property images or null on failure
     */
    public function getPropertyImages(string $propertyId, array $queryParams = []): ?array
    {
        $endpoint = "properties/{$propertyId}/images";
        return $this->executeApiCall('GET', $endpoint, [], $queryParams);
    }

    /**
     * Get reviews for a specific property from the Hospitable API.
     * 
     * Documentation: https://developer.hospitable.com/docs/public-api-docs/e939481b4e780-get-property-reviews
     *
     * @param string $propertyId The UUID of the property
     * @param array $queryParams Optional query parameters:
     *   - page (int): Page number for pagination
     *   - per_page (int): Number of results per page
     * @return array|null The list of property reviews or null on failure
     */
    public function getPropertyReviews(string $propertyId, array $queryParams = []): ?array
    {
        $endpoint = "properties/{$propertyId}/reviews";
        return $this->executeApiCall('GET', $endpoint, [], $queryParams);
    }
}
