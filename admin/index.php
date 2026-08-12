<?php
// admin/index.php - Admin Dashboard
require_once 'auth.php';
if (!admin_can('dashboard')) { header('Location: index.php'); exit; }

// Fetch stats — با کش فایل (آمار داشبورد تا ۶۰ ثانیه در کش می‌ماند)
$admin_dash = portal_cache_get('admin_dash_stats');
if (!is_array($admin_dash)) {
    $admin_dash = [
        'customers' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn(),
        'projects'  => (int) $pdo->query("SELECT COUNT(*) FROM projects WHERE status != 'completed'")->fetchColumn(),
        'products'  => (int) $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn(),
        'tickets'   => (int) $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'open'")->fetchColumn(),
        'recent_customers' => $pdo->query("SELECT * FROM users WHERE role = 'customer' ORDER BY id DESC LIMIT 5")->fetchAll(),
        'recent_logs' => $pdo->query("SELECT al.*, u.first_name, u.last_name, u.username FROM activity_logs al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.id DESC LIMIT 5")->fetchAll(),
    ];
    portal_cache_set('admin_dash_stats', $admin_dash, 60);
}
$customers_count = $admin_dash['customers'];
$projects_count  = $admin_dash['projects'];
$products_count  = $admin_dash['products'];
$tickets_count   = $admin_dash['tickets'];
$recent_customers = $admin_dash['recent_customers'];
$recent_logs      = $admin_dash['recent_logs'];
?>
<?php render_admin_header(
    'داشبورد مدیریت پیشرفته',
    'p-8 max-w-7xl w-full mx-auto space-y-8',
    '',
    '<form method="post" action="../logout.php" class="md:hidden"><input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '"><button type="submit" class="text-xs text-red-600 bg-red-50 px-3 py-1.5 rounded-lg">خروج</button></form>'
); ?>

            
            <!-- Stats Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 md:gap-6">
                <div class="card p-5 flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500 font-medium">کل مشتریان</p>
                        <p class="text-3xl font-bold text-slate-900 mt-1"><?php echo (int) $customers_count; ?></p>
                    </div>
                    <div class="w-14 h-14 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center"><?= icon('users', 'w-6 h-6') ?></div>
                </div>

                <?php if (is_module_enabled('projects')): ?>
                <div class="card p-5 flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500 font-medium">پروژه‌های فعال</p>
                        <p class="text-3xl font-bold text-slate-900 mt-1"><?php echo (int) $projects_count; ?></p>
                    </div>
                    <div class="w-14 h-14 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center"><?= icon('folder', 'w-6 h-6') ?></div>
                </div>
                <?php endif; ?>

                <?php if (is_module_enabled('products')): ?>
                <div class="card p-5 flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500 font-medium">محصولات</p>
                        <p class="text-3xl font-bold text-slate-900 mt-1"><?php echo (int) $products_count; ?></p>
                    </div>
                    <div class="w-14 h-14 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center"><?= icon('box', 'w-6 h-6') ?></div>
                </div>
                <?php endif; ?>

                <?php if (is_module_enabled('tickets')): ?>
                <div class="card p-5 flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500 font-medium">تیکت‌های باز</p>
                        <p class="text-3xl font-bold text-slate-900 mt-1"><?php echo (int) $tickets_count; ?></p>
                    </div>
                    <div class="w-14 h-14 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center"><?= icon('ticket', 'w-6 h-6') ?></div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Quick Actions & Recent Customers -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Recent Customers -->
                <div class="lg:col-span-2 space-y-6">
                    <div class="card overflow-hidden">
                        <div class="p-5 border-b border-slate-200 flex items-center justify-between">
                            <h3 class="font-semibold text-slate-800 text-sm leading-tight">مشتریان اخیر</h3>
                            <a href="customers.php" class="btn btn-ghost btn-sm"><?= icon('users') ?><span>مشاهده همه</span></a>
                        </div>
                        <div class="table-scroll">
                            <table class="table table-card-mobile">
                                <thead>
                                    <tr>
                                        <th>نام و نام خانوادگی</th>
                                        <th>شماره موبایل</th>
                                        <th>نام شرکت</th>
                                        <th>تاریخ ثبت‌نام</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recent_customers)): ?>
                                        <tr><td colspan="4" class="p-6 text-center text-slate-400">هیچ مشتری‌ای ثبت نشده است.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($recent_customers as $c): ?>
                                            <tr>
                                                <td data-label="نام" class="font-medium text-slate-900"><?php echo htmlspecialchars(trim($c['first_name'] . ' ' . $c['last_name']) !== '' ? $c['first_name'] . ' ' . $c['last_name'] : $c['username']); ?></td>
                                                <td data-label="موبایل" class="text-slate-600"><?php echo htmlspecialchars($c['mobile'] ?: '-'); ?></td>
                                                <td data-label="شرکت" class="text-slate-600"><?php echo htmlspecialchars($c['company_name'] ?: '-'); ?></td>
                                                <td data-label="تاریخ" class="text-slate-500 text-xs"><?php echo htmlspecialchars(fa_date($c['created_at'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Recent Activity Stream -->
                    <div class="card p-5">
                        <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-100">
                            <h3 class="font-semibold text-slate-800 text-sm leading-tight">آخرین فعالیت‌های سیستم</h3>
                            <a href="logs.php" class="btn btn-ghost btn-sm"><?= icon('file') ?><span>مشاهده کامل</span></a>
                        </div>
                        <div class="space-y-3">
                            <?php if (empty($recent_logs)): ?>
                                <p class="text-slate-400 text-sm text-center py-4">فعالیتی ثبت نشده است.</p>
                            <?php else: ?>
                                <?php foreach ($recent_logs as $log): ?>
                                    <div class="flex items-center justify-between gap-3 text-xs py-2 border-b border-slate-100 last:border-none">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span class="w-2 h-2 rounded-full bg-indigo-500 flex-shrink-0"></span>
                                            <strong class="text-slate-800 flex-shrink-0"><?php echo htmlspecialchars($log['first_name'] ? $log['first_name'] . ' ' . $log['last_name'] : 'سیستم'); ?>:</strong>
                                            <span class="text-slate-600 truncate"><?php echo htmlspecialchars($log['action']); ?></span>
                                        </div>
                                        <span class="text-slate-400 flex-shrink-0"><?php echo htmlspecialchars(fa_datetime($log['created_at'])); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Links Box -->
                <div class="card p-5 space-y-3">
                    <h3 class="font-semibold text-slate-800 text-sm leading-tight mb-1">دسترسی سریع</h3>
                    <?php if ($_SESSION['role'] === 'super_admin' || admin_can('customers')): ?>
                    <a href="customers.php?action=add" class="flex items-center gap-3 p-3.5 rounded-xl border border-slate-200 hover:border-indigo-400 hover:bg-indigo-50/50 transition group">
                        <span class="w-10 h-10 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center flex-shrink-0"><?= icon('users', 'w-5 h-5') ?></span>
                        <span><span class="block font-medium text-slate-800 group-hover:text-indigo-700 text-sm">افزودن مشتری جدید</span><span class="block text-xs text-slate-500 mt-0.5">تعریف دستی مشتری در سیستم</span></span>
                    </a>
                    <?php endif; ?>

                    <?php if (($_SESSION['role'] === 'super_admin' || admin_can('projects')) && is_module_enabled('projects')): ?>
                    <a href="projects.php?action=add" class="flex items-center gap-3 p-3.5 rounded-xl border border-slate-200 hover:border-indigo-400 hover:bg-indigo-50/50 transition group">
                        <span class="w-10 h-10 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center flex-shrink-0"><?= icon('folder', 'w-5 h-5') ?></span>
                        <span><span class="block font-medium text-slate-800 group-hover:text-indigo-700 text-sm">تعریف پروژه جدید</span><span class="block text-xs text-slate-500 mt-0.5">اختصاص پروژه به یک مشتری</span></span>
                    </a>
                    <?php endif; ?>

                    <?php if (($_SESSION['role'] === 'super_admin' || admin_can('products')) && is_module_enabled('products')): ?>
                    <a href="products.php?action=add" class="flex items-center gap-3 p-3.5 rounded-xl border border-slate-200 hover:border-indigo-400 hover:bg-indigo-50/50 transition group">
                        <span class="w-10 h-10 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center flex-shrink-0"><?= icon('box', 'w-5 h-5') ?></span>
                        <span><span class="block font-medium text-slate-800 group-hover:text-indigo-700 text-sm">تعریف محصول جدید</span><span class="block text-xs text-slate-500 mt-0.5">اختصاص محصول به مشتری</span></span>
                    </a>
                    <?php endif; ?>

                    <?php if (($_SESSION['role'] === 'super_admin' || admin_can('invoices')) && is_module_enabled('invoices')): ?>
                    <a href="invoices.php?action=add" class="flex items-center gap-3 p-3.5 rounded-xl border border-slate-200 hover:border-indigo-400 hover:bg-indigo-50/50 transition group">
                        <span class="w-10 h-10 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center flex-shrink-0"><?= icon('card', 'w-5 h-5') ?></span>
                        <span><span class="block font-medium text-slate-800 group-hover:text-indigo-700 text-sm">صدور فاکتور جدید</span><span class="block text-xs text-slate-500 mt-0.5">ایجاد صورتحساب مالی برای مشتری</span></span>
                    </a>
                    <?php endif; ?>

                    <?php if ($_SESSION['role'] === 'super_admin' || admin_can('custom_fields')): ?>
                    <a href="custom_fields.php?action=add" class="flex items-center gap-3 p-3.5 rounded-xl border border-slate-200 hover:border-indigo-400 hover:bg-indigo-50/50 transition group">
                        <span class="w-10 h-10 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center flex-shrink-0"><?= icon('wrench', 'w-5 h-5') ?></span>
                        <span><span class="block font-medium text-slate-800 group-hover:text-indigo-700 text-sm">ساخت فیلد سفارشی پویا</span><span class="block text-xs text-slate-500 mt-0.5">افزودن فیلد دلخواه به فرم‌ها</span></span>
                    </a>
                    <?php endif; ?>

                    <?php if ($_SESSION['role'] === 'super_admin' || admin_can('settings')): ?>
                    <a href="settings.php" class="flex items-center gap-3 p-3.5 rounded-xl border border-slate-200 hover:border-indigo-400 hover:bg-indigo-50/50 transition group">
                        <span class="w-10 h-10 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center flex-shrink-0"><?= icon('settings', 'w-5 h-5') ?></span>
                        <span><span class="block font-medium text-slate-800 group-hover:text-indigo-700 text-sm">تنظیمات فیلدهای اجباری</span><span class="block text-xs text-slate-500 mt-0.5">مدیریت فیلدهای اجباری/اختیاری پروفایل</span></span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

        <?php render_admin_footer(); ?>