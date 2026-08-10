<?php
// sms_triggers.php — فراخوانی‌های خودکار پیامک در رویدادهای سیستم
// (این فایل توسط توابع مربوطه در صورت فعال بودن هر رویداد صدا زده می‌شود)

/** پیامک خوش‌آمد به مشتری جدید (بعد از ساخت در ادمین) */
function sms_trigger_welcome(int $customer_id): void
{
    if (!sms_event_active('welcome')) {
        return;
    }
    global $pdo;
    $q = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'customer'");
    $q->execute([$customer_id]);
    $u = $q->fetch();
    if (!$u || empty($u['mobile'])) {
        return;
    }
    send_event_sms('welcome', $u['mobile'], sms_customer_vars($u), $customer_id);
}

/** پیامک انتصاب پروژه جدید */
function sms_trigger_project_assigned(int $project_id): void
{
    if (!sms_event_active('project_assigned')) {
        return;
    }
    global $pdo;
    $q = $pdo->prepare("SELECT p.*, u.first_name, u.last_name, u.mobile, u.username, u.company_name, u.job_title, u.gender, u.birth_date, u.created_at AS user_created_at FROM projects p JOIN users u ON u.id = p.customer_id WHERE p.id = ?");
    $q->execute([$project_id]);
    $row = $q->fetch();
    if (!$row || empty($row['mobile'])) {
        return;
    }

    $vars = sms_customer_vars($row);
    $vars += [
        'project_id'          => (string) $row['id'],
        'project_title'       => $row['title'],
        'project_deadline'    => $row['deadline'] ?: '-',
        'project_status'      => project_status_label($row['status'] ?: 'pending'),
        'project_created_at'  => fa_datetime($row['created_at'] ?? null),
    ];
    send_event_sms('project_assigned', $row['mobile'], $vars, (int) $row['customer_id']);
}

/** پیامک ثبت محصول جدید */
function sms_trigger_product_assigned(int $product_id): void
{
    if (!sms_event_active('product_assigned')) {
        return;
    }
    global $pdo;
    $q = $pdo->prepare("SELECT p.*, u.first_name, u.last_name, u.mobile, u.username, u.company_name, u.job_title, u.gender, u.birth_date, u.created_at AS user_created_at FROM products p JOIN users u ON u.id = p.customer_id WHERE p.id = ?");
    $q->execute([$product_id]);
    $row = $q->fetch();
    if (!$row || empty($row['mobile'])) {
        return;
    }

    $vars = sms_customer_vars($row);
    $vars += [
        'product_id'          => (string) $row['id'],
        'product_title'       => $row['title'],
        'product_status'      => product_status_label($row['product_status'] ?? 'purchased'),
        'purchase_date'       => $row['purchase_date'] ?: '-',
        'product_price'       => $row['price'] ?: '-',
        'product_created_at'  => fa_datetime($row['created_at'] ?? null),
    ];
    send_event_sms('product_assigned', $row['mobile'], $vars, (int) $row['customer_id']);
}

/** پیامک صدور فاکتور جدید */
function sms_trigger_invoice_created(int $invoice_id): void
{
    if (!sms_event_active('invoice_created')) {
        return;
    }
    global $pdo;
    $q = $pdo->prepare("SELECT i.*, u.first_name, u.last_name, u.mobile, u.username, u.company_name, u.job_title, u.gender, u.birth_date, u.created_at AS user_created_at FROM invoices i JOIN users u ON u.id = i.customer_id WHERE i.id = ?");
    $q->execute([$invoice_id]);
    $row = $q->fetch();
    if (!$row || empty($row['mobile'])) {
        return;
    }

    $vars = sms_customer_vars($row);
    $vars += [
        'invoice_id'         => (string) $row['id'],
        'invoice_number'     => $row['invoice_number'],
        'invoice_title'      => $row['title'],
        'invoice_amount'     => $row['amount'],
        'due_date'           => $row['due_date'] ?: '-',
        'invoice_status'     => invoice_status_label($row['status'] ?? 'unpaid'),
        'invoice_created_at' => fa_datetime($row['created_at'] ?? null),
    ];
    send_event_sms('invoice_created', $row['mobile'], $vars, (int) $row['customer_id']);
}

/** پیامک پاسخ به تیکت */
function sms_trigger_ticket_reply(int $ticket_id): void
{
    if (!sms_event_active('ticket_reply')) {
        return;
    }
    global $pdo;
    $q = $pdo->prepare(
        "SELECT t.*, u.first_name, u.last_name, u.mobile, u.username, u.company_name, u.job_title, u.gender, u.birth_date, u.created_at AS user_created_at,
                d.name AS department_name
         FROM tickets t
         JOIN users u ON u.id = t.customer_id
         LEFT JOIN ticket_departments d ON d.id = t.department_id
         WHERE t.id = ?"
    );
    $q->execute([$ticket_id]);
    $row = $q->fetch();
    if (!$row || empty($row['mobile'])) {
        return;
    }

    $vars = sms_customer_vars($row);
    $vars += [
        'ticket_id'          => (string) $row['id'],
        'ticket_subject'     => $row['subject'],
        'ticket_status'      => ticket_status_label($row['status'] ?? 'answered'),
        'ticket_priority'    => ticket_priority_label($row['priority'] ?? 'medium'),
        'ticket_department'  => $row['department_name'] ?: '-',
        'ticket_created_at'  => fa_datetime($row['created_at'] ?? null),
    ];
    send_event_sms('ticket_reply', $row['mobile'], $vars, (int) $row['customer_id']);
}

// ---------------------------------------------------------------------------
// یادآوری نظرسنجی ناقص (دستی + خودکار با کرون‌جاب)
// ---------------------------------------------------------------------------

