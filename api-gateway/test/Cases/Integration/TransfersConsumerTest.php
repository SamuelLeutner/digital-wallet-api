<?php

namespace HyperfTest\Cases\Integration;

use Mockery;
use Hyperf\Amqp\Result;
use Hyperf\Amqp\Producer;
use App\Model\Transaction;
use PHPUnit\Framework\TestCase;
use App\Service\ExternalAPIService;
use App\Repository\WalletRepository;
use App\Amqp\Consumer\TransfersConsumer;
use App\Repository\TransactionRepository;
use App\Amqp\Producer\SagaCompensateProducer;
use App\Exception\ExternalAPI\NotificationFailedException;

class TransfersConsumerTest extends TestCase
{
    protected $consumer;
    protected $producer;
    protected $apiService;
    protected $walletRepository;
    protected $transactionRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->producer = Mockery::mock(Producer::class);
        $this->apiService = Mockery::mock(ExternalAPIService::class);
        $this->walletRepository = Mockery::mock(WalletRepository::class);
        $this->transactionRepository = Mockery::mock(TransactionRepository::class);

        $this->consumer = new TransfersConsumer(
            $this->producer,
            $this->apiService,
            $this->walletRepository,
            $this->transactionRepository
        );
    }

    public function testSuccessfulSagaExecution(): void
    {
        $transaction = new Transaction([
            'tx_id' => 'uuid-123',
            'payer_wallet_id' => 1,
            'payee_wallet_id' => 2,
            'amount' => 100.0,
            'status' => Transaction::STATUS_PROCESSING,
            'saga_step' => 0,
            'saga_steps_completed' => [],
        ]);
        $message = Mockery::mock('PhpAmqpLib\Message\AMQPMessage');

        $this->transactionRepository->shouldReceive('findByTxId')
            ->with('uuid-123')
            ->andReturn($transaction);

        $this->apiService->shouldReceive('authorize')
            ->andReturn(true);

        $this->walletRepository->shouldReceive('debitWallet')
            ->with(1, 100.0)
            ->once();

        $this->walletRepository->shouldReceive('creditWallet')
            ->with(2, 100.0)
            ->once();

        $this->apiService->shouldReceive('notify')
            ->with($transaction)
            ->once();

        $this->transactionRepository->shouldReceive('completeSagaStep')
            ->andReturnUsing(function ($txId, $step) use ($transaction) {
                $completed = $transaction->saga_steps_completed ?? [];
                $completed[$step] = true;
                $transaction->saga_steps_completed = $completed;
                $transaction->saga_step++;

                return $transaction;
            })
            ->times(4);

        $this->transactionRepository->shouldReceive('getCurrentStep')
            ->andReturn(
                'authorize',
                'debit_payer',
                'credit_payee',
                'notify_parties',
                null
            );

        $this->transactionRepository->shouldReceive('areAllStepsCompleted')
            ->andReturn(false, false, false, true);

        $this->transactionRepository->shouldReceive('completeSaga')
            ->with('uuid-123')
            ->once();

        $this->transactionRepository->shouldReceive('markAsFailed')
            ->never();

        $messageData = [
            'tx_id' => 'uuid-123',
            'payer_wallet_id' => 1,
            'payee_wallet_id' => 2,
            'amount' => 100.0,
        ];

        $result = $this->consumer->consumeMessage($messageData, $message);

        $this->assertEquals(Result::ACK, $result);
    }

    public function testNotificationFailureTriggersCompensation(): void
    {
        $transaction = new Transaction([
            'tx_id' => 'uuid-123',
            'payer_wallet_id' => 1,
            'payee_wallet_id' => 2,
            'amount' => 100.0,
            'status' => Transaction::STATUS_PROCESSING,
            'saga_step' => 0,
            'saga_steps_completed' => [],
            'saga_id' => 'saga-123',
        ]);
        $message = Mockery::mock('PhpAmqpLib\Message\AMQPMessage');


        $this->transactionRepository->shouldReceive('findByTxId')
            ->with('uuid-123')
            ->andReturn($transaction);

        $this->apiService->shouldReceive('authorize')
            ->andReturn(true);

        $this->walletRepository->shouldReceive('debitWallet')
            ->with(1, 100.0)
            ->once();

        $this->walletRepository->shouldReceive('creditWallet')
            ->with(2, 100.0)
            ->once();

        $this->apiService->shouldReceive('notify')
            ->with($transaction)
            ->andThrow(new NotificationFailedException('Service unavailable'));

        $this->transactionRepository->shouldReceive('completeSagaStep')
            ->andReturnUsing(function ($txId, $step) use ($transaction) {
                $completed = $transaction->saga_steps_completed ?? [];
                $completed[$step] = true;
                $transaction->saga_steps_completed = $completed;
                $transaction->saga_step++;

                return $transaction;
            })
            ->times(3);

        $this->transactionRepository->shouldReceive('getCurrentStep')
            ->andReturn(
                'authorize',
                'debit_payer',
                'credit_payee',
                'notify_parties'
            );

        $this->transactionRepository->shouldReceive('areAllStepsCompleted')
            ->andReturn(false, false, false);

        $this->transactionRepository->shouldReceive('markAsFailed')
            ->with('uuid-123', 'Service unavailable')
            ->once()
            ->andReturn($transaction);

        $this->producer->shouldReceive('produce')
            ->once()
            ->with(Mockery::type(SagaCompensateProducer::class))
            ->andReturn(true);

        $messageData = [
            'tx_id' => 'uuid-123',
            'payer_wallet_id' => 1,
            'payee_wallet_id' => 2,
            'amount' => 100.0,
        ];
        $result = $this->consumer->consumeMessage($messageData, $message);

        $this->assertEquals(Result::ACK, $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}