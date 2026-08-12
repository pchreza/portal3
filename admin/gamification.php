<?php
// admin/gamification.php — مدیریت سادهٔ باشگاه امتیاز و پاداش
require_once 'auth.php';
if (!admin_can('gamification')) {
    header('Location: index.php');
    exit;
}

global $pdo;
$success = '';
$error = '';
$adminId = (int) ($_SESSION['user_id'] ?? 0);

$admin_datetime = static function (string $value, bool $allowEmpty = true): ?string {
    $value = trim($value);
    if ($value === '' && $allowEmpty) {
        return null;
    }
    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $value);
    $lastErrors = DateTime::getLastErrors();
    if (!$dt || (is_array($lastErrors) && ($lastErrors['warning_count'] > 0 || $lastErrors['error_count'] > 0))) {
        throw new InvalidArgumentException('تاریخ واردشده معتبر نیست.');
    }
    return $dt->format('Y-m-d H:i:s');
};

$normalize_pool_codes = static function (string $raw): array {
    $rawCodes = preg_split('/\R+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $codes = [];
    foreach ($rawCodes as $rawCode) {
        $code = gamification_normalize_code((string) $rawCode);
        if (!gamification_valid_code($code)) {
            throw new InvalidArgumentException('یکی از کدها معتبر نیست؛ فقط حروف انگلیسی، عدد، خط تیره و زیرخط مجاز است.');
        }
        $codes[$code] = true;
        if (count($codes) > 500) {
            throw new InvalidArgumentException('در هر مرحله حداکثر ۵۰۰ کد وارد کنید.');
        }
    }
    if (!$codes) {
        throw new InvalidArgumentException('حداقل یک کد تخفیف لازم است. هر کد را در یک خط وارد کنید.');
    }
    return array_keys($codes);
};

$assert_pool_codes_are_new = static function (PDO $db, array $codes): void {
    $check = $db->prepare('SELECT id FROM reward_coupon_pool WHERE coupon_hash = ? LIMIT 1');
    foreach ($codes as $code) {
        $check->execute([hash('sha256', $code)]);
        if ($check->fetchColumn()) {
            throw new InvalidArgumentException('کد «' . $code . '» قبلاً در مخزن ثبت شده است.');
        }
    }
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'toggle_module') {
            set_setting('module_gamification', isset($_POST['enabled']) ? '1' : '0');
            log_activity($adminId, 'تغییر وضعیت ماژول باشگاه امتیاز و پاداش');
            $success = 'وضعیت ماژول ذخیره شد.';
        } elseif ($action === 'save_rules') {
            $postedRules = $_POST['rules'] ?? [];
            if (!is_array($postedRules)) {
                throw new InvalidArgumentException('اطلاعات قوانین امتیازدهی معتبر نیست.');
            }
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('UPDATE gamification_rules SET points = ?, daily_cap = ?, cooldown_seconds = ?, is_active = ? WHERE event_key = ?');
            foreach (gamification_event_catalog() as $eventKey => $_event) {
                if ($eventKey === 'bonus_code_redeemed') {
                    continue;
                }
                $input = isset($postedRules[$eventKey]) && is_array($postedRules[$eventKey]) ? $postedRules[$eventKey] : [];
                $points = max(0, min(100000, (int) ($input['points'] ?? 0)));
                $dailyCap = max(0, min(1000000, (int) ($input['daily_cap'] ?? 0)));
                $cooldownMinutes = max(0, min(43200, (int) ($input['cooldown_minutes'] ?? 0)));
                // در UI ساده، امتیاز صفر rule را خاموش و امتیاز مثبت آن را فعال می‌کند.
                $isActive = $points > 0 ? 1 : 0;
                $stmt->execute([$points, $dailyCap, $cooldownMinutes * 60, $isActive, $eventKey]);
            }
            $pdo->commit();
            log_activity($adminId, 'ذخیرهٔ یک‌جای قوانین امتیازدهی');
            $success = 'قوانین امتیازدهی ذخیره شد.';
        } elseif ($action === 'save_rule') {
            // سازگاری با فرم‌های قدیمی یا درخواست‌های قبلی؛ UI جدید از save_rules استفاده می‌کند.
            $eventKey = (string) ($_POST['event_key'] ?? '');
            if (!isset(gamification_event_catalog()[$eventKey])) {
                throw new InvalidArgumentException('رویداد امتیازدهی معتبر نیست.');
            }
            $points = max(0, min(100000, (int) ($_POST['points'] ?? 0)));
            $dailyCap = max(0, min(1000000, (int) ($_POST['daily_cap'] ?? 0)));
            $cooldown = max(0, min(2592000, (int) ($_POST['cooldown_seconds'] ?? 0)));
            $pdo->prepare('UPDATE gamification_rules SET points = ?, daily_cap = ?, cooldown_seconds = ?, is_active = ? WHERE event_key = ?')->execute([$points, $dailyCap, $cooldown, $points > 0 ? 1 : 0, $eventKey]);
            log_activity($adminId, 'به‌روزرسانی rule امتیازدهی: ' . $eventKey);
            $success = 'قانون امتیازدهی ذخیره شد.';
        } elseif ($action === 'create_site') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $url = trim((string) ($_POST['base_url'] ?? ''));
            if ($name === '' || strlen($name) > 150 || !gamification_validate_https_url($url)) {
                throw new InvalidArgumentException('نام سایت و URL امن HTTPS معتبر الزامی است.');
            }
            $pdo->prepare('INSERT INTO reward_sites (name, base_url, provider, is_active, created_by) VALUES (?, ?, ?, 1, ?)')->execute([$name, $url, 'manual_redirect', $adminId]);
            log_activity($adminId, 'افزودن سایت فروش Gamification: ' . $name);
            $success = 'سایت مقصد اضافه شد.';
        } elseif ($action === 'update_site') {
            $siteId = max(0, (int) ($_POST['site_id'] ?? 0));
            $name = trim((string) ($_POST['name'] ?? ''));
            $url = trim((string) ($_POST['base_url'] ?? ''));
            if ($siteId <= 0 || $name === '' || strlen($name) > 150 || !gamification_validate_https_url($url)) {
                throw new InvalidArgumentException('نام و URL امن سایت معتبر نیست.');
            }
            $pdo->prepare('UPDATE reward_sites SET name = ?, base_url = ? WHERE id = ?')->execute([$name, $url, $siteId]);
            log_activity($adminId, 'ویرایش سایت فروش Gamification: ' . $siteId);
            $success = 'سایت مقصد به‌روزرسانی شد.';
        } elseif ($action === 'delete_site') {
            $siteId = max(0, (int) ($_POST['site_id'] ?? 0));
            $used = $pdo->prepare('SELECT COUNT(*) FROM reward_catalog WHERE site_id = ?');
            $used->execute([$siteId]);
            if ((int) $used->fetchColumn() > 0) {
                throw new InvalidArgumentException('سایتی که پاداش دارد قابل حذف نیست؛ ابتدا پاداش‌ها را غیرفعال کنید.');
            }
            $pdo->prepare('DELETE FROM reward_sites WHERE id = ?')->execute([$siteId]);
            log_activity($adminId, 'حذف سایت فروش Gamification: ' . $siteId);
            $success = 'سایت مقصد حذف شد.';
        } elseif ($action === 'toggle_site') {
            $siteId = max(0, (int) ($_POST['site_id'] ?? 0));
            $pdo->prepare('UPDATE reward_sites SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?')->execute([$siteId]);
            log_activity($adminId, 'تغییر وضعیت سایت فروش Gamification: ' . $siteId);
            $success = 'وضعیت سایت مقصد تغییر کرد.';
        } elseif ($action === 'create_reward') {
            $siteId = max(0, (int) ($_POST['site_id'] ?? 0));
            $title = trim((string) ($_POST['title'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $cost = max(1, min(100000000, (int) ($_POST['points_cost'] ?? 0)));
            $validDays = max(0, min(3650, (int) ($_POST['valid_days'] ?? 30)));
            $maxPerCustomer = max(1, min(1000, (int) ($_POST['max_per_customer'] ?? 1)));
            $codes = $normalize_pool_codes((string) ($_POST['coupon_codes'] ?? ''));
            $siteCheck = $pdo->prepare('SELECT id FROM reward_sites WHERE id = ? AND is_active = 1');
            $siteCheck->execute([$siteId]);
            if (!$siteCheck->fetchColumn() || $title === '' || strlen($title) > 180) {
                throw new InvalidArgumentException('سایت فعال و عنوان پاداش معتبر الزامی است.');
            }
            $pdo->beginTransaction();
            $assert_pool_codes_are_new($pdo, $codes);
            $pdo->prepare("INSERT INTO reward_catalog (site_id, title, description, points_cost, coupon_mode, fixed_coupon_code, valid_days, max_per_customer, is_active, created_by) VALUES (?, ?, ?, ?, 'pool', '', ?, ?, 1, ?)")->execute([$siteId, $title, $description, $cost, $validDays, $maxPerCustomer, $adminId]);
            $rewardId = (int) $pdo->lastInsertId();
            $insertCoupon = $pdo->prepare('INSERT INTO reward_coupon_pool (reward_id, coupon_code, coupon_hash) VALUES (?, ?, ?)');
            foreach ($codes as $code) {
                $insertCoupon->execute([$rewardId, $code, hash('sha256', $code)]);
            }
            $pdo->commit();
            log_activity($adminId, 'افزودن پاداش و ' . count($codes) . ' کد یکتا: ' . $title);
            $success = 'پاداش و ' . count($codes) . ' کد تخفیف با هم ایجاد شدند.';
        } elseif ($action === 'toggle_reward') {
            $rewardId = max(0, (int) ($_POST['reward_id'] ?? 0));
            $pdo->prepare('UPDATE reward_catalog SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?')->execute([$rewardId]);
            log_activity($adminId, 'تغییر وضعیت پاداش Gamification: ' . $rewardId);
            $success = 'وضعیت پاداش تغییر کرد.';
        } elseif ($action === 'delete_reward') {
            $rewardId = max(0, (int) ($_POST['reward_id'] ?? 0));
            $used = $pdo->prepare('SELECT COUNT(*) FROM reward_redemptions WHERE reward_id = ?');
            $used->execute([$rewardId]);
            if ((int) $used->fetchColumn() > 0) {
                throw new InvalidArgumentException('پاداش دارای سابقهٔ دریافت است و قابل حذف نیست؛ آن را غیرفعال کنید.');
            }
            $pdo->prepare('DELETE FROM reward_catalog WHERE id = ?')->execute([$rewardId]);
            log_activity($adminId, 'حذف پاداش Gamification: ' . $rewardId);
            $success = 'پاداش حذف شد.';
        } elseif ($action === 'create_campaign') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $code = gamification_normalize_code((string) ($_POST['code'] ?? ''));
            $points = max(1, min(1000000, (int) ($_POST['points'] ?? 0)));
            $startsAt = $admin_datetime((string) ($_POST['starts_at'] ?? ''));
            $expiresAt = $admin_datetime((string) ($_POST['expires_at'] ?? ''));
            $maxRedemptions = max(0, min(10000000, (int) ($_POST['max_redemptions'] ?? 0)));
            if ($name === '' || strlen($name) > 150 || !gamification_valid_code($code)) {
                throw new InvalidArgumentException('نام کمپین و کد یکتای معتبر الزامی است.');
            }
            if ($startsAt !== null && $expiresAt !== null && strtotime($expiresAt) < strtotime($startsAt)) {
                throw new InvalidArgumentException('تاریخ پایان باید بعد از شروع باشد.');
            }
            $pdo->prepare('INSERT INTO bonus_code_campaigns (name, code_hash, points, starts_at, expires_at, max_redemptions, max_per_customer, created_by) VALUES (?, ?, ?, ?, ?, ?, 1, ?)')->execute([$name, hash('sha256', $code), $points, $startsAt, $expiresAt, $maxRedemptions, $adminId]);
            log_activity($adminId, 'ایجاد کمپین کد هدیه Gamification: ' . $name);
            $success = 'کمپین کد هدیه ایجاد شد. این کد برای هر مشتری فقط یک‌بار قابل‌استفاده است.';
        } elseif ($action === 'toggle_campaign') {
            $campaignId = max(0, (int) ($_POST['campaign_id'] ?? 0));
            $pdo->prepare('UPDATE bonus_code_campaigns SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?')->execute([$campaignId]);
            log_activity($adminId, 'تغییر وضعیت کمپین کد هدیه: ' . $campaignId);
            $success = 'وضعیت کمپین تغییر کرد.';
        } elseif ($action === 'import_coupons') {
            $rewardId = max(0, (int) ($_POST['reward_id'] ?? 0));
            $codes = $normalize_pool_codes((string) ($_POST['coupon_codes'] ?? ''));
            $check = $pdo->prepare("SELECT id FROM reward_catalog WHERE id = ? AND coupon_mode = 'pool'");
            $check->execute([$rewardId]);
            if (!$check->fetchColumn()) {
                throw new InvalidArgumentException('پاداش pool معتبر انتخاب نشده است.');
            }
            $pdo->beginTransaction();
            $assert_pool_codes_are_new($pdo, $codes);
            $insert = $pdo->prepare('INSERT INTO reward_coupon_pool (reward_id, coupon_code, coupon_hash) VALUES (?, ?, ?)');
            foreach ($codes as $code) {
                $insert->execute([$rewardId, $code, hash('sha256', $code)]);
            }
            $pdo->commit();
            log_activity($adminId, 'افزودن ' . count($codes) . ' کد تخفیف به pool پاداش: ' . $rewardId);
            $success = count($codes) . ' کد تخفیف به پاداش افزوده شد.';
        }
    } catch (InvalidArgumentException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[Admin Gamification] ' . $e->getMessage());
        $error = $e instanceof PDOException && $e->getCode() === '23000' ? 'دادهٔ تکراری یا ناسازگار است؛ مقدارها را بررسی کنید.' : 'عملیات Gamification انجام نشد. اطلاعات را بررسی کنید.';
    }
}

