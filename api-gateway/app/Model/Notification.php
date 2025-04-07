<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\Database\Model\Relations\BelongsTo;

/**
 */
class Notification extends Model
{
    /**
     * The table associated with the model.
     */
    protected ?string $table = 'notifications';

    /**
     * The attributes that are mass assignable.
     */
    protected array $fillable = [
        'user_id',
        'title',
        'message',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected array $casts = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
