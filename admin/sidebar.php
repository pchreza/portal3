<?php
// admin/sidebar.php - Modular Admin Sidebar (منوها بر اساس دسترسی‌های مدیر مخفی می‌شوند)
$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? '';
$is_super = $role === 'super_admin';
$site_title_short = get_setting('site_title', 'پورتال مشتریان');
?>
<style>@media (max-width:767px){body{padding-top:3.5rem}}</style>
<!-- هدر موبایل + دکمه منو -->
<div class="portal-mobile-bar md:hidden fixed top-0 inset-x-0 z-40 h-14 bg-slate-900 text-white flex items-center gap-3 px-4 shadow-lg">
    <button type="button" aria-label="باز کردن منو" aria-expanded="false" aria-controls="mobile-admin-menu" class="hamburger-btn shrink-0"><?= icon('menu') ?></button>
    <span class="font-bold truncate min-w-0"><?= htmlspecialchars($site_title_short) ?> — مدیریت</span>
</div>
<button type="button" class="mobile-nav-backdrop" aria-label="بستن منوی مدیریت" tabindex="-1"></button>
<aside id="mobile-admin-menu" class="portal-sidebar portal-sidebar-admin hidden md:flex w-64 bg-slate-900 text-slate-300 flex-col fixed inset-y-0 right-0 z-50 border-l border-slate-800 flex-shrink-0 shadow-2xl md:sticky md:top-0 md:h-screen md:overflow-hidden md:inset-y-auto md:right-auto md:left-auto">
    <div class="portal-sidebar-scroll flex-1 overflow-y-auto min-h-0">
        <div class="portal-sidebar-brand px-5 py-5 border-b border-slate-800 flex items-center gap-3">
            <div class="flex-shrink-0"><?= site_logo_html('w-11 h-11 rounded-xl text-lg flex-shrink-0') ?></div>
            <div class="min-w-0">
                <div class="text-white font-bold text-base leading-snug truncate"><?= htmlspecialchars(get_setting('site_title', 'پورتال مشتریان')) ?> <span class="text-slate-400 font-normal">— مدیریت</span></div>
                <p class="text-xs text-slate-400 mt-1 leading-relaxed">مدیریت هوشمند مشتریان و پروژه‌ها</p>
            </div>
        </div>
        <nav class="portal-sidebar-nav p-4 space-y-1.5 text-sm" aria-label="منوی مدیریت"><p class="portal-nav-section-label">دسترسی سریع</p>
            <?php if ($is_super || admin_can('dashboard')): ?>
            <a href="index.php" class="nav-item <?php echo $current_page === 'index.php' ? 'active' : ''; ?>"><?= icon('dashboard') ?><span>داشبورد</span></a>
            <?php endif; ?>

            <p class="portal-nav-section-label portal-nav-section-label-spaced">مدیریت سامانه</p>
            <?php if ($is_super || admin_can('customers')): ?>
            <a href="customers.php" class="nav-item <?php echo $current_page === 'customers.php' ? 'active' : ''; ?>"><?= icon('users') ?><span>مدیریت مشتریان</span></a>
            <?php endif; ?>

            <?php if (($is_super || admin_can('projects')) && is_module_enabled('projects')): ?>
            <a href="projects.php" class="nav-item <?php echo $current_page === 'projects.php' ? 'active' : ''; ?>"><?= icon('folder') ?><span>مدیریت پروژه‌ها</span></a>
            <?php endif; ?>

            <?php if (($is_super || admin_can('products')) && is_module_enabled('products')): ?>
            <a href="products.php" class="nav-item <?php echo $current_page === 'products.php' ? 'active' : ''; ?>"><?= icon('box') ?><span>مدیریت محصولات</span></a>
            <?php endif; ?>

            <?php if (($is_super || admin_can('invoices')) && is_module_enabled('invoices')): ?>
            <a href="invoices.php" class="nav-item <?php echo $current_page === 'invoices.php' ? 'active' : ''; ?>"><?= icon('card') ?><span>مدیریت فاکتورها</span></a>
            <?php endif; ?>

            <?php if (($is_super || admin_can('tickets')) && is_module_enabled('tickets')): ?>
            <a href="tickets.php" class="nav-item <?php echo $current_page === 'tickets.php' ? 'active' : ''; ?>"><?= icon('ticket') ?><span>تیکت‌های پشتیبانی</span></a>
            <?php endif; ?>

            <?php if (($is_super || admin_can('ticket_departments')) && is_module_enabled('tickets')): ?>
            <a href="ticket-departments.php" class="nav-item <?php echo $current_page === 'ticket-departments.php' ? 'active' : ''; ?>"><?= icon('layers') ?><span>دپارتمان‌های تیکت</span></a>
            <?php endif; ?>

            <?php if (($is_super || admin_can('surveys')) && is_module_enabled('surveys')): ?>
            <a href="surveys.php" class="nav-item <?php echo $current_page === 'surveys.php' ? 'active' : ''; ?>"><?= icon('star') ?><span>سیستم نظرسنجی</span></a>
            <?php endif; ?>

            <?php if (($is_super || admin_can('custom_fields')) && is_module_enabled('custom_fields')): ?>
            <a href="custom_fields.php" class="nav-item <?php echo $current_page === 'custom_fields.php' ? 'active' : ''; ?>"><?= icon('wrench') ?><span>فیلدهای سفارشی پویا</span></a>
            <?php endif; ?>

            <p class="portal-nav-section-label portal-nav-section-label-spaced">ارتباط و گزارش</p>
            <?php if (($is_super || admin_can('notifications')) && is_module_enabled('notifications')): ?>
            <a href="notifications.php" class="nav-item <?php echo $current_page === 'notifications.php' ? 'active' : ''; ?>"><?= icon('bell') ?><span>اعلانات و اطلاع‌رسانی</span></a>
            <?php endif; ?>

            <?php if (($is_super || admin_can('logs')) && is_module_enabled('logs')): ?>
            <a href="logs.php" class="nav-item <?php echo $current_page === 'logs.php' ? 'active' : ''; ?>"><?= icon('file') ?><span>گزارش فعالیت‌ها</span></a>
            <?php endif; ?>

            <?php if (($is_super || admin_can('error_reports')) && is_module_enabled('error_reports')): ?>
            <a href="error-reports.php" class="nav-item <?php echo $current_page === 'error-reports.php' ? 'active' : ''; ?>"><?= icon('alert') ?><span>گزارش‌های خطا</span></a>
            <?php endif; ?>

            <?php if (($is_super || admin_can('gamification')) && is_module_enabled('gamification')): ?>
            <a href="gamification.php" class="nav-item <?php echo $current_page === 'gamification.php' ? 'active' : ''; ?>"><?= icon('star') ?><span>باشگاه امتیاز و پاداش</span></a>
            <?php endif; ?>

            <?php if ($is_super || admin_can('settings')): ?>
            <a href="settings.php" class="nav-item <?php echo $current_page === 'settings.php' ? 'active' : ''; ?>"><?= icon('settings') ?><span>تنظیمات سیستم</span></a>
            <?php endif; ?>
        </nav>
    </div>
    <div class="p-4 border-t border-slate-800 shrink-0">
        <?php if ($is_super): ?>
        <a href="admins.php" class="nav-item <?php echo $current_page === 'admins.php' ? 'active' : ''; ?> mb-1"><?= icon('users2') ?><span>مدیریت مدیران</span></a>
        <?php endif; ?>
        <?php if ($is_super || admin_can('profile')): ?>
        <a href="profile.php" class="nav-item <?php echo $current_page === 'profile.php' ? 'active' : ''; ?> mb-1"><?= icon('user') ?><span>پروفایل مدیر</span></a>
        <?php endif; ?>
        <form method="post" action="../logout.php" class="mb-1">
            <?= csrf_input() ?>
            <button type="submit" class="nav-item w-full text-red-400 hover:!text-red-300 hover:!bg-red-900/40"><?= icon('logout') ?><span>خروج از حساب</span></button>
        </form>
    </div>
