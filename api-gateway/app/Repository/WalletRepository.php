<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Wallet;
use Hyperf\DbConnection\Db;

class WalletRepository
{
    public function __construct(
        private readonly Db $database,
        private readonly Wallet $model,
    ) {
    }

    public function debitWallet(int $walletId, float $amount): void
    {
        $this->database->transaction(function () use ($walletId, $amount) {
            $wallet = $this->model->newQuery()
                ->where('id', $walletId)
                ->lockForUpdate()
                ->firstOrFail();

            $wallet->validateBalance($amount);

            $wallet->balance -= $amount;
            $wallet->save();
        });
    }

    public function creditWallet(int $walletId, float $amount): void
    {
        $this->database->transaction(function () use ($walletId, $amount) {
            $wallet = $this->model->newQuery()
                ->where('id', $walletId)
                ->lockForUpdate()
                ->firstOrFail();

            $wallet->balance += $amount;
            $wallet->save();
        });
    }
}