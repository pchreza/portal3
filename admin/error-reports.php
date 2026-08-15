<?php
// admin/error-reports.php — گزارش‌های خطای ارسال‌شده از دکمه شناور
require_once 'auth.php';
if (!admin_can('error_reports')) { header('Location: index.php'); exit; }

// اگر جدول به هر دلیل وجود نداشت (نصب قدیمی/بازیابی‌شده)، همین‌جا ساخته شود تا صفحه خطای مرگبار ندهد
try {
    portal_ensure_error_reports_table($pdo);
} catch (Throwable $t) {
    error_log('[Portal ErrorReports] ' . $t->getMessage());
    die('خطا در دسترسی به جدول گزارش‌های خطا. لاگ سرور را بررسی کنید.');
}

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

render_admin_header('گزارش‌های خطا', 'portal-page-main portal-admin-page portal-error-reports-page p-8 max-w-7xl w-full mx-auto space-y-6');
?>

<?php if ($msg): ?><div class="alert alert-success" role="status"><?= icon('check') ?><span><?= htmlspecialchars($msg) ?></span></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger" role="alert"><?= icon('alert') ?><span><?= htmlspecialchars($err) ?></span></div><?php endif; ?>

<div class="portal-error-report-stats grid grid-cols-1 sm:grid-cols-3 gap-4">
    <div class="portal-stat-card card p-5 flex items-center justify-between">
        <div><p class="body-sm text-slate-500">جدید</p><h4 class="text-2xl font-bold text-red-600 mt-1"><?= $counts['new'] ?></h4></div>
        <div class="w-11 h-11 rounded-xl bg-red-50 text-red-600 flex items-center justify-center"><?= icon('alert','w-5 h-5') ?></div>
    </div>
    <div class="portal-stat-card card p-5 flex items-center justify-between">
        <div><p class="body-sm text-slate-500">در حال بررسی</p><h4 class="text-2xl font-bold text-amber-600 mt-1"><?= $counts['reviewing'] ?></h4></div>
        <div class="w-11 h-11 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center"><?= icon('eye','w-5 h-5') ?></div>
    </div>
    <div class="portal-stat-card card p-5 flex items-center justify-between">
        <div><p class="body-sm text-slate-500">حل‌شده</p><h4 class="text-2xl font-bold text-emerald-600 mt-1"><?= $counts['resolved'] ?></h4></div>
        <div class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center"><?= icon('check','w-5 h-5') ?></div>
    </div>
</div>

<div class="portal-list-card card overflow-hidden">
    <div class="portal-list-toolbar px-6 py-4 border-b border-slate-100">
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
                            <button type="button" class="btn btn-sm btn-secondary" data-report-open="<?= (int) $r['id'] ?>"><?= icon('eye') ?><span>جزئیات</span></button>
                            <form method="post">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="status">
                                <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                                <select name="status" data-auto-submit aria-label="تغییر وضعیت گزارش" class="input portal-form-control !w-auto !h-9 text-xs cursor-pointer">
                                    <option value="new" <?= $st === 'new' ? 'selected' : '' ?>>جدید</option>
                                    <option value="reviewing" <?= $st === 'reviewing' ? 'selected' : '' ?>>در حال بررسی</option>
                                    <option value="resolved" <?= $st === 'resolved' ? 'selected' : '' ?>>حل‌شده</option>
                                </select>
                            </form>
                            <form method="post" data-confirm-msg="این گزارش حذف شود؟">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="delete_id" value="<?= $r['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><?= icon('trash') ?><span>حذف</span></button>
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

