# راهنمای نصب و ارتقای Portal3 v2.2.1

## نیازمندی‌ها

Portal3 با PHP خالص و بدون فریم‌ورک PHP اجرا می‌شود. روی سرور به PHP 8.1 یا بالاتر، MariaDB/MySQL، افزونه‌های `pdo_mysql`، `mbstring`، `xml` و `zip` و وب‌سرور Apache یا nginx نیاز دارد. برای قابلیت Backup/Restore فعال‌بودن `ZipArchive` الزامی است.

## نصب تازه

۱. فایل ZIP را در document root استخراج کنید؛ برای Laragon نمونهٔ مسیر `C:\laragon\www\portal3` است.

۲. یک دیتابیس utf8mb4 و یک کاربر محدود برای همان دیتابیس بسازید.

۳. اگر هاست Composer ندارد، روی کامپیوتر خود از ریشهٔ پروژه اجرا کنید:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
```

سپس پوشهٔ کامل `vendor/` را همراه پروژه آپلود کنید. روی هاست بدون Composer دیگر نیازی به اجرای Composer نیست.

۴. در مرورگر به `install.php` بروید و مشخصات دیتابیس و حساب مدیر ارشد را وارد کنید.

۵. پوشه‌های `storage/`، `storage/backups/` و `uploads/` باید برای PHP قابل نوشتن باشند. اجرای script در `uploads/` و دسترسی مستقیم به `storage/backups/` توسط rules امنیتی مسدود شده است.

۶. برای production، `PORTAL_DEV_MODE=false` و `PORTAL_AUTO_MIGRATE=false` باشد. پس از نصب یا upgrade، migration را فقط از CLI اجرا کنید:

```bash
php bin/migrate.php
```

## ارتقا

قبل از upgrade از دیتابیس و فایل‌ها backup بگیرید، فایل‌های release جدید را جایگزین کنید و سپس این دستور را اجرا کنید:

```bash
php bin/migrate.php
```

اجرای موفق باید schema را به نسخهٔ `29` برساند. migration را از browser یا با فعال‌کردن auto-migrate دائمی اجرا نکنید.

## راه‌اندازی Gamification

۱. پس از اجرای migration، با حساب `super_admin` وارد `admin/settings.php` شوید و در تب «ماژول‌ها» گزینهٔ «باشگاه امتیاز و پاداش» را فعال کنید.

۲. از منوی «باشگاه امتیاز و پاداش» ruleهای امتیازدهی را تنظیم کنید. ruleها به‌صورت پیش‌فرض خاموش هستند؛ برای هر رویداد امتیاز، سقف روزانه و cooldown تعیین کنید.

۳. در بخش «سایت‌های فروش» نام و URL امن HTTPS مقصد را ثبت کنید. نسخهٔ فعلی از provider `manual_redirect` استفاده می‌کند و API فروشگاه را صدا نمی‌زند.

۴. در بخش «ساخت پاداش» هزینهٔ امتیاز، مدت اعتبار و سقف هر مشتری را تعیین کنید. برای استفادهٔ امن، حالت «مخزن کدهای یکتا» را انتخاب و کدها را هر خط یک کد وارد کنید.

۵. برای نمایشگاه‌ها یا کمپین‌ها در بخش «کمپین کد هدیه» یک کد امتیازی بسازید. کد خام در دیتابیس ذخیره نمی‌شود؛ آن را در محل امن نگه دارید.

۶. مشتری پس از فعال‌شدن ماژول از صفحهٔ «امتیازها و پاداش‌ها» کد هدیه را وارد می‌کند، موجودی را می‌بیند و با تأیید تبدیل، کد تخفیف و لینک سایت فروش را دریافت می‌کند.

برای خاموش‌کردن قابلیت، toggle ماژول را غیرفعال کنید؛ wallet، ledger، campaign و redemption حذف نمی‌شوند. قبل از تغییر rule یا migration از Backup/Restore مدیر ارشد backup بگیرید.

## Backup و Restore مدیریتی

پس از ورود با حساب `super_admin` به `admin/settings.php?tab=backups` بروید. دکمهٔ «ساخت backup جدید» یک archive شامل dump دیتابیس، فایل‌های پروژه، assetها و uploadها ایجاد می‌کند. cache، Git و backupهای قبلی عمداً داخل archive قرار نمی‌گیرند.

برای restore، یک archive تولیدشده توسط Portal3 را انتخاب کنید، عبارت `RESTORE` را وارد کنید و عملیات را تأیید کنید. سیستم پیش از جایگزینی دیتابیس و فایل‌ها، از وضعیت فعلی یک pre-restore backup خودکار می‌سازد. backupها شامل داده‌های شخصی و ممکن است شامل `db_config.php` باشند؛ آن‌ها را خارج از document root یا در فضای رمزگذاری‌شده نگه‌داری کنید.

سقف پیش‌فرض archive برابر ۵۱۲ مگابایت است و با متغیر `PORTAL_BACKUP_MAX_BYTES` قابل تنظیم است. ساخت، حذف و restore در audit log ثبت می‌شود و فقط `super_admin` به این عملیات دسترسی دارد.

## environment و امنیت

مقادیر `.env.example` را در environment وب‌سرور و cron تنظیم کنید؛ مخصوصاً `PORTAL_SMS_API_KEY`. کلیدها و فایل `db_config.php` را در Git، ZIP عمومی، log یا screenshot قرار ندهید. HTTPS، backup منظم، permission حداقلی و کاربر محدود دیتابیس برای production الزامی است.

این بسته عمداً شامل `vendor/`، `node_modules/`، تست‌ها، CI، QA، cache، upload محیط توسعه، Git، archiveهای backup و `db_config.php` نیست.
