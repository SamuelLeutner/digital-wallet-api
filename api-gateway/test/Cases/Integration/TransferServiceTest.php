<?php

namespace HyperfTest\Cases\Integration;

use Mockery;
use App\Model\User;
use Hyperf\Amqp\Producer;
use App\Model\Transaction;
use Hyperf\DbConnection\Db;
use PHPUnit\Framework\TestCase;
use App\Service\TransferService;
use App\Repository\UserRepository;
use App\Amqp\Producer\TransfersProducer;
use App\Repository\TransactionRepository;
use App\Exception\Transfer\BusinessException;
use App\Exception\Transfer\InsufficientFundsException;

class TransferServiceTest extends TestCase
{
    protected $database;
    protected $producer;
    protected $userRepository;
    protected $transferService;
    protected $transactionRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->database = Mockery::mock(Db::class);
        $this->producer = Mockery::mock(Producer::class);
        $this->userRepository = Mockery::mock(UserRepository::class);
        $this->transactionRepository = Mockery::mock(TransactionRepository::class);

        $this->transferService = new TransferService(
            $this->database,
            $this->producer,
            $this->userRepository,
            $this->transactionRepository
        );
    }

    public function testSuccessfulTransferQueuesTransaction(): void
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

        $transaction = Mockery::mock(Transaction::class)->makePartial();
        $transaction->id = 1;
        $transaction->tx_id = 'uuid-123';
        $transaction->shouldReceive('toArray')->andReturn([
            'id' => 1,
            'tx_id' => 'uuid-123',
            'payer_id' => 1,
            'payee_id' => 2,
            'amount' => 100.0,
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

        $result = $this->transferService->transfer(['payer' => 1, 'payee' => 2, 'value' => 100.0]);

        $this->assertEquals('queued', $result['status']);
        $this->assertEquals(1, $result['transaction_id']);
    }

    public function testTransferFailsForMerchantPayer(): void
    {
        $payer = Mockery::mock(User::class)->makePartial();
        $payer->id = 1;
        $payer->user_type = User::USER_TYPE_PJ;
        $payer->shouldReceive('isMerchant')->andReturn(true);

        $payee = Mockery::mock(User::class)->makePartial();
        $payee->id = 2;
        $payee->user_type = User::USER_TYPE_PF;

        $this->database->shouldReceive('beginTransaction')->once()->andReturn(null);
        $this->database->shouldReceive('rollBack')->once()->andReturn(true);
        $this->database->shouldReceive('commit')->never();

        $this->userRepository->shouldReceive('getUsersByIds')
            ->with([1, 2], 'user_type')
            ->andReturn([$payer, $payee]);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Merchants cannot initiate transfers');
        $this->transferService->transfer(['payer' => 1, 'payee' => 2, 'value' => 100.0]);
    }

    public function testTransferFailsForInsufficientFunds(): void
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
            ->andReturn(false);

        $this->database->shouldReceive('beginTransaction')->once()->andReturn(null);
        $this->database->shouldReceive('rollBack')->once()->andReturn(true);
        $this->database->shouldReceive('commit')->never();

        $this->userRepository->shouldReceive('getUsersByIds')
            ->with([1, 2], 'user_type')
            ->andReturn([$payer, $payee]);

        $this->expectException(InsufficientFundsException::class);
        $this->transferService->transfer(['payer' => 1, 'payee' => 2, 'value' => 100.0]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}