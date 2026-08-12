<?php
// customer/index.php - Customer Dashboard
require_once 'auth.php';

$user_id = $_SESSION['user_id'];

// Fetch user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$customer = $stmt->fetch();
if (!$customer) { session_unset(); session_destroy(); header('Location: ../index.php'); exit; }

$full_name = trim($customer['first_name'] . ' ' . $customer['last_name']) !== '' ? $customer['first_name'] . ' ' . $customer['last_name'] : $customer['username'];

// Check if mandatory fields are missing and not skipped
$fields_check = ['first_name', 'last_name', 'mobile', 'company_name', 'job_title', 'birth_date', 'gender'];
$missing_count = 0;
if ($customer['profile_skipped'] == 0) {
    foreach ($fields_check as $fc) {
        if (get_setting('req_' . $fc, '0') === '1' && empty($customer[$fc])) {
            $missing_count++;
        }
    }
}

// --- داده‌ها فقط برای ماژول‌های فعال ---
$projects = [];
$products = [];
$projects_count = 0;
$products_count = 0;
$invoices_count = 0;
$tickets_count = 0;
$pending_surveys = 0;

if (is_module_enabled('projects')) {
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE customer_id = ? ORDER BY id DESC LIMIT 3");
    $stmt->execute([$user_id]);
    $projects = $stmt->fetchAll();
    $total_projects = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE customer_id = ?");
    $total_projects->execute([$user_id]);
    $projects_count = $total_projects->fetchColumn();
}

if (is_module_enabled('products')) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE customer_id = ? ORDER BY id DESC LIMIT 3");
    $stmt->execute([$user_id]);
    $products = $stmt->fetchAll();
    $total_products = $pdo->prepare("SELECT COUNT(*) FROM products WHERE customer_id = ?");
    $total_products->execute([$user_id]);
    $products_count = $total_products->fetchColumn();
}

if (is_module_enabled('invoices')) {
    $total_invoices = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE customer_id = ?");
    $total_invoices->execute([$user_id]);
    $invoices_count = $total_invoices->fetchColumn();
}

if (is_module_enabled('tickets')) {
    $total_tickets = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE customer_id = ? AND status != 'closed'");
    $total_tickets->execute([$user_id]);
    $tickets_count = $total_tickets->fetchColumn();
}

$next_survey_available = null; // زمان فعال‌سازی نزدیک‌ترین نظرسنجی آینده

if (is_module_enabled('surveys')) {
    ensure_survey_assignments($user_id);
    // نظرسنجی‌های در دسترس برای تکمیل (اکنون فعال و بدون پاسخ)
    $q = $pdo->prepare("SELECT COUNT(*) FROM survey_assignments sa JOIN surveys s ON s.id=sa.survey_id WHERE sa.customer_id=? AND s.is_active=1 AND sa.available_at<=NOW() AND NOT EXISTS (SELECT 1 FROM survey_responses r WHERE r.survey_id=sa.survey_id AND r.customer_id=sa.customer_id AND r.entity_type=sa.entity_type AND r.entity_id=sa.entity_id AND r.created_at>=sa.available_at)");
    $q->execute([$user_id]); $pending_surveys=(int)$q->fetchColumn();
    // نزدیک‌ترین نظرسنجی آینده (دوره‌ای که هنوز فعال نشده و پاسخ داده نشده)
    $q2 = $pdo->prepare("SELECT MIN(sa.available_at) FROM survey_assignments sa JOIN surveys s ON s.id=sa.survey_id WHERE sa.customer_id=? AND s.is_active=1 AND sa.available_at>NOW() AND NOT EXISTS (SELECT 1 FROM survey_responses r WHERE r.survey_id=sa.survey_id AND r.customer_id=sa.customer_id AND r.entity_type=sa.entity_type AND r.entity_id=sa.entity_id AND r.created_at>=sa.available_at)");
    $q2->execute([$user_id]); $next_survey_available = $q2->fetchColumn();
    $next_survey_days = $next_survey_available ? (int) ceil((strtotime($next_survey_available) - time()) / 86400) : 0;
}