/**
 * ارسال یادآوری برای یک انتساب مشخص (پس از ارسال موفق، شمارنده و زمان به‌روز می‌شود)
 * @return bool آیا پیامک با موفقیت ارسال شد؟
 */
function sms_send_survey_reminder_for_assignment(array $row): bool
{
    global $pdo;
    $q = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'customer'");
    $q->execute([(int) $row['customer_id']]);
    $u = $q->fetch();
    if (!$u || empty($u['mobile'])) {
        return false;
    }

    $vars = sms_customer_vars($u);
    $vars['survey_title'] = (string) ($row['survey_title'] ?? '');
    $vars['entity_title'] = (string) ($row['entity_title'] ?? '');
    $vars['survey_link']  = sms_survey_link((int) $row['id'], (string) ($row['token'] ?? ''));

    $r = send_event_sms('survey_reminder', $u['mobile'], $vars, (int) $u['id']);
    if ($r['ok']) {
        $upd = $pdo->prepare("UPDATE survey_assignments SET reminder_count = reminder_count + 1, last_reminder_at = NOW() WHERE id = ?");
        $upd->execute([(int) $row['id']]);
        return true;
    }
    return false;
}

/** کوئری مشترک برای یافتن انتساب‌های بدون پاسخِ یک مشتری (یا همه) */
function sms_survey_reminder_query(string $customer_where = ''): string
{
    return "SELECT sa.id, sa.survey_id, sa.customer_id, sa.entity_type, sa.entity_id, sa.available_at,
                   sa.last_reminder_at, sa.reminder_count, sa.token,
                   s.title survey_title,
                   CASE WHEN sa.entity_type = 'project' THEN p.title ELSE pr.title END entity_title
            FROM survey_assignments sa
            JOIN surveys s ON s.id = sa.survey_id
            LEFT JOIN projects p ON sa.entity_type = 'project' AND p.id = sa.entity_id
            LEFT JOIN products pr ON sa.entity_type = 'product' AND pr.id = sa.entity_id
            WHERE s.is_active = 1 AND sa.available_at <= NOW()
              AND ((sa.entity_type = 'project' AND p.id IS NOT NULL) OR (sa.entity_type = 'product' AND pr.id IS NOT NULL))
              AND NOT EXISTS (SELECT 1 FROM survey_responses r
                              WHERE r.survey_id = sa.survey_id AND r.customer_id = sa.customer_id
                                AND r.entity_type = sa.entity_type AND r.entity_id = sa.entity_id)
              " . $customer_where;
}

/**
 * یادآوری خودکار طبق تنظیمات ادمین (کرون‌جاب):
 * - اولین یادآوری: بعد از X روز از فعال‌شدن نظرسنجی (survey_reminder_days)
 * - یادآوری‌های بعدی: هر Y روز (survey_reminder_interval) تا حداکثر Z بار (survey_reminder_max)
 * @return int تعداد پیامک‌های ارسال‌شده
 */
function sms_send_due_survey_reminders(): int
{
    global $pdo;
    if (!sms_event_active('survey_reminder')) {
        return 0;
    }
    $cfg = sms_survey_reminder_settings();
    $sql = sms_survey_reminder_query() . " AND sa.reminder_count < :max AND (sa.reminder_count = 0 OR :interval > 0)";
    $q = $pdo->prepare($sql);
    $q->execute(['max' => $cfg['max'], 'interval' => $cfg['interval']]);

    $sent = 0;
    foreach ($q->fetchAll() as $row) {
        $rc = (int) $row['reminder_count'];
        if ($rc === 0) {
            $due = strtotime((string) $row['available_at']) <= time() - $cfg['days'] * 86400;
        } elseif ($cfg['interval'] > 0) {
            $due = strtotime((string) $row['last_reminder_at']) <= time() - $cfg['interval'] * 86400;
        } else {
            $due = false;
        }
        if (!$due) {
            continue;
        }
        if (sms_send_survey_reminder_for_assignment($row)) {
            $sent++;
        }
    }
    return $sent;
}

/** ارسال یادآوری نظرسنجی به همه مشتریانی که نظرسنجی ناقص دارند (دستی از ادمین) */
function sms_broadcast_survey_reminder(): int
{
    global $pdo;
    if (!sms_event_active('survey_reminder')) {
        return 0;
    }
    $sent = 0;
    $q = $pdo->query("SELECT DISTINCT customer_id FROM survey_assignments sa JOIN surveys s ON s.id = sa.survey_id WHERE s.is_active = 1 AND sa.available_at <= NOW() AND NOT EXISTS (SELECT 1 FROM survey_responses r WHERE r.survey_id = sa.survey_id AND r.customer_id = sa.customer_id AND r.entity_type = sa.entity_type AND r.entity_id = sa.entity_id)");
    foreach ($q->fetchAll() as $row) {
        sms_trigger_survey_reminder((int) $row['customer_id']);
        $sent++;
    }
    return $sent;
}

/**
 * پیامک یادآوری نظرسنجی ناقص برای یک مشتری (قدیمی‌ترین انتساب بدون پاسخ)
 * @return bool آیا پیامک ارسال شد؟
 */
function sms_trigger_survey_reminder(int $customer_id): bool
{
    if (!sms_event_active('survey_reminder')) {
        return false;
    }
    global $pdo;
    $sql = sms_survey_reminder_query('AND sa.customer_id = :cid') . " ORDER BY sa.available_at ASC LIMIT 1";
    $q = $pdo->prepare($sql);
    $q->execute(['cid' => $customer_id]);
    $row = $q->fetch();
    if (!$row) {
        return false; // نظرسنجی ناقصی ندارد
    }
    return sms_send_survey_reminder_for_assignment($row);
}