<?php $reports_json = json_encode(array_map(static function ($r) {
    $name = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
    return [
        'id'         => (int) $r['id'],
        'reporter'   => $name !== '' ? $name : (($r['reporter_name'] ?? '') ?: ($r['username'] ?? '—')),
        'role'       => (string) ($r['reporter_role'] ?? '—'),
        'url'        => (string) ($r['url'] ?? ''),
        'message'    => (string) ($r['message'] ?? ''),
        'status'     => (string) ($r['status'] ?? 'new'),
        'created_at' => fa_datetime($r['created_at']),
    ];
}, $reports), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>

<!-- پاپ‌آپ جزئیات کامل گزارش خطا -->
<div id="report-detail-modal" role="dialog" aria-modal="true" aria-labelledby="report-detail-title" tabindex="-1" class="hidden fixed inset-0 z-[2000] items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50" data-report-close></div>
    <div class="portal-error-detail-modal relative w-full max-w-lg card !rounded-2xl p-6 shadow-2xl">
        <div class="flex items-start justify-between mb-4">
            <div class="flex items-center gap-3">
                <span class="w-11 h-11 rounded-xl bg-red-50 text-red-600 flex items-center justify-center"><?= icon('alert','w-5 h-5') ?></span>
                <div>
                    <h3 id="report-detail-title" class="text-lg font-bold text-slate-900">جزئیات گزارش خطا</h3>
                    <p class="text-xs text-slate-500 mt-0.5">گزارش <span id="rd-id" class="font-mono"></span></p>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-ghost" data-report-close aria-label="بستن"><?= icon('x') ?></button>
        </div>
        <dl class="space-y-3 text-sm">
            <div class="flex gap-3 border-b border-slate-100 pb-3"><dt class="w-24 shrink-0 text-slate-500">گزارش‌دهنده</dt><dd id="rd-reporter" class="text-slate-800 font-medium"></dd></div>
            <div class="flex gap-3 border-b border-slate-100 pb-3"><dt class="w-24 shrink-0 text-slate-500">نقش</dt><dd id="rd-role" class="text-slate-800"></dd></div>
            <div class="flex gap-3 border-b border-slate-100 pb-3"><dt class="w-24 shrink-0 text-slate-500">صفحه</dt><dd id="rd-url" class="text-slate-800 break-all" dir="ltr" style="text-align:left"></dd></div>
            <div class="flex gap-3 border-b border-slate-100 pb-3"><dt class="w-24 shrink-0 text-slate-500">زمان</dt><dd id="rd-time" class="text-slate-800"></dd></div>
            <div class="flex gap-3"><dt class="w-24 shrink-0 text-slate-500">شرح خطا</dt><dd id="rd-message" class="text-slate-800 whitespace-pre-wrap"></dd></div>
        </dl>
        <div class="flex justify-end mt-5 pt-4 border-t border-slate-100">
            <button type="button" class="btn btn-secondary" data-report-close>بستن</button>
        </div>
    </div>
</div>
<script nonce="<?= e(portal_csp_nonce()) ?>">
window.__reportData = <?= $reports_json ?>;
var lastReportTrigger = null;
function openReportDetail(id, trigger){
    var d = window.__reportData || [], r = null;
    for (var i = 0; i < d.length; i++) { if (d[i].id === id) { r = d[i]; break; } }
    if (!r) return;
    lastReportTrigger = trigger || null;
    document.getElementById('rd-id').textContent      = '#' + r.id;
    document.getElementById('rd-reporter').textContent = r.reporter;
    document.getElementById('rd-role').textContent     = r.role;
    document.getElementById('rd-url').textContent      = r.url;
    document.getElementById('rd-time').textContent     = r.created_at;
    document.getElementById('rd-message').textContent  = r.message;
    document.getElementById('report-detail-modal').classList.remove('hidden');
    document.getElementById('report-detail-modal').classList.add('flex');
    document.getElementById('report-detail-modal').focus();
    document.body.style.overflow = 'hidden';
}
function closeReportDetail(){
    document.getElementById('report-detail-modal').classList.add('hidden');
    document.getElementById('report-detail-modal').classList.remove('flex');
    document.body.style.overflow = '';
    if (lastReportTrigger) { lastReportTrigger.focus(); lastReportTrigger = null; }
}
document.querySelectorAll('[data-report-open]').forEach(function(button){button.addEventListener('click',function(){openReportDetail(Number(button.dataset.reportOpen), button);});});
document.querySelectorAll('[data-report-close]').forEach(function(button){button.addEventListener('click',closeReportDetail);});
document.querySelectorAll('[data-auto-submit]').forEach(function(select){select.addEventListener('change',function(){select.form.submit();});});
document.addEventListener('keydown', function(e){
    if (e.key === 'Escape' && !document.getElementById('report-detail-modal').classList.contains('hidden')) closeReportDetail();
});
</script>

<?php render_admin_footer(); ?>
