<?php
// admin/settings.php - تنظیمات سیستم (تب‌بندی: ماژول‌ها، فیلدهای اجباری، داشبورد کاربر، عمومی)
require_once 'auth.php';
if (!admin_can('settings')) { header('Location: index.php'); exit; }

$success = '';
$err = '';

// ---------- پردازش فرم‌ها ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $save_type = $_POST['save_type'] ?? '';

    if ($save_type === 'modules') {
        $modules = ['projects', 'products', 'invoices', 'tickets', 'surveys', 'custom_fields', 'notifications', 'logs', 'error_reports'];
        foreach ($modules as $mod) {
            set_setting('module_' . $mod, isset($_POST['module_' . $mod]) ? '1' : '0');
        }
        log_activity($_SESSION['user_id'], "بروزرسانی وضعیت فعال‌سازی ماژول‌های سیستم");
        $success = 'وضعیت ماژول‌های سیستم با موفقیت بروزرسانی شد.';
    } elseif ($save_type === 'mandatory') {
        $fields = ['first_name', 'last_name', 'mobile', 'company_name', 'job_title', 'birth_date', 'gender'];
        foreach ($fields as $f) {
            set_setting('req_' . $f, isset($_POST['req_' . $f]) ? '1' : '0');
        }
        log_activity($_SESSION['user_id'], "بروزرسانی تنظیمات فیلدهای اجباری پروفایل");
        $success = 'تنظیمات فیلدهای اجباری با موفقیت ذخیره شد.';
    } elseif ($save_type === 'dashboard') {
        $widgets = [
            'dash_projects', 'dash_products', 'dash_invoices', 'dash_tickets', 'dash_surveys', 'dash_notifications',
            'dash_recent_projects', 'dash_recent_products', 'dash_recent_notifications', 'dash_survey_banner',
            'dash_quick_access', 'dash_welcome', 'dash_profile_banner',
        ];
        foreach ($widgets as $w) {
            set_setting($w, isset($_POST[$w]) ? '1' : '0');
        }
        log_activity($_SESSION['user_id'], "بروزرسانی ویجت‌های داشبورد کاربر");
        $success = 'تنظیمات داشبورد کاربر با موفقیت ذخیره شد.';
    } elseif ($save_type === 'general') {
        set_setting('site_title', trim($_POST['site_title'] ?? 'پورتال مشتریان'));
        set_setting('footer_text', trim($_POST['footer_text'] ?? ''));
        set_setting('login_subtitle', trim($_POST['login_subtitle'] ?? ''));

        // ---- طرح صفحه ورود ----
        $login_layouts = array_keys(login_layout_options());
        $ll = $_POST['login_layout'] ?? 'centered';
        set_setting('login_layout', in_array($ll, $login_layouts, true) ? $ll : 'centered');

        // ---- تنظیمات طرح دوطرفه ----
        set_setting('split_ratio', (string) max(40, min(75, (int) ($_POST['split_ratio'] ?? 70))));
        set_setting('split_side', ($_POST['split_side'] ?? 'right') === 'left' ? 'left' : 'right');
        set_setting('split_vertical', in_array($_POST['split_vertical'] ?? 'center', ['top', 'center', 'bottom'], true) ? $_POST['split_vertical'] : 'center');
        set_setting('split_title', trim($_POST['split_title'] ?? 'پورتال هوشمند مشتریان'));
        set_setting('split_subtitle', trim($_POST['split_subtitle'] ?? ''));
        foreach ([1, 2, 3] as $i) {
            set_setting('split_feature' . $i, trim($_POST['split_feature' . $i] ?? ''));
            set_setting('split_feature' . $i . '_l', trim($_POST['split_feature' . $i . '_l'] ?? ''));
        }
        // آپلود تصویر دوطرفه (دسکتاپ)
        $up = upload_login_image($_FILES['split_image'] ?? [], 'split');
        if ($up !== '') { set_setting('split_image', $up); }
        if (isset($_POST['remove_split_image']) && $_POST['remove_split_image'] === '1') { set_setting('split_image', ''); }
        $upm = upload_login_image($_FILES['split_mobile_image'] ?? [], 'split_mob');
        if ($upm !== '') { set_setting('split_mobile_image', $upm); }
        if (isset($_POST['remove_split_mobile_image']) && $_POST['remove_split_mobile_image'] === '1') { set_setting('split_mobile_image', ''); }

        // ---- تنظیمات طرح گرادیان برند ----
        set_setting('branded_from', sanitize_hex_color($_POST['branded_from'] ?? '#4f46e5'));
        set_setting('branded_to', sanitize_hex_color($_POST['branded_to'] ?? '#7c3aed'));
        $upb = upload_login_image($_FILES['branded_mobile_image'] ?? [], 'branded_mob');
        if ($upb !== '') { set_setting('branded_mobile_image', $upb); }
        if (isset($_POST['remove_branded_mobile_image']) && $_POST['remove_branded_mobile_image'] === '1') { set_setting('branded_mobile_image', ''); }

        // ---- تصویر پس‌زمینه دلخواه (همه طرح‌ها + موبایل) ----
        $upbg = upload_login_image($_FILES['login_bg_image'] ?? [], 'loginbg');
        if ($upbg !== '') { set_setting('login_bg_image', $upbg); }
        if (isset($_POST['remove_login_bg_image']) && $_POST['remove_login_bg_image'] === '1') { set_setting('login_bg_image', ''); }
        $upb2 = upload_login_image($_FILES['login_bg_mobile_image'] ?? [], 'loginbg_mob');
        if ($upb2 !== '') { set_setting('login_bg_mobile_image', $upb2); }
        if (isset($_POST['remove_login_bg_mobile_image']) && $_POST['remove_login_bg_mobile_image'] === '1') { set_setting('login_bg_mobile_image', ''); }

        // ---- منوی سفارشی هدر ----
        $menu_items = [];
        if (!empty($_POST['menu_label']) && is_array($_POST['menu_label'])) {
            foreach ($_POST['menu_label'] as $idx => $label) {
                $menu_items[] = [
                    'label'  => $label,
                    'url'    => $_POST['menu_url'][$idx] ?? '#',
                    'target' => $_POST['menu_target'][$idx] ?? '',
                ];
            }
        }
        save_header_menu_items($menu_items);
        set_setting('header_menu_align', in_array($_POST['header_menu_align'] ?? 'start', ['start', 'center', 'end'], true) ? $_POST['header_menu_align'] : 'start');

        log_activity($_SESSION['user_id'], "بروزرسانی تنظیمات عمومی و صفحه ورود");
        $success = 'تنظیمات عمومی با موفقیت ذخیره شد.';
    } elseif ($save_type === 'login_sms') {
        // --- روش ورود ---
        $lm = $_POST['login_method'] ?? 'username';
        set_setting('login_method', in_array($lm, ['username', 'mobile'], true) ? $lm : 'username');

        // --- تنظیمات پیامک ippanel ---
        set_setting('sms_api_key', trim($_POST['sms_api_key'] ?? ''));
        set_setting('sms_pattern', trim($_POST['sms_pattern'] ?? ''));
        set_setting('sms_pattern_var', trim($_POST['sms_pattern_var'] ?? 'code'));
        set_setting('sms_from_number', trim($_POST['sms_from_number'] ?? ''));
        set_setting('sms_ssl_verify', isset($_POST['sms_ssl_verify_off']) ? '0' : '1');
        set_setting('site_url', rtrim(trim($_POST['site_url'] ?? ''), '/'));

        // --- طول کد تایید (۴/۵/۶) ---
        $otp_len = (int) ($_POST['otp_length'] ?? 6);
        set_setting('otp_length', in_array($otp_len, [6, 7, 8], true) ? (string) $otp_len : '6');

        log_activity($_SESSION['user_id'], "بروزرسانی تنظیمات ورود و پیامک");
        $success = 'تنظیمات ورود و پیامک با موفقیت ذخیره شد.';
    } elseif ($save_type === 'sms_events') {
        // ذخیره تنظیمات هر رویداد پیامکی (فعال/غیرفعال + پترن + نگاشت متغیرها)
        $events = $pdo->query("SELECT event_key FROM sms_events")->fetchAll();
        foreach ($events as $ev) {
            $k = $ev['event_key'];
            $is_on = isset($_POST['event_' . $k]) ? 1 : 0;
            $code  = trim($_POST['pattern_' . $k] ?? '');

            // نگاشت متغیرها: برای هر متغیر سیستم، نام معادل در پترن
            $avail = sms_event_list()[$k]['vars'] ?? '';
            $mappings = [];
            foreach (array_values(array_filter(array_map('trim', explode(',', $avail)))) as $vname) {
                if (isset($_POST['vars_' . $k]) && in_array($vname, (array) $_POST['vars_' . $k], true)) {
                    $pat_name = trim($_POST['var_map_' . $k . '_' . $vname] ?? '');
                    $mappings[] = $vname . '=' . ($pat_name !== '' ? $pat_name : $vname);
                }
            }
            $vars_str = implode(',', $mappings);

            $upd = $pdo->prepare("UPDATE sms_events SET is_active = ?, pattern_code = ?, pattern_var = '', pattern_vars = ? WHERE event_key = ?");
            $upd->execute([$is_on, $code, $vars_str, $k]);
        }
        log_activity($_SESSION['user_id'], "بروزرسانی رویدادهای پیامکی");
        $success = 'تنظیمات رویدادهای پیامکی با موفقیت ذخیره شد.';
    } elseif ($save_type === 'survey_reminder') {
        // --- تنظیمات یادآوری خودکار نظرسنجی (کرون‌جاب) ---
        set_setting('survey_reminder_days', (string) max(1, (int) ($_POST['survey_reminder_days'] ?? 3)));
        set_setting('survey_reminder_interval', (string) max(0, (int) ($_POST['survey_reminder_interval'] ?? 7)));
        set_setting('survey_reminder_max', (string) max(1, (int) ($_POST['survey_reminder_max'] ?? 3)));
        log_activity($_SESSION['user_id'], "بروزرسانی تنظیمات یادآوری خودکار نظرسنجی");
        $success = 'تنظیمات یادآوری خودکار نظرسنجی با موفقیت ذخیره شد.';
    } elseif ($save_type === 'sms_send_manual') {
        // ارسال دستی پیامک به یک یا چند مشتری خاص
        $mobile   = fa_digits_to_en(trim($_POST['manual_mobile'] ?? ''));
        $event_key = $_POST['manual_event'] ?? '';
        $custom_value = trim($_POST['manual_value'] ?? '');
        $user_ids = $_POST['manual_user_ids'] ?? [];
        if (!is_array($user_ids)) { $user_ids = [$user_ids]; }

        $sent_count = 0;
        if ($mobile !== '') {
            // ارسال به یک شماره مشخص — اگر مقدار دلخواه داده شده، آن را به همه متغیرهای انتخابی اضافه کن
            $vars = $custom_value !== '' ? ['value' => $custom_value] : [];
            $r = send_event_sms($event_key, $mobile, $vars);
            if ($r['ok']) { $sent_count++; } else { $err = $r['message']; }
        }
        foreach ($user_ids as $uid) {
            $uid = (int) $uid;
            if ($uid <= 0) continue;
            $q = $pdo->prepare("SELECT mobile, first_name, last_name, username, company_name FROM users WHERE id = ?");
            $q->execute([$uid]);
            $u = $q->fetch();
            if (!$u || empty($u['mobile'])) continue;

            if ($event_key === 'survey_reminder') {
                // برای یادآوری نظرسنجی، یادآوری کامل با لینک عمومی و شمارنده ارسال می‌شود
                if (sms_trigger_survey_reminder($uid)) {
                    $sent_count++;
                }
                continue;
            }

            $vars = [
                'first_name' => $u['first_name'] ?: $u['username'],
                'last_name'  => $u['last_name'],
                'username'   => $u['username'],
                'company_name' => $u['company_name'] ?? '',
            ];
            if ($custom_value !== '') { $vars['value'] = $custom_value; }
            $r = send_event_sms($event_key, $u['mobile'], $vars, $uid);
            if ($r['ok']) { $sent_count++; }
        }
        if ($sent_count > 0) {
            $success = "پیامک به {$sent_count} گیرنده ارسال شد.";
            log_activity($_SESSION['user_id'], "ارسال دستی پیامک (رویداد: {$event_key}) به {$sent_count} گیرنده");
        } elseif (!$err) {
            $err = 'گیرنده‌ای برای ارسال انتخاب نشده یا شماره نامعتبر است.';
        }
    } elseif ($save_type === 'sms_test') {
        // --- ارسال پیامک تست ---
        $test_mobile = trim($_POST['test_mobile'] ?? '');
        if ($test_mobile === '') {
            $err = 'شماره موبایل برای ارسال تست وارد کنید.';
        } else {
            $result = send_sms_via_ippanel($test_mobile, '123456');
            if ($result['ok']) {
                $success = 'پیامک تست ارسال شد. ' . $result['message'];
            } else {
                $err = 'ارسال تست ناموفق: ' . $result['message'];
            }
        }
    } elseif ($save_type === 'cache') {
        set_setting('cache_enabled', isset($_POST['cache_enabled']) ? '1' : '0');
        $ttl = max(5, min(86400, (int) ($_POST['cache_ttl'] ?? 300)));
        set_setting('cache_ttl', (string) $ttl);
        portal_cache_flush();
        log_activity($_SESSION['user_id'], "بروزرسانی تنظیمات کش");
        $success = 'تنظیمات کش با موفقیت ذخیره و کش پاک‌سازی شد.';
    } elseif ($save_type === 'flush_cache') {
        $n = portal_cache_flush();
        log_activity($_SESSION['user_id'], "پاک‌سازی کش سیستم");
        $success = 'کش سیستم پاک‌سازی شد (' . $n . ' فایل حذف گردید).';
    } elseif ($save_type === 'cache') {
        set_setting('cache_enabled', isset($_POST['cache_enabled']) ? '1' : '0');
        $ttl = max(5, min(86400, (int) ($_POST['cache_ttl'] ?? 300)));
        set_setting('cache_ttl', (string) $ttl);
        portal_cache_flush();
        log_activity($_SESSION['user_id'], "بروزرسانی تنظیمات کش");
        $success = 'تنظیمات کش با موفقیت ذخیره و کش پاک‌سازی شد.';
    } elseif ($save_type === 'flush_cache') {
        $n = portal_cache_flush();
        log_activity($_SESSION['user_id'], "پاک‌سازی کش سیستم");
        $success = 'کش سیستم پاک‌سازی شد (' . $n . ' فایل حذف گردید).';
    } elseif ($save_type === 'appearance') {
        // --- پالت رنگی ---
        $palettes = array_keys(theme_palettes());
        $theme = $_POST['site_theme'] ?? 'indigo';
        if (!in_array($theme, $palettes, true)) {
            $theme = 'indigo';
        }
        set_setting('site_theme', $theme);

        // --- لوگو: یا آپلود فایل یا URL ---
        $logo_choice = $_POST['logo_choice'] ?? 'none';
        if (!in_array($logo_choice, ['none', 'upload', 'url'], true)) {
            $logo_choice = 'none';
        }
        set_setting('logo_choice', $logo_choice); // به‌خاطر سپردن انتخاب رادیو

        $current_logo_saved = get_setting('site_logo', '');
        if ($logo_choice === 'upload') {
            // اگر فایل جدیدی انتخاب شده → آپلود؛ وگرنه لوگوی قبلی حفظ می‌شود
            if (!empty($_FILES['site_logo_file']['name']) && $_FILES['site_logo_file']['error'] === UPLOAD_ERR_OK) {
                $f = $_FILES['site_logo_file'];
                $v = validate_upload_image($f, 2 * 1024 * 1024);
                if ($v === true) {
                    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                    $uploads_dir = __DIR__ . '/../uploads';
                    if (!is_dir($uploads_dir)) {
                        mkdir($uploads_dir, 0755, true);
                    }
                    $fname = 'logo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    if (move_uploaded_file($f['tmp_name'], $uploads_dir . '/' . $fname)) {
                        set_setting('site_logo', 'uploads/' . $fname);
                    } else {
                        $err = 'آپلود لوگو انجام نشد (دسترسی نوشتن در پوشه uploads را بررسی کنید).';
                    }
                } else {
                    $err = $v;
                }
            } elseif ($current_logo_saved === '') {
                $err = 'لطفا فایل لوگو را انتخاب کنید یا روش دیگری را برگزینید.';
            }
            // اگر فایل جدید نبود ولی لوگوی قبلی موجود بود → حفظ می‌شود
        } elseif ($logo_choice === 'url') {
            $url = trim($_POST['site_logo_url'] ?? '');
            set_setting('site_logo', $url);
        } elseif ($logo_choice === 'none') {
            set_setting('site_logo', '');
        }

        // --- استایل کارت محصولات و پروژه‌ها ---
        $card_styles = array_keys(entity_card_styles());
        $pc = $_POST['product_card_style'] ?? 'vertical';
        $pj = $_POST['project_card_style'] ?? 'vertical';
        set_setting('product_card_style', in_array($pc, $card_styles, true) ? $pc : 'vertical');
        set_setting('project_card_style', in_array($pj, $card_styles, true) ? $pj : 'vertical');

        // --- تصاویر پیش‌فرض محصولات و پروژه‌ها (fallback) ---
        foreach (['product', 'project'] as $etyp) {
            $field = 'default_' . $etyp . '_image';
            if (!empty($_FILES[$field]['name']) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
                $f = $_FILES[$field];
                $v = validate_upload_image($f, 4 * 1024 * 1024);
                if ($v === true) {
                    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                    $uploads_dir = __DIR__ . '/../uploads';
                    if (!is_dir($uploads_dir)) {
                        mkdir($uploads_dir, 0755, true);
                    }
                    $fname = 'default_' . $etyp . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    if (move_uploaded_file($f['tmp_name'], $uploads_dir . '/' . $fname)) {
                        set_setting($field, 'uploads/' . $fname);
                    } else {
                        $err = 'آپلود تصویر پیش‌فرض ' . ($etyp === 'product' ? 'محصول' : 'پروژه') . ' انجام نشد.';
                    }
                } else {
                    $err = $v;
                }
            } elseif (isset($_POST['remove_' . $etyp . '_default']) && $_POST['remove_' . $etyp . '_default'] === '1') {
                set_setting($field, '');
            }
        }

        if (!$err) {
            log_activity($_SESSION['user_id'], "بروزرسانی ظاهر سایت (پالت، لوگو و تصاویر پیش‌فرض)");
            $success = 'ظاهر سایت با موفقیت ذخیره شد.';
        }
    }
}