$rules = $pdo->query('SELECT * FROM gamification_rules ORDER BY id')->fetchAll();
$ruleByKey = [];
foreach ($rules as $rule) {
    $ruleByKey[(string) $rule['event_key']] = $rule;
}
$catalog = gamification_event_catalog();
$configurableEvents = array_filter($catalog, static fn (array $_event, string $key): bool => $key !== 'bonus_code_redeemed', ARRAY_FILTER_USE_BOTH);
$sites = $pdo->query('SELECT * FROM reward_sites ORDER BY id DESC')->fetchAll();
$rewards = $pdo->query("SELECT r.*, s.name site_name, (SELECT COUNT(*) FROM reward_coupon_pool cp WHERE cp.reward_id = r.id AND cp.status = 'available' AND (cp.expires_at IS NULL OR cp.expires_at >= UTC_TIMESTAMP())) available_codes, (SELECT COUNT(*) FROM reward_redemptions rr WHERE rr.reward_id = r.id AND rr.status = 'issued') redemption_count FROM reward_catalog r JOIN reward_sites s ON s.id = r.site_id ORDER BY r.id DESC")->fetchAll();
$campaigns = $pdo->query('SELECT * FROM bonus_code_campaigns ORDER BY id DESC LIMIT 100')->fetchAll();
$ledger = $pdo->query('SELECT l.*, u.username, u.first_name, u.last_name FROM customer_point_ledger l JOIN users u ON u.id = l.customer_id ORDER BY l.id DESC LIMIT 50')->fetchAll();
$customerPageSize = 50;
$customerPage = max(1, (int) ($_GET['customer_page'] ?? 1));
$customerTotal = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
$customerPageCount = max(1, (int) ceil($customerTotal / $customerPageSize));
$customerPage = min($customerPage, $customerPageCount);
$customerOffset = ($customerPage - 1) * $customerPageSize;
$customerBalancesStmt = $pdo->prepare("SELECT u.id, u.username, u.first_name, u.last_name, COALESCE(w.balance, 0) balance, COALESCE(w.total_earned, 0) total_earned, COALESCE(w.total_spent, 0) total_spent, (SELECT COUNT(*) FROM customer_point_ledger l WHERE l.customer_id = u.id) ledger_count FROM users u LEFT JOIN customer_point_wallets w ON w.customer_id = u.id WHERE u.role = 'customer' ORDER BY balance DESC, total_earned DESC, u.id DESC LIMIT :page_size OFFSET :page_offset");
$customerBalancesStmt->bindValue(':page_size', $customerPageSize, PDO::PARAM_INT);
$customerBalancesStmt->bindValue(':page_offset', $customerOffset, PDO::PARAM_INT);
$customerBalancesStmt->execute();
$customerBalances = $customerBalancesStmt->fetchAll();
$moduleOn = gamification_enabled();
$activeRuleCount = 0;
foreach ($configurableEvents as $key => $_event) {
    if (isset($ruleByKey[$key]) && (int) $ruleByKey[$key]['is_active'] && (int) $ruleByKey[$key]['points'] > 0) {
        $activeRuleCount++;
    }
}
$availableCouponCount = (int) $pdo->query("SELECT COUNT(*) FROM reward_coupon_pool cp JOIN reward_catalog r ON r.id = cp.reward_id JOIN reward_sites s ON s.id = r.site_id WHERE cp.status = 'available' AND r.is_active = 1 AND s.is_active = 1 AND (cp.expires_at IS NULL OR cp.expires_at >= UTC_TIMESTAMP())")->fetchColumn();
$totalIssuedCount = (int) $pdo->query("SELECT COUNT(*) FROM reward_redemptions WHERE status = 'issued'")->fetchColumn();

