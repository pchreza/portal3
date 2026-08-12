<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\SystemClock;
use App\Support\TrackingCode;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class SupportUtilitiesTest extends TestCase
{
    public function testTrackingCodeUsesUtcTimestampAndSecureSuffix(): void
    {
        $now = new DateTimeImmutable('2026-08-12 00:00:00', new DateTimeZone('UTC'));

        $code = TrackingCode::generate($now);

        self::assertMatchesRegularExpression('/^DW-20260812000000-[A-F0-9]{8}$/', $code);
    }

    public function testSystemClockReturnsConfiguredTimezone(): void
    {
        $clock = new SystemClock(new DateTimeZone('Asia/Tehran'));

        self::assertSame('Asia/Tehran', $clock->now()->getTimezone()->getName());
    }
}
