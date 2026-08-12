<?php
// notifications.php — سیستم اعلانات و اطلاع‌رسانی پورتال

/** انواع اعلان با برچسب فارسی و رنگ */
function notification_types(): array
{
    return [
        'info'    => ['label' => 'اطلاعیه',      'badge' => 'bg-sky-50 text-sky-700',       'icon' => 'ℹ️'],
        'success' => ['label' => 'موفقیت',       'badge' => 'bg-emerald-50 text-emerald-700', 'icon' => '✅'],
        'warning' => ['label' => 'هشدار',        'badge' => 'bg-amber-50 text-amber-700',     'icon' => '⚠️'],
        'danger'  => ['label' => 'مهم / فوری',   'badge' => 'bg-red-50 text-red-600',         'icon' => '🔴'],
        'billing' => ['label' => 'مالی',         'badge' => 'bg-violet-50 text-violet-700',   'icon' => '💳'],
        'support' => ['label' => 'پشتیبانی',     'badge' => 'bg-blue-50 text-blue-700',       'icon' => '🎫'],
    ];
}

/** برچسب فارسی نوع اعلان */
function notification_type_label(string $type): string
{
    $types = notification_types();
    return $types[$type]['label'] ?? 'اطلاعیه';
}

/** بج رنگی نوع اعلان */
function notification_type_badge(string $type): string
{
    $types = notification_types();
    $t = $types[$type] ?? $types['info'];
    return '<span class="' . $t['badge'] . ' text-xs px-2.5 py-1 rounded-full font-medium">' . e($t['label']) . '</span>';
}

/** آیکون نوع اعلان */
function notification_type_icon(string $type): string
{
    $map = [
        'info'    => 'info',
        'success' => 'check',
        'warning' => 'alert',
        'danger'  => 'alert',
        'billing' => 'card',
        'support' => 'message',
    ];
    return icon($map[$type] ?? 'info');
}

/** فهرست گزینه‌های هدف ارسال (فیلترها) */
function notification_targets(): array
{
    return [
        'all'             => 'همه مشتریان',
        'has_project'     => 'مشتریان دارای پروژه',
        'has_product'     => 'مشتریان دارای محصول / لایسنس',
        'unpaid_invoice'  => 'مشتریان دارای فاکتور پرداخت‌نشده',
        'open_ticket'     => 'مشتریان دارای تیکت باز',
        'custom'          => 'انتخاب دستی مشتریان خاص',
    ];
}

/** برچسب قابل‌نمایش هدف اعلان، شامل مقدار legacy تک‌کاربره */
function notification_target_label(string $target_type): string
{
    $targets = notification_targets();
    if (isset($targets[$target_type])) {
        return $targets[$target_type];
    }

    return $target_type === 'user' ? 'یک مشتری' : 'هدف نامشخص';
}

/**
 * محاسبه شناسه گیرندگان بر اساس هدف/فیلتر
 * @return int[] آرایه شناسه کاربران
 */
function notification_recipient_ids(string $target_type, string $target_filter = '', array $custom_ids = []): array
{
    global $pdo;
    if (!$pdo) {
        return [];
    }

    $ids = [];
    switch ($target_type) {
        case 'all':
            $q = $pdo->query("SELECT id FROM users WHERE role = 'customer' ORDER BY id ASC");
            $ids = array_column($q->fetchAll(), 'id');
            break;

        case 'has_project':
            $q = $pdo->query("SELECT DISTINCT customer_id FROM projects");
            $ids = array_map('intval', array_column($q->fetchAll(), 'customer_id'));
            break;

        case 'has_product':
            $q = $pdo->query("SELECT DISTINCT customer_id FROM products");
            $ids = array_map('intval', array_column($q->fetchAll(), 'customer_id'));
            break;

        case 'unpaid_invoice':
            $q = $pdo->query("SELECT DISTINCT customer_id FROM invoices WHERE status = 'unpaid'");
            $ids = array_map('intval', array_column($q->fetchAll(), 'customer_id'));
            break;

        case 'open_ticket':
            $q = $pdo->query("SELECT DISTINCT customer_id FROM tickets WHERE status != 'closed'");
            $ids = array_map('intval', array_column($q->fetchAll(), 'customer_id'));
            break;

        case 'custom':
            $ids = array_values(array_filter(array_map('intval', $custom_ids), fn($v) => $v > 0));
            break;
    }

    // فقط کاربرانی که واقعاً نقش مشتری دارند
    $ids = array_values(array_unique($ids));
    if (!$ids) {
        return [];
    }
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT id FROM users WHERE id IN ($ph) AND role = 'customer'");
    $st->execute($ids);
    return array_map('intval', array_column($st->fetchAll(), 'id'));
}

/**
 * ایجاد اعلان جدید و توزیع آن بین گیرندگان
 * @return int|false شناسه اعلان یا false در صورت خطا
 */
