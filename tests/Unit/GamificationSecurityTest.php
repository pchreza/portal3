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
}
