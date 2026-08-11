<?php
// customer/notifications.php — اعلانات من (لیست، خواندن، حذف)
require_once 'auth.php';

$uid = (int) $_SESSION['user_id'];
$msg = '';
$err = '';

// علامت‌گذاری یک اعلان به‌عنوان خوانده‌شده (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'read') {
    mark_notification_read($uid, (int) ($_POST['recipient_id'] ?? 0));
    unset($_SESSION['notif_bell_cache']); // باطل‌سازی کش زنگوله
    header('Location: notifications.php');
    exit;
}

// علامت‌گذاری همه به‌عنوان خوانده‌شده (POST با CSRF — قبلاً GET بود)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'read_all') {
    require_valid_csrf();
    mark_all_notifications_read($uid);
    unset($_SESSION['notif_bell_cache']); // باطل‌سازی کش زنگوله
    header('Location: notifications.php');
    exit;
}

$notifications = get_user_notifications($uid, 100);
$unread = unread_notifications_count($uid);

render_customer_header(
    'اعلانات من',
    'p-8 max-w-5xl w-full mx-auto space-y-6',
    '',
    $unread > 0 ? '<form method="POST" class="inline">' . csrf_input() . '<input type="hidden" name="action" value="read_all"><button type="submit" class="btn btn-sm btn-secondary">' . icon('check') . '<span>خواندن همه</span></button></form>' : ''
);
?>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-lg font-bold text-slate-800">اعلانات من (<?= count($notifications) ?>)</h3>
                <?php if ($unread > 0): ?>
                    <span class="badge badge-warning"><?= $unread ?> اعلان خوانده‌نشده</span>
                <?php endif; ?>
            </div>

            <?php if (empty($notifications)): ?>
                <?php echo empty_state('هنوز اعلانی برای شما ارسال نشده است', 'اعلان‌های مهم دربارهٔ پروژه‌ها، فاکتورها و تیکت‌ها در اینجا نمایش داده می‌شوند.', 'bell'); ?>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($notifications as $n): ?>
                        <div class="card card-hover p-5 flex items-start gap-4 <?= $n['is_read'] ? '' : 'border-indigo-300' ?>">
                            <div class="w-11 h-11 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center flex-shrink-0"><?= notification_type_icon($n['ntype']) ?></div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-3">
                                    <h4 class="font-bold text-slate-900 break-words flex items-start gap-2 <?= $n['is_read'] ? '' : 'text-indigo-900' ?>">
                                        <?php if (!$n['is_read']): ?><span class="w-2 h-2 rounded-full bg-red-500 mt-2 flex-shrink-0" aria-hidden="true"></span><?php endif; ?>
                                        <span><?= htmlspecialchars($n['title']) ?></span>
                                    </h4>
                                    <span class="text-xs text-slate-400 whitespace-nowrap flex-shrink-0"><?= htmlspecialchars(fa_datetime($n['created_at'])) ?></span>
                                </div>
                                <?php if ($n['body']): ?>
                                    <p class="body-sm text-slate-600 leading-relaxed mt-1.5 whitespace-pre-line break-words"><?= nl2br(htmlspecialchars($n['body'])) ?></p>
                                <?php endif; ?>
                                <div class="flex items-center gap-2 mt-3 flex-wrap">
                                    <?= notification_type_badge($n['ntype']) ?>
                                    <?php if ($n['is_read']): ?>
                                        <span class="text-xs text-slate-400">خوانده شده <?= $n['read_at'] ? 'در ' . htmlspecialchars(fa_datetime($n['read_at'])) : '' ?></span>
                                    <?php else: ?>
                                        <form method="post">
                                            <?php echo csrf_input(); ?>
                                            <input type="hidden" name="action" value="read">
                                            <input type="hidden" name="recipient_id" value="<?= (int) $n['recipient_id'] ?>">
                                            <button class="btn btn-sm btn-ghost !text-indigo-600"><?= icon('check') ?><span>خوانده شد</span></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php render_customer_footer();
