<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\User;
use App\Exception\Transfer\BusinessException;

class UserRepository
{
    public function __construct(
        private readonly User $model,
    ) {
    }

    public function getUsersByIds(array $userIds, string $column): array
    {
        $users = $this->model->newQuery()
            ->whereIn('id', $userIds)
            ->get(['id', $column])
            ->keyBy('id');

        $payer = $users->get($userIds[0]);
        $payee = $users->get($userIds[1]);

        if ($users->count() !== count($userIds) || !$payer || !$payee) {
            throw new BusinessException('USER_NOT_FOUND', 'One or more users not found', 404);
        }

        return [$payer, $payee];
    }
}