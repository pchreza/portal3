<?php
// settings.php — توابع تنظیمات سیستمی (جدول settings)

/** خواندن یک تنظیم از جدول settings */
function get_setting(string $key, string $default = ''): string
{
    global $pdo;
    if (!$pdo) {
        return $default;
    }
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? (string) $row['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

/** ذخیره یا به‌روزرسانی یک تنظیم */
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
}

/** بررسی فعال بودن یک ماژول (پیش‌فرض: فعال) */
function is_module_enabled(string $module): bool
{
    return get_setting('module_' . $module, '1') === '1';
}
