# یادداشت‌های QA بصری بازطراحی v2.3.0

## کنترل اولیهٔ مشتری (حالت روشن، desktop)

| مسیر | نتیجه | مشاهدات |
|---|---|---|
| `/customer/index.php` | موفق | Shell جدید شامل sidebar راست سفید، topbar روشن، hero بنفش، کارت‌های KPI، کارت‌های recent و بنر وضعیت نظرسنجی به‌درستی نمایش داده شد. کارت‌های KPI به‌صورت responsive به ردیف دوم منتقل شدند و navigation فعال قابل تشخیص است. |
| `/customer/tickets.php` | موفق | sidebar و topbar جدید روی صفحهٔ عملیاتی اعمال شده‌اند. عنوان، CTA تیکت جدید و table کارت‌گونه با header و badgeهای وضعیت قابل‌خواندن هستند. جدول در desktop بدون overflow مشاهده شد. |

## موارد تکمیلی برنامه‌ریزی‌شده

- کنترل form mode و mobile reflow برای profile، survey و customer CRUD پس از اعمال component classes.
- کنترل dark mode، keyboard focus و شکستن جدول‌ها در breakpointهای 320/768/1440.
- ورود مدیر و کنترل dashboard و CRUDهای مدیریتی در مرحلهٔ QA.

## کنترل اولیهٔ مدیر (حالت روشن، desktop)

| مسیر | نتیجه | مشاهدات |
|---|---|---|
| `/index.php` | موفق | صفحهٔ ورود با card مرکزی، فضای سفید کافی، آیکن برند، fieldهای واضح و CTA بنفش بر پایهٔ زبان بصری جدید نمایش داده شد. |
| `/admin/index.php` | موفق | dashboard مدیر دارای rail دسته‌بندی‌شده، topbar با user chip، چهار KPI card، table مشتریان اخیر، stream فعالیت‌ها و quick actions است. hierarchy و تراکم اطلاعات در desktop خوانا و بدون overflow مشاهده شد. |

> ورود با حساب آزمایشی مدیر تنها برای QA محلی انجام شد و هیچ داده‌ای ایجاد یا تغییر نکرد.

| `/admin/customers.php` | موفق | toolbar ورود/خروج Excel، CTA افزودن مشتری، table card با header روشن و actionهای واضح در RTL نمایش داده شدند. |
| `/admin/customers.php?action=add` | موفق | form card دارای accent line، heading/action، grid دو‌ستونه، labelهای مرتبط، date picker و action bar است؛ فرم فقط باز شد و submit نشد. |

## Accessibility و direction audit

| شاخص | نتیجه |
|---|---|
| `lang` | `fa` |
| `dir` سند | `rtl` |
| فونت محاسبه‌شدهٔ body | `Vazirmatn, Tahoma, Arial, sans-serif` |
| ورودی‌های visible بدون label یا aria-label | 0 |
| دکمه/لینک بدون نام accessible | 0 |
| skip link | موجود |
| focus ring روی `#username` | موفق؛ border بنفش و box-shadow سه‌پیکسلی با token primary |
| direction محتوای اصلی | `rtl` |
| لایهٔ portal UI | در stylesheet فعال |

## Dark mode audit

| شاخص | نتیجه |
|---|---|
| `html.dark` | فعال شد |
| background body | `rgb(22, 22, 24)` |
| متن body | `rgb(245, 245, 247)` |
| form card background | `rgb(31, 31, 33)` |
| input background / text | `rgb(31, 31, 33)` / `rgb(245, 245, 247)` |
| active navigation | primary `#5F4AFE` با متن سفید |
| نتیجهٔ بصری | بدون شکست layout و با خوانایی مناسب در screenshot فرم مدیر |

| `/admin/gamification.php` | موفق | پس از semantic patch، 4 KPI card و 8 panel در dark mode نمایش داده شدند؛ 34 control همگی label داشتند، هر 2 جدول داخل `.table-scroll` بودند و `horizontalOverflow=false` ثبت شد. |

## Route smoke test مدیر در dark mode

| مسیر | نتیجه | نکته |
|---|---|---|
| `/admin/tickets.php` | موفق | filter card، export banner، table، status/priority badges و action links بدون خطای render نمایش داده شدند؛ در viewport محدود، table به‌صورت horizontal scroll قابل استفاده است. |
| `/admin/surveys.php` | موفق | active navigation، CTA ایجاد فرم و table نظرسنجی با actionهای ویرایش/سؤال/گزارش/حذف در RTL سالم نمایش داده شد. |

| `/admin/settings.php` | موفق | هشت tab تنظیمات و 10 module toggle در dark mode نمایش داده شدند؛ `horizontalOverflow=false`. بررسی اولیهٔ DOM فقط hidden inputs و فیلدهای modal گزارش خطا را بدون متن inline نشان داد؛ این موارد خارج از viewport هستند و باید با label/aria در dialog audit نهایی شوند. |

## اصلاح و تأیید contrast اعلان‌ها

مشکل مشاهده‌شده: utilityهای Tailwind با alpha (`bg-indigo-50/50` و `bg-slate-50/50`) در dark mode به‌صورت کامل remap نشده بودند و کارت‌های اعلان بیش از حد روشن/سفید دیده می‌شدند. با selectorهای theme-aware در `portal-ui.css`، surface اعلان‌های خوانده‌شده و‌خوانده‌نشده به ترکیب‌های تیره و قابل‌خواندن تبدیل شد. screenshot بعدی dashboard مشتری نشان داد کارت‌ها دیگر سفید نیستند و hierarchy اعلان‌ها حفظ شده است.

## Route smoke test مشتری در dark mode

| مسیر | نتیجه | نکته |
|---|---|---|
| `/customer/profile.php` | موفق | form card با heading، field grid دو‌ستونه، date picker، select و action bar در dark mode بدون overflow نمایش داده شد؛ labels برای fieldها در viewport قابل مشاهده بودند. |
| `/customer/surveys.php` | موفق | page heading و survey card تکمیل‌شده با badge سبز، توضیحات و status footer در dark mode سالم نمایش داده شد؛ حالت بدون survey banner یا broken state ایجاد نکرد. |

| `/customer/gamification.php` | موفق | hero امتیاز، KPIهای موجودی/کسب/مصرف، rule cards با وضعیت «قبلاً دریافت شده»، فرم کد هدیه و reward catalog در dark mode خوانا و بدون overflow افقی مشاهده شد. |
| `/customer/invoices.php` | موفق | table card فاکتورها، اعداد LTR، due date، badge «پرداخت نشده» و active nav فاکتورها در dark mode سالم نمایش داده شد. |

| `/customer/projects.php` | موفق | entity card پروژه با mixed-direction URL، release identifier، تاریخ، status و survey completion state در dark mode خوانا و wrap کنترل‌شده داشت. |
| `/customer/notifications.php` | موفق | هفت notification card با چهار unread، خواندن همه، actionهای خواندن و timestampهای LTR در dark mode سالم و بدون overflow نمایش داده شدند. |