function send_notification(string $title, string $body, string $ntype = 'info', string $target_type = 'all', string $target_filter = '', array $custom_ids = [], ?int $created_by = null, ?string $expires_at = null)
{
    global $pdo;
    if (!$pdo || trim($title) === '') {
        return false;
    }

    $types = array_keys(notification_types());
    if (!in_array($ntype, $types, true)) {
        $ntype = 'info';
    }
    $targets = array_keys(notification_targets());
    if (!in_array($target_type, $targets, true)) {
        $target_type = 'all';
    }

    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) {
        $pdo->beginTransaction();
    }
    try {
        // تاریخ انقضا: اگر شمسی وارد شده باشد به میلادی تبدیل کن (دیت پیکر شمسی است)
        $expires_clean = $expires_at ?: null;
        if ($expires_clean !== null) {
            $conv = jalali_to_gregorian_str($expires_clean);
            if ($conv !== null) {
                $expires_clean = $conv . ' 23:59:59';
            } elseif (!preg_match('#^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$#', $expires_clean)) {
                $expires_clean = null; // قالب نامعتبر — انقضا اعمال نشود
            }
        }
        $q = $pdo->prepare(
            "INSERT INTO notifications (title, body, ntype, target_type, target_filter, created_by, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $q->execute([trim($title), trim($body), $ntype, $target_type, $target_filter, $created_by, $expires_clean]);
        $nid = (int) $pdo->lastInsertId();

        $recipients = notification_recipient_ids($target_type, $target_filter, $custom_ids);
        if ($recipients) {
            $ins = $pdo->prepare(
                "INSERT IGNORE INTO notification_recipients (notification_id, user_id) VALUES (?, ?)"
            );
            foreach ($recipients as $uid) {
                $ins->execute([$nid, $uid]);
            }
        }

        if ($ownTransaction) {
            $pdo->commit();
        }
        return $nid;
    } catch (Throwable $e) {
        if ($ownTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[Notifications] ' . $e->getMessage());
        return false;
    }
}

/** تعداد اعلان‌های نخوانده یک کاربر */
function unread_notifications_count(int $user_id): int
{
    global $pdo;
    if (!$pdo) {
        return 0;
    }
    $q = $pdo->prepare(
        "SELECT COUNT(*) FROM notification_recipients nr
         JOIN notifications n ON n.id = nr.notification_id
         WHERE nr.user_id = ? AND nr.is_read = 0
           AND n.is_active = 1
           AND (n.expires_at IS NULL OR n.expires_at > NOW())"
    );
    $q->execute([$user_id]);
    return (int) $q->fetchColumn();
}

/** اعلان‌های یک کاربر (نزولی) */
function get_user_notifications(int $user_id, int $limit = 20): array
{
    global $pdo;
    if (!$pdo) {
        return [];
    }
    $q = $pdo->prepare(
        "SELECT nr.id recipient_id, nr.is_read, nr.read_at, n.*
         FROM notification_recipients nr
         JOIN notifications n ON n.id = nr.notification_id
         WHERE nr.user_id = ?
           AND n.is_active = 1
           AND (n.expires_at IS NULL OR n.expires_at > NOW())
         ORDER BY n.created_at DESC
         LIMIT " . (int) $limit
    );
    $q->execute([$user_id]);
    return $q->fetchAll();
}

/** علامت‌گذاری یک اعلان به‌عنوان خوانده‌شده */
function mark_notification_read(int $user_id, int $recipient_id): void
{
    global $pdo;
    if (!$pdo) {
        return;
    }
    $q = $pdo->prepare(
        "UPDATE notification_recipients SET is_read = 1, read_at = NOW()
         WHERE id = ? AND user_id = ?"
    );
    $q->execute([$recipient_id, $user_id]);
}

/** علامت‌گذاری همه اعلان‌های کاربر به‌عنوان خوانده‌شده */
function mark_all_notifications_read(int $user_id): void
{
    global $pdo;
    if (!$pdo) {
        return;
    }
    $q = $pdo->prepare(
        "UPDATE notification_recipients SET is_read = 1, read_at = NOW()
         WHERE user_id = ? AND is_read = 0"
    );
    $q->execute([$user_id]);
}

/** آمار خوانده/نخوانده برای یک اعلان (برای پنل ادمین) */
function notification_read_stats(int $notification_id): array
{
    global $pdo;
    if (!$pdo) {
        return ['total' => 0, 'read' => 0, 'unread' => 0];
    }
    $q = $pdo->prepare("SELECT COUNT(*) total, COALESCE(SUM(is_read),0) read_count FROM notification_recipients WHERE notification_id = ?");
    $q->execute([$notification_id]);
    $row = $q->fetch();
    $total = (int) ($row['total'] ?? 0);
    $read = (int) ($row['read_count'] ?? 0);
    return ['total' => $total, 'read' => $read, 'unread' => $total - $read];
}

/** لیست گیرندگان یک اعلان با وضعیت خواندن (برای پنل ادمین) */
function notification_recipients_list(int $notification_id): array
{
    global $pdo;
    if (!$pdo) {
        return [];
    }
    $q = $pdo->prepare(
        "SELECT nr.is_read, nr.read_at, u.id user_id, u.username, u.first_name, u.last_name
         FROM notification_recipients nr
         JOIN users u ON u.id = nr.user_id
         WHERE nr.notification_id = ?
         ORDER BY nr.is_read ASC, nr.id DESC"
    );
    $q->execute([$notification_id]);
    return $q->fetchAll();
}
