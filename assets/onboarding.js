/**
 * onboarding.js — سیستم آموزش تعاملی مدیران جدید (Portal3)
 * یک tour گام‌به‌گام که بخش‌های اصلی پنل ادمین را معرفی می‌کند.
 * ذخیره‌سازی وضعیت در localStorage و هماهنگ با سرور (set_setting).
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'portal_onboarding_dismissed';

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
            body: 'در داشبورد، آمار کلی مشتریان، پروژه‌های فعال، محصولات و تیکت‌های باز را به‌سرعت مشاهده کنید.',
            icon: 'dashboard'
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
            body: 'برای مشاهده مجدد این آموزش، به «پروفایل مدیر» بروید و دکمه «نمایش آموزش» را بزنید. همچنین می‌توانید با کلیک روی دکمه تغییر تم، حالت روشن یا تاریک را انتخاب کنید.',
            icon: 'info'
        }
    ];

    var overlay = null;
    var tooltip = null;
    var currentStep = 0;

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
            '<div class="portal-onboarding-tooltip" role="document">' +
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

        overlay.querySelector('.portal-onboarding-skip').addEventListener('click', close);
        overlay.querySelector('.portal-onboarding-prev').addEventListener('click', prev);
        overlay.querySelector('.portal-onboarding-next').addEventListener('click', next);
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay || e.target.classList.contains('portal-onboarding-spotlight')) {
                close();
            }
        });
        document.addEventListener('keydown', onKeydown);
    }

    function onKeydown(e) {
        if (e.key === 'Escape') {
            close();
        } else if (e.key === 'ArrowLeft') {
            next();
        } else if (e.key === 'ArrowRight') {
            prev();
        }
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

        // SVG icon
        if (iconEl && typeof portalOnboardingIcon === 'function') {
            iconEl.innerHTML = portalOnboardingIcon(step.icon);
        }

        // Position tooltip near target
        if (target) {
            var rect = target.getBoundingClientRect();
            var spotlight = overlay.querySelector('.portal-onboarding-spotlight');
            spotlight.style.top = (rect.top - 8) + 'px';
            spotlight.style.left = (rect.left - 8) + 'px';
            spotlight.style.width = (rect.width + 16) + 'px';
            spotlight.style.height = (rect.height + 16) + 'px';

            // Position tooltip below or above target
            var tooltipHeight = 200;
            var tooltipWidth = 360;
            var top = rect.bottom + 12;
            if (top + tooltipHeight > window.innerHeight) {
                top = Math.max(10, rect.top - tooltipHeight - 12);
            }
            var left = rect.left + (rect.width / 2) - (tooltipWidth / 2);
            left = Math.max(16, Math.min(left, window.innerWidth - tooltipWidth - 16));
            tooltipEl.style.top = top + 'px';
            tooltipEl.style.left = left + 'px';
        } else {
            // Center if target not found
            tooltipEl.style.top = '50%';
            tooltipEl.style.left = '50%';
            tooltipEl.style.transform = 'translate(-50%, -50%)';
        }
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
        document.removeEventListener('keydown', onKeydown);
        // Notify server that onboarding was dismissed
        if (typeof portalOnboardingDismissUrl !== 'undefined' && portalOnboardingDismissUrl) {
            fetch(portalOnboardingDismissUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'action=dismiss_onboarding&csrf_token=' + (typeof portalOnboardingCsrf !== 'undefined' ? portalOnboardingCsrf : '') });
        }
    }

    /** شروع آموزش از ابتدا */
    window.portalStartOnboarding = function () {
        clearDismissed();
        currentStep = 0;
        if (overlay) {
            overlay.remove();
        }
        createOverlay();
        renderStep();
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
            window.portalAutoStartOnboarding();
        }
    });
})();
