<?php
// admin/gamification.php — مدیریت باشگاه امتیاز و پاداش
require_once 'auth.php';
if (!admin_can('gamification')) { header('Location: index.php'); exit; }

global $pdo;
$success = '';
$error = '';
$adminId = (int) ($_SESSION['user_id'] ?? 0);

$admin_datetime = static function (string $value, bool $allowEmpty = true): ?string {
    $value = trim($value);
    if ($value === '' && $allowEmpty) return null;
    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $value);
    if (!$dt || DateTime::getLastErrors() !== false && (DateTime::getLastErrors()['warning_count'] ?? 0) > 0) {
        throw new InvalidArgumentException('تاریخ واردشده معتبر نیست.');
    }
    return $dt->format('Y-m-d H:i:s');
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'toggle_module') {
            set_setting('module_gamification', isset($_POST['enabled']) ? '1' : '0');
            log_activity($adminId, 'تغییر وضعیت ماژول باشگاه امتیاز و پاداش');
            $success = 'وضعیت ماژول Gamification ذخیره شد.';
        } elseif ($action === 'save_rule') {
            $eventKey = (string) ($_POST['event_key'] ?? '');
            if (!isset(gamification_event_catalog()[$eventKey])) throw new InvalidArgumentException('رویداد امتیازدهی معتبر نیست.');
            $points = max(0, min(100000, (int) ($_POST['points'] ?? 0)));
            $dailyCap = max(0, min(1000000, (int) ($_POST['daily_cap'] ?? 0)));
            $cooldown = max(0, min(2592000, (int) ($_POST['cooldown_seconds'] ?? 0)));
            $stmt = $pdo->prepare('UPDATE gamification_rules SET points = ?, daily_cap = ?, cooldown_seconds = ?, is_active = ?, description = ? WHERE event_key = ?');
            $stmt->execute([$points, $dailyCap, $cooldown, isset($_POST['is_active']) ? 1 : 0, trim((string) ($_POST['description'] ?? '')), $eventKey]);
            log_activity($adminId, 'به‌روزرسانی rule امتیازدهی: ' . $eventKey);
            $success = 'قانون امتیازدهی ذخیره شد.';
        } elseif ($action === 'create_site') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $url = trim((string) ($_POST['base_url'] ?? ''));
            if ($name === '' || strlen($name) > 150 || !gamification_validate_https_url($url)) throw new InvalidArgumentException('نام سایت و URL امن HTTPS معتبر الزامی است.');
            $pdo->prepare('INSERT INTO reward_sites (name, base_url, provider, is_active, created_by) VALUES (?, ?, ?, 1, ?)')->execute([$name, $url, 'manual_redirect', $adminId]);
            log_activity($adminId, 'افزودن سایت فروش Gamification: ' . $name);
            $success = 'سایت فروش با موفقیت افزوده شد.';
        } elseif ($action === 'update_site') {
            $siteId = max(0, (int) ($_POST['site_id'] ?? 0));
            $name = trim((string) ($_POST['name'] ?? ''));
            $url = trim((string) ($_POST['base_url'] ?? ''));
            if ($siteId <= 0 || $name === '' || strlen($name) > 150 || !gamification_validate_https_url($url)) throw new InvalidArgumentException('نام و URL امن سایت معتبر نیست.');
            $pdo->prepare('UPDATE reward_sites SET name = ?, base_url = ? WHERE id = ?')->execute([$name, $url, $siteId]);
            log_activity($adminId, 'ویرایش سایت فروش Gamification: ' . $siteId);
            $success = 'اطلاعات سایت فروش به‌روزرسانی شد.';
        } elseif ($action === 'delete_site') {
            $siteId = max(0, (int) ($_POST['site_id'] ?? 0));
            $used = $pdo->prepare('SELECT COUNT(*) FROM reward_catalog WHERE site_id = ?');
            $used->execute([$siteId]);
            if ((int) $used->fetchColumn() > 0) throw new InvalidArgumentException('سایتی که پاداش دارد قابل حذف نیست؛ ابتدا پاداش‌ها را غیرفعال یا حذف کنید.');
            $pdo->prepare('DELETE FROM reward_sites WHERE id = ?')->execute([$siteId]);
            log_activity($adminId, 'حذف سایت فروش Gamification: ' . $siteId);
            $success = 'سایت فروش حذف شد.';
        } elseif ($action === 'toggle_site') {
            $siteId = max(0, (int) ($_POST['site_id'] ?? 0));
            $pdo->prepare('UPDATE reward_sites SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?')->execute([$siteId]);
            log_activity($adminId, 'تغییر وضعیت سایت فروش Gamification: ' . $siteId);
            $success = 'وضعیت سایت فروش تغییر کرد.';
        } elseif ($action === 'create_reward') {
            $siteId = max(0, (int) ($_POST['site_id'] ?? 0));
            $title = trim((string) ($_POST['title'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $cost = max(1, min(100000000, (int) ($_POST['points_cost'] ?? 0)));
            $mode = in_array($_POST['coupon_mode'] ?? '', ['pool', 'fixed'], true) ? (string) $_POST['coupon_mode'] : 'pool';
            $fixed = gamification_normalize_code((string) ($_POST['fixed_coupon_code'] ?? ''));
            $validDays = max(0, min(3650, (int) ($_POST['valid_days'] ?? 30)));
            $maxPerCustomer = max(1, min(1000, (int) ($_POST['max_per_customer'] ?? 1)));
            $siteCheck = $pdo->prepare('SELECT id FROM reward_sites WHERE id = ? AND is_active = 1');
            $siteCheck->execute([$siteId]);
            if (!$siteCheck->fetchColumn() || $title === '' || strlen($title) > 180) throw new InvalidArgumentException('سایت فعال و عنوان پاداش معتبر الزامی است.');
            if ($mode === 'fixed' && !gamification_valid_code($fixed)) throw new InvalidArgumentException('کد ثابت پاداش معتبر نیست.');
            $pdo->prepare('INSERT INTO reward_catalog (site_id, title, description, points_cost, coupon_mode, fixed_coupon_code, valid_days, max_per_customer, is_active, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)')->execute([$siteId, $title, $description, $cost, $mode, $mode === 'fixed' ? $fixed : '', $validDays, $maxPerCustomer, $adminId]);
            log_activity($adminId, 'افزودن پاداش Gamification: ' . $title);
            $success = 'پاداش با موفقیت ایجاد شد.';
        } elseif ($action === 'toggle_reward') {
            $rewardId = max(0, (int) ($_POST['reward_id'] ?? 0));
            $pdo->prepare('UPDATE reward_catalog SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?')->execute([$rewardId]);
            log_activity($adminId, 'تغییر وضعیت پاداش Gamification: ' . $rewardId);
            $success = 'وضعیت پاداش تغییر کرد.';
        } elseif ($action === 'delete_reward') {
            $rewardId = max(0, (int) ($_POST['reward_id'] ?? 0));
            $used = $pdo->prepare('SELECT COUNT(*) FROM reward_redemptions WHERE reward_id = ?');
            $used->execute([$rewardId]);
            if ((int) $used->fetchColumn() > 0) throw new InvalidArgumentException('پاداش دارای سابقهٔ دریافت است و قابل حذف نیست؛ آن را غیرفعال کنید.');
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
            $maxPerCustomer = max(1, min(1000, (int) ($_POST['max_per_customer'] ?? 1)));
            if ($name === '' || strlen($name) > 150 || !gamification_valid_code($code)) throw new InvalidArgumentException('نام کمپین و کد یکتای معتبر الزامی است.');
            if ($startsAt !== null && $expiresAt !== null && strtotime($expiresAt) < strtotime($startsAt)) throw new InvalidArgumentException('تاریخ پایان باید بعد از شروع باشد.');
            $pdo->prepare('INSERT INTO bonus_code_campaigns (name, code_hash, points, starts_at, expires_at, max_redemptions, max_per_customer, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([$name, hash('sha256', $code), $points, $startsAt, $expiresAt, $maxRedemptions, $maxPerCustomer, $adminId]);
            log_activity($adminId, 'ایجاد کمپین کد هدیه Gamification: ' . $name);
            $success = 'کمپین کد هدیه ایجاد شد. کد خام فقط نزد شماست و در دیتابیس ذخیره نمی‌شود.';
        } elseif ($action === 'toggle_campaign') {
            $campaignId = max(0, (int) ($_POST['campaign_id'] ?? 0));
            $pdo->prepare('UPDATE bonus_code_campaigns SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?')->execute([$campaignId]);
            log_activity($adminId, 'تغییر وضعیت کمپین کد هدیه: ' . $campaignId);
            $success = 'وضعیت کمپین تغییر کرد.';
        } elseif ($action === 'import_coupons') {
            $rewardId = max(0, (int) ($_POST['reward_id'] ?? 0));
            $rawCodes = preg_split('/\R+/', (string) ($_POST['coupon_codes'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $codes = [];
            foreach ($rawCodes as $raw) {
                $code = gamification_normalize_code((string) $raw);
                if (!gamification_valid_code($code)) throw new InvalidArgumentException('یکی از کدها معتبر نیست: فقط حروف انگلیسی، عدد، خط تیره و زیرخط.');
                $codes[$code] = true;
                if (count($codes) > 500) throw new InvalidArgumentException('در هر مرحله حداکثر ۵۰۰ کد وارد کنید.');
            }
            if ($rewardId <= 0 || !$codes) throw new InvalidArgumentException('پاداش و حداقل یک کد الزامی است.');
            $check = $pdo->prepare('SELECT id FROM reward_catalog WHERE id = ? AND coupon_mode = \'pool\'');
            $check->execute([$rewardId]);
            if (!$check->fetchColumn()) throw new InvalidArgumentException('پاداش pool معتبر انتخاب نشده است.');
            $pdo->beginTransaction();
            $insert = $pdo->prepare('INSERT INTO reward_coupon_pool (reward_id, coupon_code, coupon_hash) VALUES (?, ?, ?)');
            foreach (array_keys($codes) as $code) $insert->execute([$rewardId, $code, hash('sha256', $code)]);
            $pdo->commit();
            log_activity($adminId, 'افزودن ' . count($codes) . ' کد تخفیف به pool پاداش: ' . $rewardId);
            $success = count($codes) . ' کد تخفیف به مخزن پاداش افزوده شد.';
        }
    } catch (InvalidArgumentException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[Admin Gamification] ' . $e->getMessage());
        $error = $e instanceof PDOException && $e->getCode() === '23000' ? 'دادهٔ تکراری یا ناسازگار است؛ مقدارها را بررسی کنید.' : 'عملیات Gamification انجام نشد. اطلاعات را بررسی کنید.';
    }
}

$rules = $pdo->query('SELECT * FROM gamification_rules ORDER BY id')->fetchAll();
$sites = $pdo->query('SELECT * FROM reward_sites ORDER BY id DESC')->fetchAll();
$rewards = $pdo->query("SELECT r.*, s.name site_name, (SELECT COUNT(*) FROM reward_coupon_pool cp WHERE cp.reward_id = r.id AND cp.status = 'available' AND (cp.expires_at IS NULL OR cp.expires_at >= UTC_TIMESTAMP())) available_codes, (SELECT COUNT(*) FROM reward_redemptions rr WHERE rr.reward_id = r.id) redemption_count FROM reward_catalog r JOIN reward_sites s ON s.id = r.site_id ORDER BY r.id DESC")->fetchAll();
$campaigns = $pdo->query('SELECT * FROM bonus_code_campaigns ORDER BY id DESC LIMIT 100')->fetchAll();
$ledger = $pdo->query('SELECT l.*, u.username FROM customer_point_ledger l JOIN users u ON u.id = l.customer_id ORDER BY l.id DESC LIMIT 30')->fetchAll();
$catalog = gamification_event_catalog();
$moduleOn = gamification_enabled();

render_admin_header('باشگاه امتیاز و پاداش', 'p-8 max-w-7xl w-full mx-auto space-y-6', '', '');
?>
            <?php if ($success): ?><div class="alert alert-success" role="status"><?= e($success) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>

            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div><h2 class="text-2xl font-bold text-slate-900">باشگاه امتیاز و پاداش</h2><p class="text-sm text-slate-500 mt-1">امتیازدهی قابل‌کنترل، کدهای هدیه و پاداش‌های قابل‌استفاده در سایت فروش</p></div>
                <div class="flex items-center gap-3 px-4 py-3 rounded-xl border <?= $moduleOn ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : 'bg-amber-50 border-amber-200 text-amber-700' ?>"><span><?= $moduleOn ? 'فعال' : 'غیرفعال' ?></span></div>
            </div>

            <div class="card p-5 border-2 <?= $moduleOn ? 'border-emerald-100' : 'border-amber-100' ?>">
                <form method="post" class="flex flex-col md:flex-row md:items-center md:justify-between gap-4"><?= csrf_input() ?><input type="hidden" name="action" value="toggle_module"><div><h3 class="font-bold text-slate-900">فعال‌سازی ماژول</h3><p class="text-sm text-slate-500 mt-1">با غیرفعال‌کردن، امتیازدهی و صفحهٔ مشتری پنهان می‌شود؛ داده‌ها حذف نمی‌شوند.</p></div><label class="inline-flex items-center gap-3 cursor-pointer"><input type="checkbox" name="enabled" value="1" <?= $moduleOn ? 'checked' : '' ?> class="w-5 h-5 text-indigo-600"><span class="font-medium">باشگاه فعال باشد</span><button class="btn btn-primary" type="submit">ذخیره وضعیت</button></label></form>
            </div>

            <section class="card p-5 space-y-4"><div><h3 class="text-lg font-bold text-slate-900">قوانین امتیازدهی</h3><p class="text-sm text-slate-500">عدد صفر به‌معنای توقف امتیاز رویداد است. سقف روزانهٔ صفر یعنی بدون سقف.</p></div><div class="space-y-3">
            <?php foreach ($rules as $rule): $meta = $catalog[$rule['event_key']] ?? ['title' => $rule['event_key'], 'description' => '']; ?>
                <form method="post" class="border border-slate-200 rounded-xl p-4 space-y-3"><div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3"><?= csrf_input() ?><input type="hidden" name="action" value="save_rule"><input type="hidden" name="event_key" value="<?= e($rule['event_key']) ?>"><div><strong><?= e($meta['title']) ?></strong><span class="block text-xs text-slate-400" dir="ltr"><?= e($rule['event_key']) ?></span></div><label class="inline-flex gap-2 items-center"><input type="checkbox" name="is_active" value="1" <?= (int) $rule['is_active'] ? 'checked' : '' ?>> فعال</label></div><div class="grid grid-cols-1 sm:grid-cols-3 gap-3"><div><label class="label" for="points-<?= e($rule['event_key']) ?>">امتیاز</label><input id="points-<?= e($rule['event_key']) ?>" class="input" type="number" name="points" min="0" max="100000" value="<?= (int) $rule['points'] ?>" dir="ltr"></div><div><label class="label" for="cap-<?= e($rule['event_key']) ?>">سقف روزانه</label><input id="cap-<?= e($rule['event_key']) ?>" class="input" type="number" name="daily_cap" min="0" max="1000000" value="<?= (int) $rule['daily_cap'] ?>" dir="ltr"></div><div><label class="label" for="cooldown-<?= e($rule['event_key']) ?>">Cooldown ثانیه</label><input id="cooldown-<?= e($rule['event_key']) ?>" class="input" type="number" name="cooldown_seconds" min="0" max="2592000" value="<?= (int) $rule['cooldown_seconds'] ?>" dir="ltr"></div></div><button class="btn btn-secondary btn-sm self-start" type="submit">ذخیره قانون</button></form>
            <?php endforeach; ?></div></section>

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6"><section class="card p-5 space-y-4"><div><h3 class="text-lg font-bold">سایت‌های فروش</h3><p class="text-sm text-slate-500">در نسخهٔ نخست لینک امن دستی استفاده می‌شود.</p></div><form method="post" class="grid gap-3"><?= csrf_input() ?><input type="hidden" name="action" value="create_site"><label class="label" for="site-name">نام سایت</label><input id="site-name" class="input" name="name" required maxlength="150"><label class="label" for="site-url">URL خرید/فروشگاه (HTTPS)</label><input id="site-url" class="input" name="base_url" required maxlength="500" dir="ltr" placeholder="https://shop.example.com/discount"><button class="btn btn-primary" type="submit">افزودن سایت</button></form><div class="space-y-3"><?php foreach ($sites as $site): ?><div class="border rounded-xl p-3 space-y-3"><form method="post" class="grid gap-2"><?= csrf_input() ?><input type="hidden" name="action" value="update_site"><input type="hidden" name="site_id" value="<?= (int) $site['id'] ?>"><div class="grid grid-cols-1 md:grid-cols-2 gap-2"><label class="sr-only" for="site-name-<?= (int) $site['id'] ?>">نام سایت</label><input id="site-name-<?= (int) $site['id'] ?>" class="input" name="name" value="<?= e($site['name']) ?>" required maxlength="150"><label class="sr-only" for="site-url-<?= (int) $site['id'] ?>">URL سایت</label><input id="site-url-<?= (int) $site['id'] ?>" class="input" name="base_url" value="<?= e($site['base_url']) ?>" required maxlength="500" dir="ltr"></div><button class="btn btn-secondary btn-sm self-start" type="submit">ذخیره تغییرات</button></form><div class="flex flex-wrap items-center gap-2"><form method="post"><?= csrf_input() ?><input type="hidden" name="action" value="toggle_site"><input type="hidden" name="site_id" value="<?= (int) $site['id'] ?>"><button class="btn btn-secondary btn-sm" type="submit"><?= (int) $site['is_active'] ? 'غیرفعال‌کردن' : 'فعال‌کردن' ?></button></form><form method="post" data-confirm-msg="سایت فروش حذف شود؟ این عملیات فقط برای سایتی ممکن است که پاداش وابسته نداشته باشد." data-confirm-title="تأیید حذف سایت" data-confirm-ok-label="حذف سایت" data-confirm-tone="danger"><?= csrf_input() ?><input type="hidden" name="action" value="delete_site"><input type="hidden" name="site_id" value="<?= (int) $site['id'] ?>"><button class="btn btn-danger btn-sm" type="submit">حذف سایت</button></form></div></div><?php endforeach; ?></div></section>

            <section class="card p-5 space-y-4"><div><h3 class="text-lg font-bold">ساخت پاداش</h3><p class="text-sm text-slate-500">برای pool، بعداً کدهای یکتا را وارد کنید؛ برای fixed یک کد ثابت تعریف می‌شود.</p></div><form method="post" class="grid gap-3"><?= csrf_input() ?><input type="hidden" name="action" value="create_reward"><label class="label" for="reward-site">سایت مقصد</label><select id="reward-site" class="input" name="site_id" required><option value="">انتخاب کنید</option><?php foreach ($sites as $site): if (!(int) $site['is_active']) continue; ?><option value="<?= (int) $site['id'] ?>"><?= e($site['name']) ?></option><?php endforeach; ?></select><label class="label" for="reward-title">عنوان پاداش</label><input id="reward-title" class="input" name="title" required maxlength="180" placeholder="مثلاً ۱۰٪ تخفیف خرید"><label class="label" for="reward-desc">توضیح</label><input id="reward-desc" class="input" name="description" maxlength="500"><div class="grid grid-cols-2 gap-3"><div><label class="label" for="reward-cost">هزینه امتیاز</label><input id="reward-cost" class="input" name="points_cost" type="number" min="1" required dir="ltr"></div><div><label class="label" for="reward-days">اعتبار روز</label><input id="reward-days" class="input" name="valid_days" type="number" min="0" max="3650" value="30" dir="ltr"></div></div><div class="grid grid-cols-2 gap-3"><div><label class="label" for="reward-mode">نوع کد</label><select id="reward-mode" class="input" name="coupon_mode"><option value="pool">مخزن کدهای یکتا</option><option value="fixed">کد ثابت</option></select></div><div><label class="label" for="reward-max">سقف دریافت هر مشتری</label><input id="reward-max" class="input" name="max_per_customer" type="number" min="1" value="1" dir="ltr"></div></div><label class="label" for="fixed-code">کد ثابت (فقط در حالت fixed)</label><input id="fixed-code" class="input" name="fixed_coupon_code" maxlength="64" dir="ltr" autocomplete="off"><button class="btn btn-primary" type="submit">ساخت پاداش</button></form></section></div>

            <section class="card p-5 space-y-4"><div><h3 class="text-lg font-bold">پاداش‌ها و مخزن کد</h3><p class="text-sm text-slate-500">کدهای واردشده در فهرست عمومی نمایش داده نمی‌شوند؛ فقط تعداد موجودی قابل مشاهده است.</p></div><div class="grid grid-cols-1 md:grid-cols-2 gap-4"><?php foreach ($rewards as $reward): ?><div class="border rounded-xl p-4 space-y-3"><div class="flex justify-between gap-3"><div><strong><?= e($reward['title']) ?></strong><span class="block text-xs text-slate-500"><?= e($reward['site_name']) ?> · <?= gamification_points_label((int) $reward['points_cost']) ?></span></div><span class="text-xs <?= (int) $reward['is_active'] ? 'text-emerald-600' : 'text-slate-400' ?>"><?= (int) $reward['is_active'] ? 'فعال' : 'غیرفعال' ?></span></div><p class="text-sm text-slate-500"><?= e($reward['description']) ?></p><?php if ($reward['coupon_mode'] === 'pool'): ?><p class="text-sm">کدهای آماده: <strong><?= (int) $reward['available_codes'] ?></strong></p><form method="post" class="space-y-2"><?= csrf_input() ?><input type="hidden" name="action" value="import_coupons"><input type="hidden" name="reward_id" value="<?= (int) $reward['id'] ?>"><label class="label" for="codes-<?= (int) $reward['id'] ?>">کدها، هر خط یک کد</label><textarea id="codes-<?= (int) $reward['id'] ?>" name="coupon_codes" class="input min-h-[100px]" dir="ltr" placeholder="SHOP-001&#10;SHOP-002"></textarea><button class="btn btn-secondary btn-sm" type="submit">افزودن کدها</button></form><?php else: ?><p class="text-xs text-amber-700 bg-amber-50 rounded-lg p-2">کد ثابت؛ مصرف واقعی در سایت مقصد کنترل می‌شود.</p><?php endif; ?><form method="post"><?= csrf_input() ?><input type="hidden" name="action" value="toggle_reward"><input type="hidden" name="reward_id" value="<?= (int) $reward['id'] ?>"><button class="btn btn-secondary btn-sm" type="submit"><?= (int) $reward['is_active'] ? 'غیرفعال‌کردن پاداش' : 'فعال‌کردن پاداش' ?></button></form><?php if ((int) $reward['redemption_count'] === 0): ?><form method="post" class="mt-2" data-confirm-msg="این پاداش و کدهای pool آن حذف شود؟" data-confirm-title="تأیید حذف پاداش" data-confirm-ok-label="حذف پاداش" data-confirm-tone="danger"><?= csrf_input() ?><input type="hidden" name="action" value="delete_reward"><input type="hidden" name="reward_id" value="<?= (int) $reward['id'] ?>"><button class="btn btn-danger btn-sm" type="submit">حذف پاداش</button></form><?php endif; ?></div><?php endforeach; ?></div></section>

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6"><section class="card p-5 space-y-4"><div><h3 class="text-lg font-bold">کمپین کد هدیه</h3><p class="text-sm text-slate-500">کد خام ذخیره نمی‌شود؛ آن را نزد خود نگه دارید.</p></div><form method="post" class="grid gap-3"><?= csrf_input() ?><input type="hidden" name="action" value="create_campaign"><label class="label" for="campaign-name">نام کمپین</label><input id="campaign-name" class="input" name="name" maxlength="150" required><label class="label" for="campaign-code">کد هدیه</label><input id="campaign-code" class="input" name="code" maxlength="64" required dir="ltr" autocomplete="off" placeholder="EXPO2026"><div class="grid grid-cols-2 gap-3"><div><label class="label" for="campaign-points">امتیاز</label><input id="campaign-points" class="input" type="number" name="points" min="1" required dir="ltr"></div><div><label class="label" for="campaign-max">سقف کل مصرف، صفر=نامحدود</label><input id="campaign-max" class="input" type="number" name="max_redemptions" min="0" value="0" dir="ltr"></div></div><div class="grid grid-cols-2 gap-3"><div><label class="label" for="campaign-start">شروع</label><input id="campaign-start" class="input" type="datetime-local" name="starts_at" dir="ltr"></div><div><label class="label" for="campaign-expire">انقضا</label><input id="campaign-expire" class="input" type="datetime-local" name="expires_at" dir="ltr"></div></div><label class="label" for="campaign-per-customer">سقف هر مشتری</label><input id="campaign-per-customer" class="input" type="number" name="max_per_customer" min="1" value="1" dir="ltr"><button class="btn btn-primary" type="submit">ساخت کمپین</button></form><?php foreach ($campaigns as $campaign): ?><div class="border rounded-xl p-3 flex items-center justify-between gap-3"><div><strong><?= e($campaign['name']) ?></strong><span class="block text-xs text-slate-500"><?= gamification_points_label((int) $campaign['points']) ?> · مصرف <?= (int) $campaign['redemptions_count'] ?></span></div><form method="post"><?= csrf_input() ?><input type="hidden" name="action" value="toggle_campaign"><input type="hidden" name="campaign_id" value="<?= (int) $campaign['id'] ?>"><button class="btn btn-secondary btn-sm" type="submit"><?= (int) $campaign['is_active'] ? 'غیرفعال' : 'فعال' ?></button></form></div><?php endforeach; ?></section>

            <section class="card p-5 space-y-4"><div><h3 class="text-lg font-bold">دفترکل اخیر امتیازها</h3><p class="text-sm text-slate-500">برای ممیزی امتیازها؛ code و دادهٔ حساس نمایش داده نمی‌شود.</p></div><div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="text-slate-500 border-b"><th class="text-start p-2">مشتری</th><th class="text-start p-2">رویداد</th><th class="text-start p-2">تغییر</th><th class="text-start p-2">مانده</th><th class="text-start p-2">زمان</th></tr></thead><tbody><?php foreach ($ledger as $entry): ?><tr class="border-b last:border-0"><td class="p-2"><?= e($entry['username']) ?></td><td class="p-2"><?= e($entry['description']) ?></td><td class="p-2 <?= (int) $entry['delta'] > 0 ? 'text-emerald-600' : 'text-red-600' ?>" dir="ltr"><?= (int) $entry['delta'] > 0 ? '+' : '' ?><?= (int) $entry['delta'] ?></td><td class="p-2" dir="ltr"><?= (int) $entry['balance_after'] ?></td><td class="p-2 text-xs" dir="ltr"><?= e($entry['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div></section></div>
<?php render_admin_footer(); ?>
