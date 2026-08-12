<?php
// customer/gamification.php — کیف امتیاز و پاداش مشتری
require_once 'auth.php';
gamification_require_enabled();

$customerId = (int) ($_SESSION['user_id'] ?? 0);
$success = '';
$error = '';
$issuedReward = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'redeem_bonus') {
            $result = gamification_redeem_bonus_code($customerId, (string) ($_POST['bonus_code'] ?? ''));
            log_activity($customerId, 'ثبت موفق کد هدیهٔ Gamification');
            $success = 'کد هدیه پذیرفته شد و ' . gamification_points_label($result['points']) . ' به موجودی شما اضافه شد.';
        } elseif ($action === 'redeem_reward') {
            $rewardId = (int) ($_POST['reward_id'] ?? 0);
            $issuedReward = gamification_redeem_reward($customerId, $rewardId, (string) ($_POST['reward_nonce'] ?? ''));
            $_SESSION['gamification_reward_nonce'][$rewardId] = bin2hex(random_bytes(16));
            log_activity($customerId, 'دریافت پاداش Gamification: ' . $issuedReward['reward_id']);
            $success = 'پاداش شما آماده شد. کد را در سایت مقصد وارد کنید.';
        }
    } catch (Throwable $e) {
        error_log('[Customer Gamification] ' . $e->getMessage());
        $error = $e instanceof PDOException ? 'عملیات انجام نشد. لطفاً دوباره تلاش کنید.' : $e->getMessage();
    }
}

$summary = gamification_customer_summary($customerId);
$rewards = gamification_list_rewards($customerId);
foreach ($rewards as &$reward) {
    if (!isset($_SESSION['gamification_reward_nonce'][(int) $reward['id']])) {
        $_SESSION['gamification_reward_nonce'][(int) $reward['id']] = bin2hex(random_bytes(16));
    }
    $reward['nonce'] = $_SESSION['gamification_reward_nonce'][(int) $reward['id']];
}
unset($reward);

