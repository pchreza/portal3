# گزارش نهایی انتشار portal3 — نسخهٔ 2.0.0

**تاریخ انتشار:** 2026-08-12
**commit هستهٔ اصلاحات:** `8115470`
**نسخهٔ schema:** `28`
**نویسنده:** Manus AI

## جمع‌بندی اجرایی

در این دور، ایرادهای شناسایی‌شده در نقد امنیتی، reliability، معماری، داده، release engineering و RTL/accessibility به‌صورت اجرایی اصلاح شدند. این release یک hardening release واقعی است، نه صرفاً تغییر ظاهری: تمام mutationهای POST اکنون از guard مرکزی CSRF عبور می‌کنند، logout از GET به POST منتقل شده، migration production از چرخهٔ request جدا شده، secret پیامک از settings دیتابیس خارج شده، assetهای Tailwind از CDN runtime مستقل شده‌اند و برای quality gate پروژه PHPUnit، PHPStan و GitHub Actions اضافه شده است.

> معیار طراحی امنیتی این release این است که token پنهان در فرم به‌تنهایی کافی نیست؛ اعتبارسنجی باید در سمت server و برای هر درخواست state-changing انجام شود [1].

## فهرست اصلاحات اجراشده

| حوزه | اصلاح انجام‌شده | نتیجهٔ قابل سنجش |
|---|---|---|
| CSRF | guard مرکزی برای POST خارج از installer، پوشش فرم‌های حساس و logout | تست منفی روی 17 مسیر: PASS |
| Session و header | policy صریح cookie، HSTS مشروط به HTTPS، CSP nonce، `Permissions-Policy`، `Referrer-Policy` و `X-Content-Type-Options` | header و nonce در HTTP smoke تأیید شد |
| Logout | حذف state-changing GET و تبدیل تمام CTAهای مدیریت و مشتری به POST محافظت‌شده | regression مسیر logout: PASS |
| Secretها | `PORTAL_SMS_API_KEY` از environment خوانده می‌شود؛ migration 25 credential legacy را پاک می‌کند | کلید در release/database جدید ذخیره نمی‌شود |
| Proxy trust | اعتماد به `X-Forwarded-Proto` فقط با `PORTAL_TRUST_PROXY=true` | default امن و fail-closed |
| Asset | حذف CDN runtime Tailwind و fallback CDN datepicker؛ CSS و font محلی | build canonical Tailwind: PASS |
| RBAC | جدول `admin_user_permissions` و UI override اختصاصی هر مدیر با fallback به role | schema 28 و foreign key: PASS |
| Data integrity | transaction برای ticket و importهای مشتری/محصول/پروژه؛ side effect پیامک بعد از commit | مسیرهای چندمرحله‌ای بدون state نیمه‌کاره |
| Domain types | مبلغ‌ها به `DECIMAL(18,2)` و تاریخ‌های اصلی به `DATE` تبدیل شدند؛ constraintهای داده اضافه شد | migration با توقف امن در دادهٔ ناسالم |
| Migration | auto-migrate در production خاموش؛ command مستقل `php bin/migrate.php` اضافه شد | schema gate و migration CLI: PASS |
| Quality pipeline | PHPUnit، PHPStan، Composer scripts و GitHub Actions برای PHP 8.1 تا 8.3 | 5 تست، 14 assertion و PHPStan بدون خطا |
| UX/RTL | logical offsetها، breakpoint زیر 360px، حذف inline handler، label/aria association و حفظ داده‌های mixed-direction | accessibility scan روی 20 صفحه: PASS |

## اصلاحات امنیتی مهم

### CSRF و عملیات state-changing

در `config.php` یک guard مرکزی برای تمام POSTهای غیرinstaller اضافه شد. فرم‌ها همچنان token خود را ارسال می‌کنند، اما حتی اگر یک فرم در آینده token را فراموش کند، درخواست بدون token معتبر پیش از اجرای handler با پاسخ 419 متوقف می‌شود. logout نیز اکنون فقط POST است و CTAهای هر دو پنل با فرم محافظت‌شده render می‌شوند. این ساختار با توصیهٔ OWASP دربارهٔ اعتبارسنجی token در server هم‌راستاست [1].

### CSP و حذف inline event handler

تمام `onclick`، `onchange`، `oninput` و `onsubmit`های inline حذف یا به `data-*` attribute تبدیل شدند و listenerها در scriptهای nonceدار ثبت می‌شوند. CSP پاسخ‌ها شامل `script-src 'self'` و nonce یکتا برای هر پاسخ است و `script-src-attr 'none'` inline event handler را مسدود می‌کند. برای stylesheetهای فعلی `style-src 'unsafe-inline'` باقی مانده است؛ این مورد عمدی و non-blocking است و در hardening بعدی با انتقال کامل styleهای inline به CSS tokenها حذف خواهد شد.

### Secret و پیکربندی production

کلید پیامک دیگر از جدول `settings` خوانده یا در آن ذخیره نمی‌شود. مقدار فقط از `PORTAL_SMS_API_KEY` دریافت می‌شود. `.env.example` صرفاً نمونهٔ نام متغیرهاست و توسط PHP خودکار load نمی‌شود؛ مقادیر باید در environment سرویس وب و cron تنظیم شوند. `PORTAL_DEV_MODE` و `PORTAL_AUTO_MIGRATE` در production به‌صورت پیش‌فرض خاموش هستند.

