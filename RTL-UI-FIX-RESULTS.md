# گزارش نتیجهٔ اصلاحات UI/UX و RTL portal3

این گزارش نتیجهٔ اجرای backlog ممیزی `RTL-UI-FIX-PLAN.md` است. اصلاحات در چند گذر مستقل روی کد اعمال شد و سپس با PHP lint، diff check، smoke test HTTP، اندازه‌گیری DOM، آزمون modal/drawer، آزمون dropdown اعلان و مرور بصری mobile login بررسی شد.

## جمع‌بندی وضعیت

| وضعیت | تعداد | توضیح |
|---|---:|---|
| اصلاح و تأییدشده | 16 | اصلاح کد و حداقل یک آزمون static/runtime برای آن انجام شده است. |
| بهبودشده با محدودیت آزمون | 5 | اصلاح اعمال شده، اما برای تأیید کامل به دادهٔ واقعی، نقش ادمین یا viewport تعاملی موبایل نیاز دارد. |
| خارج از scope اصلاح فعلی | 1 | مورد کم‌اولویت مربوط به حذف کامل emoji از تمام copyهاست؛ جایگزینی بدون بازبینی محتوایی انجام نشد تا معنای برند تغییر نکند. |

> نتیجهٔ فنی: در وضعیت فعلی، **هیچ خطای syntax در ۵۵ فایل PHP خارج از vendor وجود ندارد**، fixed-height utility روی headingهای PHP باقی نمانده، smoke test عمومی HTTP موفق است و diff check نیز بدون خطا عبور کرده است.

## ثبت موردبه‌مورد

