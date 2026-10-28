# Part B – Laravel Rewards Redemption API

An interview assignment implementation using **Laravel 12** and **PHP 8.2+**.

This is a minimal backend-only Laravel project: no JavaScript build step is needed.

## 1. What this project implements

- 7 tables: `users`, `profiles`, `categories`, `products`, `category_limits`, `points_ledger`, and `redemptions`.
- Seeder: **5 categories**, **20 products** (4 in each category), **1,000 users**, **1,000 starting-point earn rows**, and **10,000 historical redemptions** with corresponding spend ledger rows.
- `POST /api/redemptions` – validate request and redeem a product.
- `GET /api/users/{id}/balance` – calculate the points balance from ledger entries.
- `GET /api/reports/redemptions` – date/category filters, list, redemption count, total points, and pagination.
- Domain rules: restricted profile, insufficient points, per-user category limit per calendar month, inactive product.
- Atomic transaction and a user-row lock to prevent double-spending on MySQL/InnoDB and PostgreSQL.
- **8 feature tests** covering success, rejections, balance, sequential overspending, report, and validation.

## 2. Requirements

- PHP >= 8.2 and PHP extensions required by Laravel 12.
- Composer.
- **MySQL 8+ with InnoDB (recommended)** or PostgreSQL for real concurrent redemptions.
- SQLite can run the automated feature tests, **but SQLite does not implement Laravel's row-level `lockForUpdate()`**. Don't use SQLite as evidence of the concurrency guarantee.

## 3. Quick start

This is a minimal Laravel application (application source included; Composer dependencies are **not** bundled).

```bash
# Unzip the project, then enter the folder.
cd laravel-rewards-api
composer install

# macOS/Linux:
cp .env.example .env
# Windows PowerShell alternative:
# Copy-Item .env.example .env

php artisan key:generate
```

Create a MySQL database, for example:

```sql
CREATE DATABASE rewards_api CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Edit `.env` to match your MySQL credentials:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rewards_api
DB_USERNAME=root
DB_PASSWORD=
```

Then run:

```bash
php artisan migrate:fresh --seed
php artisan serve
```

**WARNING:** `migrate:fresh --seed` drops all tables in the configured database. Use only with your own development/demo database. To avoid dropping existing data on a new empty database, run `php artisan migrate --seed` instead.

API base URL: `http://127.0.0.1:8000/api`

## 4. Database overview

| Table | Important fields | Purpose |
| --- | --- | --- |
| `users` | `id`, `name`, `email`, `password` | User records |
| `profiles` | `user_id` (unique), `status` | `standard` or `restricted` |
| `categories` | `id`, `name` | Product groups |
| `products` | `category_id`, `name`, `points_cost`, `is_active` | Rewards available for redemption |
| `category_limits` | `category_id` (unique), `monthly_limit` | Maximum redeems per user in each category each calendar month |
| `points_ledger` | `user_id`, `type`, `points`, `redemption_id`, `occurred_at` | One earn/spend row per event; positive point magnitudes |
| `redemptions` | `reference` (unique), `user_id`, `product_id`, `category_id`, `points_spent`, `redeemed_at` | Permanent redemption event and historical cost snapshot |

**Ledger balance formula:** `SUM(earn points) - SUM(spend points)`. Every redemption inserts a `redemptions` record and exactly one linked `points_ledger` row with `type = spend`. Starting points are `earn` records. Product cost and category are copied into the redemption so later product edits don't alter past reports or monthly checks.

## 5. Example requests and responses

### A. Redeem reward

```http
POST /api/redemptions
Content-Type: application/json
Accept: application/json

{"user_id":1,"product_id":1}
```

Success: HTTP **201 Created**:

```json
{
  "message": "Redemption successful.",
  "data": {
    "redemption_id": 10001,
    "reference": "57f91c4a-a6bb-45b9-94ce-d8f095b1027b",
    "user_id": 1,
    "product_id": 1,
    "category_id": 1,
    "points_spent": 150,
    "remaining_balance": 18950,
    "redeemed_at": "2026-10-28T10:00:00+00:00"
  }
}
```

**All numbers above are illustrative.** Actual balances depend on the user and their seeded redemption history.

Business-rule failure: HTTP **422 Unprocessable Entity**:

```json
{
  "message": "Not enough points. Required: 150; available: 80.",
  "code": "insufficient_points"
}
```

Possible error codes: `restricted_user`, `insufficient_points`, `category_limit_reached`, `product_unavailable`, and `category_limit_unconfigured`. Validation errors return HTTP 422 with the standard Laravel `errors` object for `user_id` / `product_id`. Unknown user on the balance endpoint returns HTTP 404.

### B. Get balance

```http
GET /api/users/1/balance
Accept: application/json
```

Example HTTP 200:

```json
{"user_id":1,"balance":18950}
```

### C. Redemption report

```http
GET /api/reports/redemptions?from=2026-01-01&to=2026-09-30&category_id=1&per_page=20&page=1
Accept: application/json
```

Example HTTP 200 (abbreviated):

