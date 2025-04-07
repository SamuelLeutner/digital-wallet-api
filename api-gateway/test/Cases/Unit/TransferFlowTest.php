<?php

namespace HyperfTest\Cases\Unit;

use Mockery;
use App\Model\User;
use Hyperf\Amqp\Producer;
use App\Model\Transaction;
use Hyperf\Amqp\Result;
use Hyperf\DbConnection\Db;
use PHPUnit\Framework\TestCase;
use App\Service\TransferService;
use App\Repository\UserRepository;
use App\Service\ExternalAPIService;
use App\Repository\WalletRepository;
use App\Amqp\Consumer\TransfersConsumer;
use App\Amqp\Producer\TransfersProducer;
use App\Repository\TransactionRepository;
use App\Amqp\Consumer\SagaCompensateConsumer;
use App\Exception\ExternalAPI\NotificationFailedException;

class TransferFlowTest extends TestCase
{
    protected $transferService;
    protected $transfersConsumer;
    protected $compensateConsumer;

    protected $database;
    protected $producer;
    protected $userRepository;
    protected $transactionRepository;
    protected $apiService;
    protected $walletRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = Mockery::mock(Db::class);
        $this->producer = Mockery::mock(Producer::class);
        $this->userRepository = Mockery::mock(UserRepository::class);
        $this->transactionRepository = Mockery::mock(TransactionRepository::class);
        $this->apiService = Mockery::mock(ExternalAPIService::class);
        $this->walletRepository = Mockery::mock(WalletRepository::class);

        $this->transferService = new TransferService(
            $this->database,
            $this->producer,
            $this->userRepository,
            $this->transactionRepository
        );

        $this->transfersConsumer = new TransfersConsumer(
            $this->producer,
            $this->apiService,
            $this->walletRepository,
            $this->transactionRepository
        );

