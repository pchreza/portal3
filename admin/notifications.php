<?php
// admin/notifications.php — مدیریت اعلانات و اطلاع‌رسانی (ارسال، فیلتر، آمار خواندن)
require_once 'auth.php';
if (!admin_can('notifications')) { header('Location: index.php'); exit; }

$msg = '';
$err = '';

// ---------- پردازش فرم‌ها ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';

    if ($a === 'send') {
        $title  = trim($_POST['title'] ?? '');
        $body   = trim($_POST['body'] ?? '');
        $ntype  = $_POST['ntype'] ?? 'info';
        $target = $_POST['target_type'] ?? 'all';
        $expires_at = trim($_POST['expires_at'] ?? '');
        // دیت پیکر شمسی است؛ تاریخ شمسی به میلادی تبدیل شود تا مقایسه با NOW() درست باشد
        if ($expires_at !== '') {
            $expires_conv = jalali_to_gregorian_str($expires_at);
            if ($expires_conv !== null) {
                $expires_at = $expires_conv . ' 23:59:59';
            }
        }

        // انتخاب مشتریان خاص (اگر target=custom)
        $custom_ids = [];
        if ($target === 'custom') {
            $custom_ids = $_POST['custom_user_ids'] ?? [];
            if (!is_array($custom_ids)) {
                $custom_ids = [$custom_ids];
            }
        }

        if ($title === '') {
            $err = 'عنوان اعلان الزامی است.';
        } else {
            $nid = send_notification($title, $body, $ntype, $target, '', $custom_ids, $_SESSION['user_id'], $expires_at ?: null);
            if ($nid) {
                $count = count(notification_recipient_ids($target, '', $custom_ids));
                log_activity($_SESSION['user_id'], "ارسال اعلان: {$title} به {$count} مشتری");
                $msg = "اعلان با موفقیت ارسال شد و برای {$count} مشتری فعال گردید.";
            } else {
                $err = 'خطا در ارسال اعلان. لطفاً دوباره تلاش کنید.';
            }
        }
    } elseif ($a === 'delete') {
        $nid = (int) ($_POST['delete_id'] ?? 0);
        $pdo->prepare("DELETE FROM notifications WHERE id = ?")->execute([$nid]);
        log_activity($_SESSION['user_id'], "حذف اعلان ID: {$nid}");
        $msg = 'اعلان با موفقیت حذف شد.';
    } elseif ($a === 'toggle') {
        $nid = (int) ($_POST['notification_id'] ?? 0);
        $q = $pdo->prepare("SELECT is_active FROM notifications WHERE id = ?");
        $q->execute([$nid]);
        $val = $q->fetchColumn();
        $cur = ($val === false) ? 1 : (int) $val; // ستون موجود نباشد → فرض فعال
        $new = $cur ? 0 : 1;
        $pdo->prepare("UPDATE notifications SET is_active = ? WHERE id = ?")->execute([$new, $nid]);
        $msg = $new ? 'اعلان فعال شد.' : 'اعلان غیرفعال شد.';
    }
}

// ---------- داده‌های صفحه ----------
$view_id = (int) ($_GET['view'] ?? 0);
$view_notification = null;
$view_recipients = [];
$view_stats = ['total' => 0, 'read' => 0, 'unread' => 0];

if ($view_id) {
    $q = $pdo->prepare("SELECT n.*, u.first_name, u.last_name, u.username FROM notifications n LEFT JOIN users u ON u.id = n.created_by WHERE n.id = ?");
    $q->execute([$view_id]);
    $view_notification = $q->fetch();
    if ($view_notification) {
        $view_recipients = notification_recipients_list($view_id);
        $view_stats = notification_read_stats($view_id);
    } else {
        $view_id = 0;
        $err = 'اعلان مورد نظر یافت نشد.';
    }
}

