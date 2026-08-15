/**
 * onboarding.js — سیستم آموزش تعاملی مدیران جدید (Portal3)
 * یک tour گام‌به‌گام که بخش‌های اصلی پنل ادمین را معرفی می‌کند.
 * ذخیره‌سازی وضعیت در localStorage و هماهنگ با سرور (set_setting).
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'portal_onboarding_dismissed';
    var overlay = null;
    var currentStep = 0;
    var resizeTimer = null;

    /** آیکون‌های SVG (hardcode، بدون ورودی کاربر) */
    var ICONS = {
        menu: '<path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/>',
        layers: '<path d="m12 2 8.5 4.5-8.5 4.5L3.5 6.5Z"/><path d="m3.5 12 8.5 4.5 8.5-4.5"/><path d="m3.5 17 8.5 4.5 8.5-4.5"/>',
        dashboard: '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
        file: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
        users: '<path d="M17 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>',
        ticket: '<path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/>',
        settings: '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
        info: '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
        bell: '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
        star: '<path d="M11.5 2.5 14 8l6 .5-4.5 3.9 1.3 5.9L11.5 15l-5.3 3.3 1.3-5.9L3 8.5 9 8z"/>'
    };

    function getIcon(name) {
        var path = ICONS[name] || ICONS.info;
        return '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + path + '</svg>';
    }

    /** گام‌های آموزش — هر گام یک selector، عنوان و توضیح دارد */
    var STEPS = [
        {
            selector: '.portal-topbar',
            title: 'نوار بالایی',
            body: 'در این بخش، نام شما و دکمه تغییر حالت روشن/تاریک نمایش داده می‌شود. همچنین دکمه خروج در نسخه موبایل اینجا قرار دارد.',
            icon: 'menu'
        },
        {
            selector: '.portal-sidebar',
            title: 'منوی کناری',
            body: 'از این منو می‌توانید به تمام بخش‌های مدیریت دسترسی داشته باشید: مشتریان، پروژه‌ها، محصولات، فاکتورها، تیکت‌ها، نظرسنجی‌ها و تنظیمات.',
            icon: 'layers'
        },
        {
            selector: '.portal-kpi-grid, .portal-stat-card',
            title: 'کارت‌های آماری داشبورد',
            body: 'در داشبورد، آمار کلی مشتریان، پروژه‌های فعال، محصولات و تیکت‌های باز را به‌سرعت مشاهده کنید. این کارت‌ها به‌صورت خودکار به‌روزرسانی می‌شوند.',
            icon: 'dashboard'
        },
        {
            selector: '.portal-sidebar .nav-item',
            title: 'مدیریت مشتریان',
            body: 'با کلیک روی «مشتریان» در منوی کناری، می‌توانید مشتریان جدید اضافه کنید، اطلاعات آن‌ها را ویرایش کنید و فیلدهای سفارشی تعریف کنید. هر مشتری می‌تواند پروژه، محصول، فاکتور و تیکت مخصوص به خود داشته باشد.',
            icon: 'users'
        },
        {
            selector: '.portal-sidebar .nav-item',
            title: 'پشتیبانی و تیکت‌ها',
            body: 'در بخش «تیکت‌ها»، مشتریان سوالات و مشکلات خود را مطرح می‌کنند. شما می‌توانید پاسخ دهید، وضعیت تیکت را تغییر دهید و آن را به دپارتمان‌های مختلف ارجاع دهید.',
            icon: 'ticket'
        },
        {
            selector: '.portal-sidebar .nav-item',
            title: 'اعلان‌ها و نظرسنجی‌ها',
            body: 'از بخش «اعلان‌ها» می‌توانید پیام‌های عمومی یا هدفمند ارسال کنید. نظرسنجی‌ها به شما کمک می‌کنند بازخورد مشتریان را جمع‌آوری کنید.',
            icon: 'bell'
        },
        {
            selector: '.portal-sidebar .nav-item',
            title: 'باشگاه امتیاز و پاداش',
            body: 'در بخش «گیمیفیکیشن»، قوانین امتیازدهی را تنظیم کنید، پاداش و کد تخفیف ایجاد کنید و فعالیت مشتریان را پایش کنید. مشتریان با تکمیل پروفایل، ثبت تیکت و پاسخ به نظرسنجی امتیاز کسب می‌کنند.',
            icon: 'star'
        },
        {
            selector: '.portal-sidebar .nav-item',
            title: 'تنظیمات سیستم',
            body: 'در بخش «تنظیمات» (فقط برای مدیر ارشد)، می‌توانید ظاهر سایت، رنگ‌بندی، سیستم پیامک، ماژول‌ها و backup/restore را مدیریت کنید.',
            icon: 'settings'
        },
        {
            selector: '.portal-main',
            title: 'محتوای اصلی',
            body: 'تمام عملیات مدیریت در این بخش انجام می‌شود. می‌توانید مشتری جدید اضافه کنید، فاکتور صادر کنید یا به تیکت‌ها پاسخ دهید.',
            icon: 'file'
        },
        {
            selector: '.portal-topbar',
            title: 'راهنمایی نهایی',
            body: 'برای مشاهده مجدد این آموزش، به «پروفایل مدیر» بروید و دکمه «نمایش آموزش» را بزنید. همچنین می‌توانید با کلیک روی دکمه تغییر تم، حالت روشن یا تاریک را انتخاب کنید. کلید Escape برای بستن آموزش استفاده می‌شود.',
            icon: 'info'
        }
    ];

    function isDismissed() {
        try {
            return localStorage.getItem(STORAGE_KEY) === '1';
        } catch (e) {
            return false;
        }
    }

    function setDismissed() {
        try {
            localStorage.setItem(STORAGE_KEY, '1');
        } catch (e) { /* ignore */ }
    }

    function clearDismissed() {
        try {
            localStorage.removeItem(STORAGE_KEY);
        } catch (e) { /* ignore */ }
    }

    function createOverlay() {
        overlay = document.createElement('div');
        overlay.className = 'portal-onboarding-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-labelledby', 'portal-onboarding-title');
        overlay.innerHTML =
            '<div class="portal-onboarding-spotlight"></div>' +
            '<div class="portal-onboarding-tooltip" role="document" tabindex="-1">' +
                '<div class="portal-onboarding-header">' +
                    '<span class="portal-onboarding-icon"></span>' +
                    '<h3 id="portal-onboarding-title" class="portal-onboarding-title"></h3>' +
                    '<span class="portal-onboarding-counter"></span>' +
                '</div>' +
                '<div class="portal-onboarding-body"></div>' +
                '<div class="portal-onboarding-actions">' +
                    '<button type="button" class="btn btn-secondary btn-sm portal-onboarding-skip">رد کردن</button>' +
                    '<div class="portal-onboarding-nav">' +
                        '<button type="button" class="btn btn-secondary btn-sm portal-onboarding-prev">قبلی</button>' +
                        '<button type="button" class="btn btn-primary btn-sm portal-onboarding-next">بعدی</button>' +
                    '</div>' +
                '</div>' +
            '</div>';
        document.body.appendChild(overlay);
        document.body.classList.add('portal-onboarding-active');

        overlay.querySelector('.portal-onboarding-skip').addEventListener('click', close);
        overlay.querySelector('.portal-onboarding-prev').addEventListener('click', prev);
        overlay.querySelector('.portal-onboarding-next').addEventListener('click', next);
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay || e.target.classList.contains('portal-onboarding-spotlight')) {
                close();
            }
        });
        document.addEventListener('keydown', onKeydown);
        window.addEventListener('resize', onResize);
    }

    function onKeydown(e) {
        if (e.key === 'Escape') {
            e.preventDefault();
            close();
        } else if (e.key === 'ArrowLeft') {
            // در RTL، ArrowLeft یعنی بعدی
            e.preventDefault();
            next();
        } else if (e.key === 'ArrowRight') {
            // در RTL، ArrowRight یعنی قبلی
            e.preventDefault();
            prev();
        } else if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            next();
        }
    }

    function onResize() {
        if (resizeTimer) {
            clearTimeout(resizeTimer);
        }
        resizeTimer = setTimeout(function () {
            if (overlay) {
                renderStep();
            }
        }, 150);
    }

    function renderStep() {
        if (!overlay || currentStep >= STEPS.length) {
            close();
            return;
        }
        var step = STEPS[currentStep];
        var target = document.querySelector(step.selector);
        var tooltipEl = overlay.querySelector('.portal-onboarding-tooltip');
        var titleEl = overlay.querySelector('.portal-onboarding-title');
        var bodyEl = overlay.querySelector('.portal-onboarding-body');
        var counterEl = overlay.querySelector('.portal-onboarding-counter');
        var iconEl = overlay.querySelector('.portal-onboarding-icon');
        var prevBtn = overlay.querySelector('.portal-onboarding-prev');
        var nextBtn = overlay.querySelector('.portal-onboarding-next');

        titleEl.textContent = step.title;
        bodyEl.textContent = step.body;
        counterEl.textContent = (currentStep + 1) + ' / ' + STEPS.length;
        prevBtn.style.visibility = currentStep === 0 ? 'hidden' : 'visible';
        nextBtn.textContent = currentStep === STEPS.length - 1 ? 'اتمام' : 'بعدی';

        // SVG icon (hardcode، امن)
        iconEl.innerHTML = getIcon(step.icon);

        // ریست transform قبلی
        tooltipEl.style.transform = '';

        if (target) {
            // اسکرول به target اگر خارج از دید است
            var targetRect = target.getBoundingClientRect();
            if (targetRect.top < 0 || targetRect.bottom > window.innerHeight) {
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                // صبر برای اتمام اسکرول سپس محاسبه مجدد
                setTimeout(function () { positionTooltip(target, tooltipEl); }, 300);
            } else {
                positionTooltip(target, tooltipEl);
            }
        } else {
            // center اگر target پیدا نشد
            tooltipEl.style.top = '50%';
            tooltipEl.style.left = '50%';
            tooltipEl.style.transform = 'translate(-50%, -50%)';
            // مخفی کردن spotlight اگر target نیست
            var spotlight = overlay.querySelector('.portal-onboarding-spotlight');
            if (spotlight) {
                spotlight.style.display = 'none';
            }
        }
    }

    function positionTooltip(target, tooltipEl) {
        var rect = target.getBoundingClientRect();
        var spotlight = overlay.querySelector('.portal-onboarding-spotlight');
        if (spotlight) {
            spotlight.style.display = '';
            spotlight.style.top = Math.max(0, rect.top - 8) + 'px';
            spotlight.style.left = Math.max(0, rect.left - 8) + 'px';
            spotlight.style.width = (rect.width + 16) + 'px';
            spotlight.style.height = (rect.height + 16) + 'px';
        }

        // محاسبه موقعیت tooltip
        var tooltipRect = tooltipEl.getBoundingClientRect();
        var tooltipHeight = tooltipRect.height || 220;
        var tooltipWidth = tooltipRect.width || 360;
        var margin = 12;
        var top = rect.bottom + margin;
        var left = rect.left + (rect.width / 2) - (tooltipWidth / 2);

        // اگر tooltip از پایین صفحه خارج می‌شود، بالا قرار بده
        if (top + tooltipHeight > window.innerHeight - margin) {
            top = Math.max(margin, rect.top - tooltipHeight - margin);
        }
        // اگر tooltip از بالا هم خارج می‌شود، وسط صفحه
        if (top < margin) {
            top = margin;
        }
        // محدودیت افقی
        left = Math.max(margin, Math.min(left, window.innerWidth - tooltipWidth - margin));

        tooltipEl.style.top = top + 'px';
        tooltipEl.style.left = left + 'px';

        // فوکوس روی tooltip برای screen reader
        tooltipEl.focus();
    }

    function next() {
        currentStep++;
        if (currentStep >= STEPS.length) {
            close();
        } else {
            renderStep();
        }
    }

    function prev() {
        if (currentStep > 0) {
            currentStep--;
            renderStep();
        }
    }

    function close() {
        setDismissed();
        if (overlay) {
            overlay.remove();
            overlay = null;
        }
        document.body.classList.remove('portal-onboarding-active');
        document.removeEventListener('keydown', onKeydown);
        window.removeEventListener('resize', onResize);
        if (resizeTimer) {
            clearTimeout(resizeTimer);
            resizeTimer = null;
        }
        // اطلاع به سرور که آموزش رد شد
        notifyServerDismissed();
    }

    function notifyServerDismissed() {
        if (typeof portalOnboardingDismissUrl === 'undefined' || !portalOnboardingDismissUrl) {
            return;
        }
        try {
            var formData = new FormData();
            formData.append('action', 'dismiss_onboarding');
            if (typeof portalOnboardingCsrf !== 'undefined' && portalOnboardingCsrf) {
                formData.append('csrf_token', portalOnboardingCsrf);
            }
            navigator.sendBeacon(portalOnboardingDismissUrl, formData);
        } catch (e) {
            // fallback به fetch اگر sendBeacon در دسترس نیست
            try {
                fetch(portalOnboardingDismissUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=dismiss_onboarding&csrf_token=' + encodeURIComponent(typeof portalOnboardingCsrf !== 'undefined' ? portalOnboardingCsrf : '')
                });
            } catch (e2) { /* ignore */ }
        }
    }

    /** شروع آموزش از ابتدا */
    window.portalStartOnboarding = function () {
        clearDismissed();
        currentStep = 0;
        if (overlay) {
            overlay.remove();
            overlay = null;
        }
        createOverlay();
        // تأخیر کوتاه برای اطمینان از رندر overlay
        setTimeout(renderStep, 50);
    };

    /** شروع خودکار اگر قبلاً رد نشده باشد */
    window.portalAutoStartOnboarding = function () {
        if (!isDismissed()) {
            window.portalStartOnboarding();
        }
    };

    // Auto-start on DOMContentLoaded if flag is set
    document.addEventListener('DOMContentLoaded', function () {
        if (document.documentElement.getAttribute('data-portal-onboarding') === 'auto') {
            // تأخیر کوتاه برای اطمینان از رندر کامل صفحه
            setTimeout(window.portalAutoStartOnboarding, 500);
        }
    });
})();
