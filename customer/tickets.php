<?php
// customer/tickets.php - Customer Support Tickets
require_once 'auth.php';
if (!is_module_enabled('tickets')) { header('Location: index.php'); exit; }

$user_id = $_SESSION['user_id'];
$error = '';
$success = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

// Handle Create New Ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $subject = trim($_POST['subject'] ?? '');
    $priority = in_array($_POST['priority'] ?? '', ['low', 'medium', 'high'], true) ? $_POST['priority'] : 'medium';
    $message = trim($_POST['message'] ?? '');
    $department_id = (int) ($_POST['department_id'] ?? 0);
    // اعتبارسنجی دپارتمان
    $dep_check = $pdo->prepare("SELECT id FROM ticket_departments WHERE id = ? AND is_active = 1");
    $dep_check->execute([$department_id]);
    if (!$dep_check->fetchColumn()) { $department_id = null; }

    if (empty($subject) || empty($message)) {
        $error = 'موضوع و متن پیام تیکت الزامی است.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO tickets (customer_id, subject, priority, department_id, status) VALUES (?, ?, ?, ?, 'open')");
        $stmt->execute([$user_id, $subject, $priority, $department_id]);
        $ticket_id = $pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO ticket_messages (ticket_id, sender_id, sender_role, message) VALUES (?, ?, 'customer', ?)");
        $stmt->execute([$ticket_id, $user_id, $message]);

        log_activity($user_id, "ثبت تیکت پشتیبانی جدید: {$subject}");
        $_SESSION['flash'] = 'تیکت پشتیبانی شما با موفقیت ثبت شد.';
        header('Location: tickets.php');
        exit;
    }
}

// Handle Reply to Ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reply') {
    $ticket_id = intval($_POST['ticket_id']);
    $message = trim($_POST['message'] ?? '');

    // Verify ownership + تیکت نباید بسته باشد (چک سمت سرور)
    $stmt = $pdo->prepare("SELECT * FROM tickets WHERE id = ? AND customer_id = ?");
    $stmt->execute([$ticket_id, $user_id]);
    $ticket = $stmt->fetch();

    if ($ticket && $ticket['status'] !== 'closed' && !empty($message)) {
        $stmt = $pdo->prepare("INSERT INTO ticket_messages (ticket_id, sender_id, sender_role, message) VALUES (?, ?, 'customer', ?)");
        $stmt->execute([$ticket_id, $user_id, $message]);

        // Update status back to open so admin sees it needs attention
        $stmt = $pdo->prepare("UPDATE tickets SET status = 'open' WHERE id = ?");
        $stmt->execute([$ticket_id]);

        log_activity($user_id, "ارسال پاسخ به تیکت ID: {$ticket_id}");
        $_SESSION['flash'] = 'پاسخ شما با موفقیت ارسال شد.';
        header('Location: tickets.php?view=' . $ticket_id);
        exit;
    } elseif ($ticket && $ticket['status'] === 'closed') {
        $error = 'این تیکت بسته شده است و امکان ارسال پاسخ جدید وجود ندارد.';
    } else {
        $error = 'امکان ارسال پاسخ وجود ندارد.';
    }
}

$view_id = isset($_GET['view']) ? intval($_GET['view']) : 0;
$active_ticket = null;
$ticket_messages = [];

