# گزارش دیباگ و ممیزی کامل پروژه portal3

**نسخهٔ بررسی:** Round 4 — Full PHP Debug Audit

**مخزن:** `pchreza/portal3`

**تاریخ شروع:** 2026-08-12

## روش بررسی

هر مورد فقط پس از بازتولید یا شواهد ایستا/اجرایی ثبت می‌شود. برای هر ایراد، علت ریشه‌ای، اصلاح کم‌ریسک، آزمون regression و وضعیت نهایی درج خواهد شد.

| شناسه | بخش | شدت | وضعیت | علت ریشه‌ای | اصلاح | آزمون |
|---|---|---:|---|---|---|---|

## بررسی‌های عمومی

این بخش پس از اجرای syntax، dependency، smoke test و سناریوهای authenticated تکمیل می‌شود.

## محدودیت‌ها و تصمیم‌های امنیتی

فایل `db_config.php` شامل credentials محیط محلی است و در بستهٔ تحویلی یا commit عمومی قرار نخواهد گرفت. ZIP نهایی باید بدون `.git`، `vendor` غیرقابل‌اعتماد، cookie، session و فایل‌های موقت ساخته شود و نصب آن از طریق `install.php` و `composer install` انجام‌پذیر باشد.

## مرحلهٔ ایستا — 2026-08-12

| آزمون | نتیجه |
|---|---|
| `php -l` برای تمام فایل‌های PHP خارج از `vendor` | PASS؛ بدون خطای syntax |
| `composer validate --no-check-publish` | PASS؛ فقط هشدار metadata دربارهٔ license در `composer.json` |
| extensionهای لازم | PHP، PDO، PDO MySQL، mbstring، XML و Zip در محیط توسعه شناسایی شدند. |
| TODO/FIXME و debug output | مورد کاربردی `var_dump`/`print_r`/`TODO` پیدا نشد؛ موارد `die` در مسیرهای کنترل‌شدهٔ خطا یا cron هستند. |
| شاخص‌های SQL خام | موارد پیدا‌شده برای بررسی دستی ذخیره شد؛ queryهای ثابت یا با پارامترهای bind شده از queryهای کاربری جدا می‌شوند. |

**نتیجه:** پروژه از نظر syntax قابل اجراست. هشدار license در Composer در مرحلهٔ بسته‌بندی بررسی می‌شود؛ افزودن license به metadata به‌عنوان اصلاح کم‌ریسک فقط در صورت عدم مغایرت با تصمیم مالک پروژه انجام خواهد شد.

## مرحلهٔ سلامت دیتابیس و migration — 2026-08-12

اسکریپت تشخیصی موقت، بدون mutation، روی دیتابیس fixture اجرا شد و خروجی کامل در `db-health-report.json` ذخیره شد.

| شاخص | نتیجه |
|---|---:|
| جدول‌های ضروری مفقود | 0 |
| `schema_versions` آخرین نسخه | 24 |
| شماره فاکتور تکراری | 0 |
| موبایل غیرخالی تکراری | 0 |
| پروژه/محصول/فاکتور orphan | 0 |
| تیکت و پیام تیکت orphan | 0 |
| assignment نظرسنجی orphan | 0 |
| گیرنده اعلان orphan | 0 |
| index یکتای موبایل | موجود |
| index یکتای شماره فاکتور | موجود |
| index دپارتمان تیکت و activity user | موجود |

**نتیجه:** در دادهٔ fixture، defect مربوط به integrity، orphan record، duplicate کلیدی یا migration ناقص بازتولید نشد.

## مرحلهٔ HTTP smoke — 2026-08-12

| مسیر | نتیجه |
|---|---|
| `/index.php` | HTTP 200؛ بدون marker خطای PHP |
| `/install.php` | HTTP 200؛ بدون marker خطای PHP |
| `/survey-public.php?a=invalid-token` | HTTP 200؛ پیام خطای کنترل‌شده و بدون marker خطای PHP |
| `/admin/index.php` بدون session | HTTP 302 به `../index.php` |
| `/customer/index.php` بدون session | HTTP 302 به `../index.php` |

Smoke report کامل در `http-smoke-report.txt` ذخیره شد.

## مرحلهٔ authenticated smoke — 2026-08-12

