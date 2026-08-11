<?php
/**
 * قالب مشترک صفحات پنل مدیریت
 * (این فایل از طریق render_admin_header() فراخوانی می‌شود)
 *
 * @var string $title          عنوان صفحه (در <title> و نوار بالا)
 * @var string $mainClass      کلاس‌های تگ <main>
 * @var string $extraStyles    استایل‌های اختصاصی صفحه (اختیاری)
 * @var string $topbarActions  محتوای اضافه سمت راست نوار بالا (اختیاری)
 */

$admin_display_name = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
if ($admin_display_name === '') {
    $admin_display_name = $_SESSION['username'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?></title>
    <?= site_favicon_html() ?>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
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
    <?php include dirname(__DIR__, 2) . '/admin/sidebar.php'; ?>

    <div class="flex-1 flex flex-col min-w-0">
        <header class="bg-white border-b border-slate-200 h-16 px-6 flex items-center justify-between sticky top-0 z-10 relative">
            <h1 class="text-lg font-bold text-slate-800 truncate" title="<?= e($title) ?>"><?= e($title) ?></h1>

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

            <div class="flex items-center gap-3">
                <span class="text-sm text-slate-600 font-medium hidden sm:inline">مدیر: <strong class="text-slate-800"><?= e($admin_display_name) ?></strong></span>
                <?= portal_theme_toggle() ?>
                <?= $topbarActions ?>
            </div>
        </header>

        <main id="main-content" class="<?= e($mainClass) ?>">