فایل root `.htaccess` نیز فایل‌های حساس مانند `.env`، `db_config.php`، Composer metadata و ابزارهای توسعه را deny می‌کند و اجرای script در `uploads/.htaccess` همچنان مسدود است.

## یکپارچگی داده و استقرار

ایجاد تیکت مشتری، پاسخ و تغییر وضعیت تیکت مدیر و importهای Excel در transaction اجرا می‌شوند. پیامک‌های ناشی از import تا بعد از commit به تعویق می‌افتند تا failure شبکه باعث باقی‌ماندن دادهٔ نیمه‌کاره نشود. migrationهای type-safe برای price، amount و تاریخ، دادهٔ ناسالم را silently تبدیل نمی‌کنند؛ در صورت وجود مقدار ناسازگار، migration باید متوقف شود تا تصمیم آگاهانهٔ operator گرفته شود.

در local/test می‌توان auto-migrate را فعال نگه داشت، اما در production ارتقا باید به‌صورت صریح انجام شود:

```bash
composer install --no-dev
php bin/migrate.php
```

در نصب تازه، `install.php` schema پایه را ایجاد می‌کند. در upgrade production، ابتدا backup دیتابیس تهیه کنید، سپس migration CLI را اجرا کنید و بعد سرویس وب را در دسترس قرار دهید. اگر کاربر دیتابیس مجوز `CREATE DATABASE` ندارد، دیتابیس را ابتدا توسط operator بسازید و همان نام را در installer وارد کنید.

## UX، RTL و accessibility

وابستگی runtime به CDN حذف شد و Tailwind با `package.json` و `pnpm-lock.yaml` قابل بازتولید است. فونت Vazirmatn نیز پیش‌تر به‌صورت variable WOFF2 محلی اضافه شده بود. offsetهای فیزیکی با logical property جایگزین شدند و برای viewportهای 320 تا 360 پیکسل، کارت‌های جدول، toolbar و action bar reflow فشرده‌تری دارند.

اسکن خودکار label/aria روی 20 صفحهٔ اصلی مدیر و مشتری بدون مورد unlabeled گذشت. ممیزی inline handler نیز صفر مورد گزارش کرد. بازبینی بصری داشبورد مشتری، skip-link، navigation، notification، theme toggle، logout و داده‌های mixed RTL/LTR را تأیید کرد. جزئیات این بازبینی در `RTL-QA-ROUND4.md` ثبت شده است. این رویکرد با الزام WCAG دربارهٔ reflow محتوای غیرجدولی در viewport باریک سازگار است [2].

## آزمون‌های release

| آزمون | نتیجه |
|---|---|
| `composer validate --no-check-publish` | PASS |
| PHP lint تمام فایل‌های خارج از vendor | PASS |
| `git diff --check` | PASS |
| PHPUnit | PASS — 5 tests، 14 assertions |
| PHPStan | PASS — 46 فایل بدون خطا |
| fresh schema E2E | PASS — 26 جدول، 16 foreign key |
| migration CLI | PASS — schema version 28 |
| CSRF negative regression | PASS — 17 مسیر |
| accessibility label/aria scan | PASS — 20 صفحه |
| authenticated route crawl | PASS — 48 مسیر مدیر و 13 مسیر مشتری؛ بدون non-200، warning یا exception |
| authenticated smoke | PASS — 26 مسیر اصلی |
| Excel XLSX/CSV round-trip | PASS |
| Excel workbook ZIP integrity | PASS |
| cron CLI | PASS — بدون warning، صفر SMS و صفر cleanup ناخواسته در fixture |
| Tailwind local build | PASS — خروجی canonical با hash یکسان |
| browser visual QA | PASS — داشبورد مشتری، RTL، navigation و mixed-direction data |

## فایل‌ها و اجزای جدید مهم

| مسیر | کاربرد |
|---|---|
| `bin/migrate.php` | اجرای مستقل migration در production |
| `.env.example` | فهرست امن متغیرهای environment |
| `assets/tailwind.input.css` | ورودی build محلی Tailwind |
| `assets/tailwind.css` | خروجی local و minified برای runtime |
| `tests/Unit/HelpersTest.php` | unit test helperهای money/date/escape/CSRF |
| `phpunit.xml` و `phpstan.neon` | پیکربندی quality gate |
| `.github/workflows/ci.yml` | CI روی PHP 8.1، 8.2 و 8.3 |
| `RTL-QA-ROUND4.md` | گزارش QA بصری و accessibility دور نهایی |

## محدودیت‌ها و راهنمای عملیاتی

این release برای production deployment آماده است، اما مانند هر پورتال PHP سنتی، hardening وب‌سرور، TLS معتبر، backup دوره‌ای، rotation secret و محدودکردن دسترسی فایل‌ها باید در محیط واقعی نیز اعمال شوند. `vendor/` و `node_modules/` عمداً در ZIP نهایی قرار نمی‌گیرند؛ اولی با Composer و دومی فقط برای build asset نصب می‌شود. `db_config.php` و credentialهای محلی نیز در commit و ZIP وجود ندارند.

## منابع

[1]: https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html "OWASP Cross-Site Request Forgery Prevention Cheat Sheet"
[2]: https://www.w3.org/WAI/WCAG21/Understanding/reflow.html "W3C WCAG 2.1 — Reflow"
