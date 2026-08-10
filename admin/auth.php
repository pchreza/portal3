<?php
// admin/auth.php
require_once __DIR__ . '/../config.php';

// مدیر ارشد یا مدیر عادی
$is_admin = in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'], true);
if (!isset($_SESSION['user_id']) || !$is_admin) {
    header('Location: ../index.php');
    exit;
}
require_valid_csrf();
