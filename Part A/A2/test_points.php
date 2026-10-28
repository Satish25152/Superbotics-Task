<?php

declare(strict_types=1);

require_once __DIR__ . '/PointsCalculator.php';

$entries = [
    ['type' => 'earn',  'points' => 100, 'date' => '2025-05-01'],
    ['type' => 'earn',  'points' => 60,  'date' => '2025-10-28'],
    ['type' => 'spend', 'points' => 70,  'date' => '2025-11-01'],
    ['type' => 'earn',  'points' => 40,  'date' => '2026-01-10'],
    ['type' => 'spend', 'points' => 20,  'date' => '2026-02-20'],
    ['type' => 'earn',  'points' => 25,  'date' => '2026-06-01'],
];

$calculator = new PointsCalculator($entries);
$today = '2026-10-28';
$balance = $calculator->balance();
$expired = $calculator->expiredPoints($today);

printf("Balance: %d points\n", $balance);
printf("Expired as of %s: %d points\n", $today, $expired);
printf("Available after expiry: %d points\n", $balance - $expired);

if ($balance !== 135 || $expired !== 10) {
    throw new RuntimeException('Test failed: unexpected balance or expired points.');
}

echo "Test passed.\n";
