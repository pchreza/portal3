<?php
// admin/survey-questions.php — برای سازگاری با لینک‌های قدیمی؛ مدیریت سؤال‌ها در surveys.php قرار دارد
require_once 'auth.php';
if (!admin_can('surveys')) { header('Location: index.php'); exit; }
if (!is_module_enabled('surveys')) { header('Location: index.php'); exit; }
$id = (int) ($_GET['id'] ?? $_POST['survey_id'] ?? 0);
header('Location: surveys.php?questions=' . $id);
exit;
