<?php
// Gamification domain services: points, campaigns, rewards and manual store redirects.
declare(strict_types=1);

/** @return array<string,array{title:string,description:string}> */
function gamification_event_catalog(): array
{
    return [
        'profile_completed' => ['title' => 'تکمیل کامل پروفایل', 'description' => 'پس از تکمیل همهٔ فیلدهای اجباری پروفایل'],
        'survey_submitted' => ['title' => 'تکمیل نظرسنجی', 'description' => 'پس از ثبت موفق پاسخ نهایی نظرسنجی'],
        'ticket_created' => ['title' => 'ثبت تیکت جدید', 'description' => 'پس از ثبت تیکت؛ مشمول سقف ضداسپم'],
        'ticket_customer_reply' => ['title' => 'پاسخ به تیکت', 'description' => 'پس از پاسخ معتبر مشتری؛ مشمول cooldown و سقف'],
        'bonus_code_redeemed' => ['title' => 'کد هدیهٔ کمپین', 'description' => 'امتیاز کمپین‌های نمایشگاهی و تبلیغاتی'],
    ];
}

function gamification_enabled(): bool
{
    return is_module_enabled('gamification');
}

/** مسیر داخلی مشتری برای ادامهٔ عمل مرتبط با رویداد امتیاز. */
function gamification_event_action_url(string $eventKey): string
{
    return match ($eventKey) {
        'profile_completed' => 'profile.php',
        'survey_submitted' => 'surveys.php',
        'ticket_created', 'ticket_customer_reply' => 'tickets.php',
        'bonus_code_redeemed' => 'gamification.php',
        default => 'gamification.php',
    };
}

function gamification_require_enabled(): void
{
    if (!gamification_enabled()) {
        http_response_code(404);
        exit('این بخش در حال حاضر فعال نیست.');
    }
}

function gamification_normalize_code(string $code): string
{
    $code = strtoupper(trim(fa_digits_to_en($code)));
    return preg_replace('/\s+/', '', $code) ?? '';
}

function gamification_valid_code(string $code): bool
{
    return $code !== '' && strlen($code) >= 6 && strlen($code) <= 64 && (bool) preg_match('/^[A-Z0-9_-]+$/', $code);
}

function gamification_validate_https_url(string $url): bool
{
    $url = trim($url);
    if ($url === '' || strlen($url) > 500 || !filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    return $scheme === 'https' || (defined('PORTAL_DEV_MODE') && PORTAL_DEV_MODE && $scheme === 'http');
}

/** @return array<string,mixed>|null */
function gamification_rule(string $eventKey): ?array
{
    global $pdo;
    if (!$pdo || !isset(gamification_event_catalog()[$eventKey])) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM gamification_rules WHERE event_key = ? LIMIT 1');
    $stmt->execute([$eventKey]);
    $rule = $stmt->fetch();
    return $rule ?: null;
}

function gamification_wallet(int $customerId, bool $forUpdate = false): array
{
    global $pdo;
    if (!$pdo || $customerId <= 0) {
        return ['balance' => 0, 'total_earned' => 0, 'total_spent' => 0];
    }
    $pdo->prepare('INSERT IGNORE INTO customer_point_wallets (customer_id) VALUES (?)')->execute([$customerId]);
    $sql = 'SELECT balance, total_earned, total_spent FROM customer_point_wallets WHERE customer_id = ?';
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$customerId]);
    $row = $stmt->fetch();
    return $row ? [
        'balance' => (int) $row['balance'],
        'total_earned' => (int) $row['total_earned'],
        'total_spent' => (int) $row['total_spent'],
    ] : ['balance' => 0, 'total_earned' => 0, 'total_spent' => 0];
}

