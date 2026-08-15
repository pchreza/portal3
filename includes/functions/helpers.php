<?php
// helpers.php — توابع کمکی سراسری: escape، ریدایرکت، CSRF و رندر قالب‌ها (Layout)

/** خروجی امن مقدار برای نمایش در HTML */
if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

/** ریدایرکت به آدرس دلخواه و توقف اجرای اسکریپت */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

// ---------------------------------------------------------------------------
// آیکن‌های SVG (سبک Lucide — stroke یکسان ۲×۲۴) — جایگزین ایموجی کاربردی
// ---------------------------------------------------------------------------

/** آیکن SVG خطی با استفاده از پترن‌های Lucide (برای aria-hidden وقتی تزئینی است) */
function icon(string $name, string $class = 'ic', bool $decorative = true): string
{
    $stroke = $decorative ? ' aria-hidden="true"' : '';
    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
        'users'     => '<path d="M17 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user'      => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'folder'    => '<path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.7-.9L9.4 3.9A2 2 0 0 0 7.7 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/>',
        'box'       => '<path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="M3.3 7 12 12l8.7-5"/><path d="M12 22V12"/>',
        'card'      => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
        'ticket'    => '<path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/>',
        'layers'    => '<path d="m12 2 8.5 4.5-8.5 4.5L3.5 6.5Z"/><path d="m3.5 12 8.5 4.5 8.5-4.5"/><path d="m3.5 17 8.5 4.5 8.5-4.5"/>',
        'star'      => '<path d="M11.5 2.5 14 8l6 .5-4.5 3.9 1.3 5.9L11.5 15l-5.3 3.3 1.3-5.9L3 8.5 9 8Z"/>',
        'wrench'    => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
        'bell'      => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
        'file'      => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/>',
        'settings'  => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
        'users2'    => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'logout'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
        'plus'      => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'menu'      => '<path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/>',
        'edit'      => '<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>',
        'trash'     => '<path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/>',
        'back'      => '<path d="M19 12H5"/><path d="m12 19-7-7 7-7"/>',
        'calendar'  => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/>',
        'chevron-d' => '<path d="m6 9 6 6 6-6"/>',
        'x'         => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'check'     => '<path d="M20 6 9 17l-5-5"/>',
        'alert'     => '<path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/>',
        'info'      => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
        'home'      => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>',
        'sun'       => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.9 4.9 1.4 1.4"/><path d="m17.7 17.7 1.4 1.4"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.3 17.7-1.4 1.4"/><path d="m19.1 4.9-1.4 1.4"/>',
        'moon'      => '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
        'send'      => '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>',
        'search'    => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'trending'  => '<path d="m22 7-8.5 8.5-5-5L2 17"/><path d="M16 7h6v6"/>',
        'refresh'   => '<path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M3 21v-5h5"/>',
        'mail'      => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
        'phone'     => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>',
        'lock'      => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'message'   => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'clipboard' => '<rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>',
        'file-plus' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M12 18v-6"/><path d="M9 15h6"/>',
        'link'      => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        'palette'   => '<circle cx="13.5" cy="6.5" r=".5"/><circle cx="17.5" cy="10.5" r=".5"/><circle cx="8.5" cy="7.5" r=".5"/><circle cx="6.5" cy="12.5" r=".5"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.9 0 1.5-.7 1.5-1.5 0-.4-.2-.8-.4-1-.3-.4-.5-.7-.5-1.1 0-1.4 1.2-2.4 2.7-2.4H17c3 0 5-2.5 5-5.5C22 5.5 17.5 2 12 2z"/>',
        'globe'     => '<circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
        'eye'       => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
    ];
    $body = $paths[$name] ?? $paths['info'];
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"' . $stroke . '>' . $body . '</svg>';
}

// ---------------------------------------------------------------------------
// وضعیت محصول
// ---------------------------------------------------------------------------

/** برچسب‌های فارسی وضعیت محصول */
function product_status_list(): array
{
    return [
        'purchased' => 'محصول خریداری شده',
        'shipping'  => 'در حال ارسال',
        'delivered' => 'به دست مشتری رسیده',
        'active'    => 'فعال / در حال استفاده',
        'expired'   => 'منقضی شده',
    ];
}

/** نمایش برچسب وضعیت محصول */
function product_status_label(?string $status): string
{
    $list = product_status_list();
    return $list[$status] ?? 'نامشخص';
}

/** نمایش بج رنگی وضعیت محصول */
function product_status_badge(?string $status): string
{
    $colors = [
        'purchased' => 'bg-sky-50 text-sky-700',
        'shipping'  => 'bg-amber-50 text-amber-700',
        'delivered' => 'bg-emerald-50 text-emerald-700',
        'active'    => 'bg-indigo-50 text-indigo-700',
        'expired'   => 'bg-slate-100 text-slate-600',
    ];
    $color = $colors[$status] ?? 'bg-slate-100 text-slate-600';
    return '<span class="' . $color . ' text-xs px-2.5 py-1 rounded-full font-medium">' . e(product_status_label($status)) . '</span>';
}

// ---------------------------------------------------------------------------
// برچسب‌های فارسی وضعیت پروژه / تیکت / فاکتور (برای پیامک و نمایش)
// ---------------------------------------------------------------------------

/**
 * اعتبارسنجی امن فایل تصویر آپلودی:
 * - پسوند در لیست مجاز (بدون svg — ریسک XSS)
 * - نوع MIME واقعی با finfo (نه فقط پسوند)
 * - سایز حداکثر
 * @return true|string true اگر معتبر است، وگرنه پیام خطا
 */
function validate_upload_image(array $file, int $maxBytes = 4 * 1024 * 1024)
{
    $allowed = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif'];
    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!isset($allowed[$ext])) {
        return 'فرمت فایل مجاز نیست (فقط png/jpg/jpeg/webp/gif).';
    }
    if (($file['size'] ?? 0) > $maxBytes) {
        return 'حجم فایل بیش از حد مجاز است.';
    }
    if (($file['size'] ?? 0) <= 0) {
        return 'فایل خالی است.';
    }
    // بررسی MIME واقعی محتوا
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, array_values($allowed), true)) {
            return 'محتوای فایل یک تصویر معتبر نیست (MIME: ' . $mime . ').';
        }
    }
    // بررسی صحت تصویر (برای فرمت‌های raster)
    $imgInfo = @getimagesize($file['tmp_name']);
    if ($imgInfo === false && $ext !== 'svg') {
        return 'فایل تصویر خراب یا نامعتبر است.';
    }
    return true;
}

/** برچسب فارسی وضعیت پروژه */
function project_status_label(?string $status): string
{
    return [
        'completed'   => 'تکمیل شده',
        'in_progress' => 'در حال انجام',
        'pending'     => 'در انتظار شروع',
    ][$status] ?? 'نامشخص';
}

/** برچسب فارسی وضعیت تیکت */
function ticket_status_label(?string $status): string
{
    return [
        'open'     => 'باز',
        'answered' => 'پاسخ داده شده',
        'closed'   => 'بسته شده',
    ][$status] ?? 'نامشخص';
}

/** برچسب فارسی اولویت تیکت */
function ticket_priority_label(?string $priority): string
{
    return [
        'low'    => 'کم',
        'medium' => 'متوسط',
        'high'   => 'زیاد',
    ][$priority] ?? 'نامشخص';
}

/** برچسب فارسی وضعیت فاکتور */

// ---------------------------------------------------------------------------
// صفحه‌بندی
// ---------------------------------------------------------------------------

/** محاسبه پارامترهای صفحه‌بندی از $_GET */
function pagination_info(int $total, int $perPage = 20): array
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $totalPages);
    return [
        'page'        => $page,
        'per_page'    => $perPage,
        'total'       => $total,
        'total_pages' => $totalPages,
        'offset'      => ($page - 1) * $perPage,
    ];
}

/** رندر نوار صفحه‌بندی (لینک‌های قبلی/بعدی + شماره صفحات) */
function render_pagination(array $pi, string $baseUrl = ''): string
{
    if ($pi['total_pages'] <= 1) {
        return '';
    }
    if ($baseUrl === '') {
        $baseUrl = basename($_SERVER['PHP_SELF'] ?? '');
    }
    // حفظ فیلترهای موجود در آدرس (مثلاً ?status=...) هنگام تغییر صفحه
    $qs = $_GET;
    unset($qs['page']);
    if ($qs) {
        $baseUrl .= (str_contains($baseUrl, '?') ? '&' : '?') . http_build_query($qs);
    }
    $sep = str_contains($baseUrl, '?') ? '&' : '?';
    $html = '<nav class="flex items-center justify-center gap-1.5 pt-4 flex-wrap" aria-label="صفحه‌بندی">';

    // قبلی
    $prev = $pi['page'] > 1 ? $pi['page'] - 1 : 0;
    $html .= $prev
        ? '<a href="' . e($baseUrl . $sep . 'page=' . $prev) . '" class="px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 text-sm hover:bg-slate-50 transition" aria-label="صفحه قبلی">قبلی</a>'
        : '<span class="px-3 py-1.5 rounded-lg border border-slate-100 text-slate-300 text-sm cursor-not-allowed">قبلی</span>';

    // شماره صفحات (حداکثر ۷)
    $start = max(1, $pi['page'] - 3);
    $end   = min($pi['total_pages'], $pi['page'] + 3);
    for ($i = $start; $i <= $end; $i++) {
        if ($i === $pi['page']) {
            $html .= '<span class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-sm font-medium" aria-current="page" aria-label="صفحه ' . $i . '">' . $i . '</span>';
        } else {
            $html .= '<a href="' . e($baseUrl . $sep . 'page=' . $i) . '" class="px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 text-sm hover:bg-slate-50 transition">' . $i . '</a>';
        }
    }

    // بعدی
    $next = $pi['page'] < $pi['total_pages'] ? $pi['page'] + 1 : 0;
    $html .= $next
        ? '<a href="' . e($baseUrl . $sep . 'page=' . $next) . '" class="px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 text-sm hover:bg-slate-50 transition" aria-label="صفحه بعدی">بعدی</a>'
        : '<span class="px-3 py-1.5 rounded-lg border border-slate-100 text-slate-300 text-sm cursor-not-allowed">بعدی</span>';

    $html .= '</nav>';
    return $html;
}
function invoice_status_label(?string $status): string
{
    return [
        'unpaid'    => 'پرداخت نشده',
        'paid'      => 'پرداخت شده',
        'cancelled' => 'لغو شده',
    ][$status] ?? 'نامشخص';
}

// ---------------------------------------------------------------------------
// عکس محصولات / پروژه‌ها + تصویر پیش‌فرض (fallback)
// ---------------------------------------------------------------------------

/**
 * آدرس تصویر یک محصول/پروژه — اگر تصویر نداشته باشد، تصویر پیش‌فرض تنظیم‌شده
 * در ادمین (default_product_image / default_project_image) برگردانده می‌شود.
 * اگر آن هم نباشد، خالی برمی‌گردد (نمایش آیکن).
 */
function entity_image_url(string $type, ?string $image): string
{
    $image = trim((string) $image);
    if ($image !== '') {
        return $image;
    }
    $default = get_setting('default_' . $type . '_image', '');
    return trim($default);
}

/**
 * HTML تصویر محصول/پروژه — اگر هیچ تصویری نباشد، placeholder آیکنی نمایش می‌دهد.
 * @param string $type 'product' | 'project'
 * @param string|null $image مسیر تصویر ذخیره‌شده
 * @param string $class کلاس‌های img (سایز و شکل)
 * @param string $icon آیکن placeholder
 */
function entity_image_html(string $type, ?string $image, string $class = 'w-full h-40 object-cover', string $icon = ''): string
{
    if ($icon === '') {
        $icon = $type === 'product' ? icon('box', 'w-10 h-10') : icon('folder', 'w-10 h-10');
    }
    $url = asset_url(entity_image_url($type, $image));
    if ($url) {
        return '<img src="' . e($url) . '" alt="تصویر" loading="lazy" class="' . e($class) . ' img-skeleton">';
    }
    return '<div class="' . e($class) . ' flex items-center justify-center bg-slate-100 text-slate-300">' . $icon . '</div>';
}

/** رندر empty-state (آیکن + عنوان + توضیح + CTA اختیاری) */
function empty_state(string $title, string $description = '', string $iconName = 'info', string $cta = ''): string
{
    return '<div class="empty-state">'
        . '<div class="empty-icon">' . icon($iconName, 'w-7 h-7') . '</div>'
        . '<h3>' . e($title) . '</h3>'
        . ($description !== '' ? '<p>' . e($description) . '</p>' : '')
        . ($cta !== '' ? '<div class="empty-action">' . $cta . '</div>' : '')
        . '</div>';
}

// ---------------------------------------------------------------------------
// استایل‌های کارت محصولات / پروژه‌ها (قابل انتخاب در پنل ادمین)
// ---------------------------------------------------------------------------

