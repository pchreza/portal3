# پورتال مشتریان Portal3

**نسخهٔ فعلی:** `2.0.3`
**نوع سیستم:** پورتال مشتریان تحت وب با PHP و MariaDB/MySQL
**زبان رابط کاربری:** فارسی و راست‌به‌چپ (RTL)
**مخزن رسمی:** [github.com/pchreza/portal3](https://github.com/pchreza/portal3)

Portal3 یک سامانهٔ وب برای مدیریت ارتباط با مشتریان، پروژه‌ها، محصولات، فاکتورها، تیکت‌های پشتیبانی، نظرسنجی‌ها، اعلان‌ها و اطلاعات تکمیلی مشتریان است. سیستم با PHP خالص، PDO، MariaDB/MySQL و رابط کاربری فارسی RTL ساخته شده و برای اجرا روی Apache، Laragon، XAMPP و هاست‌های معمول PHP قابل استفاده است.

> **نکتهٔ مهم:** این README راهنمای اصلی نصب و استفادهٔ Portal3 است. برای نصب تازه یا ارتقای نسخه، مراحل این فایل را به‌ترتیب انجام دهید و migration را قبل از استفادهٔ عملیاتی اجرا کنید.

## فهرست مطالب

1. [امکانات سیستم](#امکانات-سیستم)
2. [معماری و مسیرهای مهم](#معماری-و-مسیرهای-مهم)
3. [نیازمندی‌های سرور](#نیازمندی‌های-سرور)
4. [نصب سریع روی Laragon یا XAMPP](#نصب-سریع-روی-laragon-یا-xampp)
5. [نصب روی هاست Apache](#نصب-روی-هاست-apache)
6. [نصب مرحله‌به‌مرحله با ویزارد](#نصب-مرحلهبهمرحله-با-ویزارد)
7. [ارتقای نسخهٔ موجود](#ارتقای-نسخهٔ-موجود)
8. [پیکربندی دیتابیس و environment](#پیکربندی-دیتابیس-و-environment)
9. [راهنمای استفادهٔ مدیر](#راهنمای-استفادهٔ-مدیر)
10. [راهنمای استفادهٔ مشتری](#راهنمای-استفادهٔ-مشتری)
11. [یادآوری خودکار نظرسنجی و Cron](#یادآوری-خودکار-نظرسنجی-و-cron)
12. [توسعهٔ assetهای CSS](#توسعهٔ-assetهای-css)
13. [امنیت و استقرار production](#امنیت-و-استقرار-production)
14. [Backup و بازیابی](#backup-و-بازیابی)
15. [تست و بررسی سلامت پروژه](#تست-و-بررسی-سلامت-پروژه)
16. [رفع خطاهای رایج](#رفع-خطاهای-رایج)
17. [ساختار نسخه و انتشار](#ساختار-نسخه-و-انتشار)

## امکانات سیستم

Portal3 امکانات زیر را در اختیار مدیر و مشتری قرار می‌دهد:

| حوزه | امکانات |
| --- | --- |
| ورود و حساب کاربری | ورود با نام کاربری و رمز عبور، ورود با شمارهٔ موبایل و OTP در صورت تنظیم سرویس پیامک، خروج امن و محدودسازی تلاش‌های ناموفق ورود |
| مدیریت مشتریان | ایجاد، ویرایش، جست‌وجو و مدیریت وضعیت مشتریان و اطلاعات تکمیلی آن‌ها |
| پروژه‌ها | ثبت پروژه برای مشتری، وضعیت پروژه، بودجه، مهلت، توضیحات و نمایش اطلاعات در پنل مشتری |
| محصولات | ثبت محصولات یا خدمات خریداری‌شده، قیمت، وضعیت، تاریخ خرید، تصویر و license key |
| فاکتورها | ایجاد و مدیریت فاکتور، مبلغ، وضعیت پرداخت، شمارهٔ فاکتور و تاریخ سررسید |
| پشتیبانی | تیکت مشتری، پاسخ مدیر، وضعیت و اولویت تیکت، دپارتمان‌های پشتیبانی و پیام‌های تیکت |
| نظرسنجی | ایجاد نظرسنجی، تعریف سؤال، تخصیص به مشتری، پاسخ عمومی با لینک امن و گزارش نتایج |
| اعلان‌ها | ایجاد اعلان برای همه یا گروه هدف، دریافت اعلان در پنل مشتری و ثبت وضعیت خوانده‌شدن |
| فیلدهای سفارشی | تعریف فیلدهای تکمیلی برای مشتری، پروژه یا محصول و نمایش آن‌ها در فرم‌های مربوط |
| گزارش و ثبت خطا | ثبت گزارش خطا توسط کاربر از رابط سیستم و مشاهدهٔ آن توسط مدیر مجاز |
| Excel و CSV | ورود و خروجی‌گیری اطلاعات در مسیرهای پشتیبانی‌شده با PhpSpreadsheet |
| رابط کاربری | طراحی فارسی RTL، فونت محلی Vazirmatn، responsive برای موبایل، accessibility پایه و assetهای local بدون وابستگی به CDN |

## معماری و مسیرهای مهم

| مسیر | کاربرد |
| --- | --- |
| `index.php` | صفحهٔ ورود و نقطهٔ ورود عمومی سیستم |
| `admin/` | صفحات پنل مدیر و مدیریت داده‌ها |
| `customer/` | صفحات پنل مشتری |
| `install.php` | ویزارد نصب اولیه |
| `bin/migrate.php` | اجرای دستی و امن migrationها از CLI |
| `migrations.php` | تعریف migrationهای نسخه‌بندی‌شدهٔ دیتابیس |
| `schema.sql` | schema پایه برای نصب تازه |
| `config.php` | bootstrap، session، security headers، PDO و کنترل migration |
| `includes/functions/` | توابع مشترک احراز هویت، تنظیمات، اعلان، نظرسنجی، Excel و فعالیت‌ها |
| `assets/` | CSS، فونت Vazirmatn، تصویرها و assetهای رابط کاربری |
| `storage/` | cache و فایل‌های runtime؛ باید برای PHP قابل نوشتن باشد |
| `uploads/` | فایل‌های upload شده؛ اجرای script در آن با `.htaccess` مسدود شده است |
| `cron_survey_reminder.php` | job یادآوری نظرسنجی و ارسال پیامک در صورت فعال‌بودن تنظیمات |
| `.env.example` | نام متغیرهای environment؛ این فایل خودکار توسط PHP load نمی‌شود |
| `db_config.example.php` | نمونهٔ تنظیم اتصال دیتابیس |

## نیازمندی‌های سرور

برای اجرای نسخهٔ فعلی، سرور باید حداقل شرایط زیر را داشته باشد:

| مورد | مقدار پیشنهادی یا لازم |
| --- | --- |
| PHP | نسخهٔ `8.1` یا بالاتر؛ برای production استفاده از PHP 8.2 یا 8.3 پیشنهاد می‌شود |
| Database | MariaDB 10.11 یا MySQL سازگار با InnoDB و UTF-8/utf8mb4 |
| PHP extensions | `PDO`، `pdo_mysql`، `mbstring`، `xml` و `zip` |
| Web server | Apache با پشتیبانی از `.htaccess`؛ برای nginx باید معادل rules امنیتی تنظیم شود |
| دسترسی فایل | نوشتن برای `db_config.php` در زمان نصب، `storage/` و `uploads/` در زمان اجرا |
| Composer | برای نصب dependencyهای PHP در صورت نبودن `vendor/` |
| Node.js و pnpm | فقط برای بازسازی CSS در محیط توسعه؛ برای اجرای release آماده ضروری نیست |
| HTTPS | برای production الزامی و برای ورود امن و cookieهای امن به‌شدت توصیه می‌شود |

برای بررسی extensionهای PHP در سیستم محلی، اجرا کنید:

```bash
php -m
```

سپس مطمئن شوید نام‌های `PDO`، `pdo_mysql`، `mbstring`، `xml` و `zip` در خروجی وجود دارند.

## نصب سریع روی Laragon یا XAMPP

### ۱. قرار دادن فایل‌های پروژه

فایل ZIP نسخهٔ Portal3 را در مسیر web root استخراج کنید. نمونهٔ مسیرها:

```text
Laragon: C:\laragon\www\portal3
XAMPP:   C:\xampp\htdocs\portal3
```

در Git Bash برای Laragon:

```bash
cd /c/laragon/www
unzip /مسیر/portal3-v2.0.3-clean-20260812.zip
cd portal3
```

اگر ZIP پوشهٔ `portal3/` ایجاد می‌کند، مطمئن شوید ساختار نهایی به‌صورت زیر است و فایل‌ها یک لایه اضافی ندارند:

```text
C:\laragon\www\portal3\index.php
C:\laragon\www\portal3\install.php
C:\laragon\www\portal3\schema.sql
```

### ۲. ساخت دیتابیس

در Laragon می‌توانید از HeidiSQL یا phpMyAdmin استفاده کنید. یک دیتابیس با collation زیر بسازید:

```sql
CREATE DATABASE client_portal
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

اگر کاربر دیتابیس مجوز `CREATE DATABASE` ندارد، دیتابیس را از قبل بسازید و دسترسی کامل آن را به کاربر اختصاص دهید. ویزارد نصب از دیتابیس ازپیش‌ساخته نیز پشتیبانی می‌کند.

### ۳. راه‌اندازی سرویس‌ها

در Laragon، Apache و MySQL/MariaDB را Start کنید. سپس مرورگر را باز کنید:

```text
http://localhost/portal3/install.php
```

در XAMPP نیز Apache و MySQL را Start کرده و همین مسیر را با نام virtual host یا پوشهٔ خود باز کنید.

### ۴. تکمیل ویزارد نصب

اطلاعات اتصال دیتابیس و حساب مدیر ارشد را وارد کنید. پس از پایان نصب، با حساب مدیر وارد شوید و ابتدا تنظیمات پایه، URL سایت و کاربران را بررسی کنید.

## نصب روی هاست Apache

۱. یک backup از فایل‌های فعلی و دیتابیس بگیرید.

۲. ZIP را در document root یا مسیر اختصاصی دامنه استخراج کنید. ساختار باید شبیه این باشد:

```text
public_html/portal3/index.php
public_html/portal3/install.php
public_html/portal3/config.php
```

۳. مطمئن شوید Apache فایل `.htaccess` را می‌خواند و `AllowOverride All` یا تنظیم معادل آن فعال است. اگر هاست از nginx استفاده می‌کند، rules مربوط به deny فایل‌های حساس و جلوگیری از اجرای script در `uploads/` را دستی معادل‌سازی کنید.

۴. در control panel یک دیتابیس و کاربر بسازید و دسترسی کامل کاربر را فقط به همان دیتابیس بدهید.

۵. با مرورگر به مسیر زیر بروید:

```text
https://example.com/portal3/install.php
```

۶. بعد از نصب، دسترسی فایل‌ها را بررسی کنید. `db_config.php` باید خارج از دسترسی عمومی باشد یا حداقل توسط `.htaccess` مسدود شود. این فایل را هرگز در Git commit نکنید.

۷. برای production، `PORTAL_ENV=production`، `PORTAL_DEV_MODE=false` و `PORTAL_AUTO_MIGRATE=false` تنظیم شود و migration فقط از CLI اجرا شود.

## نصب مرحله‌به‌مرحله با ویزارد

ویزارد نصب سه مرحله دارد:

| مرحله | کار |
| --- | --- |
| ۱. اتصال دیتابیس | دریافت host، نام دیتابیس، کاربر و رمز؛ تلاش برای ساخت دیتابیس یا استفاده از دیتابیس ازپیش‌ساخته؛ ایجاد schema پایه و ذخیرهٔ `db_config.php` |
| ۲. حساب مدیر | اجرای migrationهای لازم و ایجاد حساب `super_admin` |
| ۳. پایان نصب | نمایش موفقیت نصب و لینک ورود به سیستم |

در مرحلهٔ اول این مقادیر را وارد کنید:

| فیلد | نمونهٔ Laragon | توضیح |
| --- | --- | --- |
| Database Host | `localhost` | در بعضی محیط‌ها `127.0.0.1` نیز قابل استفاده است |
| Database Name | `client_portal` | فقط حروف انگلیسی، عدد و `_` مجاز است |
| DB User | `root` یا کاربر ساخته‌شده | در production از root استفاده نکنید |
| DB Password | رمز کاربر | در Laragon پیش‌فرض ممکن است خالی باشد؛ تنظیم واقعی خود را بررسی کنید |

در مرحلهٔ دوم یک نام کاربری و رمز قوی برای مدیر ارشد وارد کنید. رمز باید طول مناسب داشته باشد و در فایل، Git، screenshot یا گزارش ذخیره نشود.

پس از نصب موفق، برای امنیت بیشتر:

- URL و تنظیمات عمومی سایت را در پنل مدیر بررسی کنید.
- یک مدیر عملیاتی جدا از حساب آزمایشی بسازید.
- رمز حساب اولیه را در صورت استفادهٔ موقت تغییر دهید.
- سرویس HTTPS را فعال کنید.
- دسترسی به `install.php` را طبق سیاست استقرار خود محدود کنید؛ ویزارد پس از وجود مدیر فعال، اجرای مجدد را مسدود می‌کند.

## ارتقای نسخهٔ موجود

برای ارتقای یک Portal3 موجود، ابتدا از فایل‌ها و دیتابیس backup بگیرید. سپس فایل‌های release جدید را جایگزین کنید و migration را از ریشهٔ پروژه و با CLI اجرا کنید:

```bash
php bin/migrate.php
```

خروجی موفق نسخهٔ فعلی باید شبیه این باشد:

```text
Migration موفق بود؛ schema version=28.
```

در production از اجرای migration از طریق browser یا فعال‌کردن دائمی auto-migration خودداری کنید. اگر نسخهٔ کد جدید باشد و schema قدیمی، برنامه با پیام «سامانه در حال ارتقاست» سرویس‌های web را موقتاً متوقف می‌کند تا مدیر migration را اجرا کند.

Migrationها نسخه‌بندی‌شده و idempotent طراحی شده‌اند؛ migrationهای ثبت‌شده دوباره اجرا نمی‌شوند. با این حال، backup دیتابیس قبل از هر upgrade الزامی است.

### ارتقای نسخهٔ v2.0.3 یا بالاتر از نسخه‌های قبلی

در برخی دیتابیس‌های قدیمی ممکن است رکوردهای orphan وجود داشته باشد. نسخهٔ فعلی پیش از ایجاد foreign keyهای جدید، این موارد را کنترل می‌کند:

- assignment نظرسنجی که survey والد ندارد حذف می‌شود، چون بدون survey قابل استفاده نیست.
- اشارهٔ نامعتبر دپارتمان تیکت، کاربر activity log، کاربر SMS log یا creator اعلان به `NULL` تبدیل می‌شود و رکورد اصلی حفظ می‌گردد.

اگر migration شکست خورد، متن دقیق `php_errors.log` را بررسی کنید و قبل از اجرای query دستی، backup را نگه دارید.

## پیکربندی دیتابیس و environment

### فایل `db_config.php`

در نصب با ویزارد، این فایل به‌صورت خودکار در ریشه ساخته می‌شود. برای نصب دستی، نمونه را کپی کنید:

```bash
cp db_config.example.php db_config.php
```

سپس مقادیر را تنظیم کنید:

```php
<?php
$db_host = 'localhost';
$db_name = 'client_portal';
$db_user = 'portal_user';
$db_pass = 'رمز-دیتابیس';
```

فایل `db_config.php` شامل credential است و نباید در Git، ZIP عمومی، issue، screenshot یا log قرار گیرد.

### متغیرهای environment

فایل `.env.example` فقط فهرست نام متغیرهاست و PHP آن را خودکار load نمی‌کند. مقادیر را در environment سرویس وب و jobهای CLI تنظیم کنید:

```dotenv
PORTAL_ENV=production
PORTAL_DEV_MODE=false
PORTAL_AUTO_MIGRATE=false
PORTAL_TRUST_PROXY=false
PORTAL_SMS_API_KEY=replace-with-secret
```

| متغیر | مقدار production | کاربرد |
| --- | --- | --- |
| `PORTAL_ENV` | `production` | تعیین محیط اجرا |
| `PORTAL_DEV_MODE` | `false` | فعال‌کردن قابلیت‌های توسعه؛ در production خاموش باشد |
| `PORTAL_AUTO_MIGRATE` | `false` | در production migration خودکار در request خاموش باشد |
| `PORTAL_TRUST_PROXY` | فقط در صورت نیاز `true` | اعتماد به `X-Forwarded-Proto` فقط پشت reverse proxy کنترل‌شده |
| `PORTAL_SMS_API_KEY` | secret واقعی | کلید سرویس پیامک؛ در database یا repository ذخیره نشود |

## راهنمای استفادهٔ مدیر

مدیر پس از ورود به مسیر `admin/index.php` داشبورد مدیریت را می‌بیند. دسترسی‌ها بر اساس role و permission کنترل می‌شوند و صرفاً مخفی‌کردن لینک در رابط کاربری معیار امنیتی نیست.

### مشتریان

در بخش مشتریان می‌توانید مشتری جدید بسازید، اطلاعات پروفایل را تکمیل کنید، وضعیت و اطلاعات تماس را بررسی کنید و پروژه‌ها، محصولات، فاکتورها و تیکت‌های مربوط به هر مشتری را مدیریت کنید. برای شمارهٔ موبایل، قالب معتبر و یکتا استفاده کنید؛ ورود OTP به شمارهٔ ثبت‌شده وابسته است.

### پروژه‌ها و محصولات

برای هر پروژه یا محصول، مشتری مربوط، عنوان، توضیحات، وضعیت، مبلغ یا بودجه، تاریخ و تصویر را وارد کنید. داده‌های مالی و تاریخ‌ها را با قالب قابل‌فهم و یکنواخت ذخیره کنید. تغییر وضعیت‌ها را از طریق فرم مدیریت انجام دهید تا ارتباط آن‌ها با داشبورد مشتری حفظ شود.

### فاکتورها

برای ایجاد فاکتور، مشتری، شمارهٔ یکتا، عنوان، مبلغ، وضعیت پرداخت و سررسید را وارد کنید. شمارهٔ فاکتور باید یکتا باشد و قبل از import گروهی، فایل Excel را با دادهٔ آزمایشی بررسی کنید.

### تیکت و دپارتمان

ابتدا دپارتمان‌های پشتیبانی را در `ticket-departments.php` تعریف کنید. سپس تیکت‌ها را بر اساس وضعیت، اولویت و دپارتمان مدیریت کنید. پاسخ مدیر در همان زنجیرهٔ پیام ثبت می‌شود و مشتری آن را در پنل خود می‌بیند.

### نظرسنجی

فرآیند معمول نظرسنجی به این صورت است:

۱. در بخش نظرسنجی، survey جدید بسازید.

۲. سؤال‌ها را در بخش سؤال‌های همان survey اضافه کنید.

۳. survey را به مشتری یا scope موردنظر تخصیص دهید.

۴. وضعیت فعال‌بودن و تنظیمات periodic/reminder را بررسی کنید.

۵. نتایج را در بخش survey results مشاهده کنید.

برای پاسخ عمومی، از لینک تولیدشده توسط سیستم استفاده کنید و لینک را در کانال امن برای گیرنده ارسال کنید. token عمومی را در log یا پیام عمومی غیرضروری قرار ندهید.

### فیلدهای سفارشی

در بخش Custom Fields، target را یکی از customer، project یا product انتخاب کنید؛ سپس نام سیستمی، label، نوع فیلد و وضعیت نمایش را تعیین کنید. نام سیستمی باید پایدار و یکتا باشد، زیرا در ذخیره و نمایش مقادیر استفاده می‌شود.

### اعلان‌ها و گزارش خطا

مدیر می‌تواند اعلان عمومی یا هدفمند ایجاد کند. گزارش‌های خطای ثبت‌شده توسط کاربران در بخش Error Reports دیده می‌شوند. جزئیات فنی حساس نباید در متن قابل‌نمایش کاربر قرار گیرد؛ log سرور برای بررسی فنی استفاده می‌شود.

## راهنمای استفادهٔ مشتری

مشتری پس از ورود به `customer/index.php` به امکانات مرتبط با خودش دسترسی دارد:

| بخش | کاربرد |
| --- | --- |
| داشبورد | مشاهدهٔ خلاصهٔ پروژه‌ها، محصولات، فاکتورها، اعلان‌ها و وضعیت کلی |
| پروفایل | تکمیل نام، نام خانوادگی، موبایل، شرکت و اطلاعات تکمیلی |
| پروژه‌ها | مشاهدهٔ پروژه‌ها و جزئیات قابل‌نمایش |
| محصولات | مشاهدهٔ محصولات یا خدمات خریداری‌شده |
| فاکتورها | مشاهدهٔ مبلغ، وضعیت پرداخت و جزئیات فاکتور |
| تیکت‌ها | ثبت درخواست پشتیبانی، مشاهدهٔ پاسخ و ادامهٔ گفتگو |
| نظرسنجی‌ها | مشاهده و پاسخ به surveyهای تخصیص‌یافته |
| اعلان‌ها | مشاهدهٔ اعلان‌های دریافتی و علامت‌گذاری به‌عنوان خوانده‌شده |

مشتری فقط داده‌هایی را می‌بیند که براساس شناسهٔ کاربری و permission برای او مجاز است. تغییرات state-changing از فرم‌های داخلی و با CSRF انجام می‌شوند.

## یادآوری خودکار نظرسنجی و Cron

فایل `cron_survey_reminder.php` برای اجرای دوره‌ای یادآوری نظرسنجی طراحی شده است. این فایل باید از CLI و با همان environment و credential دیتابیس اجرای وب اجرا شود.

نمونهٔ اجرای دستی:

```bash
php cron_survey_reminder.php
```

در Linux، نمونهٔ cron روزانه در ساعت ۹ صبح:

```cron
0 9 * * * cd /var/www/portal3 && /usr/bin/php cron_survey_reminder.php >> /var/log/portal3-survey-cron.log 2>&1
```

در Windows، از Task Scheduler یک task بسازید که program آن `php.exe` و argument آن مسیر کامل `cron_survey_reminder.php` باشد؛ working directory را ریشهٔ پروژه قرار دهید.

برای ارسال پیامک، `PORTAL_SMS_API_KEY` را در environment job نیز تنظیم کنید. اگر کلید تنظیم نشده باشد یا reminder غیرفعال باشد، job نباید پیامک واقعی ارسال کند. مقدار secret را داخل command line، فایل عمومی یا repository ننویسید.

تنظیمات مربوط به روز تأخیر، فاصلهٔ یادآوری و حداکثر تعداد یادآوری در بخش settings قابل مدیریت است. job را بدون فاصلهٔ مناسب چند بار پشت‌سرهم اجرا نکنید.

## توسعهٔ assetهای CSS

برای اجرای عادی release، فایل‌های local زیر کافی هستند:

```text
assets/tailwind.css
assets/portal-ui.css
assets/fonts/
```

برای بازسازی CSS در محیط توسعه:

```bash
pnpm install
pnpm run build:css
```

`node_modules/` را در release قرار ندهید. بعد از build، تغییرات `assets/tailwind.css` را review کنید و lint یا smoke test صفحات اصلی را اجرا کنید.

## امنیت و استقرار production

برای استقرار production این موارد را رعایت کنید:

۱. از PHP و MariaDB/MySQL به‌روز و پشتیبانی‌شده استفاده کنید.

۲. HTTPS معتبر فعال کنید و سیستم را پشت TLS اجرا کنید.

۳. `PORTAL_DEV_MODE=false` و `PORTAL_AUTO_MIGRATE=false` بماند.

۴. migration فقط با `php bin/migrate.php` و در maintenance window اجرا شود.

۵. `db_config.php`، `.env`، logها، cache، فایل‌های test و ابزارهای توسعه از دسترسی عمومی خارج باشند.

۶. دسترسی فایل‌ها را حداقلی تنظیم کنید. فقط `storage/` و مسیرهای لازم upload باید writeable باشند.

۷. اجرای script در `uploads/` را مسدود نگه دارید و فایل upload را با نام امن مدیریت کنید.

۸. کلید `PORTAL_SMS_API_KEY` را در secret manager یا environment امن نگه دارید و دوره‌ای rotate کنید.

۹. کاربر دیتابیس production را با دسترسی محدود به schema Portal3 بسازید و از root استفاده نکنید.

۱۰. بعد از هر release، login، dashboard، ایجاد تیکت، مشاهدهٔ فاکتور و یک مسیر نظرسنجی را smoke test کنید.

۱۱. logهای PHP و web server را مانیتور کنید، اما credential، session token و دادهٔ شخصی را در گزارش عمومی منتشر نکنید.

۱۲. `.htaccess` و تنظیمات deny فایل‌های حساس را بررسی کنید. اگر از nginx استفاده می‌کنید، این سیاست‌ها را در server block معادل‌سازی کنید.

## Backup و بازیابی

قبل از نصب، upgrade یا migration، دو backup جدا تهیه کنید:

### Backup دیتابیس

نمونهٔ MariaDB/MySQL:

```bash
mysqldump --single-transaction --routines --triggers \
  -u portal_user -p client_portal > portal3-$(date +%F-%H%M).sql
```

در Windows می‌توانید همین کار را با `mysqldump.exe` از پوشهٔ نصب MariaDB یا Laragon انجام دهید.

### Backup فایل‌ها

Linux:

```bash
tar -czf portal3-files-$(date +%F-%H%M).tar.gz /var/www/portal3
```

Windows: کل پوشهٔ `C:\laragon\www\portal3` را در یک مسیر backup نسخه‌دار کپی یا zip کنید. فایل `db_config.php` و uploadها را نیز طبق سیاست محرمانگی نگه‌داری کنید.

### بازیابی

برای rollback، ابتدا سرویس وب را به نسخهٔ قبلی فایل‌ها برگردانید و سپس snapshot دیتابیس سازگار با همان نسخه را restore کنید. migrationها را دستی حذف یا معکوس نکنید؛ schema و code باید با هم سازگار باشند.

## تست و بررسی سلامت پروژه

در محیط توسعه، dependencyها را نصب کنید:

```bash
composer install
```

سپس تست‌های پروژه را اجرا کنید:

```bash
composer validate --no-check-publish
composer test
composer analyse
composer lint
git diff --check
```

برای اجرای lint فقط روی فایل‌های PHP، دستور زیر نیز قابل استفاده است:

```bash
find . -path './vendor' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
```

تست migration روی دیتابیس واقعی باید بعد از backup و در maintenance window انجام شود. برای بررسی نسخهٔ schema:

```sql
SELECT COALESCE(MAX(version), 0) AS schema_version FROM schema_versions;
```

در نسخهٔ `2.0.3` مقدار مورد انتظار `28` است.

## رفع خطاهای رایج

### پیام «سامانه در حال ارتقاست»

این پیام یعنی نسخهٔ کد از schema دیتابیس جلوتر است. از ریشهٔ پروژه اجرا کنید:

```bash
php bin/migrate.php
```

اگر پیام عمومی باقی ماند، لاگ PHP را بررسی کنید. در production migration را از browser اجرا نکنید.

### خطای MariaDB 1093 در `surveys`

این خطا مربوط به نسخه‌های قدیمی migration در حذف surveyهای orphan بود. از آخرین release Portal3 استفاده کنید و migration را پس از backup دوباره اجرا کنید. نسخهٔ فعلی query سازگار با MariaDB را استفاده می‌کند.

### خطای SQLSTATE 1452 برای `fk_sa_survey`

این خطا یعنی در `survey_assignments` رکوردی وجود دارد که `survey_id` آن در `surveys` وجود ندارد. نسخهٔ فعلی پیش از ایجاد FK این orphanها را repair می‌کند. فایل‌ها را کامل با release جدید جایگزین کنید و سپس اجرا کنید:

```bash
php bin/migrate.php
```

اگر خطا تکرار شد، متن کامل log را ارسال کنید و قبل از حذف دستی داده‌ها backup بگیرید.

### خطای «اتصال به دیتابیس برقرار نشد»

این موارد را بررسی کنید:

- سرویس MariaDB/MySQL روشن باشد.
- host، نام دیتابیس، user و password در `db_config.php` صحیح باشد.
- کاربر به دیتابیس دسترسی داشته باشد.
- extension `pdo_mysql` فعال باشد.
- دیتابیس و کاربر از نظر collation و encoding سازگار باشند.

### خطای permission برای `db_config.php` یا `storage`

PHP باید بتواند در زمان نصب `db_config.php` را ایجاد کند و در زمان اجرا cache/runtime را بنویسد. مالکیت و permission را فقط به‌اندازهٔ لازم اصلاح کنید و کل document root را writeable نکنید.

### خطای Composer یا PhpSpreadsheet

از ریشهٔ پروژه اجرا کنید:

```bash
composer install --no-dev --optimize-autoloader
```

سپس extensionهای `zip`، `xml` و `mbstring` را فعال کنید. اگر هاست Composer ندارد، dependencyهای production را در فرآیند deployment provision کنید؛ `vendor/` عمداً در ZIP clean runtime-only قرار نمی‌گیرد.

### CSS یا فونت نمایش داده نمی‌شود

وجود این فایل‌ها را بررسی کنید:

```text
assets/tailwind.css
assets/portal-ui.css
assets/fonts/vazirmatn.css
assets/fonts/Vazirmatn-v33.003-wght.woff2
```

همچنین مسیر base URL و cache مرورگر را بررسی کنید. فایل‌های asset را از CDN جایگزین نکنید مگر اینکه سیاست امنیتی پروژه تغییر کند.

### ورود OTP پیامک ارسال نمی‌شود

وجود و درست‌بودن `PORTAL_SMS_API_KEY` در environment سرویس وب و cron را بررسی کنید. همچنین شمارهٔ موبایل باید در حساب کاربر ثبت و یکتا باشد. مقدار کلید را در log یا پیام خطا چاپ نکنید.

## ساختار نسخه و انتشار

نسخهٔ پروژه در فایل `VERSION` نگه‌داری می‌شود. برای هر release این موارد را انجام دهید:

۱. تغییرات را در branch قابل review ثبت کنید.

۲. تست‌های Composer، PHPStan و lint را اجرا کنید.

۳. migration را روی fixture ایزوله و دیتابیس staging بررسی کنید.

۴. `VERSION` و release notes را به‌روزرسانی کنید.

۵. ZIP runtime-only را بدون `tests/`، `vendor/`، `.git/` و credentialهای محلی بسازید.

۶. SHA-256 فایل ZIP را منتشر کنید.

۷. قبل از deployment، backup فایل و دیتابیس بگیرید.

۸. tag نسخه را در Git ثبت کنید و بعد از انتشار smoke test انجام دهید.

## وضعیت و مسئولیت نگه‌داری

Portal3 یک برنامهٔ PHP خالص است و مسئول نگه‌داری production فقط شامل کد نیست؛ وب‌سرور، PHP، database، TLS، backup، secretها، cron و permissionها نیز باید به‌صورت دوره‌ای بررسی شوند. هیچ releaseای جایگزین backup معتبر، بررسی log و تست روی staging نمی‌شود.

برای گزارش خطا، شمارهٔ نسخه، commit یا tag، محیط اجرا، نسخهٔ PHP/MariaDB، دستور اجراشده و متن دقیق log را ارسال کنید. credential، رمز عبور، API key، session token و دادهٔ شخصی را ارسال نکنید.

## منابع رسمی

- [PHP Manual](https://www.php.net/manual/en/)
- [Composer Documentation](https://getcomposer.org/doc/)
- [MariaDB Documentation](https://mariadb.com/kb/en/documentation/)
- [MySQL Documentation](https://dev.mysql.com/doc/)
- [PHPUnit Documentation](https://docs.phpunit.de/)
- [PHPStan Documentation](https://phpstan.org/user-guide/getting-started)