function gamification_award_points(
    int $customerId,
    string $eventKey,
    string $idempotencyKey,
    string $description = '',
    string $referenceType = '',
    string $referenceId = ''
): int {
    global $pdo;
    if (!$pdo || !gamification_enabled() || $customerId <= 0 || !isset(gamification_event_catalog()[$eventKey])) {
        return 0;
    }
    $idempotencyKey = substr(trim($idempotencyKey), 0, 120);
    if ($idempotencyKey === '') {
        return 0;
    }
    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) {
        $pdo->beginTransaction();
    }
    try {
        $ruleStmt = $pdo->prepare('SELECT * FROM gamification_rules WHERE event_key = ? AND is_active = 1 FOR UPDATE');
        $ruleStmt->execute([$eventKey]);
        $rule = $ruleStmt->fetch();
        if (!$rule || (int) $rule['points'] <= 0) {
            if ($ownTransaction) $pdo->commit();
            return 0;
        }
        $existing = $pdo->prepare('SELECT id FROM customer_point_ledger WHERE idempotency_key = ? LIMIT 1');
        $existing->execute([$idempotencyKey]);
        if ($existing->fetchColumn()) {
            if ($ownTransaction) $pdo->commit();
            return 0;
        }
        $points = min(100000, max(1, (int) $rule['points']));
        $dailyCap = min(1000000, max(0, (int) $rule['daily_cap']));
        if ($dailyCap > 0) {
            $cap = $pdo->prepare("SELECT COALESCE(SUM(delta), 0) FROM customer_point_ledger WHERE customer_id = ? AND event_key = ? AND delta > 0 AND created_at >= UTC_DATE()");
            $cap->execute([$customerId, $eventKey]);
            if ((int) $cap->fetchColumn() + $points > $dailyCap) {
                if ($ownTransaction) $pdo->commit();
                return 0;
            }
        }
        $cooldown = min(86400 * 30, max(0, (int) $rule['cooldown_seconds']));
        if ($cooldown > 0) {
            $last = $pdo->prepare("SELECT id FROM customer_point_ledger WHERE customer_id = ? AND event_key = ? AND delta > 0 AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL {$cooldown} SECOND) LIMIT 1");
            $last->execute([$customerId, $eventKey]);
            if ($last->fetchColumn()) {
                if ($ownTransaction) $pdo->commit();
                return 0;
            }
        }
        $wallet = gamification_wallet($customerId, true);
        $newBalance = $wallet['balance'] + $points;
        $ledger = $pdo->prepare('INSERT INTO customer_point_ledger (customer_id, delta, balance_after, event_key, reference_type, reference_id, idempotency_key, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $ledger->execute([$customerId, $points, $newBalance, $eventKey, $referenceType, $referenceId, $idempotencyKey, $description !== '' ? $description : (gamification_event_catalog()[$eventKey]['title'] ?? $eventKey)]);
        $walletUpdate = $pdo->prepare('UPDATE customer_point_wallets SET balance = ?, total_earned = total_earned + ?, updated_at = CURRENT_TIMESTAMP WHERE customer_id = ?');
        $walletUpdate->execute([$newBalance, $points, $customerId]);

        // اعلان پایدار باید در مسیر مرکزی award ساخته شود تا هیچ event موفقی جا نماند.
        if (function_exists('send_notification')) {
            $eventTitle = gamification_event_catalog()[$eventKey]['title'] ?? $eventKey;
            $awardMessage = 'برای «' . $eventTitle . '» ' . gamification_points_label($points) . ' گرفتید.';
            if (send_notification('امتیاز جدید دریافت کردید', $awardMessage, 'success', 'custom', '', [$customerId], null, null, gamification_event_action_url($eventKey)) === false) {
                error_log('[Gamification Award] notification delivery failed for customer ' . $customerId . ' / ' . $eventKey);
            }
        }

        if ($ownTransaction) $pdo->commit();
        return $points;
    } catch (Throwable $e) {
        if ($ownTransaction && $pdo->inTransaction()) $pdo->rollBack();
        if ($e instanceof PDOException && $e->getCode() === '23000') {
            return 0;
        }
        error_log('[Gamification Award] ' . $e->getMessage());
        return 0;
    }
}

