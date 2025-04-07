<?php

namespace HyperfTest\Cases\Integration;

use Mockery;
use Hyperf\Amqp\Result;
use App\Model\Transaction;
use Hyperf\DbConnection\Db;
use PHPUnit\Framework\TestCase;
use App\Repository\WalletRepository;
use App\Repository\TransactionRepository;
use App\Amqp\Consumer\SagaCompensateConsumer;

class SagaCompensateConsumerTest extends TestCase
{
    protected $consumer;
    protected $database;
    protected $walletRepository;
    protected $transactionRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->database = Mockery::mock(Db::class);
        $this->walletRepository = Mockery::mock(WalletRepository::class);
        $this->transactionRepository = Mockery::mock(TransactionRepository::class);
        $this->database->shouldReceive('rollBack');

        $this->consumer = new SagaCompensateConsumer(
            $this->database,
            $this->walletRepository,
            $this->transactionRepository
        );
    }

    public function testCompensationRevertsDebitAndCredit(): void
    {
        $transaction = new Transaction([
            'tx_id' => 'uuid-123',
            'payer_wallet_id' => 1,
            'payee_wallet_id' => 2,
            'amount' => 100.0,
            'saga_steps_completed' => ['debit_payer' => true, 'credit_payee' => true],
            'compensated_at' => null,
        ]);
        $message = Mockery::mock('PhpAmqpLib\Message\AMQPMessage');

        $this->transactionRepository->shouldReceive('findByTxId')->with('uuid-123')->andReturn($transaction);
        $this->database->shouldReceive('beginTransaction')->once();
        $this->transactionRepository->shouldReceive('isStepCompleted')->with(
            $transaction,
            Transaction::SAGA_STATUS_DEBIT_PAYER
        )->andReturn(true);
        $this->walletRepository->shouldReceive('creditWallet')->with(1, 100.0)->once();
        $this->transactionRepository->shouldReceive('isStepCompleted')->with(
            $transaction,
            Transaction::SAGA_STATUS_CREDIT_PAYEE
        )->andReturn(true);
        $this->walletRepository->shouldReceive('debitWallet')->with(2, 100.0)->once();
        $this->transactionRepository->shouldReceive('markAsCompensated')->with('uuid-123', 'notify_parties')->once();
        $this->database->shouldReceive('commit')->once();

        $result = $this->consumer->consumeMessage(['tx_id' => 'uuid-123', 'failed_step' => 'notify_parties'], $message);
        $this->assertEquals(Result::ACK, $result);
        $this->database->shouldReceive('rollBack')->never();
    }

    public function testAlreadyCompensatedTransactionIsSkipped(): void
    {
        $transaction = new Transaction([
            'tx_id' => 'uuid-123',
            'compensated_at' => '2025-04-06 12:00:00',
        ]);
        $message = Mockery::mock('PhpAmqpLib\Message\AMQPMessage');

        $this->transactionRepository->shouldReceive('findByTxId')->with('uuid-123')->andReturn($transaction);

        $result = $this->consumer->consumeMessage(['tx_id' => 'uuid-123', 'failed_step' => 'notify_parties'], $message);

        $this->assertEquals(Result::ACK, $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}