/** لیست استایل‌های موجود کارت (برای محصولات و پروژه‌ها) */
function entity_card_styles(): array
{
    return [
        'vertical'   => 'عمودی — عکس در بالا',
        'horizontal' => 'افقی — عکس در کنار',
        'minimal'    => 'ساده — بدون عکس',
        'list'       => 'لیستی — ردیفی فشرده',
    ];
}

/** کلاس گرید برای هر استایل کارت */
function entity_card_grid_class(string $style): string
{
    if ($style === 'list') {
        return 'grid grid-cols-1 gap-3';
    }
    return 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6';
}

/**
 * رندر یک کارت محصول/پروژه بر اساس استایل انتخابی
 *
 * @param string $type          'product' یا 'project'
 * @param array  $row           ردیف دیتابیس
 * @param string $style         یکی از کلیدهای entity_card_styles()
 * @param array  $surveys       نظرسنجی‌های مرتبط با این مورد (برای دکمه/پیام)
 * @param bool   $surveys_enabled آیا ماژول نظرسنجی فعال است
 */
function render_entity_card(string $type, array $row, string $style = 'vertical', array $surveys = [], bool $surveys_enabled = true): string
{
    $is_product = $type === 'product';
    $title = e($row['title'] ?? '');
    $desc  = e($row['description'] ?: 'بدون توضیحات');
    // عناوین و توضیحات از داده‌های کاربر/مدیر می‌آیند و ممکن است فارسی، لاتین یا مختلط باشند.
    $title_html = '<bdi dir="auto">' . $title . '</bdi>';
    $desc_html  = '<bdi dir="auto">' . $desc . '</bdi>';
    $icon  = $is_product ? icon('box', 'w-6 h-6') : icon('folder', 'w-6 h-6');

    // ---- بج وضعیت ----
    if ($is_product) {
        $badge = product_status_badge($row['product_status'] ?? null);
    } else {
        $st = $row['status'] ?? 'pending';
        if ($st === 'completed') {
            $badge = '<span class="bg-emerald-50 text-emerald-700 text-xs px-2.5 py-1 rounded-full font-medium shadow-sm">تکمیل شده</span>';
        } elseif ($st === 'in_progress') {
            $badge = '<span class="bg-indigo-50 text-indigo-700 text-xs px-2.5 py-1 rounded-full font-medium shadow-sm">در حال انجام</span>';
        } else {
            $badge = '<span class="bg-amber-50 text-amber-700 text-xs px-2.5 py-1 rounded-full font-medium shadow-sm">در انتظار شروع</span>';
        }
    }

    // ---- جزئیات ----
    if ($is_product) {
        $details = '';
        if (!empty($row['purchase_date'])) {
            $details .= '<div class="flex items-center justify-between gap-3 text-xs"><span class="text-slate-500">تاریخ خرید</span><strong class="text-slate-800 value-ltr whitespace-nowrap" dir="ltr">' . e($row['purchase_date']) . '</strong></div>';
        }
        $details_inline = !empty($row['purchase_date'])
            ? '<span>تاریخ خرید: <b class="text-slate-700 value-ltr whitespace-nowrap" dir="ltr">' . e($row['purchase_date']) . '</b></span>' : '';
    } else {
        $details = '<div class="flex items-center justify-between gap-3 text-xs"><span class="text-slate-500">تاریخ تکمیل پروژه</span><strong class="text-slate-800 value-ltr whitespace-nowrap" dir="ltr">' . e($row['deadline'] ?: '-') . '</strong></div>';
        $details_inline = '<span>تاریخ تکمیل: <b class="text-slate-700 value-ltr whitespace-nowrap" dir="ltr">' . e($row['deadline'] ?: '-') . '</b></span>';
    }

    // ---- بخش نظرسنجی ----
    $survey_html = '';
    if ($surveys_enabled) {
        $pending = array_filter($surveys, static fn($sv) => empty($sv['answered']) && strtotime($sv['available_at'] ?? '') <= time());
        if (!empty($pending)) {
            $survey_html = '<div class="flex flex-wrap gap-2">';
            foreach ($pending as $sv) {
                $survey_html .= '<a href="surveys.php?take=' . (int) $sv['assignment_id'] . '" class="btn btn-sm btn-primary">' . icon('star') . '<span>شروع نظرسنجی</span></a>';
            }
            $survey_html .= '</div>';
        } elseif (!empty($surveys)) {
            $survey_html = '<div class="inline-flex items-center gap-1.5 text-xs text-emerald-600 bg-emerald-50 px-3 py-2 rounded-lg">' . icon('check', 'w-4 h-4') . '<span>پاسخ شما ثبت شد</span></div>';
        }
    }

    // ---- عکس ----
    $img = $row['image'] ?? '';

    switch ($style) {
        case 'horizontal':
            return
            '<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col sm:flex-row hover:shadow-lg hover:border-slate-300 transition group">'
                . '<div class="relative sm:w-52 sm:min-h-full h-40 overflow-hidden bg-slate-100 flex-shrink-0">'
                    . entity_image_html($type, $img, 'w-full h-full object-cover group-hover:scale-105 transition duration-300', $icon)
                    . '<div class="absolute top-3 end-3">' . $badge . '</div>'
                . '</div>'
                . '<div class="p-5 flex flex-col flex-1 gap-3">'
                    . '<h4 class="font-bold text-slate-900 text-base leading-snug">' . $title_html . '</h4>'
                    . '<p class="text-slate-600 text-sm leading-relaxed line-clamp-2">' . $desc_html . '</p>'
                    . '<div class="mt-auto pt-2 space-y-2">' . $details . '</div>'
                    . ($survey_html ? '<div class="pt-1">' . $survey_html . '</div>' : '')
                . '</div>'
            . '</div>';

        case 'minimal':
            return
            '<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 flex flex-col gap-3 hover:shadow-lg hover:border-slate-300 transition">'
                . '<div class="flex items-start justify-between gap-3">'
                    . '<div class="flex items-center gap-3 min-w-0">'
                        . '<span class="w-11 h-11 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl flex-shrink-0">' . $icon . '</span>'
                        . '<h4 class="font-bold text-slate-900 text-base leading-snug">' . $title_html . '</h4>'
                    . '</div>'
                    . '<span class="flex-shrink-0">' . $badge . '</span>'
                . '</div>'
                . '<p class="text-slate-600 text-sm leading-relaxed line-clamp-2">' . $desc_html . '</p>'
                . '<div class="pt-3 border-t border-slate-100 space-y-2">' . $details . '</div>'
                . ($survey_html ? '<div>' . $survey_html . '</div>' : '')
            . '</div>';

        case 'list':
            return
            '<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-3 hover:shadow-md hover:border-slate-300 transition flex items-center gap-4">'
                . '<div class="w-16 h-16 rounded-xl overflow-hidden bg-slate-100 flex-shrink-0">'
                    . entity_image_html($type, $img, 'w-full h-full object-cover', $icon)
                . '</div>'
                . '<div class="flex-1 min-w-0">'
                    . '<div class="flex items-center justify-between gap-2">'
                        . '<h4 class="font-bold text-slate-900 text-sm truncate" title="' . $title . '">' . $title_html . '</h4>'
                        . $badge
                    . '</div>'
                    . '<p class="text-xs text-slate-500 truncate mt-0.5">' . $desc_html . '</p>'
                    . '<div class="mt-1.5 flex items-center gap-3 text-xs text-slate-500 flex-wrap">' . $details_inline . '</div>'
                . '</div>'
                . ($survey_html ? '<div class="flex-shrink-0">' . $survey_html . '</div>' : '')
            . '</div>';

        default: // vertical
            return
            '<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col hover:shadow-lg hover:border-slate-300 transition group">'
                . '<div class="relative h-44 overflow-hidden bg-slate-100">'
                    . entity_image_html($type, $img, 'w-full h-full object-cover group-hover:scale-105 transition duration-300', $icon)
                    . '<div class="absolute top-3 end-3">' . $badge . '</div>'
                . '</div>'
                . '<div class="p-5 flex flex-col flex-1 gap-3">'
                    . '<h4 class="font-bold text-slate-900 text-base leading-snug">' . $title . '</h4>'
                    . '<p class="text-slate-600 text-sm leading-relaxed line-clamp-2 flex-1">' . $desc . '</p>'
                    . '<div class="pt-3 border-t border-slate-100 space-y-2">' . $details . '</div>'
                    . ($survey_html ? '<div>' . $survey_html . '</div>' : '')
                . '</div>'
            . '</div>';
    }
}

// ---------------------------------------------------------------------------
// پالت‌های رنگی سایت (Theme) و لوگو
// ---------------------------------------------------------------------------

/** پالت‌های رنگی پیش‌فرض سیستم */
function theme_palettes(): array
{
    return [
        'indigo'  => ['label' => 'بنفش (پیش‌فرض)',   'primary' => '#4f46e5', 'primary_dark' => '#4338ca', 'primary_light' => '#eef2ff', 'primary_soft' => '#e0e7ff', 'accent' => '#7c3aed', 'grad_from' => '#4f46e5', 'grad_to' => '#7c3aed'],
        'emerald' => ['label' => 'سبز زمردی',        'primary' => '#059669', 'primary_dark' => '#047857', 'primary_light' => '#ecfdf5', 'primary_soft' => '#d1fae5', 'accent' => '#10b981', 'grad_from' => '#059669', 'grad_to' => '#10b981'],
        'blue'    => ['label' => 'آبی',              'primary' => '#2563eb', 'primary_dark' => '#1d4ed8', 'primary_light' => '#eff6ff', 'primary_soft' => '#dbeafe', 'accent' => '#3b82f6', 'grad_from' => '#2563eb', 'grad_to' => '#3b82f6'],
        'rose'    => ['label' => 'رز / قرمز',        'primary' => '#e11d48', 'primary_dark' => '#be123c', 'primary_light' => '#fff1f2', 'primary_soft' => '#ffe4e6', 'accent' => '#f43f5e', 'grad_from' => '#e11d48', 'grad_to' => '#f43f5e'],
        'orange'  => ['label' => 'نارنجی',           'primary' => '#ea580c', 'primary_dark' => '#c2410c', 'primary_light' => '#fff7ed', 'primary_soft' => '#ffedd5', 'accent' => '#f97316', 'grad_from' => '#ea580c', 'grad_to' => '#f97316'],
        'violet'  => ['label' => 'بنفش روشن',        'primary' => '#7c3aed', 'primary_dark' => '#6d28d9', 'primary_light' => '#f5f3ff', 'primary_soft' => '#ede9fe', 'accent' => '#8b5cf6', 'grad_from' => '#7c3aed', 'grad_to' => '#8b5cf6'],
        'slate'   => ['label' => 'سرمه‌ای تیره',     'primary' => '#334155', 'primary_dark' => '#1e293b', 'primary_light' => '#f1f5f9', 'primary_soft' => '#e2e8f0', 'accent' => '#475569', 'grad_from' => '#334155', 'grad_to' => '#475569'],
    ];
}

/** پالت فعال سایت (از تنظیمات) */
function active_theme_palette(): array
{
    $key = get_setting('site_theme', 'indigo');
    $palettes = theme_palettes();
    return $palettes[$key] ?? $palettes['indigo'];
}

/**
 * CSS استایل پالت رنگی فعال — در <head> همه قالب‌ها شامل می‌شود.
 * کلاس‌های اصلی Tailwind (indigo) با رنگ پالت انتخابی override می‌شوند.
 */
