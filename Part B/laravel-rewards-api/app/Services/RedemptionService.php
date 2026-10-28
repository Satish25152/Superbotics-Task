<?php

namespace App\Services;

use App\Exceptions\RedemptionDeniedException;
use App\Models\PointsLedger;
use App\Models\Product;
use App\Models\Redemption;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RedemptionService
{
    public function __construct(private readonly PointsBalanceService $balances)
    {
    }

    /**
     * All balance-changing writers must acquire the same user lock first.
     * This serializes concurrent redemptions for a single user on InnoDB/PostgreSQL.
     *
     * @return array{redemption: Redemption, remaining_balance: int}
     */
    public function redeem(int $userId, int $productId): array
    {
        return DB::transaction(function () use ($userId, $productId): array {
            // Lock an existing parent row; locking the ledger SUM is not sufficient.
            $user = User::query()->whereKey($userId)->lockForUpdate()->first();
            if ($user === null) {
                throw new RedemptionDeniedException('User not found.', 'user_not_found');
            }

            if ($user->profile?->status === 'restricted') {
                throw new RedemptionDeniedException(
                    'This user has a restricted profile and cannot redeem rewards.',
                    'restricted_user'
                );
            }

            $product = Product::query()->with('category.limit')->find($productId);
            if ($product === null || ! $product->is_active) {
                throw new RedemptionDeniedException(
                    'Product is not available for redemption.',
                    'product_unavailable'
                );
            }

            $limit = $product->category?->limit?->monthly_limit;
            if ($limit === null) {
                throw new RedemptionDeniedException(
                    'The category monthly limit is not configured.',
                    'category_limit_unconfigured'
                );
            }

            $now = now();
            $monthStart = $now->copy()->startOfMonth();
            $nextMonthStart = $monthStart->copy()->addMonth();

            $usedThisMonth = Redemption::query()
                ->where('user_id', $user->id)
                ->where('category_id', $product->category_id)
                ->where('redeemed_at', '>=', $monthStart)
                ->where('redeemed_at', '<', $nextMonthStart)
                ->count();

            if ($usedThisMonth >= $limit) {
                throw new RedemptionDeniedException(
                    "Monthly category limit reached ({$limit} redemptions).",
                    'category_limit_reached'
                );
            }

            $balance = $this->balances->balanceFor($user->id);
            if ($balance < $product->points_cost) {
                throw new RedemptionDeniedException(
                    "Not enough points. Required: {$product->points_cost}; available: {$balance}.",
                    'insufficient_points'
                );
            }

            // Both rows commit or both roll back; history preserves redemption-time cost.
            $redemption = Redemption::create([
                'reference' => Str::uuid()->toString(),
                'user_id' => $user->id,
                'product_id' => $product->id,
                'category_id' => $product->category_id,
                'points_spent' => $product->points_cost,
                'redeemed_at' => $now,
            ]);

            PointsLedger::create([
                'user_id' => $user->id,
                'type' => 'spend',
                'points' => $product->points_cost,
                'redemption_id' => $redemption->id,
                'occurred_at' => $now,
            ]);

            return [
                'redemption' => $redemption,
                'remaining_balance' => $balance - $product->points_cost,
            ];
        }, 5); // Retry transaction on a database deadlock (max 5 attempts).
    }
}
