<?php

declare(strict_types=1);

final class PointsCalculator
{
    /** @var list<array{type: string, points: int, date: string}> */
    private array $entries;

    /**
     * @param array<int, array{type: string, points: int, date: string}> $entries
     */
    public function __construct(array $entries)
    {
        foreach ($entries as $entry) {
            if (!is_array($entry)
                || !isset($entry['type'], $entry['points'], $entry['date'])
                || !in_array($entry['type'], ['earn', 'spend'], true)
                || !is_int($entry['points'])
                || $entry['points'] <= 0
                || !is_string($entry['date'])) {
                throw new InvalidArgumentException('Each entry needs a valid type, positive integer points, and a date.');
            }

            self::parseDate($entry['date']);
        }

        // PHP 8.2's stable sort preserves input order for entries on the same day.
        usort($entries, static fn (array $a, array $b): int => strcmp($a['date'], $b['date']));
        $this->entries = array_values($entries);
    }

    /** Total earned points minus spent points (including unspent expired points). */
    public function balance(): int
    {
        $balance = 0;
        foreach ($this->remainingEarnBatches() as $batch) {
            $balance += $batch['points'];
        }
        return $balance;
    }

    /** Unspent points earned strictly earlier than today minus 12 months. */
    public function expiredPoints(string $today): int
    {
        $cutoff = self::parseDate($today)->modify('-12 months');
        $expired = 0;

        foreach ($this->remainingEarnBatches($today) as $batch) {
            if (self::parseDate($batch['date']) < $cutoff) {
                $expired += $batch['points'];
            }
        }

        return $expired;
    }

    /**
     * @return list<array{date: string, points: int}>
     */
    private function remainingEarnBatches(?string $asOf = null): array
    {
        $batches = [];
        $oldest = 0;

        foreach ($this->entries as $entry) {
            if ($asOf !== null && $entry['date'] > $asOf) {
                break;
            }

            if ($entry['type'] === 'earn') {
                $batches[] = ['date' => $entry['date'], 'points' => $entry['points']];
                continue;
            }

            // Spend points from the oldest earn batch first (FIFO).
            $toSpend = $entry['points'];
            while ($toSpend > 0 && $oldest < count($batches)) {
                $used = min($toSpend, $batches[$oldest]['points']);
                $batches[$oldest]['points'] -= $used;
                $toSpend -= $used;

                if ($batches[$oldest]['points'] === 0) {
                    $oldest++;
                }
            }

            if ($toSpend > 0) {
                throw new InvalidArgumentException(
                    'Not enough earned points to cover spending on ' . $entry['date']
                );
            }
        }

        return $batches;
    }

    private static function parseDate(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Invalid date: expected YYYY-MM-DD.');
        }
        return $date;
    }
}
