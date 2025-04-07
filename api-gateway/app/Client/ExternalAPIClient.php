<?php

namespace App\Client;

use RuntimeException;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Exception\GuzzleException;

const BASE_URL_EXTERNAL_API = 'https://util.devi.tools/api';
const DEFAULT_TIMEOUT = 5;

class ExternalAPIClient
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public function sendToAuthorize(string $endpoint = '/v2/authorize'): array
    {
        return $this->makeRequest('GET', $endpoint);
    }

    public function sendToNotify(string $message, array $payload = [], string $endpoint = '/v1/notify'): array
    {
        return $this->makeRequest('POST', $endpoint, [
            'message' => $message,
            'payload' => $payload,
        ]);
    }

    private function makeRequest(string $method, string $endpoint, array $data = []): array
    {
        $url = BASE_URL_EXTERNAL_API.$endpoint;

        try {
            $httpClient = new Client();

            $options = [
                'json' => $data,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'timeout' => DEFAULT_TIMEOUT,
            ];

            $this->logger->debug('Making external API request', [
                'method' => $method,
                'url' => $url,
                'data' => $data,
            ]);

            $response = $httpClient->request($method, $url, $options);
            $body = json_decode((string)$response->getBody(), true) ?? [];

            $this->logger->debug('External API response', [
                'status_code' => $response->getStatusCode(),
                'body' => $body,
            ]);

            return [
                'status_code' => $response->getStatusCode(),
                'data' => $body,
            ];
        } catch (GuzzleException $e) {
            $this->logger->error('External API request failed', [
                'error' => $e->getMessage(),
                'url' => $url,
                'method' => $method,
            ]);

            throw new RuntimeException(
                sprintf('External API error: %s', $e->getMessage()),
                $e->getCode(),
                $e
            );
        }
    }
}