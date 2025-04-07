<?php

declare(strict_types=1);

namespace App\Model;

use App\Exception\Transfer\InsufficientFundsException;
use Hyperf\Database\Model\Relations\BelongsTo;

/**
 */
class Wallet extends Model
{
    /**
     * The table associated with the model.
     */
    protected ?string $table = 'wallets';

    /**
     * The attributes that are mass assignable.
     */
    protected array $fillable = [
        'user_id',
        'balance',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected array $casts = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function hasSufficientBalance(float $amount): bool
    {
        return ($this->balance >= $amount) && ($amount >= 0);
    }

    public function validateBalance(float $amount): void
    {
        if (!$this->hasSufficientBalance($amount)) {
            throw new InsufficientFundsException();
        }
    }
}
