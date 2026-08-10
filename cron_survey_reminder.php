<?php
// cron_survey_reminder.php — ارسال خودکار یادآوری نظرسنجی ناقص به مشتریان
//
// رفتار (بر اساس تنظیمات بخش «یادآوری نظرسنجی» در پنل ادمین):
//   - اولین یادآوری: چند روز بعد از فعال‌شدن نظرسنجی (survey_reminder_days)
//   - یادآوری‌های بعدی: هر چند روز یک‌بار تا حداکثر تعداد مجاز
//   - پیامک شامل متغیر survey_link است: لینک عمومی تکمیل نظرسنجی (بدون نیاز به ورود)
//
// اجرای دستی:  php cron_survey_reminder.php
// کرون‌جاب پیشنهادی:  0 9 * * *  php /path/to/cron_survey_reminder.php
require_once __DIR__ . '/config.php';

if (PHP_SAPI !== 'cli') {
    die('فقط از خط فرمان قابل اجراست.');
}

$count = sms_send_due_survey_reminders();
echo "یادآوری نظرسنجی به {$count} مشتری ارسال شد." . PHP_EOL;

// پاکسازی خودکار داده‌های قدیمی (اجرا روزانه با همین کرون‌جاب)
$cleanup = cleanup_old_data();
echo 'پاکسازی خودکار: activity_logs=' . ($cleanup['activity_logs'] ?? '?')
    . ' | otp_codes=' . ($cleanup['otp_codes'] ?? '?')
    . ' | sms_logs=' . ($cleanup['sms_logs'] ?? '?')
    . ' | login_attempts=' . ($cleanup['login_attempts'] ?? '?') . PHP_EOL;
