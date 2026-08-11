<?php
// admin/ticket-departments.php — مدیریت دپارتمان‌های تیکت پشتیبانی
require_once 'auth.php';
if (!admin_can('ticket_departments')) { header('Location: index.php'); exit; }
if (!is_module_enabled('tickets')) { header('Location: index.php'); exit; }

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';

    if ($a === 'add') {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if ($name === '') {
            $err = 'نام دپارتمان الزامی است.';
        } else {
            $q = $pdo->prepare("INSERT INTO ticket_departments (name, description, sort_order) VALUES (?, ?, ?)");
            $q->execute([$name, $desc, (int) ($_POST['sort_order'] ?? 0)]);
            $msg = 'دپارتمان با موفقیت اضافه شد.';
        }
    } elseif ($a === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $err = 'نام دپارتمان الزامی است.';
        } else {
            $q = $pdo->prepare("UPDATE ticket_departments SET name = ?, description = ?, sort_order = ? WHERE id = ?");
            $q->execute([$name, trim($_POST['description'] ?? ''), (int) ($_POST['sort_order'] ?? 0), $id]);
            $msg = 'دپارتمان ویرایش شد.';
        }
    } elseif ($a === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $q = $pdo->prepare("SELECT is_active FROM ticket_departments WHERE id = ?");
        $q->execute([$id]);
        $cur = (int) $q->fetchColumn();
        $pdo->prepare("UPDATE ticket_departments SET is_active = ? WHERE id = ?")->execute([$cur ? 0 : 1, $id]);
        $msg = $cur ? 'دپارتمان غیرفعال شد.' : 'دپارتمان فعال شد.';
    } elseif ($a === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        // تیکت‌های این دپارتمان به «عمومی» برمی‌گردند
        $pdo->prepare("UPDATE tickets SET department_id = NULL WHERE department_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM ticket_departments WHERE id = ?")->execute([$id]);
        $msg = 'دپارتمان حذف شد.';
    }
}

$departments = $pdo->query("SELECT d.*, (SELECT COUNT(*) FROM tickets t WHERE t.department_id = d.id) ticket_count FROM ticket_departments d ORDER BY d.sort_order ASC, d.id ASC")->fetchAll();

render_admin_header('مدیریت دپارتمان‌های تیکت', 'p-8 max-w-4xl w-full mx-auto space-y-6');
?>

            <?php if ($msg): ?>
                <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>
            <?php if ($err): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
            <?php endif; ?>

            <!-- فرم افزودن -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                <h3 class="text-lg font-bold text-slate-800 mb-4">افزودن دپارتمان جدید</h3>
                <form method="post" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action" value="add">
                    <div class="md:col-span-1">
                        <label class="label" for="td_name">نام دپارتمان<span class="required-star" aria-hidden="true">*</span></label>
                        <input type="text" name="name" id="td_name" required class="input">
                    </div>
                    <div class="md:col-span-2">
                        <label class="label" for="td_desc">توضیحات</label>
                        <input type="text" name="description" id="td_desc" class="input">
                    </div>
                    <div>
                        <label class="label" for="td_sort">ترتیب</label>
                        <input type="number" name="sort_order" id="td_sort" value="0" dir="ltr" inputmode="numeric" class="value-ltr input">
                    </div>
                    <div class="md:col-span-4 flex justify-end">
                        <button class="btn btn-primary"><?= icon('plus') ?><span>افزودن</span></button>
                    </div>
                </form>
            </div>

            <!-- لیست دپارتمان‌ها -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="table table-card-mobile">
                        <thead class="bg-slate-50 text-slate-500 text-xs font-semibold">
                            <tr>
                                <th class="p-4">نام</th>
                                <th class="p-4">توضیحات</th>
                                <th class="p-4">ترتیب</th>
                                <th class="p-4">تیکت‌ها</th>
                                <th class="p-4">وضعیت</th>
                                <th class="p-4 text-center min-w-[11rem]">عملیات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($departments)): ?>
                                <tr><td colspan="6" class="p-6 text-center text-slate-400">دپارتمانی ثبت نشده است.</td></tr>
                            <?php else: ?>
                                <?php foreach ($departments as $dep): ?>
                                    <tr class="hover:bg-slate-50 transition">
                                        <td class="p-4 font-medium text-slate-900"><?= htmlspecialchars($dep['name']) ?></td>
                                        <td class="p-4 text-xs text-slate-500"><?= htmlspecialchars($dep['description'] ?: '-') ?></td>
                                        <td data-label="ترتیب" class="p-4 text-slate-600 value-ltr" dir="ltr"><?= (int) $dep['sort_order'] ?></td>
                                        <td data-label="تیکت‌ها" class="p-4 text-slate-600 value-ltr" dir="ltr"><?= (int) $dep['ticket_count'] ?></td>
                                        <td class="p-4">
                                            <?php if ($dep['is_active']): ?>
                                                <span class="bg-emerald-50 text-emerald-700 text-xs px-2.5 py-1 rounded-full font-medium">فعال</span>
                                            <?php else: ?>
                                                <span class="bg-slate-100 text-slate-600 text-xs px-2.5 py-1 rounded-full">غیرفعال</span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="عملیات" class="p-4 min-w-[11rem]">
                                            <div class="cell-actions flex flex-wrap items-center justify-center gap-2">
                                                <form method="post">
                                                    <?php echo csrf_input(); ?>
                                                    <input type="hidden" name="action" value="toggle">
                                                    <input type="hidden" name="id" value="<?= $dep['id'] ?>">
                                                    <button class="btn btn-sm btn-ghost !text-slate-700 whitespace-nowrap"><?= $dep['is_active'] ? 'غیرفعال' : 'فعال' ?></button>
                                                </form>
                                                <form method="post" data-confirm-msg="حذف شود؟ تیکت‌های این دپارتمان به «عمومی» منتقل می‌شوند.">
                                                    <?php echo csrf_input(); ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= $dep['id'] ?>">
                                                    <button class="btn btn-sm btn-outline-danger">حذف</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php render_admin_footer();
