# نصب portal3 نسخه 2.0.3

1. در ریشهٔ پروژه اجرا کنید: `composer install --no-dev`.
2. برای نصب تازه، به `install.php` بروید و مشخصات دیتابیس و حساب مدیر را وارد کنید.
3. متغیرهای محیطی `.env.example` را در سرویس وب و cron تنظیم کنید؛ مخصوصاً `PORTAL_SMS_API_KEY`.
4. در production مقدار `PORTAL_DEV_MODE=false` و `PORTAL_AUTO_MIGRATE=false` باشد.
5. برای upgrade، ابتدا backup بگیرید و سپس `php bin/migrate.php` را اجرا کنید.
6. وب‌سرور باید HTTPS، اجرای PHP و دسترسی نوشتن به `storage/` و `uploads/` را فراهم کند.

این بسته عمداً شامل `vendor/`، `node_modules/`، تست‌ها، CI، فایل‌های QA، cache، upload محیط توسعه، Git و `db_config.php` نیست.
