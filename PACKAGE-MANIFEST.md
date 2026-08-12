# Runtime Package Manifest — Portal3 v2.1.1

این archive فقط فایل‌های لازم برای اجرای Portal3، نصب، migration، مستندات و قابلیت Backup/Restore را شامل می‌شود.

## Included

`admin/`، `customer/`، `includes/`، `assets/`، `bin/`، فایل‌های PHP ریشه، `config.php`، `install.php`، `schema.sql`، `migrations.php`، `composer.json`، `composer.lock`، `.env.example`، `README.md`، `INSTALL.md`، `VERSION`، `storage/.htaccess`، `storage/cache/.htaccess`، `storage/backups/.htaccess` و `uploads/.htaccess`.

## Backup/Restore files

کتابخانهٔ `includes/functions/backup.php` ساخت، فهرست، download، validation و restore archiveهای کامل Portal3 را فراهم می‌کند. archive واقعی backup داخل بستهٔ release قرار نمی‌گیرد. `storage/backups/.htaccess` دسترسی مستقیم وب به backupها را مسدود می‌کند.

## Excluded intentionally

`.git/`، `vendor/`، `node_modules/`، `tests/`، `.github/`، PHPUnit، PHPStan، فایل‌های QA و گزارش‌های توسعه، cache، uploadهای محیط توسعه، archiveهای backup، `db_config.php`، فایل‌های موقت و source build مربوط به Tailwind.

## Deployment note

اگر هاست Composer ندارد، dependencyهای production را روی یک کامپیوتر دیگر با `composer install --no-dev --prefer-dist --optimize-autoloader` نصب کنید و پوشهٔ کامل `vendor/` را جداگانه روی هاست آپلود کنید. `db_config.php` باید روی همان محیط مقصد ساخته یا تنظیم شود و نباید از محیط توسعه کپی عمومی شود.
