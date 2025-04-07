<?php

namespace App\Client;

use RuntimeException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

const BASE_URL_NOTIFY_API = 'https://util.devi.tools/api/v1';

class NotificationClient
{
    public function __construct(
        private readonly Client $httpClient,
    )
    {
    }

    public function notify(string $message, array $payload = [], string $endpoint = '/notify'): int
    {
        $url = BASE_URL_NOTIFY_API . $endpoint;
        $data = array_merge(['message' => $message], $payload);

        try {
            $response = $this->httpClient->post($url, [
                'json' => $data,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 5,
            ]);

            return $response->getStatusCode();
        } catch (GuzzleException $e) {
            throw new RuntimeException('Failed to send notification: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }
}