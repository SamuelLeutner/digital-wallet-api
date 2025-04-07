<?php

declare(strict_types=1);

namespace App\Service;

use Throwable;
use Carbon\Carbon;
use App\Model\User;
use Hyperf\Amqp\Producer;
use App\Model\Transaction;
use Hyperf\DbConnection\Db;
use App\Repository\UserRepository;
use App\Amqp\Producer\TransfersProducer;
use App\Repository\TransactionRepository;
use App\Exception\Transfer\BusinessException;
use App\Exception\Transfer\InsufficientFundsException;

class TransferService
{
    public function __construct(
        protected readonly Db $database,
        protected readonly Producer $producer,
        protected readonly UserRepository $userRepository,
        protected readonly TransactionRepository $transactionRepository,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function transfer(array $params): array
    {
        try {
            $payerId = $params['payer'];
            $payeeId = $params['payee'];
            $amount = $params['value'];

            $this->database->beginTransaction();

            [$payer, $payee] = $this->userRepository->getUsersByIds([$payerId, $payeeId], 'user_type');

            $this->validateTransfer($amount, $payer, $payeeId);

            $transaction = $this->transactionRepository->createTransaction($payer, $payee, $amount);
            $this->transactionRepository->initializeSaga($transaction->tx_id);

            $this->database->commit();

            $this->publishToQueue($transaction);

            return [
                'status' => 'queued',
                'transaction_id' => $transaction->id,
                'timestamp' => Carbon::now()->format('H:i:s d-m-Y'),
            ];
        } catch (Throwable $exception) {
            $this->database->rollBack();
            throw $exception;
        }
    }

    private function validateTransfer(float $amount, User $payer, int $payeeId): void
    {
        if ($amount <= 0) {
            throw new BusinessException('INVALID_AMOUNT', 'Transfer amount must be greater than zero', 400);
        }

        if ($payer->isMerchant()) {
            throw new BusinessException('MERCHANT_TRANSFER', 'Merchants cannot initiate transfers', 403);
        }

        if (!$payer->wallet || !$payer->wallet->hasSufficientBalance($amount)) {
            throw new InsufficientFundsException();
        }

        if ($payer->id === $payeeId) {
            throw new BusinessException('SELF_TRANSFER', 'Cannot transfer to yourself', 400);
        }
    }

    private function publishToQueue(Transaction $transaction): void
    {
        try {
            $message = new TransfersProducer($transaction->toArray());

            $this->producer->produce($message);
        } catch (Throwable $e) {
            throw new BusinessException(
                'QUEUE_ERROR',
                'Failed to queue transaction: '.$e->getMessage(),
                503
            );
        }
    }
}