if ($view_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM tickets WHERE id = ? AND customer_id = ?");
    $stmt->execute([$view_id, $user_id]);
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

$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// Fetch customer tickets
$stmt = $pdo->prepare("SELECT * FROM tickets WHERE customer_id = ? ORDER BY id DESC");
$stmt->execute([$user_id]);
$tickets = $stmt->fetchAll();
?>
<?php render_customer_header(
    'تیکت‌های پشتیبانی',
    'p-8 max-w-7xl w-full mx-auto space-y-6',
    '',
); ?>


            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($active_ticket): ?>
                <!-- Ticket Thread (بازطراحی چتی FULLMASTER) -->
                <div class="card p-4 md:p-6 space-y-5">
                    <div class="flex flex-wrap items-center justify-between gap-3 pb-4 border-b border-slate-200">
                        <div class="flex items-center gap-3 flex-wrap">
                            <a href="tickets.php" class="btn btn-ghost btn-sm !px-2" aria-label="بازگشت به لیست تیکت‌ها"><?= icon('back') ?></a>
                            <h3 class="text-lg font-bold text-slate-900"><bdi dir="auto"><?php echo htmlspecialchars($active_ticket['subject']); ?></bdi></h3>
                            <?php
                                $st = $active_ticket['status'];
                                if ($st === 'open') echo '<span class="badge badge-info">باز</span>';
                                elseif ($st === 'answered') echo '<span class="badge badge-success">پاسخ داده شده</span>';
                                else echo '<span class="badge badge-muted">بسته شده</span>';
                            ?>
                        </div>
                        <span class="body-sm text-slate-500"><bdi dir="auto"><?= htmlspecialchars(ticket_department_name((int) ($active_ticket['department_id'] ?? 0))) ?></bdi> · <span class="value-ltr whitespace-nowrap" dir="ltr"><?= htmlspecialchars(fa_datetime($active_ticket['created_at'])) ?></span></span>
                    </div>

                    <div class="chat-thread" id="chat-thread">
                        <?php foreach ($ticket_messages as $msg):
                            $isCustomer = $msg['sender_role'] === 'customer';
                            $senderName = $isCustomer ? 'شما' : 'پشتیبان';
                        ?>
                            <div class="chat-row <?php echo $isCustomer ? 'mine' : 'agent'; ?>">
                                <div class="chat-avatar <?php echo $isCustomer ? 'customer' : 'agent'; ?>"><?= icon($isCustomer ? 'user' : 'users', 'w-5 h-5') ?></div>
                                <div>
                                    <div class="chat-meta">
                                        <span class="font-medium"><?= e($senderName) ?></span>
                                        <span class="value-ltr whitespace-nowrap" dir="ltr"><?= e(fa_datetime($msg['created_at'])) ?></span>
                                    </div>
                                    <div class="chat-bubble"><p class="whitespace-pre-line"><bdi dir="auto"><?= e($msg['message']) ?></bdi></p></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($active_ticket['status'] !== 'closed'): ?>
                        <form method="POST" class="chat-reply-box pt-3 border-t border-slate-200" novalidate><?php echo csrf_input(); ?>
                            <input type="hidden" name="action" value="reply">
                            <input type="hidden" name="ticket_id" value="<?php echo $active_ticket['id']; ?>">
                            <div class="flex items-end gap-2">
                                <div class="flex-1 min-w-0">
                                    <label class="sr-only" for="reply_msg">پیام پاسخ</label>
                                    <textarea name="message" id="reply_msg" rows="2" required dir="auto" placeholder="پیام خود را بنویسید..." class="input"></textarea>
                                    <p class="field-error" style="display:none"></p>
                                </div>
                                <button type="submit" class="btn btn-primary shrink-0 !h-11"><?= icon('send') ?><span class="hidden sm:inline">ارسال</span></button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-muted text-center justify-center">این تیکت بسته شده است.</div>
                    <?php endif; ?>
                </div>

                <script>document.addEventListener('DOMContentLoaded',function(){var t=document.getElementById('chat-thread');if(t)t.scrollTop=t.scrollHeight;});</script>

            <?php elseif ($action === 'new'): ?>
                <!-- New Ticket Form -->
                <div class="card p-6 md:p-8 max-w-2xl mx-auto w-full">
                    <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-200">
                        <h3 class="text-lg font-bold text-slate-800">ارسال تیکت پشتیبانی جدید</h3>
                        <a href="tickets.php" class="btn btn-ghost btn-sm"><?= icon('back') ?><span>بازگشت</span></a>
                    </div>

                    <form method="POST" class="space-y-6" novalidate><?php echo csrf_input(); ?>
                        <input type="hidden" name="action" value="create">
                        <div class="form-error-summary" style="display:none" role="alert"></div>
                        <div>
                            <label class="label" for="tk_subject">موضوع تیکت<span class="required-star" aria-hidden="true">*</span></label>
                            <input type="text" name="subject" id="tk_subject" required dir="auto" class="input" placeholder="مثلا: مشکل در اتصال به پنل">
                        </div>
                        <div>
                            <label class="label" for="tk_priority">اولویت</label>
                            <select name="priority" id="tk_priority" class="input cursor-pointer">
                                <option value="low">کم</option>
                                <option value="medium" selected>متوسط</option>
                                <option value="high">زیاد (فوری)</option>
                            </select>
                        </div>
                        <div>
                            <label class="label" for="tk_dept">دپارتمان</label>
                            <select name="department_id" id="tk_dept" class="input cursor-pointer">
                                <option value="0">عمومی</option>
                                <?php foreach (ticket_departments() as $dep): ?>
                                    <option value="<?= (int) $dep['id'] ?>"><?= htmlspecialchars($dep['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="label" for="tk_msg">متن پیام<span class="required-star" aria-hidden="true">*</span></label>
                            <textarea name="message" id="tk_msg" rows="5" required dir="auto" class="input" placeholder="شرح درخواست یا مشکل خود را به تفصیل بنویسید..."></textarea>
                        </div>
                        <div class="desktop-form-actions flex flex-col-reverse sm:flex-row sm:justify-end gap-3 pt-4 border-t border-slate-200">
                            <a href="tickets.php" class="btn btn-secondary">انصراف</a>
                            <button type="submit" class="btn btn-primary"><?= icon('send') ?><span>ارسال تیکت</span></button>
                        </div>
                        <div class="mobile-action-bar">
                            <a href="tickets.php" class="btn btn-secondary">انصراف</a>
                            <button type="submit" class="btn btn-primary"><?= icon('send') ?><span>ارسال تیکت</span></button>
                        </div>
                    </form>
                </div>

            <?php else: ?>
                <!-- Tickets List -->
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-lg font-bold text-slate-800">لیست تیکت‌های شما (<?php echo count($tickets); ?>)</h3>
                    <a href="tickets.php?action=new" class="btn btn-primary"><?= icon('plus') ?><span>تیکت جدید</span></a>
                </div>

                <div class="card overflow-hidden">
                    <div class="table-scroll">
                        <table class="table table-card-mobile">
                            <thead>
                                <tr>
                                    <th class="min-w-[15rem]">موضوع تیکت</th>
                                    <th>دپارتمان</th>
                                    <th>اولویت</th>
                                    <th>وضعیت</th>
                                    <th class="min-w-[9rem]">تاریخ ایجاد</th>
                                    <th class="text-center">مشاهده</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tickets)): ?>
                                    <tr><td colspan="6"><?php echo empty_state('هیچ تیکتی ثبت نکرده‌اید.', 'از دکمهٔ «تیکت جدید» برای ارسال درخواست پشتیبانی استفاده کنید.', 'ticket'); ?></td></tr>
                                <?php else: ?>
                                    <?php foreach ($tickets as $t): ?>
                                        <tr>
                                            <td data-label="موضوع" class="font-medium text-slate-900">
                                                <a href="tickets.php?view=<?php echo $t['id']; ?>" class="hover:text-indigo-600 transition"><bdi dir="auto"><?php echo htmlspecialchars($t['subject']); ?></bdi></a>
                                            </td>
                                            <td data-label="دپارتمان"><span class="badge badge-muted"><bdi dir="auto"><?= htmlspecialchars(ticket_department_name((int) ($t['department_id'] ?? 0))) ?></bdi></span></td>
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
                                            <td data-label="تاریخ ایجاد" class="text-xs text-slate-500 value-ltr whitespace-nowrap" dir="ltr"><?php echo htmlspecialchars(fa_datetime($t['created_at'])); ?></td>
                                            <td data-label="عملیات" class="text-center">
                                                <a href="tickets.php?view=<?php echo $t['id']; ?>" class="btn btn-sm btn-ghost !text-indigo-600"><?= icon('message') ?><span>مکاتبات</span></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        <?php render_customer_footer(); ?>