// لیست اعلان‌ها + آمار هرکدام
$notifications = $pdo->query(
    "SELECT n.*, u.first_name, u.last_name, u.username,
            (SELECT COUNT(*) FROM notification_recipients nr WHERE nr.notification_id = n.id) total,
            (SELECT COALESCE(SUM(nr.is_read),0) FROM notification_recipients nr WHERE nr.notification_id = n.id) read_count
     FROM notifications n
     LEFT JOIN users u ON u.id = n.created_by
     ORDER BY n.created_at DESC
     LIMIT 100"
)->fetchAll();

// مشتریان برای انتخاب دستی
$customers_all = $pdo->query("SELECT id, first_name, last_name, username FROM users WHERE role = 'customer' ORDER BY first_name ASC")->fetchAll();

render_admin_header('مدیریت اعلانات و اطلاع‌رسانی', 'portal-page-main portal-admin-page portal-notifications-page p-8 max-w-7xl w-full mx-auto space-y-6');
?>

            <?php if ($msg): ?>
                <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>
            <?php if ($err): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
            <?php endif; ?>

            <?php if ($view_id && $view_notification): ?>
                <!-- ===== جزئیات یک اعلان ===== -->
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-slate-800">جزئیات اعلان: <bdi dir="auto"><?= htmlspecialchars($view_notification['title']) ?></bdi></h3>
                    <a href="notifications.php" class="text-sm text-slate-600 bg-slate-100 hover:bg-slate-200 px-4 py-2 rounded-lg font-medium">بازگشت به لیست</a>
                </div>

                <div class="card portal-notification-detail p-6 space-y-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-center gap-3 flex-wrap">
                            <?= notification_type_badge($view_notification['ntype']) ?>
                            <?php if (!empty($view_notification['is_active'])): ?>
                                <span class="bg-emerald-50 text-emerald-700 text-xs px-2.5 py-1 rounded-full font-medium">فعال</span>
                            <?php else: ?>
                                <span class="bg-slate-100 text-slate-600 text-xs px-2.5 py-1 rounded-full">غیرفعال</span>
                            <?php endif; ?>
                        </div>
                        <span class="text-xs text-slate-500 value-ltr whitespace-nowrap" dir="ltr"><?= htmlspecialchars(fa_datetime($view_notification['created_at'])) ?></span>
                    </div>
                    <div class="text-slate-600 text-sm leading-relaxed whitespace-pre-line break-words overflow-hidden">
                        <bdi dir="auto"><?= nl2br(htmlspecialchars($view_notification['body'] ?: '-')) ?></bdi>
                    </div>
                    <div class="text-xs text-slate-500 space-y-1 border-t border-slate-100 pt-3">
                        <div>هدف ارسال: <b><bdi dir="auto"><?= htmlspecialchars(notification_target_label($view_notification['target_type'])) ?></bdi></b></div>
                        <?php if ($view_notification['expires_at']): ?>
                            <div>تاریخ انقضا: <b class="value-ltr whitespace-nowrap" dir="ltr"><?= htmlspecialchars(fa_datetime($view_notification['expires_at'])) ?></b></div>
                        <?php endif; ?>
                        <div>فرستنده: <b><bdi dir="auto"><?= htmlspecialchars(trim($view_notification['first_name'] . ' ' . $view_notification['last_name']) ?: $view_notification['username'] ?: 'سیستم') ?></bdi></b></div>
                    </div>
                </div>

                <!-- آمار خواندن -->
                <div class="portal-notification-stats grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="portal-stat-card card p-5 flex items-center justify-between">
                        <div><p class="body-sm text-slate-500">کل دریافت‌کنندگان</p><h4 class="text-2xl font-bold text-slate-900 mt-1 value-ltr" dir="ltr"><?= $view_stats['total'] ?></h4></div>
                        <div class="w-11 h-11 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center"><?= icon('users', 'w-5 h-5') ?></div>
                    </div>
                    <div class="portal-stat-card card p-5 flex items-center justify-between">
                        <div><p class="body-sm text-slate-500">خوانده‌شده</p><h4 class="text-2xl font-bold text-emerald-600 mt-1 value-ltr" dir="ltr"><?= $view_stats['read'] ?></h4></div>
                        <div class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center"><?= icon('check', 'w-5 h-5') ?></div>
                    </div>
                    <div class="portal-stat-card card p-5 flex items-center justify-between">
                        <div><p class="body-sm text-slate-500">نخوانده</p><h4 class="text-2xl font-bold text-amber-600 mt-1 value-ltr" dir="ltr"><?= $view_stats['unread'] ?></h4></div>
                        <div class="w-11 h-11 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center"><?= icon('alert', 'w-5 h-5') ?></div>
                    </div>
                </div>

                <!-- لیست گیرندگان -->
                <div class="card portal-list-card overflow-hidden">
                    <div class="portal-list-toolbar p-4 border-b border-slate-100">
                        <h4 class="font-semibold text-slate-800 text-sm">گیرندگان (<?= count($view_recipients) ?>)</h4>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table table-card-mobile">
                            <thead class="bg-slate-50 text-slate-500 text-xs font-semibold">
                                <tr>
                                    <th class="p-4">مشتری</th>
                                    <th class="p-4">نام کاربری</th>
                                    <th class="p-4">وضعیت</th>
                                    <th class="p-4">زمان خواندن</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (empty($view_recipients)): ?>
                                    <tr><td colspan="4" class="p-6 text-center text-slate-400">این اعلان برای هیچ‌کس ارسال نشده است.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($view_recipients as $r): ?>
                                        <tr>
                                            <td data-label="مشتری" class="font-medium text-slate-800"><bdi dir="auto"><?= htmlspecialchars(trim($r['first_name'] . ' ' . $r['last_name']) ?: $r['username']) ?></bdi></td>
                                            <td data-label="نام کاربری" class="text-slate-500 text-xs value-ltr" dir="ltr"><?= htmlspecialchars($r['username']) ?></td>
                                            <td data-label="وضعیت">
                                                <?php if ($r['is_read']): ?>
                                                    <span class="badge badge-success"><?= icon('check','w-3.5 h-3.5') ?> خوانده شده</span>
                                                <?php else: ?>
                                                    <span class="badge badge-warning">نخوانده</span>
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="زمان خواندن" class="text-xs text-slate-500 value-ltr whitespace-nowrap" dir="ltr"><?= htmlspecialchars(fa_datetime($r['read_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php else: ?>
                <!-- ===== فرم ارسال اعلان جدید (بازطراحی FULLMASTER) ===== -->
                <div class="card portal-notification-compose overflow-hidden">
                    <div class="px-6 py-5 border-b border-slate-100 portal-panel-heading">
                        <h3 class="text-base font-bold text-slate-800">ارسال اعلان جدید</h3>
                        <p class="text-sm text-slate-500 mt-1.5 leading-relaxed">برای همه مشتریان یا یک گروه خاص، با فیلترهای هوشمند</p>
                    </div>
                    <form method="post" class="p-6 space-y-6" id="notification-form" novalidate>
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="action" value="send">
                        <div class="form-error-summary" style="display:none" role="alert"></div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="label" for="nt_title">عنوان اعلان<span class="required-star" aria-hidden="true">*</span></label>
                                <input type="text" name="title" id="nt_title" required dir="auto" maxlength="255" placeholder="مثلا: اطلاعیه مهم درباره تعمیرات سیستم" class="input">
                            </div>
                            <div>
                                <label class="label" for="n_type">نوع اعلان</label>
                                <select name="ntype" id="n_type" class="input cursor-pointer">
                                    <?php foreach (notification_types() as $k => $v): ?>
                                        <option value="<?= $k ?>"><?= htmlspecialchars($v['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="label" for="nt_body">متن اعلان</label>
                                <textarea name="body" id="nt_body" rows="4" dir="auto" placeholder="متن کامل اطلاعیه را بنویسید..." class="input"></textarea>
                            </div>
                            <div>
                                <label class="label" for="target_type">هدف ارسال (فیلتر)</label>
                                <select name="target_type" id="target_type" class="input cursor-pointer">
                                    <?php foreach (notification_targets() as $k => $v): ?>
                                        <option value="<?= $k ?>"><?= $v ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="label" for="expires_at">تاریخ انقضا (اختیاری)</label>
                                <div class="flex flex-wrap sm:flex-nowrap gap-2 items-stretch">
                                    <input type="text" name="expires_at" id="expires_at" data-jdp readonly dir="ltr" class="value-ltr flex-1 min-w-0 input cursor-pointer" placeholder="پس از این تاریخ نمایش داده نشود">
                                    <button type="button" class="jdp-trigger btn btn-secondary shrink-0" aria-label="انتخاب تاریخ" data-target="expires_at"><?= icon('calendar') ?></button>
                                </div>
                            </div>
                        </div>

                        <!-- انتخاب دستی مشتریان -->
                        <div id="custom-users-box" class="portal-recipient-panel hidden rounded-xl p-5 border border-slate-200">
                            <span class="block text-sm font-medium text-slate-700 mb-2">انتخاب مشتریان خاص<span class="required-star" aria-hidden="true">*</span></span>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2 max-h-64 overflow-y-auto">
                                <?php foreach ($customers_all as $c): ?>
                                                <label class="portal-recipient-option flex items-center gap-2 text-sm rounded-lg px-3 py-2 cursor-pointer transition">
                                        <input type="checkbox" name="custom_user_ids[]" value="<?= $c['id'] ?>" class="w-4 h-4 text-indigo-600 rounded border-slate-300">
                                        <span class="truncate"><bdi dir="auto"><?= htmlspecialchars(trim($c['first_name'] . ' ' . $c['last_name']) ?: $c['username']) ?></bdi></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="text-xs text-slate-400 mt-2">برای انتخاب همه، از دکمه‌های زیر استفاده کنید:</p>
                            <div class="flex gap-3 mt-1">
                                <button type="button" data-select-all-users class="text-xs text-indigo-600 font-medium hover:underline cursor-pointer">انتخاب همه</button>
                                <button type="button" data-clear-all-users class="text-xs text-slate-500 font-medium hover:underline cursor-pointer">پاک‌کردن</button>
                            </div>
                        </div>

                        <div class="desktop-form-actions flex flex-col-reverse sm:flex-row sm:justify-end gap-3 pt-2">
                            <button class="btn btn-primary btn-lg"><?= icon('send') ?><span>ارسال اعلان</span></button>
                        </div>
                        <div class="mobile-action-bar"><button class="btn btn-primary btn-lg"><?= icon('send') ?><span>ارسال اعلان</span></button></div>
                    </form>
                </div>

                <script nonce="<?= e(portal_csp_nonce()) ?>">
                    // نمایش/مخفی‌کردن انتخاب دستی مشتریان بر اساس هدف
                    (function(){
                        var sel = document.getElementById('target_type');
                        var box = document.getElementById('custom-users-box');
                        if (!sel || !box) return;
                        function toggle(){
                            box.classList.toggle('hidden', sel.value !== 'custom');
                            box.querySelectorAll('input[type=checkbox]').forEach(function(c){ c.required = (sel.value === 'custom'); });
                        }
                        sel.addEventListener('change', toggle);
                        var selectAll = document.querySelector('[data-select-all-users]');
                        var clearAll = document.querySelector('[data-clear-all-users]');
                        if (selectAll) selectAll.addEventListener('click', function(){box.querySelectorAll('input[type=checkbox]').forEach(function(c){c.checked=true;});});
                        if (clearAll) clearAll.addEventListener('click', function(){box.querySelectorAll('input[type=checkbox]').forEach(function(c){c.checked=false;});});
                        toggle();
                    })();
                </script>

                <!-- ===== لیست اعلان‌های ارسال‌شده ===== -->
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-slate-800">اعلان‌های ارسال‌شده (<?= count($notifications) ?>)</h3>
                </div>

                <div class="card portal-list-card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="table table-card-mobile">
                            <thead class="bg-slate-50 text-slate-500 text-xs font-semibold">
                                <tr>
                                    <th class="p-4 min-w-[15rem]">عنوان</th>
                                    <th class="p-4">نوع</th>
                                    <th class="p-4">هدف</th>
                                    <th class="p-4">دریافت</th>
                                    <th class="p-4">خوانده</th>
                                    <th class="p-4">وضعیت</th>
                                    <th class="p-4 min-w-[9rem]">تاریخ</th>
                                    <th class="p-4 text-center min-w-[18rem]">عملیات</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (empty($notifications)): ?>
                                    <tr><td colspan="8" class="p-8 text-center text-slate-400"><?php echo empty_state('هنوز اعلانی ارسال نشده است.', '', 'info'); ?></td></tr>
                                <?php else: ?>
                                    <?php foreach ($notifications as $n): ?>
                                        <?php
                                            $total = (int) $n['total'];
                                            $read = (int) $n['read_count'];
                                            $pct = $total ? round($read / $total * 100) : 0;
                                        ?>
                                        <tr>
                                            <td data-label="عنوان" class="font-medium text-slate-900 max-w-[220px]">
                                                <a href="notifications.php?view=<?= $n['id'] ?>" class="hover:text-indigo-600 block truncate" title="<?= htmlspecialchars($n['title']) ?>"><bdi dir="auto"><?= htmlspecialchars($n['title']) ?></bdi></a>
                                            </td>
                                            <td data-label="نوع"><?= notification_type_badge($n['ntype']) ?></td>
                                            <td data-label="هدف" class="text-xs text-slate-500"><bdi dir="auto"><?= htmlspecialchars(notification_target_label($n['target_type'])) ?></bdi></td>
                                            <td data-label="دریافت" class="text-slate-700 value-ltr" dir="ltr"><?= $total ?></td>
                                            <td data-label="خوانده">
                                                <div class="flex items-center gap-2 whitespace-nowrap">
                                                    <div class="w-16 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                                        <div class="h-full bg-emerald-500 rounded-full" style="width:<?= $pct ?>%"></div>
                                                    </div>
                                                    <span class="text-xs text-slate-500 value-ltr" dir="ltr"><?= $read ?>/<?= $total ?> (<?= $pct ?>%)</span>
                                                </div>
                                            </td>
                                            <td data-label="وضعیت">
                                                <?php if (!empty($n['is_active'])): ?>
                                                    <span class="badge badge-success">فعال</span>
                                                <?php else: ?>
                                                    <span class="badge badge-muted">غیرفعال</span>
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="تاریخ" class="text-xs text-slate-500 value-ltr whitespace-nowrap" dir="ltr"><?= htmlspecialchars(fa_datetime($n['created_at'])) ?></td>
                                            <td data-label="عملیات" class="min-w-[18rem]">
                                                <div class="cell-actions flex flex-wrap items-center justify-center gap-1.5">
                                                    <a href="notifications.php?view=<?= $n['id'] ?>" class="btn btn-sm btn-ghost !text-indigo-600"><?= icon('eye') ?><span>جزئیات</span></a>
                                                    <form method="post" data-confirm-msg="با حذف این اعلان، برای همه مشتریان حذف می‌شود. ادامه؟">
                                                        <?php echo csrf_input(); ?>
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="delete_id" value="<?= $n['id'] ?>">
                                                        <button class="btn btn-sm btn-outline-danger"><?= icon('trash') ?><span>حذف</span></button>
                                                    </form>
                                                    <form method="post">
                                                        <?php echo csrf_input(); ?>
                                                        <input type="hidden" name="action" value="toggle">
                                                        <input type="hidden" name="notification_id" value="<?= $n['id'] ?>">
                                                        <button title="فعال/غیرفعال" class="btn btn-sm btn-secondary"><?= !empty($n['is_active']) ? icon('x') : icon('check') ?><span><?= !empty($n['is_active']) ? 'غیرفعال‌سازی' : 'فعال‌سازی' ?></span></button>
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
            <?php endif; ?>

        <?php render_admin_footer();
