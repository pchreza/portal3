<?php
// admin/survey-results.php — برای سازگاری با لینک‌های قدیمی؛ گزارش پاسخ‌ها در surveys.php قرار دارد
require_once 'auth.php';
if (!admin_can('surveys')) { header('Location: index.php'); exit; }
if (!is_module_enabled('surveys')) { header('Location: index.php'); exit; }
$id = (int) ($_GET['id'] ?? 0);
header('Location: surveys.php?results=' . $id, true, 301);
exit;
