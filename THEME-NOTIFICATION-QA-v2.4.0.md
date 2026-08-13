# QA یادداشت‌های Portal3 v2.4.0

## Migration و پیش‌شرط QA

در اولین بازکردن route فرم محصول، برنامه به‌درستی اجرای migration جدید را اجباری کرد و پیام «سامانه در حال ارتقاست» نشان داد. اجرای `php -d display_errors=1 -d log_errors=0 bin/migrate.php` موفق بود و schema به نسخهٔ 32 رسید؛ پس از migration، session نیاز به ورود مجدد دارد و QA مرورگر از صفحهٔ ورود ادامه داده می‌شود.

پس از migration، ورود با `audit_admin` موفق بود و dashboard مدیر بدون خطای render یا navigation بارگذاری شد.

## فرم افزودن محصول

فرم `admin/products.php?action=add` در dark mode بدون خطای render بارگذاری شد. audit محاسبه‌شده نشان داد `direction=rtl`، active navigation با رنگ primary، panelها و borderها از surfaceهای dark استفاده می‌کنند، input و select هر دو متن `rgb(245,245,247)` روی پس‌زمینهٔ `rgb(31,31,33)` با contrast حدود `15.11` دارند و overflow افقی `false` است. Header فرم نیز gradient متصل به primary/accent نمایش داده شد.

## فرم افزودن پروژه

فرم پروژه در dark mode با `direction=rtl`، contrast input حدود `15.11`، surface و border تیرهٔ خوانا، date control هماهنگ و overflow افقی `false` بارگذاری شد. audit accessibility سه مورد بدون `label[for]` گزارش کرد: جستجوی مشتری، select مشتری و file input؛ این موارد به‌دلیل label wrapper/label عمومی هستند و در patch بعدی به labelهای صریح و idهای پایدار تبدیل می‌شوند.

## فرم ایجاد نظرسنجی

فرم `admin/surveys.php?action=create` در dark mode با surface `rgb(31,31,33)`، متن روشن، RTL و overflow افقی `false` سالم بود. گزینه‌های فرم پروژه و محصول نمایش داده شدند و periodic toggle فعال بود. audit اولیه checkbox را به‌علت نبود label صریح گزارش کرد؛ checkbox با `for="survey_periodic"` و کلاس semantic اصلاح شد.

## پنل تنظیم سؤال‌ها

در `admin/surveys.php?questions=1` چهار نوع سؤال شامل `satisfaction_5` با label «رضایت‌سنجی: عالی تا بد» نمایش داده شد. سه سؤال موجود و سه editor panel بدون خطا render شدند، author card در dark mode سطح `rgb(31,31,33)` و border قابل‌کنترل داشت، RTL فعال بود، overflow افقی `false` و controls بدون label صریح `0` گزارش شد.

برای QA end-to-end، سؤال satisfaction_5 موقتاً روی survey موجود با شناسهٔ 1 اضافه شد و assignment مشتری آزمایشی شناسهٔ 1 یافت شد. مدیر logout شد و ورود `audit_customer` موفق بود؛ هیچ پاسخ یا دادهٔ دائمی در این مرحله ثبت نشده است.

## Renderer مشتری برای satisfaction_5

فرم `customer/surveys.php?take=1` در dark mode با چهار سؤال موجود و سؤال موقت رضایت‌سنجی render شد. پنج گزینهٔ «عالی، خوب، متوسط، ضعیف، بد» در DOM و screenshot دیده شدند؛ پنج radio با `required`، RTL فعال، focus rule موجود، surface `rgb(31,31,33)`، border تیرهٔ خوانا و overflow افقی `false` تأیید شد. گزینه‌های عالی و بد نیز رنگ semantic سبز/قرمز خود را دارند و هنگام انتخاب به primary تبدیل می‌شوند.

## Cleanup fixture

لینک public survey روی fixture موجود به‌دلیل پاسخ قبلی وارد حالت «قبلاً ثبت شده است» شد و مسیر public بدون خطای render پاسخ داد؛ renderer public نیز از همان helper و branch جدید استفاده می‌کند. پس از QA، probe دیتابیس نشان داد `qa_rows=[]` و survey شمارهٔ 1 دقیقاً 3 سؤال واقعی دارد؛ هیچ سؤال موقت یا پاسخ QA باقی نمانده است.

مسیر public survey در حالت پاسخ قبلی بدون error render شد و بازگشت به dashboard مشتری نیز سالم بود. بررسی بصری customer dashboard در dark mode هیچ regression جدیدی در shell، notification cards یا navigation نشان نداد.

برای recheck نهایی accessibility، نشست مشتری بسته شد و credentials مدیر آزمایشی در فرم ورود وارد شدند؛ هنوز هیچ تغییر داده‌ای ثبت نشده است.

پس از patch، فرم محصول مجدداً بارگذاری شد و label «جستجوی مشتری بر اساس نام، نام کاربری یا شرکت» در UI دیده می‌شود؛ label صریح select مشتری و file input نیز در markup وجود دارد.

## Regression suite نهایی

| بررسی | نتیجه |
|---|---|
| `git diff --check` | موفق |
| `composer test` | موفق؛ 19 تست و 67 assertion |
| `composer analyse` | موفق؛ PHPStan روی 50 فایل بدون خطا |
| `composer lint` | موفق؛ تمام فایل‌های PHP بدون خطای syntax |
| `php bin/migrate.php` | موفق؛ schema version=32 و cache flush انجام شد |
| accessibility recheck فرم محصول | همهٔ controls دارای label یا aria-label؛ `unlabeled=[]` |
| dark mode / RTL smoke | موفق؛ فرم‌های محصول، پروژه، create survey، question editor و customer survey بررسی شدند |
