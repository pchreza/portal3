<?php
// admin/tickets.php - Admin Support Tickets Management (با فیلترها و دپارتمان‌ها)
require_once 'auth.php';
if (!admin_can('tickets')) { header('Location: index.php'); exit; }
if (!is_module_enabled('tickets')) { header('Location: index.php'); exit; }

// ========== اکسل: خروجی تیکت‌ها ==========
if (isset($_GET['export']) && $_GET['export'] === 'xlsx') {
    $q = $pdo->query("
        SELECT t.id, t.subject, t.status, t.priority, d.name AS department_name,
               u.first_name, u.last_name, u.username, u.company_name, t.created_at,
               (SELECT COUNT(*) FROM ticket_messages m WHERE m.ticket_id = t.id) AS messages_count,
               (SELECT m.message FROM ticket_messages m WHERE m.ticket_id = t.id ORDER BY m.id DESC LIMIT 1) AS last_message
        FROM tickets t
        JOIN users u ON u.id = t.customer_id
        LEFT JOIN ticket_departments d ON d.id = t.department_id
        ORDER BY t.id DESC");
    $rows = [['شناسه', 'موضوع', 'مشتری', 'نام کاربری مشتری', 'دپارتمان', 'اولویت', 'وضعیت', 'تعداد پیام', 'آخرین پیام', 'تاریخ ایجاد']];
    foreach ($q->fetchAll() as $t) {
        $rows[] = [
            $t['id'], $t['subject'], trim($t['first_name'] . ' ' . $t['last_name']), $t['username'],
            $t['department_name'] ?: '-', ticket_priority_label($t['priority'] ?? 'medium'),
            ticket_status_label($t['status'] ?? 'open'), $t['messages_count'],
            mb_substr((string) ($t['last_message'] ?? ''), 0, 120), fa_datetime($t['created_at'] ?? null),
        ];
    }
    excel_output('tickets', $rows, 'تیکت‌ها');
}

$error = '';
$success = '';

// ---------- پردازش فرم‌ها ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'reply') {
        $ticket_id = intval($_POST['ticket_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');

        // چک سمت سرور: تیکت نباید بسته باشد
        $tst = $pdo->prepare("SELECT status FROM tickets WHERE id = ?");
        $tst->execute([$ticket_id]);
        $ticket_status = $tst->fetchColumn();

        if ($ticket_status === 'closed') {
            $error = 'این تیکت بسته شده است و امکان ارسال پاسخ جدید وجود ندارد.';
        } elseif (!empty($message)) {
            $stmt = $pdo->prepare("INSERT INTO ticket_messages (ticket_id, sender_id, sender_role, message) VALUES (?, ?, 'admin', ?)");
            $stmt->execute([$ticket_id, $_SESSION['user_id'], $message]);

            // Update ticket status to answered
            $stmt = $pdo->prepare("UPDATE tickets SET status = 'answered' WHERE id = ?");
            $stmt->execute([$ticket_id]);

            log_activity($_SESSION['user_id'], "ارسال پاسخ به تیکت ID: {$ticket_id}");
            sms_trigger_ticket_reply($ticket_id);
            $success = 'پاسخ شما با موفقیت ثبت شد.';
        } else {
            $error = 'متن پاسخ نمی‌تواند خالی باشد.';
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'status') {
        $ticket_id = intval($_POST['ticket_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if (in_array($status, ['open', 'answered', 'closed'], true)) {
            $stmt = $pdo->prepare("UPDATE tickets SET status = ? WHERE id = ?");
            $stmt->execute([$status, $ticket_id]);
            log_activity($_SESSION['user_id'], "تغییر وضعیت تیکت ID: {$ticket_id} به {$status}");
            $success = 'وضعیت تیکت بروزرسانی شد.';
        }
    } elseif (($_POST['action'] ?? '') === 'delete') {
        $del_id = intval($_POST['delete_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM tickets WHERE id = ?");
        $stmt->execute([$del_id]);
        log_activity($_SESSION['user_id'], "حذف تیکت ID: {$del_id}");
        $success = 'تیکت با موفقیت حذف شد.';
    }
}

// ---------- فیلترها ----------
$f_status    = $_GET['status'] ?? '';
$f_priority  = $_GET['priority'] ?? '';
$f_department = $_GET['department'] ?? '';
$f_search    = trim($_GET['search'] ?? '');
$f_unanswered = isset($_GET['unanswered']) ? '1' : '';

// ---------- جزئیات تیکت ----------
$view_id = isset($_GET['view']) ? intval($_GET['view']) : 0;
$active_ticket = null;
$ticket_messages = [];

if ($view_id > 0) {
    $stmt = $pdo->prepare("
        SELECT t.*, u.first_name, u.last_name, u.username, u.company_name
        FROM tickets t
        JOIN users u ON t.customer_id = u.id
        WHERE t.id = ?
    ");
    $stmt->execute([$view_id]);
    $active_ticket = $stmt->fetch();

    if ($active_ticket) {
        $stmt = $pdo->prepare("
            SELECT tm.*, u.first_name, u.last_name, u.role
            FROM ticket_messages tm
            JOIN users u ON tm.sender_id = u.id
            WHERE tm.ticket_id = ?
            ORDER BY tm.id ASC
        ");
        $stmt->execute([$view_id]);
        $ticket_messages = $stmt->fetchAll();
    }
}

// ---------- لیست تیکت‌ها با فیلترها ----------
$where = [];
$params = [];
if (in_array($f_status, ['open', 'answered', 'closed'], true)) { $where[] = 't.status = ?'; $params[] = $f_status; }
if (in_array($f_priority, ['low', 'medium', 'high'], true)) { $where[] = 't.priority = ?'; $params[] = $f_priority; }
if ($f_department !== '') { $where[] = 't.department_id = ?'; $params[] = (int) $f_department; }
if ($f_unanswered) { $where[] = "t.status = 'open'"; }
if ($f_search !== '') {
    $where[] = '(t.subject LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ?)';
    $like = '%' . $f_search . '%';
    array_push($params, $like, $like, $like, $like);
}
$sql_where = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

// شمارش کل + صفحه‌بندی (هم‌با فیلترها)
$cnt = $pdo->prepare("SELECT COUNT(*) FROM tickets t JOIN users u ON t.customer_id = u.id $sql_where");
$cnt->execute($params);
$pi = pagination_info((int) $cnt->fetchColumn(), 15);

$tickets = $pdo->prepare("
    SELECT t.*, u.first_name, u.last_name, u.username, u.company_name
    FROM tickets t
    JOIN users u ON t.customer_id = u.id
    $sql_where
    ORDER BY t.id DESC
    LIMIT " . (int) $pi['per_page'] . " OFFSET " . (int) $pi['offset'] . "
");
$tickets->execute($params);
$tickets = $tickets->fetchAll();

// ---------- داده‌های کمکی ----------
$departments = ticket_departments();

render_admin_header('تیکت‌های پشتیبانی مشتریان', 'p-8 max-w-7xl w-full mx-auto space-y-6');
?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if (!$active_ticket): ?>
                <?php render_excel_toolbar([
                    'page' => 'tickets.php',
                    'withSample' => false,
                    'withImport' => false,
                    'importHint' => '',
                    'importExtra' => '',
                ]); ?>
            <?php endif; ?>

            <?php if ($active_ticket): ?>
                <!-- ===== گفتگوی تیکت (بازطراحی چتی FULLMASTER) ===== -->
                <div class="card p-4 md:p-6 space-y-5">
                    <div class="flex flex-wrap items-start justify-between gap-3 pb-4 border-b border-slate-200">
                        <div class="min-w-0">
                            <div class="flex items-center gap-3 flex-wrap">
                                <a href="tickets.php" class="btn btn-ghost btn-sm !px-2" aria-label="بازگشت به لیست تیکت‌ها"><?= icon('back') ?></a>
                                <h3 class="text-lg font-bold text-slate-900 h-3"><?php echo htmlspecialchars($active_ticket['subject']); ?></h3>
                                <?php
                                    $st = $active_ticket['status'];
                                    if ($st === 'open') echo '<span class="badge badge-info">باز</span>';
                                    elseif ($st === 'answered') echo '<span class="badge badge-success">پاسخ داده شده</span>';
                                    else echo '<span class="badge badge-muted">بسته شده</span>';
                                ?>
                                <span class="badge badge-muted"><?= htmlspecialchars(ticket_department_name((int) ($active_ticket['department_id'] ?? 0))) ?></span>
                            </div>
                            <p class="body-sm text-slate-500 mt-1.5">مشتری: <strong class="text-slate-800"><?php echo htmlspecialchars(trim($active_ticket['first_name'] . ' ' . $active_ticket['last_name']) !== '' ? $active_ticket['first_name'] . ' ' . $active_ticket['last_name'] : $active_ticket['username']); ?></strong> <?php echo $active_ticket['company_name'] ? ' (' . htmlspecialchars($active_ticket['company_name']) . ')' : ''; ?> — ایجاد: <?= htmlspecialchars(fa_datetime($active_ticket['created_at'])) ?></p>
                        </div>
                        <form method="POST" class="flex items-center gap-2" data-confirm-msg="وضعیت این تیکت تغییر کند؟"><?php echo csrf_input(); ?>
                            <input type="hidden" name="action" value="status">
                            <input type="hidden" name="ticket_id" value="<?php echo $active_ticket['id']; ?>">
                            <label class="sr-only" for="tk_status">وضعیت تیکت</label>
                            <select name="status" id="tk_status" class="input !w-auto !h-9 cursor-pointer">
                                <option value="open" <?php echo $st === 'open' ? 'selected' : ''; ?>>باز</option>
                                <option value="answered" <?php echo $st === 'answered' ? 'selected' : ''; ?>>پاسخ داده شده</option>
                                <option value="closed" <?php echo $st === 'closed' ? 'selected' : ''; ?>>بسته شده</option>
                            </select>
                            <button type="submit" class="btn btn-sm btn-secondary" aria-label="ثبت وضعیت"><?= icon('check') ?></button>
                        </form>
                    </div>

                    <!-- Messages Thread -->
                    <div class="chat-thread" id="chat-thread">
                        <?php foreach ($ticket_messages as $msg):
                            $isAdmin = $msg['sender_role'] === 'admin';
                            $senderLabel = $isAdmin ? 'مدیر: ' . trim((string)($msg['first_name'] . ' ' . $msg['last_name'])) : 'مشتری: ' . trim((string)($msg['first_name'] . ' ' . $msg['last_name']));
                        ?>
                            <div class="chat-row <?php echo $isAdmin ? 'agent' : 'mine'; ?>">
                                <div class="chat-avatar <?php echo $isAdmin ? 'agent' : 'customer'; ?>"><?= icon($isAdmin ? 'users' : 'user', 'w-5 h-5') ?></div>
                                <div>
                                    <div class="chat-meta">
                                        <span class="font-medium"><?= e($senderLabel) ?></span>
                                        <span><?= e(fa_datetime($msg['created_at'])) ?></span>
                                    </div>
                                    <div class="chat-bubble"><p class="whitespace-pre-line"><?= e($msg['message']) ?></p></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Reply Form -->
                    <?php if ($active_ticket['status'] !== 'closed'): ?>
                        <form method="POST" class="chat-reply-box pt-3 border-t border-slate-200" novalidate><?php echo csrf_input(); ?>
                            <input type="hidden" name="action" value="reply">
                            <input type="hidden" name="ticket_id" value="<?php echo $active_ticket['id']; ?>">
                            <div class="flex items-end gap-2">
                                <div class="flex-1 min-w-0">
                                    <label class="sr-only" for="admin_reply">پاسخ به مشتری</label>
                                    <textarea name="message" id="admin_reply" rows="2" required placeholder="پاسخ خود را بنویسید..." class="input"></textarea>
                                    <p class="field-error" style="display:none"></p>
                                </div>
                                <button type="submit" class="btn btn-primary shrink-0 !h-11"><?= icon('send') ?><span class="hidden sm:inline">ارسال پاسخ</span></button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-muted">این تیکت بسته شده است و امکان ارسال پاسخ جدید وجود ندارد.</div>
                    <?php endif; ?>
                </div>

                <script>document.addEventListener('DOMContentLoaded',function(){var t=document.getElementById('chat-thread');if(t)t.scrollTop=t.scrollHeight;});</script>

            <?php else: ?>
                <!-- ===== فیلترها ===== -->
                <div class="card p-5">
                    <form method="get" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-3 items-end">
                        <div class="lg:col-span-2">
                            <label class="label" for="f_search">جستجو</label>
                            <input type="text" name="search" id="f_search" value="<?= htmlspecialchars($f_search) ?>" placeholder="موضوع یا نام مشتری..." class="input">
                        </div>
                        <div>
                            <label class="label" for="f_status">وضعیت</label>
                            <select name="status" id="f_status" class="input cursor-pointer">
                                <option value="">همه وضعیت‌ها</option>
                                <option value="open" <?= $f_status === 'open' ? 'selected' : '' ?>>باز</option>
                                <option value="answered" <?= $f_status === 'answered' ? 'selected' : '' ?>>پاسخ داده شده</option>
                                <option value="closed" <?= $f_status === 'closed' ? 'selected' : '' ?>>بسته شده</option>
                            </select>
                        </div>
                        <div>
                            <label class="label" for="f_priority">اولویت</label>
                            <select name="priority" id="f_priority" class="input cursor-pointer">
                                <option value="">همه اولویت‌ها</option>
                                <option value="low" <?= $f_priority === 'low' ? 'selected' : '' ?>>کم</option>
                                <option value="medium" <?= $f_priority === 'medium' ? 'selected' : '' ?>>متوسط</option>
                                <option value="high" <?= $f_priority === 'high' ? 'selected' : '' ?>>زیاد</option>
                            </select>
                        </div>
                        <div>
                            <label class="label" for="f_department">دپارتمان</label>
                            <select name="department" id="f_department" class="input cursor-pointer">
                                <option value="">همه دپارتمان‌ها</option>
                                <?php foreach ($departments as $dep): ?>
                                    <option value="<?= (int) $dep['id'] ?>" <?= $f_department == $dep['id'] ? 'selected' : '' ?>><?= htmlspecialchars($dep['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex items-end gap-2 flex-wrap">
                            <button class="btn btn-primary"><?= icon('search') ?><span>فیلتر</span></button>
                            <a href="tickets.php" class="btn btn-ghost">پاک‌کردن</a>
                        </div>
                        <div class="lg:col-span-6 flex items-center">
                            <label class="inline-flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                                <input type="checkbox" name="unanswered" value="1" <?= $f_unanswered ? 'checked' : '' ?> class="w-4 h-4 text-indigo-600 rounded border-slate-300"> فقط تیکت‌های بی‌پاسخ
                            </label>
                        </div>
                    </form>
                </div>

                <!-- ===== لیست تیکت‌ها ===== -->
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-slate-800 h-3">لیست تیکت‌های پشتیبانی (<?php echo count($tickets); ?>)</h3>
                </div>

                <div class="card overflow-hidden">
                    <div class="table-scroll">
                        <table class="table table-card-mobile">
                            <thead>
                                <tr>
                                    <th>موضوع تیکت</th>
                                    <th>مشتری</th>
                                    <th>دپارتمان</th>
                                    <th>اولویت</th>
                                    <th>وضعیت</th>
                                    <th>تاریخ ایجاد</th>
                                    <th class="text-center">عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tickets)): ?>
                                    <tr><td colspan="7"><?php echo empty_state('هیچ تیکتی مطابق فیلترها یافت نشد.', 'فیلترها را تغییر دهید یا از دکمهٔ پاک‌کردن استفاده کنید.', 'ticket'); ?></td></tr>
                                <?php else: ?>
                                    <?php foreach ($tickets as $t): ?>
                                        <tr>
                                            <td data-label="موضوع" class="font-medium text-slate-900 max-w-[220px]">
                                                <a href="tickets.php?view=<?php echo $t['id']; ?>" class="hover:text-indigo-600 block truncate" title="<?= htmlspecialchars($t['subject']) ?>"><?php echo htmlspecialchars($t['subject']); ?></a>
                                            </td>
                                            <td data-label="مشتری" class="text-slate-700">
                                                <div class="truncate max-w-[140px]"><?php echo htmlspecialchars(trim($t['first_name'] . ' ' . $t['last_name']) !== '' ? $t['first_name'] . ' ' . $t['last_name'] : $t['username']); ?></div>
                                                <div class="text-xs text-slate-400 truncate max-w-[140px]"><?php echo htmlspecialchars($t['company_name'] ?: ''); ?></div>
                                            </td>
                                            <td data-label="دپارتمان"><span class="badge badge-muted"><?= htmlspecialchars(ticket_department_name((int) ($t['department_id'] ?? 0))) ?></span></td>
                                            <td data-label="اولویت">
                                                <?php
                                                    $pr = $t['priority'];
                                                    if ($pr === 'high') echo '<span class="badge badge-danger">زیاد</span>';
                                                    elseif ($pr === 'medium') echo '<span class="badge badge-warning">متوسط</span>';
                                                    else echo '<span class="badge badge-muted">کم</span>';
                                                ?>
                                            </td>
                                            <td data-label="وضعیت">
                                                <?php
                                                    $st = $t['status'];
                                                    if ($st === 'open') echo '<span class="badge badge-info">باز</span>';
                                                    elseif ($st === 'answered') echo '<span class="badge badge-success">پاسخ داده شده</span>';
                                                    else echo '<span class="badge badge-muted">بسته شده</span>';
                                                ?>
                                            </td>
                                            <td data-label="تاریخ" class="text-xs text-slate-500 whitespace-nowrap"><?php echo htmlspecialchars(fa_datetime($t['created_at'])); ?></td>
                                            <td data-label="عملیات" class="text-center whitespace-nowrap">
                                                <div class="inline-flex items-center gap-1.5 cell-actions">
                                                    <a href="tickets.php?view=<?php echo $t['id']; ?>" class="btn btn-sm btn-ghost !text-indigo-600"><?= icon('message') ?><span>مشاهده و پاسخ</span></a>
                                                    <form method="POST" data-confirm-msg="آیا از حذف این تیکت اطمینان دارید؟"><input type="hidden" name="action" value="delete"><input type="hidden" name="delete_id" value="<?php echo (int)$t['id']; ?>"><?php echo csrf_input(); ?><button type="submit" class="btn btn-sm btn-outline-danger"><?= icon('trash') ?><span>حذف</span></button></form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <?php echo render_pagination($pi, 'tickets.php'); ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php render_admin_footer();