| ID | مورد | اصلاح انجام‌شده | نتیجه و روش تأیید |
|---|---|---|---|
| R01 | clipping headingهای فارسی به‌علت `h-1` تا `h-4` | height utility از headingهای تیکت، پروفایل، اعلان، تنظیمات و survey حذف و `leading-snug`/typography طبیعی استفاده شد. | **بهبود پیدا کرد و تأیید شد.** اسکن static تعداد headingهای دارای `h-*` را صفر و DOM heading پروفایل/اعلان/public survey را بدون height ثابت نشان داد. |
| R02 | نمایش تکراری CTA در mobile | action rowهای desktop با `desktop-form-actions` علامت‌گذاری شدند تا در breakpoint موبایل مخفی شوند؛ mobile action bar باقی ماند. | **بهبود پیدا کرد و تأیید شد.** تمام ۷ فایل دارای mobile action بررسی و در پروفایل مشتری DOM نشان داد desktop در desktop قابل مشاهده و mobile bar مخفی است. |
| R03 | modal گزارش خطا scroll lock و focus ناقص | close controller مشترک، restore overflow، Escape، backdrop close، focus trap، return focus و `aria-describedby` اضافه شد. | **بهبود پیدا کرد و تأیید شد.** مودال باز شد، body lock شد، close انجام شد و focus به `error-report-fab` بازگشت. |
| R04 | drawer موبایل بدون overlay و keyboard management | backdrop، `aria-controls`، Escape، outside click، scroll lock، focus return و Tab trap برای admin/customer sidebar اضافه شد. | **بهبود پیدا کرد.** تست DOM با شبیه‌سازی viewport موبایل open/close، aria و scroll lock را تأیید کرد؛ آزمون لمسی واقعی روی دستگاه هنوز توصیه می‌شود. |
| R05 | جایگذاری physical/logical ناسازگار FAB و notification dropdown | FAB به `inline-end` منطقی در RTL منتقل شد؛ dropdown اعلان به `end-0` و max-width viewport منتقل شد و کنترل keyboard گرفت. | **بهبود پیدا کرد و تأیید شد.** screenshot نهایی FAB را در لبهٔ فیزیکی چپ نشان داد؛ dropdown با `aria-expanded` و Escape آزموده شد. |
| R06 | toggle با `right` و translate فیزیکی | thumb به `inset-inline-start` منتقل و focus-visible به track افزوده شد. | **بهبود پیدا کرد.** static CSS و lint تأیید شدند؛ آزمون click/Space در محیط authenticated admin باید در QA بعدی تکرار شود. |
| R07 | header menu با position فیزیکی و احتمال overlap | center alignment از `left-1/2` به `start-1/2` منتقل و flex wrapping حفظ شد. | **بهبود پیدا کرد و static تأیید شد.** آزمون عنوان‌های طولانی در تمام تنظیمات هدر هنوز به fixture محتوایی نیاز دارد. |
| R08 | تب‌های تنظیمات overflow-prone | نوار تب‌ها از horizontal overflow به flex-wrap responsive تبدیل شد؛ active/focus linkها در ردیف‌های قابل مشاهده قرار می‌گیرند. physical spacingهای `mr/pr` نیز در بخش‌های مرتبط logical شدند. | **بهبود پیدا کرد و lint/diff تأیید شد.** مرور بصری admin با چند برچسب طولانی توصیه می‌شود. |
| R09 | فشردگی input تاریخ و trigger | تمام date inputهای شناسایی‌شده `dir="ltr"`، `value-ltr`، `flex-1/min-w-0` و wrapper `flex-wrap sm:flex-nowrap` گرفتند. | **بهبود پیدا کرد و static تأیید شد.** اسکن ۶ date input همهٔ آن‌ها را با direction صریح نشان داد. |
| R10 | mixed-direction در username/phone/code/date | کلاس مشترک `value-ltr` با `unicode-bidi:isolate` و tabular numerics اضافه شد و روی username، mobile، invoice number/amount/date، customer table و profile اعمال شد. | **بهبود پیدا کرد.** profile DOM مقدارهای LTR را تأیید کرد؛ گزینه‌های select با متن ترکیبی هنوز نیازمند fixture و تست copy/paste واقعی هستند. |
| R11 | جدول responsive و targetهای کوچک عملیات | table card mobile اکنون `text-align:start` و حداقل ارتفاع 44px برای دکمه‌های مستقیم، cell-actions و form action دارد. | **بهبود پیدا کرد و lint/diff تأیید شد.** data fixture روی تمام جدول‌ها برای بررسی wrap نهایی توصیه می‌شود. |
| R12 | toolbar فیلتر تیکت متراکم | فرم فیلتر کلاس `responsive-toolbar` گرفت و در compact breakpoint به grid تک‌ستونهٔ خوانا تبدیل می‌شود. | **بهبود پیدا کرد و static تأیید شد.** آزمون viewport 320/390 برای admin session هنوز توصیه می‌شود. |
| R13 | truncate بدون دسترسی به مقدار کامل | برای عنوان/شرح کارت‌های entity و اعلان‌های dashboard/dropdown و title صفحهٔ admin `title`/full-value affordance اضافه شد. | **بهبود پیدا کرد و static تأیید شد.** مقدار کامل در hover/focus/DOM موجود است. |
| R14 | pagination بدون semantics کافی | wrapper به `nav aria-label="صفحه‌بندی"` تبدیل شد، current page `aria-current="page"` گرفت و prev/next label معنایی گرفتند. | **بهبود پیدا کرد و static تأیید شد.** keyboard مرورگر باید همراه با دادهٔ چندصفحه‌ای یک بار آزموده شود. |
| R15 | empty و no-results با copy یکسان | در admin tickets بر اساس وجود `$where` بین «هنوز تیکتی ثبت نشده» و «نتیجه‌ای با این فیلترها پیدا نشد» تفکیک شد. | **بهبود پیدا کرد و static تأیید شد.** مسیر بدون داده و مسیر دارای filter در QA دیتابیسی باید هر دو اجرا شوند. |
| R16 | عنوان و CTA نظرسنجی | height ثابت titleهای customer/public survey حذف و action row desktop از mobile CTA جدا شد. | **بهبود پیدا کرد و تأیید شد.** public survey state بدون token load شد و heading اصلی height طبیعی برابر line-height داشت. |
| R17 | rating بدون focus/نام‌گذاری کافی | radioها aria-label دقیق گرفتند و focus-visible برای yes/no، stars و scale 1–10 اضافه شد؛ direction ذاتی scale حفظ شد. | **بهبود پیدا کرد و static تأیید شد.** active survey با fixture واقعی برای arrow navigation و screen reader باید بررسی شود. |
| R18 | contrast در dark mode و palette قابل تنظیم | shared tokenهای موجود حفظ شدند؛ focus ring برای toggle/rating اضافه شد و dark-mode DOM/runtime بررسی شد. | **بهبود پیدا کرد با محدودیت.** contrast رنگ‌های تمام paletteهای قابل تنظیم هنوز نیازمند ابزار contrast خودکار و ورودی‌های واقعی تنظیمات است. |
| R19 | وابستگی typography به CDN | `preconnect` به jsDelivr و fallback `Vazirmatn, Tahoma, Arial, sans-serif` در public/admin/customer/install اضافه شد. | **بهبود پیدا کرد.** failure کامل CDN همچنان به build/local asset مستقل نیاز دارد و خارج از این patch باقی است. |
| R20 | emoji و نمادهای متنی در copy | جایگزینی سراسری انجام نشد تا semantics و برند copy بدون بازبینی محصول تغییر نکند. | **خارج از scope اصلاح فعلی.** این تنها موردی است که عمداً به‌عنوان کار محتوایی کم‌اولویت باقی مانده است. |
| R21 | alignment داده‌های مالی و عددی | invoice number، amount، due date و تاریخ‌های فرم با `value-ltr`/`dir=ltr` و جداسازی label تومان اصلاح شدند. | **بهبود پیدا کرد و static تأیید شد.** دادهٔ چندرقمی واقعی برای بررسی locale formatting نهایی توصیه می‌شود. |
| R22 | titleهای کوتاه‌شده بدون affordance | title attribute به entity list card، header title و اعلان‌های dashboard/dropdown اضافه شد. | **بهبود پیدا کرد و static تأیید شد.** مقدار کامل از DOM قابل دسترسی است. |