</aside>
<script nonce="<?= e(portal_csp_nonce()) ?>">
document.addEventListener('DOMContentLoaded', function(){
    var menu = document.getElementById('mobile-admin-menu');
    var toggle = document.querySelector('[aria-controls="mobile-admin-menu"]');
    var backdrop = document.querySelector('.mobile-nav-backdrop');
    if (!menu || !toggle || !backdrop) return;
    var lastTrigger = toggle;
    function closeMenu(){
        menu.classList.add('hidden');
        backdrop.classList.remove('is-open');
        document.body.classList.remove('mobile-nav-open');
        toggle.setAttribute('aria-expanded', 'false');
        if (window.innerWidth < 768) lastTrigger.focus();
    }
    function openMenu(){
        lastTrigger = document.activeElement === toggle ? toggle : toggle;
        menu.classList.remove('hidden');
        backdrop.classList.add('is-open');
        document.body.classList.add('mobile-nav-open');
        toggle.setAttribute('aria-expanded', 'true');
        var first = menu.querySelector('a, button');
        if (first) first.focus();
    }
    toggle.addEventListener('click', function(){
        if (menu.classList.contains('hidden')) openMenu(); else closeMenu();
    });
    backdrop.addEventListener('click', closeMenu);
    document.addEventListener('keydown', function(e){
        if (window.innerWidth >= 768 || menu.classList.contains('hidden')) return;
        if (e.key === 'Escape') { e.preventDefault(); closeMenu(); return; }
        if (e.key === 'Tab') {
            var focusables = Array.prototype.slice.call(menu.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])')).filter(function(el){ return el.offsetParent !== null; });
            if (!focusables.length) return;
            var first = focusables[0], last = focusables[focusables.length - 1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        }
    });
    menu.querySelectorAll('a').forEach(function(a){
        a.addEventListener('click', function(){ if (window.innerWidth < 768) closeMenu(); });
    });
});
</script>
