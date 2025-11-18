<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeocodingService
{
    public function __construct(private HttpClientInterface $client) {}

    public function geocode(string $address): ?array
    {
        $url = 'https://nominatim.openstreetmap.org/search';

        $response = $this->client->request('GET', $url, [
            'query' => [
                'q' => $address,
                'format' => 'json',
                'limit' => 1
            ],
            'headers' => [
                'User-Agent' => 'ChatFixApp'
            ]
        ]);

        $data = $response->toArray();

        if (empty($data)) {
            return null;
        }

        return [
            'lat' => (float) $data[0]['lat'],
            'lng' => (float) $data[0]['lon'],
        ];
    }
}