تمام صفحات اصلی مدیر و مشتری با fixture و حساب‌های آزمایشی بررسی شدند. مسیرهای پنل مدیر و مشتری HTTP 200 و بدون markerهای `Fatal error`، `Parse error`، `Warning` یا `Notice` پاسخ دادند. صفحات CRUD مدیریتی که بدون query لازم باز شدند، به‌صورت مورد انتظار به مسیر مناسب redirect شدند:

| مسیر | نتیجه |
|---|---|
| `admin/index.php`, `customers.php`, `projects.php`, `products.php`, `invoices.php`, `tickets.php`, `notifications.php`, `surveys.php`, `custom_fields.php`, `ticket-departments.php`, `settings.php`, `admins.php`, `profile.php`, `logs.php`, `error-reports.php` | HTTP 200، clean |
| `admin/survey-create.php` بدون action لازم | HTTP 302، clean و مورد انتظار |
| `admin/survey-questions.php?survey_id=1` | HTTP 302، clean و مورد انتظار برای query نامعتبر/ناقص fixture |
| `admin/survey-results.php?survey_id=1` | HTTP 302، clean و مورد انتظار برای query نامعتبر/ناقص fixture |
| تمام صفحات اصلی `customer/` | HTTP 200، clean |

خروجی کامل در `authenticated-smoke-report.txt` ذخیره شد.

## مرحلهٔ internal route crawl — 2026-08-12

در اجرای اول crawler به‌دلیل bug در ابزار تشخیص، لینک‌های نسبی را نسبت به ریشهٔ سایت resolve می‌کرد و 404های کاذب گزارش شد. علت در اپلیکیشن نبود؛ resolution به `urljoin(current_url, href)` اصلاح و آزمون تکرار شد.

| نقش | صفحات بررسی‌شده | non-200 واقعی | marker خطای PHP | exception |
|---|---:|---:|---:|---:|
| مدیر | 50 | 0 | 0 | 0 |
| مشتری | 14 | 0 | 0 | 0 |

**نتیجه:** پس از اصلاح ابزار، مسیرهای داخلی کشف‌شده در پنل‌های مدیر و مشتری بدون صفحهٔ شکسته یا marker خطای PHP پاسخ دادند. خروجی کامل در `route-check-report.json` ذخیره شد.

## BUG-001 — installer با کاربر دیتابیس بدون مجوز CREATE DATABASE

| فیلد | شرح |
|---|---|
| شدت | متوسط؛ نصب روی shared hosting یا دیتابیس ازپیش‌ساخته شکست می‌خورد |
| بازتولید | اجرای `install.php?step=1` با DB user دارای دسترسی روی یک schema موجود، اما بدون مجوز global برای `CREATE DATABASE` |
| علت ریشه‌ای | installer شکست `CREATE DATABASE IF NOT EXISTS` را بدون fallback مدیریت می‌کرد و پیش از تکمیل schema، `db_config.php` را ذخیره می‌کرد. |
| اصلاح | خطای CREATE اکنون فقط در صورت موفقیت `USE` دیتابیس ازپیش‌ساخته قابل ادامه است؛ در غیر این صورت پیام امن و actionable نمایش داده می‌شود. ذخیرهٔ `db_config.php` به بعد از تکمیل schema و default settings منتقل شد و با `LOCK_EX` و بررسی نتیجه انجام می‌شود. |
| آزمون | PASS: `INSTALL_NO_CREATE=PASS` برای خطای کنترل‌شده و بدون فایل config نیمه‌کاره؛ `INSTALL_EXISTING_DB=PASS` و `INSTALL_E2E=PASS` برای ساخت schema، اجرای migration و ایجاد super_admin. |
| وضعیت | رفع شد و regression test ثبت شد. |

### نتیجهٔ مرحلهٔ نصب

تست installer در دیتابیس ایزولهٔ موقت انجام شد و پس از cleanup، دیتابیس و فایل‌های temporary حذف شدند. مسیر نصب با کاربر محدود و دیتابیس ازپیش‌ساخته اکنون کار می‌کند؛ خطای دسترسی نیز دیگر raw SQL message را به کاربر نهایی نمایش نمی‌دهد.

## BUG-002 — پیام‌های exception خام در فرم‌های کاربری و مدیریتی

