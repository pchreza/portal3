<?php
/**
 * اجرای صریح migrationها در محیط production.
 * اجرا: php bin/migrate.php
 */
require_once dirname(__DIR__) . '/config.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("فقط از خط فرمان قابل اجراست.\n");
}

if (!$pdo instanceof PDO) {
    fwrite(STDERR, "اتصال دیتابیس برقرار نشد؛ db_config.php و دسترسی DB را بررسی کنید.\n");
    exit(1);
}

try {
    portal_migrations($pdo);
    // Migration ممکن است تنظیمات یا defaultهای جدید اضافه کند؛ cache فایل را
    // باطل کن تا اولین درخواست وب، مقدار تازه را مستقیم از DB بخواند.
    $flushedCache = function_exists('portal_cache_flush') ? portal_cache_flush() : 0;
    $version = (int) $pdo->query('SELECT COALESCE(MAX(version), 0) FROM schema_versions')->fetchColumn();
    fwrite(STDOUT, "Migration موفق بود؛ schema version={$version}; cache flushed={$flushedCache}.\n");
} catch (Throwable $e) {
    error_log('[Portal Migration CLI] ' . $e->getMessage());
    fwrite(STDERR, "Migration انجام نشد؛ جزئیات در لاگ سرور ثبت شد.\n");
    exit(1);
}