## آزمون‌های اجراشده

| آزمون | نتیجه |
|---|---|
| PHP lint تمام پروژه خارج از `vendor` | PASS — ۵۵ فایل بدون خطا |
| `git diff --check` | PASS |
| اسکن headingهای دارای `h-*` | PASS — صفر مورد باقی‌مانده |
| smoke test `index.php` و `survey-public.php` | PASS — HTTP 200 |
| DOM test modal گزارش خطا | PASS — overflow restore، focus return و close hooks |
| DOM test drawer مشتری | PASS — open/close، aria، backdrop و Escape |
| DOM test notification dropdown | PASS — logical placement، aria-expanded و Escape |
| screenshot mobile login در `390×844` | PASS — بدون clipping/overflow؛ FAB در inline-end فیزیکی چپ RTL |
| Composer/vendor و `db_config.php` | عمدی خارج از commit؛ `db_config.php` شامل credentials محیط محلی است و vendor به gitignore اضافه شد |

## وضعیت نهایی و محدودیت‌ها

این patch همهٔ ایرادهای با شدت High و بخش عمدهٔ ایرادهای Medium ثبت‌شده در ممیزی را اصلاح کرده است. مواردی که به دادهٔ واقعی، نقش ادمین، active survey، palette تنظیم‌شده یا device touch وابسته‌اند، از نظر static و smoke بررسی شده‌اند اما برای ادعای پوشش کامل محصول باید در محیط staging نیز اجرا شوند. هیچ تغییری در `db_config.php` یا dependencyهای تولیدشدهٔ `vendor/` commit نمی‌شود.

## منابع