function gamification_profile_is_complete(int $customerId): bool
{
    global $pdo;
    if (!$pdo || $customerId <= 0) return false;
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? AND role = \'customer\' LIMIT 1');
    $stmt->execute([$customerId]);
    $user = $stmt->fetch();
    if (!$user) return false;
    foreach (['first_name', 'last_name', 'mobile', 'company_name', 'job_title', 'birth_date', 'gender'] as $field) {
        if (get_setting('req_' . $field, '0') === '1' && trim((string) ($user[$field] ?? '')) === '') return false;
    }
    if (is_module_enabled('custom_fields')) {
        $check = $pdo->prepare("SELECT COUNT(*) FROM custom_fields f LEFT JOIN custom_field_values v ON v.field_id = f.id AND v.entity_id = ? WHERE f.target_entity = 'customer' AND f.is_required = 1 AND (v.field_value IS NULL OR TRIM(v.field_value) = '')");
        $check->execute([$customerId]);
        if ((int) $check->fetchColumn() > 0) return false;
    }
    return true;
}

function gamification_award_profile_completion(int $customerId): int
{
    if (!gamification_profile_is_complete($customerId)) return 0;
    return gamification_award_points($customerId, 'profile_completed', 'profile_completed:' . $customerId, 'امتیاز تکمیل کامل پروفایل', 'customer', (string) $customerId);
}

function gamification_customer_has_event(int $customerId, string $eventKey): bool
{
    global $pdo;
    if (!$pdo || $customerId <= 0 || !isset(gamification_event_catalog()[$eventKey])) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT id FROM customer_point_ledger WHERE customer_id = ? AND event_key = ? AND delta > 0 LIMIT 1');
    $stmt->execute([$customerId, $eventKey]);
    return (bool) $stmt->fetchColumn();
}

function gamification_customer_has_idempotency(int $customerId, string $idempotencyKey): bool
{
    global $pdo;
    if (!$pdo || $customerId <= 0 || trim($idempotencyKey) === '') {
        return false;
    }
    $stmt = $pdo->prepare('SELECT id FROM customer_point_ledger WHERE customer_id = ? AND idempotency_key = ? LIMIT 1');
    $stmt->execute([$customerId, substr(trim($idempotencyKey), 0, 160)]);
    return (bool) $stmt->fetchColumn();
}

/** @return array{state:string,points:int,daily_cap:int,cooldown_seconds:int,daily_earned:int,total_earned:int,cooldown_remaining:int,last_awarded_at:?string} */
function gamification_customer_event_status(int $customerId, string $eventKey): array
{
    global $pdo;
    $empty = ['state' => 'disabled', 'points' => 0, 'daily_cap' => 0, 'cooldown_seconds' => 0, 'daily_earned' => 0, 'total_earned' => 0, 'cooldown_remaining' => 0, 'last_awarded_at' => null];
    if (!$pdo || $customerId <= 0 || !gamification_enabled() || !isset(gamification_event_catalog()[$eventKey])) {
        return $empty;
    }
    $rule = gamification_rule($eventKey);
    if (!$rule || !(int) $rule['is_active'] || (int) $rule['points'] <= 0) {
        return $empty;
    }
    $totalStmt = $pdo->prepare('SELECT COALESCE(SUM(delta), 0) FROM customer_point_ledger WHERE customer_id = ? AND event_key = ? AND delta > 0');
    $totalStmt->execute([$customerId, $eventKey]);
    $dailyStmt = $pdo->prepare('SELECT COALESCE(SUM(delta), 0) FROM customer_point_ledger WHERE customer_id = ? AND event_key = ? AND delta > 0 AND created_at >= UTC_DATE()');
    $dailyStmt->execute([$customerId, $eventKey]);
    $lastStmt = $pdo->prepare('SELECT created_at, UNIX_TIMESTAMP(created_at) created_epoch FROM customer_point_ledger WHERE customer_id = ? AND event_key = ? AND delta > 0 ORDER BY id DESC LIMIT 1');
    $lastStmt->execute([$customerId, $eventKey]);
    $lastRow = $lastStmt->fetch();
    $last = $lastRow['created_at'] ?? null;
    $lastEpoch = (int) ($lastRow['created_epoch'] ?? 0);
    $cooldown = min(2592000, max(0, (int) $rule['cooldown_seconds']));
    $remaining = 0;
    if ($lastEpoch > 0 && $cooldown > 0) {
        $remaining = max(0, $cooldown - (time() - $lastEpoch));
    }
    $total = (int) $totalStmt->fetchColumn();
    $daily = (int) $dailyStmt->fetchColumn();
    $state = 'available';
    if ($eventKey === 'profile_completed' && $total > 0) {
        $state = 'received';
    } elseif ((int) $rule['daily_cap'] > 0 && $daily >= (int) $rule['daily_cap']) {
        $state = 'daily_cap';
    } elseif ($remaining > 0) {
        $state = 'cooldown';
    }
    return ['state' => $state, 'points' => (int) $rule['points'], 'daily_cap' => (int) $rule['daily_cap'], 'cooldown_seconds' => $cooldown, 'daily_earned' => $daily, 'total_earned' => $total, 'cooldown_remaining' => $remaining, 'last_awarded_at' => $last ? (string) $last : null];
}

