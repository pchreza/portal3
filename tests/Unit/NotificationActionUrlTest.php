<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class NotificationActionUrlTest extends TestCase
{
    public function testInternalCustomerActionUrlsAreAccepted(): void
    {
        self::assertSame('tickets.php?action=new', notification_internal_action_url('tickets.php?action=new'));
        self::assertSame('gamification.php', notification_internal_action_url(' gamification.php '));
        self::assertSame('surveys.php?take=42', notification_internal_action_url('surveys.php?take=42'));
    }

    public function testExternalAndMalformedActionUrlsAreRejected(): void
    {
        self::assertNull(notification_internal_action_url('https://example.test/steal'));
        self::assertNull(notification_internal_action_url('//example.test/steal'));
        self::assertNull(notification_internal_action_url('../admin/index.php'));
        self::assertNull(notification_internal_action_url('tickets.php#unsafe'));
        self::assertNull(notification_internal_action_url('tickets.php?next=<script>'));
    }

    public function testGamificationEventsMapToCustomerActions(): void
    {
        self::assertSame('profile.php', gamification_event_action_url('profile_completed'));
        self::assertSame('surveys.php', gamification_event_action_url('survey_submitted'));
        self::assertSame('tickets.php', gamification_event_action_url('ticket_customer_reply'));
        self::assertSame('gamification.php', gamification_event_action_url('bonus_code_redeemed'));
    }
}
