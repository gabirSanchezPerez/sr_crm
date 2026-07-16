<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;

final class GoalEditPolicy
{
    public function editable(int $year, int $month, ?DateTimeImmutable $now = null): bool
    {
        if ($month < 1 || $month > 12) {
            return false;
        }
        $now ??= new DateTimeImmutable('now', new DateTimeZone(config('App')->appTimezone));
        $target = $year * 100 + $month;
        $current = (int) $now->format('Y') * 100 + (int) $now->format('n');
        return $target > $current || ($target === $current && (int) $now->format('j') <= 10);
    }
}
