<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryLimit;
use App\Models\PointsLedger;
use App\Models\Product;
use App\Models\Profile;
use App\Models\Redemption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RewardsApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(int $points = 500, string $status = 'standard'): User
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => Str::uuid().'@example.test',
            'password' => 'Password123!',
        ]);
        Profile::create(['user_id' => $user->id, 'status' => $status]);
        PointsLedger::create([
            'user_id' => $user->id,
            'type' => 'earn',
            'points' => $points,
            'occurred_at' => now()->subDay(),
        ]);

        return $user;
    }

    private function makeProduct(int $cost = 100, int $monthlyLimit = 2): Product
    {
        $category = Category::create(['name' => 'Category '.Str::random(12)]);
        CategoryLimit::create([
            'category_id' => $category->id,
            'monthly_limit' => $monthlyLimit,
        ]);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Reward Item',
            'points_cost' => $cost,
            'is_active' => true,
        ]);
    }

    public function test_redemption_succeeds_and_creates_one_spend_ledger_row(): void
    {
        $user = $this->makeUser(500);
        $product = $this->makeProduct(100);

        $this->postJson('/api/redemptions', [
            'user_id' => $user->id, 'product_id' => $product->id,
        ])->assertCreated()
            ->assertJsonPath('message', 'Redemption successful.')
            ->assertJsonPath('data.remaining_balance', 400)
            ->assertJsonPath('data.points_spent', 100);

        $this->assertDatabaseCount('redemptions', 1);
        $this->assertDatabaseHas('points_ledger', [
            'user_id' => $user->id, 'type' => 'spend', 'points' => 100,
        ]);
    }

    public function test_rejects_when_user_has_insufficient_points(): void
    {
        $user = $this->makeUser(50);
        $product = $this->makeProduct(100);

        $this->postJson('/api/redemptions', [
            'user_id' => $user->id, 'product_id' => $product->id,
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'insufficient_points');

        $this->assertDatabaseCount('redemptions', 0);
        $this->assertDatabaseCount('points_ledger', 1); // earn only
    }

    public function test_rejects_restricted_user(): void
    {
        $user = $this->makeUser(500, 'restricted');
        $product = $this->makeProduct();

        $this->postJson('/api/redemptions', [
            'user_id' => $user->id, 'product_id' => $product->id,
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'restricted_user');

        $this->assertDatabaseCount('redemptions', 0);
    }

    public function test_rejects_when_category_monthly_limit_is_reached(): void
    {
        $user = $this->makeUser(500);
        $product = $this->makeProduct(100, 2);

        foreach (range(1, 2) as $number) {
            $redeemedAt = now()->startOfMonth()->addDays($number - 1);
            $redemption = Redemption::create([
                'reference' => Str::uuid()->toString(),
                'user_id' => $user->id,
                'product_id' => $product->id,
                'category_id' => $product->category_id,
                'points_spent' => 100,
                'redeemed_at' => $redeemedAt,
            ]);
            PointsLedger::create([
                'user_id' => $user->id,
                'type' => 'spend',
                'points' => 100,
                'redemption_id' => $redemption->id,
                'occurred_at' => $redeemedAt,
            ]);
        }

        $this->postJson('/api/redemptions', [
            'user_id' => $user->id, 'product_id' => $product->id,
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'category_limit_reached');

        $this->assertDatabaseCount('redemptions', 2);
    }

    public function test_balance_endpoint_returns_net_ledger_points(): void
    {
        $user = $this->makeUser(500);
        $product = $this->makeProduct(100);
        $this->postJson('/api/redemptions', [
            'user_id' => $user->id, 'product_id' => $product->id,
        ])->assertCreated();

        $this->getJson("/api/users/{$user->id}/balance")
            ->assertOk()->assertJsonPath('balance', 400);
    }

    public function test_redeeming_twice_cannot_overspend_remaining_points(): void
    {
        $user = $this->makeUser(150);
        $product = $this->makeProduct(100);
        $payload = ['user_id' => $user->id, 'product_id' => $product->id];

        $this->postJson('/api/redemptions', $payload)->assertCreated();
        $this->postJson('/api/redemptions', $payload)
            ->assertUnprocessable()->assertJsonPath('code', 'insufficient_points');

        $this->assertDatabaseCount('redemptions', 1);
        $this->getJson("/api/users/{$user->id}/balance")
            ->assertOk()->assertJsonPath('balance', 50);
    }

    public function test_report_filters_by_dates_and_category_and_sums_full_result(): void
    {
        $user = $this->makeUser(3000);
        $productA = $this->makeProduct();
        $productB = $this->makeProduct();

        foreach ([
            [$productA, '2026-09-05 10:00:00', 100],
            [$productA, '2026-09-20 10:00:00', 150],
            [$productB, '2026-09-12 10:00:00', 300],
            [$productA, '2026-08-05 10:00:00', 999],
        ] as [$product, $date, $points]) {
            $redemption = Redemption::create([
                'reference' => Str::uuid()->toString(),
                'user_id' => $user->id,
                'product_id' => $product->id,
                'category_id' => $product->category_id,
                'points_spent' => $points,
                'redeemed_at' => $date,
            ]);
            PointsLedger::create([
                'user_id' => $user->id,
                'type' => 'spend',
                'points' => $points,
                'redemption_id' => $redemption->id,
                'occurred_at' => $date,
            ]);
        }

        $this->getJson('/api/reports/redemptions?from=2026-09-01&to=2026-09-30'
            .'&category_id='.$productA->category_id.'&per_page=1')
            ->assertOk()->assertJsonPath('count', 2)
            ->assertJsonPath('total_points', 250)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('pagination.last_page', 2);
    }

    public function test_invalid_payload_has_field_validation_errors(): void
    {
        $this->postJson('/api/redemptions', [])
            ->assertUnprocessable()->assertJsonValidationErrors(['user_id', 'product_id']);
    }
}
