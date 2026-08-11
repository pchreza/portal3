<?php
// admin/customers.php - Customer Management
require_once 'auth.php';
if (!admin_can('customers')) { header('Location: index.php'); exit; }

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$error = '';
$success = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);
// ========== اکسل: خروجی / نمونه / ورود دسته‌جمعی ==========
if (isset($_GET['export']) && $_GET['export'] === 'xlsx') {
    $q = $pdo->query("SELECT username, first_name, last_name, mobile, company_name, job_title, gender, birth_date, created_at FROM users WHERE role = 'customer' ORDER BY id DESC");
    $rows = [['نام کاربری', 'نام', 'نام خانوادگی', 'شماره موبایل', 'نام شرکت', 'سمت', 'جنسیت', 'تاریخ تولد', 'تاریخ عضویت']];
    foreach ($q->fetchAll() as $u) {
        $rows[] = [
            $u['username'], $u['first_name'], $u['last_name'], $u['mobile'],
            $u['company_name'], $u['job_title'], $u['gender'], $u['birth_date'] ? portal_date_to_display($u['birth_date']) : '',
            fa_datetime($u['created_at'] ?? null),
        ];
    }
    excel_output('customers', $rows, 'مشتریان');
}
if (isset($_GET['sample']) && $_GET['sample'] === 'xlsx') {
    $rows = [
        ['نام کاربری', 'رمز عبور', 'نام', 'نام خانوادگی', 'شماره موبایل', 'نام شرکت', 'سمت', 'جنسیت', 'تاریخ تولد'],
        ['ali.rezaei', 'Alireza@123', 'علی', 'رضایی', '09121234567', 'شرکت نمونه', 'مدیر فروش', 'مرد', '1370/02/15'],
    ];
    excel_output('sample-customers', $rows, 'نمونه مشتریان');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'excel_import') {
    require_valid_csrf();
    $rows = excel_parse_upload($_FILES['excel_file'] ?? []);
    if (empty($rows)) {
        $error = 'فایل اکسل خوانده نشد یا خالی است.';
    } else {
        $result = excel_import_customers($rows, (string) ($_POST['default_password'] ?? ''));
        $success = $result['added'] . ' مشتری با موفقیت از اکسل وارد شد.';
        if (!empty($result['errors'])) {
            $error = count($result['errors']) . ' سطر نادیده گرفته شد: ' . implode(' | ', array_slice($result['errors'], 0, 6)) . (count($result['errors']) > 6 ? ' …' : '');
        }
        log_activity($_SESSION['user_id'], "ورود دسته‌جمعی مشتریان از اکسل: " . $result['added'] . " مورد");
    }
}


