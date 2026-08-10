<?php
// auth.php — توابع مرتبط با ورود: قفل تلاش‌های ناموفق و ثبت تلاش‌ها

/**
 * بررسی قفل شدن ورود به دلیل تلاش‌های ناموفق اخیر
 * - ترکیب دقیق (نام کاربری + IP): ۵ تلاش ناموفق در ۱۵ دقیقه → قفل
 *   (مهاجم نمی‌تواند با ۵ تلاش از IP دیگر، قربانی را قفل کند — رفع DoS)
 * - فقط IP: آستانه بالاتر (۲۰ تلاش) برای جلوگیری از brute-force چندکاربره
 */
function login_is_locked(string $username, string $ip): bool
{
    global $pdo;
    // قفل ترکیب دقیق کاربر+IP
    $q = $pdo->prepare(
        "SELECT COUNT(*) FROM login_attempts
         WHERE success = 0
           AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
           AND username = ? AND ip_address = ?"
    );
    $q->execute([$username, $ip]);
    if ((int) $q->fetchColumn() >= 5) {
        return true;
    }
    // قفل IP جداگانه با آستانه بالاتر
    $q2 = $pdo->prepare(
        "SELECT COUNT(*) FROM login_attempts
         WHERE success = 0
           AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
           AND ip_address = ?"
    );
    $q2->execute([$ip]);
    return (int) $q2->fetchColumn() >= 20;
}

/**
 * ثبت یک تلاش ورود (موفق یا ناموفق) در جدول login_attempts
 * در صورت موفقیت، رکوردهای قبلی همان کاربر/IP پاک می‌شوند.
 */
function record_login_attempt(string $username, string $ip, bool $success): void
{
    global $pdo;
    $q = $pdo->prepare("INSERT INTO login_attempts (username, ip_address, success) VALUES (?, ?, ?)");
    $q->execute([$username, $ip, $success ? 1 : 0]);

    if ($success) {
        $q = $pdo->prepare("DELETE FROM login_attempts WHERE username = ? AND ip_address = ?");
        $q->execute([$username, $ip]);
    }
}