// ---------- تب فعال ----------
$tab = $_GET['tab'] ?? 'modules';

$fields_config = [
    'first_name'  => 'نام',
    'last_name'   => 'نام خانوادگی',
    'mobile'      => 'شماره موبایل',
    'company_name'=> 'نام شرکت',
    'job_title'   => 'سمت سازمانی',
    'birth_date'  => 'تاریخ تولد',
    'gender'      => 'جنسیت',
];

$modules_config = [
    'projects'      => ['مدیریت پروژه‌ها', icon('folder')],
    'products'      => ['مدیریت محصولات', icon('box')],
    'invoices'      => ['فاکتورها و صورتحساب‌های مالی', icon('card')],
    'tickets'       => ['تیکت‌های پشتیبانی مشتریان', icon('ticket')],
    'surveys'       => ['سیستم نظرسنجی حرفه‌ای', icon('star')],
    'custom_fields' => ['فیلدهای سفارشی پویا', icon('wrench')],
    'notifications' => ['اعلانات و اطلاع‌رسانی', icon('bell')],
    'logs'          => ['گزارش فعالیت‌ها', icon('file')],
    'error_reports' => ['گزارش خطا', icon('alert')],
];

$dash_widgets_config = [
    'dash_projects'         => ['کارت آمار «پروژه‌های من»', icon('folder')],
    'dash_products'         => ['کارت آمار «محصولات»', icon('box')],
    'dash_invoices'         => ['کارت آمار «فاکتورهای مالی»', icon('card')],
    'dash_tickets'          => ['کارت آمار «تیکت‌های پشتیبانی»', icon('ticket')],
    'dash_surveys'          => ['کارت آمار «نظرسنجی‌های در انتظار»', icon('star')],
    'dash_notifications'    => ['کارت آمار «اعلانات من»', icon('bell')],
    'dash_recent_projects'  => ['ویجت «پروژه‌های اخیر»', icon('folder')],
    'dash_recent_products'  => ['ویجت «محصولات اخیر»', icon('box')],
    'dash_recent_notifications' => ['ویجت «اعلانات اخیر»', icon('bell')],
    'dash_survey_banner'    => ['بنر «نظرسنجی‌های شما»', icon('star')],
    'dash_quick_access'     => ['ویجت «دسترسی سریع» (جایگزین)', icon('link')],
    'dash_welcome'          => ['بنر خوش‌آمدگویی', icon('trending')],
    'dash_profile_banner'   => ['بنر یادآوری تکمیل پروفایل', icon('alert')],
];

