<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RewardsDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $now = now();
            $timestamp = $now->toDateTimeString();

            // Five categories, each with a per-user monthly limit of two.
            $categoryNames = ['Electronics', 'Shopping', 'Food', 'Travel', 'Lifestyle'];
            foreach ($categoryNames as $name) {
                $id = DB::table('categories')->insertGetId([
                    'name' => $name,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);

                DB::table('category_limits')->insert([
                    'category_id' => $id,
                    'monthly_limit' => 2,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);

                for ($i = 1; $i <= 4; $i++) {
                    DB::table('products')->insert([
                        'category_id' => $id,
                        'name' => "{$name} Reward {$i}",
                        'points_cost' => 100 + ($i * 50),
                        'is_active' => true,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]);
                }
            }

            $productsByCategory = DB::table('products')->orderBy('id')->get()
                ->groupBy('category_id')->values()->all();

            $passwordHash = Hash::make('Password123!');
            $userRows = [];
            for ($i = 1; $i <= 1000; $i++) {
                $userRows[] = [
                    'name' => sprintf('Demo User %04d', $i),
                    'email' => sprintf('demo%04d@example.test', $i),
                    'password' => $passwordHash,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }

            foreach (array_chunk($userRows, 100) as $batch) {
                DB::table('users')->insert($batch);
            }

            $userIds = DB::table('users')
                ->where('email', 'like', 'demo%@example.test')
                ->orderBy('id')->pluck('id')->all();

            $profileRows = [];
            $earnRows = [];
            $initialEarnedAt = $now->copy()->subMonths(12)->toDateTimeString();
            foreach ($userIds as $index => $id) {
                $profileRows[] = [
                    'user_id' => $id,
                    'status' => ($index + 1) % 20 === 0 ? 'restricted' : 'standard',
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
                $earnRows[] = [
                    'user_id' => $id,
                    'type' => 'earn',
                    'points' => 20000,
                    'redemption_id' => null,
                    'occurred_at' => $initialEarnedAt,
                    'created_at' => $initialEarnedAt,
                    'updated_at' => $initialEarnedAt,
                ];
            }

            foreach (array_chunk($profileRows, 100) as $batch) {
                DB::table('profiles')->insert($batch);
            }
            foreach (array_chunk($earnRows, 100) as $batch) {
                DB::table('points_ledger')->insert($batch);
            }

            // Exactly 10,000 past redemptions. Each user has 10 redemptions,
            // spread across 10 previous months (no monthly limit violation).
            // Use bulk inserts to keep the seeder fast and memory usage modest.
            $redemptionRows = [];
            for ($i = 0; $i < 10000; $i++) {
                $userIndex = $i % 1000;
                $round = intdiv($i, 1000); // 0 through 9
                $categoryIndex = ($userIndex + $round) % 5;
                $productIndex = ($userIndex + $round) % 4;
                $product = $productsByCategory[$categoryIndex][$productIndex];
                $redeemedAt = $now->copy()->startOfMonth()->subMonths($round + 1)
                    ->addDays(($userIndex % 24) + 1)
                    ->addHours($i % 23)->toDateTimeString();

                $redemptionRows[] = [
                    'reference' => sprintf('00000000-0000-4000-8000-%012d', $i + 1),
                    'user_id' => $userIds[$userIndex],
                    'product_id' => $product->id,
                    'category_id' => $product->category_id,
                    'points_spent' => $product->points_cost,
                    'redeemed_at' => $redeemedAt,
                    'created_at' => $redeemedAt,
                    'updated_at' => $redeemedAt,
                ];

                if (count($redemptionRows) === 100 || $i === 9999) {
                    DB::table('redemptions')->insert($redemptionRows);
                    $idsByReference = DB::table('redemptions')
                        ->whereIn('reference', array_column($redemptionRows, 'reference'))
                        ->pluck('id', 'reference');

                    $spendRows = [];
                    foreach ($redemptionRows as $row) {
                        $spendRows[] = [
                            'user_id' => $row['user_id'],
                            'type' => 'spend',
                            'points' => $row['points_spent'],
                            'redemption_id' => $idsByReference[$row['reference']],
                            'occurred_at' => $row['redeemed_at'],
                            'created_at' => $row['redeemed_at'],
                            'updated_at' => $row['redeemed_at'],
                        ];
                    }
                    DB::table('points_ledger')->insert($spendRows);
                    $redemptionRows = [];
                }
            }
        });
    }
}