render_admin_header('باشگاه امتیاز و پاداش', 'portal-page-main portal-admin-page portal-gamification-page p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto space-y-6', '', '');
?>
            <?php if ($success): ?><div class="alert alert-success" role="status"><?= icon('check') ?><span><?= e($success) ?></span></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= icon('alert') ?><span><?= e($error) ?></span></div><?php endif; ?>

            <section class="portal-page-hero flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
                <div><p class="overline text-indigo-600">مدیریت سادهٔ امتیاز و پاداش</p><h1 class="portal-page-heading h-1 text-slate-900 mt-2">باشگاه امتیاز و پاداش</h1><p class="text-sm text-slate-500 mt-2 max-w-2xl leading-6">سه کار اصلی را انجام دهید: روش‌های امتیازدهی را روشن کنید، پاداش و کدهای تخفیف را بسازید و موجودی مشتریان را بررسی کنید.</p></div>
                <span class="badge <?= $moduleOn ? 'badge-success' : 'badge-warning' ?>"><?= $moduleOn ? 'ماژول فعال' : 'ماژول غیرفعال' ?></span>
            </section>

            <section class="portal-kpi-grid grid grid-cols-2 lg:grid-cols-4 gap-3" aria-label="خلاصهٔ مدیریتی Gamification">
                <div class="portal-stat-card card p-4"><span class="portal-stat-label text-xs text-slate-500">روش‌های امتیازدهی فعال</span><strong class="portal-stat-value block text-2xl text-indigo-600 mt-2 tabular-nums" dir="ltr"><?= $activeRuleCount ?></strong></div>
                <div class="portal-stat-card card p-4"><span class="portal-stat-label text-xs text-slate-500">مشتریان دارای کیف امتیاز</span><strong class="portal-stat-value block text-2xl text-emerald-600 mt-2 tabular-nums" dir="ltr"><?= count(array_filter($customerBalances, static fn (array $row): bool => (int) $row['total_earned'] > 0)) ?></strong></div>
                <div class="portal-stat-card card p-4"><span class="portal-stat-label text-xs text-slate-500">کدهای آمادهٔ مصرف</span><strong class="portal-stat-value block text-2xl text-amber-600 mt-2 tabular-nums" dir="ltr"><?= $availableCouponCount ?></strong></div>
                <div class="portal-stat-card card p-4"><span class="portal-stat-label text-xs text-slate-500">پاداش‌های صادرشده</span><strong class="portal-stat-value block text-2xl text-violet-600 mt-2 tabular-nums" dir="ltr"><?= $totalIssuedCount ?></strong></div>
            </section>

            <section class="portal-panel-card card p-5 sm:p-6 border-2 <?= $moduleOn ? 'border-emerald-200' : 'border-amber-200' ?>" aria-labelledby="module-title">
                <form method="post" class="flex flex-col md:flex-row md:items-center md:justify-between gap-4"><?= csrf_input() ?><input type="hidden" name="action" value="toggle_module"><div><h2 id="module-title" class="text-lg font-bold">۱. فعال‌سازی باشگاه</h2><p class="text-sm text-slate-500 mt-1 leading-6">خاموش‌کردن ماژول فقط نمایش و امتیازدهی را متوقف می‌کند؛ موجودی و تاریخچهٔ مشتریان حذف نمی‌شود.</p></div><div class="flex items-center gap-3"><label class="inline-flex items-center gap-2 cursor-pointer"><input type="checkbox" name="enabled" value="1" <?= $moduleOn ? 'checked' : '' ?> class="w-5 h-5 text-indigo-600"><span class="font-medium">فعال باشد</span></label><button class="btn btn-primary" type="submit">ذخیره</button></div></form>
            </section>

            <section class="portal-panel-card card p-5 sm:p-6 space-y-4" aria-labelledby="rules-title">
                <div><h2 id="rules-title" class="text-lg font-bold">۲. روش‌های دریافت امتیاز</h2><p class="text-sm text-slate-500 mt-1 leading-6">برای هر فعالیت فقط امتیاز و محدودیت لازم را وارد کنید. امتیاز مثبت rule را فعال می‌کند و مقدار صفر آن را خاموش می‌کند؛ سقف روزانه برحسب امتیاز و فاصلهٔ مجاز برحسب دقیقه است.</p></div>
                <form method="post" class="space-y-4"><?= csrf_input() ?><input type="hidden" name="action" value="save_rules"><div class="space-y-3">
                    <?php foreach ($configurableEvents as $eventKey => $event): $rule = $ruleByKey[$eventKey] ?? ['points' => 0, 'daily_cap' => 0, 'cooldown_seconds' => 0, 'is_active' => 0]; ?>
                        <div class="portal-rule-card rounded-xl border border-slate-200 p-4"><div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3"><div><h3 class="font-bold text-slate-900"><?= e($event['title']) ?></h3><p class="text-xs text-slate-500 mt-1"><?= e($event['description']) ?></p></div><span class="badge <?= (int) $rule['points'] > 0 ? 'badge-success' : 'badge-muted' ?>"><?= (int) $rule['points'] > 0 ? 'فعال' : 'خاموش؛ امتیاز را وارد کنید' ?></span></div><div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-4"><div><label class="label" for="rule-points-<?= e($eventKey) ?>">امتیاز هر بار</label><input id="rule-points-<?= e($eventKey) ?>" class="input" type="number" name="rules[<?= e($eventKey) ?>][points]" min="0" max="100000" value="<?= (int) $rule['points'] ?>" dir="ltr"></div><div><label class="label" for="rule-cap-<?= e($eventKey) ?>">سقف امتیاز روزانه</label><input id="rule-cap-<?= e($eventKey) ?>" class="input" type="number" name="rules[<?= e($eventKey) ?>][daily_cap]" min="0" max="1000000" value="<?= (int) $rule['daily_cap'] ?>" dir="ltr"><p class="helper">صفر یعنی بدون سقف</p></div><div><label class="label" for="rule-cooldown-<?= e($eventKey) ?>">فاصلهٔ بین دو دریافت، دقیقه</label><input id="rule-cooldown-<?= e($eventKey) ?>" class="input" type="number" name="rules[<?= e($eventKey) ?>][cooldown_minutes]" min="0" max="43200" value="<?= (int) round(((int) $rule['cooldown_seconds']) / 60) ?>" dir="ltr"><p class="helper">برای پروفایل و نظرسنجی معمولاً صفر</p></div></div></div>
                    <?php endforeach; ?>
                </div><button class="btn btn-primary" type="submit"><?= icon('check') ?><span>ذخیرهٔ همهٔ روش‌ها</span></button></form>
            </section>

            <section class="portal-panel-card card p-5 sm:p-6 space-y-4" aria-labelledby="reward-setup-title">
                <div><h2 id="reward-setup-title" class="text-lg font-bold">۳. ساخت پاداش و کد تخفیف</h2><p class="text-sm text-slate-500 mt-1 leading-6">پاداش و کدهای یکتا را یک‌جا بسازید. هر کد در یک خط؛ Portal3 کد استفاده‌شده را به‌صورت خودکار کنار می‌گذارد.</p></div>
                <form method="post" class="grid grid-cols-1 md:grid-cols-2 gap-4"><?= csrf_input() ?><input type="hidden" name="action" value="create_reward"><div><label class="label" for="reward-site">سایت مقصد</label><select id="reward-site" class="input" name="site_id" required><option value="">انتخاب کنید</option><?php foreach ($sites as $site): if (!(int) $site['is_active']) continue; ?><option value="<?= (int) $site['id'] ?>"><?= e($site['name']) ?></option><?php endforeach; ?></select></div><div><label class="label" for="reward-title">عنوان پاداش</label><input id="reward-title" class="input" name="title" required maxlength="180" placeholder="مثلاً ۱۰٪ تخفیف خرید"></div><div class="md:col-span-2"><label class="label" for="reward-desc">توضیح کوتاه</label><input id="reward-desc" class="input" name="description" maxlength="500" placeholder="مثلاً برای خرید دورهٔ تابستانی"></div><div><label class="label" for="reward-cost">هزینهٔ امتیاز</label><input id="reward-cost" class="input" name="points_cost" type="number" min="1" max="100000000" required dir="ltr"></div><div><label class="label" for="reward-days">اعتبار کد، روز</label><input id="reward-days" class="input" name="valid_days" type="number" min="0" max="3650" value="30" dir="ltr"><p class="helper">صفر یعنی بدون انقضا</p></div><div><label class="label" for="reward-max">سقف دریافت هر مشتری</label><input id="reward-max" class="input" name="max_per_customer" type="number" min="1" max="1000" value="1" dir="ltr"></div><div class="md:col-span-2"><label class="label" for="reward-codes">کدهای تخفیف یکتا، هر خط یک کد</label><textarea id="reward-codes" class="input min-h-[130px]" name="coupon_codes" required maxlength="130000" dir="ltr" spellcheck="false" placeholder="SHOP-001&#10;SHOP-002&#10;SHOP-003"></textarea><p class="helper">حداکثر ۵۰۰ کد در هر مرحله. کد تکراری کل عملیات را متوقف می‌کند تا موجودی ناقص ایجاد نشود.</p></div><div class="md:col-span-2"><button class="btn btn-primary" type="submit"><?= icon('plus') ?><span>ساخت پاداش و افزودن کدها</span></button></div></form>
            </section>

            <section class="portal-content-grid grid grid-cols-1 xl:grid-cols-2 gap-6" aria-label="مدیریت سایت‌ها و کمپین‌ها">
                <section class="portal-panel-card card p-5 sm:p-6 space-y-4" aria-labelledby="sites-title"><div><h2 id="sites-title" class="text-lg font-bold">سایت‌های مقصد</h2><p class="text-sm text-slate-500 mt-1">فقط URL امن HTTPS ثبت کنید. Portal3 خرید را انجام نمی‌دهد و مشتری را به این سایت هدایت می‌کند.</p></div><form method="post" class="grid gap-3"><?= csrf_input() ?><input type="hidden" name="action" value="create_site"><div><label class="label" for="site-name">نام سایت</label><input id="site-name" class="input" name="name" required maxlength="150"></div><div><label class="label" for="site-url">آدرس فروشگاه</label><input id="site-url" class="input" name="base_url" required maxlength="500" dir="ltr" placeholder="https://shop.example.com/discount"></div><button class="btn btn-secondary" type="submit">افزودن سایت</button></form><div class="space-y-3"><?php foreach ($sites as $site): ?><div class="rounded-xl border border-slate-200 p-3"><form method="post" class="grid gap-2"><?= csrf_input() ?><input type="hidden" name="action" value="update_site"><input type="hidden" name="site_id" value="<?= (int) $site['id'] ?>"><div class="grid grid-cols-1 md:grid-cols-2 gap-2"><label class="sr-only" for="site-name-<?= (int) $site['id'] ?>">نام سایت</label><input id="site-name-<?= (int) $site['id'] ?>" class="input" name="name" value="<?= e($site['name']) ?>" required maxlength="150"><label class="sr-only" for="site-url-<?= (int) $site['id'] ?>">آدرس سایت</label><input id="site-url-<?= (int) $site['id'] ?>" class="input" name="base_url" value="<?= e($site['base_url']) ?>" required maxlength="500" dir="ltr"></div><button class="btn btn-secondary btn-sm" type="submit">ذخیره</button></form><div class="flex flex-wrap gap-2"><form method="post"><?= csrf_input() ?><input type="hidden" name="action" value="toggle_site"><input type="hidden" name="site_id" value="<?= (int) $site['id'] ?>"><button class="btn btn-secondary btn-sm" type="submit"><?= (int) $site['is_active'] ? 'غیرفعال‌کردن' : 'فعال‌کردن' ?></button></form><form method="post" data-confirm-msg="سایت فروش حذف شود؟ فقط سایت بدون پاداش وابسته حذف می‌شود." data-confirm-title="تأیید حذف سایت" data-confirm-ok-label="حذف سایت" data-confirm-tone="danger"><?= csrf_input() ?><input type="hidden" name="action" value="delete_site"><input type="hidden" name="site_id" value="<?= (int) $site['id'] ?>"><button class="btn btn-danger btn-sm" type="submit">حذف</button></form></div></div><?php endforeach; ?></div></section>

                <section class="portal-panel-card card p-5 sm:p-6 space-y-4" aria-labelledby="campaign-title"><div><h2 id="campaign-title" class="text-lg font-bold">کد هدیهٔ کمپین</h2><p class="text-sm text-slate-500 mt-1">یک کد برای نمایشگاه یا کمپین بسازید. هر مشتری هر کد را فقط یک‌بار می‌تواند استفاده کند.</p></div><form method="post" class="grid gap-3"><?= csrf_input() ?><input type="hidden" name="action" value="create_campaign"><div><label class="label" for="campaign-name">نام کمپین</label><input id="campaign-name" class="input" name="name" maxlength="150" required></div><div><label class="label" for="campaign-code">کد هدیه</label><input id="campaign-code" class="input" name="code" maxlength="64" required dir="ltr" autocomplete="off" placeholder="EXPO2026"></div><div><label class="label" for="campaign-points">امتیاز هدیه</label><input id="campaign-points" class="input" type="number" name="points" min="1" max="1000000" required dir="ltr"></div><div><label class="label" for="campaign-max">حداکثر مصرف کل، صفر یعنی نامحدود</label><input id="campaign-max" class="input" type="number" name="max_redemptions" min="0" max="10000000" value="0" dir="ltr"></div><div class="grid grid-cols-1 sm:grid-cols-2 gap-3"><div><label class="label" for="campaign-start">شروع</label><input id="campaign-start" class="input" type="datetime-local" name="starts_at" dir="ltr"></div><div><label class="label" for="campaign-expire">انقضا</label><input id="campaign-expire" class="input" type="datetime-local" name="expires_at" dir="ltr"></div></div><button class="btn btn-primary" type="submit">ساخت کد هدیه</button></form><div class="space-y-3"><?php foreach ($campaigns as $campaign): ?><div class="rounded-xl border border-slate-200 p-3 flex items-center justify-between gap-3"><div class="min-w-0"><strong class="block"><bdi dir="auto"><?= e($campaign['name']) ?></bdi></strong><span class="block text-xs text-slate-500 mt-1"><?= e(gamification_points_label((int) $campaign['points'])) ?> · مصرف <?= (int) $campaign['redemptions_count'] ?><?= (int) $campaign['max_redemptions'] > 0 ? ' از ' . (int) $campaign['max_redemptions'] : '' ?></span></div><form method="post"><?= csrf_input() ?><input type="hidden" name="action" value="toggle_campaign"><input type="hidden" name="campaign_id" value="<?= (int) $campaign['id'] ?>"><button class="btn btn-secondary btn-sm" type="submit"><?= (int) $campaign['is_active'] ? 'غیرفعال' : 'فعال' ?></button></form></div><?php endforeach; ?></div></section>
            </section>

            <section class="portal-panel-card card p-5 sm:p-6 space-y-4" aria-labelledby="customer-balances-title"><div><h2 id="customer-balances-title" class="text-lg font-bold">موجودی و عملکرد مشتریان</h2><p class="text-sm text-slate-500 mt-1">مدیر می‌تواند ببیند هر مشتری چه مقدار امتیاز گرفته، خرج کرده و اکنون در اختیار دارد.</p><p class="text-xs text-slate-500 mt-2">نمایش <?= $customerTotal > 0 ? ($customerOffset + 1) : 0 ?> تا <?= min($customerOffset + $customerPageSize, $customerTotal) ?> از <?= $customerTotal ?> مشتری</p></div><div class="table-scroll"><table class="table table-card-mobile"><thead><tr><th>مشتری</th><th>موجودی</th><th>کل کسب‌شده</th><th>کل مصرف‌شده</th><th>تراکنش</th></tr></thead><tbody><?php if (!$customerBalances): ?><tr><td colspan="5" class="p-5 text-center text-slate-500">هنوز مشتری‌ای برای گزارش وجود ندارد.</td></tr><?php endif; ?><?php foreach ($customerBalances as $customer): ?><tr><td data-label="مشتری" class="font-medium"><bdi dir="auto"><?= e(trim((string) $customer['first_name'] . ' ' . (string) $customer['last_name']) ?: $customer['username']) ?></bdi><span class="block text-xs text-slate-500" dir="ltr"><?= e($customer['username']) ?></span></td><td data-label="موجودی" class="font-bold text-indigo-600 tabular-nums" dir="ltr"><?= number_format((int) $customer['balance']) ?></td><td data-label="کل کسب‌شده" class="text-emerald-600 tabular-nums" dir="ltr"><?= number_format((int) $customer['total_earned']) ?></td><td data-label="کل مصرف‌شده" class="text-amber-600 tabular-nums" dir="ltr"><?= number_format((int) $customer['total_spent']) ?></td><td data-label="تراکنش" class="text-slate-500 tabular-nums" dir="ltr"><?= (int) $customer['ledger_count'] ?></td></tr><?php endforeach; ?></tbody></table></div><?php if ($customerPageCount > 1): ?><nav class="flex items-center justify-between gap-3 pt-3" aria-label="صفحه‌بندی موجودی مشتریان"><a class="btn btn-secondary btn-sm <?= $customerPage <= 1 ? 'pointer-events-none opacity-50' : '' ?>" href="?customer_page=<?= max(1, $customerPage - 1) ?>" <?= $customerPage <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>صفحهٔ قبل</a><span class="text-xs text-slate-500">صفحه <?= $customerPage ?> از <?= $customerPageCount ?></span><a class="btn btn-secondary btn-sm <?= $customerPage >= $customerPageCount ? 'pointer-events-none opacity-50' : '' ?>" href="?customer_page=<?= min($customerPageCount, $customerPage + 1) ?>" <?= $customerPage >= $customerPageCount ? 'aria-disabled="true" tabindex="-1"' : '' ?>>صفحهٔ بعد</a></nav><?php endif; ?></section>

            <section class="portal-content-grid grid grid-cols-1 xl:grid-cols-2 gap-6"><section class="portal-panel-card card p-5 sm:p-6 space-y-4" aria-labelledby="rewards-title"><div><h2 id="rewards-title" class="text-lg font-bold">پاداش‌ها و موجودی کد</h2><p class="text-sm text-slate-500 mt-1">پاداش جدید را بالاتر با کدهایش یک‌جا بسازید. این بخش فقط برای شارژ کدهای پاداش موجود است.</p></div><div class="grid grid-cols-1 md:grid-cols-2 gap-4"><?php if (!$rewards): ?><p class="text-sm text-slate-500">هنوز پاداشی ساخته نشده است.</p><?php endif; ?><?php foreach ($rewards as $reward): ?><article class="portal-reward-card rounded-xl border border-slate-200 p-4 space-y-3"><div class="flex items-start justify-between gap-3"><div><h3 class="font-bold"><bdi dir="auto"><?= e($reward['title']) ?></bdi></h3><p class="text-xs text-slate-500 mt-1"><bdi dir="auto"><?= e($reward['site_name']) ?></bdi> · <?= e(gamification_points_label((int) $reward['points_cost'])) ?></p></div><?php $rewardBadge = !(int) $reward['is_active'] ? ['badge-muted', 'غیرفعال'] : (!(int) $reward['site_active'] ? ['badge-warning', 'سایت خاموش'] : ['badge-success', 'فعال']); ?><span class="badge <?= e($rewardBadge[0]) ?>"><?= e($rewardBadge[1]) ?></span></div><p class="text-sm text-slate-500"><?= e($reward['description']) ?></p><?php if ((string) $reward['coupon_mode'] === 'pool'): ?><p class="text-sm">کدهای آماده: <strong class="text-indigo-600" dir="ltr"><?= (int) $reward['available_codes'] ?></strong></p><form method="post" class="space-y-2"><?= csrf_input() ?><input type="hidden" name="action" value="import_coupons"><input type="hidden" name="reward_id" value="<?= (int) $reward['id'] ?>"><label class="label" for="more-codes-<?= (int) $reward['id'] ?>">افزودن کدهای بیشتر، اختیاری</label><textarea id="more-codes-<?= (int) $reward['id'] ?>" name="coupon_codes" class="input min-h-[90px]" dir="ltr" placeholder="SHOP-004&#10;SHOP-005"></textarea><button class="btn btn-secondary btn-sm" type="submit">افزودن کدها</button></form><?php else: ?><p class="text-xs text-amber-700 bg-amber-50 rounded-lg p-3">این پاداش قدیمی از نوع کد ثابت است؛ برای پاداش‌های جدید فقط مخزن کدهای یکتا ساخته می‌شود.</p><?php endif; ?><div class="flex flex-wrap gap-2"><form method="post"><?= csrf_input() ?><input type="hidden" name="action" value="toggle_reward"><input type="hidden" name="reward_id" value="<?= (int) $reward['id'] ?>"><button class="btn btn-secondary btn-sm" type="submit"><?= (int) $reward['is_active'] ? 'غیرفعال‌کردن' : 'فعال‌کردن' ?></button></form><?php if ((int) $reward['redemption_count'] === 0): ?><form method="post" data-confirm-msg="این پاداش و کدهای آن حذف شود؟" data-confirm-title="تأیید حذف پاداش" data-confirm-ok-label="حذف پاداش" data-confirm-tone="danger"><?= csrf_input() ?><input type="hidden" name="action" value="delete_reward"><input type="hidden" name="reward_id" value="<?= (int) $reward['id'] ?>"><button class="btn btn-danger btn-sm" type="submit">حذف</button></form><?php endif; ?></div></article><?php endforeach; ?></div></section>

