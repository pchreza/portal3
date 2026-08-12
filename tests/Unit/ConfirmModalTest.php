<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ConfirmModalTest extends TestCase
{
    public function testConfirmScriptSupportsOperationSpecificLabelsAndNativeValidation(): void
    {
        $script = portal_confirm_script();

        self::assertStringContainsString('data-confirm-ok-label', $script);
        self::assertStringContainsString('data-confirm-title', $script);
        self::assertStringContainsString('data-confirm-tone', $script);
        self::assertStringContainsString('requestSubmit', $script);
        self::assertStringContainsString('dataset.confirmed', $script);
    }

    public function testConfirmModalKeepsDeleteDefaultsForLegacyForms(): void
    {
        $modal = portal_confirm_modal();

        self::assertStringContainsString('تأیید حذف', $modal);
        self::assertStringContainsString('>حذف</button>', $modal);
        self::assertStringContainsString('>انصراف</button>', $modal);
    }
}
