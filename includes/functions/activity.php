<?php
// activity.php — ثبت فعالیت‌های کاربران (Activity Log)

/**
 * ثبت یک فعالیت در جدول activity_logs
 * (شکست در ثبت لاگ باعث توقف برنامه نمی‌شود)
 */
function log_activity(int $user_id, string $action): void
{
    global $pdo;
    if (!$pdo) {
        return;
    }

    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $action, $ip]);
    } catch (Exception $e) {
        // Silent fail for logging
    }
}

/**
 * پاکسازی داده‌های قدیمی (بایگانی خودکار) — برای جلوگیری از رشد نامحدود دیتابیس
 * توسط کرون‌جاب روزانه فراخوانی می‌شود.
 * @return array خلاصه حذف‌ها
 */
function cleanup_old_data(): array
{
    global $pdo;
    if (!$pdo) {
        return [];
    }
    $summary = [];
    $jobs = [
        'activity_logs'   => ['DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)', 'activity_logs'],
        'otp_codes'       => ['DELETE FROM otp_codes WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)', 'otp_codes'],
        'sms_logs'        => ['DELETE FROM sms_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)', 'sms_logs'],
        'login_attempts'  => ['DELETE FROM login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)', 'login_attempts'],
    ];
    foreach ($jobs as $key => [$sql, $table]) {
        try {
            $deleted = $pdo->exec($sql);
            $summary[$key] = (int) $deleted;
        } catch (Throwable $e) {
            $summary[$key] = -1; // خطا
        }
    }
    return $summary;
}