// اعلانات
$notif_unread = unread_notifications_count($user_id);
$recent_notifications = get_user_notifications($user_id, 5);
$gamification_summary = gamification_enabled() ? gamification_customer_summary((int) $user_id) : null;

// --- کنترل ویجت‌های داشبورد توسط ادمین (از تنظیمات) ---
// توجه: یک ویجت فقط وقتی نمایش داده می‌شود که هم ماژولش فعال باشد و هم در تنظیمات داشبورد روشن باشد.
function dash_widget_enabled(string $key): bool
{
    return get_setting($key, '1') === '1';
}

// --- ساخت ویجت‌های آمار به‌صورت پویا (ماژول فعال + ویجت روشن) ---
$stat_widgets = [];
if (is_module_enabled('projects') && dash_widget_enabled('dash_projects')) {
    $stat_widgets[] = ['link' => 'projects.php', 'label' => 'پروژه‌های من', 'count' => $projects_count, 'color' => 'indigo', ];
}
if (is_module_enabled('products') && dash_widget_enabled('dash_products')) {
    $stat_widgets[] = ['link' => 'products.php', 'label' => 'محصولات', 'count' => $products_count, 'color' => 'amber', ];
}
if (is_module_enabled('invoices') && dash_widget_enabled('dash_invoices')) {
    $stat_widgets[] = ['link' => 'invoices.php', 'label' => 'فاکتورهای مالی', 'count' => $invoices_count, 'color' => 'emerald', ];
}
if (is_module_enabled('tickets') && dash_widget_enabled('dash_tickets')) {
    $stat_widgets[] = ['link' => 'tickets.php', 'label' => 'تیکت‌های پشتیبانی', 'count' => $tickets_count, 'color' => 'blue', ];
}
if (is_module_enabled('surveys') && dash_widget_enabled('dash_surveys')) {
    $stat_widgets[] = ['link' => 'surveys.php', 'label' => 'نظرسنجی‌های در انتظار', 'count' => $pending_surveys, 'color' => 'violet', ];
}
if (gamification_enabled()) {
    $stat_widgets[] = ['link' => 'gamification.php', 'label' => 'موجودی امتیاز من', 'count' => $gamification_summary['wallet']['balance'], 'color' => 'indigo', ];
}
if (dash_widget_enabled('dash_notifications')) {
    $stat_widgets[] = ['link' => 'notifications.php', 'label' => 'اعلانات من', 'count' => $notif_unread, 'color' => 'rose', ];
}

// اگر ویجت کافی نبود (ماژول‌های زیادی خاموش/مخفی‌اند)، ویجت‌های جایگزین اضافه کن
if (count($stat_widgets) < 4) {
    $stat_widgets[] = ['link' => 'profile.php', 'label' => 'پروفایل من', 'count' => '', 'color' => 'slate', ];
}
if (count($stat_widgets) < 4) {
    $stat_widgets[] = ['link' => 'index.php', 'label' => 'داشبورد من', 'count' => '', 'color' => 'slate', ];
}

