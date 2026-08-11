<?php
// cache.php — کش فایل سبک برای کاهش کوئری‌های دیتابیس و پاسخ‌دهی سریع‌تر
// کلیدها به‌صورت فایل‌های سریال‌شده در storage/cache ذخیره می‌شوند (بدون eval).

function portal_cache_dir(): string
{
    $dir = __DIR__ . '/../../storage/cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

/** آیا کش فعال است؟ (خواندن مستقیم از کش درون‌درخواستی — بدون بازگشت) */
function portal_cache_enabled(): bool
{
    $all = $GLOBALS['__portal_settings_cache'] ?? null;
    $v = is_array($all) ? ($all['cache_enabled'] ?? '1') : get_setting('cache_enabled', '1');
    return $v === '1';
}

/** مدت اعتبار پیش‌فرض کش (ثانیه) */
function portal_cache_ttl(): int
{
    $t = (int) get_setting('cache_ttl', '300');
    return max(5, min(86400, $t));
}

function portal_cache_file(string $key): string
{
    return portal_cache_dir() . '/pc_' . md5($key) . '.cache';
}

function portal_cache_get(string $key, ?int $ttl = null, bool $skipEnabled = false): mixed
{
    if (!$skipEnabled && !portal_cache_enabled()) {
        return null;
    }
    $file = portal_cache_file($key);
    if (!is_file($file)) {
        return null;
    }
    $raw = @file_get_contents($file);
    if ($raw === false) {
        return null;
    }
    $data = @unserialize($raw);
    if (!is_array($data) || !isset($data['exp'], $data['val'])) {
        @unlink($file);
        return null;
    }
    if ($data['exp'] < time()) {
        @unlink($file);
        return null;
    }
    return $data['val'];
}

function portal_cache_set(string $key, mixed $value, ?int $ttl = null, bool $skipEnabled = false): bool
{
    if (!$skipEnabled && !portal_cache_enabled()) {
        return false;
    }
    $payload = serialize(['exp' => time() + ($ttl ?? portal_cache_ttl()), 'val' => $value]);
    return @file_put_contents(portal_cache_file($key), $payload, LOCK_EX) !== false;
}

function portal_cache_delete(string $key): bool
{
    @unlink(portal_cache_file($key));
    return true;
}

/** حذف همه‌ی فایل‌های کش — تعداد حذف‌شده برمی‌گردد */
function portal_cache_flush(): int
{
    $n = 0;
    foreach (glob(portal_cache_dir() . '/pc_*.cache') ?: [] as $f) {
        if (@unlink($f)) {
            $n++;
        }
    }
    return $n;
}

function portal_cache_file_count(): int
{
    return count(glob(portal_cache_dir() . '/pc_*.cache') ?: []);
}
