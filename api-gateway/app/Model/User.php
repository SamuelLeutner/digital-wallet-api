<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\Database\Model\Relations\HasMany;
use Hyperf\Database\Model\Relations\HasOne;

/**
 */
class User extends Model
{
    public const USER_TYPES = [
        self::USER_TYPE_PF,
        self::USER_TYPE_PJ,
    ];
    public const USER_TYPE_PF = 'pf';
    public const USER_TYPE_PJ = 'pj';
    /**
     * The table associated with the model.
     */
    protected ?string $table = 'users';
    /**
     * The attributes that are mass assignable.
     */
    protected array $fillable = [
        'name',
        'email',
        'password',
        'cpf_cnpj',
        'user_type',
    ];
    /**
     * The attributes that should be cast to native types.
     */
    protected array $casts = [];

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class, 'user_id', 'id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'user_id', 'id');
    }

    public function isMerchant(): bool
    {
        return $this->user_type === self::USER_TYPE_PF;
    }
}