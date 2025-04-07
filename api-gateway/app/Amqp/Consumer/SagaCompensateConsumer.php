<?php

declare(strict_types=1);

namespace App\Amqp\Consumer;

use Throwable;
use Hyperf\Amqp\Result;
use App\Model\Transaction;
use Hyperf\DbConnection\Db;
use PhpAmqpLib\Message\AMQPMessage;
use Hyperf\Amqp\Annotation\Consumer;
use App\Repository\WalletRepository;
use Hyperf\Amqp\Message\ConsumerMessage;
use App\Repository\TransactionRepository;
use Hyperf\Database\Model\ModelNotFoundException;

#[Consumer(
    exchange: 'saga',
    routingKey: 'saga.compensate',
    queue: 'saga_compensate_queue',
    name: 'SagaCompensateConsumer',
    nums: 1
)]
class SagaCompensateConsumer extends ConsumerMessage
{
    public function __construct(
        protected readonly Db $database,
        protected readonly WalletRepository $walletRepository,
        protected readonly TransactionRepository $transactionRepository,
    ) {
    }

    public function consumeMessage($data, AMQPMessage $message): Result
    {
        try {
            $transaction = $this->transactionRepository->findByTxId($data['tx_id']);

            if ($transaction->compensated_at !== null) {
                return Result::ACK;
            }

            $this->executeCompensation($transaction, $data['failed_step']);

            return Result::ACK;
        } catch (ModelNotFoundException $e) {
            return Result::ACK;
        } catch (Throwable $e) {
            return Result::REQUEUE;
        }
    }

    /**
     * @throws Throwable
     */
    private function executeCompensation(Transaction $transaction, string $failedStep): void
    {
        $this->database->beginTransaction();

        try {
            if ($this->transactionRepository->isStepCompleted($transaction, Transaction::SAGA_STATUS_DEBIT_PAYER)) {
                $this->walletRepository->creditWallet(
                    $transaction->payer_wallet_id,
                    $transaction->amount
                );
            }

            if ($this->transactionRepository->isStepCompleted($transaction, Transaction::SAGA_STATUS_CREDIT_PAYEE)) {
                $this->walletRepository->debitWallet(
                    $transaction->payee_wallet_id,
                    $transaction->amount
                );
            }

            $this->transactionRepository->markAsCompensated($transaction->tx_id, $failedStep);
            $this->database->commit();
        } catch (Throwable $e) {
            $this->database->rollBack();
            throw $e;
        }
    }
}