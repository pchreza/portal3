# Worklog — FULLMASTER UI/UX Redesign (portal3)

**Agent:** Senior UI/UX Engineer & Design System Architect
**Task:** بازطراحی لایه presentation پورتال (پوسته + هسته) بدون دست‌زدن به business logic/API/db/auth
**Execution mode:** FULL_REDESIGN (supervised) — scope تأییدشده: پوسته مشترک + ورود + داشبوردها + مشتری/تیکت (فرم/لیست) + هماهنگی سراسری.

---

## Design System (جدید)

- **`assets/portal-ui.css`** (فایل جدید — منبع حقیقت واحد)
  - Semantic tokens (light + dark): رنگ، spacing، radius، shadow، focus-ring.
  - Remap سراسری کلاس‌های Tailwind (`bg-white`, `text-slate-*`, `border-slate-*`, ...) به توکن‌ها → یکپارچگی خودکار + dark mode در همه صفحات بدون لمس هر صفحه.
  - کلاس‌های کامپوننت: `.btn` (primary/secondary/ghost/danger/outline-danger + sm/lg/icon)، `.input`, `.label`, `.card`, `.badge`, `.alert`, `.table`, `.nav-item`, `.skeleton`, `.skip-link`.
  - `prefers-reduced-motion` و `focus-visible` سراسری.

## Work Log (خلاصه)

