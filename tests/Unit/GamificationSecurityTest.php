<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class GamificationSecurityTest extends TestCase
{
    public function testEventCatalogContainsOnlyKnownRewardableEvents(): void
    {
        $catalog = gamification_event_catalog();
        self::assertArrayHasKey('profile_completed', $catalog);
        self::assertArrayHasKey('survey_submitted', $catalog);
        self::assertArrayHasKey('bonus_code_redeemed', $catalog);
        self::assertArrayNotHasKey('page_view', $catalog);
    }

    public function testBonusCodeValidationRejectsUnsafeOrShortValues(): void
    {
        self::assertSame('EXPO2026_TEST', gamification_normalize_code(' expo2026_test '));
        self::assertTrue(gamification_valid_code('EXPO2026_TEST'));
        self::assertFalse(gamification_valid_code('ABC'));
        self::assertFalse(gamification_valid_code('EXPO 2026'));
        self::assertFalse(gamification_valid_code('EXPO<script>'));
    }

    public function testStoreUrlMustBeHttpsOutsideDevelopment(): void
    {
        self::assertTrue(gamification_validate_https_url('https://shop.example.test/discount'));
        self::assertFalse(gamification_validate_https_url('javascript:alert(1)'));
        self::assertFalse(gamification_validate_https_url('https://'));
    }

    public function testContextOfferFailsClosedWhenGamificationIsDisabled(): void
    {
        global $pdo;
        $previousPdo = $pdo ?? null;
        $previousSettings = $GLOBALS['__portal_settings_cache'] ?? null;
        $pdo = null;
        $GLOBALS['__portal_settings_cache'] = ['module_gamification' => '0'];

        try {
            self::assertNull(gamification_context_offer(42, 'survey_submitted'));
            self::assertFalse(gamification_customer_has_event(42, 'survey_submitted'));
            self::assertSame('disabled', gamification_customer_event_status(42, 'survey_submitted')['state']);
        } finally {
            $pdo = $previousPdo;
            if ($previousSettings === null) {
                unset($GLOBALS['__portal_settings_cache']);
            } else {
                $GLOBALS['__portal_settings_cache'] = $previousSettings;
            }
        }
    }

    public function testAwardFeedbackStoresAOneTimeToastPayload(): void
    {
        $previousSession = $_SESSION ?? [];
        $_SESSION = [];
        try {
            gamification_award_feedback(7, 'survey_submitted', 50);
            self::assertSame('survey_submitted', $_SESSION['gamification_award_flash']['event_key']);
            self::assertSame(50, $_SESSION['gamification_award_flash']['points']);
            self::assertStringContainsString('50', $_SESSION['gamification_award_flash']['message']);
        } finally {
            $_SESSION = $previousSession;
        }
    }

    public function testPersistentAwardNotificationIsCentralizedAndNotDuplicatedByFeedback(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/includes/functions/gamification.php');
        self::assertIsString($source);
        $awardStart = strpos($source, 'function gamification_award_points(');
        $profileStart = strpos($source, 'function gamification_profile_is_complete(');
        $feedbackStart = strpos($source, 'function gamification_award_feedback(');
        $bonusStart = strpos($source, 'function gamification_bonus_feedback(');
        self::assertIsInt($awardStart);
        self::assertIsInt($profileStart);
        self::assertIsInt($feedbackStart);
        self::assertIsInt($bonusStart);

        $awardBody = substr($source, $awardStart, $profileStart - $awardStart);
        $feedbackBody = substr($source, $feedbackStart, $bonusStart - $feedbackStart);
        self::assertStringContainsString("send_notification('امتیاز جدید دریافت کردید'", $awardBody);
        self::assertStringNotContainsString('send_notification(', $feedbackBody);
    }
}