| فیلد | شرح |
|---|---|
| شدت | متوسط؛ امکان افشای جزئیات schema/SQL و تجربهٔ خطای ضعیف |
| محل | ثبت/ویرایش مشتری، به‌روزرسانی profile مشتری و ثبت پاسخ نظرسنجی |
| علت ریشه‌ای | الحاق مستقیم `Exception::getMessage()` به پیام قابل‌نمایش در UI |
| اصلاح | علت فنی با context در `error_log` ثبت می‌شود؛ UI فقط پیام فارسی امن نمایش می‌دهد. duplicate key برای مشتری و profile با پیام اختصاصی تشخیص داده می‌شود. |
| آزمون | PASS: ارسال نام کاربری تکراری مشتری پیام امن دریافت کرد و `SQLSTATE` در response وجود نداشت. |
| وضعیت | رفع شد. |

## BUG-003 — اعتبارسنجی و semantics ناکامل custom fields

| فیلد | شرح |
|---|---|
| شدت | متوسط؛ نام سیستمی نامعتبر می‌توانست بعد از slugify به نام خالی/غیرمعنادار تبدیل شود و label به input متصل نبود. |
| علت ریشه‌ای | اعتبارسنجی فقط قبل از slugify انجام می‌شد؛ uniqueness در سطح UI بررسی نمی‌شد؛ renderer `for`/`id` تولید نمی‌کرد. |
| اصلاح | slug با trim امن انجام می‌شود، مقدار خالی یا طول بیش از 100 رد می‌شود و duplicate در همان `target_entity` بررسی می‌گردد. renderer اکنون `id` پایدار، `label for` و جهت LTR برای number/date دارد و typeهای خارج از whitelist را text در نظر می‌گیرد. |
| آزمون | PASS: POST نام `@@@` بدون ایجاد داده با پیام validation رد شد. |
| وضعیت | رفع شد. |

## Regression POST — 2026-08-12

| سناریو | نتیجه |
|---|---|
| نام سیستمی custom field نامعتبر | PASS |
| نام کاربری مشتری تکراری و عدم نمایش SQLSTATE | PASS |
| CSRF نامعتبر در profile مشتری | PASS؛ پاسخ قراردادی HTTP 419 و پیام کنترل‌شده |

خروجی machine-readable در `post-regression-report.json` ذخیره شد.

## BUG-004 — warning در اجرای CLI cron

| فیلد | شرح |
|---|---|
| شدت | پایین تا متوسط؛ cron سالم اجرا می‌شد اما با warning آلوده می‌شد و می‌توانست monitoring را دچار false alarm کند. |
| بازتولید | اجرای `php cron_survey_reminder.php` |
| علت ریشه‌ای | bootstrap در `config.php` مستقیماً به `$_SERVER['REQUEST_METHOD']` دسترسی داشت؛ این کلید در SAPI نوع CLI تعریف نشده است. |
| اصلاح | شرط پردازش گزارش خطای global از `($_SERVER['REQUEST_METHOD'] ?? 'GET')` استفاده می‌کند. |
| آزمون | PASS: اجرای cron با survey reminder غیرفعال، خروجی clean و `CRON_CLI=PASS`؛ تعداد حذف‌ها و پیام‌ها صفر بود. |
| وضعیت | رفع شد. |

## BUG-005 — فایل پیکربندی محلی دیتابیس همچنان track شده بود

| فیلد | شرح |
|---|---|
| شدت | بالا از نظر انتشار؛ فایل محلی credential/connection نباید جزو source distribution یا historyهای جدید باشد. |
| بازتولید | `git ls-files db_config.php` با وجود الگوی ignore موجود، فایل را tracked نشان می‌داد. |
| علت ریشه‌ای | فایل پیش‌تر وارد index شده بود؛ `.gitignore` فقط از فایل‌های untracked جلوگیری می‌کند و روی فایل already-tracked اثر ندارد. |
| اصلاح | `db_config.php` با `git rm --cached` از index حذف شد، اما نسخهٔ local محیط توسعه حفظ شد. الگوی ignore موجود باقی ماند. |
| آزمون | PASS: فایل local همچنان موجود است و `db_config.php` در commit و بستهٔ نصب قرار نمی‌گیرد. |
| وضعیت | رفع شد. |

## جمع‌بندی رگرسیون پیش از بسته‌بندی

