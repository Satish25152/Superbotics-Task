<?php

namespace App\Services;

use App\Models\PointsLedger;

class PointsBalanceService
{
    public function balanceFor(int $userId): int
    {
        return (int) PointsLedger::query()
            ->where('user_id', $userId)
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'earn' THEN points ELSE -points END), 0) AS current_balance")
            ->value('current_balance');
    }
}
