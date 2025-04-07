<?php

declare(strict_types=1);

namespace App\Repository;

use Throwable;
use App\Model\Notification;
use Hyperf\DbConnection\Db;
use App\Exception\Transfer\BusinessException;

class NotificationRepository
{
    public function __construct(
        private readonly Db $database,
        private readonly Notification $model,
    ) {
    }

    public function create(array $attributes): Notification
    {
        try {
            return $this->database->transaction(function () use ($attributes) {
                return $this->model->newQuery()->create([
                    'user_id' => $attributes['user_id'],
                    'title' => $attributes['title'],
                    'message' => $attributes['message'],
                ]);
            });
        } catch (Throwable $e) {
            throw new BusinessException($e->getMessage());
        }
    }
}