| حوزهٔ آزمون | نتیجهٔ نهایی |
|---|---|
| PHP lint تمام فایل‌ها خارج از `vendor` | PASS؛ بدون خطای syntax |
| `git diff --check` | PASS؛ بدون whitespace error |
| سلامت schema و دادهٔ fixture | PASS؛ schema version 24، orphan و duplicate کلیدی صفر |
| HTTP public و access control بدون session | PASS |
| crawl داخلی | PASS؛ 50 مسیر مدیر و 14 مسیر مشتری، بدون non-200 واقعی یا marker خطای PHP |
| authenticated smoke نهایی | PASS؛ 26 مسیر اصلی مدیر/مشتری پس از اصلاحات Round 4 |
| installer E2E | PASS؛ سناریوی دیتابیس ازپیش‌ساخته و خطای مجوز CREATE DATABASE |
| POST regression | PASS؛ custom field نامعتبر، duplicate customer و CSRF profile |
| Excel | PASS؛ download XLSX (ساختار ZIP معتبر) و round-trip XLSX/CSV با RTL |
| cron CLI | PASS؛ بدون warning؛ با reminder غیرفعال هیچ پیامک یا cleanup recordی ایجاد/حذف نشد |

> نتیجه: شش ایراد تأییدشده رفع شدند و آزمون‌های رگرسیون مرتبط با موفقیت تکرار شدند. برای BUG-006 یک release patch سازگار با MariaDB تولید می‌شود.


## BUG-006 — خطای MariaDB 1093 در migration 21 روی surveys

| فیلد | شرح |
|---|---|
| شدت | بحرانی برای upgrade؛ سرویس پس از overwrite تا اجرای migration در دسترس نبود. |
| بازتولید | روی MariaDB با `schema_versions` تا نسخهٔ 20 و یک survey یتیم، migration 21 query مستقیم `DELETE FROM surveys ... NOT IN (SELECT id FROM surveys)` را اجرا می‌کرد. MariaDB آن را با `SQLSTATE[HY000]: General error: 1093` رد می‌کرد. |
| علت ریشه‌ای | حذف از یک جدول با subquery خواندن همان جدول در همان statement؛ محدودیت شناخته‌شدهٔ MariaDB/MySQL برای target table در subquery. |
| اصلاح | query به `DELETE child FROM surveys child LEFT JOIN surveys parent ...` تبدیل شد؛ رفتار حذف orphan حفظ شده و با foreign key self-reference سازگار است. |
| آزمون | PASS: fresh schema + orphan fixture + schema version 20؛ خروجی `MIGRATION_1093_E2E_PASS version=28 orphan=0`. |
| وضعیت | رفع شد؛ باید در release patch بعدی منتشر شود. |


## BUG-007 — orphan reference هنگام ایجاد FKهای migration 27

| فیلد | شرح |
|---|---|
| شدت | زیاد برای upgrade؛ migration روی دیتابیس‌های قدیمی متوقف می‌شد و schema version به 28 نمی‌رسید. |
| بازتولید | روی MariaDB با `schema_versions` تا نسخهٔ 26 و یک رکورد `survey_assignments` که `survey_id` آن در `surveys` وجود نداشت، اجرای migration 27 هنگام افزودن `fk_sa_survey` با `SQLSTATE[23000]: Integrity constraint violation: 1452` متوقف شد. |
| علت ریشه‌ای | migration پیش از افزودن constraintهای foreign key، داده‌های orphan موجود در نصب‌های قدیمی را بررسی یا repair نمی‌کرد. در نتیجه MariaDB ساخت FK را به‌درستی رد می‌کرد. |
| اصلاح | پیش از ساخت FKها، assignmentهای orphan برای روابط `CASCADE` حذف می‌شوند و مقادیر orphan برای روابط `SET NULL` در ticket department، activity log، SMS log و notification creator به `NULL` تبدیل می‌شوند. تعداد رکوردهای اصلاح‌شده فقط در error log ثبت می‌شود و به کاربر نمایش داده نمی‌شود. |
| ملاحظهٔ داده | حذف assignment orphan از نظر business state قابل نگه‌داری نیست، چون parent survey وجود ندارد؛ برای روابط `SET NULL` رکورد اصلی نگه داشته می‌شود و فقط اشارهٔ نامعتبر پاک می‌شود. قبل از migration همچنان backup دیتابیس توصیه می‌شود. |
| آزمون | PASS: fixture ایزوله با orphan survey assignment؛ cleanup یک رکورد را حذف کرد، migration تا version 28 رسید، orphanها صفر شدند و `fk_sa_survey` و `fk_sa_customer` ساخته شدند. PHPUnit، PHPStan و lint نیز PASS شدند. |
| وضعیت | اصلاح شد؛ در patch بعدی منتشر می‌شود. |


