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
$wallet = $summary['wallet'];
$rewards = gamification_list_rewards($customerId);
foreach ($rewards as &$reward) {
    if (!isset($_SESSION['gamification_reward_nonce'][(int) $reward['id']])) {
        $_SESSION['gamification_reward_nonce'][(int) $reward['id']] = bin2hex(random_bytes(16));
    }
    $reward['nonce'] = $_SESSION['gamification_reward_nonce'][(int) $reward['id']];
}
unset($reward);

$eventIcons = [
    'profile_completed' => 'user',
    'survey_submitted' => 'star',
    'ticket_created' => 'ticket',
    'ticket_customer_reply' => 'message',
    'bonus_code_redeemed' => 'star',
];
$eventLinks = [
    'profile_completed' => 'profile.php',
    'survey_submitted' => 'surveys.php',
    'ticket_created' => 'tickets.php?action=new',
    'ticket_customer_reply' => 'tickets.php',
];
$activeEvents = [];
foreach (gamification_event_catalog() as $eventKey => $event) {
    $rule = gamification_rule($eventKey);
    if (!$rule || !(int) $rule['is_active'] || (int) $rule['points'] <= 0) {
        continue;
    }
    $activeEvents[$eventKey] = [
        'title' => $event['title'],
        'description' => $event['description'],
        'points' => (int) $rule['points'],
        'daily_cap' => (int) $rule['daily_cap'],
        'icon' => $eventIcons[$eventKey] ?? 'star',
        'link' => $eventLinks[$eventKey] ?? null,
        'status' => gamification_customer_event_status($customerId, $eventKey),
    ];
}
$availableEventCount = count(array_filter(
    $activeEvents,
    static fn (array $event): bool => (string) ($event['status']['state'] ?? '') === 'available'
));

$nextReward = null;
foreach ($rewards as $reward) {
    if ((int) ($reward['customer_redemptions'] ?? 0) >= (int) $reward['max_per_customer']) {
        continue;
    }
    if ($nextReward === null || (int) $reward['points_cost'] < (int) $nextReward['points_cost']) {
        $nextReward = $reward;
    }
}
$nextRewardCost = $nextReward ? max(1, (int) $nextReward['points_cost']) : 0;
$nextRewardProgress = $nextRewardCost > 0 ? min(100, (int) floor(((int) $wallet['balance'] / $nextRewardCost) * 100)) : 0;
$nextRewardRemaining = $nextRewardCost > 0 ? max(0, $nextRewardCost - (int) $wallet['balance']) : 0;

