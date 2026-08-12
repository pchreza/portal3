<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class TrackingCode
{
    private function __construct()
    {
    }

    /**
     * Generate a short, non-sensitive support/error correlation code.
     */
    public static function generate(?DateTimeImmutable $now = null): string
    {
        $clock = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        try {
            $random = strtoupper(bin2hex(random_bytes(4)));
        } catch (\Throwable $exception) {
            throw new RuntimeException('Unable to generate a secure tracking code.', 0, $exception);
        }

        return sprintf('DW-%s-%s', $clock->format('YmdHis'), $random);
    }
}
