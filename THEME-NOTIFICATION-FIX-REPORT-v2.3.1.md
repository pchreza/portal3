# گزارش اصلاحات Portal3 v2.3.1

**هدف patch:** رفع ناهماهنگی palette انتخابی مدیر در active navigation و اجزای Conca-inspired، و تضمین اعلان خودکار برای هر award موفق امتیاز.

## اصلاح رنگ‌بندی

ریشهٔ باگ این بود که `theme_styles()` متغیرهای `--tp-*` را از تنظیم مدیر تولید می‌کرد، اما لایهٔ جدید `assets/portal-ui.css` برای `--portal-primary`، accent، hero gradient، form accent و shadowها مقدارهای ثابت بنفش داشت. در این patch tokenهای Portal به `--tp-primary`، `--tp-primary-dark` و `--tp-accent` متصل شدند.

همچنین mappingهای utilityهای indigo/violet برای background، text، border، ring، shadow، hover، group-hover، file input، gradient و کنترل‌های native تکمیل شد. fallback favicon نیز اکنون از primary پالت فعال استفاده می‌کند. preview پالت‌ها در `admin/settings.php` نیز به رنگ واقعی همان گزینه متصل شد؛ بنابراین قبل از ذخیره نیز border، soft background و checkmark هر palette رنگ درست خود را نشان می‌دهند.

## اصلاح اعلان امتیاز

اعلان پایدار از `gamification_award_feedback()` به مسیر مرکزی `gamification_award_points()` منتقل شد. از این پس هر award موفق، صرف‌نظر از اینکه از profile، survey، ticket یا مسیر دیگر فراخوانی شده باشد، یک اعلان با عنوان «امتیاز جدید دریافت کردید» و متن قالب زیر ایجاد می‌کند:

> برای «نام فعالیت» X امتیاز گرفتید.

`gamification_award_feedback()` فقط مسئول floating toast باقی مانده است تا اعلان duplicate ایجاد نشود. برای bonus code، رفتار اعلان هدیه حفظ شده است. `send_notification()` نیز به‌گونه‌ای اصلاح شد که هنگام فراخوانی داخل transaction موجود، nested transaction ایجاد نکند و commit/rollback را به transaction مالک واگذار کند.

## QA و تست

| بررسی | نتیجه |
|---|---|
| انتخاب و ذخیرهٔ پالت سبز زمردی در پنل مدیر | موفق |
| `--tp-primary` پس از ذخیره سبز | `#059669` |
| `--portal-primary` | `#059669` |
| active navigation background | `rgb(5, 150, 105)` |
| primary button background | `rgb(5, 150, 105)` |
| fallback favicon | سبز با `#059669` |
| overflow افقی | `false` |
| integration award واقعی برای `audit_customer` | موفق؛ 100 امتیاز و notification recipient ثبت شد |
| متن notification | «برای «تکمیل کامل پروفایل» 100 امتیاز گرفتید.» |
| cleanup دادهٔ integration | کامل؛ ledger، wallet delta و notification آزمایشی حذف شدند |
| `composer test` | 15 تست و 54 assertion موفق |
| `composer analyse` | 50 فایل بدون خطای PHPStan |
| `composer lint` | تمام فایل‌های PHP بدون خطای syntax |
| `git diff --check` | موفق |

برای جلوگیری از تغییر ناخواسته در محیط آزمایش، palette fixture پس از تست به «بنفش (پیش‌فرض)» بازگردانده شد.

## فایل‌های تغییرکرده

| فایل | نقش |
|---|---|
| `assets/portal-ui.css` | اتصال tokenهای shell و componentها به palette runtime و حذف accentهای ثابت |
| `includes/functions/helpers.php` | تکمیل mappingهای theme، favicon و utilityهای indigo/violet |
| `includes/functions/notifications.php` | پشتیبانی امن از transaction موجود |
| `includes/functions/gamification.php` | اعلان مرکزی award و حذف duplicate از feedback |
| `admin/settings.php` | preview پویا برای گزینه‌های palette |
| `tests/Unit/GamificationSecurityTest.php` | regression assertion برای central notification dispatch |
| `VERSION` | bump به `2.3.1` |