## BUG-008 — خطای MariaDB/MySQL 1292 در migration 26 هنگام نصب تازه

| فیلد | شرح |
|---|---|
| شدت | زیاد برای نصب تازه؛ مرحلهٔ دوم ویزارد قبل از ایجاد حساب مدیر با Fatal error متوقف می‌شد. |
| بازتولید | اجرای `install.php` روی schema تازه و اجرای `portal_migrations()` با SQL strict؛ migration 26 پس از اینکه ستون‌های تاریخ در `schema.sql` از ابتدا `DATE` هستند، عبارت `UPDATE invoices SET due_date = NULL WHERE due_date = ''` را اجرا می‌کرد و MariaDB/MySQL آن را با `SQLSTATE[22007] / 1292` رد می‌کرد. |
| علت ریشه‌ای | migration 26 فرض می‌کرد ستون‌ها هنوز رشته‌ای هستند. در schema فعلی، ستون‌های تاریخ و مبلغ از ابتدا `DATE` و `DECIMAL` هستند؛ مقایسهٔ آن‌ها با رشتهٔ خالی در strict SQL mode تبدیل ضمنی نامعتبر ایجاد می‌کند. |
| اصلاح | تابع `portal_column_data_type()` اضافه شد. پاک‌سازی و تبدیل مقدار فقط زمانی انجام می‌شود که ستون واقعاً از نوع string قدیمی باشد؛ مقدارهای خالی نیز پیش از `ALTER TABLE` به `NULL` تبدیل می‌شوند. روی ستون‌های `DATE` و `DECIMAL` نصب تازه دیگر query مقایسه با `''` اجرا نمی‌شود. |
| آزمون | PASS: سناریوی fresh با schema فعلی و SQL strict تا `schema version=28` اجرا شد؛ سناریوی legacy با ستون‌های VARCHAR، مقدار خالی و SQL strict نیز تا `schema version=28` اجرا شد و type نهایی تاریخ/مبلغ صحیح بود. |
| وضعیت | رفع شد؛ در patch v2.0.3 منتشر می‌شود. |


## BACKUP-001 — خطای lifecycle در restore اولیه

| فیلد | شرح |
|---|---|
| شدت | متوسط؛ restore کامل در اولین E2E بعد از اجرای عملیات دیتابیس با خطای `Invalid or uninitialized Zip object` متوقف می‌شد. |
| علت ریشه‌ای | کد در `finally` پس از `ZipArchive::close()` دوباره property وضعیت همان object را می‌خواند. |
| اصلاح | وضعیت بسته‌شدن با flag مستقل کنترل شد و `close()` فقط زمانی اجرا می‌شود که archive هنوز باز باشد. |
| آزمون | PASS؛ E2E کامل restore شامل جایگزینی دیتابیس، فایل marker و ایجاد pre-restore backup با موفقیت اجرا شد. |
| وضعیت | رفع شد و پیش از release نهایی ثبت می‌شود. |


## FEATURE-001 — ماژول Backup و Restore مدیریتی

| موضوع | پیاده‌سازی و وضعیت |
|---|---|
| دسترسی | تب Backup فقط برای `super_admin` نمایش داده می‌شود؛ POST و download نیز server-side محدود شده‌اند. |
| محتوای backup | dump دیتابیس، فایل‌های پروژه، assetها و uploadها؛ `.git`، cacheها و backupهای قبلی عمداً حذف می‌شوند. |
| امنیت archive | نام فایل strict، manifest اختصاصی Portal3، سقف حجم، ZipArchive، بررسی مسیرهای traversal و deny مستقیم Apache در `storage/backups/.htaccess`. |
| Restore | عبارت تأیید `RESTORE`، CSRF، ساخت pre-restore backup خودکار، سپس restore دیتابیس و فایل‌ها. |
| Audit | ایجاد، حذف و restore در `activity_logs` ثبت می‌شود. |
| آزمون | UI smoke در پنل مدیر، E2E ساخت/اعتبارسنجی archive، E2E restore دیتابیس و فایل، PHPUnit security tests، PHPStan و lint؛ همه PASS. |