$customer_gamification_styles = '
.gamification-hero{background:linear-gradient(135deg,var(--ring),color-mix(in srgb,var(--ring) 68%,#0f172a));color:#fff;border-radius:1.5rem;box-shadow:0 18px 36px -24px color-mix(in srgb,var(--ring) 70%,transparent)}
.gamification-hero-muted{color:color-mix(in srgb,#fff 78%,transparent)}
.gamification-progress{height:.65rem;background:color-mix(in srgb,var(--fg-strong) 12%,transparent);border-radius:999px;overflow:hidden}
.gamification-progress-fill{display:block;width:0;height:100%;background:var(--ring);border-radius:inherit;transition:width .35s var(--ease)}
.gamification-hero .gamification-progress{background:rgba(255,255,255,.22)}
.gamification-hero .gamification-progress-fill{background:#fff}
.gamification-earn-card{display:flex;align-items:flex-start;gap:.85rem;min-height:7.25rem;padding:1rem;border:1px solid var(--border);border-radius:var(--radius);background:var(--surface);transition:box-shadow .2s var(--ease),border-color .2s var(--ease),transform .2s var(--ease)}
.gamification-earn-card:hover{border-color:color-mix(in srgb,var(--ring) 42%,var(--border));box-shadow:0 10px 24px -16px color-mix(in srgb,var(--ring) 40%,transparent);transform:translateY(-2px)}
.gamification-earn-icon{display:flex;align-items:center;justify-content:center;width:2.5rem;height:2.5rem;flex:0 0 2.5rem;border-radius:.85rem;background:color-mix(in srgb,var(--ring) 12%,transparent);color:var(--ring)}
.gamification-reward-card{display:flex;flex-direction:column;gap:.85rem;padding:1.15rem;border:1px solid var(--border);border-radius:var(--radius);background:var(--surface);transition:box-shadow .2s var(--ease),border-color .2s var(--ease)}
.gamification-reward-card:hover{border-color:color-mix(in srgb,var(--ring) 38%,var(--border));box-shadow:0 10px 24px -18px rgba(15,23,42,.3)}
.gamification-stat{min-height:6.75rem}
.gamification-code{letter-spacing:.06em;font-variant-numeric:tabular-nums}
@media (max-width:767px){.gamification-hero{border-radius:1.25rem}.gamification-hero .btn{width:100%}.gamification-earn-card{min-height:0}.gamification-reward-card{padding:1rem}}
';

render_customer_header('امتیازها و پاداش‌ها', 'portal-page-main portal-gamification-page p-4 sm:p-6 lg:p-8 max-w-6xl w-full mx-auto space-y-6', $customer_gamification_styles, '', '');
?>
            <?php if ($success): ?>
                <div class="alert alert-success" role="status" aria-live="polite"><?= icon('check') ?><span><?= e($success) ?></span></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert" aria-live="assertive"><?= icon('alert') ?><span><?= e($error) ?></span></div>
            <?php endif; ?>

            <section class="gamification-hero p-5 sm:p-6 lg:p-8" aria-labelledby="gamification-title">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                    <div class="min-w-0 max-w-2xl">
                        <span class="inline-flex items-center gap-2 text-xs font-semibold rounded-full px-3 py-1 bg-white/15 text-white mb-4"><?= icon('star', 'w-4 h-4') ?><span>باشگاه امتیاز و پاداش</span></span>
                        <h1 id="gamification-title" class="text-2xl sm:text-3xl font-extrabold tracking-tight">امتیازهای شما، برای هر قدم ارزشمند</h1>
                        <p class="gamification-hero-muted text-sm sm:text-base leading-7 mt-3 max-w-xl">با فعالیت در پورتال امتیاز بگیرید، مسیر پیشرفت خود را ببینید و امتیازها را به پاداش‌های قابل‌استفاده تبدیل کنید.</p>
                        <div class="flex flex-col sm:flex-row gap-3 mt-5">
                            <a href="#reward-catalog" class="btn bg-white text-slate-900 hover:bg-slate-100">دیدن پاداش‌ها</a>
                            <a href="#earn-points" class="btn border border-white/35 text-white hover:bg-white/10">راه‌های کسب امتیاز</a>
                        </div>
                    </div>
                    <div class="w-full lg:max-w-xs rounded-2xl p-5 bg-white/10 border border-white/20 backdrop-blur-sm">
                        <p class="text-sm gamification-hero-muted">موجودی قابل‌مصرف</p>
                        <div class="flex items-end gap-2 mt-1"><strong class="text-4xl font-extrabold tabular-nums" dir="ltr"><?= number_format((int) $wallet['balance']) ?></strong><span class="text-sm gamification-hero-muted mb-1">امتیاز</span></div>
                        <?php if ($nextReward): ?>
                            <div class="flex items-center justify-between gap-3 mt-5 text-xs"><span class="gamification-hero-muted">هدف بعدی: <b class="text-white"><bdi dir="auto"><?= e($nextReward['title']) ?></bdi></b></span><span class="text-white tabular-nums" dir="ltr"><?= $nextRewardProgress ?>%</span></div>
                            <div class="gamification-progress mt-2" role="progressbar" aria-label="پیشرفت تا پاداش بعدی" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $nextRewardProgress ?>"><span class="gamification-progress-fill" data-gamification-progress="<?= $nextRewardProgress ?>"></span></div>
                            <p class="text-xs gamification-hero-muted mt-2"><?= $nextRewardRemaining > 0 ? 'فقط ' . e(gamification_points_label($nextRewardRemaining)) . ' تا این پاداش فاصله دارید.' : 'این پاداش با موجودی فعلی شما قابل دریافت است.' ?></p>
                        <?php else: ?>
                            <p class="text-xs gamification-hero-muted mt-4">هنوز پاداش فعالی برای نمایش تنظیم نشده است.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 sm:grid-cols-3 gap-4" aria-label="خلاصهٔ امتیازها">
                <div class="card gamification-stat p-5"><div class="flex items-center justify-between gap-3"><span class="text-sm text-slate-500">کل کسب‌شده</span><span class="gamification-earn-icon w-9 h-9 !rounded-xl"><?= icon('plus', 'w-4 h-4') ?></span></div><strong class="block text-2xl font-extrabold text-emerald-600 mt-3 tabular-nums" dir="ltr"><?= number_format((int) $wallet['total_earned']) ?></strong><span class="text-xs text-slate-500">امتیاز در تمام فعالیت‌ها</span></div>
                <div class="card gamification-stat p-5"><div class="flex items-center justify-between gap-3"><span class="text-sm text-slate-500">کل مصرف‌شده</span><span class="gamification-earn-icon w-9 h-9 !rounded-xl text-amber-600 bg-amber-50"><?= icon('link', 'w-4 h-4') ?></span></div><strong class="block text-2xl font-extrabold text-amber-600 mt-3 tabular-nums" dir="ltr"><?= number_format((int) $wallet['total_spent']) ?></strong><span class="text-xs text-slate-500">امتیاز تبدیل‌شده به پاداش</span></div>
                <div class="card gamification-stat p-5"><div class="flex items-center justify-between gap-3"><span class="text-sm text-slate-500">روش‌های قابل دریافت</span><span class="gamification-earn-icon w-9 h-9 !rounded-xl text-indigo-600 bg-indigo-50"><?= icon('layers', 'w-4 h-4') ?></span></div><strong class="block text-2xl font-extrabold text-indigo-600 mt-3 tabular-nums" dir="ltr"><?= $availableEventCount ?></strong><span class="text-xs text-slate-500"><?= count($activeEvents) > $availableEventCount ? 'از ' . count($activeEvents) . ' روش تنظیم‌شده' : 'فعالیت قابل‌امتیازدهی' ?></span></div>
            </section>

            <section id="earn-points" class="card p-5 sm:p-6 space-y-4" aria-labelledby="earn-points-title">
                <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-2"><div><h2 id="earn-points-title" class="text-lg sm:text-xl font-bold">چطور امتیاز بگیرم؟</h2><p class="text-sm text-slate-500 mt-1">هر کارت وضعیت واقعی فعالیت را نشان می‌دهد؛ موارد دریافت‌شده دیگر دکمهٔ فعال ندارند.</p></div><span class="badge <?= $availableEventCount > 0 ? 'badge-brand' : 'badge-muted' ?>"><?= $availableEventCount ?> قابل دریافت از <?= count($activeEvents) ?> روش</span></div>
                <?php if (!$activeEvents): ?>
                    <div class="alert alert-muted" role="status"><?= icon('info') ?><span>در حال حاضر روش فعالی برای کسب امتیاز تنظیم نشده است.</span></div>
                <?php else: ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
                        <?php foreach ($activeEvents as $eventKey => $event): ?>
                            <?php
                            $eventState = (string) ($event['status']['state'] ?? 'available');
                            $eventStateMeta = [
                                'available' => ['label' => 'قابل دریافت', 'class' => 'badge-brand'],
                                'received' => ['label' => 'قبلاً دریافت شده', 'class' => 'badge-success'],
                                'daily_cap' => ['label' => 'سقف امروز تکمیل شد', 'class' => 'badge-warning'],
                                'cooldown' => ['label' => 'در انتظار فعال‌شدن', 'class' => 'badge-muted'],
                            ][$eventState] ?? ['label' => 'وضعیت نامشخص', 'class' => 'badge-muted'];
                            $eventInteractive = $event['link'] && $eventState === 'available';
                            $eventCard = $eventInteractive ? '<a href="' . e($event['link']) . '" class="gamification-earn-card group" aria-label="' . e($event['title']) . '">' : '<div class="gamification-earn-card">';
                            ?>
                            <?= $eventCard ?>
                                <span class="gamification-earn-icon <?= $eventState === 'received' ? 'bg-emerald-50 text-emerald-600' : '' ?>"><?= icon($eventState === 'received' ? 'check' : $event['icon'], 'w-5 h-5') ?></span>
                                <span class="min-w-0 flex-1"><span class="flex items-start justify-between gap-2"><b class="text-sm text-slate-900 leading-6"><bdi dir="auto"><?= e($event['title']) ?></bdi></b><span class="badge <?= e($eventStateMeta['class']) ?> shrink-0"><?= e($eventStateMeta['label']) ?></span></span><span class="block text-xs text-slate-500 leading-5 mt-1"><?= e($event['description']) ?></span><span class="flex flex-wrap items-center gap-x-2 gap-y-1 mt-2"><span class="text-xs font-semibold text-indigo-600"><?= e(gamification_points_label($event['points'])) ?></span><?php if ($event['daily_cap'] > 0): ?><span class="text-[11px] text-slate-400">سقف روزانه: <?= e(gamification_points_label($event['daily_cap'])) ?></span><?php endif; ?><?php if ($eventState === 'cooldown'): ?><span class="text-[11px] text-slate-400">حدود <?= (int) ceil(((int) $event['status']['cooldown_remaining']) / 60) ?> دقیقه دیگر</span><?php endif; ?></span></span>
                            <?= $eventInteractive ? '</a>' : '</div>' ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="card p-5 sm:p-6" aria-labelledby="bonus-code-title">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">
                    <div class="flex items-start gap-3 min-w-0"><span class="gamification-earn-icon shrink-0"><?= icon('star', 'w-5 h-5') ?></span><div><h2 id="bonus-code-title" class="text-lg font-bold">کد هدیه دارید؟</h2><p class="text-sm text-slate-500 mt-1 leading-6">کد نمایشگاه یا کمپین را وارد کنید؛ پس از اعتبارسنجی، امتیاز همان لحظه به موجودی شما اضافه می‌شود.</p></div></div>
                    <form method="post" class="flex flex-col sm:flex-row gap-3 w-full lg:max-w-lg"><?= csrf_input() ?><input type="hidden" name="action" value="redeem_bonus"><label class="sr-only" for="bonus_code">کد هدیه</label><input class="input flex-1" id="bonus_code" name="bonus_code" required maxlength="64" dir="ltr" autocomplete="off" placeholder="EXPO2026"><button class="btn btn-primary shrink-0" type="submit"><?= icon('check') ?><span>ثبت کد هدیه</span></button></form>
                </div>
            </section>

            <section id="reward-catalog" class="card p-5 sm:p-6 space-y-5" aria-labelledby="reward-catalog-title">
                <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-2"><div><h2 id="reward-catalog-title" class="text-lg sm:text-xl font-bold">پاداش‌های قابل‌دریافت</h2><p class="text-sm text-slate-500 mt-1">پیشرفت هر پاداش را ببینید و وقتی آماده شد، آن را با یک کلیک دریافت کنید.</p></div><span class="badge badge-muted"><?= count($rewards) ?> پاداش فعال</span></div>
                <?php if (!$rewards): ?>
                    <?= empty_state('هنوز پاداشی برای دریافت تنظیم نشده است.', 'با فعال‌شدن پاداش‌ها، گزینه‌های تبدیل امتیاز در این بخش نمایش داده می‌شوند.', 'star') ?>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($rewards as $reward): ?>
                            <?php
                            $rewardCost = max(1, (int) $reward['points_cost']);
                            $rewardProgress = min(100, (int) floor(((int) $wallet['balance'] / $rewardCost) * 100));
                            $rewardRemaining = max(0, $rewardCost - (int) $wallet['balance']);
                            $customerLimitReached = (int) ($reward['customer_redemptions'] ?? 0) >= (int) $reward['max_per_customer'];
                            $canRedeem = (int) $wallet['balance'] >= $rewardCost && (int) $reward['is_active'] && (int) $reward['site_active'] && ((string) $reward['coupon_mode'] === 'fixed' || (int) $reward['available_codes'] > 0) && !$customerLimitReached;
                            ?>
                            <article class="gamification-reward-card">
                                <div class="flex items-start justify-between gap-3"><div class="min-w-0"><h3 class="font-bold text-slate-900 leading-6"><bdi dir="auto"><?= e($reward['title']) ?></bdi></h3><p class="text-sm text-slate-500 mt-1 leading-6"><bdi dir="auto"><?= e($reward['description']) ?></bdi></p></div><span class="badge badge-brand shrink-0"><?= e(gamification_points_label($rewardCost)) ?></span></div>
                                <div class="text-xs text-slate-500 flex flex-wrap gap-x-3 gap-y-1"><span>سایت مقصد: <b class="text-slate-700"><bdi dir="auto"><?= e($reward['site_name']) ?></bdi></b></span><span>اعتبار: <?= (int) $reward['valid_days'] > 0 ? (int) $reward['valid_days'] . ' روز' : 'بدون انقضا' ?></span></div>
                                <div><div class="flex items-center justify-between gap-3 text-xs mb-2"><span class="text-slate-500">پیشرفت شما</span><span class="font-semibold <?= $rewardProgress >= 100 ? 'text-emerald-600' : 'text-indigo-600' ?>"><?= $rewardProgress >= 100 ? 'آمادهٔ دریافت' : e(gamification_points_label($rewardRemaining)) . ' تا دریافت' ?></span></div><div class="gamification-progress" role="progressbar" aria-label="پیشرفت تا پاداش <?= e($reward['title']) ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $rewardProgress ?>"><span class="gamification-progress-fill" data-gamification-progress="<?= $rewardProgress ?>"></span></div></div>
                                <?php if ($customerLimitReached): ?><p class="text-xs text-amber-700 bg-amber-50 rounded-lg p-3" role="status">سقف دریافت این پاداش برای شما تکمیل شده است.</p><?php elseif ((string) $reward['coupon_mode'] === 'pool' && (int) $reward['available_codes'] <= 0): ?><p class="text-xs text-slate-500 bg-slate-50 rounded-lg p-3" role="status">کد این پاداش موقتاً موجود نیست.</p><?php endif; ?>
                                <form method="post" class="mt-auto" data-confirm-msg="با کسر <?= e(gamification_points_label($rewardCost)) ?> این پاداش صادر می‌شود. ادامه می‌دهید؟" data-confirm-title="تأیید دریافت پاداش" data-confirm-ok-label="دریافت پاداش" data-confirm-tone="primary"><?= csrf_input() ?><input type="hidden" name="action" value="redeem_reward"><input type="hidden" name="reward_id" value="<?= (int) $reward['id'] ?>"><input type="hidden" name="reward_nonce" value="<?= e($reward['nonce']) ?>"><button type="submit" class="btn <?= $canRedeem ? 'btn-primary' : 'btn-secondary' ?> w-full" type="submit" <?= $canRedeem ? '' : 'disabled' ?>><?= icon('star') ?><span><?= $canRedeem ? 'دریافت پاداش' : 'امکان دریافت نیست' ?></span></button></form>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <?php if ($issuedReward): ?>
                <section class="card p-5 sm:p-6 border-2 border-emerald-200 bg-emerald-50 space-y-4" aria-live="polite" aria-labelledby="issued-reward-title"><div class="flex items-start gap-3"><span class="shrink-0 text-emerald-700 mt-0.5"><?= icon('check', 'w-5 h-5') ?></span><div><h2 id="issued-reward-title" class="text-lg font-bold text-emerald-900">پاداش شما آماده است</h2><p class="text-sm text-emerald-800 mt-1 leading-6"><?= e(get_setting('gamification_store_message', 'کد تخفیف را هنگام خرید در سایت مقصد وارد کنید.')) ?></p></div></div><div class="bg-white rounded-xl p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"><span class="text-sm text-slate-500">کد تخفیف</span><div class="flex items-center gap-2"><code class="text-xl font-bold text-slate-900 gamification-code" dir="ltr"><?= e($issuedReward['coupon_code']) ?></code><button type="button" class="btn btn-secondary btn-sm" data-copy-value="<?= e($issuedReward['coupon_code']) ?>">کپی کد</button></div></div><div class="flex flex-col sm:flex-row sm:items-center gap-3"><a class="btn btn-primary" href="<?= e($issuedReward['redirect_url']) ?>" target="_blank" rel="noopener noreferrer"><?= icon('link') ?><span>رفتن به سایت فروش</span></a><?php if ($issuedReward['expires_at']): ?><span class="text-xs text-emerald-800">اعتبار تا <?= e(fa_datetime($issuedReward['expires_at'])) ?></span><?php endif; ?></div></section>
            <?php endif; ?>

            <div class="grid grid-cols-1 xl:grid-cols-5 gap-6">
                <section class="card p-5 sm:p-6 space-y-4 xl:col-span-3" aria-labelledby="ledger-title"><div><h2 id="ledger-title" class="text-lg font-bold">تاریخچهٔ امتیازها</h2><p class="text-sm text-slate-500 mt-1">آخرین تغییرات موجودی شما در دفترکل امتیاز.</p></div><div class="table-scroll"><table class="table table-card-mobile"><thead><tr><th>شرح</th><th>تغییر</th><th>مانده</th><th>زمان</th></tr></thead><tbody><?php if (!$summary['ledger']): ?><tr><td colspan="4" class="p-5 text-center text-slate-500">هنوز تراکنشی ثبت نشده است.</td></tr><?php endif; ?><?php foreach ($summary['ledger'] as $entry): $delta = (int) $entry['delta']; ?><tr><td data-label="شرح" class="font-medium text-slate-800"><?= e($entry['description']) ?></td><td data-label="تغییر"><span class="badge <?= $delta > 0 ? 'badge-success' : 'badge-danger' ?> tabular-nums" dir="ltr"><?= $delta > 0 ? '+' : '' ?><?= number_format($delta) ?></span></td><td data-label="مانده" class="tabular-nums" dir="ltr"><?= number_format((int) $entry['balance_after']) ?></td><td data-label="زمان" class="text-xs text-slate-500 value-ltr whitespace-nowrap" dir="ltr"><?= e(fa_datetime((string) $entry['created_at'])) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
                <section class="card p-5 sm:p-6 space-y-4 xl:col-span-2" aria-labelledby="redemptions-title"><div><h2 id="redemptions-title" class="text-lg font-bold">پاداش‌های اخیر</h2><p class="text-sm text-slate-500 mt-1">پاداش‌هایی که تاکنون دریافت کرده‌اید.</p></div><?php if (!$summary['redemptions']): ?><div class="alert alert-muted" role="status"><?= icon('info') ?><span>هنوز پاداشی دریافت نکرده‌اید.</span></div><?php else: ?><div class="space-y-3"><?php foreach (array_slice($summary['redemptions'], 0, 5) as $redemption): ?><div class="rounded-xl border border-slate-200 p-3"><div class="flex items-start justify-between gap-3"><b class="text-sm text-slate-800"><bdi dir="auto"><?= e($redemption['reward_title']) ?></bdi></b><span class="badge badge-success">صادرشده</span></div><div class="flex items-center justify-between gap-3 mt-2 text-xs text-slate-500"><code class="gamification-code" dir="ltr"><?= e($redemption['coupon_code_snapshot']) ?></code><span><?= e(fa_datetime((string) $redemption['created_at'])) ?></span></div></div><?php endforeach; ?></div><?php endif; ?></section>
            </div>

            <script nonce="<?= e(portal_csp_nonce()) ?>">
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('[data-gamification-progress]').forEach(function (bar) {
                    var progress = Math.max(0, Math.min(100, Number(bar.getAttribute('data-gamification-progress')) || 0));
                    bar.style.width = progress + '%';
                });
                document.querySelectorAll('[data-copy-value]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        var value = button.getAttribute('data-copy-value') || '';
                        if (!navigator.clipboard || !value) return;
                        navigator.clipboard.writeText(value).then(function () {
                            var original = button.textContent;
                            button.textContent = 'کپی شد';
                            window.setTimeout(function () { button.textContent = original; }, 1800);
                        });
                    });
                });
            });
            </script>
<?php render_customer_footer(); ?>
