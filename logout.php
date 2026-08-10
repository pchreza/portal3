<?php
// logout.php — خروج امن: پاک‌سازی کامل سشن و کوکی
require_once 'config.php';

// پاک کردن کامل داده‌های سشن
$_SESSION = [];

// باطل کردن کوکی سشن
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// نابود کردن سشن سمت سرور
session_destroy();

// جلوگیری از session fixation در ورود بعدی
session_start();
session_regenerate_id(true);
session_destroy();

header('Location: index.php');
exit;
