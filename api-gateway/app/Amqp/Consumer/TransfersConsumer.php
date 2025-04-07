<?php

declare(strict_types=1);

namespace App\Amqp\Consumer;

use Throwable;
use RuntimeException;
use Hyperf\Amqp\Result;
use Hyperf\Amqp\Producer;
use App\Model\Transaction;
use InvalidArgumentException;
use PhpAmqpLib\Message\AMQPMessage;
use App\Service\ExternalAPIService;
use Hyperf\Amqp\Annotation\Consumer;
use App\Repository\WalletRepository;
use Hyperf\Amqp\Message\ConsumerMessage;
use App\Repository\TransactionRepository;
use App\Amqp\Producer\SagaCompensateProducer;
use Hyperf\Database\Model\ModelNotFoundException;
use App\Exception\Transfer\InsufficientFundsException;
use App\Exception\ExternalAPI\NotificationFailedException;
use App\Exception\ExternalAPI\ServiceUnavailableException;
use App\Exception\ExternalAPI\AuthorizationFailedException;

#[Consumer(
    exchange: 'transfers',
    routingKey: 'transfers.create',
    queue: 'transfer_queue',
    name: "TransfersConsumer",
    nums: 1,
)]
class TransfersConsumer extends ConsumerMessage
{
    public function __construct(
        protected readonly Producer $producer,
        protected readonly ExternalAPIService $apiService,
        protected readonly WalletRepository $walletRepository,
        protected readonly TransactionRepository $transactionRepository,
    ) {
    }

    public function consumeMessage($data, AMQPMessage $message): Result
    {
        try {
            if (!isset($data['tx_id'])) {
                throw new InvalidArgumentException('Missing tx_id in message');
            }


            $transaction = $this->transactionRepository->findByTxId($data['tx_id']);

            if ($transaction->status === Transaction::STATUS_COMPLETED) {
                return Result::ACK;
            }

            $this->processSaga($data, $transaction);

            return Result::ACK;
        } catch (Throwable $exception) {
            $this->handleSagaFailure($data['tx_id'] ?? 'unknown', $exception);

            return $this->shouldRequeue($exception) ? Result::REQUEUE : Result::ACK;
        }
    }

    /**
     * @throws Throwable
     */
    private function processSaga(array $data, Transaction $transaction): void
    {
        while ($currentStep = $this->transactionRepository->getCurrentStep($transaction)) {
            $this->executeSagaStep($currentStep, $data, $transaction);

            $transaction = $this->transactionRepository->completeSagaStep($transaction->tx_id, $currentStep);

            if ($this->transactionRepository->areAllStepsCompleted($transaction)) {
                $this->transactionRepository->completeSaga($transaction->tx_id);
                break;
            }
        }
    }

    private function handleSagaFailure(string $txId, Throwable $exception): void
    {
        try {
            $transaction = $this->transactionRepository->markAsFailed($txId, $exception->getMessage());

            if ($transaction->compensated_at === null) {
                $currentStep = $this->transactionRepository->getCurrentStep($transaction) ?? 'unknown';

                $this->producer->produce(new SagaCompensateProducer([
                    'saga_id' => $transaction->saga_id,
                    'tx_id' => $transaction->tx_id,
                    'failed_step' => $currentStep,
                    'error' => $exception->getMessage(),
                ]));
            }
        } catch (Throwable $e) {
        }
    }

    private function executeSagaStep(string $step, array $data, Transaction $transaction): void
    {
        try {
            switch ($step) {
                case 'authorize':
                    if (!$this->apiService->authorize()) {
                        throw new AuthorizationFailedException('Authorization explicitly denied');
                    }
                    break;

                case 'debit_payer':
                    $this->walletRepository->debitWallet($data['payer_wallet_id'], $data['amount']);
                    break;

                case 'credit_payee':
                    $this->walletRepository->creditWallet($data['payee_wallet_id'], $data['amount']);
                    break;

                case 'notify_parties':
                    $this->apiService->notify($transaction);
                    break;

                default:
                    throw new InvalidArgumentException("Unknown SAGA step: {$step}");
            }
        } catch (ServiceUnavailableException $e) {
            throw $e;
        } catch (AuthorizationFailedException|NotificationFailedException $e) {
            throw $e;
        }
    }

    private function shouldRequeue(Throwable $exception): bool
    {
        $nonRetryableErrors = [
            RuntimeException::class,
            ModelNotFoundException::class,
            InvalidArgumentException::class,
            InsufficientFundsException::class,
            AuthorizationFailedException::class,
            NotificationFailedException::class,
        ];

        $shouldRequeue = !in_array(get_class($exception), $nonRetryableErrors, true);

        return $shouldRequeue;
    }
}