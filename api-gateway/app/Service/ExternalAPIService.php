<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;
use App\Model\Transaction;
use App\Client\ExternalAPIClient;
use App\Exception\BusinessException;

class NotificationService
{
    public function __construct(
        private readonly ExternalAPIClient $client,
    )
    {
    }

    public function notify(Transaction $transaction): int
    {
        try {
            return $this->client->sendToNotify(
                'Transfer completed successfully',
                [
                    'payer' => $transaction->payer(),
                    'payee' => $transaction->payee(),
                    'transaction_id' => $transaction->id,
                    'amount' => $transaction->amount,
                ]
            );
        } catch (RuntimeException $e) {
            throw new BusinessException($e->getMessage());
        }
    }
}