function gamification_award_feedback(int $customerId, string $eventKey, int $points): void
{
    if ($customerId <= 0 || $points <= 0 || !isset(gamification_event_catalog()[$eventKey])) {
        return;
    }
    $eventTitle = gamification_event_catalog()[$eventKey]['title'];
    $message = 'برای «' . $eventTitle . '» ' . gamification_points_label($points) . ' گرفتید.';
    $_SESSION['gamification_award_flash'] = ['message' => $message, 'event_key' => $eventKey, 'points' => $points];
}

function gamification_bonus_feedback(int $customerId, int $points): void
{
    if ($customerId <= 0 || $points <= 0) {
        return;
    }
    $message = 'کد هدیه پذیرفته شد و ' . gamification_points_label($points) . ' به موجودی شما اضافه شد.';
    $_SESSION['gamification_award_flash'] = ['message' => $message, 'event_key' => 'bonus_code_redeemed', 'points' => $points];
    if (function_exists('send_notification')) {
        send_notification('امتیاز هدیه دریافت کردید', $message, 'success', 'custom', '', [$customerId], null, null, gamification_event_action_url('bonus_code_redeemed'));
    }
}

/** @return array{event_key:string,title:string,description:string,points:int,daily_cap:int,cooldown_seconds:int}|null */
function gamification_context_offer(int $customerId, string $eventKey): ?array
{
    if (!gamification_enabled() || $customerId <= 0 || !isset(gamification_event_catalog()[$eventKey])) {
        return null;
    }
    $rule = gamification_rule($eventKey);
    if (!$rule || !(int) $rule['is_active'] || (int) $rule['points'] <= 0) {
        return null;
    }
    $status = gamification_customer_event_status($customerId, $eventKey);
    if ($status['state'] !== 'available') {
        return null;
    }
    $event = gamification_event_catalog()[$eventKey];
    return [
        'event_key' => $eventKey,
        'title' => $event['title'],
        'description' => $event['description'],
        'points' => (int) $rule['points'],
        'daily_cap' => (int) $rule['daily_cap'],
        'cooldown_seconds' => (int) $rule['cooldown_seconds'],
    ];
}