## BUG-009 — نمایش دکمهٔ «حذف» هنگام ایجاد backup

| فیلد | شرح |
|---|---|
| شدت | متوسط؛ عملیات ایجاد backup از نظر فنی درست بود، اما modal عمومی دکمهٔ تأیید را به‌صورت ثابت «حذف» نمایش می‌داد و UX گمراه‌کننده ایجاد می‌کرد. |
| علت ریشه‌ای | `portal_confirm_modal()` برای همهٔ فرم‌های `data-confirm-msg` عنوان «تأیید حذف» و دکمهٔ «حذف» داشت و فرم‌ها نوع عملیات خود را به modal اعلام نمی‌کردند. |
| اصلاح | modal اکنون `data-confirm-title`، `data-confirm-ok-label` و `data-confirm-tone` را از فرم می‌خواند. ایجاد backup دکمهٔ «ایجاد backup»، restore دکمهٔ «بازیابی» و حذف backup دکمهٔ «حذف backup» دارد. برای حفظ validation بومی فرم، از `requestSubmit()` با bypass کنترل‌شده استفاده می‌شود. |
| آزمون | PASS؛ regression تست metadata، PHPUnit با ۹ تست و ۲۹ assertion، PHPStan، lint و diff check موفق شدند. |
| وضعیت | رفع شد و در patch v2.1.1 منتشر می‌شود. |


## FEATURE-001 — ماژول Gamification و باشگاه امتیاز و پاداش

**نسخه:** 2.2.0

**وضعیت:** پیاده‌سازی‌شده و آمادهٔ release پس از final gate

### دامنهٔ قابلیت

ماژول Gamification به‌صورت مستقل و قابل‌فعال‌سازی/غیرفعال‌سازی پیاده‌سازی شد. هنگام خاموش‌بودن، endpointها، navigation و کارت‌های مشتری در سمت سرور و UI غیرفعال هستند و داده‌های قبلی حذف نمی‌شوند.

### امتیازدهی و رویدادها

رویدادهای `profile_completed`، `survey_submitted`، `ticket_created`، `ticket_customer_reply` و `bonus_code_redeemed` به event registry اضافه شدند. مقدار امتیاز فقط server-side محاسبه می‌شود و ruleها دارای فعال‌بودن، daily cap و cooldown هستند. wallet و ledger با transaction و idempotency key به‌روزرسانی می‌شوند.

### پاداش و فروشگاه

مدیر می‌تواند سایت فروش HTTPS، reward catalog، هزینهٔ امتیاز، سقف مشتری و مدت اعتبار را تنظیم کند. حالت امن pool کدهای یکتا هنگام redeem با row lock رزرو می‌شود؛ حالت fixed نیز برای کمپین‌های عمومی با هشدار کنترل مصرف در سایت مقصد وجود دارد. provider فعلی `manual_redirect` است و adapter API آینده بدون تغییر wallet/ledger قابل اضافه‌شدن خواهد بود.

### کنترل‌های امنیتی

کدهای کمپین با SHA-256 hash ذخیره می‌شوند؛ کد خام در log یا دیتابیس نگه‌داری نمی‌شود. URL فروش فقط HTTPS است مگر در development صریح. CSRF مرکزی، RBAC، query پارامتری، ledger immutable، idempotency، جلوگیری از موجودی منفی، سقف مصرف، اعتبار و جلوگیری از نمایش coupon pool کامل اعمال شده است.

### آزمون‌ها

- PHPUnit: ۱۲ تست و ۴۱ assertion موفق.
- PHPStan: ۵۰ فایل بدون خطا.
- PHP lint و `git diff --check`: موفق.
- Core E2E روی دیتابیس ایزوله: migration 29، award idempotency، bonus code، wallet و coupon pool موفق.
- Upgrade E2E از schema 28 به 29: موفق.
- Regression امنیتی کد و URL: موفق.
- بررسی iconهای UI و تعادل فرم‌های مدیر/مشتری: موفق.


## FEATURE-002 — بهبود UX داشبورد و اعلان contextual Gamification

**نسخه:** 2.2.1

**وضعیت:** پیاده‌سازی و regression کامل شد.

### تغییرات UX

