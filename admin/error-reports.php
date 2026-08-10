<?php
// admin/error-reports.php — گزارش‌های خطای ارسال‌شده از دکمه شناور
require_once 'auth.php';
if (!admin_can('error_reports')) { header('Location: index.php'); exit; }

$msg = '';
$err = '';

// تغییر وضعیت (جدید / در حال بررسی / حل‌شده)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'status') {
    $rid = (int) ($_POST['report_id'] ?? 0);
    $st = $_POST['status'] ?? '';
    if (in_array($st, ['new', 'reviewing', 'resolved'], true)) {
        $pdo->prepare("UPDATE error_reports SET status = ? WHERE id = ?")->execute([$st, $rid]);
        $msg = 'وضعیت گزارش بروزرسانی شد.';
    }
}
// حذف
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $rid = (int) ($_POST['delete_id'] ?? 0);
    $pdo->prepare("DELETE FROM error_reports WHERE id = ?")->execute([$rid]);
    $msg = 'گزارش حذف شد.';
}

$reports = error_reports_list(200);
$counts = ['new' => 0, 'reviewing' => 0, 'resolved' => 0];
foreach ($reports as $r) { $counts[$r['status']] = ($counts[$r['status']] ?? 0) + 1; }

render_admin_header('گزارش‌های خطا', 'p-8 max-w-7xl w-full mx-auto space-y-6');
?>

<?php if ($msg): ?><div class="alert alert-success" role="status"><?= icon('check') ?><span><?= htmlspecialchars($msg) ?></span></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger" role="alert"><?= icon('alert') ?><span><?= htmlspecialchars($err) ?></span></div><?php endif; ?>

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    <div class="card p-5 flex items-center justify-between">
        <div><p class="body-sm text-slate-500">جدید</p><h4 class="text-2xl font-bold text-red-600 mt-1"><?= $counts['new'] ?></h4></div>
        <div class="w-11 h-11 rounded-xl bg-red-50 text-red-600 flex items-center justify-center"><?= icon('alert','w-5 h-5') ?></div>
    </div>
    <div class="card p-5 flex items-center justify-between">
        <div><p class="body-sm text-slate-500">در حال بررسی</p><h4 class="text-2xl font-bold text-amber-600 mt-1"><?= $counts['reviewing'] ?></h4></div>
        <div class="w-11 h-11 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center"><?= icon('eye','w-5 h-5') ?></div>
    </div>
    <div class="card p-5 flex items-center justify-between">
        <div><p class="body-sm text-slate-500">حل‌شده</p><h4 class="text-2xl font-bold text-emerald-600 mt-1"><?= $counts['resolved'] ?></h4></div>
        <div class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center"><?= icon('check','w-5 h-5') ?></div>
    </div>
</div>

<div class="card overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100">
        <h3 class="font-semibold text-slate-800 text-sm">گزارش‌های خطای دریافتی (<?= count($reports) ?>)</h3>
    </div>
    <?php if (empty($reports)): ?>
        <?php echo empty_state('هنوز گزارش خطایی ثبت نشده است', 'گزارش‌های ارسال‌شده از دکمه شناور «گزارش خطا» در اینجا نمایش داده می‌شوند.', 'alert'); ?>
    <?php else: ?>
    <div class="table-scroll">
        <table class="table table-card-mobile">
            <thead>
                <tr>
                    <th>#</th>
                    <th>گزارش‌دهنده</th>
                    <th>نقش</th>
                    <th>صفحه</th>
                    <th>پیام</th>
                    <th>وضعیت</th>
                    <th>زمان</th>
                    <th class="text-center">عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reports as $r):
                    $name = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
                    $name = $name !== '' ? $name : ($r['reporter_name'] ?: ($r['username'] ?? 'کاربر'));
                    $role = $r['reporter_role'] === 'super_admin' ? 'سوپر ادمین' : ($r['reporter_role'] === 'admin' ? 'مدیر' : ($r['reporter_role'] === 'customer' ? 'مشتری' : 'کاربر'));
                    $st = $r['status'];
                    $stBadge = $st === 'new' ? '<span class="badge badge-danger">جدید</span>' : ($st === 'reviewing' ? '<span class="badge badge-warning">در حال بررسی</span>' : '<span class="badge badge-success">حل‌شده</span>');
                ?>
                <tr>
                    <td data-label="#" class="text-slate-400"><?= $r['id'] ?></td>
                    <td data-label="گزارش‌دهنده" class="font-medium text-slate-800"><?= htmlspecialchars($name) ?></td>
                    <td data-label="نقش"><?= htmlspecialchars($role) ?></td>
                    <td data-label="صفحه" class="text-xs text-slate-500 max-w-[160px] truncate" title="<?= htmlspecialchars($r['url']) ?>" dir="ltr"><?= htmlspecialchars($r['url']) ?></td>
                    <td data-label="پیام" class="text-slate-600 max-w-[240px]"><div class="line-clamp-2"><?= htmlspecialchars(mb_substr($r['message'], 0, 200)) ?></div></td>
                    <td data-label="وضعیت"><?= $stBadge ?></td>
                    <td data-label="زمان" class="text-xs text-slate-500 whitespace-nowrap"><?= htmlspecialchars(fa_datetime($r['created_at'])) ?></td>
                    <td data-label="عملیات">
                        <div class="inline-flex items-center gap-1.5 flex-wrap">
                            <form method="post">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="status">
                                <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                                <select name="status" onchange="this.form.submit()" class="input !w-auto !h-9 text-xs cursor-pointer">
                                    <option value="new" <?= $st === 'new' ? 'selected' : '' ?>>جدید</option>
                                    <option value="reviewing" <?= $st === 'reviewing' ? 'selected' : '' ?>>در حال بررسی</option>
                                    <option value="resolved" <?= $st === 'resolved' ? 'selected' : '' ?>>حل‌شده</option>
                                </select>
                            </form>
                            <form method="post" data-confirm-msg="این گزارش حذف شود؟">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="delete_id" value="<?= $r['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger"><?= icon('trash') ?><span>حذف</span></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php render_admin_footer(); ?>