```json
{
  "data": [
    {"id":17,"user_id":5,"product_id":1,"category_id":1,"points_spent":150,"redeemed_at":"2026-09-12T10:00:00+00:00"}
  ],
  "total_points": 7500,
  "count": 40,
  "pagination": {"current_page":1,"last_page":2,"per_page":20}
}
```

Filters: `from` and `to` in `YYYY-MM-DD` format (inclusive), optional `category_id`, and `per_page` 1 to 100 (default 50). Total points and count refer to **all rows matching the filters**, not just the current page. The list is paginated to avoid returning 10,000 rows at once.

For curl on any terminal:

```bash
curl -X POST http://127.0.0.1:8000/api/redemptions \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"user_id":1,"product_id":1}'

curl -H "Accept: application/json" http://127.0.0.1:8000/api/users/1/balance

curl -H "Accept: application/json" \
  'http://127.0.0.1:8000/api/reports/redemptions?from=2026-01-01&to=2026-09-30&category_id=1'
```

## 6. No double-spending: transaction and locking

`app/Services/RedemptionService.php` runs the whole operation inside `DB::transaction()` and does these steps **in order**:

1. Lock the **user row** using `User::whereKey($userId)->lockForUpdate()->first()`.
2. Reject if the profile is `restricted`.
3. Validate that the product is available and its category limit is configured.
4. Count redemptions in that user/category from the first day of the current calendar month (inclusive) to the first of the next month (exclusive).
5. Compute current available points by summing the user's ledger earns minus spends.
6. Reject if the category limit is reached or points are insufficient.
7. Insert the redemption and linked `spend` ledger entry in the **same transaction**, then commit.

**Why the parent user row?** The ledger consists of many rows, and a `SUM()` lock is not a reliable mechanism for serializing new ledger inserts. Instead, all writers agree to lock one existing, unique `users.id` row before changing any balance. For the same user, request B waits while request A holds that row lock. After A commits, B checks the newly committed ledger/spend and category count. This prevents spending the same points twice and prevents two requests bypassing a category limit.

On a rejection/exception, Laravel rolls back the transaction. `DB::transaction(..., 5)` also allows retries on database deadlocks. **This guarantee relies on MySQL/InnoDB or PostgreSQL and on every balance-changing code path using the user-row-lock convention.** It does not apply to external code that writes ledger rows without using this convention.

## 7. Tests

```bash
php artisan test --testsuite=Feature
# Or:
composer test
```

The `phpunit.xml` test configuration uses a fresh in-memory SQLite database. The test scenarios are:

1. Successful redemption creates one spend ledger row and updates the balance.
2. Not enough points produces a clear 422 and makes no spend.
3. Restricted profile produces a clear 422.
4. Monthly category limit produces a clear 422.
5. Balance endpoint calculates net points.
6. Sequential attempts cannot overspend existing points.
7. Report filters by date and category; aggregates remain correct with pagination.
8. Missing/invalid inputs produce Form Request validation errors.

**Concurrency testing note:** the sequential overspend test validates rechecking balance; it is **not** a simultaneous-request stress test. Validate true racing requests against MySQL or PostgreSQL under multiple worker processes before a production deployment.

## 8. File structure

```text
app/
  Exceptions/RedemptionDeniedException.php
  Http/Controllers/Api/{RedemptionController,BalanceController,RedemptionReportController}.php
  Http/Requests/{StoreRedemptionRequest,RedemptionReportRequest}.php
  Models/{User,Profile,Category,CategoryLimit,Product,PointsLedger,Redemption}.php
  Services/{PointsBalanceService,RedemptionService}.php
bootstrap/app.php
routes/api.php
database/migrations/2026_10_28_000001_create_users_table.php
database/migrations/2026_10_28_000002_create_rewards_tables.php
database/seeders/{DatabaseSeeder,RewardsDemoSeeder}.php
tests/Feature/RewardsApiTest.php
```

## 9. Notes for the interviewer

- Business rules are isolated in the service class; HTTP validation is in Form Requests.
- `points_ledger` is the source of truth for balance; no editable cached balance column can drift.
- Category limit uses a **calendar month**, not a rolling 30-day period; default: 2 redemptions/user/category/month.
- Database indexes support per-user/category/month counts and date/category reports.
- Seeding uses batches and inserts a matching spend ledger entry for each of the 10,000 redemption rows.
- **Authentication is intentionally outside the given assignment.** In a real app, do not trust a caller-provided `user_id`; authenticate the user, authorize use, and use the authenticated ID. Likewise, add protected admin routes to earn points and maintain products/limits.
- Historical seed entries are representative fixture data; the seeded 50 restricted users have historical activity, while all **new** redemption attempts by restricted users are rejected. Profile restrictions may have been applied after prior redemptions.
- No points expiration requirement is present in Part B, so this balance includes all unspent earns; expiration from Part A2 is not silently applied.

## 10. Verification status

The PHP source files are syntax checked with `php -l`. Composer dependencies are not included in this archive; framework boot/migrations and Laravel feature tests have not been executed in this environment and must be run after `composer install` on a system with Composer, PHP extensions, and database configured.