render_customer_header('امتیازها و پاداش‌ها', 'p-8 max-w-6xl w-full mx-auto space-y-6', '', '');
?>
            <?php if ($success): ?><div class="alert alert-success" role="status"><?= icon('check') ?><span><?= e($success) ?></span></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= icon('alert') ?><span><?= e($error) ?></span></div><?php endif; ?>

            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4"><div><h2 class="text-2xl font-bold text-slate-900">امتیازها و پاداش‌های من</h2><p class="text-sm text-slate-500 mt-1">برای فعالیت‌های ارزشمند امتیاز بگیرید و آن را به کد تخفیف تبدیل کنید.</p></div><div class="card px-6 py-4 bg-indigo-600 text-white"><span class="text-sm text-indigo-100">موجودی قابل‌مصرف</span><strong class="block text-3xl mt-1" dir="ltr"><?= number_format($summary['wallet']['balance']) ?></strong><span class="text-xs text-indigo-100">امتیاز</span></div></div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4"><div class="card p-5"><span class="text-sm text-slate-500">کل امتیاز کسب‌شده</span><strong class="block text-2xl text-emerald-600 mt-2" dir="ltr"><?= number_format($summary['wallet']['total_earned']) ?></strong></div><div class="card p-5"><span class="text-sm text-slate-500">کل امتیاز مصرف‌شده</span><strong class="block text-2xl text-amber-600 mt-2" dir="ltr"><?= number_format($summary['wallet']['total_spent']) ?></strong></div><div class="card p-5"><span class="text-sm text-slate-500">روش‌های کسب امتیاز</span><strong class="block text-2xl text-indigo-600 mt-2" dir="ltr"><?= count(gamification_event_catalog()) ?></strong></div></div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6"><section class="card p-5 space-y-4"><div><h3 class="text-lg font-bold">کد هدیه دارید؟</h3><p class="text-sm text-slate-500 mt-1">کد نمایشگاه یا کمپین را وارد کنید؛ هر کد فقط طبق policy همان کمپین پذیرفته می‌شود.</p></div><form method="post" class="flex flex-col sm:flex-row gap-3"><?= csrf_input() ?><input type="hidden" name="action" value="redeem_bonus"><input class="input flex-1" name="bonus_code" required maxlength="64" dir="ltr" autocomplete="off" placeholder="EXPO2026"><button class="btn btn-primary" type="submit">ثبت کد هدیه</button></form></section><section class="card p-5"><h3 class="text-lg font-bold">چطور امتیاز بگیرم؟</h3><div class="mt-3 space-y-2 text-sm text-slate-600"><?php foreach (gamification_event_catalog() as $eventKey => $event): $rule = gamification_rule($eventKey); if (!$rule || !(int) $rule['is_active'] || (int) $rule['points'] <= 0) continue; ?><div class="flex items-center justify-between gap-3 border-b last:border-0 py-2"><span><?= e($event['title']) ?></span><strong class="text-indigo-600"><?= gamification_points_label((int) $rule['points']) ?></strong></div><?php endforeach; ?></div></section></div>

            <section class="card p-5 space-y-4"><div><h3 class="text-lg font-bold">پاداش‌های قابل‌دریافت</h3><p class="text-sm text-slate-500 mt-1">پس از دریافت، کد و لینک سایت مقصد در همین صفحه نمایش داده می‌شود.</p></div><div class="grid grid-cols-1 md:grid-cols-2 gap-4"><?php if (!$rewards): ?><p class="text-slate-500">هنوز پاداشی برای دریافت تنظیم نشده است.</p><?php endif; ?><?php foreach ($rewards as $reward): $canRedeem = (int) $summary['wallet']['balance'] >= (int) $reward['points_cost'] && (int) $reward['is_active'] && (int) $reward['site_active'] && ((string) $reward['coupon_mode'] === 'fixed' || (int) $reward['available_codes'] > 0) && (int) ($reward['customer_redemptions'] ?? 0) < (int) $reward['max_per_customer']; ?><div class="border rounded-2xl p-5 flex flex-col gap-3"><div class="flex items-start justify-between gap-3"><div><h4 class="font-bold text-slate-900"><?= e($reward['title']) ?></h4><p class="text-sm text-slate-500 mt-1"><?= e($reward['description']) ?></p></div><span class="badge badge-primary whitespace-nowrap"><?= gamification_points_label((int) $reward['points_cost']) ?></span></div><div class="text-xs text-slate-500">سایت مقصد: <b><?= e($reward['site_name']) ?></b> · اعتبار: <?= (int) $reward['valid_days'] > 0 ? (int) $reward['valid_days'] . ' روز' : 'بدون انقضا' ?></div><?php if ((int) ($reward['customer_redemptions'] ?? 0) >= (int) $reward['max_per_customer']): ?><p class="text-xs text-amber-700 bg-amber-50 rounded-lg p-2">سقف دریافت این پاداش برای شما تکمیل شده است.</p><?php elseif ((string) $reward['coupon_mode'] === 'pool' && (int) $reward['available_codes'] <= 0): ?><p class="text-xs text-slate-500 bg-slate-50 rounded-lg p-2">کد این پاداش موقتاً موجود نیست.</p><?php endif; ?><form method="post" data-confirm-msg="با کسر <?= e(gamification_points_label((int) $reward['points_cost'])) ?> این پاداش صادر می‌شود. ادامه می‌دهید؟" data-confirm-title="تأیید دریافت پاداش" data-confirm-ok-label="دریافت پاداش" data-confirm-tone="primary"><?= csrf_input() ?><input type="hidden" name="action" value="redeem_reward"><input type="hidden" name="reward_id" value="<?= (int) $reward['id'] ?>"><input type="hidden" name="reward_nonce" value="<?= e($reward['nonce']) ?>"><button class="btn btn-primary w-full" type="submit" <?= $canRedeem ? '' : 'disabled' ?>><?= icon('star') ?><span><?= $canRedeem ? 'دریافت پاداش' : 'امکان دریافت نیست' ?></span></button></form></div><?php endforeach; ?></div></section>

            <?php if ($issuedReward): ?><section class="card p-6 border-2 border-emerald-200 bg-emerald-50 space-y-4" aria-live="polite"><div><h3 class="text-lg font-bold text-emerald-900">پاداش شما آماده است</h3><p class="text-sm text-emerald-800 mt-1"><?= e(get_setting('gamification_store_message', 'کد تخفیف را هنگام خرید در سایت مقصد وارد کنید.')) ?></p></div><div class="bg-white rounded-xl p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"><span class="text-sm text-slate-500">کد تخفیف</span><code class="text-xl font-bold text-slate-900" dir="ltr"><?= e($issuedReward['coupon_code']) ?></code></div><div class="flex flex-wrap gap-3"><a class="btn btn-primary" href="<?= e($issuedReward['redirect_url']) ?>" target="_blank" rel="noopener noreferrer"><?= icon('link') ?><span>رفتن به سایت فروش</span></a><?php if ($issuedReward['expires_at']): ?><span class="text-xs text-emerald-800 self-center">اعتبار تا <?= e($issuedReward['expires_at']) ?></span><?php endif; ?></div></section><?php endif; ?>

            <section class="card p-5 space-y-4"><h3 class="text-lg font-bold">تاریخچهٔ امتیازها</h3><div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="text-slate-500 border-b"><th class="text-start p-3">شرح</th><th class="text-start p-3">تغییر</th><th class="text-start p-3">مانده</th><th class="text-start p-3">زمان</th></tr></thead><tbody><?php if (!$summary['ledger']): ?><tr><td colspan="4" class="p-5 text-center text-slate-500">هنوز تراکنشی ثبت نشده است.</td></tr><?php endif; ?><?php foreach ($summary['ledger'] as $entry): ?><tr class="border-b last:border-0"><td class="p-3"><?= e($entry['description']) ?></td><td class="p-3 <?= (int) $entry['delta'] > 0 ? 'text-emerald-600' : 'text-red-600' ?>" dir="ltr"><?= (int) $entry['delta'] > 0 ? '+' : '' ?><?= (int) $entry['delta'] ?></td><td class="p-3" dir="ltr"><?= (int) $entry['balance_after'] ?></td><td class="p-3 text-xs" dir="ltr"><?= e($entry['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php render_customer_footer(); ?>