### Pass 1 — پوسته + هسته
| File | kind | logic_touched | rationale |
|------|------|---------------|-----------|
| assets/portal-ui.css | structural(new) | false | توکن‌ها + کامپوننت‌ها + remap سراسری + دارک‌مود |
| includes/functions/helpers.php | structural | false | icon()، portal_ui_*، مودال تأیید حذف |
| includes/functions/notifications.php | visual | false | ایموجی → SVG در notification_type_icon |
| includes/layout/* | structural | false | CSS، skip-link، toggle، main#main-content، مودال |
| admin/sidebar.php, customer/sidebar.php | visual | false | SVG + nav-item + aria |
| index.php, admin/{index,customers,tickets}.php, customer/index.php | visual | false | کارت/دکمه/جدول کامپوننت + delete قابل‌دسترس |
| admin/{products,projects,invoices,surveys,custom_fields,ticket-departments,notifications,admins}.php | visual | false | confirm()→مودال + کلاس کامپوننت |
| install.php | visual | false | کلاس alert |

### Pass 2 — یکپارچگی سراسری + دسترس‌پذیری
| File | kind | logic_touched | rationale |
|------|------|---------------|-----------|
| admin/settings.php | visual | false | ایموجی لیبل‌ها → SVG (icon()) |
| admin/{surveys,profile,products,projects}.php، customer/{profile,surveys}.php | visual | false | ایموجی سربرگ/دکمه → SVG |
| customer/index.php | visual | false | پاک‌سازی ویجت‌ها و بنرها (emoji→SVG، حذف icon مرده) |
| assets/portal-ui.css | structural | false | کلاس validation + card-stack موبایل |

### Pass 3 — اعتبارسنجی + سلسله‌مراتب + h1
| File | kind | logic_touched | rationale |
|------|------|---------------|-----------|
| includes/functions/helpers.php | structural | false | اسکریپت validation inline |
| includes/layout/{admin,customer,public}_footer.php | structural | false | include اسکریپت validation |
| index.php، admin/{customers,projects,products,invoices,profile}.php، customer/profile.php | visual | false | novalidate + error-summary + field-error (inline validation) |
| admin/index.php، admin/{projects,products,invoices,tickets,surveys,custom_fields,ticket-departments,notifications,logs,admins}.php، customer/* | visual | false | جدول → `.table table-card-mobile` (card-stack موبایل) |
| admin/sidebar.php, customer/sidebar.php | visual | false | برند h1 → div (یک h1 در هر صفحه) |
| includes/layout/{admin,customer}_header.php | visual | false | عنوان صفحه `<h1>` |
| سراسری | visual | false | حذف text-[10px]/[11px] → text-xs |

**logic_touched = false در همه موارد.** هیچ query، اعتبارسنجی سمت سرور، CSRF، auth، پیامک، اکسل، schema یا مسیر API تغییر نکرد.

## Functional Regression (تأییدشده با POST واقعی)
- ورود ادمین و مشتری ✅
- ثبت مشتری جدید (DB) ✅ · حذف مشتری (DB) ✅
- همه ۲۳ صفحه بدون خطا و بدون صفحهی «خطای اتصال» رندر می‌شوند ✅


---

## Pass 5 — ۴ پیشنهاد بازطراحی هدفمند (دونه‌به‌دونه)

### پیشنهاد ۱: نظرسنجی (عمومی + پنل مشتری)
| File | kind | logic_touched | rationale |
|------|------|---------------|-----------|
| survey-public.php | visual | false | فرم کامل با کارت سؤال، progress-bar، segmented بله/خیر، ستاره/۱-۱۰ بزرگ، submit sticky موبایل، stateهای done/already/error با سیستم طراحی |
| customer/surveys.php | visual | false | نمای take با progress-bar + کارت سؤال + touch-target ۴۴px + mobile-action-bar؛ لیست فرم‌ها با card/empty-state |
| تأیید | — | — | ثبت پاسخ مشتری (۳ جواب ذخیره) ✓ و عمومی (۳۰۲→done) ✓ |

### پیشنهاد ۲: تیکت به‌صورت گفتگوی چتی
| File | kind | logic_touched | rationale |
|------|------|---------------|-----------|
| customer/tickets.php | visual | false | thread چتی با آواتار/حباب/زمان، reply sticky، فرم جدید novalidate، لیست با badge |
| admin/tickets.php | visual | false | thread چتی، کنترل وضعیت با دکمهٔ صریح + مودال تأیید (به‌جای confirm بومی)، فیلترها/لیست با badge |
| assets/portal-ui.css | structural | false | CSS چت (chat-row/bubble/avatar/reply-box) |
| تأیید | — | — | تغییر وضعیت تیکت (POST) ✓ · ارسال پاسخ ✓ · confirm بومی حذف شد ✓ |

### پیشنهاد ۳: مرکز اعلانات
| File | kind | logic_touched | rationale |
|------|------|---------------|-----------|
| customer/notifications.php | visual | false | card-list با آیکن رنگی، نشان خوانده‌نشده، دکمهٔ «خواندن همه»، empty-state |
| admin/notifications.php | visual | false | فرم ارسال novalidate + کارت آمار با آیکن + لیست با badge/progress + حذف ایموجی |
| تأیید | — | — | ارسال اعلان (POST) → ۱ اعلان + ۱ گیرنده ✓ |

### پیشنهاد ۴: تنظیمات ادمین (UX)
| File | kind | logic_touched | rationale |
|------|------|---------------|-----------|
| admin/settings.php | visual | false | تب با آیکن + حالت فعال بهتر؛ ردیف‌های سوییچ با باکس آیکن/پیل وضعیت؛ حذف ایموجی سربرگ‌ها؛ استانداردسازی دکمه‌ها |
| تأیید | — | — | هر ۶ تب رندر ✓ · ذخیره ماژول‌ها (غیرفعال‌سازی) ✓ |

### ضمیمه: دسترس‌پذیری دکمه‌های تاریخ
- admin/{invoices,products,projects,notifications}.php + customer/{profile,surveys}.php: افزودن `aria-label` به دکمه‌های تقویم (jdp-trigger) و حذف ایموجی 📅.

### QA نهایی
- `php -l` روی کل پروژه ✓ · همه ۳۰ حالت (۱۴ ادمین + ۸ مشتری + لاگین × ۱۲۸۰/۳۷۵) پاک ✓
- ۰ overflow · ۱ h1 در هر صفحه · ۰ کنترل بدون نام · ۰ تصویر بدون alt ✓
- تعاملات JS (دارک/توست/مودال/validation) ✓ · فلوی POST (مشتری/تیکت/نظرسنجی/اعلان) ✓

---

## Pass 6 — فرم ورود ۳ مرحله‌ای + طرح‌های قابل‌انتخاب صفحه ورود

### فرم ورود موبایل (OTP) — ۳ مرحله
| File | kind | logic_touched | rationale |
|------|------|---------------|-----------|
| index.php | visual | false | رندر ۳ مرحله با شاخص stepper (شماره موبایل → کد تایید → ورود)؛ فقط presentation، لاجیک send_otp/verify_otp دست‌نخورده |
| تأیید | — | — | فلوی کامل: ارسال کد → کد در session → تأیید → 302 به dashboard ✓ |

### طرح‌های صفحه ورود (انتخاب در تنظیمات)
| File | kind | logic_touched | rationale |
|------|------|---------------|-----------|
| includes/functions/helpers.php | structural | false | افزودن `login_layout_options()` و `active_login_layout()` (۴ طرح) |
| admin/settings.php | visual | false | ذخیره `login_layout` + بخش انتخاب طرح در تب عمومی با پیش‌نمایش مینیاتوری |
| index.php | visual | false | رندر ۴ طرح: centered، split (تصویر+فرم)، branded (گرادیان)، minimal |
| assets/login-side.jpg | asset | false | تصویر سمت طرح split (تولیدشده) |

### طرح‌ها
1. **centered** (پیش‌فرض): کارت متمرکز روی پس‌زمینه ساده.
2. **split**: تصویر برند/گرادیان در یک سمت + فرم در سمت دیگر (دسکتاپ)؛ در موبایل به تک‌ستون تبدیل می‌شود.
3. **branded**: پس‌زمینه گرادیانی برند با کارت شیشه‌ای.
4. **minimal**: فرم ساده بدون کارت.

### QA
- هر ۴ طرح + ۲ مرحله OTP در مرورگر تست و اسکرین‌شات شد (رنگ‌ها/طرح متمایز).
- فلوی OTP کامل تأیید (کد → ورود → 302).
- `php -l` کل پروژه ✓ · QA ۳۰ حالت پاک (۰ overflow، ۱ h1، ۰ کنترل بدون نام) ✓

---

## Pass 7 — تنظیمات کامل صفحه ورود + منوی هدر

### 1) انتخاب زنده طرح صفحه ورود
- رادیوهای طرح با CSS `:has(input:checked)` حالا به‌صورت زنده (بدون نیاز به ذخیره) هایلایت می‌شوند (حاشیه + نقطه تیک).

### 2) طرح دوطرفه — کاملاً قابل تنظیم
- نسبت تصویر/فرم (۴۰–۷۵٪) با اسلایدر
- سمت فرم: راست یا چپ
- تصویر دسکتاپ + تصویر جداگانه موبایل (آپلود + حذف)
- عنوان، زیرعنوان، ۳ ویژگی (عدد + برچسب)

### 3) طرح گرادیان برند — رنگ‌بندی قابل تنظیم
- دو انتخاب‌گر رنگ (آغاز/پایان) + پیش‌نمایش زنده + تصویر موبایل جداگانه

### 4) مینیمال — رفع لوگوی دوتایی
- حذف لوگوی تکراری؛ فقط یک لوگو (در render_login_form) باقی می‌ماند.

### 5) تصویر پس‌زمینه دلخواه صفحه ورود
- آپلود تصویر پس‌زمینه دسکتاپ + تصویر جداگانه موبایل (در همه طرح‌ها).

### 6) منوی سفارشی هدر
- بخش جدید در تنظیمات عمومی: افزودن/حذف آیتم‌های منو (متن + لینک + تب جدید/همان تب).
- در صفحه لاگین (بالا-چپ) و در هدر پنل ادمین/مشتری نمایش داده می‌شود.
- کلاس `.header-menu-link` برای مخفی شدن در موبایل و نمایش در md+ (رفع overflow).

### فایل‌ها
- includes/functions/helpers.php: `login_config()`, `sanitize_hex_color()`, `upload_login_image()`, `header_menu_items()`, `save_header_menu_items()`
- admin/settings.php: ذخیره و فرم کامل تب عمومی
- index.php: رندر ۴ طرح با تنظیمات + منوی هدر + پس‌زمینه
- includes/layout/{admin,customer}_header.php: منوی هدر
- assets/portal-ui.css: `.header-menu-link`

---

## Pass 8 — رفع باگها و بهبودها (دونه‌به‌دونه)

### 1) سوالات نظرسنجی
- رفع تگ شکسته `</a>` (به‌جای `/a&gt;`) در دکمه «مشاهده گزارش»
- افزودن دکمه «ویرایش» برای هر سؤال + فرم ویرایش inline (متن + نوع) + هندلر `update_question`
- تمیزسازی دکمه‌ها (btn component)

### 2) اعلانات — رندر تمیز
- اصلاح هدر «ارسال اعلان جدید» با فاصله‌گذاری مناسب (px-6 py-5، mt-1.5)

### 3) جزئیات اعلان — رفع Warning
- اصلاح کوئری `SELECT * FROM notifications` به `JOIN users` برای کلیدهای first_name/last_name/username
- اعلان سیستمی (creator=null) → «فرستنده: سیستم» بدون خطا

### 4) هدر تب‌های تنظیمات
- اصلاح فاصله عنوان/زیرعنوان (h3 بدون h-3، mt-2، leading-relaxed) در همه تب‌ها

### 5) ماژول‌های اعلانات و گزارش فعالیت
- افزودن `notifications` و `logs` به لیست ماژول‌ها (ذخیره + نمایش) و سایدبار ادمین

### 6) رویدادهای پیامکی — ذخیره
- ریشه: فرم sms_events دکمه submit نداشت → افزودن «ذخیره رویدادهای پیامکی»
- تأیید: toggle فعال/غیرفعال در DB ذخیره می‌شود

### 7) کرون‌جاب
- اصلاح لینک به مسیر مطلق کامل (`/usr/bin/php -q /مسیر/cron_survey_reminder.php`)

### 8) نمایش شرطی بخش‌های تنظیمات
- بخش «تنظیمات دوطرفه» فقط وقتی طرح split انتخاب شود نمایش داده می‌شود
- بخش «تنظیمات گرادیان» فقط وقتی طرح branded انتخاب شود نمایش داده می‌شود (JS + data-layout-show)

### 9) تراز منوی هدر
- افزودن کنترل جایگاه منو (راست/وسط/چپ) + اعمال در index.php

### 10) موقعیت عمودی فرم دوطرفه
- افزودن کنترل (بالا/وسط/پایین) + اعمال در index.php

### تأیید
- php -l کل پروژه ✓ · QA ۳۰ حالت پاک ✓ · همه باگ‌ها تست شدند ✓

---

## Pass 9 — داشبورد، دکمه گزارش خطا، منوی هدر

### 1) داشبورد ادمین — متن دسترسی سریع / مشتریان اخیر / آخرین فعالیت
- حذف کلاس `h-3` (تیپ‌اسکیل بزرگ) از عنوان‌های سکشن → `text-sm` فشرده (تأیید: 14px)
- رفع همپوشانی متن «دسترسی سریع» با دکمه‌ها
- اعمال در داشبورد ادمین و مشتری

### 2) دکمه شناور «گزارش خطا» + فرم
- جدول `error_reports` از طریق مهاجرت ۲۲
- دکمه شناور + مودال گزارش در همه صفحات (admin/customer/public) از طریق `error_report_widget()`
- پردازش سراسری `report_error` در config.php (CSRF-safe)
- نقش/نام/آدرس/پیام ذخیره می‌شود
- فقط برای سوپر ادمین/مدیر با دسترسی `error_reports` قابل مشاهده
- فلش پیام بعد از ارسال

### 3) صفحه مدیریت گزارش‌های خطا
- `admin/error-reports.php`: آمار (جدید/بررسی/حل‌شده) + جدول گزارش‌ها + تغییر وضعیت + حذف
- لینک در سایدبار ادمین

### 4) ماژول گزارش خطا
- افزودن `module_error_reports` به ماژول‌ها + سایدبار (OFF=مخفی شدن دکمه و لینک)

### 5) منوی هدر
- رفع نمایش منوی سفارشی در صفحه لاگین (حذف از index.php — فقط در هدر پنل‌ها)
- رفع تراز در پنل: منو در جایگاه absolute بر اساس `header_menu_align` (راست/وسط/چپ) — تأیید مرورگر
- `relative` به هدر پنل‌ها اضافه شد

### تأیید
- php -l کل پروژه ✓ · QA ۳۰ حالت پاک ✓ · همه باگ‌ها تست شدند ✓
