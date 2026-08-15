# Portal3 — پورتال مشتریان

**نسخهٔ انتشار:** `2.7.1`
**schema دیتابیس:** `35`
**نوع سیستم:** پورتال مشتریان با PHP خالص، MariaDB/MySQL و رابط فارسی RTL
**مخزن رسمی:** [github.com/pchreza/portal3](https://github.com/pchreza/portal3)

Portal3 سامانه‌ای برای مدیریت مشتریان، پروژه‌ها، محصولات، فاکتورها، پشتیبانی، نظرسنجی، اعلان، فیلدهای سفارشی و باشگاه امتیاز و پاداش است. رابط کاربری کاملاً فارسی، راست‌چین، responsive و مبتنی بر فونت محلی Vazirmatn است. منطق PHP، session، RBAC، CSRF و سیاست‌های امنیتی در هستهٔ سیستم حفظ شده‌اند.

## ویژگی‌ها

| بخش | قابلیت‌ها |
|---|---|
| حساب کاربری | ورود امن، session، محدودسازی تلاش ناموفق و OTP در صورت پیکربندی سرویس پیامک |
| مدیریت مشتری | ایجاد، ویرایش، جست‌وجو، وضعیت، اطلاعات تماس و فیلدهای تکمیلی |
| پروژه و محصول | تخصیص به مشتری، وضعیت، توضیحات، بودجه یا قیمت، deadline، تصویر و license key |
| فاکتور | شمارهٔ یکتا، مبلغ، وضعیت پرداخت، تاریخ سررسید و مشاهده در پنل مشتری |
| پشتیبانی | تیکت، پاسخ، وضعیت، اولویت، دپارتمان و تاریخچهٔ گفتگو |
| نظرسنجی | سؤال‌های امتیازی، بله/خیر، ستاره‌ای، رضایت پنج‌گزینه‌ای، متن آزاد و چندگزینه‌ای؛ لینک عمومی امن و گزارش توزیع پاسخ‌ها |
| اعلان | ارسال عمومی یا هدفمند، وضعیت خوانده‌شدن و CTA داخلی امن به اقدام مرتبط |
| فیلد سفارشی | فیلد برای مشتری، پروژه یا محصول با پشتیبانی از الزامی/اختیاری بودن |
| Gamification | کیف امتیاز، دفترکل، قوانین قابل تنظیم، کد هدیه، پاداش، coupon pool و اعلان خودکار امتیاز |
| Backup/Restore | پشتیبان‌گیری و بازیابی کنترل‌شده از بخش تنظیمات برای مدیر ارشد |
| گزارش و import/export | گزارش فعالیت و خطا، import/export فایل‌های XLSX یا CSV در مسیرهای مربوط |
| رابط کاربری | فارسی، RTL واقعی، dark mode، responsive از موبایل تا desktop، focus ring و assetهای محلی بدون CDN |

## نیازمندی‌های اجرا

| مورد | نیازمندی |
|---|---|
| PHP | نسخهٔ 8.1 یا بالاتر؛ PHP 8.2 یا 8.3 پیشنهاد می‌شود |
| Database | MariaDB 10.11 یا MySQL سازگار با InnoDB و `utf8mb4` |
| Extensionها | `PDO`، `pdo_mysql`، `mbstring`، `xml` و `zip` |
| Web server | Apache با فعال بودن `.htaccess`؛ در nginx باید ruleهای امنیتی معادل تعریف شود |
| HTTPS | برای production الزامی است |
| Composer | فقط زمانی لازم است که dependencyهای PHP از قبل در `vendor/` نصب نشده باشند |
| Node.js/pnpm | فقط برای بازسازی CSS در محیط توسعه؛ برای اجرای release لازم نیست |

## نصب تازه با ویزارد

### ۱. استخراج فایل‌ها

ZIP نسخهٔ release را در web root استخراج کنید. ساختار نهایی باید بدون لایهٔ اضافی باشد:

```text
portal3/
├── index.php
├── install.php
├── config.php
├── schema.sql
├── admin/
├── customer/
├── includes/
├── assets/
├── storage/
└── uploads/
```

در Laragon و XAMPP، مسیرهای رایج به‌ترتیب زیر هستند:

```text
C:\laragon\www\portal3
C:\xampp\htdocs\portal3
```

### ۲. ایجاد دیتابیس

یک دیتابیس خالی با collation مناسب بسازید:

```sql
CREATE DATABASE client_portal
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

کاربر production باید فقط به دیتابیس Portal3 دسترسی داشته باشد و نباید از کاربر `root` استفاده شود.

### ۳. تنظیم دسترسی فایل

وب‌سرور باید در زمان نصب بتواند `db_config.php` را در ریشه ایجاد کند و در زمان اجرا به `storage/` و `uploads/` دسترسی نوشتن داشته باشد. اجرای PHP در `uploads/` باید توسط `.htaccess` یا rule معادل nginx مسدود بماند.

### ۴. اجرای ویزارد

مرورگر را به مسیر زیر ببرید:

```text
http://localhost/portal3/install.php
```

یا در production:

```text
https://example.com/portal3/install.php
```

در wizard، host دیتابیس، نام دیتابیس، کاربر، رمز و اطلاعات مدیر ارشد را وارد کنید. پس از پایان نصب، با حساب مدیر وارد شوید، تنظیمات سایت و palette را بررسی کنید و دسترسی `install.php` را طبق سیاست هاست محدود کنید. مقدارهای حساس را در Git، log یا screenshot ذخیره نکنید.

## نصب روی هاست بدون Composer یا SSH

این release به‌صورت runtime-only منتشر می‌شود و پوشهٔ `vendor/` داخل ZIP قرار ندارد. روی یک سیستم دارای Composer، dependencyهای production را نصب کنید:

```bash
composer install --no-dev --optimize-autoloader
```

سپس کل پوشهٔ `vendor/` را همراه کد release روی هاست بارگذاری کنید. اگر هاست Composer و SSH ندارد، همین کار را با کامپیوتر محلی انجام دهید و پوشهٔ `vendor/` را از طریق File Manager یا FTP بارگذاری کنید. فایل `composer.json` و `composer.lock` برای بازتولید دقیق dependencyها در مخزن باقی می‌مانند.

## ارتقای نصب موجود

پیش از ارتقا از فایل‌ها، دیتابیس و `uploads/` backup بگیرید. سپس فایل‌های release را جایگزین کنید، اما `db_config.php` و داده‌های runtime را حفظ کنید. از ریشهٔ پروژه migration را اجرا کنید:

```bash
php bin/migrate.php
```

در نسخهٔ `2.7.0`، schema نهایی باید `35` باشد. migrationها idempotent هستند و migration اجراشده را دوباره اعمال نمی‌کنند. در production مقدارهای زیر را تنظیم کنید و migration را فقط در maintenance window اجرا کنید:

```dotenv
PORTAL_ENV=production
PORTAL_DEV_MODE=false
PORTAL_AUTO_MIGRATE=false
PORTAL_TRUST_PROXY=false
```

اگر SSH یا CLI در دسترس نیست، از قابلیت migration خودکار کنترل‌شدهٔ پنل مدیر مطابق تنظیمات محیط استفاده کنید؛ برای production اجرای CLI روش ترجیحی است. در صورت مشاهدهٔ پیام «سامانه در حال ارتقاست»، ابتدا backup بگیرید و سپس `php bin/migrate.php` را اجرا کنید.

## راهنمای استفادهٔ مدیر

پس از ورود به `admin/index.php`، مدیر به داشبورد و بخش‌های زیر دسترسی دارد:

| مسیر | کاربرد |
|---|---|
| `customers.php` | مدیریت مشتریان و اطلاعات تکمیلی |
| `projects.php` | مدیریت پروژه و فیلتر وضعیت/مشتری |
| `products.php` | مدیریت محصول، جست‌وجو و فیلتر |
| `invoices.php` | مدیریت فاکتورها |
| `tickets.php` | مدیریت تیکت و پاسخ |
| `surveys.php` | ساخت survey، سؤال، تخصیص و گزارش |
| `notifications.php` | ارسال اعلان عمومی یا هدفمند با لینک اقدام داخلی |
| `gamification.php` | تنظیم امتیاز، پاداش، coupon و گزارش کیف مشتریان |
| `settings.php` | تنظیمات عمومی، palette، backup/restore و ماژول‌ها |
| `admins.php` | مدیریت مدیران و permissionها |

برای استفاده از Gamification، ابتدا ماژول را فعال کنید، سپس ruleهای امتیاز را تعیین کنید. امتیازها فقط از رویدادهای معتبر server-side صادر می‌شوند؛ refresh یا hidden input امتیاز ایجاد نمی‌کند. اعلان امتیاز به‌صورت خودکار ایجاد می‌شود و مشتری می‌تواند از CTA داخلی به صفحهٔ مرتبط برود.

برای استفاده از survey، survey بسازید، سؤال‌ها را اضافه کنید، آن را به مشتری یا scope موردنظر تخصیص دهید و سپس نتایج را از بخش گزارش مشاهده کنید. سؤال چندگزینه‌ای حداقل به دو گزینه نیاز دارد و پاسخ متن آزاد با محدودیت طول سمت سرور اعتبارسنجی می‌شود.

## راهنمای استفادهٔ مشتری

مشتری پس از ورود به `customer/index.php` فقط داده‌های متعلق به شناسهٔ خودش را می‌بیند. امکانات پنل مشتری شامل داشبورد، پروژه‌ها، محصولات، فاکتورها، تیکت‌ها، surveyهای تخصیص‌یافته، اعلان‌ها، پروفایل و Gamification است. عملیات تغییر‌دهنده از فرم‌های داخلی و CSRF محافظت‌شده انجام می‌شوند.

## نمونه پیکربندی nginx

اگر از nginx استفاده می‌کنید، این ruleها را برای هم‌ارز ساختن امنیت Apache اضافه کنید:

```nginx
# مسدودسازی اجرای PHP در پوشه uploads
location ~* /uploads/\.php$ {
    deny all;
    return 403;
}

# مسدودسازی دسترسی به فایل‌های حساس
location ~ /\.(db_config\.php|env.*)$ {
    deny all;
    return 403;
}

# ارسال هدرهای امنیتی
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Permissions-Policy "camera=(), microphone=(), geolocation=()" always;
```

## Backup و Restore

در `admin/settings.php?tab=backups`، فقط `super_admin` مجاز به backup و restore است. Backup می‌تواند شامل داده‌های حساس، دیتابیس و `db_config.php` باشد؛ بنابراین فایل آن را خارج از document root یا در storage امن نگه‌داری کنید و هرگز در repository یا مسیر عمومی قرار ندهید. قبل از restore، عبارت تأیید را دقیق وارد کنید و checksum فایل را بررسی کنید.

## یادآوری survey و Cron

برای اجرای job یادآوری survey از CLI استفاده کنید:

```bash
php cron_survey_reminder.php
```

نمونهٔ cron روزانه در Linux:

```cron
0 9 * * * cd /var/www/portal3 && /usr/bin/php cron_survey_reminder.php >> /var/log/portal3-survey-cron.log 2>&1
```

کلید پیامک باید فقط در environment امن تنظیم شود:

```dotenv
PORTAL_SMS_API_KEY=replace-with-secret
```

## امنیت و استقرار production

HTTPS را فعال کنید، `PORTAL_DEV_MODE` را خاموش نگه دارید، از کاربر محدود دیتابیس استفاده کنید، اجرای script در `uploads/` را مسدود کنید و مجوز نوشتن را فقط به `storage/` و مسیرهای لازم بدهید. فایل‌های credential مانند `db_config.php` و `.env` نباید public یا tracked باشند. پس از هر release، ورود، dashboard، ایجاد یا مشاهدهٔ تیکت، فاکتور و یک مسیر survey را در staging smoke test کنید.

## Assetهای رابط کاربری

برای اجرای release، assetهای زیر کافی هستند و محلی ارائه می‌شوند:

```text
assets/tailwind.css
assets/portal-ui.css
assets/fonts/
```

بازسازی CSS فقط در محیط توسعه انجام می‌شود:

```bash
pnpm install
pnpm run build:css
```

`node_modules/` و فایل ورودی build در release runtime لازم نیستند.

## عیب‌یابی سریع

| پیام یا مشکل | اقدام |
|---|---|
| سامانه در حال ارتقاست | backup بگیرید و `php bin/migrate.php` را اجرا کنید |
| اتصال دیتابیس برقرار نشد | روشن بودن MariaDB/MySQL، مقادیر `db_config.php` و extension `pdo_mysql` را بررسی کنید |
| CSS یا فونت نمایش داده نمی‌شود | وجود `assets/tailwind.css`، `assets/portal-ui.css` و `assets/fonts/` و درست بودن base URL را بررسی کنید |
| Backup ساخته نمی‌شود | فعال بودن `ZipArchive` و قابل‌نوشتن بودن `storage/` را بررسی کنید |
| Composer روی هاست موجود نیست | dependencyهای production را روی سیستم محلی نصب و پوشهٔ `vendor/` را بارگذاری کنید |

## اطلاعات انتشار

نسخهٔ برنامه در فایل `VERSION` نگه‌داری می‌شود. برای انتشار جدید، migration و schema را هماهنگ کنید، تست‌های توسعه را خارج از بستهٔ runtime نگه دارید، backup بگیرید، نسخه را افزایش دهید و SHA-256 ZIP را ثبت کنید.

مخزن رسمی: [pchreza/portal3](https://github.com/pchreza/portal3)
