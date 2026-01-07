<?php

namespace App\Repositories;

use App\Models\Balance;

class BalanceRepository
{
    public function getByUserId(int $userId): ?Balance
    {
        return Balance::firstWhere('user_id', $userId);
    }

    public function getByUserIdForUpdateOrCreate(int $userId): Balance
    {
        return Balance::lockForUpdate()->firstOrCreate(
            ['user_id' => $userId],
            ['balance' => 0]
        );
    }

    public function save(Balance $balance): void
    {
        $balance->save();
    }
}


