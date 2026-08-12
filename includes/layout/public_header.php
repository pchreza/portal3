<?php
/**
 * قالب مشترک صفحات عمومی (مثل صفحه ورود)
 * (این فایل از طریق render_public_header() فراخوانی می‌شود)
 *
 * @var string $title       عنوان صفحه
 * @var string $bodyClass   کلاس‌های تگ <body>
 * @var string $extraStyles استایل‌های اختصاصی صفحه (اختیاری)
 */
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
<body class="<?= e($bodyClass) ?>">
    <?= portal_skip_link() ?>
