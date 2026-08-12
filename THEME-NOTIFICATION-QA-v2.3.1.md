# QA Theme & Notification Fix — v2.3.1

## مشاهدهٔ اولیه

با وجود تنظیم فعلی پالت پیش‌فرض، shell جدید از `--portal-primary` با مقدار ثابت بنفش استفاده می‌کرد و active navigation در sidebar به رنگ پالت انتخابی مدیر متصل نبود. این مسیر برای پالت سبز با اصلاح tokenها و `theme_styles()` پوشش داده شد.

## مسیر آزمون واقعی

نشست مشتری بسته شد و نشست مدیر آزمایشی برای بازکردن مسیر `admin/settings.php?tab=appearance` ایجاد شد. آزمون بعدی پالت «سبز زمردی» را انتخاب و ذخیره می‌کند و سپس active navigation، primary button، hero و focus ring را با computed style بررسی خواهد کرد.

## کنترل preview پالت

پالت «سبز زمردی» در تب ظاهر انتخاب شد، اما هنوز ذخیره نشده است. کارت انتخاب‌شده پس از patch با checkmark، border و surface سبز نمایش داده شد؛ این کنترل تأیید می‌کند preview دیگر به رنگ indigo ثابت وابسته نیست.

## کنترل پالت ذخیره‌شدهٔ سبز زمردی

| شاخص | مقدار ثبت‌شده | نتیجه |
|---|---:|---|
| `--tp-primary` | `#059669` | صحیح |
| `--portal-primary` | `#059669` | صحیح؛ دیگر مقدار بنفش ثابت ندارد |
| پس‌زمینهٔ active nav | `rgb(5, 150, 105)` | صحیح |
| رنگ دکمهٔ primary | `rgb(5, 150, 105)` | صحیح |
| متن active nav | سفید | خوانا |
| favicon fallback | `#059669` | صحیح |
| overflow افقی سند | `false` | سالم |

پالت سبز فقط برای آزمون واقعی در fixture محلی ذخیره شد و در انتهای QA به پالت پیش‌فرض بنفش بازگردانده خواهد شد.

## Integration smoke اعلان امتیاز

آزمون با award واقعی روی مشتری fixture (`audit_customer`, شناسهٔ 2) اجرا شد. نتیجه: `100` امتیاز ثبت شد، ledger ساخته شد، اعلان با عنوان «امتیاز جدید دریافت کردید» و متن «برای «تکمیل کامل پروفایل» 100 امتیاز گرفتید.» به recipient شمارهٔ 2 رسید و سپس ledger، wallet delta و notification به‌صورت کامل cleanup شدند. هیچ دادهٔ QA باقی نماند.

## Cleanup fixture

پس از اتمام آزمون، پالت به «بنفش (پیش‌فرض)» بازگردانده و ذخیره شد؛ تغییر سبز فقط برای QA محلی بود.

## تست‌های regression

| فرمان | نتیجه |
|---|---|
| `git diff --check` | موفق |
| `composer test` | موفق؛ 15 تست و 54 assertion |
| `composer analyse` | موفق؛ PHPStan روی 50 فایل بدون خطا |
| `composer lint` | موفق؛ تمام فایل‌های PHP بدون خطای syntax |

تغییرات theme شامل اتصال `--portal-primary`، `--portal-accent`، active navigation، button، hero، form accent، focus ring، favicon fallback و utilityهای indigo/violet به `--tp-*` است. اعلان persistent در مسیر مرکزی `gamification_award_points()` ایجاد می‌شود و feedback فقط toast را نگه می‌دارد تا duplicate notification رخ ندهد.
