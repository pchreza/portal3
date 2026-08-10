<?php
// admin/survey-create.php — برای سازگاری با لینک‌های قدیمی؛ فرم ساخت در surveys.php قرار دارد
require_once 'auth.php';
if (!is_module_enabled('surveys')) { header('Location: index.php'); exit; }
header('Location: surveys.php?action=create');
exit;
