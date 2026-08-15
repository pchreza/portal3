<?php
// config.php — بوت‌استرپ برنامه: سشن امن، اتصال دیتابیس (PDO) و بارگذاری توابع مشترک
// توابع کمکی در پوشه includes/functions/ دسته‌بندی شده‌اند.

// تشخیص HTTPS: مستقیم یا پشت پراکسی (nginx/Cloudflare) — هدر X-Forwarded-Proto
$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443);
$trust_proxy = filter_var((string) (getenv('PORTAL_TRUST_PROXY') ?: '0'), FILTER_VALIDATE_BOOLEAN);
$fwd_proto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
if ($trust_proxy && $fwd_proto === 'https') {
    $is_https = true;
}

// --- هدرهای امنیتی پایه ---
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
if ($is_https) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// --- راه‌اندازی سشن با تنظیمات امن کوکی ---
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1'); // فقط سشن‌های ساخته‌شده توسط سرور پذیرفته شوند
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'secure' => $is_https,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// --- مدیریت timeout سشن (idle) ---
// سشن‌های غیرفعال پس از ۳۰ دقیقه منقضی می‌شوند.
$session_idle_timeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - (int) $_SESSION['last_activity']) > $session_idle_timeout) {
    $_SESSION = [];
    session_destroy();
    session_start();
    session_regenerate_id(true);
}
$_SESSION['last_activity'] = time();

// --- محیط اجرا و حالت توسعه ---
// حالت امن پیش‌فرض production است؛ development فقط با environment صریح فعال می‌شود.
$portal_env = strtolower(trim((string) (getenv('PORTAL_ENV') ?: 'production')));
if (!defined('PORTAL_ENV')) {
    define('PORTAL_ENV', $portal_env);
}
if (!defined('PORTAL_DEV_MODE')) {
    $dev_env = getenv('PORTAL_DEV_MODE');
    $dev_enabled = $dev_env !== false
        ? filter_var($dev_env, FILTER_VALIDATE_BOOLEAN)
        : in_array($portal_env, ['local', 'development', 'test'], true);
    define('PORTAL_DEV_MODE', $dev_enabled);
}

// --- کنترل نمایش خطاها بر اساس محیط اجرا ---
// در production خطاها نمایش داده نمی‌شوند تا اطلاعات حساس سرور افشا نشود.
if (PORTAL_DEV_MODE) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline): bool {
        if (!(error_reporting() & $errno)) {
            return false;
        }
        error_log(sprintf('[Portal PHP %d] %s in %s on line %d', $errno, $errstr, $errfile, $errline));
        return true;
    });
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
require_once __DIR__ . '/includes/functions/backup.php';
require_once __DIR__ . '/includes/functions/gamification.php';

// --- Content Security Policy ---
$script_nonce = portal_csp_nonce();
header("Content-Security-Policy: default-src 'self'; base-uri 'self'; frame-ancestors 'self'; form-action 'self'; object-src 'none'; img-src 'self' data: blob:; font-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'nonce-{$script_nonce}'; script-src-attr 'none'; connect-src 'self'; frame-src 'none'");

// --- حفاظت مرکزی CSRF برای تمام mutationهای HTTP ---
// همهٔ فرم‌های داخلی state-changing باید csrf_input() داشته باشند. Installer
// تنها استثناست، چون قبل از ساخته‌شدن session/DB اجرا می‌شود.
if (!$is_install_page && (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')) {
    require_valid_csrf();
}

// --- اجرای migration ---
// در production migration باید با bin/migrate.php و خارج از request اجرا شود.
// auto-migrate فقط برای local/test یا با PORTAL_AUTO_MIGRATE=true مجاز است.
require_once __DIR__ . '/migrations.php';
$auto_migrate_env = getenv('PORTAL_AUTO_MIGRATE');
$auto_migrate = $auto_migrate_env !== false
    ? filter_var($auto_migrate_env, FILTER_VALIDATE_BOOLEAN)
    : in_array(PORTAL_ENV, ['local', 'development', 'test'], true);
if ($pdo && $auto_migrate) {
    if (portal_auto_migrate($pdo)) {
        portal_cache_flush(); // migration اجرا شد — کش‌ها را باطل کن
    }
}
if ($pdo && !$auto_migrate && !$is_install_page && PHP_SAPI !== 'cli'
    && portal_schema_version($pdo) < PORTAL_SCHEMA_VERSION) {
    http_response_code(503);
    header('Retry-After: 300');
    exit('<div style="font-family:Tahoma;direction:rtl;text-align:center;padding:50px"><h2>سامانه در حال ارتقاست</h2><p>مدیر سامانه باید migration نسخهٔ جدید را اجرا کند.</p></div>');
}

// --- پردازش سراسری فرم گزارش خطا (دکمه شناور) ---
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'report_error') {
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