<section class="portal-panel-card card p-5 sm:p-6 space-y-4" aria-labelledby="ledger-title"><div><h2 id="ledger-title" class="text-lg font-bold">آخرین تراکنش‌های امتیاز</h2><p class="text-sm text-slate-500 mt-1">برای بررسی جزئیات، ۵۰ تراکنش آخر نمایش داده می‌شود؛ گزارش تجمیعی در بخش بالا قرار دارد.</p></div><div class="table-scroll"><table class="table table-card-mobile"><thead><tr><th>مشتری</th><th>شرح</th><th>تغییر</th><th>مانده</th><th>زمان</th></tr></thead><tbody><?php if (!$ledger): ?><tr><td colspan="5" class="p-5 text-center text-slate-500">هنوز تراکنشی ثبت نشده است.</td></tr><?php endif; ?><?php foreach ($ledger as $entry): $delta = (int) $entry['delta']; ?><tr><td data-label="مشتری"><bdi dir="auto"><?= e(trim((string) $entry['first_name'] . ' ' . (string) $entry['last_name']) ?: $entry['username']) ?></bdi></td><td data-label="شرح"><?= e($entry['description']) ?></td><td data-label="تغییر"><span class="badge <?= $delta > 0 ? 'badge-success' : 'badge-danger' ?> tabular-nums" dir="ltr"><?= $delta > 0 ? '+' : '' ?><?= number_format($delta) ?></span></td><td data-label="مانده" class="tabular-nums" dir="ltr"><?= number_format((int) $entry['balance_after']) ?></td><td data-label="زمان" class="text-xs text-slate-500 value-ltr whitespace-nowrap" dir="ltr"><?= e(fa_datetime((string) $entry['created_at'])) ?></td></tr><?php endforeach; ?></tbody></table></div></section></section>
<?php render_admin_footer(); ?>
