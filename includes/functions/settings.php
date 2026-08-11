<?php
// settings.php — توابع تنظیمات سیستمی (جدول settings)

/** خواندن یک تنظیم از جدول settings — با کش درون‌درخواستی و کش فایل */
function get_setting(string $key, string $default = ''): string
{
    $all = portal_settings_all();
    return $all[$key] ?? $default;
}

/**
 * بارگذاری همه‌ی تنظیمات یک‌جا:
 * ۱) کش استاتیک درون‌درخواستی (حذف کوئری‌های تکراری)
 * ۲) کش فایل بین درخواست‌ها (کاهش کوئری دیتابیس در هر صفحه‌بار)
 */
function portal_settings_all(): array
{
    global $pdo;
    if (isset($GLOBALS['__portal_settings_cache']) && is_array($GLOBALS['__portal_settings_cache'])) {
        return $GLOBALS['__portal_settings_cache'];
    }
    $cached = portal_cache_get('settings_all', null, true);
    if (is_array($cached)) {
        $GLOBALS['__portal_settings_cache'] = $cached;
        return $cached;
    }
    $all = [];
    $ok = false;
    if ($pdo) {
        try {
            foreach ($pdo->query("SELECT setting_key, setting_value FROM settings") as $row) {
                $all[$row['setting_key']] = (string) $row['setting_value'];
            }
            $ok = true;
        } catch (Throwable $e) {
            $all = [];
        }
    }
    $GLOBALS['__portal_settings_cache'] = $all;
    // فقط وقتی کوئری واقعاً موفق شد کش کن — نه در حالت بدون-دیتابیس/نصب‌نشده
    if ($ok) {
        portal_cache_set('settings_all', $all, null, true);
    }
    return $all;
}

/** ذخیره یا به‌روزرسانی یک تنظیم — کش‌ها هم باطل می‌شوند */
function set_setting(string $key, string $value): void
{
    global $pdo;
    if (!$pdo) {
        return;
    }
    $stmt = $pdo->prepare(
        "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = ?"
    );
    $stmt->execute([$key, $value, $value]);
    unset($GLOBALS['__portal_settings_cache']);
    portal_cache_delete('settings_all');
}

/** بررسی فعال بودن یک ماژول (پیش‌فرض: فعال) */
function is_module_enabled(string $module): bool
{
    return get_setting('module_' . $module, '1') === '1';
}
