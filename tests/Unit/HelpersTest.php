<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST = [];
    }

    public function testMoneyNormalizationAcceptsPersianDigitsAndSeparators(): void
    {
        self::assertSame('123450.00', normalize_money_input('۱۲۳٬۴۵۰'));
        self::assertSame('12.50', normalize_money_input('12.5'));
        self::assertSame('', normalize_money_input(''));
    }

    public function testMoneyNormalizationRejectsMalformedValues(): void
    {
        self::assertNull(normalize_money_input('12,3,4'));
        self::assertNull(normalize_money_input('-10'));
        self::assertNull(normalize_money_input('12.345'));
    }

    public function testJalaliDateIsNormalizedToDatabaseDate(): void
    {
        self::assertSame('2026-09-22', portal_date_to_db('1405/06/31'));
        self::assertSame('2026-09-22', portal_date_to_db('2026-09-22'));
        self::assertSame('', portal_date_to_db(''));
    }

    public function testEscapingIsContextSafeForHtmlText(): void
    {
        self::assertSame('&lt;script&gt;&amp;&quot;', e('<script>&"'));
    }

    public function testCsrfTokenIsSessionBoundAndRejectsInvalidToken(): void
    {
        $token = csrf_token();
        self::assertSame(64, strlen($token));
        self::assertTrue(hash_equals($token, csrf_token()));

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['csrf_token' => 'invalid'];
        self::assertFalse(verify_csrf());

        $_POST = ['csrf_token' => $token];
        self::assertTrue(verify_csrf());
    }
}