داشبورد مشتری در `customer/gamification.php` با hero موجودی، progress تا پاداش بعدی، KPIهای کل کسب/مصرف، کارت‌های قابل‌کلیک کسب امتیاز، progress هر پاداش، وضعیت موجودی coupon pool، تاریخچهٔ responsive و نمایش خواناتر پاداش‌های اخیر بازطراحی شد. کد صادرشده امکان copy دارد و اعداد، کدها، URLها و عنوان‌های ترکیبی با جداسازی جهت مناسب نمایش داده می‌شوند.

### اعلان‌های contextual

helper مرکزی `gamification_context_offer()` فعال‌بودن ماژول و rule و مثبت‌بودن امتیاز را بررسی می‌کند. در پروفایل، اعلان فقط تا قبل از ثبت امتیاز تکمیل کامل نمایش داده می‌شود. در نظرسنجی، اعلان فقط در صورت وجود فرم قابل‌تکمیل نمایش داده می‌شود. در تیکت، بسته به حالت صفحه، امتیاز ثبت تیکت جدید یا پاسخ معتبر پیشنهاد می‌شود. اعلان‌ها با `alert-info` سبک و responsive هستند و هنگام خاموش‌بودن Gamification یا rule مربوطه حذف می‌شوند.

### آزمون و دیباگ

| مورد | نتیجه |
|---|---|
| PHPUnit | PASS؛ ۱۳ تست و ۴۳ assertion |
| PHPStan | PASS؛ ۵۰ فایل بدون خطا |
| PHP lint | PASS؛ تمام فایل‌های PHP |
| Tailwind local build | PASS؛ utilityهای جدید در `assets/tailwind.css` تولید شدند |
| regression helper خاموش | PASS؛ در حالت disabled و بدون PDO fail-closed است |
| ZIP policy | PASS؛ بدون tests، vendor، Git، `db_config.php`، cache، upload محیط توسعه و backup archive |
| smoke authenticated در sandbox | محدود؛ server موجود قبل از اجرای migration پاسخ maintenance با HTTP 503 داد، نه خطای syntax/runtime این تغییر |

### BUG-010 — dependency ناقص در PHPUnit bootstrap برای helper settings

| فیلد | شرح |
|---|---|
| شدت | پایین؛ فقط regression test جدید در محیط تست شکست می‌خورد و runtime production تحت تأثیر نبود. |
| علت ریشه‌ای | `tests/bootstrap.php` پیش از این `settings.php` را load نمی‌کرد، درحالی‌که `gamification_enabled()` به `is_module_enabled()` وابسته است. |
| اصلاح | include صریح `includes/functions/settings.php` به bootstrap اضافه شد و تست fail-closed برای helper جدید ثبت گردید. |
| آزمون | PASS؛ PHPUnit پس از اصلاح ۱۳ تست و ۴۳ assertion را موفق اجرا کرد. |
| وضعیت | رفع شد. |


## PLATFORM-AUDIT-20260813 — ممیزی و اصلاحات Gamification v2.2.2

**دامنه:** ماتریس customer/super_admin × dashboard/profile/surveys/tickets/notifications/admin Gamification × available/received/cooldown/daily cap/redeem/duplicate/disabled/RBAC.

### AUDIT-001 — cache stale پس از migration

| فیلد | شرح |
|---|---|
| شدت | متوسط؛ می‌توانست setting جدید یا module toggle را تا پایان TTL کهنه نشان دهد. |
| علت | `bin/migrate.php` پس از اجرای migration cache فایل تنظیمات را invalid نمی‌کرد. |
| اصلاح | پس از migration، `portal_cache_flush()` اجرا می‌شود و تعداد فایل‌های پاک‌شده در خروجی CLI اعلام می‌گردد. |
| آزمون | قبل `2` cache؛ خروجی `schema version=30; cache flushed=2`؛ بعد `0` cache. |
| وضعیت | رفع شد. |

### AUDIT-002 — وضعیت دریافت‌شده و شمارندهٔ misleading در داشبورد

| فیلد | شرح |
|---|---|
| شدت | متوسط؛ profile پس از award همچنان در «روش‌های فعال» شمرده می‌شد و CTA ذهنی ایجاد می‌کرد. |
| علت | شمارندهٔ dashboard تعداد ruleهای پیکربندی‌شده را به‌جای state=`available` می‌شمرد. |
| اصلاح | `availableEventCount` اضافه شد؛ dashboard اکنون «۱ قابل دریافت از ۴ روش تنظیم‌شده» نشان می‌دهد و کارت received لینک فعال ندارد. Profile نیز تا قبل از award، در حالت کامل‌بودن اطلاعات پیام «با ذخیرهٔ پروفایل ... امتیاز بگیرید» نشان می‌دهد. |
| آزمون | browser E2E: profile پس از award با badge «قبلاً دریافت شده» و شمارندهٔ available صحیح نمایش داده شد. |
| وضعیت | رفع شد. |

