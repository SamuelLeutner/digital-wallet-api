<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Transaction;
use App\Client\ExternalAPIClient;
use GuzzleHttp\Exception\GuzzleException;
use App\Exception\ExternalAPI\AuthorizationFailedException;
use App\Exception\ExternalAPI\NotificationFailedException;
use App\Exception\ExternalAPI\ServiceUnavailableException;

class ExternalAPIService
{
    public function __construct(
        private readonly ExternalAPIClient $client,
    ) {
    }

    public function authorize(): bool
    {
        try {
            $response = $this->client->sendToAuthorize();

            if ($response['status_code'] !== 200) {
                throw new AuthorizationFailedException('Authorization service returned non-200 status');
            }

            if (!isset($response['data']['status']) || $response['data']['status'] !== 'success') {
                throw new AuthorizationFailedException(
                    'Authorization failed: '.($response['data']['message'] ?? 'No reason provided')
                );
            }

            return $response['data']['data']['authorization'] ?? false;
        } catch (GuzzleException $e) {
            throw new ServiceUnavailableException('Authorization service unavailable: '.$e->getMessage());
        }
    }

    public function notify(Transaction $transaction): void
    {
        try {
            $response = $this->client->sendToNotify(
                'Transfer completed successfully',
                [
                    'payer' => $transaction->payer_id,
                    'payee' => $transaction->payee_id,
                    'transaction_id' => $transaction->id,
                    'amount' => $transaction->amount,
                ]
            );

            if ($response['status_code'] !== 204) {
                $errorMessage = $response['data']['message'] ?? 'Notification failed with status '.$response['status_code'];
                throw new NotificationFailedException($errorMessage);
            }
        } catch (GuzzleException $e) {
            throw new ServiceUnavailableException('Notification service unavailable: '.$e->getMessage());
        }
    }
}