        $this->compensateConsumer = new SagaCompensateConsumer(
            $this->database,
            $this->walletRepository,
            $this->transactionRepository
        );
    }

    public function testFullTransferFlowWithNotificationFailure(): void
    {
        $payer = Mockery::mock(User::class)->makePartial();
        $payer->id = 1;
        $payer->user_type = User::USER_TYPE_PF;
        $payer->shouldReceive('isMerchant')->andReturn(false);

        $payee = Mockery::mock(User::class)->makePartial();
        $payee->id = 2;
        $payee->user_type = User::USER_TYPE_PJ;

        $payer->wallet = Mockery::mock();
        $payer->wallet->shouldReceive('hasSufficientBalance')
            ->with(100.0)
            ->andReturn(true);

        $state = [
            'saga_step' => 0,
            'saga_steps_completed' => [],
        ];

        $transaction = Mockery::mock(Transaction::class)->makePartial();
        $transaction->id = 1;
        $transaction->tx_id = 'uuid-123';
        $transaction->payer_wallet_id = 1;
        $transaction->payee_wallet_id = 2;
        $transaction->amount = 100.0;
        $transaction->status = Transaction::STATUS_PROCESSING;
        $transaction->shouldReceive('toArray')->andReturn([
            'id' => $transaction->id,
            'tx_id' => $transaction->tx_id,
            'payer_wallet_id' => 1,
            'payee_wallet_id' => 2,
            'amount' => 100.0,
            'status' => Transaction::STATUS_PROCESSING,
            'saga_step' => $state['saga_step'],
            'saga_steps_completed' => $state['saga_steps_completed'],
        ]);

        $this->database->shouldReceive('beginTransaction')->once()->andReturn(null);
        $this->database->shouldReceive('commit')->once()->andReturn(true);
        $this->database->shouldReceive('rollBack')->never();

        $this->userRepository->shouldReceive('getUsersByIds')
            ->with([1, 2], 'user_type')
            ->andReturn([$payer, $payee]);

        $this->transactionRepository->shouldReceive('createTransaction')
            ->with($payer, $payee, 100.0)
            ->andReturn($transaction);

        $this->transactionRepository->shouldReceive('initializeSaga')
            ->with('uuid-123')
            ->andReturn($transaction);

        $this->producer->shouldReceive('produce')
            ->with(Mockery::type(TransfersProducer::class))
            ->once()
            ->andReturn(true);

        $this->transactionRepository->shouldReceive('findByTxId')
            ->with('uuid-123')
            ->andReturn($transaction);

        $this->apiService->shouldReceive('authorize')
            ->once()
            ->andReturn(true);

        $this->walletRepository->shouldReceive('debitWallet')
            ->with(1, 100.0)
            ->once()
            ->andReturn(true);

        $this->walletRepository->shouldReceive('creditWallet')
            ->with(2, 100.0)
            ->once()
            ->andReturn(true);

        $this->apiService->shouldReceive('notify')
            ->with($transaction)
            ->once()
            ->andThrow(new NotificationFailedException('Service unavailable'));

        $this->transactionRepository->shouldReceive('completeSagaStep')
            ->with('uuid-123', 'authorize')
            ->once()
            ->andReturnUsing(function () use ($transaction, &$state) {
                $state['saga_steps_completed']['authorize'] = true;
                $state['saga_step'] = 1;

                return $transaction;
            });

        $this->transactionRepository->shouldReceive('completeSagaStep')
            ->with('uuid-123', 'debit_payer')
            ->once()
            ->andReturnUsing(function () use ($transaction, &$state) {
                $state['saga_steps_completed']['debit_payer'] = true;
                $state['saga_step'] = 2;

                return $transaction;
            });

        $this->transactionRepository->shouldReceive('completeSagaStep')
            ->with('uuid-123', 'credit_payee')
            ->once()
            ->andReturnUsing(function () use ($transaction, &$state) {
                $state['saga_steps_completed']['credit_payee'] = true;
                $state['saga_step'] = 3;

                return $transaction;
            });

        $this->transactionRepository->shouldReceive('getCurrentStep')
            ->with($transaction)
            ->andReturnUsing(function () use (&$state) {
                return Transaction::SAGA_STEPS[$state['saga_step']] ?? null;
            });

        $this->transactionRepository->shouldReceive('areAllStepsCompleted')
            ->with($transaction)
            ->andReturnUsing(function () use (&$state) {
                $completed = $state['saga_steps_completed'];
                foreach (Transaction::SAGA_STEPS as $step) {
                    if (!isset($completed[$step])) {
                        return false;
                    }
                }

                return true;
            });

        $this->transactionRepository->shouldReceive('markAsFailed')
            ->with('uuid-123', 'Service unavailable')
            ->once()
            ->andReturn($transaction);

        $this->transactionRepository->shouldReceive('markAsFailed')
            ->with('uuid-123', Mockery::pattern('/Indirect modification of overloaded property/'))
            ->andReturn($transaction)
            ->atMost()->once();

        $this->producer->shouldReceive('produce')
            ->with(Mockery::type('App\Amqp\Producer\SagaCompensateProducer'))
            ->once()
            ->andReturn(true);

        $this->database->shouldReceive('beginTransaction')->once()->andReturn(null);
        $this->transactionRepository->shouldReceive('findByTxId')
            ->with('uuid-123')
            ->andReturn($transaction);

        $this->transactionRepository->shouldReceive('isStepCompleted')
            ->with($transaction, Transaction::SAGA_STATUS_DEBIT_PAYER)
            ->once()
            ->andReturn(true);

        $this->walletRepository->shouldReceive('creditWallet')
            ->with(1, 100.0)
            ->once()
            ->andReturn(true);

        $this->transactionRepository->shouldReceive('isStepCompleted')
            ->with($transaction, Transaction::SAGA_STATUS_CREDIT_PAYEE)
            ->once()
            ->andReturn(true);

        $this->walletRepository->shouldReceive('debitWallet')
            ->with(2, 100.0)
            ->once()
            ->andReturn(true);

        $this->transactionRepository->shouldReceive('markAsCompensated')
            ->with('uuid-123', 'notify_parties')
            ->once()
            ->andReturn($transaction);

        $this->database->shouldReceive('commit')->once()->andReturn(true);
        $this->database->shouldReceive('rollBack')->never();

        $result = $this->transferService->transfer(['payer' => 1, 'payee' => 2, 'value' => 100.0]);
        $this->assertEquals('queued', $result['status']);
        $this->assertEquals(1, $result['transaction_id']);

        $message = Mockery::mock('PhpAmqpLib\Message\AMQPMessage');
        $consumerResult = $this->transfersConsumer->consumeMessage([
            'tx_id' => 'uuid-123',
            'payer_wallet_id' => 1,
            'payee_wallet_id' => 2,
            'amount' => 100.0,
        ], $message);
        $this->assertEquals(Result::ACK, $consumerResult);

        $compensateResult = $this->compensateConsumer->consumeMessage([
            'tx_id' => 'uuid-123',
            'failed_step' => 'notify_parties',
        ], $message);
        $this->assertEquals(Result::ACK, $compensateResult);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}