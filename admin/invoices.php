<?php
// admin/invoices.php - Admin Invoices Management
require_once 'auth.php';
if (!admin_can('invoices')) { header('Location: index.php'); exit; }
if (!is_module_enabled('invoices')) {
    header('Location: index.php');
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$error = '';
$success = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

// پیشنهاد شماره فاکتور یکتا برای فرم جدید (به‌جای rand که ممکن است تکراری شود)
$next_invoice_suggestion = $pdo->query("SELECT CONCAT('INV-', YEAR(CURDATE()), '-', LPAD(COALESCE(MAX(id),0)+1, 4, '0')) FROM invoices")->fetchColumn();
if (!$next_invoice_suggestion) { $next_invoice_suggestion = 'INV-' . date('Y') . '-0001'; }

// Handle Delete FIRST — تا درخواست حذف وارد بلاک افزودن/ویرایش نشود
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $del_id = intval($_POST['delete_id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM invoices WHERE id = ?");
    $stmt->execute([$del_id]);
    log_activity($_SESSION['user_id'], "حذف فاکتور ID: {$del_id}");
    $_SESSION['flash'] = 'فاکتور با موفقیت حذف شد.';
    header('Location: invoices.php');
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle Form Submission (افزودن / ویرایش)
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $customer_id = intval($_POST['customer_id'] ?? 0);
    $invoice_number = trim($_POST['invoice_number'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $amount = normalize_money_input((string) ($_POST['amount'] ?? ''));
    $due_date = portal_date_to_db(trim((string) ($_POST['due_date'] ?? '')));
    $status = in_array($_POST['status'] ?? 'unpaid', ['unpaid', 'paid', 'cancelled'], true) ? $_POST['status'] : 'unpaid';

    if ($amount === null) {
        $error = 'مبلغ فاکتور باید فقط شامل عدد و حداکثر دو رقم اعشار باشد.';
    } elseif ((float) $amount <= 0) {
        $error = 'مبلغ فاکتور باید بزرگتر از صفر باشد.';
    } elseif (empty($title) || empty($invoice_number) || $customer_id <= 0) {
        $error = 'انتخاب مشتری، شماره فاکتور و عنوان فاکتور الزامی است.';
    } else {
        if ($id === 0) {
            try {
                $stmt = $pdo->prepare("INSERT INTO invoices (customer_id, invoice_number, title, amount, due_date, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$customer_id, $invoice_number, $title, $amount, $due_date, $status]);
            } catch (PDOException $e) {
                $error = 'این شماره فاکتور قبلاً استفاده شده است یا خطایی در ثبت رخ داد. شماره دیگری انتخاب کنید.';
            }
        }
        if ($id === 0 && $error === '') {
            log_activity($_SESSION['user_id'], "صدور فاکتور جدید شماره: {$invoice_number}");
            sms_trigger_invoice_created((int) $pdo->lastInsertId());
            $_SESSION['flash'] = 'فاکتور جدید با موفقیت ایجاد و به مشتری منتصب شد.';
            header('Location: invoices.php');
            exit;
        } elseif ($id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE invoices SET customer_id = ?, invoice_number = ?, title = ?, amount = ?, due_date = ?, status = ? WHERE id = ?");
                $stmt->execute([$customer_id, $invoice_number, $title, $amount, $due_date, $status, $id]);
            } catch (PDOException $e) {
                $error = 'این شماره فاکتور قبلاً استفاده شده است. شماره دیگری انتخاب کنید.';
            }
            if ($error === '') {
                log_activity($_SESSION['user_id'], "ویرایش فاکتور ID: {$id}");
                $_SESSION['flash'] = 'فاکتور با موفقیت ویرایش شد.';
                header('Location: invoices.php');
                exit;
            }
        }
    }
}

// Handle Delete (moved to top)

$edit_invoice = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $edit_id = intval($_GET['id']);
    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_invoice = $stmt->fetch();
    if (!$edit_invoice) $action = 'list';
}

// Fetch customers dropdown
$customers = $pdo->query("SELECT id, first_name, last_name, username, company_name FROM users WHERE role = 'customer' ORDER BY first_name ASC")->fetchAll();

// Fetch invoices list (با صفحه‌بندی)
$invoices_total = (int) $pdo->query("SELECT COUNT(*) FROM invoices")->fetchColumn();
$pi = pagination_info($invoices_total, 15);
$invoices = $pdo->query("
    SELECT i.*, u.first_name, u.last_name, u.username, u.company_name 
    FROM invoices i 
    JOIN users u ON i.customer_id = u.id 
    ORDER BY i.id DESC LIMIT " . (int) $pi['per_page'] . " OFFSET " . (int) $pi['offset'] . "
")->fetchAll();
?>
<?php render_admin_header(
    'مدیریت فاکتورها و صورتحساب‌ها',
    'portal-page-main portal-admin-page portal-invoices-page p-8 max-w-7xl w-full mx-auto space-y-6',
    '',
    ''
); ?>


            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($action === 'add' || $action === 'edit'): ?>
                <div class="card portal-form-card portal-invoice-form-card">
                    <div class="portal-form-header flex items-center justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-bold"><?php echo $action === 'edit' ? 'ویرایش فاکتور' : 'صدور فاکتور جدید'; ?></h3>
                            <p class="portal-form-subtitle">صورتحساب را به مشتری اختصاص دهید و وضعیت پرداخت را ثبت کنید.</p>
                        </div>
                        <a href="invoices.php" class="btn btn-secondary portal-form-back">بازگشت به لیست</a>
                    </div>

                    <form method="POST" class="portal-form-body space-y-6" novalidate><?php echo csrf_input(); ?>
                    <div class="form-error-summary" style="display:none" role="alert"></div>
                        <?php if ($edit_invoice): ?>
                            <input type="hidden" name="id" value="<?php echo $edit_invoice['id']; ?>">
                        <?php endif; ?>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <label class="label" for="invoice_customer">انتصاب به مشتری *</label>
                                <select id="invoice_customer" name="customer_id" required class="input portal-form-control">
                                    <option value="">انتخاب مشتری...</option>
                                    <?php foreach ($customers as $c): ?>
                                        <option value="<?php echo $c['id']; ?>" <?php echo (($edit_invoice['customer_id'] ?? '') == $c['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars(trim($c['first_name'] . ' ' . $c['last_name']) !== '' ? $c['first_name'] . ' ' . $c['last_name'] . ' (' . $c['username'] . ')' : $c['username']); ?>
                                            <?php echo $c['company_name'] ? ' - ' . htmlspecialchars($c['company_name']) : ''; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="label" for="invoice_number">شماره فاکتور *</label>
                                <input id="invoice_number" type="text" name="invoice_number" value="<?php echo htmlspecialchars($edit_invoice['invoice_number'] ?? $next_invoice_suggestion); ?>" required dir="ltr" class="input portal-form-control value-ltr">
                            </div>
                            <div>
                                <label class="label" for="invoice_title">عنوان فاکتور *</label>
                                <input id="invoice_title" type="text" name="title" value="<?php echo htmlspecialchars($edit_invoice['title'] ?? ''); ?>" required class="input portal-form-control">
                            </div>
                            <div>
                                <label class="label" for="invoice_amount">مبلغ (تومان) *</label>
                                <input id="invoice_amount" type="text" name="amount" value="<?php echo htmlspecialchars($edit_invoice['amount'] ?? ''); ?>" required dir="ltr" class="input portal-form-control value-ltr" placeholder="مثلا: ۵,۰۰۰,۰۰۰">
                            </div>
                            <div>
                                <label class="label" for="due_date">سررسید پرداخت</label>
                                <div class="flex flex-wrap sm:flex-nowrap gap-2 items-stretch">
                                    <input type="text" name="due_date" id="due_date" data-jdp data-jdp-min-date="today" readonly dir="ltr" value="<?php echo htmlspecialchars($edit_invoice['due_date'] ?? ''); ?>" class="input portal-form-control value-ltr flex-1 min-w-0 cursor-pointer" placeholder="انتخاب تاریخ شمسی">
                                    <button type="button" class="btn btn-secondary jdp-trigger shrink-0" data-target="due_date"><?= icon('calendar') ?><span>انتخاب تاریخ</span></button>
                                </div>
                            </div>
                            <div class="md:col-span-2">
                                <label class="label" for="invoice_status">وضعیت فاکتور</label>
                                <select id="invoice_status" name="status" class="input portal-form-control">
                                    <option value="unpaid" <?php echo (($edit_invoice['status'] ?? 'unpaid') === 'unpaid') ? 'selected' : ''; ?>>پرداخت نشده</option>
                                    <option value="paid" <?php echo (($edit_invoice['status'] ?? '') === 'paid') ? 'selected' : ''; ?>>پرداخت شده</option>
                                    <option value="cancelled" <?php echo (($edit_invoice['status'] ?? '') === 'cancelled') ? 'selected' : ''; ?>>لغو شده</option>
                                </select>
                            </div>
                        </div>

                        <div class="portal-form-actions flex justify-end gap-3">
                            <a href="invoices.php" class="btn btn-secondary">انصراف</a>
                            <button type="submit" class="btn btn-primary">ذخیره فاکتور</button>
                        </div>
                    </form>
                </div>

            <?php else: ?>
                <div class="portal-list-toolbar flex items-center justify-between gap-4">
                    <h3 class="text-lg font-bold text-slate-800">لیست فاکتورها (<?php echo count($invoices); ?>)</h3>
                    <a href="invoices.php?action=add" class="btn btn-primary">
                        <?= icon('plus') ?><span>صدور فاکتور جدید</span>
                    </a>
                </div>

                <div class="card portal-list-card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="table table-card-mobile">
                            <thead class="bg-slate-50 text-slate-500 text-xs font-semibold">
                                <tr>
                                    <th class="p-4 min-w-[13rem]">شماره / عنوان فاکتور</th>
                                    <th class="p-4">مشتری</th>
                                    <th class="p-4">مبلغ</th>
                                    <th class="p-4">سررسید</th>
                                    <th class="p-4">وضعیت</th>
                                    <th class="p-4 text-center min-w-[11rem]">عملیات</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (empty($invoices)): ?>
                                    <tr>
                                        <td colspan="6" class="p-8 text-center text-slate-400"><?php echo empty_state('هیچ فاکتوری صادر نشده است.', '', 'info'); ?></td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($invoices as $inv): ?>
                                        <tr class="hover:bg-slate-50 transition">
                                            <td data-label="شماره و عنوان" class="p-4 min-w-[13rem]">
                                                <div class="flex-1 min-w-0 text-end">
                                                    <div class="font-bold text-slate-900 value-ltr whitespace-nowrap" dir="ltr" title="<?= htmlspecialchars($inv['invoice_number']) ?>"><?php echo htmlspecialchars($inv['invoice_number']); ?></div>
                                                    <div class="text-xs text-slate-500"><bdi dir="auto"><?php echo htmlspecialchars($inv['title']); ?></bdi></div>
                                                </div>
                                            </td>
                                            <td class="p-4 text-slate-700">
                                                <div><?php echo htmlspecialchars(trim($inv['first_name'] . ' ' . $inv['last_name']) !== '' ? $inv['first_name'] . ' ' . $inv['last_name'] : $inv['username']); ?></div>
                                                <div class="text-xs text-slate-400"><?php echo htmlspecialchars($inv['company_name'] ?: ''); ?></div>
                                            </td>
                                            <td data-label="مبلغ" class="p-4 font-semibold text-slate-900"><span class="value-ltr" dir="ltr"><?php echo htmlspecialchars(number_format((float) $inv['amount'], 0, '.', ',')); ?></span> <span dir="rtl">تومان</span></td>
                                            <td class="p-4 text-xs text-slate-600 value-ltr" dir="ltr"><?php echo htmlspecialchars($inv['due_date'] ?: '-'); ?></td>
                                            <td data-label="وضعیت" class="p-4">
                                                <?php 
                                                    $st = $inv['status'];
                                                    if ($st === 'paid') echo '<span class="bg-emerald-50 text-emerald-700 text-xs px-2.5 py-1 rounded-full font-medium">پرداخت شده</span>';
                                                    elseif ($st === 'unpaid') echo '<span class="bg-amber-50 text-amber-700 text-xs px-2.5 py-1 rounded-full font-medium">پرداخت نشده</span>';
                                                    else echo '<span class="bg-slate-100 text-slate-600 text-xs px-2.5 py-1 rounded-full font-medium">لغو شده</span>';
                                                ?>
                                            </td>
                                            <td data-label="عملیات" class="p-4 min-w-[11rem]"><div class="cell-actions flex flex-wrap items-center justify-center gap-2">
                                                <a href="invoices.php?action=edit&id=<?php echo $inv['id']; ?>" class="btn btn-sm btn-ghost !text-indigo-600">ویرایش</a>
                                                <form method="POST" data-confirm-msg="آیا از حذف این مورد اطمینان دارید؟"><input type="hidden" name="action" value="delete"><input type="hidden" name="delete_id" value="<?php echo (int)$inv['id']; ?>"><?php echo csrf_input(); ?><button type="submit" class="btn btn-sm btn-outline-danger">حذف</button></form>
                                            </div></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php echo render_pagination($pi, 'invoices.php'); ?>
                </div>
            <?php endif; ?>

        <?php render_admin_footer(); ?>