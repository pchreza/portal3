<?php
// customer/sidebar.php - Modular Customer Sidebar
$current_page = basename($_SERVER['PHP_SELF']);
$site_title_short = get_setting('site_title', 'پورتال مشتریان');
?>
<style>@media (max-width:767px){body{padding-top:3.5rem}}</style>
<div class="md:hidden fixed top-0 inset-x-0 z-40 h-14 bg-slate-900 text-white flex items-center justify-between px-4 shadow-lg">
    <span class="font-bold truncate"><?= htmlspecialchars($site_title_short) ?></span>
    <button type="button" aria-label="باز کردن منو" aria-expanded="false" onclick="this.setAttribute('aria-expanded', this.getAttribute('aria-expanded')==='true'?'false':'true'); document.getElementById('mobile-customer-menu').classList.toggle('hidden');" class="hamburger-btn"><?= icon('menu') ?></button>
</div>
<aside id="mobile-customer-menu" class="hidden md:flex w-64 bg-slate-900 text-slate-300 flex-col fixed inset-y-0 right-0 z-50 border-l border-slate-800 flex-shrink-0 shadow-2xl md:sticky md:top-0 md:h-screen md:overflow-hidden md:inset-y-auto md:right-auto md:left-auto">
    <div class="flex-1 overflow-y-auto min-h-0">
        <div class="px-5 py-5 border-b border-slate-800 flex items-center gap-3">
            <div class="flex-shrink-0"><?= site_logo_html('w-11 h-11 rounded-xl text-lg flex-shrink-0') ?></div>
            <div class="min-w-0">
                <div class="text-white font-bold text-base leading-snug truncate"><?= htmlspecialchars($site_title_short) ?></div>
                <p class="text-xs text-slate-400 mt-1 leading-relaxed">پنل اختصاصی مشتری</p>
            </div>
        </div>
        <nav class="p-4 space-y-1.5 text-sm" aria-label="منوی مشتری">
            <a href="index.php" class="nav-item <?php echo $current_page === 'index.php' ? 'active' : ''; ?>"><?= icon('dashboard') ?><span>داشبورد من</span></a>
            <?php if (is_module_enabled('projects')): ?>
            <a href="projects.php" class="nav-item <?php echo $current_page === 'projects.php' ? 'active' : ''; ?>"><?= icon('folder') ?><span>پروژه‌های من</span></a>
            <?php endif; ?>
            <?php if (is_module_enabled('products')): ?>
            <a href="products.php" class="nav-item <?php echo $current_page === 'products.php' ? 'active' : ''; ?>"><?= icon('box') ?><span>محصولات من</span></a>
            <?php endif; ?>
            <?php if (is_module_enabled('invoices')): ?>
            <a href="invoices.php" class="nav-item <?php echo $current_page === 'invoices.php' ? 'active' : ''; ?>"><?= icon('card') ?><span>فاکتورهای من</span></a>
            <?php endif; ?>
            <?php if (is_module_enabled('tickets')): ?>
            <a href="tickets.php" class="nav-item <?php echo $current_page === 'tickets.php' ? 'active' : ''; ?>"><?= icon('ticket') ?><span>تیکت‌های پشتیبانی</span></a>
            <?php endif; ?>
            <?php if (is_module_enabled('surveys')): ?>
            <a href="surveys.php" class="nav-item <?php echo $current_page === 'surveys.php' ? 'active' : ''; ?>"><?= icon('star') ?><span>نظرسنجی‌ها</span></a>
            <?php endif; ?>
            <a href="notifications.php" class="nav-item <?php echo $current_page === 'notifications.php' ? 'active' : ''; ?>"><?= icon('bell') ?><span>اعلانات من</span></a>
            <a href="profile.php" class="nav-item <?php echo $current_page === 'profile.php' ? 'active' : ''; ?>"><?= icon('user') ?><span>پروفایل و اطلاعات شخصی</span></a>
        </nav>
    </div>
    <div class="p-4 border-t border-slate-800 shrink-0">
        <a href="../logout.php" class="nav-item mb-1 text-red-400 hover:!text-red-300 hover:!bg-red-900/40"><?= icon('logout') ?><span>خروج از حساب</span></a>
    </div>
</aside>
<script>
// بستن خودکار منوی موبایل بعد از کلیک روی هر لینک
document.addEventListener('DOMContentLoaded', function(){
    var menu = document.getElementById('mobile-customer-menu');
    if (menu) {
        menu.querySelectorAll('a').forEach(function(a){
            a.addEventListener('click', function(){
                if (window.innerWidth < 768) menu.classList.add('hidden');
            });
        });
    }
});
</script>