function theme_styles(): string
{
    $p = active_theme_palette();
    $primary   = $p['primary'];
    $dark      = $p['primary_dark'];
    $light     = $p['primary_light'];
    $soft      = $p['primary_soft'];
    $accent    = $p['accent'];
    $grad_from = $p['grad_from'];
    $grad_to   = $p['grad_to'];

    return '
<style>
:root {
    --tp-primary: ' . $primary . ';
    --tp-primary-dark: ' . $dark . ';
    --tp-primary-light: ' . $light . ';
    --tp-primary-soft: ' . $soft . ';
    --tp-accent: ' . $accent . ';
    --tp-sidebar: linear-gradient(180deg, color-mix(in srgb, ' . $dark . ' 38%, #0f172a) 0%, #0f172a 62%, #0f172a 100%);
    --tp-sidebar-hover: color-mix(in srgb, ' . $primary . ' 28%, #1e293b);
    --tp-sidebar-border: color-mix(in srgb, ' . $primary . ' 26%, #1e293b);
}
.bg-indigo-600, .peer-checked\:bg-indigo-600 { background-color: var(--tp-primary); }
.bg-indigo-700, .hover\:bg-indigo-700:hover { background-color: var(--tp-primary-dark); }
.bg-indigo-500, .hover\:bg-indigo-500:hover { background-color: var(--tp-primary); }
.bg-indigo-50, .hover\:bg-indigo-50:hover { background-color: var(--tp-primary-light); }
.bg-indigo-100, .hover\:bg-indigo-100:hover { background-color: var(--tp-primary-soft); }
.text-indigo-600, .hover\:text-indigo-600:hover { color: var(--tp-primary); }
.text-indigo-700, .hover\:text-indigo-700:hover { color: var(--tp-primary-dark); }
.text-indigo-800, .hover\:text-indigo-800:hover { color: var(--tp-primary-dark); }
.text-indigo-900 { color: var(--tp-primary-dark); }
.text-indigo-100 { color: var(--tp-primary-light); }
.border-indigo-500, .hover\:border-indigo-500:hover { border-color: var(--tp-primary); }
.border-indigo-200 { border-color: var(--tp-primary-soft); }
.border-indigo-100 { border-color: var(--tp-primary-light); }
.ring-indigo-500, .focus\:ring-indigo-500:focus { --tw-ring-color: var(--tp-primary); }
.shadow-indigo-200 { --tw-shadow-color: var(--tp-primary-soft); }
.shadow-indigo-600\/30 { --tw-shadow-color: var(--tp-primary); }
.from-indigo-600 { --tw-gradient-from: var(--tp-primary); }
	.from-indigo-950, .via-indigo-950 { --tw-gradient-from: var(--tp-primary-dark); --tw-gradient-stops: var(--tw-gradient-from), var(--tp-primary-dark), var(--tw-gradient-to); }
	.bg-indigo-600\/20 { background-color: color-mix(in srgb, ' . $primary . ' 20%, transparent); }
	.bg-indigo-600\/30 { background-color: color-mix(in srgb, ' . $primary . ' 30%, transparent); }
	.bg-indigo-50\/30 { background-color: color-mix(in srgb, ' . $light . ' 30%, white); }
	.bg-indigo-50\/40, .hover\:bg-indigo-50\/40:hover { background-color: color-mix(in srgb, ' . $light . ' 40%, white); }
	.bg-indigo-50\/50, .hover\:bg-indigo-50\/50:hover { background-color: color-mix(in srgb, ' . $light . ' 50%, white); }
	.bg-indigo-50\/60 { background-color: color-mix(in srgb, ' . $light . ' 60%, white); }
	.bg-violet-100 { background-color: var(--tp-primary-soft); }
	.bg-violet-600 { background-color: var(--tp-accent); }
	.bg-violet-50, .hover\:bg-violet-50:hover { background-color: var(--tp-primary-light); }
	.hover\:bg-violet-700:hover { background-color: var(--tp-primary-dark); }
	.text-violet-600, .hover\:text-violet-600:hover { color: var(--tp-accent); }
	.text-violet-700, .hover\:text-violet-700:hover { color: var(--tp-primary-dark); }
	.border-violet-200, .hover\:border-violet-500:hover { border-color: var(--tp-primary-soft); }
	.from-violet-600 { --tw-gradient-from: var(--tp-accent); }
	.to-violet-600 { --tw-gradient-to: var(--tp-accent); }
	.hover\:bg-indigo-100:hover { background-color: var(--tp-primary-soft); }
	.hover\:bg-indigo-700:hover { background-color: var(--tp-primary-dark); }
	.hover\:border-indigo-300:hover, .hover\:border-indigo-400:hover, .hover\:border-indigo-500:hover { border-color: var(--tp-primary); }
	.hover\:text-indigo-600:hover, .hover\:text-indigo-700:hover, .hover\:text-indigo-800:hover { color: var(--tp-primary-dark); }
	.border-indigo-300, .border-indigo-500, .border-indigo-600 { border-color: var(--tp-primary); }
	.border-indigo-100 { border-color: var(--tp-primary-light); }
	.ring-indigo-100 { --tw-ring-color: var(--tp-primary-light); }
	.ring-indigo-500 { --tw-ring-color: var(--tp-primary); }
	.shadow-indigo-600, .shadow-indigo-600\/30 { --tw-shadow-color: var(--tp-primary); }
	.shadow-indigo-200 { --tw-shadow-color: var(--tp-primary-soft); }
	.text-indigo-100 { color: var(--tp-primary-light); }
	.text-indigo-900 { color: var(--tp-primary-dark); }
	.group:hover .group-hover\:text-indigo-600, .group:hover .group-hover\:text-indigo-700 { color: var(--tp-primary); }
	.group:hover .group-hover\:border-indigo-500 { border-color: var(--tp-primary); }
	.file\:bg-indigo-50::file-selector-button { background-color: var(--tp-primary-light); }
	.file\:text-indigo-700::file-selector-button { color: var(--tp-primary-dark); }
/* ---- تقویت کنتراست رنگ primary در حالت دارک ---- */
html.dark {
  --tp-primary: color-mix(in srgb, var(--tp-primary-orig, #4f46e5) 60%, #c7d2fe) !important;
  --tp-primary-dark: color-mix(in srgb, var(--tp-primary-orig-dark, #4338ca) 50%, #a5b4fc) !important;
}
html.dark .text-indigo-600, html.dark .text-indigo-700,
html.dark .hover\:text-indigo-600:hover, html.dark .hover\:text-indigo-700:hover { color: color-mix(in srgb, var(--tp-primary) 75%, white); }
html.dark .text-violet-600, html.dark .text-violet-700,
html.dark .hover\:text-violet-600:hover, html.dark .hover\:text-violet-700:hover { color: color-mix(in srgb, var(--tp-accent) 75%, white); }
html.dark .bg-indigo-50, html.dark .bg-indigo-100 { background-color: color-mix(in srgb, var(--tp-primary) 18%, var(--portal-surface, #1f1f21)); }
html.dark .bg-violet-50, html.dark .bg-violet-100 { background-color: color-mix(in srgb, var(--tp-accent) 18%, var(--portal-surface, #1f1f21)); }
html.dark .badge-brand { background: color-mix(in srgb, var(--tp-primary) 22%, transparent); color: color-mix(in srgb, var(--tp-primary) 75%, white); }

/* ---- سایدبار و بخش‌های تیره — هماهنگ با پالت رنگی ---- */
.bg-slate-900 { background: var(--tp-sidebar); }
.bg-slate-800, .hover\:bg-slate-800\/80:hover { background-color: var(--tp-sidebar-hover); }
.border-slate-800 { border-color: var(--tp-sidebar-border); }
.hover\:bg-slate-800\/80:hover { background-color: var(--tp-sidebar-hover); }
/* رادیو/چک‌باکس‌ها */
	.accent-indigo-600 { accent-color: var(--tp-primary); }
	.text-indigo-600 { color: var(--tp-primary); }
/* اعداد و شمارنده‌ها در کارت‌ها */
.bg-indigo-600\/20, .bg-indigo-600\/30 { background-color: color-mix(in srgb, ' . $primary . ' 20%, transparent); }
/* لینک‌های فعال در سایدبار و هایلایت منو */
.bg-indigo-600.text-white { background-color: var(--tp-primary-dark); }
.peer-checked\:bg-indigo-600 { background-color: var(--tp-primary); }
/* ---- سوییچر (Toggle Switch) — وضعیت روشن/خاموش همیشه واضح و هماهنگ با تم ---- */
.switch-track{position:relative;width:48px;height:24px;border-radius:9999px;background-color:#cbd5e1;transition:background-color .15s ease;flex-shrink:0;cursor:pointer}
.switch-track::after{content:"";position:absolute;top:2px;inset-inline-start:2px;width:20px;height:20px;border-radius:9999px;background:#fff;border:1px solid #94a3b8;box-shadow:0 1px 3px rgba(0,0,0,.25);transition:inset-inline-start .15s ease, border-color .15s ease}
.peer:checked ~ .switch-track{background-color:var(--tp-primary)}
.peer:checked ~ .switch-track::after{inset-inline-start:26px;border-color:#fff}
.peer:focus-visible ~ .switch-track{box-shadow:0 0 0 3px color-mix(in srgb, var(--tp-primary) 35%, transparent)}
</style>' . "\n";
}

/** تبدیل مسیر نسبی به آدرس درست بر اساس عمق صفحه (admin/ و customer/ → ../) */
function asset_url(string $path): string
{
    $path = trim($path);
    if ($path === '') {
        return '';
    }
    // آدرس‌های مطلق (http/https/data) بدون تغییر برمی‌گردند
    if (preg_match('#^(https?:)?//#i', $path) || str_starts_with($path, 'data:')) {
        return $path;
    }
    return datepicker_assets_base() . $path;
}

/** مسیر لوگوی سایت (اگر تنظیم شده باشد) یا خالی */
function site_logo_url(): string
{
    $logo = get_setting('site_logo', '');
    return trim($logo);
}

/**
 * HTML لوگوی سایت — اگر لوگو تنظیم شده باشد تصویر، وگرنه حرف پیش‌فرض
 * @param string $boxClass کلاس‌های کانتینر لوگو (سایز و …)
 */
function site_logo_html(string $boxClass = 'w-8 h-8'): string
{
    $logo = site_logo_url();
    if ($logo) {
        return '<img src="' . e(asset_url($logo)) . '" alt="لوگو" class="' . e($boxClass) . ' object-contain rounded-lg">';
    }
    return '<span class="' . e($boxClass) . ' bg-indigo-600 rounded-lg flex items-center justify-center text-white text-sm font-bold">پ</span>';
}

/** تگ آیکن سایت (Favicon) — از لوگوی تنظیم‌شده استفاده می‌کند، وگرنه آیکن پیش‌فرض */
function site_favicon_html(): string
{
    $logo = site_logo_url();
    if ($logo) {
        $logo_url = asset_url($logo);
        $ext = strtolower(pathinfo(parse_url($logo_url, PHP_URL_PATH) ?: $logo_url, PATHINFO_EXTENSION));
        if ($ext === 'svg') {
            return '<link rel="icon" type="image/svg+xml" href="' . e($logo_url) . '">' . "\n";
        }
        return '<link rel="icon" href="' . e($logo_url) . '">' . "\n";
    }
    // آیکن پیش‌فرض: حرف «پ» با primary پالت فعال (SVG اینلاین)
    $p = active_theme_palette();
    $svg = 'data:image/svg+xml,' . rawurlencode(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="' . $p['primary'] . '"/><text x="32" y="46" font-family="Tahoma,Arial,sans-serif" font-size="36" font-weight="bold" fill="#ffffff" text-anchor="middle">پ</text></svg>'
    );
    return '<link rel="icon" type="image/svg+xml" href="' . $svg . '">' . "\n";
}

// ---------------------------------------------------------------------------
// نقش‌ها و دسترسی‌های مدیران (RBAC)
// ---------------------------------------------------------------------------

/** برچسب فارسی نقش مدیر */
function admin_role_label(string $role): string
{
    return match ($role) {
        'super_admin' => 'مدیر ارشد (سوپر ادمین)',
        'admin'       => 'مدیر',
        default       => 'نامشخص',
    };
}

/** لیست همه دسترسی‌های ممکن سیستم (برای مدیریت نقش‌ها) */
function admin_permissions_list(): array
{
    return [
        'dashboard'          => 'داشبورد',
        'customers'          => 'مدیریت مشتریان',
        'projects'           => 'مدیریت پروژه‌ها',
        'products'           => 'مدیریت محصولات',
        'invoices'           => 'مدیریت فاکتورها',
        'tickets'            => 'تیکت‌های پشتیبانی',
        'ticket_departments' => 'دپارتمان‌های تیکت',
        'surveys'            => 'سیستم نظرسنجی',
        'custom_fields'      => 'فیلدهای سفارشی',
        'notifications'      => 'اعلانات و اطلاع‌رسانی',
        'settings'           => 'تنظیمات سیستم',
        'logs'               => 'گزارش فعالیت‌ها',
        'error_reports'      => 'گزارش‌های خطا',
        'gamification'       => 'باشگاه امتیاز و پاداش',
        'admins'             => 'مدیریت مدیران',
        'profile'            => 'پروفایل مدیر',
    ];
}

/** آیا مدیر جاری دسترسی به بخش موردنظر دارد؟ */
function admin_can(string $permission): bool
{
    $role = $_SESSION['role'] ?? '';
    if ($role === 'super_admin') {
        return true; // سوپر ادمین همه‌جا دسترسی دارد
    }
    if ($role !== 'admin') {
        return false;
    }

    // مدیریت مدیران و تنظیمات سیستم (شامل credentialهای پیامک) فقط برای سوپر ادمین
    if ($permission === 'admins' || $permission === 'settings') {
        return false;
    }

    // ابتدا override اختصاصی کاربر بررسی می‌شود؛ نبود override یعنی fallback به role.
    global $pdo;
    if (!$pdo) {
        return false;
    }
    try {
        $user_permission = $pdo->prepare("SELECT allowed FROM admin_user_permissions WHERE user_id = ? AND permission = ? LIMIT 1");
        $user_permission->execute([(int) ($_SESSION['user_id'] ?? 0), $permission]);
        $override = $user_permission->fetchColumn();
        if ($override !== false) {
            return (bool) $override;
        }

        $q = $pdo->prepare("SELECT permission FROM admin_permissions WHERE role = 'admin' AND permission = ?");
        $q->execute([$permission]);
        return (bool) $q->fetchColumn();
    } catch (Throwable $e) {
        // fail-closed: خطای دیتابیس نباید دسترسی را باز کند (امنیت)
        error_log('[Portal Perm] ' . $e->getMessage());
        return false;
    }
}

/**
 * الزام دسترسی — اگر مدیر دسترسی نداشت، به داشبورد برگردانده می‌شود
 * (در ابتدای صفحات ادمین صدا زده شود)
 */
function admin_require_permission(string $permission): void
{
    if (!admin_can($permission)) {
        header('Location: index.php');
        exit;
    }
}

// ---------------------------------------------------------------------------
// دپارتمان‌های تیکت
// ---------------------------------------------------------------------------

/** لیست دپارتمان‌های فعال تیکت */
function ticket_departments(): array
{
    global $pdo;
    if (!$pdo) {
        return [];
    }
    try {
        $q = $pdo->query("SELECT * FROM ticket_departments WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
        return $q->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/** نام دپارتمان با شناسه (یا 'نامشخص') */
function ticket_department_name(?int $dept_id): string
{
    if (!$dept_id) {
        return 'عمومی';
    }
    global $pdo;
    try {
        $q = $pdo->prepare("SELECT name FROM ticket_departments WHERE id = ?");
        $q->execute([$dept_id]);
        $n = $q->fetchColumn();
        return $n ? (string) $n : 'عمومی';
    } catch (Throwable $e) {
        return 'عمومی';
    }
}

// ---------------------------------------------------------------------------
// ورود با شماره موبایل و کد OTP (پیامک ippanel)
// ---------------------------------------------------------------------------

/**
 * نرمال‌سازی مبلغ برای ذخیره و محاسبه.
 * خروجی همیشه رشتهٔ اعشاری با دو رقم است؛ null یعنی ورودی نامعتبر.
 */
function normalize_money_input(string $value): ?string
{
    $value = fa_digits_to_en(trim($value));
    $value = str_replace('٬', ',', $value);
    if ($value === '') {
        return '';
    }
    if (str_contains($value, ',')) {
        if (!preg_match('/^\d{1,3}(?:,\d{3})+(?:\.\d{1,2})?$/', $value)) {
            return null;
        }
        $value = str_replace(',', '', $value);
    }
    if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
        return null;
    }
    [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
    $whole = ltrim($whole, '0') ?: '0';
    return $whole . '.' . str_pad($fraction, 2, '0');
}

/** نرمال‌سازی شماره موبایل به فرمت E.164 برای ippanel (+98...) */
function normalize_mobile(string $mobile): string
{
    $m = preg_replace('/[^0-9]/', '', $mobile);
    if (strlen($m) === 10 && str_starts_with($m, '9')) {
        $m = '0' . $m;
    }
    if (str_starts_with($m, '0')) {
        $m = '98' . substr($m, 1);
    }
    return '+' . $m;
}

/** نرمال‌سازی شماره موبایل برای جستجو در دیتابیس (۰۹۱۲...) */
function normalize_mobile_db(string $mobile): string
{
    $m = preg_replace('/[^0-9]/', '', $mobile);
    if (str_starts_with($m, '98')) {
        $m = '0' . substr($m, 2);
    }
    return $m;
}

/**
 * بررسی تکراری بودن شماره موبایل بین کاربران
 * @param string $mobile شماره خام (می‌تواند فارسی یا با +98 باشد)
 * @param int|null $ignore_id شناسه کاربری که باید نادیده گرفته شود (برای ویرایش)
 * @return bool true اگر شماره قبلاً برای کاربر دیگری ثبت شده باشد
 */
function mobile_exists(string $mobile, ?int $ignore_id = null): bool
{
    global $pdo;
    $m = normalize_mobile_db(fa_digits_to_en($mobile));
    if ($m === '') {
        return false; // شماره خالی، تکراری محسوب نمی‌شود
    }
    if (!$pdo) {
        return false;
    }
    $sql = "SELECT id FROM users WHERE mobile = ?";
    $params = [$m];
    if ($ignore_id) {
        $sql .= " AND id != ?";
        $params[] = $ignore_id;
    }
    $sql .= " LIMIT 1";
    $q = $pdo->prepare($sql);
    $q->execute($params);
    return (bool) $q->fetchColumn();
}

/** تولید کد OTP تصادفی با طول قابل تنظیم (۴ تا ۶ رقم) */
function generate_otp_code(): string
{
    $len = (int) get_setting('otp_length', '6');
    if ($len < 4 || $len > 6) {
        $len = 6;
    }
    $min = (int) str_pad('1', $len, '0');
    $max = (int) str_repeat('9', $len);
    return (string) random_int($min, $max);
}

/** تبدیل ارقام فارسی/عربی به انگلیسی (مثلا ۱۲۳ → 123) */
function fa_digits_to_en(string $input): string
{
    $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    return str_replace($fa, $en, str_replace($ar, $en, $input));
}

/**
 * ارسال پیامک از طریق ippanel (روش pattern)
 *
 * نکته: در محیط‌های لوکال (مثل لاراگون) ممکن است فایل گواهی SSL (cacert.pem)
 * تنظیم نشده باشد و cURL خطای «error setting certificate file» بدهد.
 * برای حل این مشکل:
 *  - ابتدا با اعتبارسنجی SSL تلاش می‌شود؛
 *  - اگر خطای سرتیفیکیت رخ داد، به‌صورت خودکار یک بار دیگر بدون اعتبارسنجی تلاش می‌شود؛
 *  - ادمین می‌تواند از تنظیمات، اعتبارسنجی SSL را همیشه غیرفعال کند (برای تست لوکال).
 *
 * @param string $mobile شماره موبایل گیرنده
 * @param string|array $params پارامترهای پترن:
 *        - اگر string: مقدار یک متغیر (با pattern_var)
 *        - اگر array: نقشه کامل نام‌متغیر → مقدار (چند پارامتر)
 * @param string $pattern_code کد پترن (اختیاری — پیش‌فرض از تنظیمات sms_pattern)
 * @param string $pattern_var نام متغیر (فقط وقتی $params رشته است)
 * @return array ['ok'=>bool, 'message'=>string]
 */
function portal_sms_api_key(): string
{
    // credential فقط از environment خوانده می‌شود؛ مقدار legacy دیتابیس عمداً نادیده گرفته می‌شود.
    return trim((string) (getenv('PORTAL_SMS_API_KEY') ?: ''));
}

function send_sms_via_ippanel(string $mobile, $params, string $pattern_code = '', string $pattern_var = ''): array
{
    $api_key     = portal_sms_api_key();
    $from_number = get_setting('sms_from_number', '');
    $pattern     = $pattern_code !== '' ? $pattern_code : get_setting('sms_pattern', '');
    $var         = $pattern_var !== '' ? $pattern_var : get_setting('sms_pattern_var', 'code');

    if ($api_key === '' || $pattern === '' || $from_number === '') {
        return ['ok' => false, 'message' => 'تنظیمات پیامک در پنل مدیریت کامل نشده است.'];
    }

    // ساخت پارامترهای پترن: اگر آرایه بود همان را استفاده کن، وگرنه تک‌متغیره بساز
    if (is_array($params)) {
        $pattern_params = [];
        foreach ($params as $k => $v) {
            $pattern_params[(string) $k] = (string) $v;
        }
    } else {
        $pattern_params = [$var => (string) $params];
    }

    $url = 'https://edge.ippanel.com/v1/api/send';
    $postfields = json_encode([
        'sending_type' => 'pattern',
        'from_number'  => $from_number,
        'code'         => $pattern,
        'recipients'   => [normalize_mobile($mobile)],
        'params'       => $pattern_params,
    ]);
    $headers = [
        'Content-Type: application/json',
        'Authorization: ' . $api_key,
    ];

    // اگر ادمین اعتبارسنجی SSL را از تنظیمات غیرفعال کرده باشد (تست لوکال) → فقط بدون اعتبارسنجی
    $force_skip_ssl = get_setting('sms_ssl_verify', '1') !== '1';
    $attempts = $force_skip_ssl ? [false] : [true, false]; // اول با اعتبارسنجی، در صورت خطای سرتیفیکیت دوباره بدون آن

    $last_error = '';
    foreach ($attempts as $verify) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => $postfields,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => $verify,
            CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
        ]);

        // اگر فایل CA معتبر در تنظیمات PHP وجود دارد، از آن استفاده کن
        // (مشکل لاراگون معمولاً همین جاست: مسیر cacert.pem اشتباه/ناموجود است)
        $ca_candidates = array_filter([
            ini_get('curl.cainfo'),
            ini_get('openssl.cafile'),
        ]);
        foreach ($ca_candidates as $cafile) {
            if (is_file($cafile)) {
                curl_setopt($ch, CURLOPT_CAINFO, $cafile);
                break;
            }
        }

        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($resp !== false) {
            $data = json_decode($resp, true);
            if (!empty($data['meta']['status'])) {
                return ['ok' => true, 'message' => 'پیامک با موفقیت ارسال شد.'];
            }
            return ['ok' => false, 'message' => 'خطای سرویس پیامک: ' . ($data['meta']['message'] ?? 'نامشخص')];
        }

        $last_error = $err;
        $is_cert_error = (stripos($err, 'certificate') !== false)
                      || (stripos($err, 'ssl') !== false)
                      || (stripos($err, 'cacert') !== false)
                      || (stripos($err, 'ca:') !== false);

        // اگر خطای سرتیفیکیت بود و هنوز تلاش بدون اعتبارسنجی باقی است، ادامه بده
        if ($verify && $is_cert_error) {
            continue;
        }
        break;
    }

    return ['ok' => false, 'message' => 'خطای اتصال به سرویس پیامک: ' . $last_error];
}

/**
 * ارسال کد تایید برای شماره موبایل: تولید کد، ذخیره در otp_codes و ارسال پیامک
 * @return array ['ok'=>bool, 'message'=>string, 'code'=>?string] (code فقط در حالت تست/dev)
 */
function send_otp_code(string $mobile): array
{
    global $pdo;
    $m = normalize_mobile_db($mobile);
    if (strlen($m) !== 11 || !str_starts_with($m, '09')) {
        return ['ok' => false, 'message' => 'شماره موبایل معتبر نیست.'];
    }

    // بررسی اینکه این شماره کاربر (مشتری یا مدیر) دارد
    $q = $pdo->prepare("SELECT id FROM users WHERE mobile = ? AND role IN ('customer','admin','super_admin') LIMIT 1");
    $q->execute([$m]);
    if (!$q->fetchColumn()) {
        return ['ok' => false, 'message' => 'کاربری با این شماره موبایل در سیستم یافت نشد.'];
    }

    // محدودیت نرخ: حداکثر ۳ ارسال کد در ۱۰ دقیقه برای هر شماره
    $rate = $pdo->prepare("SELECT COUNT(*) FROM otp_codes WHERE mobile = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
    $rate->execute([$m]);
    if ((int) $rate->fetchColumn() >= 3) {
        return ['ok' => false, 'message' => 'تعداد درخواست‌های ارسال کد زیاد است. لطفاً ۱۰ دقیقه دیگر تلاش کنید.'];
    }

    $code = generate_otp_code();
    $expires = date('Y-m-d H:i:s', time() + 300); // معتبر ۵ دقیقه (هم‌زمان با timezone سرور PHP)

    // باطل کردن همه کدهای قبلی استفاده‌نشده این شماره (هر ارسال جدید کد قبلی را بی‌اعتبار می‌کند)
    $pdo->prepare("UPDATE otp_codes SET is_used = 1 WHERE mobile = ? AND is_used = 0")->execute([$m]);

    $ins = $pdo->prepare("INSERT INTO otp_codes (mobile, code, expires_at) VALUES (?, ?, ?)");
    $ins->execute([$m, $code, $expires]);

    // ارسال پیامک
    if (portal_sms_api_key() === '') {
        // حالت تست (بدون درگاه پیامک): کد فقط در محیط توسعه در پاسخ/سشن می‌آید.
        // در تولید (PORTAL_DEV_MODE=false) کد فقط در لاگ سرور ثبت می‌شود — امنیت ورود
        if (defined('PORTAL_DEV_MODE') && PORTAL_DEV_MODE) {
            $_SESSION['otp_test_code'] = $code;
            return ['ok' => true, 'message' => 'کد تایید (حالت تست): ' . $code];
        }
        error_log('[Portal OTP test-mode] code for ' . $m . ': ' . $code);
        return ['ok' => true, 'message' => 'کد تایید برای شما ارسال شد.'];
    }

    $sent = send_sms_via_ippanel($m, $code);
    if (!$sent['ok']) {
        return ['ok' => false, 'message' => $sent['message']];
    }

    return ['ok' => true, 'message' => 'کد تایید برای شما ارسال شد.'];
}

/**
 * بررسی کد OTP واردشده
 * - ارقام فارسی/عربی به انگلیسی تبدیل می‌شوند
 * - مقایسه زمان با زمان PHP (نه NOW() دیتابیس) تا با timezone سرور هماهنگ باشد
 * - بعد از ۵ تلاش ناموفق، کد باطل می‌شود
 */
// ---------------------------------------------------------------------------
// سیستم پیامک رویدادمحور (پترن اختصاصی برای هر رویداد + ارسال دستی به مشتری)
// ---------------------------------------------------------------------------

/** لیست همه رویدادهای پیامکی با توضیح و متغیرهای پیشنهادی */
function sms_event_list(): array
{
    return [
        'welcome'            => [
            'title' => 'خوش‌آمدگویی به مشتری جدید',
            'vars'  => 'first_name, last_name, username, mobile, company_name, job_title, gender, birth_date, created_at',
        ],
        'project_assigned'   => [
            'title' => 'انتصاب پروژه جدید',
            'vars'  => 'first_name, last_name, username, mobile, company_name, job_title, project_id, project_title, project_deadline, project_status, project_created_at',
        ],
        'product_assigned'   => [
            'title' => 'ثبت محصول جدید',
            'vars'  => 'first_name, last_name, username, mobile, company_name, job_title, product_id, product_title, product_status, purchase_date, product_price, product_created_at',
        ],
        'invoice_created'    => [
            'title' => 'صدور فاکتور جدید',
            'vars'  => 'first_name, last_name, username, mobile, company_name, job_title, invoice_id, invoice_number, invoice_title, invoice_amount, due_date, invoice_status, invoice_created_at',
        ],
        'ticket_reply'       => [
            'title' => 'پاسخ به تیکت',
            'vars'  => 'first_name, last_name, username, mobile, company_name, job_title, ticket_id, ticket_subject, ticket_status, ticket_priority, ticket_department, ticket_created_at',
        ],
        'survey_reminder'    => [
            'title' => 'یادآوری نظرسنجی ناقص',
            'vars'  => 'first_name, last_name, username, mobile, company_name, job_title, survey_title, entity_title, survey_link',
        ],
        'otp_login'          => ['title' => 'کد تایید ورود', 'vars' => 'code'],
    ];
}

/** اطلاعات یک رویداد پیامکی از جدول sms_events */
function sms_event(string $event_key): ?array
{
    global $pdo;
    if (!$pdo) {
        return null;
    }
    try {
        $q = $pdo->prepare("SELECT * FROM sms_events WHERE event_key = ?");
        $q->execute([$event_key]);
        $row = $q->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/** آیا رویداد پیامکی فعال است؟ */
function sms_event_active(string $event_key): bool
{
    $ev = sms_event($event_key);
    return $ev && (int) $ev['is_active'] === 1 && trim((string) $ev['pattern_code']) !== '';
}

/** ثبت لاگ ارسال پیامک */
function sms_log(string $event_key, string $mobile, ?int $user_id, string $message, bool $ok, string $error = ''): void
{
    global $pdo;
    if (!$pdo) {
        return;
    }
    try {
        $q = $pdo->prepare("INSERT INTO sms_logs (event_key, mobile, user_id, message, status, error) VALUES (?, ?, ?, ?, ?, ?)");
        $q->execute([$event_key, $mobile, $user_id, mb_substr($message, 0, 490), $ok ? 1 : 0, mb_substr($error, 0, 250)]);
    } catch (Throwable $e) {
        // بی‌صدا
    }
}

/**
 * ارسال پیامک برای یک رویداد خاص با پترن اختصاصی و متغیرهای داینامیک
 * @param string $event_key کلید رویداد
 * @param string $mobile شماره موبایل
 * @param array  $vars متغیرهای پترن (کلید → مقدار)
 * @param int|null $user_id شناسه کاربر
 */
function send_event_sms(string $event_key, string $mobile, array $vars = [], ?int $user_id = null): array
{
    global $pdo;
    $m = normalize_mobile_db($mobile);
    if (strlen($m) !== 11 || !str_starts_with($m, '09')) {
        return ['ok' => false, 'message' => 'شماره موبایل معتبر نیست.'];
    }

    // OTP: اگر رویداد otp_login تنظیمات مستقل داشته باشد از آن استفاده می‌شود،
    // وگرنه به تنظیمات سراسری پیامک (sms_pattern) برمی‌گردد.
    if ($event_key === 'otp_login') {
        $ev = sms_event('otp_login');
        $has_own = $ev && (int) $ev['is_active'] === 1 && trim((string) $ev['pattern_code']) !== '';
        $pattern_code = $has_own ? trim((string) $ev['pattern_code']) : get_setting('sms_pattern', '');
        $pattern_var  = get_setting('sms_pattern_var', 'code');
        $code = $vars['code'] ?? '';
        $sent = send_sms_via_ippanel($m, (string) $code, $pattern_code, $pattern_var);
        sms_log($event_key, $m, $user_id, "کد تایید: {$code}", $sent['ok'], $sent['message']);
        return $sent;
    }

    $ev = sms_event($event_key);
    if (!$ev || (int) $ev['is_active'] !== 1) {
        return ['ok' => false, 'message' => 'این رویداد پیامکی فعال نیست.'];
    }
    if (trim((string) $ev['pattern_code']) === '') {
        return ['ok' => false, 'message' => 'کد پترن این رویداد تنظیم نشده است.'];
    }

    // خواندن نگاشت متغیرهای این رویداد: "sys=pattern,sys2=pattern2" (از تنظیمات ادمین)
    $mapping = [];
    foreach (array_filter(array_map('trim', explode(',', (string) ($ev['pattern_vars'] ?? '')))) as $pair) {
        if (str_contains($pair, '=')) {
            [$sys, $pat] = explode('=', $pair, 2);
            $sys = trim($sys);
            $mapping[$sys] = trim($pat) !== '' ? trim($pat) : $sys;
        } else if ($pair !== '') {
            $mapping[$pair] = $pair;
        }
    }

    // ساخت پارامترهای پترن بر اساس نگاشت (کلید = نام معادل در پترن)
    $pattern_params = [];
    if (!empty($mapping)) {
        foreach ($mapping as $sys => $pat) {
            if (array_key_exists($sys, $vars)) {
                $pattern_params[$pat] = (string) $vars[$sys];
            }
        }
        // اگر هیچ متغیری مطابقت نداشت، همه $vars را بفرست
        if (empty($pattern_params)) {
            $pattern_params = array_map('strval', $vars);
        }
    } else {
        // بدون نگاشت → همه متغیرها ارسال می‌شوند
        $pattern_params = array_map('strval', $vars);
    }

    $sent = send_sms_via_ippanel($m, $pattern_params, (string) $ev['pattern_code']);
    sms_log($event_key, $m, $user_id, json_encode($vars, JSON_UNESCAPED_UNICODE), $sent['ok'], $sent['message']);
    return $sent;
}

/**
 * متغیرهای مشترک هر مشتری برای پیامک‌های رویدادمحور
 * (نام، نام خانوادگی، نام کاربری، موبایل، شرکت، سمت، جنسیت، تاریخ تولد، تاریخ عضویت)
 */
function sms_customer_vars(array $u): array
{
    return [
        'first_name'   => $u['first_name'] ?: $u['username'],
        'last_name'    => (string) ($u['last_name'] ?? ''),
        'username'     => (string) ($u['username'] ?? ''),
        'mobile'       => (string) ($u['mobile'] ?? ''),
        'company_name' => (string) ($u['company_name'] ?? ''),
        'job_title'    => (string) ($u['job_title'] ?? ''),
        'gender'       => (string) ($u['gender'] ?? ''),
        'birth_date'   => (string) ($u['birth_date'] ?? ''),
        'created_at'   => fa_datetime($u['user_created_at'] ?? $u['created_at'] ?? null),
    ];
}

/**
 * ساخت لینک عمومی تکمیل نظرسنجی برای پیامک یادآوری
 * (بدون نیاز به ورود — بر اساس توکن یکتای انتساب)
 */
function sms_survey_link(int $assignment_id, string $token = ''): string
{
    $base = rtrim(get_setting('site_url', ''), '/');
    if ($base === '') {
        // در حالت اجرای وب، دامنه فعلی را حدس بزن (کرون‌جاب دامنه ندارد → خالی برمی‌گردد)
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? '';
        if ($host !== '') {
            $base = $scheme . '://' . $host;
        }
    }
    if ($base === '') {
        return '';
    }
    $token = trim($token);
    if ($token !== '') {
        return $base . '/survey-public.php?token=' . rawurlencode($token);
    }
    return $base . '/customer/surveys.php?take=' . (int) $assignment_id;
}

/** تنظیمات یادآوری خودکار نظرسنجی: روز اولین یادآوری، فاصله تکرار و حداکثر تعداد */
function sms_survey_reminder_settings(): array
{
    return [
        'days'     => max(1, (int) get_setting('survey_reminder_days', '3')),
        'interval' => max(0, (int) get_setting('survey_reminder_interval', '7')),
        'max'      => max(1, (int) get_setting('survey_reminder_max', '3')),
    ];
}

/** لیست مشتریان برای ارسال دستی پیامک */
function sms_customer_list(): array
{
    global $pdo;
    if (!$pdo) {
        return [];
    }
    return $pdo->query("SELECT id, first_name, last_name, username, mobile FROM users WHERE role = 'customer' AND mobile != '' ORDER BY first_name ASC")->fetchAll();
}

/** تاریخچه ارسال پیامک‌ها (برای پنل ادمین) */
function sms_logs(int $limit = 100): array
{
    global $pdo;
    if (!$pdo) {
        return [];
    }
    return $pdo->query("SELECT l.*, u.first_name, u.last_name, u.username FROM sms_logs l LEFT JOIN users u ON u.id = l.user_id ORDER BY l.id DESC LIMIT " . (int) $limit)->fetchAll();
}

function verify_otp_code(string $mobile, string $code): bool
{
    global $pdo;
    $m = normalize_mobile_db($mobile);
    $code = trim(fa_digits_to_en((string) $code));

    if ($m === '' || $code === '') {
        return false;
    }

    // پیدا کردن جدیدترین کد فعال برای این شماره
    $q = $pdo->prepare("SELECT id, expires_at FROM otp_codes WHERE mobile = ? AND code = ? AND is_used = 0 ORDER BY id DESC LIMIT 1");
    $q->execute([$m, $code]);
    $row = $q->fetch();

    if (!$row) {
        // تلاش ناموفق: شمارنده تلاش جدیدترین کد استفاده‌نشده این شماره را زیاد کن؛ بعد از ۵ بار باطلش کن
        $pdo->prepare("UPDATE otp_codes SET attempts = attempts + 1 WHERE mobile = ? AND is_used = 0 ORDER BY id DESC LIMIT 1")->execute([$m]);
        $pdo->prepare("UPDATE otp_codes SET is_used = 1 WHERE mobile = ? AND is_used = 0 AND attempts >= 5")->execute([$m]);
        return false;
    }

    // مقایسه با زمان PHP (نه NOW()) — حل مشکل timezone
    if (strtotime($row['expires_at']) < time()) {
        // کد منقضی: باطلش کن و در شمارنده تلاش‌ها لحاظ کن
        $pdo->prepare("UPDATE otp_codes SET is_used = 1, attempts = attempts + 1 WHERE id = ?")->execute([$row['id']]);
        return false;
    }

    $pdo->prepare("UPDATE otp_codes SET is_used = 1 WHERE id = ?")->execute([$row['id']]);
    return true;
}

/** روش ورود فعال سیستم ('username' یا 'mobile') */
function login_method(): string
{
    $m = get_setting('login_method', 'username');
    return in_array($m, ['username', 'mobile'], true) ? $m : 'username';
}

/**
 * لیست طرح‌های صفحه ورود (برای تنظیمات عمومی)
 * هر طرح یک کلید، عنوان، توضیح و کلاس بدنه دارد.
 */
function login_layout_options(): array
{
    return [
        'centered' => [
            'title' => 'کارت متمرکز (پیش‌فرض)',
            'desc'  => 'کارت ورود در مرکز صفحه روی پس‌زمینه ساده',
            'badge' => 'bg-indigo-50 text-indigo-600',
        ],
        'split' => [
            'title' => 'دوطرفه — تصویر + فرم',
            'desc'  => 'تصویر برند در یک سمت و فرم ورود در سمت دیگر (دسکتاپ)',
            'badge' => 'bg-emerald-50 text-emerald-600',
        ],
        'branded' => [
            'title' => 'گرادیان برند (هیرو)',
            'desc'  => 'پس‌زمینه گرادیانی برند با کارت شیشه‌ای ورود',
            'badge' => 'bg-violet-50 text-violet-600',
        ],
        'minimal' => [
            'title' => 'مینیمال',
            'desc'  => 'بدون کارت، فرم ساده و شناور با حداقل عناصر',
            'badge' => 'bg-slate-100 text-slate-600',
        ],
    ];
}

/** طرح صفحه ورود فعال */
function active_login_layout(): string
{
    $l = get_setting('login_layout', 'centered');
    return array_key_exists($l, login_layout_options()) ? $l : 'centered';
}

/** اعتبارسنجی و نرمال‌سازی یک کد رنگ HEX (#rrggbb) */
function sanitize_hex_color(string $val, string $default = '#4f46e5'): string
{
    $val = trim($val);
    if (preg_match('/^#?[0-9a-fA-F]{6}$/', $val)) {
        return '#' . strtolower(substr(ltrim($val, '#'), 0, 6));
    }
    return $default;
}

/** متن پیش‌فرض و سازگار subtitle ورود؛ مقدار legacy قدیمی را به copy جدید ارتقا می‌دهد. */
function login_subtitle_value(): string
{
    $legacy = 'لطفا برای ورود به حساب کاربری خود اطلاعات زیر را وارد کنید';
    $subtitle = trim((string) get_setting('login_subtitle', ''));
    return $subtitle === '' || $subtitle === $legacy
        ? 'برای ورود، نام کاربری و رمز عبور خود را وارد کنید'
        : $subtitle;
}

/**
 * پیکربندی کامل صفحه ورود (برای رندر index.php)
 */
function login_config(): array
{
    $c = [
        'layout'       => active_login_layout(),
        'title'        => get_setting('site_title', 'پورتال مشتریان'),
        'subtitle'     => login_subtitle_value(),
        'footer'       => get_setting('footer_text', 'سیستم هوشمند پورتال مشتریان'),

        // طرح دوطرفه
        'split_image'       => get_setting('split_image', ''),
        'split_mobile_image'=> get_setting('split_mobile_image', ''),
        'split_ratio'       => (int) get_setting('split_ratio', '70'), // درصد تصویر
        'split_side'        => get_setting('split_side', 'right'),     // فرم در کدام سمت
        'split_vertical'    => get_setting('split_vertical', 'center'), // جایگاه عمودی فرم: top|center|bottom
        'split_title'       => get_setting('split_title', 'پورتال هوشمند مشتریان'),
        'split_subtitle'    => get_setting('split_subtitle', 'مدیریت هوشمند مشتریان، پروژه‌ها، فاکتورها و پشتیبانی در یک پورتال واحد.'),
        'split_feature1'    => get_setting('split_feature1', '24/7'),
        'split_feature1_l'  => get_setting('split_feature1_l', 'پشتیبانی'),
        'split_feature2'    => get_setting('split_feature2', '۱۰۰٪'),
        'split_feature2_l'  => get_setting('split_feature2_l', 'دسترس‌پذیری'),
        'split_feature3'    => get_setting('split_feature3', '🔒'),
        'split_feature3_l'  => get_setting('split_feature3_l', 'امنیت'),

        // طرح گرادیان برند
        'branded_from'  => sanitize_hex_color(get_setting('branded_from', '#4f46e5')),
        'branded_to'    => sanitize_hex_color(get_setting('branded_to', '#7c3aed')),
        'branded_mobile_image' => get_setting('branded_mobile_image', ''),

        // منوی هدر
        'header_align'  => get_setting('header_menu_align', 'start'), // start|center|end

        // تصویر پس‌زمینه دلخواه (برای همه طرح‌ها)
        'bg_image'      => get_setting('login_bg_image', ''),
        'bg_mobile_image'=> get_setting('login_bg_mobile_image', ''),
    ];

    // نرمال‌سازی نسبت تصویر (۴۰ تا ۷۵)
    $ratio = $c['split_ratio'];
    $c['split_ratio'] = max(40, min(75, $ratio));

    // سمت فرم: 'left' یا 'right'
    $c['split_side'] = $c['split_side'] === 'left' ? 'left' : 'right';

    // جایگاه عمودی فرم: top|center|bottom
    $c['split_vertical'] = in_array($c['split_vertical'], ['top', 'center', 'bottom'], true) ? $c['split_vertical'] : 'center';

    // تراز منوی هدر
    $c['header_align'] = in_array($c['header_align'], ['start', 'center', 'end'], true) ? $c['header_align'] : 'start';

    return $c;
}

/**
 * ذخیره فایل تصویر آپلودشده برای صفحه ورود (برگشتن مسیر یا خالی)
 */
function upload_login_image(array $file, string $prefix): string
{
    if (empty($file['name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return '';
    }
    $v = validate_upload_image($file, 5 * 1024 * 1024);
    if ($v !== true) {
        return '';
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $uploads_dir = dirname(__DIR__, 2) . '/uploads';
    if (!is_dir($uploads_dir)) {
        @mkdir($uploads_dir, 0755, true);
    }
    $fname = $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (@move_uploaded_file($file['tmp_name'], $uploads_dir . '/' . $fname)) {
        return 'uploads/' . $fname;
    }
    return '';
}

/** URL قابل نمایش در navigation سفارشی؛ schemeهای اجرایی و protocol-relative رد می‌شوند. */
function safe_navigation_url(string $url): string
{
    $url = trim($url);
    if ($url === '' || $url === '#') {
        return '#';
    }
    if (preg_match('/^(?:javascript|data|vbscript):/i', $url) || str_starts_with($url, '//')) {
        return '#';
    }
    if (str_starts_with($url, '/') || str_starts_with($url, '#') || str_starts_with($url, '?')) {
        return mb_substr($url, 0, 255);
    }
    if (preg_match('#^https?://#i', $url) && filter_var($url, FILTER_VALIDATE_URL)) {
        return mb_substr($url, 0, 255);
    }
    return '#';
}

/**
 * منوی سفارشی هدر (آیتم‌های مدیریتی) — ذخیره‌شده به‌صورت JSON در settings
 */
function header_menu_items(): array
{
    $raw = get_setting('header_menu', '[]');
    $items = json_decode($raw, true);
    if (!is_array($items)) {
        return [];
    }
    return array_values(array_filter(array_map(static function ($item): array {
        if (!is_array($item) || empty($item['label'])) {
            return [];
        }
        $item['url'] = safe_navigation_url((string) ($item['url'] ?? '#'));
        $item['target'] = (($item['target'] ?? '') === '_blank') ? '_blank' : '_self';
        return $item;
    }, $items), static fn(array $item): bool => !empty($item)));
}

// ---------------------------------------------------------------------------
// گزارش خطا (دکمه شناور) — فقط به سوپر ادمین
// ---------------------------------------------------------------------------

/** آیا ماژول گزارش خطا فعال است؟ */
function error_report_module_enabled(): bool
{
    return get_setting('module_error_reports', '1') === '1';
}

/** اطمینان از وجود جدول گزارش‌های خطا (نصب‌های قدیمی/بازیابی‌شده که migration 22 اجرا نشده) */
function portal_ensure_error_reports_table(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS error_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        reporter_name VARCHAR(120) DEFAULT '',
        reporter_role VARCHAR(20) DEFAULT '',
        url VARCHAR(500) DEFAULT '',
        message TEXT,
        status VARCHAR(20) DEFAULT 'new',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/** ثبت گزارش خطا (از دکمه شناور) — خود-ترمیمی: اگر جدول نبود، ساخته و دوباره تلاش می‌کند */
function create_error_report(array $data): bool
{
    global $pdo;
    if (!$pdo) return false;
    $params = [
        isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
        mb_substr(trim((string) ($data['reporter_name'] ?? '')), 0, 120),
        (string) ($_SESSION['role'] ?? 'guest'),
        mb_substr(trim((string) ($data['url'] ?? '')), 0, 500),
        mb_substr(trim((string) ($data['message'] ?? '')), 0, 3000),
    ];
    $sql = "INSERT INTO error_reports (user_id, reporter_name, reporter_role, url, message) VALUES (?, ?, ?, ?, ?)";
    try {
        $q = $pdo->prepare($sql);
        return $q->execute($params);
    } catch (PDOException $e) {
        // 42S02 / 1146: جدول وجود ندارد — خود-ترمیمی و تلاش مجدد
        $msg = $e->getMessage();
        if ($e->getCode() === '42S02' || str_contains($msg, "doesn't exist") || str_contains($msg, 'no such table')) {
            try {
                portal_ensure_error_reports_table($pdo);
                $q = $pdo->prepare($sql);
                return $q->execute($params);
            } catch (Throwable $t) {
                error_log('[Portal ErrorReport] ' . $t->getMessage());
                return false;
            }
        }
        error_log('[Portal ErrorReport] ' . $msg);
        return false;
    }
}

/** لیست گزارش‌های خطا (برای پنل مدیریت) — بدون خطای مرگبار اگر جدول هنوز ساخته نشده باشد */
function error_reports_list(int $limit = 100): array
{
    global $pdo;
    if (!$pdo) return [];
    $sql = "SELECT r.*, u.first_name, u.last_name, u.username FROM error_reports r LEFT JOIN users u ON u.id = r.user_id ORDER BY r.id DESC LIMIT " . (int) $limit;
    try {
        return $pdo->query($sql)->fetchAll();
    } catch (Throwable $e) {
        try {
            portal_ensure_error_reports_table($pdo);
            return $pdo->query($sql)->fetchAll();
        } catch (Throwable $t) {
            error_log('[Portal ErrorReport] ' . $t->getMessage());
            return [];
        }
    }
}

/** مارک‌آپ دکمه + مودال شناور گزارش خطا */
function error_report_widget(): string
{
    if (!error_report_module_enabled()) return '';
    $current_url = (isset($_SERVER['HTTP_HOST']) ? 'http' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] : '') . ($_SERVER['REQUEST_URI'] ?? '');
    $reporter = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
    if ($reporter === '') $reporter = $_SESSION['username'] ?? '';

    // دکمه شناور فقط-آیکن و کوچک — گوشه پایین-چپ، کم‌جلب‌توجه (باز شدن با اسکریپت پایین)
    $html = '<button type="button" id="error-report-fab" class="fixed bottom-20 md:bottom-4 end-4 z-[1800] flex items-center justify-center h-10 w-10 rounded-full text-white transition hover:scale-105 active:scale-95" style="background:linear-gradient(135deg,#ef4444,#dc2626);box-shadow:0 6px 16px -4px rgba(220,38,38,.45)" aria-label="گزارش خطا" title="گزارش خطا">'
        . icon('alert', 'w-5 h-5') . '</button>'
        . '<div id="error-report-modal" role="dialog" aria-modal="true" aria-labelledby="error-report-title" aria-describedby="error-report-description" class="hidden fixed inset-0 z-[2000] items-center justify-center p-4">'
        . '<div class="absolute inset-0 bg-black/50" data-error-modal-close></div>'
        . '<div class="relative w-full max-w-md card !rounded-2xl p-6 shadow-2xl" tabindex="-1">'
        . '<div class="flex items-start justify-between mb-4">'
        . '<div class="flex items-center gap-3"><span class="w-11 h-11 rounded-xl bg-red-50 text-red-600 flex items-center justify-center">' . icon('alert', 'w-5 h-5') . '</span><div><h3 id="error-report-title" class="text-lg font-bold text-slate-900">گزارش خطا</h3><p id="error-report-description" class="text-xs text-slate-500 mt-0.5">برای بهبود سامانه، خطای مشاهده‌شده را گزارش دهید.</p></div></div>'
        . '<button type="button" class="btn btn-sm btn-ghost" data-error-modal-close aria-label="بستن">' . icon('x') . '</button>'
        . '</div>'
        . '<form method="post" action="' . e((isset($_SERVER['PHP_SELF']) ? basename($_SERVER['PHP_SELF']) : '')) . '" novalidate>'
        . csrf_input()
        . '<input type="hidden" name="action" value="report_error">'
        . '<div class="space-y-4">'
        . '<div><label class="label" for="reporter_name">نام شما (اختیاری)</label><input type="text" name="reporter_name" id="reporter_name" value="' . e($reporter) . '" class="input"></div>'
        . '<div><label class="label" for="er_url">صفحه/آدرس</label><input type="text" name="url" id="er_url" value="' . e($current_url) . '" dir="ltr" class="input"></div>'
        . '<div><label class="label" for="er_msg">شرح خطا<span class="required-star" aria-hidden="true">*</span></label><textarea name="message" id="er_msg" rows="4" required class="input" placeholder="چه مشکلی رخ داد؟"></textarea><p class="field-error" style="display:none"></p></div>'
        . '</div>'
        . '<div class="flex justify-end gap-3 mt-5 pt-4 border-t border-slate-100">'
        . '<button type="button" class="btn btn-secondary" data-error-modal-close>انصراف</button>'
        . '<button type="submit" class="btn btn-danger">' . icon('send') . '<span>ارسال گزارش</span></button>'
        . '</div>'
        . '</form></div></div>'
        . '<script nonce="' . e(portal_csp_nonce()) . '">'
        . '(function(){var fab=document.getElementById(\'error-report-fab\'),modal=document.getElementById(\'error-report-modal\');if(!fab||!modal)return;var lastFocus=null,previousOverflow=\'\';function focusables(){return Array.prototype.slice.call(modal.querySelectorAll(\'a[href],button:not([disabled]),input:not([disabled]),textarea:not([disabled]),select:not([disabled]),[tabindex]:not([tabindex="-1"])\')).filter(function(el){return el.offsetParent!==null;});}'
        . 'function openM(){lastFocus=document.activeElement;previousOverflow=document.body.style.overflow;modal.classList.remove(\'hidden\');modal.classList.add(\'flex\');document.body.style.overflow=\'hidden\';var f=focusables()[0];if(f)setTimeout(function(){f.focus();},60);}'
        . 'function closeM(){modal.classList.add(\'hidden\');modal.classList.remove(\'flex\');document.body.style.overflow=previousOverflow;if(lastFocus&&typeof lastFocus.focus===\'function\')lastFocus.focus();}'
        . 'fab.addEventListener(\'click\',openM);modal.querySelectorAll(\'[data-error-modal-close]\').forEach(function(el){el.addEventListener(\'click\',closeM);});'
        . 'document.addEventListener(\'keydown\',function(e){if(modal.classList.contains(\'hidden\'))return;if(e.key===\'Escape\'){e.preventDefault();closeM();return;}if(e.key===\'Tab\'){var fs=focusables();if(!fs.length)return;var first=fs[0],last=fs[fs.length-1];if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus();}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus();}}});'
        . '})();'
        . '</script>';
    return $html;
}

/** ذخیره آیتم‌های منوی هدر */
function save_header_menu_items(array $items): void
{
    $clean = [];
    foreach ($items as $i) {
        $label = trim((string) ($i['label'] ?? ''));
        if ($label === '') continue;
        $clean[] = [
            'label' => mb_substr($label, 0, 60),
            'url'   => safe_navigation_url((string) ($i['url'] ?? '#')),
            'target'=> ((string) ($i['target'] ?? '')) === 'blank' ? '_blank' : '_self',
        ];
    }
    set_setting('header_menu', json_encode($clean, JSON_UNESCAPED_UNICODE));
}

// ---------------------------------------------------------------------------
// تاریخ شمسی (جلالی)
// ---------------------------------------------------------------------------

/** تبدیل تاریخ میلادی به اجزای شمسی (الگوریتم استاندارد جلالی) */
function jalali_parts(int $gy, int $gm, int $gd): array
{
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $jy = ($gy <= 1600) ? 0 : 979;
    $gy -= ($gy <= 1600) ? 621 : 1600;
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) - 80 + $gd + $g_d_m[$gm - 1];

    $jy += 33 * intdiv($days, 12053);
    $days %= 12053;
    $jy += 4 * intdiv($days, 1461);
    $days %= 1461;
    $jy += intdiv($days - 1, 365);
    if ($days > 365) {
        $days = ($days - 1) % 365;
    }

    if ($days < 186) {
        $jm = 1 + intdiv($days, 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + intdiv($days - 186, 30);
        $jd = 1 + (($days - 186) % 30);
    }

    return [(int) $jy, (int) $jm, (int) $jd];
}

/**
 * تبدیل تاریخ شمسی به میلادی (رشته Y-m-d) — با جستجوی عددی روی jalali_parts()
 * ورودی: 1404/05/20 یا 1404-05-20 — خروجی: 2025-08-11 یا null
 */
function jalali_to_gregorian_str(string $jalali): ?string
{
    if (!preg_match('#^(\d{4})[/\-](\d{1,2})[/\-](\d{1,2})$#', trim($jalali), $m)) {
        return null;
    }
    [$jy, $jm, $jd] = [(int) $m[1], (int) $m[2], (int) $m[3]];
    if ($jy < 1200 || $jy > 1500 || $jm < 1 || $jm > 12 || $jd < 1 || $jd > 31) {
        return null;
    }
    // شروع تقریبی: اول فروردین ≈ ۲۱ مارس
    $base = mktime(0, 0, 0, 3, 21, $jy + 621) - 5 * 86400;
    for ($i = -400; $i <= 400; $i++) {
        $t = $base + $i * 86400;
        [$py, $pm, $pd] = jalali_parts((int) date('Y', $t), (int) date('n', $t), (int) date('j', $t));
        if ($py === $jy && $pm === $jm && $pd === $jd) {
            return date('Y-m-d', $t);
        }
    }
    return null;
}

/**
 * نرمال‌سازی تاریخ ورودی فرم → میلادی Y-m-d (اگر شمسی بود تبدیل می‌کند)
 * ورودی‌های از-قبل-میلادی یا غیرقابل‌تبدیل دست‌نخورده برمی‌گردند.
 */
function portal_date_to_db(string $value): string
{
    $v = trim($value);
    if ($v === '') {
        return '';
    }
    if (preg_match('#^\d{4}-\d{2}-\d{2}$#', $v)) {
        return $v; // از قبل میلادی
    }
    $c = jalali_to_gregorian_str($v);
    return $c ?? $v;
}

/** نمایش تاریخ ذخیره‌شده (میلادی) به شمسی — اگر قبلاً شمسی بود همان را برمی‌گرداند */
function portal_date_to_display(string $value): string
{
    $v = trim($value);
    if ($v === '') {
        return '';
    }
    if (preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', $v, $m)) {
        [$jy, $jm, $jd] = jalali_parts((int) $m[1], (int) $m[2], (int) $m[3]);
        return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
    }
    return $v;
}

/** نام ماه‌های شمسی */
function jalali_months(): array
{
    return [1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان', 9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند'];
}

/**
 * نمایش تاریخ/زمان میلادی دیتابیس به‌صورت شمسی
 * مثال: fa_datetime("2026-08-07 12:30:00") => "1405/05/16 12:30"
 */
function fa_datetime(?string $datetime): string
{
    if (!$datetime) {
        return '-';
    }
    $ts = strtotime($datetime);
    if (!$ts) {
        return '-';
    }
    $g = getdate($ts);
    [$jy, $jm, $jd] = jalali_parts((int) $g['year'], (int) $g['mon'], (int) $g['mday']);
    return sprintf('%d/%02d/%02d %02d:%02d', $jy, $jm, $jd, (int) $g['hours'], (int) $g['minutes']);
}

/** نمایش فقط تاریخ به‌صورت شمسی (بدون ساعت) */
function fa_date(?string $datetime): string
{
    if (!$datetime) {
        return '-';
    }
    $ts = strtotime($datetime);
    if (!$ts) {
        return '-';
    }
    $g = getdate($ts);
    [$jy, $jm, $jd] = jalali_parts((int) $g['year'], (int) $g['mon'], (int) $g['mday']);
    return sprintf('%d/%02d/%02d', $jy, $jm, $jd);
}

// ---------------------------------------------------------------------------
// دیت پیکر شمسی (JalaliDatePicker — majidh1/JalaliDatePicker، لایسنس MIT)
// فایل‌ها به‌صورت محلی در assets/jalalidatepicker/ نگهداری می‌شوند.
// ---------------------------------------------------------------------------

/** عمق مسیر نسبی از پوشه جاری تا ریشه پروژه (برای صفحات admin/ و customer/) */
function datepicker_assets_base(): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = rtrim(dirname($script), '/');
    // اگر صفحه داخل admin/ یا customer/ باشد (حتی در زیرپوشه‌هایی مثل /portal3/admin)
    if (preg_match('#/(admin|customer)$#', $dir)) {
        return '../';
    }
    return '';
}

/** لینک CSS دیت‌پیکر local — asset ناقص باید در deploy به‌صورت صریح گزارش شود. */
function datepicker_assets_css(): string
{
    $local = datepicker_assets_base() . 'assets/jalalidatepicker/jalalidatepicker.min.css';
    return '<link rel="stylesheet" href="' . e($local) . '">' . "\n";
}

/**
 * اسکریپت فعال‌سازی دیت‌پیکر local — asset ناقص باید در deploy به‌صورت صریح گزارش شود.
 * هر input با ویژگی data-jdp خودکار به دیت‌پیکر شمسی تبدیل می‌شود.
 */
function datepicker_assets_js(): string
{
    $local = datepicker_assets_base() . 'assets/jalalidatepicker/jalalidatepicker.min.js';
    return
        '<script src="' . e($local) . '"></script>' . "\n" .
        '<script nonce="' . e(portal_csp_nonce()) . '">' . "\n" .
        'window.__initJdp = function(){' . "\n" .
        '  if (typeof jalaliDatepicker === "undefined") { console.warn("[Portal] jalaliDatepicker بارگذاری نشد — پوشه assets/jalalidatepicker را آپلود کنید."); return; }' . "\n" .
        '  jalaliDatepicker.startWatch({' . "\n" .
        '    useDropDownYears: true,' . "\n" .
        '    showTodayBtn: true,' . "\n" .
        '    showEmptyBtn: true,' . "\n" .
        '    showCloseBtn: true,' . "\n" .
        '    autoHide: true,' . "\n" .
        '    hideAfterChange: true,' . "\n" .
        '      changeMonthRotateYear: true,' . "\n" .
        '      persianDigits: false,' . "\n" .
        '      separatorChars: { date: "/", between: " ", time: ":" }' . "\n" .
        '    });' . "\n" .
        '    // دکمه‌های «انتخاب تاریخ» — باز کردن دیت پیکر برای فیلد هدف' . "\n" .
        '    document.querySelectorAll(".jdp-trigger").forEach(function(btn){' . "\n" .
        '      btn.addEventListener("click", function(ev){' . "\n" .
        '        ev.preventDefault();' . "\n" .
        '        var target = document.getElementById(btn.getAttribute("data-target"));' . "\n" .
        '        if (target) { jalaliDatepicker.show(target); }' . "\n" .
        '      });' . "\n" .
        '    });' . "\n" .
        '};' . "\n" .
        'document.addEventListener("DOMContentLoaded", window.__initJdp);' . "\n" .
        '</script>' . "\n";
}

// ---------------------------------------------------------------------------
// حفاظت CSRF
// ---------------------------------------------------------------------------

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): bool
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return true;
    }
    $sent = $_POST['csrf_token'] ?? '';
    return is_string($sent) && hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $sent);
}

function require_valid_csrf(): void
{
    if (!verify_csrf()) {
        http_response_code(419);
        exit('درخواست نامعتبر یا منقضی شده است. صفحه را بازخوانی کرده و دوباره تلاش کنید.');
    }
}

// ---------------------------------------------------------------------------
// رندر قالب‌های مشترک (Layout)
// ---------------------------------------------------------------------------
// هر تابع، متغیرهای محلی خود (title, mainClass, ...) را به فایل قالب پاس می‌دهد.

// ---------------------------------------------------------------------------
// Design System Assets (FULLMASTER) — CSS و اسکریپت دارک‌مود و skip-link
// ---------------------------------------------------------------------------

/** عمق مسیر به ریشه پروژه (برای admin/customer) — با همان منطق datepicker */
function ui_base_path(): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = rtrim(dirname($script), '/');
    if (preg_match('#/(admin|customer)$#', $dir)) {
        return '../';
    }
    return '';
}

/** آدرس asset محلی با نسخهٔ مبتنی بر mtime برای invalidation کش پس از انتشار */
function portal_asset_href(string $relative_path): string
{
    $relative_path = ltrim($relative_path, '/');
    $url = ui_base_path() . $relative_path;
    $file = dirname(__DIR__, 2) . '/' . $relative_path;

    if (is_file($file)) {
        $url .= '?v=' . rawurlencode((string) filemtime($file));
    }

    return $url;
}

/** nonce یکتا برای هر پاسخ؛ برای مجازکردن scriptهای inline trusted در CSP استفاده می‌شود. */
function portal_csp_nonce(): string
{
    static $nonce = null;
    if ($nonce === null) {
        $nonce = base64_encode(random_bytes(18));
    }
    return $nonce;
}

/** preload و stylesheet فونت محلی Vazirmatn؛ نام فایل نسخه‌دار از cache پایدار پشتیبانی می‌کند. */
function portal_font_css_link(): string
{
    $base = ui_base_path();
    $font = e($base . 'assets/fonts/Vazirmatn-v33.003-wght.woff2');
    $css = e(portal_asset_href('assets/fonts/vazirmatn.css'));

    return '<link rel="preload" href="' . $font . '" as="font" type="font/woff2" crossorigin>' . "\n"
        . '<link rel="stylesheet" href="' . $css . '">' . "\n";
}

/** لینک CSS سیستم طراحی (portal-ui.css) — در <head> همه قالب‌ها */
function portal_ui_css_link(): string
{
    return '<link rel="stylesheet" href="' . e(portal_asset_href('assets/portal-ui.css')) . '">' . "\n";
}

/** اسکریپت اولیه‌سازی دارک‌مود (قبل از رندر — جلوگیری از FOUC) */
function portal_darkmode_init(): string
{
    return '<script nonce="' . e(portal_csp_nonce()) . '">' . "\n"
        . '(function(){var d=localStorage.getItem("portal-theme");if(d==="dark"||(d==="auto"&&window.matchMedia("(prefers-color-scheme: dark)").matches)){document.documentElement.classList.add("dark");}})();'
        . "\n" . '</script>' . "\n";
}

/** لینک Skip-link «پرش به محتوای اصلی» — اولین عنصر focusable */
function portal_skip_link(): string
{
    return '<a href="#main-content" class="skip-link">پرش به محتوای اصلی</a>';
}

/** دکمه toggle دارک‌مود (در نوار بالای پنل‌ها) */
function portal_theme_toggle(): string
{
    return '<button type="button" id="theme-toggle" aria-label="تغییر حالت روشن/تاریک" title="تغییر حالت روشن/تاریک" class="btn btn-icon btn-ghost !w-10 !h-10">'
        . '<span class="theme-ic-moon">' . icon('moon') . '</span>'
        . '<span class="theme-ic-sun">' . icon('sun') . '</span>'
        . '</button>';
}

/** اسکریپت رفتار toggle دارک‌مود */
function portal_darkmode_script(): string
{
    return '<script nonce="' . e(portal_csp_nonce()) . '">' . "\n"
        . 'document.addEventListener("DOMContentLoaded",function(){var t=document.getElementById("theme-toggle");if(!t)return;'
        . 'var cur=localStorage.getItem("portal-theme")||"auto";'
        . 't.addEventListener("click",function(){var next=document.documentElement.classList.contains("dark")?"light":"dark";'
        . 'document.documentElement.classList.toggle("dark",next==="dark");localStorage.setItem("portal-theme",next);});});'
        . "\n" . '</script>' . "\n";
}

// ---------------------------------------------------------------------------
// مودال تأیید اکشن مخرب (جایگزین confirm() بومی — دسترس‌پذیر)
// ---------------------------------------------------------------------------

/** مارک‌آپ مودال تأیید اکشن — باید قبل از </body> رندر شود */
function portal_confirm_modal(): string
{
    return '<div id="portal-confirm" role="dialog" aria-modal="true" aria-labelledby="portal-confirm-title" aria-describedby="portal-confirm-msg" class="hidden fixed inset-0 z-[2000] items-center justify-center p-4">'
        . '<div class="absolute inset-0 bg-black/50" data-confirm-close></div>'
        . '<div class="relative w-full max-w-md card !rounded-2xl p-6 shadow-2xl" tabindex="-1">'
        . '<div class="flex items-start gap-3">'
        . '<span class="w-11 h-11 rounded-xl bg-red-50 text-red-600 flex items-center justify-center flex-shrink-0">' . icon('alert', 'w-5 h-5') . '</span>'
        . '<div class="min-w-0">'
        . '<h3 id="portal-confirm-title" class="text-lg font-bold text-slate-900">تأیید حذف</h3>'
        . '<p id="portal-confirm-msg" class="text-sm text-slate-600 mt-1.5 leading-relaxed">این عملیات قابل بازگشت نیست.</p>'
        . '</div></div>'
        . '<div class="flex justify-end gap-3 mt-6">'
        . '<button type="button" class="btn btn-secondary" data-confirm-close>انصراف</button>'
        . '<button type="button" class="btn btn-danger" data-confirm-ok>حذف</button>'
        . '</div></div></div>';
}

/** اسکریپت رفتار مودال تأیید — فرم‌هایی با data-confirm-msg را امن می‌کند */
function portal_confirm_script(): string
{
    return '<script nonce="' . e(portal_csp_nonce()) . '">' . "\n"
        . 'document.addEventListener("DOMContentLoaded",function(){'
        . 'var m=document.getElementById("portal-confirm");if(!m)return;'
        . 'var ok=m.querySelector("[data-confirm-ok]"),pending=null;'
        . 'function open(msg,f){document.getElementById("portal-confirm-title").textContent=f.getAttribute("data-confirm-title")||"تأیید حذف";document.getElementById("portal-confirm-msg").textContent=msg;ok.textContent=f.getAttribute("data-confirm-ok-label")||"حذف";ok.className="btn "+((f.getAttribute("data-confirm-tone")||"danger")==="primary"?"btn-primary":"btn-danger");pending=f;m.classList.remove("hidden");m.classList.add("flex");ok.focus();}'
        . 'function close(){m.classList.add("hidden");m.classList.remove("flex");pending=null;}'
        . 'm.querySelectorAll("[data-confirm-close]").forEach(function(e){e.addEventListener("click",close);});'
        . 'ok.addEventListener("click",function(){if(!pending)return;var f=pending;pending=null;close();f.dataset.confirmed="1";if(typeof f.requestSubmit==="function"){f.requestSubmit();}else{f.submit();}delete f.dataset.confirmed;});'
        . 'document.addEventListener("keydown",function(e){if(e.key==="Escape")close();});'
        . 'document.querySelectorAll("form[data-confirm-msg]").forEach(function(f){'
        . 'f.addEventListener("submit",function(e){if(f.dataset.confirmed==="1"){delete f.dataset.confirmed;return;}e.preventDefault();open(f.getAttribute("data-confirm-msg")||"آیا از انجام این عملیات اطمینان دارید؟",f);});});});'
        . "\n" . '</script>' . "\n";
}

/** Toast region (پایین صفحه) — برای بازخورد گذرا */
function portal_toast_region(): string
{
    return '<div class="toast-region" id="portal-toasts" aria-live="polite" aria-atomic="false"></div>';
}

/**
 * اسکریپت توست — window.portalToast(message, type)
 * type: success | danger | info
 */
function portal_toast_script(): string
{
    $icons = json_encode([
        'success' => icon('check', 'w-4 h-4'),
        'danger'  => icon('alert', 'w-4 h-4'),
        'info'    => icon('info', 'w-4 h-4'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return '<script nonce="' . e(portal_csp_nonce()) . '">' . "\n"
        . 'window.portalToast=function(msg,type){'
        . 'var r=document.getElementById("portal-toasts");if(!r)return;'
        . 'var ics=' . $icons . ';'
        . 'var t=document.createElement("div");t.className="toast toast-"+(type||"info");'
        . 't.innerHTML="<span class=\\"toast-ic\\">"+(ics[type]||ics.info)+"</span><span></span>";'
        . 't.lastChild.textContent=msg;r.appendChild(t);'
        . 'setTimeout(function(){t.style.transition="opacity .3s";t.style.opacity="0";setTimeout(function(){t.remove();},320);},3200);};'
        . "\n" . '</script>' . "\n";
}

/**
 * اسکریپت اعتبارسنجی inline فرم (بدون تغییر منطق سمت سرور).
 * فرم‌هایی که novalidate دارند بررسی می‌شوند؛ فیلدهای required پیام فارسی + aria-invalid
 * و خطای متمرکز در بالای فرم دریافت می‌کنند. لینک به اولین خطا هم ساخته می‌شود.
 */
function portal_validation_script(): string
{
    return '<script nonce="' . e(portal_csp_nonce()) . '">' . "\n"
        . 'document.addEventListener("DOMContentLoaded",function(){'
        . 'document.querySelectorAll("form[novalidate]").forEach(function(form){'
        . 'form.addEventListener("submit",function(e){'
        . 'var errs=[];form.querySelectorAll("[required]").forEach(function(inp){'
        . 'var label=form.querySelector("label[for=\'"+inp.id+"\']");var name=(label?label.textContent.trim():(inp.name||""));'
        . 'var wrap=inp.closest("div");var fe=wrap?wrap.querySelector(".field-error"):null;'
        . 'var ok = inp.type==="checkbox"||inp.type==="radio" ? inp.checked : (inp.value&&inp.value.trim()!=="");'
        . 'inp.classList.toggle("input-error",!ok);inp.setAttribute("aria-invalid",ok?"false":"true");'
        . 'if(fe){fe.textContent=ok?"":"لطفاً «"+name+"» را وارد کنید.";fe.style.display=ok?"none":"block";}'
        . 'if(!ok)errs.push({name:name,id:inp.id});});'
        . 'if(errs.length){e.preventDefault();'
        . 'var sum=form.querySelector(".form-error-summary");'
        . 'if(sum){var ul=sum.querySelector("ul")||document.createElement("ul");ul.innerHTML="";'
        . 'errs.forEach(function(err){var li=document.createElement("li");var a=document.createElement("a");a.href="#"+err.id;a.textContent=err.name;li.appendChild(a);ul.appendChild(li);});'
        . 'if(!sum.querySelector("ul")){sum.appendChild(ul);}sum.style.display="flex";}'
        . 'var first=form.querySelector(".input-error");if(first)first.focus();}'
        . 'else{var sum=form.querySelector(".form-error-summary");if(sum)sum.style.display="none";}'
        . '});});});'
        . "\n" . '</script>' . "\n";
}

/**
 * آیا آموزش onboarding برای مدیر فعلی نمایش داده شود؟
 * فقط مدیرانی که قبلاً آموزش را ندیده‌اند (first login) آموزش می‌بینند.
 */
function portal_onboarding_should_show(): bool
{
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        return false;
    }
    if (!in_array($_SESSION['role'], ['admin', 'super_admin'], true)) {
        return false;
    }
    // بررسی فلگ در user meta (settings با کلید اختصاصی کاربر)
    $dismissed = get_setting('onboarding_dismissed_' . (int) $_SESSION['user_id'], '0');
    return $dismissed !== '1';
}

/** علامت‌گذاری آموزش به‌عنوان دیده‌شده/ردشده برای مدیر فعلی */
function portal_onboarding_dismiss(): void
{
    if (isset($_SESSION['user_id'])) {
        set_setting('onboarding_dismissed_' . (int) $_SESSION['user_id'], '1');
    }
}

/** بازنشانی وضعیت آموزش (برای نمایش مجدد) */
function portal_onboarding_reset(): void
{
    if (isset($_SESSION['user_id'])) {
        set_setting('onboarding_dismissed_' . (int) $_SESSION['user_id'], '0');
    }
}

/** لینک فایل onboarding.js با cache-busting */
function portal_onboarding_js(): string
{
    return '<script src="' . e(portal_asset_href('assets/onboarding.js')) . '"></script>' . "\n";
}

/** اسکریپت راه‌اندازی آموزش (auto-start + dismiss URL + CSRF) */
function portal_onboarding_init(): string
{
    $autoStart = portal_onboarding_should_show() ? 'auto' : 'manual';
    $dismissUrl = ui_base_path() . 'profile.php';
    $csrf = csrf_token();
    $vars = json_encode([
        'dismissUrl' => $dismissUrl,
        'csrf' => $csrf,
    ]);
    return '<script nonce="' . e(portal_csp_nonce()) . '">' . "\n"
        . 'var portalOnboardingDismissUrl=' . json_encode($dismissUrl) . ';' . "\n"
        . 'var portalOnboardingCsrf=' . json_encode($csrf) . ';' . "\n"
        . 'document.documentElement.setAttribute("data-portal-onboarding","' . $autoStart . '");' . "\n"
        . '</script>' . "\n";
}

/**
 * قالب مشترک صفحات پنل مدیریت
 *
 * @param string $title          عنوان صفحه (در <title> و نوار بالا)
 * @param string $mainClass      کلاس‌های تگ <main>
 * @param string $extraStyles    استایل‌های اختصاصی صفحه (اختیاری)
 * @param string $topbarActions  محتوای اضافه سمت راست نوار بالا (اختیاری)
 */
function render_admin_header(
    string $title,
    string $mainClass = 'p-8 max-w-7xl w-full mx-auto space-y-6',
    string $extraStyles = '',
    string $topbarActions = ''
): void {
    include __DIR__ . '/../layout/admin_header.php';
}

function render_admin_footer(): void
{
    include __DIR__ . '/../layout/admin_footer.php';
}

/**
 * قالب مشترک صفحات پنل مشتری
 *
 * @param string $title          عنوان صفحه (در <title> و نوار بالا)
 * @param string $mainClass      کلاس‌های تگ <main>
 * @param string $extraStyles    استایل‌های اختصاصی صفحه (اختیاری)
 * @param string $topbarActions  محتوای اضافه سمت راست نوار بالا (اختیاری)
 * @param string $topbarUser     نام نمایشی کاربر در نوار بالا (اختیاری؛ در صورت
 *                               خالی بودن از اطلاعات سشن خوانده می‌شود)
 * @param string $mobileTitle    عنوان کوتاهِ اختیاری برای جلوگیری از شکست ناخواسته در نوار موبایل
 */
function render_customer_header(
    string $title,
    string $mainClass = 'p-8 max-w-7xl w-full mx-auto space-y-6',
    string $extraStyles = '',
    string $topbarActions = '',
    string $topbarUser = '',
    string $mobileTitle = ''
): void {
    include __DIR__ . '/../layout/customer_header.php';
}

function render_customer_footer(): void
{
    include __DIR__ . '/../layout/customer_footer.php';
}

/**
 * قالب مشترک صفحات عمومی (مثل صفحه ورود)
 *
 * @param string $title       عنوان صفحه
 * @param string $bodyClass   کلاس‌های تگ <body>
 * @param string $extraStyles استایل‌های اختصاصی صفحه (اختیاری)
 */
function render_public_header(
    string $title,
    string $bodyClass = 'bg-slate-50 text-slate-800 min-h-screen',
    string $extraStyles = ''
): void {
    include __DIR__ . '/../layout/public_header.php';
}

function render_public_footer(): void
{
    include __DIR__ . '/../layout/public_footer.php';
}
