<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\Database\Model\Relations\BelongsTo;

/**
 */
class Transaction extends Model
{
    public const TRANSACTION_STATUS = [
        self::STATUS_FAILED,
        self::STATUS_PENDING,
        self::STATUS_COMPLETED,
        self::STATUS_PROCESSING,
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_PROCESSING = 'processing';

    public const SAGA_STEPS = [
        self::SAGA_STATUS_AUTHORIZE,
        self::SAGA_STATUS_DEBIT_PAYER,
        self::SAGA_STATUS_CREDIT_PAYEE,
        self::SAGA_STATUS_NOTIFY_PARTIES,
    ];

    public const SAGA_STATUS_AUTHORIZE = 'authorize';
    public const SAGA_STATUS_DEBIT_PAYER = 'debit_payer';
    public const SAGA_STATUS_CREDIT_PAYEE = 'credit_payee';
    public const SAGA_STATUS_NOTIFY_PARTIES = 'notify_parties';

    /**
     * The table associated with the model.
     */
    protected ?string $table = 'transactions';
    /**
     * The attributes that are mass assignable.
     */
    protected array $fillable = [
        'tx_id',
        'payer_id',
        'payee_id',
        'payer_wallet_id',
        'payee_wallet_id',
        'status',
        'amount',
        'saga_id',
        'saga_step',
        'saga_steps_completed',
        'compensated_at',
        'compensation_data',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected array $casts = [
        'amount' => 'float',
        'compensation_data' => 'array',
        'saga_steps_completed' => 'array',
    ];
}