### AUDIT-003 — ساده‌سازی تنظیمات rule و legacy consistency

| فیلد | شرح |
|---|---|
| شدت | متوسط؛ checkbox مستقل فعال‌بودن با points می‌توانست وضعیت متناقض بسازد و تنظیمات را پیچیده کند. |
| اصلاح | checkboxهای تکراری حذف شد؛ امتیاز مثبت یعنی فعال و صفر یعنی خاموش. migration 31 همین قرارداد را برای ruleهای legacy همسان می‌کند و `save_rule` قدیمی نیز همین رفتار را اعمال می‌کند. |
| آزمون | پنل مدیر بدون checkbox rule، با badge فعال/خاموش و سه ورودی اصلی smoke شد؛ schema به 31 رسید. |
| وضعیت | رفع شد. |

### AUDIT-004 — گزارش ناقص موجودی مشتریان

| فیلد | شرح |
|---|---|
| شدت | متوسط؛ `LIMIT 200` بدون صفحه‌بندی می‌توانست مشتریان بیشتر را بی‌صدا پنهان کند. |
| اصلاح | گزارش مدیر به page size=50، شمارش کل، متن بازهٔ نمایش و navigation RTL مجهز شد. |
| آزمون | browser: «نمایش ۱ تا ۱ از ۱ مشتری» و جدول balance/earned/spent/ledger مشاهده شد. |
| وضعیت | رفع شد. |

### AUDIT-005 — reward متصل به سایت خاموش

| فیلد | شرح |
|---|---|
| شدت | متوسط؛ reward فعال ولی سایت مقصد خاموش می‌توانست CTA دریافت غیرقابل‌استفاده داشته باشد. |
| اصلاح | `gamification_list_rewards()` فقط rewardهای متصل به سایت فعال را برای مشتری برمی‌گرداند؛ badge مدیر نیز «سایت خاموش» را از «فعال» جدا می‌کند و KPI کدهای آماده فقط مقصدهای فعال را می‌شمرد. |
| آزمون | با خاموش‌کردن سایت، catalog مشتری empty state صحیح نشان داد و بعد از تست سایت restore شد. |
| وضعیت | رفع شد. |

### AUDIT-006 — اختلاف timezone در cooldown

| فیلد | شرح |
|---|---|
| شدت | متوسط؛ `strtotime(created_at)` در PHP و UTC query در SQL می‌توانست روی Laragon/هاست با timezone متفاوت، زمان باقی‌مانده را نادرست کند. |
| اصلاح | status اکنون `UNIX_TIMESTAMP(created_at)` را از MariaDB می‌گیرد و با epoch فعلی مقایسه می‌کند. |
| آزمون | lint، PHPUnit و PHPStan پس از اصلاح موفق بودند؛ browser cooldown روزانه/دقیقه‌ای مقدار منطقی نشان داد. |
| وضعیت | رفع شد. |

### شواهد E2E نقش‌ها و اعلان‌ها

در نقش customer، profile، survey، ticket create، ticket reply، bonus redeem، reward pool redeem و duplicate rejection با browser اجرا شدند. هر award واقعی toast شناور و اعلان inbox success ایجاد کرد؛ duplicateها موجودی/ledger را افزایش ندادند. در نقش customer، دسترسی مستقیم به `admin/gamification.php` به داشبورد مشتری redirect شد. در نقش super_admin، ruleها، سایت، pool، ledger و گزارش aggregate مشتریان قابل مشاهده بودند.

### کنترل کیفیت release

- PHPUnit: ۱۴ تست و ۴۷ assertion موفق.
- PHPStan: ۵۰ فایل بدون خطا.
- PHP lint: فایل‌های تغییرکرده بدون خطا.
- migration: schema version 31 و cache flush فعال.
- browser smoke: customer و super_admin، RTL، responsive snapshot، module disabled/enabled، reward site disabled/enabled، notification unread/read و duplicate safety.