/** @return array{points:int,balance:int} */
function gamification_redeem_bonus_code(int $customerId, string $rawCode): array
{
    global $pdo;
    if (!$pdo || !gamification_enabled() || $customerId <= 0) throw new RuntimeException('این قابلیت فعال نیست.');
    $code = gamification_normalize_code($rawCode);
    if (!gamification_valid_code($code)) throw new RuntimeException('کد هدیه معتبر نیست.');
    $hash = hash('sha256', $code);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM bonus_code_campaigns WHERE code_hash = ? AND is_active = 1 LIMIT 1 FOR UPDATE');
        $stmt->execute([$hash]);
        $campaign = $stmt->fetch();
        $now = time();
        if (!$campaign || ($campaign['starts_at'] && strtotime((string) $campaign['starts_at']) > $now) || ($campaign['expires_at'] && strtotime((string) $campaign['expires_at']) < $now)) {
            throw new RuntimeException('کد هدیه معتبر نیست یا منقضی شده است.');
        }
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM bonus_code_redemptions WHERE campaign_id = ? AND customer_id = ?');
        $countStmt->execute([(int) $campaign['id'], $customerId]);
        if ((int) $campaign['max_per_customer'] > 0 && (int) $countStmt->fetchColumn() >= (int) $campaign['max_per_customer']) {
            throw new RuntimeException('این کد را قبلاً به سقف مجاز استفاده کرده‌اید.');
        }
        if ((int) $campaign['max_redemptions'] > 0 && (int) $campaign['redemptions_count'] >= (int) $campaign['max_redemptions']) {
            throw new RuntimeException('ظرفیت استفاده از این کد تکمیل شده است.');
        }
        $points = min(1000000, max(1, (int) $campaign['points']));
        $wallet = gamification_wallet($customerId, true);
        $newBalance = $wallet['balance'] + $points;
        $idempotency = 'bonus:' . (int) $campaign['id'] . ':' . $customerId . ':' . hash('sha256', $code);
        $existing = $pdo->prepare('SELECT id FROM customer_point_ledger WHERE idempotency_key = ? LIMIT 1');
        $existing->execute([$idempotency]);
        if ($existing->fetchColumn()) throw new RuntimeException('این کد قبلاً ثبت شده است.');
        $ledger = $pdo->prepare('INSERT INTO customer_point_ledger (customer_id, delta, balance_after, event_key, reference_type, reference_id, idempotency_key, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $ledger->execute([$customerId, $points, $newBalance, 'bonus_code_redeemed', 'bonus_campaign', (string) $campaign['id'], $idempotency, 'امتیاز هدیهٔ ' . $campaign['name']]);
        $ledgerId = (int) $pdo->lastInsertId();
        $redemption = $pdo->prepare('INSERT INTO bonus_code_redemptions (campaign_id, customer_id, ledger_id) VALUES (?, ?, ?)');
        $redemption->execute([(int) $campaign['id'], $customerId, $ledgerId]);
        $pdo->prepare('UPDATE bonus_code_campaigns SET redemptions_count = redemptions_count + 1 WHERE id = ?')->execute([(int) $campaign['id']]);
        $pdo->prepare('UPDATE customer_point_wallets SET balance = ?, total_earned = total_earned + ?, updated_at = CURRENT_TIMESTAMP WHERE customer_id = ?')->execute([$newBalance, $points, $customerId]);
        $pdo->commit();
        gamification_bonus_feedback($customerId, $points);
        return ['points' => $points, 'balance' => $newBalance];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($e instanceof PDOException && $e->getCode() === '23000') throw new RuntimeException('این کد قبلاً ثبت شده است.');
        throw $e;
    }
}

/** @return array<int,array<string,mixed>> */
function gamification_list_rewards(int $customerId = 0): array
{
    global $pdo;
    if (!$pdo) return [];
    $sql = "SELECT r.*, s.name site_name, s.base_url, s.is_active site_active,
                   (SELECT COUNT(*) FROM reward_coupon_pool cp WHERE cp.reward_id = r.id AND cp.status = 'available' AND (cp.expires_at IS NULL OR cp.expires_at >= UTC_TIMESTAMP())) available_codes
            FROM reward_catalog r JOIN reward_sites s ON s.id = r.site_id WHERE r.is_active = 1 AND s.is_active = 1 ORDER BY r.points_cost, r.id";
    $rows = $pdo->query($sql)->fetchAll();
    if ($customerId > 0) {
        foreach ($rows as &$row) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM reward_redemptions WHERE reward_id = ? AND customer_id = ? AND status = \'issued\'');
            $stmt->execute([(int) $row['id'], $customerId]);
            $row['customer_redemptions'] = (int) $stmt->fetchColumn();
        }
        unset($row);
    }
    return $rows;
}

