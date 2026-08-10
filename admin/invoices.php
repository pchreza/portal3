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
    $amount = trim($_POST['amount'] ?? '');
    $due_date = trim($_POST['due_date'] ?? '');
    $status = trim($_POST['status'] ?? 'unpaid');

    if (empty($title) || empty($invoice_number) || $customer_id <= 0) {
        $error = 'انتخاب مشتری، شماره فاکتور و عنوان فاکتور الزامی است.';
    } else {
        if ($id === 0) {
            $stmt = $pdo->prepare("INSERT INTO invoices (customer_id, invoice_number, title, amount, due_date, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$customer_id, $invoice_number, $title, $amount, $due_date, $status]);
            log_activity($_SESSION['user_id'], "صدور فاکتور جدید شماره: {$invoice_number}");
            sms_trigger_invoice_created((int) $pdo->lastInsertId());
            $_SESSION['flash'] = 'فاکتور جدید با موفقیت ایجاد و به مشتری منتصب شد.';
            header('Location: invoices.php');
            exit;
        } else {
            $stmt = $pdo->prepare("UPDATE invoices SET customer_id = ?, invoice_number = ?, title = ?, amount = ?, due_date = ?, status = ? WHERE id = ?");
            $stmt->execute([$customer_id, $invoice_number, $title, $amount, $due_date, $status, $id]);
            log_activity($_SESSION['user_id'], "ویرایش فاکتور ID: {$id}");
            $_SESSION['flash'] = 'فاکتور با موفقیت ویرایش شد.';
            header('Location: invoices.php');
            exit;
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
    'p-8 max-w-7xl w-full mx-auto space-y-6',
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
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8">
                    <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-200">
                        <h3 class="text-lg font-bold text-slate-800"><?php echo $action === 'edit' ? 'ویرایش فاکتور' : 'صدور فاکتور جدید'; ?></h3>
                        <a href="invoices.php" class="text-sm text-slate-500 hover:text-slate-700">بازگشت به لیست</a>
                    </div>

                    <form method="POST" class="space-y-6" novalidate><?php echo csrf_input(); ?>
                    <div class="form-error-summary" style="display:none" role="alert"></div>
                        <?php if ($edit_invoice): ?>
                            <input type="hidden" name="id" value="<?php echo $edit_invoice['id']; ?>">
                        <?php endif; ?>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">انتصاب به مشتری *</label>
                                <select name="customer_id" required class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm bg-white">
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
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">شماره فاکتور *</label>
                                <input type="text" name="invoice_number" value="<?php echo htmlspecialchars($edit_invoice['invoice_number'] ?? 'INV-' . rand(1000, 9999)); ?>" required class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">عنوان فاکتور *</label>
                                <input type="text" name="title" value="<?php echo htmlspecialchars($edit_invoice['title'] ?? ''); ?>" required class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">مبلغ (تومان) *</label>
                                <input type="text" name="amount" value="<?php echo htmlspecialchars($edit_invoice['amount'] ?? ''); ?>" required class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm" placeholder="مثلا: ۵,۰۰۰,۰۰۰">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">سررسید پرداخت</label>
                                <div class="flex gap-2 items-stretch">
                                    <input type="text" name="due_date" id="due_date" data-jdp data-jdp-min-date="today" readonly value="<?php echo htmlspecialchars($edit_invoice['due_date'] ?? ''); ?>" class="flex-1 min-w-0 px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm bg-white cursor-pointer" placeholder="انتخاب تاریخ شمسی">
                                    <button type="button" class="jdp-trigger shrink-0 inline-flex items-center gap-1.5 text-xs font-medium text-slate-600 bg-slate-100 hover:bg-slate-200 border border-slate-300 px-3 py-2 rounded-lg transition cursor-pointer" data-target="due_date"><?= icon('calendar') ?><span>انتخاب تاریخ</span></button>
                                </div>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">وضعیت فاکتور</label>
                                <select name="status" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm bg-white">
                                    <option value="unpaid" <?php echo (($edit_invoice['status'] ?? 'unpaid') === 'unpaid') ? 'selected' : ''; ?>>پرداخت نشده</option>
                                    <option value="paid" <?php echo (($edit_invoice['status'] ?? '') === 'paid') ? 'selected' : ''; ?>>پرداخت شده</option>
                                    <option value="cancelled" <?php echo (($edit_invoice['status'] ?? '') === 'cancelled') ? 'selected' : ''; ?>>لغو شده</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
                            <a href="invoices.php" class="btn btn-secondary">انصراف</a>
                            <button type="submit" class="btn btn-primary">ذخیره فاکتور</button>
                        </div>
                    </form>
                </div>

            <?php else: ?>
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-slate-800">لیست فاکتورها (<?php echo count($invoices); ?>)</h3>
                    <a href="invoices.php?action=add" class="btn btn-primary">
                        <?= icon('plus') ?><span>صدور فاکتور جدید</span>
                    </a>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="table table-card-mobile">
                            <thead class="bg-slate-50 text-slate-500 text-xs font-semibold">
                                <tr>
                                    <th class="p-4">شماره / عنوان فاکتور</th>
                                    <th class="p-4">مشتری</th>
                                    <th class="p-4">مبلغ</th>
                                    <th class="p-4">سررسید</th>
                                    <th class="p-4">وضعیت</th>
                                    <th class="p-4 text-center">عملیات</th>
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
                                            <td class="p-4">
                                                <div class="font-bold text-slate-900"><?php echo htmlspecialchars($inv['invoice_number']); ?></div>
                                                <div class="text-xs text-slate-500"><?php echo htmlspecialchars($inv['title']); ?></div>
                                            </td>
                                            <td class="p-4 text-slate-700">
                                                <div><?php echo htmlspecialchars(trim($inv['first_name'] . ' ' . $inv['last_name']) !== '' ? $inv['first_name'] . ' ' . $inv['last_name'] : $inv['username']); ?></div>
                                                <div class="text-xs text-slate-400"><?php echo htmlspecialchars($inv['company_name'] ?: ''); ?></div>
                                            </td>
                                            <td class="p-4 font-semibold text-slate-900"><?php echo htmlspecialchars($inv['amount']); ?> تومان</td>
                                            <td class="p-4 text-xs text-slate-600"><?php echo htmlspecialchars($inv['due_date'] ?: '-'); ?></td>
                                            <td class="p-4">
                                                <?php 
                                                    $st = $inv['status'];
                                                    if ($st === 'paid') echo '<span class="bg-emerald-50 text-emerald-700 text-xs px-2.5 py-1 rounded-full font-medium">پرداخت شده</span>';
                                                    elseif ($st === 'unpaid') echo '<span class="bg-amber-50 text-amber-700 text-xs px-2.5 py-1 rounded-full font-medium">پرداخت نشده</span>';
                                                    else echo '<span class="bg-slate-100 text-slate-600 text-xs px-2.5 py-1 rounded-full font-medium">لغو شده</span>';
                                                ?>
                                            </td>
                                            <td class="p-4 text-center space-x-2 space-x-reverse">
                                                <a href="invoices.php?action=edit&id=<?php echo $inv['id']; ?>" class="btn btn-sm btn-ghost !text-indigo-600">ویرایش</a>
                                                <form method="POST" style="display:inline" data-confirm-msg="آیا از حذف این مورد اطمینان دارید؟"><input type="hidden" name="action" value="delete"><input type="hidden" name="delete_id" value="<?php echo (int)$inv['id']; ?>"><?php echo csrf_input(); ?><button type="submit" class="btn btn-sm btn-outline-danger">حذف</button></form>
                                            </td>
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