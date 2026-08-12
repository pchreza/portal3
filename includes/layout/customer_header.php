<?php
/**
 * قالب مشترک صفحات پنل مشتری
 * (این فایل از طریق render_customer_header() فراخوانی می‌شود)
 *
 * @var string $title          عنوان صفحه (در <title> و نوار بالا)
 * @var string $mainClass      کلاس‌های تگ <main>
 * @var string $extraStyles    استایل‌های اختصاصی صفحه (اختیاری)
 * @var string $topbarActions  محتوای اضافه سمت راست نوار بالا (اختیاری)
 * @var string $topbarUser     نام نمایشی کاربر در نوار بالا (اختیاری؛ اگر خالی
 *                             باشد از اطلاعات سشن خوانده می‌شود)
 */

$customer_display_name = trim($topbarUser);
if ($customer_display_name === '') {
    $customer_display_name = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
}
if ($customer_display_name === '') {
    $customer_display_name = $_SESSION['username'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?></title>
    <?= site_favicon_html() ?>
    <?= portal_font_css_link() ?>
    <link rel="stylesheet" href="<?= e(portal_asset_href('assets/tailwind.css')) ?>">
    <style>
        body { font-family: 'Vazirmatn', Tahoma, Arial, sans-serif; }
        <?= $extraStyles ?>
    </style>
    <?= theme_styles() ?>
    <?= portal_ui_css_link() ?>
    <?= portal_darkmode_init() ?>
    <?= datepicker_assets_css() ?>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex">
    <?= portal_skip_link() ?>
    <?php include dirname(__DIR__, 2) . '/customer/sidebar.php'; ?>

    <div class="flex-1 flex flex-col min-w-0">
        <header class="bg-white border-b border-slate-200 h-16 px-6 flex items-center justify-between sticky top-0 z-10 relative">
            <h1 class="portal-page-title text-lg font-bold text-slate-800 truncate" title="<?= e($title) ?>">
                <span class="sm:hidden"><?= e($mobileTitle !== '' ? $mobileTitle : $title) ?></span>
                <span class="hidden sm:inline"><?= e($title) ?></span>
            </h1>
            <?php
                $ha = get_setting('header_menu_align', 'start');
                $hamenu = header_menu_items();
                if (!empty($hamenu)):
                    $hpos = $ha === 'center' ? 'start-1/2 -translate-x-1/2' : ($ha === 'end' ? 'end-4' : 'start-4');
            ?>
            <div class="absolute top-1/2 -translate-y-1/2 <?= $hpos ?> flex items-center gap-2 max-w-[45%] flex-wrap hidden md:flex">
                <?php foreach ($hamenu as $hmi): ?>
                    <a href="<?= e($hmi['url']) ?>" target="<?= e($hmi['target']) ?>" class="header-menu-link btn btn-sm btn-secondary"><?= e($hmi['label']) ?></a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="flex items-center gap-2 sm:gap-3">
                <span class="text-sm text-slate-600 font-medium hidden sm:inline">کاربر: <strong class="text-slate-800"><?= e($customer_display_name) ?></strong></span>

                <!-- زنگوله اعلانات -->
                <?php if (!empty($_SESSION['user_id']) && $_SESSION['role'] === 'customer'): ?>
                    <?php
                        // کش سشن (۳۰ ثانیه) — جلوگیری از ۲ کوئری در هر بار باز شدن هر صفحه
                        $notif_cache = $_SESSION['notif_bell_cache'] ?? [];
                        if (($notif_cache['ts'] ?? 0) < time() - 30 || ($notif_cache['uid'] ?? null) !== $_SESSION['user_id']) {
                            $notif_cache = [
                                'ts'    => time(),
                                'uid'   => $_SESSION['user_id'],
                                'count' => unread_notifications_count((int) $_SESSION['user_id']),
                                'recent'=> get_user_notifications((int) $_SESSION['user_id'], 5),
                            ];
                            $_SESSION['notif_bell_cache'] = $notif_cache;
                        }
                        $notif_unread = $notif_cache['count'];
                        $notif_recent = $notif_cache['recent'];
                    ?>
                    <div class="relative" id="notif-bell">
                        <button type="button" id="notif-bell-toggle" aria-label="اعلانات<?= $notif_unread > 0 ? ' (' . $notif_unread . ' خوانده‌نشده)' : '' ?>" aria-expanded="false" aria-controls="notif-dropdown" class="btn btn-icon btn-ghost relative" title="اعلانات">
                            <?= icon('bell') ?>
                            <?php if ($notif_unread > 0): ?>
                                <span class="absolute -top-0.5 -start-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold flex items-center justify-center"><?= $notif_unread > 99 ? '99+' : $notif_unread ?></span>
                            <?php endif; ?>
                        </button>
                        <div id="notif-dropdown" class="hidden absolute end-0 top-full mt-2 w-80 max-w-[calc(100vw-1rem)] bg-white rounded-2xl border border-slate-200 shadow-xl z-50 overflow-hidden">
                            <div class="p-3 border-b border-slate-100 flex items-center justify-between">
                                <b class="text-sm text-slate-800">اعلانات</b>
                                <a href="notifications.php" class="text-xs text-indigo-600 hover:underline">مشاهده همه</a>
                            </div>
                            <div class="max-h-80 overflow-y-auto">
                                <?php if (empty($notif_recent)): ?>
                                    <p class="text-center text-slate-400 text-sm py-6">اعلانی وجود ندارد</p>
                                <?php else: ?>
                                    <?php foreach ($notif_recent as $nn): ?>
                                        <a href="notifications.php" class="flex items-start gap-3 px-4 py-3 hover:bg-slate-50 transition border-b border-slate-50 last:border-none <?= $nn['is_read'] ? '' : 'bg-indigo-50/40' ?>">
                                            <span class="text-lg flex-shrink-0"><?= notification_type_icon($nn['ntype']) ?></span>
                                            <span class="min-w-0">
                                                <span class="block text-sm font-medium text-slate-800 truncate" title="<?= htmlspecialchars($nn['title']) ?>"><?= htmlspecialchars($nn['title']) ?></span>
                                                <?php if ($nn['body']): ?>
                                                    <span class="block text-xs text-slate-500 truncate mt-0.5" title="<?= htmlspecialchars($nn['body']) ?>"><?= htmlspecialchars($nn['body']) ?></span>
                                                <?php endif; ?>
                                                <span class="block text-[11px] text-slate-400 mt-1"><?= htmlspecialchars(fa_datetime($nn['created_at'])) ?></span>
                                            </span>
                                            <?php if (!$nn['is_read']): ?>
                                                <span class="w-2 h-2 rounded-full bg-red-500 flex-shrink-0 mt-1.5"></span>
                                            <?php endif; ?>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <script nonce="<?= e(portal_csp_nonce()) ?>">
                    (function(){
                        var root=document.getElementById('notif-bell'),toggle=document.getElementById('notif-bell-toggle'),dropdown=document.getElementById('notif-dropdown');
                        if(!root||!toggle||!dropdown)return;
                        function close(){dropdown.classList.add('hidden');toggle.setAttribute('aria-expanded','false');}
                        toggle.addEventListener('click',function(){var open=toggle.getAttribute('aria-expanded')==='true';if(open)close();else{dropdown.classList.remove('hidden');toggle.setAttribute('aria-expanded','true');}});
                        document.addEventListener('click',function(e){if(!root.contains(e.target))close();});
                        document.addEventListener('keydown',function(e){if(e.key==='Escape')close();});
                    })();
                    </script>
                <?php endif; ?>

                <?php
                // لینک «بازگشت به داشبورد» در همه صفحات غیر از خود داشبورد، یکسان نمایش داده می‌شود
                $customer_is_dashboard = (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'index.php');
                ?>
                <?php if (!$customer_is_dashboard): ?>
                    <a href="index.php" class="btn btn-secondary btn-sm hidden md:inline-flex"><?= icon('home') ?><span>بازگشت به داشبورد</span></a>
                <?php endif; ?>
                <?= portal_theme_toggle() ?>
                <?= $topbarActions ?>
            </div>
        </header>

        <main id="main-content" class="<?= e($mainClass) ?>">