/** @return array{reward_id:int,title:string,coupon_code:string,redirect_url:string,expires_at:?string,balance:int,redemption_id:int} */
function gamification_redeem_reward(int $customerId, int $rewardId, string $nonce): array
{
    global $pdo;
    if (!$pdo || !gamification_enabled() || $customerId <= 0 || $rewardId <= 0) {
        throw new RuntimeException('این قابلیت فعال نیست.');
    }
    $nonce = trim($nonce);
    if ($nonce === '' || strlen($nonce) > 80 || !preg_match('/^[A-Za-z0-9_-]+$/', $nonce)) {
        throw new RuntimeException('درخواست تبدیل امتیاز معتبر نیست.');
    }
    $idempotency = 'reward:' . $customerId . ':' . $rewardId . ':' . $nonce;
    $findExisting = static function (PDO $db, string $key): ?array {
        $stmt = $db->prepare('SELECT rr.id redemption_id, rr.reward_id, rr.coupon_code_snapshot coupon_code, rr.redirect_url_snapshot redirect_url, rr.expires_at, rr.status, rr.points_cost, rr.customer_id, r.title FROM reward_redemptions rr JOIN reward_catalog r ON r.id = rr.reward_id WHERE rr.idempotency_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ?: null;
    };
    $pdo->beginTransaction();
    try {
        $existing = $findExisting($pdo, $idempotency);
        if ($existing) {
            $wallet = gamification_wallet($customerId, true);
            $pdo->commit();
            return ['reward_id' => (int) $existing['reward_id'], 'title' => (string) $existing['title'], 'coupon_code' => (string) $existing['coupon_code'], 'redirect_url' => (string) $existing['redirect_url'], 'expires_at' => $existing['expires_at'] !== null ? (string) $existing['expires_at'] : null, 'balance' => $wallet['balance'], 'redemption_id' => (int) $existing['redemption_id']];
        }
        $rewardStmt = $pdo->prepare('SELECT r.*, s.name site_name, s.base_url, s.is_active site_active FROM reward_catalog r JOIN reward_sites s ON s.id = r.site_id WHERE r.id = ? AND r.is_active = 1 FOR UPDATE');
        $rewardStmt->execute([$rewardId]);
        $reward = $rewardStmt->fetch();
        if (!$reward || !(bool) $reward['site_active'] || !gamification_validate_https_url((string) $reward['base_url'])) {
            throw new RuntimeException('این پاداش در حال حاضر قابل دریافت نیست.');
        }
        $already = $pdo->prepare("SELECT COUNT(*) FROM reward_redemptions WHERE reward_id = ? AND customer_id = ? AND status = 'issued'");
        $already->execute([$rewardId, $customerId]);
        if ((int) $already->fetchColumn() >= (int) $reward['max_per_customer']) {
            throw new RuntimeException('این پاداش را قبلاً به سقف مجاز دریافت کرده‌اید.');
        }
        $wallet = gamification_wallet($customerId, true);
        $cost = (int) $reward['points_cost'];
        if ($cost <= 0 || $wallet['balance'] < $cost) {
            throw new RuntimeException('موجودی امتیاز شما برای این پاداش کافی نیست.');
        }
        $expiresAt = (int) $reward['valid_days'] > 0 ? gmdate('Y-m-d H:i:s', time() + ((int) $reward['valid_days'] * 86400)) : null;
        $couponId = null;
        $couponCode = '';
        if ($reward['coupon_mode'] === 'pool') {
            $couponStmt = $pdo->prepare("SELECT id, coupon_code, expires_at FROM reward_coupon_pool WHERE reward_id = ? AND status = 'available' AND (expires_at IS NULL OR expires_at >= UTC_TIMESTAMP()) ORDER BY id LIMIT 1 FOR UPDATE");
            $couponStmt->execute([$rewardId]);
            $coupon = $couponStmt->fetch();
            if (!$coupon) throw new RuntimeException('کد تخفیف این پاداش فعلاً موجود نیست.');
            $couponId = (int) $coupon['id'];
            $couponCode = (string) $coupon['coupon_code'];
            if ($coupon['expires_at'] !== null && ($expiresAt === null || strtotime((string) $coupon['expires_at']) < strtotime($expiresAt))) {
                $expiresAt = (string) $coupon['expires_at'];
            }
            $pdo->prepare("UPDATE reward_coupon_pool SET status = 'issued', assigned_customer_id = ?, issued_at = UTC_TIMESTAMP() WHERE id = ? AND status = 'available'")->execute([$customerId, $couponId]);
        } else {
            $couponCode = gamification_normalize_code((string) $reward['fixed_coupon_code']);
            if (!gamification_valid_code($couponCode)) throw new RuntimeException('کد ثابت این پاداش توسط مدیر معتبر تنظیم نشده است.');
        }
        $newBalance = $wallet['balance'] - $cost;
        $redemption = $pdo->prepare('INSERT INTO reward_redemptions (reward_id, customer_id, coupon_id, coupon_code_snapshot, points_cost, site_id, redirect_url_snapshot, expires_at, status, idempotency_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'issued\', ?)');
        $redemption->execute([$rewardId, $customerId, $couponId, $couponCode, $cost, (int) $reward['site_id'], (string) $reward['base_url'], $expiresAt, $idempotency]);
        $redemptionId = (int) $pdo->lastInsertId();
        $ledger = $pdo->prepare('INSERT INTO customer_point_ledger (customer_id, delta, balance_after, event_key, reference_type, reference_id, idempotency_key, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $ledger->execute([$customerId, -$cost, $newBalance, 'reward_redeemed', 'reward_redemption', (string) $redemptionId, $idempotency . ':ledger', 'تبدیل امتیاز به پاداش: ' . $reward['title']]);
        $pdo->prepare('UPDATE customer_point_wallets SET balance = ?, total_spent = total_spent + ?, updated_at = CURRENT_TIMESTAMP WHERE customer_id = ?')->execute([$newBalance, $cost, $customerId]);
        $pdo->commit();
        return ['reward_id' => $rewardId, 'title' => (string) $reward['title'], 'coupon_code' => $couponCode, 'redirect_url' => (string) $reward['base_url'], 'expires_at' => $expiresAt, 'balance' => $newBalance, 'redemption_id' => $redemptionId];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($e instanceof PDOException && $e->getCode() === '23000') {
            $existing = $findExisting($pdo, $idempotency);
            if ($existing) {
                $wallet = gamification_wallet($customerId);
                return ['reward_id' => (int) $existing['reward_id'], 'title' => (string) $existing['title'], 'coupon_code' => (string) $existing['coupon_code'], 'redirect_url' => (string) $existing['redirect_url'], 'expires_at' => $existing['expires_at'] !== null ? (string) $existing['expires_at'] : null, 'balance' => $wallet['balance'], 'redemption_id' => (int) $existing['redemption_id']];
            }
        }
        throw $e;
    }
}

/** @return array{wallet:array<string,int>,ledger:array<int,array<string,mixed>>,redemptions:array<int,array<string,mixed>>} */
function gamification_customer_summary(int $customerId): array
{
    global $pdo;
    $wallet = gamification_wallet($customerId);
    if (!$pdo || $customerId <= 0) return ['wallet' => $wallet, 'ledger' => [], 'redemptions' => []];
    $ledgerStmt = $pdo->prepare('SELECT delta, balance_after, event_key, description, created_at FROM customer_point_ledger WHERE customer_id = ? ORDER BY id DESC LIMIT 50');
    $ledgerStmt->execute([$customerId]);
    $redemptionStmt = $pdo->prepare('SELECT rr.*, r.title reward_title, s.name site_name FROM reward_redemptions rr JOIN reward_catalog r ON r.id = rr.reward_id LEFT JOIN reward_sites s ON s.id = r.site_id WHERE rr.customer_id = ? ORDER BY rr.id DESC LIMIT 30');
    $redemptionStmt->execute([$customerId]);
    return ['wallet' => $wallet, 'ledger' => $ledgerStmt->fetchAll(), 'redemptions' => $redemptionStmt->fetchAll()];
}

function gamification_points_label(int $points): string
{
    return number_format($points) . ' امتیاز';
}
