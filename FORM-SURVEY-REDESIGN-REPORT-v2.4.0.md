# گزارش بازطراحی فرم‌ها و نظرسنجی Portal3 v2.4.0

**هدف release:** بازطراحی جامع فرم‌های افزودن/ویرایش محصول و پروژه، بازطراحی ایجاد نظرسنجی و مدیریت سؤال‌ها، افزودن مقیاس رضایت توصیفی پنج‌گزینه‌ای و رفع مشکلات contrast در dark mode.

## فرم‌های محصول و پروژه

فرم‌های `admin/products.php` و `admin/projects.php` به الگوی مشترک semantic منتقل شدند. هر فرم اکنون header gradient متصل به palette، icon و subtitle، back action، step hierarchy، section panel، input primitive، date control، upload panel، custom-field panel و action bar دارد. رنگ amber ثابت دکمهٔ ذخیرهٔ محصول حذف شد و هر دو فرم از primary انتخابی مدیر پیروی می‌کنند.

کنترل‌های جستجوی مشتری، select مشتری، عنوان، توضیحات، وضعیت، تاریخ و تصویر دارای label یا `aria-label` پایدار هستند. preview تصویر در dark mode از surface و border متناسب استفاده می‌کند و date trigger از button primitive مشترک بهره می‌برد.

## ایجاد نظرسنجی و مدیریت سؤال‌ها

صفحهٔ یکپارچهٔ `admin/surveys.php` برای حالت‌های create، edit، question management و results به semantic surfaceهای author card، form card، period panel، question list، question editor، filter card و statistics chips منتقل شد. پنل افزودن سؤال توضیح کمکی دربارهٔ نوع نمایش پاسخ دارد و سؤال‌های موجود در cardهای خواناتر با editorهای collapsible مدیریت می‌شوند.

## نوع سؤال جدید: رضایت‌سنجی پنج‌گزینه‌ای

نوع جدید با کلید `satisfaction_5` و label «رضایت‌سنجی: عالی تا بد» اضافه شد. ترتیب گزینه‌ها چنین است:

| کلید داخلی | متن فارسی |
|---|---|
| `excellent` | عالی |
| `good` | خوب |
| `average` | متوسط |
| `weak` | ضعیف |
| `bad` | بد |

catalog و validation در `includes/functions/surveys.php` متمرکز شده‌اند. پنل مدیر، فرم مشتری و لینک عمومی survey از همین catalog و validator استفاده می‌کنند. گزارش مدیر پاسخ‌های این نوع سؤال را به‌صورت distribution با label فارسی نمایش می‌دهد و مقدار خام key به کاربر نشان داده نمی‌شود.

برای نصب‌های موجود migration شمارهٔ 32، ENUM `survey_questions.question_type` را گسترش می‌دهد. schema پایه نیز برای نصب‌های تازه به‌روزرسانی شده است.

## Dark mode و accessibility

componentهای جدید از tokenهای `--portal-*`، surfaceهای semantic و borderهای runtime استفاده می‌کنند. در dark mode input و select فرم محصول روی `rgb(31,31,33)` با متن `rgb(245,245,247)` و contrast اندازه‌گیری‌شدهٔ حدود `15.11` بررسی شدند. RTL، focus rule، keyboard radio labels، responsive satisfaction grid و نبود horizontal overflow نیز بررسی شدند.

## تست و QA

| بررسی | نتیجه |
|---|---|
| Migration schema | موفق؛ نسخهٔ 32 |
| PHPUnit | 19 تست و 67 assertion موفق |
| PHPStan | 50 فایل بدون خطا |
| PHP lint | تمام فایل‌های PHP بدون syntax error |
| فرم محصول dark mode | موفق؛ RTL، contrast، labels و overflow |
| فرم پروژه dark mode | موفق؛ RTL، contrast، date/upload controls و overflow |
| create survey dark mode | موفق؛ type/entity، periodic toggle و contrast |
| question editor dark mode | موفق؛ چهار type، editor panels و accessibility |
| customer satisfaction renderer | موفق؛ پنج گزینهٔ فارسی، focus و overflow |
| fixture cleanup | موفق؛ هیچ سؤال QA یا پاسخ آزمایشی باقی نماند |

نسخهٔ رسمی این release در فایل `VERSION` برابر `2.4.0` است.