$widget_colors = [
    'indigo'  => ['bg' => 'bg-indigo-50 text-indigo-600',   'hover' => 'hover:border-indigo-500'],
    'amber'   => ['bg' => 'bg-amber-50 text-amber-600',     'hover' => 'hover:border-amber-500'],
    'emerald' => ['bg' => 'bg-emerald-50 text-emerald-600', 'hover' => 'hover:border-emerald-500'],
    'blue'    => ['bg' => 'bg-blue-50 text-blue-600',       'hover' => 'hover:border-blue-500'],
    'violet'  => ['bg' => 'bg-violet-50 text-violet-600',   'hover' => 'hover:border-violet-500'],
    'rose'    => ['bg' => 'bg-rose-50 text-rose-600',       'hover' => 'hover:border-rose-500'],
    'slate'   => ['bg' => 'bg-slate-100 text-slate-600',    'hover' => 'hover:border-slate-400'],
];
?>
<?php render_customer_header(
    'داشبورد اختصاصی مشتری',
    'p-8 max-w-7xl w-full mx-auto space-y-8',
    '',
    '',
    $full_name
); ?>

            <?php if (is_module_enabled('surveys') && dash_widget_enabled('dash_survey_banner')): ?>
                <?php if ($pending_surveys > 0): ?>
                    <!-- نظرسنجی در انتظار -->
                    <a href="surveys.php" class="block bg-indigo-600 text-white rounded-2xl p-5 shadow-sm hover:bg-indigo-700 transition">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="font-bold text-lg">نظرسنجی‌های شما</div>
                                <div class="text-indigo-100 text-sm mt-1">فرم‌های مربوط به پروژه‌ها و محصولاتتان</div>
                            </div>
                            <strong class="text-3xl"><?= (int) $pending_surveys ?></strong>
                        </div>
                    </a>
                <?php elseif ($next_survey_days > 0): ?>
                    <!-- نظرسنجی دوره‌ای آینده -->
                    <a href="surveys.php" class="block bg-amber-500 text-white rounded-2xl p-5 shadow-sm hover:bg-amber-600 transition">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="font-bold text-lg">نظرسنجی بعدی</div>
                                <div class="text-amber-100 text-sm mt-1">شما <?= (int) $next_survey_days ?> روز دیگر باید نظرسنجی بعدی را کامل کنید</div>
                            </div>
                            <span class="text-3xl"><?= icon('alert','w-8 h-8') ?></span>
                        </div>
                    </a>
                <?php else: ?>
                    <!-- همه نظرسنجی‌ها کامل شده -->
                    <div class="block bg-emerald-600 text-white rounded-2xl p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="font-bold text-lg">سپاس از همراهی شما</div>
                                <div class="text-emerald-100 text-sm mt-1">شما به همه نظرسنجی‌ها پاسخ داده‌اید.</div>
                            </div>
                            <span class="text-3xl"><?= icon('check','w-8 h-8') ?></span>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($missing_count > 0 && dash_widget_enabled('dash_profile_banner')): ?>
                <!-- Mandatory Profile Completion Banner with Skip Button -->
                <div class="bg-gradient-to-l from-amber-500 to-orange-500 text-white rounded-2xl p-6 shadow-lg flex flex-col md:flex-row items-center justify-between gap-6">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center flex-shrink-0"><?= icon('alert','w-6 h-6') ?></div>
                        <div>
                            <h3 class="font-bold text-lg">تکمیل اطلاعات پروفایل</h3>
                            <p class="text-amber-100 text-sm mt-0.5">برخی از فیلدهای مهم حساب کاربری شما توسط مدیر سیستم به عنوان «اجباری» تعیین شده که هنوز تکمیل نشده‌اند.</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 whitespace-nowrap">
                        <a href="profile.php" class="bg-white text-amber-900 hover:bg-amber-50 font-bold px-5 py-2.5 rounded-xl text-sm transition shadow-sm">
                            تکمیل اطلاعات
                        </a>
                        <a href="profile.php?skip=1" class="bg-black/20 hover:bg-black/30 text-white font-medium px-5 py-2.5 rounded-xl text-sm transition">
                            رد کردن
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (dash_widget_enabled('dash_welcome')): ?>
            <!-- Welcome Widget Banner -->
            <div class="bg-gradient-to-l from-indigo-600 to-violet-600 rounded-3xl p-8 text-white shadow-xl flex flex-col md:flex-row items-center justify-between gap-6">
                <div class="space-y-2">
                    <span class="bg-white/20 text-xs px-3 py-1 rounded-full font-medium">پنل هوشمند مشتریان</span>
                    <h2 class="text-2xl md:text-3xl font-bold">خوش آمدید، <?php echo htmlspecialchars($full_name); ?> عزیز!</h2>
                    <p class="text-indigo-100 text-sm max-w-xl">از طریق این پورتال می‌توانید به پروژه‌های فعال، محصولات خریداری شده، صورتحساب‌های مالی و پشتیبانی مستقیم دسترسی داشته باشید.</p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="tickets.php?action=new" class="btn btn-lg bg-white !text-indigo-700 hover:!bg-indigo-50"><?= icon('ticket') ?><span>ارسال تیکت جدید</span></a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Stats Overview Widgets (داینامیک — فقط ماژول‌های فعال) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 md:gap-6">
                <?php
                    $wicon = [
                        'projects.php' => 'folder', 'products.php' => 'box', 'invoices.php' => 'card',
                        'tickets.php' => 'ticket', 'surveys.php' => 'star', 'notifications.php' => 'bell', 'gamification.php' => 'gift',
                        'profile.php' => 'user', 'index.php' => 'dashboard',
                    ];
                    foreach ($stat_widgets as $w):
                ?>
                    <a href="<?= $w['link'] ?>" class="card card-hover p-5 flex items-center justify-between transition group">
                        <div>
                            <p class="text-sm text-slate-500 font-medium"><?= htmlspecialchars($w['label']) ?></p>
                            <p class="text-3xl font-bold text-slate-900 mt-1">
                                <?php if ($w['count'] !== ''): ?><?= (int) $w['count'] ?><?php else: ?><span class="text-xl">—</span><?php endif; ?>
                            </p>
                        </div>
                        <div class="w-14 h-14 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center group-hover:scale-110 transition"><?= icon($wicon[$w['link']] ?? 'dashboard', 'w-6 h-6') ?></div>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Recent Widgets Grid (داینامیک — فقط ماژول‌های فعال + اعلانات) -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

                <?php if (dash_widget_enabled('dash_recent_notifications')): ?>
                <!-- اعلانات اخیر -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <h3 class="font-bold text-slate-800 text-base flex items-center gap-2"><?= icon('bell', 'w-5 h-5 text-indigo-600') ?> اعلانات اخیر</h3>
                        <a href="notifications.php" class="btn btn-ghost btn-sm"><?= icon('chevron-d') ?><span>مشاهده همه</span></a>
                    </div>
                    <?php if (empty($recent_notifications)): ?>
                        <p class="text-slate-400 text-sm text-center py-6">اعلانی برای شما وجود ندارد.</p>
                    <?php else: ?>
                        <div class="space-y-2">
                            <?php foreach ($recent_notifications as $nn): ?>
                                <a href="notifications.php" class="p-3 rounded-xl border <?= $nn['is_read'] ? 'border-slate-100 bg-slate-50/50' : 'border-indigo-100 bg-indigo-50/50' ?> flex items-start gap-3 hover:shadow-sm transition">
                                    <span class="text-lg flex-shrink-0"><?= notification_type_icon($nn['ntype']) ?></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-medium text-slate-800 text-sm truncate" title="<?= htmlspecialchars($nn['title']) ?>"><?= htmlspecialchars($nn['title']) ?></span>
                                        <?php if ($nn['body']): ?>
                                            <span class="block text-xs text-slate-500 truncate mt-0.5" title="<?= htmlspecialchars($nn['body']) ?>"><?= htmlspecialchars($nn['body']) ?></span>
                                        <?php endif; ?>
                                    </span>
                                    <?php if (!$nn['is_read']): ?>
                                        <span class="w-2 h-2 rounded-full bg-red-500 flex-shrink-0 mt-2"></span>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if (is_module_enabled('projects') && dash_widget_enabled('dash_recent_projects')): ?>
                    <!-- Projects Widget -->
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-4">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                            <h3 class="font-bold text-slate-800 text-base flex items-center gap-2"><?= icon('folder', 'w-5 h-5 text-indigo-600') ?> پروژه‌های اخیر شما</h3>
                            <a href="projects.php" class="btn btn-ghost btn-sm"><?= icon('chevron-d') ?><span>مشاهده همه</span></a>
                        </div>
                        <?php if (empty($projects)): ?>
                            <p class="text-slate-400 text-sm text-center py-6">هیچ پروژه‌ای ثبت نشده است.</p>
                        <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach ($projects as $proj): ?>
                                    <div class="p-4 rounded-xl border border-slate-100 bg-slate-50/50 flex items-center justify-between">
                                        <div>
                                            <h4 class="font-bold text-slate-900 text-sm"><?php echo htmlspecialchars($proj['title']); ?></h4>
                                            <p class="text-xs text-slate-500 mt-0.5">ددلاین: <?php echo htmlspecialchars($proj['deadline'] ?: '-'); ?></p>
                                        </div>
                                        <div>
                                            <?php 
                                                $st = $proj['status'];
                                                if ($st === 'completed') echo '<span class="bg-emerald-50 text-emerald-700 text-xs px-2.5 py-1 rounded-full font-medium">تکمیل شده</span>';
                                                elseif ($st === 'in_progress') echo '<span class="bg-indigo-50 text-indigo-700 text-xs px-2.5 py-1 rounded-full font-medium">در حال انجام</span>';
                                                else echo '<span class="bg-amber-50 text-amber-700 text-xs px-2.5 py-1 rounded-full font-medium">در انتظار شروع</span>';
                                            ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (is_module_enabled('products') && dash_widget_enabled('dash_recent_products')): ?>
                    <!-- Products Widget -->
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-4">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                            <h3 class="font-bold text-slate-800 text-base flex items-center gap-2"><?= icon('box', 'w-5 h-5 text-indigo-600') ?> محصولات اخیر</h3>
                            <a href="products.php" class="btn btn-ghost btn-sm"><?= icon('chevron-d') ?><span>مشاهده همه</span></a>
                        </div>
                        <?php if (empty($products)): ?>
                            <p class="text-slate-400 text-sm text-center py-6">هیچ محصولی ثبت نشده است.</p>
                        <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach ($products as $prod): ?>
                                    <div class="p-4 rounded-xl border border-slate-100 bg-slate-50/50 flex items-center justify-between">
                                        <div>
                                            <h4 class="font-bold text-slate-900 text-sm"><?php echo htmlspecialchars($prod['title']); ?></h4>
                                        </div>
                                        <?php echo product_status_badge($prod['product_status'] ?? null); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- اگر هیچ کدام از ماژول‌های پایینی فعال نبود، ویجت جایگزین -->
                <?php if (dash_widget_enabled('dash_quick_access') && (!is_module_enabled('projects') || !dash_widget_enabled('dash_recent_projects')) && (!is_module_enabled('products') || !dash_widget_enabled('dash_recent_products'))): ?>
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-4">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                            <h3 class="font-semibold text-slate-800 text-sm leading-tight mb-1">دسترسی سریع</h3>
                        </div>
                        <div class="space-y-3">
                            <?php if (is_module_enabled('invoices')): ?>
                                <a href="invoices.php" class="p-3.5 rounded-xl border border-slate-200 bg-slate-50/50 flex items-center justify-between hover:border-indigo-400 hover:bg-indigo-50/40 transition">
                                    <span class="flex items-center gap-2 font-medium text-slate-800 text-sm"><?= icon('card', 'w-4 h-4 text-indigo-600') ?> فاکتورهای من</span>
                                    <span class="text-slate-400 text-xs"><?= (int) $invoices_count ?></span>
                                </a>
                            <?php endif; ?>
                            <?php if (is_module_enabled('tickets')): ?>
                                <a href="tickets.php" class="p-3.5 rounded-xl border border-slate-200 bg-slate-50/50 flex items-center justify-between hover:border-indigo-400 hover:bg-indigo-50/40 transition">
                                    <span class="flex items-center gap-2 font-medium text-slate-800 text-sm"><?= icon('ticket', 'w-4 h-4 text-indigo-600') ?> تیکت‌های من</span>
                                    <span class="text-slate-400 text-xs"><?= (int) $tickets_count ?></span>
                                </a>
                            <?php endif; ?>
                            <a href="profile.php" class="p-3.5 rounded-xl border border-slate-200 bg-slate-50/50 flex items-center justify-between hover:border-indigo-400 hover:bg-indigo-50/40 transition">
                                <span class="flex items-center gap-2 font-medium text-slate-800 text-sm"><?= icon('user', 'w-4 h-4 text-indigo-600') ?> پروفایل من</span>
                                <span class="text-slate-400 text-xs">ویرایش اطلاعات</span>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        <?php render_customer_footer(); ?>