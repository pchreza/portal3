<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SurveyQuestionTypesTest extends TestCase
{
    public function testCatalogContainsDescriptiveSatisfactionType(): void
    {
        $types = survey_question_types();

        self::assertArrayHasKey('satisfaction_5', $types);
        self::assertSame('رضایت‌سنجی: عالی تا بد', $types['satisfaction_5']['label']);
        self::assertSame('satisfaction', $types['satisfaction_5']['kind']);
    }

    public function testSatisfactionOptionsAreOrderedAndLabeled(): void
    {
        self::assertSame(
            ['excellent' => 'عالی', 'good' => 'خوب', 'average' => 'متوسط', 'weak' => 'ضعیف', 'bad' => 'بد'],
            survey_satisfaction_options()
        );
    }

    public function testAnswerValidatorSupportsAllQuestionTypes(): void
    {
        self::assertTrue(survey_answer_is_valid('satisfaction_5', 'excellent'));
        self::assertTrue(survey_answer_is_valid('yes_no', 'بله'));
        self::assertTrue(survey_answer_is_valid('rating_1_10', '10'));
        self::assertTrue(survey_answer_is_valid('star_rating', '5'));
        self::assertFalse(survey_answer_is_valid('satisfaction_5', 'خیلی عالی'));
        self::assertFalse(survey_answer_is_valid('rating_1_10', '11'));
    }

    public function testAnswerLabelMapsSatisfactionKeyToPersianText(): void
    {
        self::assertSame('عالی', survey_answer_label('satisfaction_5', 'excellent'));
        self::assertSame('خوب', survey_answer_label('satisfaction_5', 'good'));
        self::assertSame('10', survey_answer_label('rating_1_10', '10'));
    }
}