[1] [W3C — Inline markup and bidirectional text in HTML](https://www.w3.org/International/articles/inline-bidi-markup/)

[2] [Material Design 3 — Bidirectionality & RTL](https://m3.material.io/foundations/layout/bidirectionality-rtl)

[3] [W3C — Internationalization Best Practices for Spec Developers](https://www.w3.org/TR/international-specs/)

## گذر دوم اصلاحات و ممیزی داده‌دار — 2026-08-12

در گذر دوم، صفحات داده‌دار هر دو نقش مدیر و مشتری با fixture واقعی بررسی شدند؛ از جمله پروژه، محصول، فاکتور، سه تیکت، سه اعلان و یک assignment فعال نظرسنجی. اصلاحات زیر در کد اعمال و بعد از هر بخش با PHP lint و `git diff --check` اعتبارسنجی شدند.

| حوزه | اصلاح اجرایی | نتیجهٔ ممیزی داده‌دار |
|---|---|---|
| کارت‌های پروژه و محصول مشتری | renderer مشترک `render_entity_card()` برای عنوان و شرح از `<bdi dir="auto">` استفاده می‌کند؛ تاریخ تکمیل/خرید با `dir="ltr"` و `value-ltr` نمایش داده می‌شود. | عنوان‌های فارسی/لاتین، URL و تاریخ fixture در کارت پروژه و محصول بدون بازآرایی مخرب یا شکست جهت مشاهده شدند. |
| فاکتورهای مدیریت و مشتری | شماره، مبلغ، تاریخ سررسید/صدور، عنوان mixed و ستون‌های موبایل با isolation، `data-label`، حداقل عرض و action layout اصلاح شدند. | `INV-RTL-2026-001`، مبلغ `125,450,000` و تاریخ‌های شمسی/میلادی در desktop و mobile خوانا ماندند؛ wrapping عمودی عنوان فاکتور مشتری نیز با wrapper واحد رفع شد. |
| تیکت‌های مدیریت و مشتری | subject، نام مشتری، شرکت، دپارتمان، پیام thread و URL/شناسه‌های فنی با `bdi`; زمان‌ها با `value-ltr`; textareaهای آزاد با `dir="auto"`; action groupها wrap شدند. | سه تیکت fixture شامل `admin@example.test`، `invoice INV-RTL-2026-001` و thread باز در هر دو نقش بررسی شدند. فیلتر مدیریت در 390px تک‌ستونه و جدول‌ها کارت‌محور شدند. |
| اعلانات | نگاشت امن `notification_target_label()` برای مقدار legacy `user` اضافه شد؛ عنوان/متن/نام کاربر isolate و آمار/زمان‌ها LTR شدند. | اعلان‌های واقعی با URL، release ID، مبلغ و target «یک مشتری» در لیست و جزئیات مدیر و کارت مشتری درست نمایش داده شدند. فرم ارسال اعلان در موبایل بدون clipping reflow شد. |
| نظرسنجی | عنوان، entity title، شرح، سؤال‌ها و پاسخ‌ها isolate شدند؛ progress عددی به `0 / 3` با جهت LTR تبدیل شد؛ date/ID filterها و label association فرم مدیریت تکمیل شدند. | assignment فعال با سه سؤال rating، yes/no و star در customer flow باز شد؛ فرم مدیریت، سؤال‌ها و گزارش خالی با fixture بررسی شدند. |
| مدیریت مشتریان | username/password/mobile و مقدار پیش‌فرض import LTR؛ نام/شرکت/سمت `dir="auto"`; تاریخ تولد LTR؛ data label و action group تکمیل شد. | customer fixture با username `audit_customer` و موبایل `09121111111` در جدول desktop و card mobile پایدار بود. |
| navigation موبایل RTL | دکمهٔ hamburger در sidebarهای مدیر و مشتری به inline-start منطقی منتقل شد؛ focus trap/Escape/overlay/return focus حفظ شد. | در 390×844، کنترل منوی مدیریت و مشتری در سمت leading راست قرار گرفت و title بدون overlap truncate شد. |
| نوار Excel | emojiهای CTA حذف و spacing file input از `file:mr-2` به `file:ms-2` تبدیل شد. | toolbar مشتریان/تیکت‌ها در موبایل wrap خوانا دارد و copy بدون وابستگی به emoji ارائه می‌شود. |

### شواهد آزمون گذر دوم

| آزمون | نتیجه |
|---|---|
| lint کامل PHP خارج از `vendor` | PASS؛ خروجی در `rtl-qa-round2/php-lint.log` ذخیره شد. |
| `git diff --check` | PASS. |
| صفحات داده‌دار admin/customer | PASS؛ تیکت، اعلان، نظرسنجی، مشتری، پروژه، محصول و فاکتور با fixture مرور شدند. |
| screenshots موبایل 390×844 | PASS پس از اصلاح navigation و wrapping؛ فایل‌ها در `rtl-qa-round2/mobile/` ذخیره شدند. |
| فاکتور مشتری در mobile | PASS پس از wrapper واحد شماره/عنوان؛ عنوان دیگر به کلمات عمودی نمی‌شکند. |
| dark mode runtime | PASS؛ body `rgb(30,43,71)`، card `rgb(22,33,58)` و متن `rgb(219,227,239)` محاسبه شد؛ نسبت contrast متن body برابر 10.89، متن card برابر 12.38 و CTA اصلی برابر 20.94 ثبت شد. |
| keyboard smoke | PASS؛ اولین Tab به «پرش به محتوای اصلی» رسید و focus ring قابل مشاهده بود؛ رفتار Escape/Tab trap drawer و modal در گذر اول نیز تأیید شده است. |

فایل `db_config.php` عمداً به دلیل credentials محلی خارج از commit باقی می‌ماند. پوشهٔ `rtl-qa-round2/` فقط شامل گزارش و شواهد QA است و فایل‌های حساس نشست/credential در commit نهایی وارد نخواهد شد.


## گذر سوم — فونت محلی، microcopy و typography — 2026-08-12

در این گذر، وابستگی runtime به فونت CDN حذف شد و فونت variable نسخهٔ pin‌شدهٔ Vazirmatn به‌صورت محلی اضافه شد. فایل `Vazirmatn-v33.003-wght.woff2` با اندازهٔ 111,152 بایت و hash برابر `4e3fa217d38fdafc1fea4414ceb58ca5e662cf0ab5fa735a8c8c20e8b42cad92` در `assets/fonts/` قرار گرفت و متن مجوز OFL نیز کنار آن نگهداری شد. stylesheet محلی وزن‌های 100 تا 900 را با `font-display: swap` پوشش می‌دهد؛ helper مشترک نیز preload فونت و versioning مبتنی بر mtime برای CSS را ارائه می‌کند.

| حوزه | اصلاح اجرایی | نتیجهٔ تأیید |
|---|---|---|
| فونت و performance | حذف لینک Vazirmatn از jsDelivr در public/admin/customer/install؛ افزودن local preload و stylesheet؛ versioning برای `portal-ui.css`. | در runtime واقعی پنل مدیریت و مشتری، Vazirmatn محلی loaded شد و request به `rastikerdar` یا Google Fonts صفر بود. |
| عنوان نوار مشتری | افزودن `mobileTitle` اختیاری به renderer؛ عنوان‌های تیکت و فاکتور در موبایل به «تیکت‌ها» و «فاکتورها» کوتاه شدند و عنوان کامل در desktop حفظ شد. | در 390×844 عنوان‌ها تک‌خطی، بدون overlap و با `title` کامل باقی ماندند. |
| فاکتور | «لیست صورتحساب‌های صادر شده» به «فاکتورهای صادرشده» تبدیل شد و wrapper قبلی شماره/عنوان حفظ شد. | heading و کارت فاکتور مشتری در 390×844 بدون شکست عمودی یا فاصلهٔ زائد نمایش داده شدند. |
| اعلان مشتری | در mobile، تاریخ از کنار عنوان به ردیف مستقل منتقل شد تا عنوان mixed فضای کافی داشته باشد؛ `copy-wrap` برای title/body اضافه شد. | عنوان اعلان fixture از چهار خط فشرده به دو خط خوانا کاهش یافت و URL، شناسه و تاریخ LTR باقی ماندند. |
| toolbar اکسل | CTAها به «دریافت فایل»، «دریافت نمونه» و «ورود فایل» کوتاه شدند؛ راهنمای فقط‌خروجی از متن نادرست «ورود و خروج» جدا شد؛ `aria-controls` و `aria-expanded` برای پنل import افزوده شد. | toolbar مدیریت مشتریان و تیکت‌ها در 390×844 بدون شکستن CTAها نمایش داده شد. |
| نگارش و microcopy | «لطفا» به «لطفاً»، «کد تایید» به «کد تأیید»، پیام‌های موفقیت نظرسنجی کوتاه، و CTA مبهم پروفایل به «تکمیل بعداً» تبدیل شد. | پیام‌ها کوتاه‌تر، یکدست‌تر و بدون تکرار معنایی شدند. |
| سازگاری تنظیمات موجود | `login_subtitle_value()` مقدار legacy قدیمی را فقط در صورت خالی‌بودن یا برابر بودن با مقدار legacy به copy جدید ارتقا می‌دهد و متن سفارشی مدیر را حفظ می‌کند. | copy جدید بدون migration مخرب روی دیتابیس‌های موجود نیز در صفحه ورود و تنظیمات نمایان می‌شود. |

### شواهد و اعتبارسنجی گذر سوم

| آزمون | نتیجه |
|---|---|
| lint کامل PHP خارج از `vendor` | PASS؛ خروجی در `rtl-qa-round3/php-lint.log` ذخیره شد. |
| `git diff --check` | PASS. |
| runtime font check در admin/customer | PASS؛ font family برابر `Vazirmatn, Tahoma, Arial, sans-serif`، فونت local loaded و CDN font requests برابر صفر. |
| screenshots واقعی 390×844 | PASS برای تیکت، فاکتور و اعلان مشتری و مشتری/تیکت مدیریت. |
| title و CTA wrapping | PASS؛ عناوین کوتاه موبایل و toolbar اکسل تک‌خطی و قابل لمس باقی ماندند. |

Asset فونت با مجوز SIL Open Font License 1.1 در `assets/fonts/OFL-Vazirmatn.txt` ثبت شده است. فایل‌های نشست، cookie، header و HTML آزمون از artifacts حذف شدند و فقط تصاویر QA، گزارش typography و lint log باقی مانده‌اند.
