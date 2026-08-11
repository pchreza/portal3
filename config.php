<?php
// config.php — بوت‌استرپ برنامه: سشن امن، اتصال دیتابیس (PDO) و بارگذاری توابع مشترک
// توابع کمکی در پوشه includes/functions/ دسته‌بندی شده‌اند.

// --- هدرهای امنیتی پایه ---
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-XSS-Protection: 1; mode=block');

// --- راه‌اندازی سشن با تنظیمات امن کوکی ---
if (session_status() === PHP_SESSION_NONE) {
    // تشخیص HTTPS: مستقیم یا پشت پراکسی (nginx/Cloudflare) — هدر X-Forwarded-Proto
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    $fwd_proto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    if ($fwd_proto === 'https') {
        $is_https = true;
    }
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $is_https,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1'); // فقط سشن‌های ساخته‌شده توسط سرور پذیرفته شوند
    session_start();
}

// --- حالت توسعه: در محیط عملیاتی مقدار را false کنید ---
// (در حالت تست، کد تایید پیامک در پاسخ/سشن می‌آید؛ در حالت عادی فقط در لاگ سرور)
if (!defined('PORTAL_DEV_MODE')) {
    define('PORTAL_DEV_MODE', true);
}

// --- اتصال به پایگاه داده ---
$db_config_file = __DIR__ . '/db_config.php';
$is_install_page = basename($_SERVER['PHP_SELF'] ?? '') === 'install.php';

$pdo = null;

if (file_exists($db_config_file)) {
    require_once $db_config_file;
    try {
        $pdo = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        // اسکیمای دیتابیس توسط install.php/schema.sql نصب و توسط migrations.php ارتقا می‌یابد.
    } catch (PDOException $e) {
        error_log('[Portal DB] ' . $e->getMessage());
        if (!$is_install_page) {
            die("<div style='font-family:Tahoma;direction:rtl;text-align:center;padding:50px'><h2>خطا در اتصال به پایگاه داده</h2><p>ارتباط با پایگاه داده برقرار نشد. تنظیمات اتصال را بررسی کنید.</p><a href='install.php'>اجرای مجدد نصب</a></div>");
        }
    }
} elseif (!$is_install_page) {
    // هنوز نصب نشده است؛ هدایت به ویزارد نصب
    header('Location: install.php');
    exit;
}

// --- بارگذاری توابع مشترک ---
require_once __DIR__ . '/includes/functions/helpers.php';
require_once __DIR__ . '/includes/functions/auth.php';
require_once __DIR__ . '/includes/functions/cache.php';
require_once __DIR__ . '/includes/functions/settings.php';
require_once __DIR__ . '/includes/functions/custom_fields.php';
require_once __DIR__ . '/includes/functions/surveys.php';
require_once __DIR__ . '/includes/functions/activity.php';
require_once __DIR__ . '/includes/functions/notifications.php';
require_once __DIR__ . '/includes/functions/sms_triggers.php';
require_once __DIR__ . '/includes/functions/excel.php';

// --- اجرای خودکار مهاجرت‌های نسخه‌بندی‌شده ---
// با آپدیت کد، نصب‌های موجود در اولین درخواست به‌صورت خودکار ارتقا می‌یابند.
require_once __DIR__ . '/migrations.php';
if ($pdo) {
    if (portal_auto_migrate($pdo)) {
        portal_cache_flush(); // مهاجرت اجرا شد — کش‌ها را باطل کن
    }
}

// --- پردازش سراسری فرم گزارش خطا (دکمه شناور) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'report_error') {
    require_valid_csrf();
    $ok = error_report_module_enabled() && create_error_report($_POST);
    $page = basename($_SERVER['PHP_SELF'] ?? '');
    $qs = $_GET ? ('?' . http_build_query($_GET)) : '';
    // تنظیم فلگ برای نمایش پیام در صفحه
    if ($ok) {
        $_SESSION['error_report_flash'] = 'گزارش خطای شما با موفقیت ثبت شد. متشکریم از کمک شما.';
    } else {
        $_SESSION['error_report_flash'] = 'ثبت گزارش ناموفق بود. لطفاً دوباره تلاش کنید.';
    }
    header('Location: ' . $page . $qs);
    exit;
}