$tab_labels = [
    'modules'    => ['ماژول‌ها', 'فعال/غیرفعال کردن بخش‌های سیستم', 'box'],
    'cache'      => ['کش و سرعت', 'مدیریت کش برای افزایش سرعت سیستم', 'box'],
    'fields'     => ['فیلدهای اجباری', 'تعیین فیلدهای الزامی پروفایل مشتری', 'clipboard'],
    'dashboard'  => ['داشبورد کاربر', 'کنترل ویجت‌های صفحه اصلی مشتری', 'dashboard'],
    'appearance' => ['ظاهر سایت', 'لوگو و رنگ‌بندی سیستم', 'palette'],
    'login_sms'  => ['ورود و پیامک', 'روش ورود و تنظیمات سرویس پیامک', 'phone'],
    'general'    => ['عمومی', 'عنوان و متن‌های عمومی سیستم', 'globe'],
];

// اعتبارسنجی تب فعال بر اساس کلیدهای واقعی تب‌ها — هر تب جدید خودکار مجاز است
if (!array_key_exists($tab, $tab_labels)) {
    $tab = 'modules';
}

render_admin_header('تنظیمات سیستم', 'p-8 max-w-4xl w-full mx-auto space-y-6');
?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($err): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
            <?php endif; ?>

            <!-- تب‌ها -->
            <div class="card overflow-hidden">
                <nav class="flex border-b border-slate-200 overflow-x-auto" aria-label="بخش‌های تنظیمات">
                    <?php foreach ($tab_labels as $tkey => $tinfo): ?>
                        <a href="settings.php?tab=<?= $tkey ?>" class="inline-flex items-center gap-2 px-5 py-3.5 text-sm font-medium whitespace-nowrap transition border-b-2 <?= $tab === $tkey ? 'border-indigo-600 text-indigo-700 bg-indigo-50/50' : 'border-transparent text-slate-500 hover:text-slate-800 hover:bg-slate-50' ?>">
                            <?= icon($tinfo[2] ?? 'settings', 'w-4 h-4') ?>
                            <span><?= $tinfo[0] ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>
                <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/50">
                    <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2 leading-tight"><?= icon($tab_labels[$tab][2] ?? 'settings', 'w-5 h-5 text-indigo-600 flex-shrink-0') ?> <?= $tab_labels[$tab][0] ?></h3>
                    <p class="text-sm text-slate-500 mt-2 leading-relaxed"><?= $tab_labels[$tab][1] ?></p>
                </div>

                <div class="p-6">

                <?php if ($tab === 'modules'): ?>
                    <!-- ===== تب ماژول‌ها ===== -->
                    <form method="POST" class="space-y-5">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="save_type" value="modules">
                        <div class="divide-y divide-slate-100">
                            <?php foreach ($modules_config as $m_key => $m_info): ?>
                                <?php $is_enabled = is_module_enabled($m_key); ?>
                                <div class="py-4 flex items-center justify-between gap-4">
                                    <div class="flex items-center gap-3">
                                        <span class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center flex-shrink-0"><?= $m_info[1] ?></span>
                                        <div>
                                            <span class="font-medium text-slate-800 text-sm"><?= $m_info[0] ?></span>
                                            <span class="text-xs text-slate-400 block mt-0.5">شناسه: <?= $m_key ?></span>
                                        </div>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer select-none shrink-0">
                                        <input type="checkbox" name="module_<?= $m_key ?>" value="1" <?= $is_enabled ? 'checked' : '' ?> class="sr-only peer">
                                        <div class="switch-track"></div>
                                        <span class="badge <?= $is_enabled ? 'badge-success' : 'badge-muted' ?> mr-3"><?= $is_enabled ? 'فعال' : 'غیرفعال' ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="pt-5 border-t border-slate-100 flex justify-end">
                            <button class="btn btn-primary btn-lg"><?= icon('check') ?><span>ذخیره وضعیت ماژول‌ها</span></button>
                        </div>
                    </form>

                <?php elseif ($tab === 'cache'): ?>
                    <!-- ===== تب کش و سرعت ===== -->
                    <form method="POST" class="space-y-5">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="save_type" value="cache">
                        <div class="card p-5 space-y-4">
                            <div class="py-3 flex items-center justify-between gap-4">
                                <div>
                                    <span class="font-medium text-slate-800 text-sm">کش تنظیمات و داده‌ها</span>
                                    <span class="text-xs text-slate-400 block mt-0.5">ذخیره موقت تنظیمات و آمار در فایل — کاهش کوئری‌های دیتابیس و پاسخ‌دهی سریع‌تر</span>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer select-none shrink-0">
                                    <input type="checkbox" name="cache_enabled" value="1" <?= portal_cache_enabled() ? 'checked' : '' ?> class="sr-only peer">
                                    <div class="switch-track"></div>
                                    <span class="badge <?= portal_cache_enabled() ? 'badge-success' : 'badge-muted' ?> mr-3"><?= portal_cache_enabled() ? 'فعال' : 'غیرفعال' ?></span>
                                </label>
                            </div>
                            <div>
                                <label class="label" for="cache_ttl">مدت اعتبار کش (ثانیه)</label>
                                <input type="number" name="cache_ttl" id="cache_ttl" min="5" max="86400" value="<?= (int) get_setting('cache_ttl', '300') ?>" class="input" dir="ltr">
                                <p class="text-xs text-slate-400 mt-1">پیش‌فرض ۳۰۰ ثانیه (۵ دقیقه). مقدار کمتر = به‌روزرسانی سریع‌تر، مقدار بیشتر = سرعت بالاتر.</p>
                            </div>
                            <div class="pt-4 border-t border-slate-100 flex items-center justify-between gap-3 flex-wrap">
                                <p class="text-xs text-slate-500">وضعیت کش: <b dir="ltr"><?= htmlspecialchars(portal_cache_dir()) ?></b><br><span class="text-slate-400"><?= portal_cache_file_count() ?> فایل کش فعال</span></p>
                                <button type="submit" class="btn btn-primary"><?= icon('check') ?><span>ذخیره تنظیمات کش</span></button>
                            </div>
                        </div>
                    </form>
                    <form method="POST" class="mt-4" onsubmit="return confirm('کش سیستم پاک شود؟')">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="save_type" value="flush_cache">
                        <button class="btn btn-secondary"><?= icon('trash') ?><span>پاک‌سازی کش</span></button>
                    </form>

                <?php elseif ($tab === 'fields'): ?>
                    <!-- ===== تب فیلدهای اجباری ===== -->
                    <form method="POST" class="space-y-5">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="save_type" value="mandatory">
                        <div class="divide-y divide-slate-100">
                            <?php foreach ($fields_config as $key => $label): ?>
                                <?php $is_req = get_setting('req_' . $key, '0') === '1'; ?>
                                <div class="py-4 flex items-center justify-between gap-4">
                                    <div>
                                        <span class="font-medium text-slate-800 text-sm"><?= $label ?></span>
                                        <span class="text-xs text-slate-400 block mt-0.5">شناسه فیلد: <?= $key ?></span>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer select-none shrink-0">
                                        <input type="checkbox" name="req_<?= $key ?>" value="1" <?= $is_req ? 'checked' : '' ?> class="sr-only peer">
                                        <div class="switch-track"></div>
                                        <span class="badge <?= $is_req ? 'badge-brand' : 'badge-muted' ?> mr-3"><?= $is_req ? 'اجباری' : 'اختیاری' ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="pt-5 border-t border-slate-100 flex justify-end">
                            <button class="btn btn-primary btn-lg"><?= icon('check') ?><span>ذخیره تنظیمات فیلدهای اجباری</span></button>
                        </div>
                    </form>

                <?php elseif ($tab === 'dashboard'): ?>
                    <!-- ===== تب داشبورد کاربر ===== -->
                    <form method="POST" class="space-y-5">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="save_type" value="dashboard">
                        <div class="divide-y divide-slate-100">
                            <?php foreach ($dash_widgets_config as $w_key => $w_info): ?>
                                <?php $is_on = get_setting($w_key, '1') === '1'; ?>
                                <div class="py-4 flex items-center justify-between gap-4">
                                    <div class="flex items-center gap-3">
                                        <span class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center flex-shrink-0"><?= $w_info[1] ?></span>
                                        <div>
                                            <span class="font-medium text-slate-800 text-sm"><?= $w_info[0] ?></span>
                                            <span class="text-xs text-slate-400 block mt-0.5">شناسه: <?= $w_key ?></span>
                                        </div>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer select-none shrink-0">
                                        <input type="checkbox" name="<?= $w_key ?>" value="1" <?= $is_on ? 'checked' : '' ?> class="sr-only peer">
                                        <div class="switch-track"></div>
                                        <span class="badge <?= $is_on ? 'badge-success' : 'badge-muted' ?> mr-3"><?= $is_on ? 'نمایش' : 'مخفی' ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="pt-5 border-t border-slate-100 flex justify-end">
                            <button class="btn btn-primary btn-lg"><?= icon('check') ?><span>ذخیره تنظیمات داشبورد کاربر</span></button>
                        </div>
                    </form>

                <?php elseif ($tab === 'appearance'): ?>
                    <!-- ===== تب ظاهر سایت (لوگو + رنگ‌بندی) ===== -->
                    <?php $active_palette = active_theme_palette(); $current_theme = get_setting('site_theme', 'indigo'); $current_logo = site_logo_url(); $saved_logo_choice = get_setting('logo_choice', $current_logo === '' ? 'none' : 'url'); ?>
                    <form method="POST" class="space-y-6" enctype="multipart/form-data">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="save_type" value="appearance">

                        <!-- پالت رنگی -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">رنگ‌بندی سایت</label>
                            <p class="text-xs text-slate-400 mb-3">روی پالت دلخواه کلیک کنید — انتخاب شما بلافاصله با تیک و حاشیه رنگی مشخص می‌شود (پس از ذخیره اعمال می‌شود).</p>
                            <style>
                                .theme-option { position: relative; }
                                .theme-option:has(input[type="radio"]:checked) {
                                    border-color: #6366f1 !important;
                                    background: #eef2ff !important;
                                    box-shadow: 0 0 0 3px rgba(99,102,241,.18);
                                }
                                .theme-option .theme-check { display: none; }
                                .theme-option:has(input:checked) .theme-check { display: inline-flex; }
                            </style>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                <?php foreach (theme_palettes() as $pkey => $p): ?>
                                    <label class="theme-option flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition <?= $current_theme === $pkey ? 'border-indigo-500 ring-2 ring-indigo-100 bg-indigo-50/40' : 'border-slate-200 hover:border-slate-300' ?>">
                                        <input type="radio" name="site_theme" value="<?= $pkey ?>" <?= $current_theme === $pkey ? 'checked' : '' ?> class="sr-only">
                                        <span class="w-9 h-9 rounded-lg flex-shrink-0 border border-black/5" style="background:linear-gradient(135deg, <?= $p['primary'] ?>, <?= $p['accent'] ?>)"></span>
                                        <span class="text-sm font-medium text-slate-700 flex-1"><?= $p['label'] ?></span>
                                        <span class="theme-check w-5 h-5 rounded-full bg-indigo-600 text-white items-center justify-center flex-shrink-0"><?= icon('check','w-3 h-3') ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- لوگو -->
                        <div class="pt-4 border-t border-slate-100">
                            <label class="block text-sm font-medium text-slate-700 mb-2">لوگوی سایت</label>
                            <div class="flex items-center gap-4 mb-3">
                                <div class="w-16 h-16 rounded-xl border border-slate-200 bg-slate-50 flex items-center justify-center overflow-hidden">
                                    <?= site_logo_html('w-16 h-16 rounded-xl text-2xl') ?>
                                </div>
                                <div class="text-xs text-slate-500">
                                    <div>نمایش فعلی لوگو</div>
                                    <?php if ($current_logo): ?><div class="font-mono text-xs text-slate-400 mt-1 break-all" dir="ltr"><?= htmlspecialchars($current_logo) ?></div><?php endif; ?>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="logo_choice" value="none" <?= $saved_logo_choice === 'none' ? 'checked' : '' ?> class="w-4 h-4 text-indigo-600">
                                    <span class="text-sm text-slate-700">بدون لوگو (حرف پیش‌فرض «پ»)</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="logo_choice" value="upload" id="logo_upload_choice" <?= $saved_logo_choice === 'upload' ? 'checked' : '' ?> class="w-4 h-4 text-indigo-600">
                                    <span class="text-sm text-slate-700">آپلود فایل لوگو</span>
                                    <input type="file" name="site_logo_file" accept=".png,.jpg,.jpeg,.svg,.webp,.gif" class="text-xs text-slate-500 file:mr-2 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-700 file:text-xs file:font-medium">
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="logo_choice" value="url" id="logo_url_choice" <?= $saved_logo_choice === 'url' ? 'checked' : '' ?> class="w-4 h-4 text-indigo-600">
                                    <span class="text-sm text-slate-700">آدرس (URL) لوگو</span>
                                </label>
                                <input type="text" name="site_logo_url" id="site_logo_url" dir="ltr" value="<?= htmlspecialchars($current_logo) ?>" placeholder="https://example.com/logo.png" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm">
                            </div>
                        </div>

                        <!-- استایل کارت محصولات و پروژه‌ها -->
                        <div class="pt-4 border-t border-slate-100">
                            <label class="block text-sm font-medium text-slate-700 mb-1">استایل کارت محصولات و پروژه‌ها</label>
                            <p class="text-xs text-slate-400 mb-3">نحوه نمایش کارت‌های محصولات و پروژه‌ها در پنل مشتری را انتخاب کنید (برای هر کدام جداگانه).</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <?php foreach (['product' => ['محصولات', icon('box')], 'project' => ['پروژه‌ها', icon('folder')]] as $ckey => $cinfo): ?>
                                    <?php $cur_style = get_setting($ckey . '_card_style', 'vertical'); ?>
                                    <div class="border border-slate-200 rounded-xl p-4 bg-slate-50/50">
                                        <span class="font-medium text-slate-800 text-sm mb-3 block"><?= $cinfo[1] ?> استایل کارت <?= $cinfo[0] ?></span>
                                        <div class="grid grid-cols-2 gap-2">
                                            <?php foreach (entity_card_styles() as $skey => $slabel): ?>
                                                <label class="flex items-center gap-2 p-2.5 rounded-lg border cursor-pointer transition <?= $cur_style === $skey ? 'border-indigo-500 bg-indigo-50/60 ring-1 ring-indigo-100' : 'border-slate-200 bg-white hover:border-slate-300' ?>">
                                                    <input type="radio" name="<?= $ckey ?>_card_style" value="<?= $skey ?>" <?= $cur_style === $skey ? 'checked' : '' ?> class="w-4 h-4 text-indigo-600">
                                                    <span class="text-xs font-medium text-slate-700"><?= $slabel ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- تصاویر پیش‌فرض محصولات و پروژه‌ها -->
                        <div class="pt-4 border-t border-slate-100">
                            <label class="block text-sm font-medium text-slate-700 mb-1">تصاویر پیش‌فرض محصولات و پروژه‌ها</label>
                            <p class="text-xs text-slate-400 mb-3">اگر محصول یا پروژه‌ای عکس نداشته باشد، این تصاویر به‌صورت خودکار نمایش داده می‌شوند.</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <?php foreach (['product' => ['محصول', icon('box')], 'project' => ['پروژه', icon('folder')]] as $etyp => $info): ?>
                                    <?php $cur = get_setting('default_' . $etyp . '_image', ''); ?>
                                    <div class="border border-slate-200 rounded-xl p-4 bg-slate-50/50">
                                        <div class="flex items-center gap-3 mb-3">
                                            <div class="w-16 h-16 rounded-lg border border-slate-200 bg-white overflow-hidden flex-shrink-0">
                                                <?php if ($cur): ?>
                                                    <img src="<?= htmlspecialchars(asset_url($cur)) ?>" class="w-full h-full object-cover" alt="">
                                                <?php else: ?>
                                                    <div class="w-full h-full flex items-center justify-center text-2xl text-slate-300"><?= $info[1] ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <span class="font-medium text-slate-800 text-sm">تصویر پیش‌فرض <?= $info[0] ?></span>
                                                <?php if ($cur): ?><div class="text-xs text-slate-400 font-mono truncate max-w-[150px]" dir="ltr"><?= htmlspecialchars($cur) ?></div><?php endif; ?>
                                            </div>
                                        </div>
                                        <input type="file" name="default_<?= $etyp ?>_image" accept=".png,.jpg,.jpeg,.webp,.gif,.svg" class="text-xs text-slate-500 file:mr-2 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-700 file:text-xs file:font-medium">
                                        <?php if ($cur): ?>
                                            <label class="flex items-center gap-1.5 text-xs text-red-600 mt-2 cursor-pointer">
                                                <input type="checkbox" name="remove_<?= $etyp ?>_default" value="1" class="w-3.5 h-3.5 text-red-600 rounded border-slate-300"> حذف تصویر پیش‌فرض
                                            </label>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-slate-100 flex justify-end">
                            <button class="btn btn-primary btn-lg">ذخیره ظاهر سایت</button>
                        </div>
                    </form>

                <?php elseif ($tab === 'login_sms'): ?>
                    <!-- ===== تب ورود و پیامک ===== -->
                    <form method="POST" class="space-y-6">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="save_type" value="login_sms">

                        <!-- روش ورود -->
                        <div class="pb-4 border-b border-slate-100">
                            <label class="block text-sm font-medium text-slate-700 mb-2">روش ورود کاربران</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition <?= login_method() === 'username' ? 'border-indigo-500 ring-2 ring-indigo-100 bg-indigo-50/40' : 'border-slate-200 hover:border-slate-300' ?>">
                                    <input type="radio" name="login_method" value="username" <?= login_method() === 'username' ? 'checked' : '' ?> class="w-4 h-4 text-indigo-600">
                                    <span class="text-sm font-medium text-slate-700">نام کاربری و رمز عبور</span>
                                </label>
                                <label class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition <?= login_method() === 'mobile' ? 'border-indigo-500 ring-2 ring-indigo-100 bg-indigo-50/40' : 'border-slate-200 hover:border-slate-300' ?>">
                                    <input type="radio" name="login_method" value="mobile" <?= login_method() === 'mobile' ? 'checked' : '' ?> class="w-4 h-4 text-indigo-600">
                                    <span class="text-sm font-medium text-slate-700">شماره موبایل و کد تایید (OTP)</span>
                                </label>
                            </div>
                            <p class="text-xs text-slate-400 mt-2">با انتخاب «شماره موبایل»، کاربران فقط با شماره موبایل و کد پیامکی وارد می‌شوند.</p>
                        </div>

                        <!-- تنظیمات پیامک -->
                        <div>
                            <h4 class="font-bold text-slate-800 text-sm mb-3">تنظیمات سرویس پیامک (IPPanel)</h4>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1.5">API Key</label>
                                    <input type="text" name="sms_api_key" dir="ltr" value="<?= htmlspecialchars(get_setting('sms_api_key', '')) ?>" placeholder="کلید API از پنل ippanel (Developers > Access Keys)" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm font-mono">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1.5">آدرس سایت (برای ساخت لینک پیامک‌ها)</label>
                                    <input type="text" name="site_url" dir="ltr" value="<?= htmlspecialchars(get_setting('site_url', '')) ?>" placeholder="مثلا https://portal.example.com" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm font-mono">
                                    <p class="text-xs text-slate-400 mt-1">آدرس کامل پورتال که از بیرون قابل دسترسی است؛ در پیامک یادآوری نظرسنجی برای ساخت متغیر <span class="font-mono">survey_link</span> استفاده می‌شود.</p>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-1.5">شماره فرستنده (From)</label>
                                        <input type="text" name="sms_from_number" dir="ltr" value="<?= htmlspecialchars(get_setting('sms_from_number', '')) ?>" placeholder="+983000505" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm font-mono">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-1.5">کد پترن (Pattern Code)</label>
                                        <input type="text" name="sms_pattern" dir="ltr" value="<?= htmlspecialchars(get_setting('sms_pattern', '')) ?>" placeholder="کد پترن تاییدشده" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm font-mono">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-1.5">نام متغیر کد در پترن</label>
                                        <input type="text" name="sms_pattern_var" dir="ltr" value="<?= htmlspecialchars(get_setting('sms_pattern_var', 'code')) ?>" placeholder="مثلا code" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm font-mono">
                                    </div>
                                </div>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="sms_ssl_verify_off" value="1" <?= get_setting('sms_ssl_verify', '1') !== '1' ? 'checked' : '' ?> class="w-4 h-4 text-indigo-600 rounded border-slate-300">
                                    <span class="text-sm text-slate-700">غیرفعال‌سازی اعتبارسنجی SSL (برای تست لوکال / لاراگون)</span>
                                </label>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1.5 mt-3">طول کد تایید (OTP)</label>
                                    <div class="flex gap-3">
                                        <?php foreach ([6, 7, 8] as $len): ?>
                                            <label class="flex items-center gap-2 p-2.5 rounded-lg border cursor-pointer transition <?= (int) get_setting('otp_length', '6') === $len ? 'border-indigo-500 bg-indigo-50/60 ring-1 ring-indigo-100' : 'border-slate-200 bg-white hover:border-slate-300' ?>">
                                                <input type="radio" name="otp_length" value="<?= $len ?>" <?= (int) get_setting('otp_length', '6') === $len ? 'checked' : '' ?> class="w-4 h-4 text-indigo-600">
                                                <span class="text-sm font-medium text-slate-700"><?= $len ?> رقمی</span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <p class="text-xs text-slate-400 mt-1 pr-6">اگر روی سیستم لوکال خطای «error setting certificate file» می‌بینید، این گزینه را فعال کنید. در محیط آنلاین بهتر است فعال نباشد.</p>
                                <div class="bg-slate-50 rounded-xl p-4 text-xs text-slate-500 leading-relaxed">
                                    <b class="text-slate-700">راهنما:</b> ابتدا در پنل <span class="font-mono">ippanel.com</span> یک پترن (Pattern) تاییدشده بسازید که شامل متغیر کد باشد (مثلا: «کد تایید شما: %code%»). سپس کد پترن و نام متغیر (مثلا code) را اینجا وارد کنید. پیامک‌های کد تایید ورود از این طریق ارسال می‌شوند.
                                </div>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-slate-100 flex justify-end">
                            <button class="btn btn-primary btn-lg">ذخیره تنظیمات ورود و پیامک</button>
                        </div>
                    </form>

                    <!-- فرم ارسال پیامک تست -->
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="p-5 border-b border-slate-100">
                            <h4 class="font-bold text-slate-800 text-sm h-4 flex items-center gap-2"><?= icon('send','w-4 h-4 text-indigo-600') ?> ارسال پیامک تست</h4>
                            <p class="text-xs text-slate-500 mt-0.5">برای اطمینان از صحت تنظیمات، یک پیامک تست به شماره خود بفرستید.</p>
                        </div>
                        <form method="post" class="p-5 flex flex-col sm:flex-row gap-3 items-end">
                            <?php echo csrf_input(); ?>
                            <input type="hidden" name="save_type" value="sms_test">
                            <div class="flex-1 w-full">
                                <label class="block text-xs text-slate-500 mb-1">شماره موبایل گیرنده</label>
                                <input type="text" name="test_mobile" dir="ltr" placeholder="09123456789" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm">
                            </div>
                            <button class="btn btn-primary"><?= icon('send') ?><span>ارسال تست</span></button>
                        </form>
                    </div>

                    <!-- مدیریت رویدادهای پیامکی -->
                    <?php $sms_events = $pdo->query("SELECT * FROM sms_events ORDER BY id ASC")->fetchAll(); ?>
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="p-5 border-b border-slate-100">
                            <h4 class="font-bold text-slate-800 text-sm h-4 flex items-center gap-2"><?= icon('settings','w-4 h-4 text-indigo-600') ?> رویدادهای پیامکی خودکار</h4>
                            <p class="text-xs text-slate-500 mt-0.5">برای هر رویداد، کد پترن و نام متغیر اختصاصی تعیین کنید. با فعال‌سازی هر رویداد، پیامک به‌صورت خودکار ارسال می‌شود.</p>
                        </div>
                        <form method="post" class="p-5 space-y-4">
                            <?php echo csrf_input(); ?>
                            <input type="hidden" name="save_type" value="sms_events">
                            <div class="space-y-3">
                                <?php foreach ($sms_events as $ev): ?>
                                    <?php $ev_info = sms_event_list()[$ev['event_key']] ?? ['title' => $ev['title'], 'vars' => '']; ?>
                                    <?php $avail_vars = array_values(array_filter(array_map('trim', explode(',', $ev_info['vars'] ?? '')))); ?>
                                    <?php
                                        // خواندن نگاشت ذخیره‌شده: "sys=pattern,sys2=pattern2"
                                        $saved_map = [];
                                        foreach (array_filter(array_map('trim', explode(',', (string) ($ev['pattern_vars'] ?? '')))) as $pair) {
                                            if (str_contains($pair, '=')) {
                                                [$sys, $pat] = explode('=', $pair, 2);
                                                $saved_map[trim($sys)] = trim($pat);
                                            } else if ($pair !== '') {
                                                $saved_map[$pair] = $pair;
                                            }
                                        }
                                        $ev_active = (int) $ev['is_active'] === 1;
                                    ?>
                                    <div class="sms-event-card border rounded-xl overflow-hidden <?= $ev_active ? 'border-indigo-200 ring-1 ring-indigo-100' : 'border-slate-200' ?>">
                                        <!-- سربرگ کارت (کلیک = باز/بسته کردن آکاردئون) -->
                                        <div class="flex items-center justify-between gap-3 p-4 cursor-pointer select-none sms-event-head transition <?= $ev_active ? 'bg-indigo-50/40' : 'bg-slate-50/60 hover:bg-slate-50' ?>" onclick="toggleSmsEventBody(this)">
                                            <div class="flex items-center gap-3">
                                                <label class="relative inline-flex items-center cursor-pointer select-none" onclick="event.stopPropagation()" title="فعال/غیرفعال کردن رویداد">
                                                    <input type="checkbox" name="event_<?= $ev['event_key'] ?>" value="1" <?= $ev_active ? 'checked' : '' ?> class="sr-only peer sms-event-toggle">
                                                    <div class="switch-track"></div>
                                                </label>
                                                <div>
                                                    <span class="font-medium text-slate-800 text-sm"><?= htmlspecialchars($ev['title']) ?></span>
                                                    <p class="text-xs text-slate-400"><?= htmlspecialchars($ev['description'] ?: '') ?></p>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <span class="text-xs text-slate-400 font-mono hidden lg:inline"><?= count($avail_vars) ?> متغیر</span>
                                                <svg class="event-chevron w-5 h-5 text-slate-400 transition-transform <?= $ev_active ? 'rotate-180' : '' ?>" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                                            </div>
                                        </div>
                                        <!-- بدنه تنظیمات (فقط برای رویداد فعال باز است) -->
                                        <div class="event-settings-body px-4 pb-4 <?= $ev_active ? '' : 'hidden' ?>" style="overflow:hidden;transition:max-height .3s ease">
                                            <div class="mt-3">
                                                <label class="block text-xs text-slate-500 mb-1">کد پترن</label>
                                                <input type="text" name="pattern_<?= $ev['event_key'] ?>" dir="ltr" value="<?= htmlspecialchars($ev['pattern_code']) ?>" placeholder="مثلا f12345" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm font-mono max-w-md">
                                            </div>
                                            <?php if (!empty($avail_vars)): ?>
                                            <div class="mt-3">
                                                <label class="block text-xs text-slate-500 mb-1.5">متغیرها و نام معادل آن‌ها در پترن</label>
                                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                                                    <?php foreach ($avail_vars as $vname): ?>
                                                        <?php $is_selected = array_key_exists($vname, $saved_map); ?>
                                                        <div class="border rounded-lg p-2.5 <?= $is_selected ? 'border-indigo-500 bg-indigo-50/40' : 'border-slate-200 bg-white' ?>">
                                                            <label class="flex items-center gap-1.5 cursor-pointer text-xs font-mono text-slate-700">
                                                                <input type="checkbox" name="vars_<?= $ev['event_key'] ?>[]" value="<?= htmlspecialchars($vname) ?>" <?= $is_selected ? 'checked' : '' ?> class="w-3.5 h-3.5 text-indigo-600 rounded border-slate-300">
                                                                <?= htmlspecialchars($vname) ?>
                                                            </label>
                                                            <input type="text" name="var_map_<?= $ev['event_key'] ?>_<?= htmlspecialchars($vname) ?>" dir="ltr" value="<?= htmlspecialchars($saved_map[$vname] ?? '') ?>" placeholder="نام معادل در پترن" class="mt-1.5 w-full px-2 py-1.5 rounded-lg border border-slate-300 text-xs font-mono" <?= $is_selected ? '' : 'disabled' ?>>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                <p class="text-xs text-slate-400 mt-1.5">هر متغیر را تیک بزنید و «نام معادل در پترن» را وارد کنید (مثلا متغیر <span class="font-mono">first_name</span> با نام <span class="font-mono">name</span> در پترن). اگر نام معادل خالی باشد، همان نام متغیر استفاده می‌شود.</p>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                        <script>
                        // ===== آکاردئون رویدادهای پیامکی =====
                        // رویداد فعال = باز، غیرفعال = بسته (سوییچر و هدر هر دو کنترل می‌کنند)
                        function toggleSmsEventBody(headEl){
                            var card = headEl.closest('.sms-event-card');
                            var body = card.querySelector('.event-settings-body');
                            var chev = card.querySelector('.event-chevron');
                            var open = !body.classList.contains('hidden');
                            animateSmsBody(body, !open);
                            chev.classList.toggle('rotate-180', !open);
                        }
                        function animateSmsBody(body, open){
                            if (open){
                                body.classList.remove('hidden');
                                body.style.maxHeight = body.scrollHeight + 'px';
                                setTimeout(function(){ body.style.maxHeight = 'none'; }, 320);
                            } else {
                                body.style.maxHeight = body.scrollHeight + 'px';
                                requestAnimationFrame(function(){
                                    requestAnimationFrame(function(){ body.style.maxHeight = '0px'; });
                                });
                                setTimeout(function(){ body.classList.add('hidden'); }, 300);
                            }
                        }
                        // همگام‌سازی باز/بسته با سوییچر فعال‌سازی
                        document.addEventListener('DOMContentLoaded', function(){
                            document.querySelectorAll('.sms-event-card').forEach(function(card){
                                var cb = card.querySelector('.sms-event-toggle');
                                var body = card.querySelector('.event-settings-body');
                                var chev = card.querySelector('.event-chevron');
                                function sync(){
                                    var open = cb.checked;
                                    if (open){
                                        body.classList.remove('hidden');
                                        body.style.maxHeight = 'none';
                                        card.classList.add('border-indigo-200','ring-1','ring-indigo-100');
                                        card.classList.remove('border-slate-200');
                                        card.querySelector('.sms-event-head').classList.add('bg-indigo-50/40');
                                        card.querySelector('.sms-event-head').classList.remove('bg-slate-50/60','hover:bg-slate-50');
                                    } else {
                                        body.classList.add('hidden');
                                        body.style.maxHeight = '';
                                        card.classList.remove('border-indigo-200','ring-1','ring-indigo-100');
                                        card.classList.add('border-slate-200');
                                        card.querySelector('.sms-event-head').classList.remove('bg-indigo-50/40');
                                        card.querySelector('.sms-event-head').classList.add('bg-slate-50/60','hover:bg-slate-50');
                                    }
                                    chev.classList.toggle('rotate-180', open);
                                }
                                cb.addEventListener('change', sync);
                            });
                        });
                        </script>
                        <div class="pt-4 border-t border-slate-100 flex justify-end">
                            <button type="submit" class="btn btn-primary"><?= icon('check') ?><span>ذخیره رویدادهای پیامکی</span></button>
                        </div>
                        </form>
                        <script>
                        // فعال/غیرفعال کردن فیلد «نام معادل در پترن» بر اساس تیک متغیر
                        document.addEventListener('DOMContentLoaded', function(){
                            document.querySelectorAll('input[type=checkbox][name^="vars_"]').forEach(function(cb){
                                function sync(){
                                    var box = cb.closest('.border');
                                    var inp = box ? box.querySelector('input[name^="var_map_"]') : null;
                                    if (inp) {
                                        inp.disabled = !cb.checked;
                                        box.classList.toggle('border-indigo-500', cb.checked);
                                        box.classList.toggle('bg-indigo-50/40', cb.checked);
                                        box.classList.toggle('border-slate-200', !cb.checked);
                                        box.classList.toggle('bg-white', !cb.checked);
                                    }
                                }
                                cb.addEventListener('change', sync);
                                sync();
                            });
                        });
                        </script>
                    </div>

                    <!-- تنظیمات یادآوری خودکار نظرسنجی -->
                    <?php $sr = sms_survey_reminder_settings(); ?>
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="p-5 border-b border-slate-100">
                            <h4 class="font-bold text-slate-800 text-sm h-4 flex items-center gap-2"><?= icon('bell','w-4 h-4 text-indigo-600') ?> یادآوری خودکار نظرسنجی (کرون‌جاب)</h4>
                            <p class="text-xs text-slate-500 mt-0.5">اگر مشتری نظرسنجی فعال را کامل نکرده باشد، طبق این زمان‌بندی پیامک یادآوری با لینک تکمیل دریافت می‌کند.</p>
                        </div>
                        <form method="post" class="p-5 space-y-4">
                            <?php echo csrf_input(); ?>
                            <input type="hidden" name="save_type" value="survey_reminder">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">اولین یادآوری (روز بعد از فعال‌شدن)</label>
                                    <input type="number" name="survey_reminder_days" min="1" max="365" value="<?= $sr['days'] ?>" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm">
                                    <p class="text-xs text-slate-400 mt-1">مثلا ۳ یعنی ۳ روز بعد از فعال‌شدن فرم، اولین یادآوری ارسال شود.</p>
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">فاصله یادآوری‌های بعدی (روز)</label>
                                    <input type="number" name="survey_reminder_interval" min="0" max="365" value="<?= $sr['interval'] ?>" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm">
                                    <p class="text-xs text-slate-400 mt-1">هر چند روز یک‌بار تکرار شود؟ <b>۰ = فقط یک‌بار یادآوری</b></p>
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">حداکثر تعداد یادآوری برای هر فرم</label>
                                    <input type="number" name="survey_reminder_max" min="1" max="20" value="<?= $sr['max'] ?>" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm">
                                    <p class="text-xs text-slate-400 mt-1">پس از این تعداد، دیگر یادآوری ارسال نمی‌شود.</p>
                                </div>
                            </div>
                            <div class="bg-slate-50 rounded-xl p-4 text-xs text-slate-500 leading-relaxed space-y-1.5">
                                <p><b class="text-slate-700">راه‌اندازی:</b></p>
                                <p>۱) رویداد «یادآوری نظرسنجی ناقص» را در بخش بالا فعال کنید و کد پترن آن را وارد کنید.</p>
                                <p>۲) در پترن، متغیر <span class="font-mono">survey_link</span> را تیک زده و «نام معادل در پترن» (مثلا <span class="font-mono">link</span>) را وارد کنید — این متغیر لینک عمومی تکمیل نظرسنجی است و نیازی به ورود ندارد.</p>
                                <p>۳) «آدرس سایت» را در تنظیمات سرویس پیامک بالا وارد کنید تا لینک کامل ساخته شود.</p>
                                <p>۴) کرون‌جاب را در سرور تنظیم کنید (مسیر کامل php را با <code class="font-mono" dir="ltr">which php</code> در سرور پیدا کنید):</p>
                                <p><code class="font-mono text-xs bg-slate-100 rounded px-2 py-1 block" dir="ltr">0 9 * * * /usr/bin/php -q <?= htmlspecialchars(dirname(__DIR__) . '/cron_survey_reminder.php') ?></code></p>
                                <p>📌 یادآوری فقط به کسانی ارسال می‌شود که هنوز پاسخ نداده‌اند و پس از تکمیل فرم، خودکار متوقف می‌شود.</p>
                            </div>
                            <div class="flex justify-end pt-3 border-t border-slate-100">
                                <button class="btn btn-primary">ذخیره تنظیمات یادآوری</button>
                            </div>
                        </form>
                    </div>

                    <!-- ارسال دستی پیامک -->
                    <?php $sms_customers = sms_customer_list(); ?>
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="p-5 border-b border-slate-100">
                            <h4 class="font-bold text-slate-800 text-sm h-4 flex items-center gap-2"><?= icon('send','w-4 h-4 text-indigo-600') ?> ارسال دستی پیامک به مشتریان</h4>
                            <p class="text-xs text-slate-500 mt-0.5">پیامک را به یک شماره خاص یا چند مشتری انتخابی بفرستید.</p>
                        </div>
                        <form method="post" class="p-5 space-y-4">
                            <?php echo csrf_input(); ?>
                            <input type="hidden" name="save_type" value="sms_send_manual">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">رویداد (پترن)</label>
                                    <select name="manual_event" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm bg-white">
                                        <?php foreach ($sms_events as $ev): ?>
                                            <option value="<?= $ev['event_key'] ?>"><?= htmlspecialchars($ev['title']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">شماره موبایل (اختیاری)</label>
                                    <input type="text" name="manual_mobile" dir="ltr" placeholder="09123456789" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">مقدار متغیر (اختیاری)</label>
                                    <input type="text" name="manual_value" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm" placeholder="برای پترن‌های تک‌متغیره">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs text-slate-500 mb-1.5">انتخاب مشتریان (اختیاری — چندتایی)</label>
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 max-h-40 overflow-y-auto border border-slate-200 rounded-xl p-3">
                                    <?php if (empty($sms_customers)): ?>
                                        <p class="text-xs text-slate-400 col-span-full">مشتری با شماره موبایل یافت نشد.</p>
                                    <?php else: ?>
                                        <?php foreach ($sms_customers as $cu): ?>
                                            <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer hover:bg-slate-50 rounded-lg px-2 py-1.5">
                                                <input type="checkbox" name="manual_user_ids[]" value="<?= $cu['id'] ?>" class="w-3.5 h-3.5 text-indigo-600 rounded border-slate-300">
                                                <span class="truncate"><?= htmlspecialchars(trim($cu['first_name'] . ' ' . $cu['last_name']) ?: $cu['username']) ?> <span class="text-xs text-slate-400 font-mono" dir="ltr"><?= htmlspecialchars($cu['mobile']) ?></span></span>
                                            </label>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="flex justify-end">
                                <button class="btn btn-primary"><?= icon('send') ?><span>ارسال پیامک</span></button>
                            </div>
                        </form>
                    </div>

                    <!-- تاریخچه پیامک‌ها -->
                    <?php $sms_history = sms_logs(50); ?>
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="p-5 border-b border-slate-100">
                            <h4 class="font-bold text-slate-800 text-sm h-4 flex items-center gap-2"><?= icon('file','w-4 h-4 text-indigo-600') ?> تاریخچه ارسال پیامک‌ها</h4>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="table table-card-mobile">
                                <thead class="bg-slate-50 text-slate-500 text-xs font-semibold">
                                    <tr>
                                        <th class="p-3">رویداد</th>
                                        <th class="p-3">گیرنده</th>
                                        <th class="p-3">وضعیت</th>
                                        <th class="p-3">پیام / خطا</th>
                                        <th class="p-3">زمان</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php if (empty($sms_history)): ?>
                                        <tr><td colspan="5" class="p-6 text-center text-slate-400">هنوز پیامکی ارسال نشده است.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($sms_history as $lg): ?>
                                            <tr class="hover:bg-slate-50">
                                                <td class="p-3 text-xs font-mono text-slate-600"><?= htmlspecialchars($lg['event_key']) ?></td>
                                                <td class="p-3 text-xs" dir="ltr"><?= htmlspecialchars($lg['mobile']) ?><br><span class="text-slate-400"><?= htmlspecialchars(trim($lg['first_name'] . ' ' . $lg['last_name']) ?: ($lg['username'] ?? '')) ?></span></td>
                                                <td class="p-3">
                                                    <?php if ($lg['status']): ?>
                                                        <span class="bg-emerald-50 text-emerald-700 text-xs px-2 py-0.5 rounded-full">ارسال شد</span>
                                                    <?php else: ?>
                                                        <span class="bg-red-50 text-red-600 text-xs px-2 py-0.5 rounded-full">ناموفق</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="p-3 text-xs text-slate-500 max-w-[200px] truncate" title="<?= htmlspecialchars($lg['error'] ?: $lg['message']) ?>"><?= htmlspecialchars($lg['error'] ?: $lg['message']) ?></td>
                                                <td class="p-3 text-xs text-slate-500"><?= htmlspecialchars(fa_datetime($lg['created_at'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- ===== تب عمومی ===== -->
                    <?php $lc = login_config(); ?>
                    <style>
                        /* نمایش زنده انتخاب رادیو طرح صفحه ورود */
                        .login-layout-card { position: relative; }
                        .login-layout-card input[type="radio"] { position:absolute; opacity:0; pointer-events:none; }
                        .login-layout-card:has(input[type="radio"]:checked) { border-color:#6366f1 !important; box-shadow:0 0 0 3px rgba(99,102,241,.18); }
                        .login-layout-card .layout-check { opacity:0; }
                        .login-layout-card:has(input:checked) .layout-check { opacity:1; }
                        .login-layout-card:has(input:checked) .layout-check-dot { background:#4f46e5; border-color:#4f46e5; }
                    </style>
                    <form method="POST" class="space-y-6" enctype="multipart/form-data">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="save_type" value="general">

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">عنوان سیستم (در منوها و صفحه ورود)</label>
                                <input type="text" name="site_title" value="<?= htmlspecialchars(get_setting('site_title', 'پورتال مشتریان')) ?>" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">متن پایین صفحه ورود</label>
                                <input type="text" name="footer_text" value="<?= htmlspecialchars(get_setting('footer_text', 'سیستم هوشمند پورتال مشتریان')) ?>" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm">
                            </div>
                            <div class="md:col-span-3">
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">زیرعنوان صفحه ورود</label>
                                <input type="text" name="login_subtitle" value="<?= htmlspecialchars(get_setting('login_subtitle', 'لطفا برای ورود به حساب کاربری خود اطلاعات زیر را وارد کنید')) ?>" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm">
                            </div>
                        </div>

                        <!-- ===== طرح صفحه ورود (با انتخاب زنده) ===== -->
                        <div class="pt-5 border-t border-slate-200">
                            <label class="block text-sm font-semibold text-slate-800 mb-1">طرح صفحه ورود</label>
                            <p class="text-xs text-slate-500 mb-4">روی هر طرح کلیک کنید تا انتخاب شود؛ با کلیک، کادر انتخابی به‌صورت زنده مشخص می‌شود.</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                <?php foreach (login_layout_options() as $lkey => $linfo): ?>
                                    <label class="login-layout-card cursor-pointer block border rounded-2xl p-3 transition <?= $lc['layout'] === $lkey ? 'border-indigo-500' : 'border-slate-200 hover:border-slate-300' ?>">
                                        <input type="radio" name="login_layout" value="<?= $lkey ?>" <?= $lc['layout'] === $lkey ? 'checked' : '' ?>>
                                        <div class="h-20 rounded-xl overflow-hidden border border-slate-200 mb-3 relative bg-slate-100">
                                            <?php if ($lkey === 'centered'): ?>
                                                <div class="absolute inset-0 flex items-center justify-center"><div class="w-16 h-12 bg-white rounded-lg shadow-sm border border-slate-200"></div></div>
                                            <?php elseif ($lkey === 'split'): ?>
                                                <div class="absolute inset-0 flex">
                                                    <div class="w-2/3 h-full" style="background:linear-gradient(135deg,#4f46e5,#7c3aed)"></div>
                                                    <div class="w-1/3 h-full flex items-center justify-center bg-white"><div class="w-8 h-8 bg-slate-100 rounded border border-slate-200"></div></div>
                                                </div>
                                            <?php elseif ($lkey === 'branded'): ?>
                                                <div class="absolute inset-0" style="background:linear-gradient(135deg,<?= $lc['branded_from'] ?>,<?= $lc['branded_to'] ?>)"></div>
                                                <div class="absolute inset-0 flex items-center justify-center"><div class="w-14 h-10 bg-white/80 rounded-lg shadow"></div></div>
                                            <?php else: ?>
                                                <div class="absolute inset-0 flex items-center justify-center"><div class="w-20 h-3 bg-slate-300 rounded mb-6"></div><div class="w-16 h-3 bg-slate-200 rounded mt-6"></div></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex items-start justify-between gap-2">
                                            <span class="text-sm font-medium text-slate-800 leading-snug"><?= $linfo['title'] ?></span>
                                            <span class="layout-check shrink-0 w-5 h-5 rounded-full border-2 border-slate-300 flex items-center justify-center transition">
                                                <span class="layout-check-dot w-3 h-3 rounded-full transition"></span>
                                            </span>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- ===== تنظیمات طرح دوطرفه (فقط وقتی دوطرفه انتخاب شود نمایش داده می‌شود) ===== -->
                        <div class="pt-5 border-t border-slate-200 layout-section" data-layout-show="split" <?= $lc['layout'] === 'split' ? '' : 'style="display:none"' ?>>
                            <h4 class="font-bold text-slate-800 text-sm mb-3 flex items-center gap-2"><?= icon('palette','w-4 h-4 text-indigo-600') ?> تنظیمات طرح «دوطرفه — تصویر + فرم»</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1.5">موقعیت افقی فرم</label>
                                    <div class="flex gap-3">
                                        <label class="flex-1 flex items-center justify-center gap-2 p-2.5 rounded-lg border cursor-pointer transition <?= $lc['split_side'] === 'right' ? 'border-indigo-500 bg-indigo-50/40' : 'border-slate-200 hover:border-slate-300' ?>">
                                            <input type="radio" name="split_side" value="right" <?= $lc['split_side'] === 'right' ? 'checked' : '' ?> class="w-4 h-4 text-indigo-600"> فرم در راست
                                        </label>
                                        <label class="flex-1 flex items-center justify-center gap-2 p-2.5 rounded-lg border cursor-pointer transition <?= $lc['split_side'] === 'left' ? 'border-indigo-500 bg-indigo-50/40' : 'border-slate-200 hover:border-slate-300' ?>">
                                            <input type="radio" name="split_side" value="left" <?= $lc['split_side'] === 'left' ? 'checked' : '' ?> class="w-4 h-4 text-indigo-600"> فرم در چپ
                                        </label>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1.5">موقعیت عمودی فرم</label>
                                    <div class="flex gap-3">
                                        <label class="flex-1 flex items-center justify-center gap-1 p-2.5 rounded-lg border cursor-pointer transition <?= $lc['split_vertical'] === 'top' ? 'border-indigo-500 bg-indigo-50/40' : 'border-slate-200 hover:border-slate-300' ?>">
                                            <input type="radio" name="split_vertical" value="top" <?= $lc['split_vertical'] === 'top' ? 'checked' : '' ?> class="w-4 h-4 text-indigo-600"> بالا
                                        </label>
                                        <label class="flex-1 flex items-center justify-center gap-1 p-2.5 rounded-lg border cursor-pointer transition <?= $lc['split_vertical'] === 'center' ? 'border-indigo-500 bg-indigo-50/40' : 'border-slate-200 hover:border-slate-300' ?>">
                                            <input type="radio" name="split_vertical" value="center" <?= $lc['split_vertical'] === 'center' ? 'checked' : '' ?> class="w-4 h-4 text-indigo-600"> وسط
                                        </label>
                                        <label class="flex-1 flex items-center justify-center gap-1 p-2.5 rounded-lg border cursor-pointer transition <?= $lc['split_vertical'] === 'bottom' ? 'border-indigo-500 bg-indigo-50/40' : 'border-slate-200 hover:border-slate-300' ?>">
                                            <input type="radio" name="split_vertical" value="bottom" <?= $lc['split_vertical'] === 'bottom' ? 'checked' : '' ?> class="w-4 h-4 text-indigo-600"> پایین
                                        </label>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1.5">نسبت تصویر <span class="text-slate-400">(<?= $lc['split_ratio'] ?>٪ تصویر — <?= 100 - $lc['split_ratio'] ?>٪ فرم)</span></label>
                                    <input type="range" name="split_ratio" min="40" max="75" step="5" value="<?= $lc['split_ratio'] ?>" class="w-full accent-indigo-600" oninput="document.getElementById('split_ratio_val').textContent=this.value+'٪ تصویر'">
                                    <p class="text-xs text-slate-400 mt-1" id="split_ratio_val"><?= $lc['split_ratio'] ?>٪ تصویر</p>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-slate-700 mb-1.5">عنوان سمت تصویر</label>
                                    <input type="text" name="split_title" value="<?= htmlspecialchars($lc['split_title']) ?>" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-slate-700 mb-1.5">متن سمت تصویر</label>
                                    <textarea name="split_subtitle" rows="2" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm"><?= htmlspecialchars($lc['split_subtitle']) ?></textarea>
                                </div>
                                <?php for ($i = 1; $i <= 3; $i++): ?>
                                    <div class="flex gap-2 items-end">
                                        <div class="flex-1">
                                            <label class="block text-xs text-slate-500 mb-1">ویژگی <?= $i ?> (عدد/آیکن)</label>
                                            <input type="text" name="split_feature<?= $i ?>" value="<?= htmlspecialchars($lc['split_feature' . $i]) ?>" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm">
                                        </div>
                                        <div class="flex-1">
                                            <label class="block text-xs text-slate-500 mb-1">برچسب ویژگی <?= $i ?></label>
                                            <input type="text" name="split_feature<?= $i ?>_l" value="<?= htmlspecialchars($lc['split_feature' . $i . '_l']) ?>" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm">
                                        </div>
                                    </div>
                                <?php endfor; ?>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1.5">تصویر سمت تصویر (دسکتاپ)</label>
                                    <input type="file" name="split_image" accept=".png,.jpg,.jpeg,.webp,.gif" class="text-xs text-slate-500 file:mr-2 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-700 file:text-xs file:font-medium">
                                    <?php if ($lc['split_image']): ?>
                                        <div class="flex items-center gap-2 mt-1.5">
                                            <img src="<?= e(asset_url($lc['split_image'])) ?>" class="w-16 h-10 object-cover rounded border" alt="">
                                            <label class="flex items-center gap-1 text-xs text-red-600 cursor-pointer"><input type="checkbox" name="remove_split_image" value="1" class="w-3.5 h-3.5"> حذف</label>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1.5">تصویر سمت تصویر (موبایل — جداگانه)</label>
                                    <input type="file" name="split_mobile_image" accept=".png,.jpg,.jpeg,.webp,.gif" class="text-xs text-slate-500 file:mr-2 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-700 file:text-xs file:font-medium">
                                    <?php if ($lc['split_mobile_image']): ?>
                                        <div class="flex items-center gap-2 mt-1.5">
                                            <img src="<?= e(asset_url($lc['split_mobile_image'])) ?>" class="w-16 h-10 object-cover rounded border" alt="">
                                            <label class="flex items-center gap-1 text-xs text-red-600 cursor-pointer"><input type="checkbox" name="remove_split_mobile_image" value="1" class="w-3.5 h-3.5"> حذف</label>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- ===== تنظیمات طرح گرادیان برند (فقط وقتی گرادیان انتخاب شود نمایش داده می‌شود) ===== -->
                        <div class="pt-5 border-t border-slate-200 layout-section" data-layout-show="branded" <?= $lc['layout'] === 'branded' ? '' : 'style="display:none"' ?>>
                            <h4 class="font-bold text-slate-800 text-sm mb-3 flex items-center gap-2"><?= icon('settings','w-4 h-4 text-indigo-600') ?> تنظیمات طرح «گرادیان برند»</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1.5">رنگ آغاز گرادیان</label>
                                    <div class="flex items-center gap-2">
                                        <input type="color" name="branded_from" value="<?= e($lc['branded_from']) ?>" class="w-12 h-10 rounded border border-slate-300 cursor-pointer">
                                        <input type="text" name="branded_from_text" value="<?= e($lc['branded_from']) ?>" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm font-mono" oninput="this.form.querySelector('[name=branded_from]').value=this.value">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1.5">رنگ پایان گرادیان</label>
                                    <div class="flex items-center gap-2">
                                        <input type="color" name="branded_to" value="<?= e($lc['branded_to']) ?>" class="w-12 h-10 rounded border border-slate-300 cursor-pointer">
                                        <input type="text" name="branded_to_text" value="<?= e($lc['branded_to']) ?>" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm font-mono" oninput="this.form.querySelector('[name=branded_to]').value=this.value">
                                    </div>
                                    <div class="mt-2 h-6 rounded-lg border border-slate-200" style="background:linear-gradient(135deg,<?= $lc['branded_from'] ?>,<?= $lc['branded_to'] ?>)"></div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1.5">تصویر پس‌زمینه موبایل (اختیاری)</label>
                                    <input type="file" name="branded_mobile_image" accept=".png,.jpg,.jpeg,.webp,.gif" class="text-xs text-slate-500 file:mr-2 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-700 file:text-xs file:font-medium">
                                    <?php if ($lc['branded_mobile_image']): ?>
                                        <div class="flex items-center gap-2 mt-1.5"><img src="<?= e(asset_url($lc['branded_mobile_image'])) ?>" class="w-16 h-10 object-cover rounded border" alt=""><label class="flex items-center gap-1 text-xs text-red-600 cursor-pointer"><input type="checkbox" name="remove_branded_mobile_image" value="1" class="w-3.5 h-3.5"> حذف</label></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- ===== تصویر پس‌زمینه دلخواه صفحه ورود ===== -->
                        <div class="pt-5 border-t border-slate-200">
                            <h4 class="font-bold text-slate-800 text-sm mb-3 flex items-center gap-2"><?= icon('file-plus','w-4 h-4 text-indigo-600') ?> تصویر پس‌زمینه صفحه ورود</h4>
                            <p class="text-xs text-slate-500 mb-3">در صورت بارگذاری، این تصویر به‌عنوان پس‌زمینهٔ صفحهٔ ورود در همهٔ طرح‌ها نمایش داده می‌شود (برای موبایل می‌توانید تصویر جداگانه بارگذاری کنید).</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1.5">تصویر پس‌زمینه (دسکتاپ)</label>
                                    <input type="file" name="login_bg_image" accept=".png,.jpg,.jpeg,.webp,.gif" class="text-xs text-slate-500 file:mr-2 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-700 file:text-xs file:font-medium">
                                    <?php if ($lc['bg_image']): ?><div class="flex items-center gap-2 mt-1.5"><img src="<?= e(asset_url($lc['bg_image'])) ?>" class="w-24 h-12 object-cover rounded border" alt=""><label class="flex items-center gap-1 text-xs text-red-600 cursor-pointer"><input type="checkbox" name="remove_login_bg_image" value="1" class="w-3.5 h-3.5"> حذف</label></div><?php endif; ?>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1.5">تصویر پس‌زمینه (موبایل)</label>
                                    <input type="file" name="login_bg_mobile_image" accept=".png,.jpg,.jpeg,.webp,.gif" class="text-xs text-slate-500 file:mr-2 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-700 file:text-xs file:font-medium">
                                    <?php if ($lc['bg_mobile_image']): ?><div class="flex items-center gap-2 mt-1.5"><img src="<?= e(asset_url($lc['bg_mobile_image'])) ?>" class="w-24 h-12 object-cover rounded border" alt=""><label class="flex items-center gap-1 text-xs text-red-600 cursor-pointer"><input type="checkbox" name="remove_login_bg_mobile_image" value="1" class="w-3.5 h-3.5"> حذف</label></div><?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- ===== منوی سفارشی هدر ===== -->
                        <div class="pt-5 border-t border-slate-200">
                            <h4 class="font-bold text-slate-800 text-sm mb-3 flex items-center gap-2"><?= icon('menu','w-4 h-4 text-indigo-600') ?> منوی هدر (دکمه‌های سفارشی)</h4>
                            <p class="text-xs text-slate-500 mb-3">آیتم‌های منوی دلخواه که در هدر بالای صفحات نمایش داده می‌شوند؛ متن و لینک هر آیتم را تنظیم کنید.</p>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">جایگاه منو در هدر</label>
                                <div class="flex gap-3">
                                    <label class="flex-1 flex items-center justify-center gap-2 p-2.5 rounded-lg border cursor-pointer transition <?= $lc['header_align'] === 'start' ? 'border-indigo-500 bg-indigo-50/40' : 'border-slate-200 hover:border-slate-300' ?>">
                                        <input type="radio" name="header_menu_align" value="start" <?= $lc['header_align'] === 'start' ? 'checked' : '' ?> class="w-4 h-4 text-indigo-600"> راست
                                    </label>
                                    <label class="flex-1 flex items-center justify-center gap-2 p-2.5 rounded-lg border cursor-pointer transition <?= $lc['header_align'] === 'center' ? 'border-indigo-500 bg-indigo-50/40' : 'border-slate-200 hover:border-slate-300' ?>">
                                        <input type="radio" name="header_menu_align" value="center" <?= $lc['header_align'] === 'center' ? 'checked' : '' ?> class="w-4 h-4 text-indigo-600"> وسط
                                    </label>
                                    <label class="flex-1 flex items-center justify-center gap-2 p-2.5 rounded-lg border cursor-pointer transition <?= $lc['header_align'] === 'end' ? 'border-indigo-500 bg-indigo-50/40' : 'border-slate-200 hover:border-slate-300' ?>">
                                        <input type="radio" name="header_menu_align" value="end" <?= $lc['header_align'] === 'end' ? 'checked' : '' ?> class="w-4 h-4 text-indigo-600"> چپ
                                    </label>
                                </div>
                            </div>
                            <?php $hmenu = header_menu_items(); ?>
                            <div id="header-menu-rows" class="space-y-3">
                                <?php if (empty($hmenu)): ?>
                                    <div class="flex gap-2 items-center"><input type="text" name="menu_label[]" placeholder="متن منو (مثلا: تماس با ما)" class="flex-1 px-3 py-2 rounded-lg border border-slate-300 text-sm"><input type="text" name="menu_url[]" placeholder="لینک (مثلا: /contact)" dir="ltr" class="flex-1 px-3 py-2 rounded-lg border border-slate-300 text-sm font-mono"><select name="menu_target[]" class="px-3 py-2 rounded-lg border border-slate-300 text-sm bg-white"><option value="_self">همان تب</option><option value="blank">تب جدید</option></select><button type="button" onclick="this.closest('.flex').remove()" class="btn btn-sm btn-outline-danger shrink-0">حذف</button></div>
                                <?php else: ?>
                                    <?php foreach ($hmenu as $mi): ?>
                                        <div class="flex gap-2 items-center">
                                            <input type="text" name="menu_label[]" value="<?= htmlspecialchars($mi['label']) ?>" class="flex-1 px-3 py-2 rounded-lg border border-slate-300 text-sm">
                                            <input type="text" name="menu_url[]" value="<?= htmlspecialchars($mi['url']) ?>" dir="ltr" class="flex-1 px-3 py-2 rounded-lg border border-slate-300 text-sm font-mono">
                                            <select name="menu_target[]" class="px-3 py-2 rounded-lg border border-slate-300 text-sm bg-white"><option value="_self" <?= ($mi['target'] ?? '') === '_self' ? 'selected' : '' ?>>همان تب</option><option value="blank" <?= ($mi['target'] ?? '') === '_blank' ? 'selected' : '' ?>>تب جدید</option></select>
                                            <button type="button" onclick="this.closest('.flex').remove()" class="btn btn-sm btn-outline-danger shrink-0"><?= icon('trash') ?></button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <button type="button" onclick="addHeaderMenuRow()" class="mt-2 btn btn-sm btn-secondary"><?= icon('plus') ?> افزودن آیتم</button>
                            <script>
                            function addHeaderMenuRow(){
                                var rows=document.getElementById('header-menu-rows');
                                var d=document.createElement('div');d.className='flex gap-2 items-center';
                                d.innerHTML='<input type="text" name="menu_label[]" placeholder="متن منو" class="flex-1 px-3 py-2 rounded-lg border border-slate-300 text-sm"><input type="text" name="menu_url[]" placeholder="لینک" dir="ltr" class="flex-1 px-3 py-2 rounded-lg border border-slate-300 text-sm font-mono"><select name="menu_target[]" class="px-3 py-2 rounded-lg border border-slate-300 text-sm bg-white"><option value="_self">همان تب</option><option value="blank">تب جدید</option></select><button type="button" onclick="this.parentElement.remove()" class="btn btn-sm btn-outline-danger shrink-0"><?= icon('trash') ?></button>';
                                rows.appendChild(d);
                            }
                            </script>
                        </div>

                        <div class="pt-4 border-t border-slate-100 flex justify-end">
                            <script>
                            // نمایش/مخفی‌کردن بخش‌های تنظیمات مرتبط با طرح انتخاب‌شده
                            document.addEventListener('DOMContentLoaded', function(){
                                var layoutRadios = document.querySelectorAll('input[name="login_layout"]');
                                var sections = document.querySelectorAll('.layout-section');
                                function syncLayout(){
                                    var selected = document.querySelector('input[name="login_layout"]:checked');
                                    var val = selected ? selected.value : 'centered';
                                    sections.forEach(function(sec){
                                        var show = sec.getAttribute('data-layout-show') === val;
                                        sec.style.display = show ? '' : 'none';
                                    });
                                }
                                layoutRadios.forEach(function(r){ r.addEventListener('change', syncLayout); });
                                syncLayout();
                            });
                            </script>
                            <button class="btn btn-primary btn-lg"><?= icon('check') ?> ذخیره تنظیمات عمومی و صفحه ورود</button>
                        </div>
                    </form>
                <?php endif; ?>

                </div>
            </div>

        <?php render_admin_footer();
