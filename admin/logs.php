<?php
// admin/logs.php - Activity Logs
require_once 'auth.php';
if (!admin_can('logs')) { header('Location: index.php'); exit; }

// Fetch activity logs with user info (با صفحه‌بندی)
$logs_total = (int) $pdo->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();
$pi = pagination_info($logs_total, 50);
$logs = $pdo->query("
    SELECT al.*, u.first_name, u.last_name, u.username, u.role 
    FROM activity_logs al 
    LEFT JOIN users u ON al.user_id = u.id 
    ORDER BY al.id DESC 
    LIMIT " . (int) $pi['per_page'] . " OFFSET " . (int) $pi['offset'] . "
")->fetchAll();
?>
<?php render_admin_header('گزارش فعالیت‌های سیستم', 'portal-page-main portal-admin-page portal-logs-page p-8 max-w-7xl w-full mx-auto space-y-6',
    '',
    ''
); ?>


            <div class="portal-list-toolbar flex items-center justify-between gap-4">
                <h3 class="text-lg font-bold text-slate-800">۱۰۰ فعالیت اخیر سیستم (Audit Trail)</h3>
            </div>

            <div class="card portal-list-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="table table-card-mobile">
                        <thead class="bg-slate-50 text-slate-500 text-xs font-semibold">
                            <tr>
                                <th class="p-4">کاربر</th>
                                <th class="p-4">نقش</th>
                                <th class="p-4">شرح فعالیت</th>
                                <th class="p-4">آدرس IP</th>
                                <th class="p-4">زمان ثبت</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="5" class="p-8 text-center text-slate-400"><?php echo empty_state('هیچ فعالیتی ثبت نشده است.', '', 'info'); ?></td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr class="hover:bg-slate-50 transition">
                                        <td class="p-4 font-medium text-slate-900">
                                            <?php echo htmlspecialchars($log['first_name'] ? $log['first_name'] . ' ' . $log['last_name'] : ($log['username'] ?? 'سیستم / مهمان')); ?>
                                        </td>
                                        <td class="p-4">
                                            <?php 
                                                $role = $log['role'] ?? 'system';
                                                if ($role === 'super_admin') echo '<span class="badge badge-brand">مدیر ارشد</span>';
                                                elseif ($role === 'admin') echo '<span class="badge badge-brand">ادمین</span>';
                                                elseif ($role === 'customer') echo '<span class="badge badge-info">مشتری</span>';
                                                else echo '<span class="badge badge-muted">سیستم</span>';
                                            ?>
                                        </td>
                                        <td class="p-4 text-slate-700"><?php echo htmlspecialchars($log['action']); ?></td>
                                        <td class="p-4 text-xs font-mono text-slate-500"><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                        <td class="p-4 text-xs text-slate-500"><?php echo htmlspecialchars($log['created_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <?php echo render_pagination($pi, 'logs.php'); ?>
                </div>
            </div>

        <?php render_admin_footer(); ?>