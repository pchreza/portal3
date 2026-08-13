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
        self::assertTrue(survey_answer_is_valid('text_free', 'پاسخ تشریحی معتبر'));
        self::assertTrue(survey_answer_is_valid('multiple_choice', 'خوب', ['عالی', 'خوب', 'ضعیف']));
        self::assertFalse(survey_answer_is_valid('satisfaction_5', 'خیلی عالی'));
        self::assertFalse(survey_answer_is_valid('rating_1_10', '11'));
        self::assertFalse(survey_answer_is_valid('text_free', ''));
        self::assertFalse(survey_answer_is_valid('multiple_choice', 'نامعتبر', ['عالی', 'خوب']));
    }

    public function testMultipleChoiceCatalogAndOptionParserAreSafe(): void
    {
        $types = survey_question_types();
        self::assertSame('پاسخ تشریحی', $types['text_free']['label']);
        self::assertSame('چندگزینه‌ای', $types['multiple_choice']['label']);
        self::assertSame(['عالی', 'خوب', 'ضعیف'], survey_multiple_choice_options_from_text("عالی\nخوب\nخوب\n\nضعیف"));
        self::assertSame(['عالی', 'خوب'], survey_multiple_choice_options('["عالی","خوب","عالی"]'));
        self::assertSame('["عالی","خوب"]', survey_multiple_choice_options_json(['عالی', 'خوب']));
    }

    public function testAnswerLabelMapsSatisfactionKeyToPersianText(): void
    {
        self::assertSame('عالی', survey_answer_label('satisfaction_5', 'excellent'));
        self::assertSame('خوب', survey_answer_label('satisfaction_5', 'good'));
        self::assertSame('10', survey_answer_label('rating_1_10', '10'));
    }
}
