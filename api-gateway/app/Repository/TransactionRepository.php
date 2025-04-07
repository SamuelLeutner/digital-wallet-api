<?php

declare(strict_types=1);

namespace App\Repository;

use Carbon\Carbon;
use App\Model\User;
use Hyperf\Stringable\Str;
use App\Model\Transaction;
use Hyperf\DbConnection\Db;

class TransactionRepository
{
    public function __construct(
        private readonly Db $database,
        private readonly Carbon $carbon,
        private readonly Transaction $model,
    ) {
    }

    public function createTransaction(User $payer, User $payee, float $amount): Transaction
    {
        return $this->database->transaction(function () use ($payer, $payee, $amount) {
            return $this->model->create([
                'tx_id' => Str::uuid()->toString(),
                'payer_id' => $payer->id,
                'payee_id' => $payee->id,
                'payer_wallet_id' => $payer->wallet->id,
                'payee_wallet_id' => $payee->wallet->id,
                'amount' => $amount,
            ]);
        });
    }

    public function findByTxId(string $txId): Transaction
    {
        return $this->model->newQuery()
            ->where('tx_id', $txId)
            ->firstOrFail();
    }

    public function updateTransactionStep(string $txId, array $data): Transaction
    {
        return $this->database->transaction(function () use ($txId, $data) {
            $transaction = $this->findByTxId($txId);
            $transaction->update($data);

            return $transaction->fresh();
        });
    }

    public function markAsFailed(string $txId, string $error): Transaction
    {
        return $this->updateTransactionStep($txId, [
            'status' => Transaction::STATUS_FAILED,
            'error' => $error,
        ]);
    }

    public function initializeSaga(string $txId): Transaction
    {
        return $this->updateTransactionStep($txId, [
            'saga_id' => Str::uuid()->toString(),
            'status' => Transaction::STATUS_PROCESSING,
            'saga_step' => 0,
            'saga_steps_completed' => [],
        ]);
    }

    public function completeSagaStep(string $txId, string $step): Transaction
    {
        return $this->database->transaction(function () use ($txId, $step) {
            $transaction = $this->findByTxId($txId);
            $completed = $transaction->saga_steps_completed ?? [];
            $completed[$step] = true;

            $stepIndex = array_search($step, Transaction::SAGA_STEPS);
            $nextStep = $stepIndex !== false ? $stepIndex + 1 : 0;

            $transaction->update([
                'saga_steps_completed' => $completed,
                'saga_step' => $nextStep,
            ]);

            return $transaction->fresh();
        });
    }

    public function markAsCompensated(string $txId, string $failedStep): Transaction
    {
        $transaction = $this->findByTxId($txId);
        $compensatedSteps = $transaction->saga_steps_completed ?? [];

        return $this->updateTransactionStep($txId, [
            'compensated_at' => $this->carbon->now(),
            'compensation_data' => [
                'failed_step' => $failedStep,
                'compensated_steps' => $compensatedSteps,
            ],
        ]);
    }

    public function getCurrentStep(Transaction $transaction): ?string
    {
        if (isset(Transaction::SAGA_STEPS[$transaction->saga_step])) {
            return Transaction::SAGA_STEPS[$transaction->saga_step];
        }

        return null;
    }

    public function areAllStepsCompleted(Transaction $transaction): bool
    {
        $completed = $transaction->saga_steps_completed ?? [];
        foreach (Transaction::SAGA_STEPS as $step) {
            if (!isset($completed[$step])) {
                return false;
            }
        }

        return true;
    }

    public function isStepCompleted(Transaction $transaction, string $step): bool
    {
        return isset($transaction->saga_steps_completed[$step]);
    }

    public function completeSaga(string $txId): Transaction
    {
        return $this->updateTransactionStep($txId, [
            'status' => Transaction::STATUS_COMPLETED,
        ]);
    }
}