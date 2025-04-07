<?php

declare(strict_types=1);

namespace App\Service;

use Throwable;
use Carbon\Carbon;
use App\Model\User;
use Hyperf\Amqp\Producer;
use App\Model\Transaction;
use Hyperf\Stringable\Str;
use Hyperf\DbConnection\Db;
use App\Exception\BusinessException;
use App\Amqp\Producer\TransfersProducer;

class TransferService
{
    public function __construct(
        private readonly Producer    $producer,
        private readonly User        $userModel,
        private readonly Transaction $transactionModel,
    )
    {
    }

    public function transfer(array $params): array
    {
        $payerId = $params['payer'];
        $payeeId = $params['payee'];
        $amount = $params['value'];

        $users = $this->getUsersByIds([$payerId, $payeeId], 'user_type');

        $payer = $users->get($payerId);
        $payee = $users->get($payeeId);

        $payerWalletId = $payer->wallet->id;
        $payeeWalletId = $payee->wallet->id;

        var_dump('$payer', $payer);
        var_dump('$payee', $payee);
        var_dump('$payerWalletId', $payerWalletId);
        var_dump('$payeeWalletId', $payeeWalletId);

        return Db::transaction(function () use ($payer, $payeeId, $payerWalletId, $payeeWalletId, $amount) {
            $this->validateTransfer($amount, $payer, $payeeId);

            $transaction = $this->createTransaction($payer, $payeeId, $payerWalletId, $payeeWalletId, $amount);
            $this->publishToQueue($transaction);

            return [
                'status' => 'queued',
                'transaction_id' => $transaction->id,
                'timestamp' => Carbon::now()->toDateTimeString(),
            ];
        });
    }

    public function getUsersByIds(array $userIds, string $column)
    {
        $users = $this->userModel->newQuery()
            ->whereIn('id', $userIds)
            ->get(['id', $column])
            ->keyBy('id');

        var_dump('$users', $users);
        if ($users->count() !== count($userIds)) {
            throw new BusinessException('USER_NOT_FOUND', 'One or more users not found', 404);
        }

        return $users;
    }

    private function validateTransfer(float $amount, User $payer, int $payeeId): void
    {
        if ($payer->id === $payeeId) {
            throw new BusinessException('INVALID_TRANSFER', 'Payer and payee cannot be the same', 400);
        }

        if ($payer->isMerchant()) {
            throw new BusinessException('MERCHANT_TRANSFER', 'Merchants cannot initiate transfers', 403);
        }

        if (!$payer->wallet || !$payer->wallet->hasSufficientBalance($amount)) {
            throw new BusinessException('INSUFFICIENT_BALANCE', 'Insufficient balance for transfer', 400);
        }
    }

    private function createTransaction(User $payer, int $payeeId, int $payerWalletId, int $payeeWalletId, float $amount): Transaction
    {
        return $this->transactionModel->newQuery()->create([
            'tx_id' => Str::uuid()->toString(),
            'payer_id' => $payer->id,
            'payee_id' => $payeeId,
            'payer_wallet_id' => $payerWalletId,
            'payee_wallet_id' => $payeeWalletId,
            'amount' => $amount,
        ]);
    }

    private function publishToQueue(Transaction $transaction): void
    {
        try {
            $message = new TransfersProducer($transaction->toArray());

            $this->producer->produce($message);
        } catch (Throwable $e) {
            var_dump('Failed to publish message', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new BusinessException(
                'QUEUE_ERROR',
                'Failed to queue transaction: ' . $e->getMessage(),
                503
            );
        }
    }
}