// Handle Delete FIRST — تا درخواست حذف وارد بلاک افزودن/ویرایش نشود
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $del_id = intval($_POST['delete_id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'customer'");
    $stmt->execute([$del_id]);

    // پاک‌سازی نظرسنجی‌های مرتبط با مشتری حذف‌شده
    $pdo->prepare("DELETE FROM survey_assignments WHERE customer_id = ?")->execute([$del_id]);

    log_activity($_SESSION['user_id'], "حذف مشتری ID: {$del_id}");
    $_SESSION['flash'] = 'مشتری با موفقیت حذف شد.';
    header('Location: customers.php');
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'excel_import') {
    // Handle Add / Edit form submission
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $mobile = fa_digits_to_en(trim($_POST['mobile'] ?? ''));
    $mobile = $mobile !== '' ? normalize_mobile_db($mobile) : null; // NULL تا ایندکس یکتا موبایل نشکند
    $company_name = trim($_POST['company_name'] ?? '');
    $job_title = trim($_POST['job_title'] ?? '');
    $birth_date = portal_date_to_db(trim($_POST['birth_date'] ?? '')); // شمسی → میلادی
    $gender = trim($_POST['gender'] ?? '');

    if (empty($username)) {
        $error = 'نام کاربری الزامی است.';
    } elseif ($mobile !== null && $mobile !== '' && mobile_exists($mobile, $id > 0 ? $id : null)) {
        // شماره موبایل نباید با هیچ کاربر دیگری (مشتری یا مدیر) یکسان باشد
        $error = 'این شماره موبایل قبلاً برای کاربر دیگری (مشتری یا مدیر) ثبت شده است.';
    } else {
        if ($id === 0) {
            // New customer
            $auto_password = '';
            if (empty($password)) {
                // رمز تصادفی امن به‌جای رمز پیش‌فرض ضعیف — یک‌بار به ادمین نمایش داده می‌شود
                $auto_password = bin2hex(random_bytes(6)); // ۱۲ کاراکتر hex
                $password = $auto_password;
            }
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare("INSERT INTO users (username, password, role, first_name, last_name, mobile, company_name, job_title, birth_date, gender) VALUES (?, ?, 'customer', ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$username, $hashed, $first_name, $last_name, $mobile, $company_name, $job_title, $birth_date, $gender]);
                $new_customer_id = $pdo->lastInsertId();

                // Save custom fields
                save_custom_fields_values('customer', $new_customer_id);

                log_activity($_SESSION['user_id'], "ثبت مشتری جدید: {$first_name} {$last_name} ({$username})");
                sms_trigger_welcome((int) $new_customer_id);
                $_SESSION['flash'] = 'مشتری جدید با موفقیت اضافه شد.'
                    . ($auto_password !== '' ? " — رمز عبور موقت: " . $auto_password . " (حتماً به مشتری اطلاع دهید)" : '');
                header('Location: customers.php');
                exit;
            } catch (Exception $e) {
                $error = 'خطا در ثبت مشتری (احتمالا نام کاربری تکراری است): ' . $e->getMessage();
            }
        } else {
            // Edit customer
            try {
                if (!empty($password)) {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, first_name = ?, last_name = ?, mobile = ?, company_name = ?, job_title = ?, birth_date = ?, gender = ? WHERE id = ? AND role = 'customer'");
                    $stmt->execute([$username, $hashed, $first_name, $last_name, $mobile, $company_name, $job_title, $birth_date, $gender, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, first_name = ?, last_name = ?, mobile = ?, company_name = ?, job_title = ?, birth_date = ?, gender = ? WHERE id = ? AND role = 'customer'");
                    $stmt->execute([$username, $first_name, $last_name, $mobile, $company_name, $job_title, $birth_date, $gender, $id]);
                }

                // Save custom fields
                save_custom_fields_values('customer', $id);

                log_activity($_SESSION['user_id'], "ویرایش اطلاعات مشتری ID: {$id}");
                $_SESSION['flash'] = 'اطلاعات مشتری با موفقیت ویرایش شد.';
                header('Location: customers.php');
                exit;
            } catch (Exception $e) {
                $error = 'خطا در ویرایش مشتری: ' . $e->getMessage();
            }
        }
    }
}

// Fetch customer for edit if action=edit
$edit_customer = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $edit_id = intval($_GET['id']);
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'customer'");
    $stmt->execute([$edit_id]);
    $edit_customer = $stmt->fetch();
    if (!$edit_customer) {
        $action = 'list';
    }
}

// Fetch all customers for list (با صفحه‌بندی)
$customers_total = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
$pi = pagination_info($customers_total, 15);
$customers_stmt = $pdo->query("SELECT * FROM users WHERE role = 'customer' ORDER BY id DESC LIMIT " . (int) $pi['per_page'] . " OFFSET " . (int) $pi['offset']);
$customers = $customers_stmt->fetchAll();
?>
<?php render_admin_header(
    'مدیریت مشتریان',
    'p-8 max-w-7xl w-full mx-auto space-y-6',
    '',
    ''
); ?>


            <?php if ($success): ?>
                <div class="alert alert-success" role="status"><?= icon('check') ?><span><?php echo htmlspecialchars($success); ?></span></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert"><?= icon('alert') ?><span><?php echo htmlspecialchars($error); ?></span></div>
            <?php endif; ?>

            <?php if ($action === 'list'): ?>
                <?php render_excel_toolbar([
                    'page' => 'customers.php',
                    'withSample' => true,
                    'withImport' => true,
                    'importHint' => 'ستون‌ها: نام کاربری*، رمز عبور، نام، نام خانوادگی، شماره موبایل، نام شرکت، سمت، جنسیت، تاریخ تولد.',
                    'importExtra' => '<div><label class="block text-xs text-slate-500 mb-1">رمز عبور پیش‌فرض (برای مشتریان بدون رمز)</label><input type="text" name="default_password" value="" placeholder="(خالی = رمز تصادفی)" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm"></div>',
                ]); ?>
            <?php endif; ?>

            <?php if ($action === 'add' || $action === 'edit'): ?>
                <!-- Add / Edit Form -->
                <div class="card p-6 md:p-8">
                    <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-200">
                        <h3 class="text-lg font-bold text-slate-800"><?php echo $action === 'edit' ? 'ویرایش اطلاعات مشتری' : 'تعریف مشتری جدید'; ?></h3>
                        <a href="customers.php" class="btn btn-ghost btn-sm"><?= icon('back') ?><span>بازگشت به لیست</span></a>
                    </div>

                    <form method="POST" class="space-y-6" novalidate><?php echo csrf_input(); ?>
<div class="form-error-summary" style="display:none" role="alert"></div>
                        <?php if ($edit_customer): ?>
                            <input type="hidden" name="id" value="<?php echo $edit_customer['id']; ?>">
                        <?php endif; ?>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="label" for="username">نام کاربری (برای ورود)<span class="required-star" aria-hidden="true">*</span></label>
                                <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($edit_customer['username'] ?? ''); ?>" required class="input"><p class="field-error" style="display:none"></p>
                            </div>
                            <div>
                                <label class="label" for="password">رمز عبور <?php echo $edit_customer ? '<span class="font-normal text-slate-400">(در صورت نیاز به تغییر)</span>' : '<span class="required-star" aria-hidden="true">*</span>'; ?></label>
                                <input type="password" name="password" id="password" <?php echo $edit_customer ? '' : 'required'; ?> class="input" placeholder="••••••••"><p class="field-error" style="display:none"></p>
                            </div>
                            <div>
                                <label class="label" for="first_name">نام</label>
                                <input type="text" name="first_name" id="first_name" value="<?php echo htmlspecialchars($edit_customer['first_name'] ?? ''); ?>" class="input">
                            </div>
                            <div>
                                <label class="label" for="last_name">نام خانوادگی</label>
                                <input type="text" name="last_name" id="last_name" value="<?php echo htmlspecialchars($edit_customer['last_name'] ?? ''); ?>" class="input">
                            </div>
                            <div>
                                <label class="label" for="mobile">شماره موبایل</label>
                                <input type="text" name="mobile" id="mobile" inputmode="tel" value="<?php echo htmlspecialchars($edit_customer['mobile'] ?? ''); ?>" class="input" placeholder="09123456789">
                            </div>
                            <div>
                                <label class="label" for="company_name">نام شرکت</label>
                                <input type="text" name="company_name" id="company_name" value="<?php echo htmlspecialchars($edit_customer['company_name'] ?? ''); ?>" class="input">
                            </div>
                            <div>
                                <label class="label" for="job_title">سمت سازمانی</label>
                                <input type="text" name="job_title" id="job_title" value="<?php echo htmlspecialchars($edit_customer['job_title'] ?? ''); ?>" class="input">
                            </div>
                            <div>
                                <label class="label" for="birth_date">تاریخ تولد</label>
                                <div class="flex gap-2 items-stretch">
                                    <input type="text" name="birth_date" id="birth_date" data-jdp data-jdp-max-date="today" readonly value="<?php echo htmlspecialchars(portal_date_to_display((string) ($edit_customer['birth_date'] ?? ''))); ?>" class="input cursor-pointer" placeholder="انتخاب تاریخ شمسی">
                                    <button type="button" class="jdp-trigger btn btn-secondary shrink-0" aria-label="انتخاب تاریخ" data-target="birth_date"><?= icon('calendar') ?><span>انتخاب تاریخ</span></button>
                                </div>
                            </div>
                            <div>
                                <label class="label" for="gender">جنسیت</label>
                                <select name="gender" id="gender" class="input cursor-pointer">
                                    <option value="">انتخاب کنید...</option>
                                    <option value="male" <?php echo ($edit_customer['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>مرد</option>
                                    <option value="female" <?php echo ($edit_customer['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>زن</option>
                                    <option value="other" <?php echo ($edit_customer['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>سایر</option>
                                </select>
                            </div>

                            <!-- Render Custom Fields dynamically -->
                            <?php echo render_custom_fields_inputs('customer', $edit_customer['id'] ?? 0); ?>
                        </div>

                        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3 pt-5 border-t border-slate-200">
                            <a href="customers.php" class="btn btn-secondary">انصراف</a>
                            <button type="submit" class="btn btn-primary"><?= icon('check') ?><span>ذخیره اطلاعات مشتری</span></button>
                        </div>
                        <div class="mobile-action-bar">
                            <a href="customers.php" class="btn btn-secondary">انصراف</a>
                            <button type="submit" class="btn btn-primary"><?= icon('check') ?><span>ذخیره</span></button>
                        </div>
                    </form>
                </div>

            <?php else: ?>
                <!-- Customer List View -->
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-lg font-bold text-slate-800">لیست مشتریان (<?php echo count($customers); ?>)</h3>
                    <a href="customers.php?action=add" class="btn btn-primary"><?= icon('plus') ?><span>افزودن مشتری جدید</span></a>
                </div>

                <div class="card overflow-hidden">
                    <div class="table-scroll">
                        <table class="table table-card-mobile">
                            <thead>
                                <tr>
                                    <th>نام و نام خانوادگی</th>
                                    <th>نام کاربری</th>
                                    <th>موبایل</th>
                                    <th>شرکت / سمت</th>
                                    <th>تاریخ تولد / جنسیت</th>
                                    <th class="text-center">عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($customers)): ?>
                                    <tr><td colspan="6"><?php echo empty_state('هیچ مشتری‌ای تعریف نشده است', 'برای شروع، اولین مشتری را اضافه کنید.', 'users'); ?></td></tr>
                                <?php else: ?>
                                    <?php foreach ($customers as $c): ?>
                                        <tr>
                                            <td data-label="نام و نام خانوادگی" class="font-medium text-slate-900">
                                                <?php echo htmlspecialchars(trim($c['first_name'] . ' ' . $c['last_name']) !== '' ? $c['first_name'] . ' ' . $c['last_name'] : 'تکمیل نشده'); ?>
                                            </td>
                                            <td data-label="نام کاربری" class="text-slate-600"><?php echo htmlspecialchars($c['username']); ?></td>
                                            <td data-label="موبایل" class="text-slate-600" dir="ltr"><?php echo htmlspecialchars($c['mobile'] ?: '-'); ?></td>
                                            <td data-label="شرکت / سمت" class="text-slate-600">
                                                <div><?php echo htmlspecialchars($c['company_name'] ?: '-'); ?></div>
                                                <div class="text-xs text-slate-400"><?php echo htmlspecialchars($c['job_title'] ?: ''); ?></div>
                                            </td>
                                            <td data-label="تولد / جنسیت" class="text-slate-600 text-xs">
                                                <div>تولد: <?php echo htmlspecialchars($c['birth_date'] ? portal_date_to_display($c['birth_date']) : '-'); ?></div>
                                                <div>جنسیت: <?php 
                                                    $g = $c['gender'];
                                                    echo $g === 'male' ? 'مرد' : ($g === 'female' ? 'زن' : ($g === 'other' ? 'سایر' : '-')); 
                                                ?></div>
                                            </td>
                                            <td data-label="عملیات" class="text-center">
                                                <div class="inline-flex items-center gap-1.5 cell-actions">
                                                    <a href="customers.php?action=edit&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-ghost !text-indigo-600"><?= icon('edit') ?><span>ویرایش</span></a>
                                                    <form method="POST" data-confirm-msg="آیا از حذف این مشتری اطمینان دارید؟ این عمل قابل بازگشت نیست."><input type="hidden" name="action" value="delete"><input type="hidden" name="delete_id" value="<?php echo (int)$c['id']; ?>"><?php echo csrf_input(); ?><button type="submit" class="btn btn-sm btn-outline-danger"><?= icon('trash') ?><span>حذف</span></button></form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php echo render_pagination($pi, 'customers.php'); ?>
                </div>
            <?php endif; ?>

        <?php render_admin_footer(); ?>