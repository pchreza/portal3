# FULLMASTER — UI Audit Report (portal3)

## Project Facts

- **Framework:** PHP خالص (بدون فریم‌ورک) + PDO/MySQL — سرویسمحور؛ هر صفحه `require_once 'auth.php'` + رندر با `render_*_header()/footer()`.
- **Styling:** Tailwind CSS 4 (حالت CDN — کامپایل در مرورگر) + فونت Vazirmatn + RTL (`dir="rtl" lang="fa"`).
- **Design System:** سیستم پالت رنگی قابل تنظیم ادمین (`theme_palettes()` + `theme_styles()`) که کلاسهای indigo تیلویند را با متغیر CSS اوورراید میکند. لایه token محدود و ad-hoc.
- **Routing:** صفحات فیزیکی `.php` در پوشههای `admin/`، `customer/`، ریشه (ورود/نصب/نظرسنجی عمومی).
- **State/Theme:** session؛ تم از `settings.site_theme` (پیشفرض کد: `indigo` — در محیط تست من `emerald` قرار گرفت تا از آبی/ایندیگؤ ممنوعه دور بمانم).
- **i18n:** فارسی، RTL، تاریخ شمسی (JalaliDatePicker).

## Screen Inventory

| Route | Purpose | Primary Task | Primary Action |
|-------|---------|--------------|----------------|
| `/` (index.php) | ورود (نامکاربری یا OTP) | احراز هویت | «ورود به پنل» |
| `/admin/index.php` | داشبورد ادمین | دیدن وضعیت | (لینکهای دسترسی سریع) |
| `/admin/customers.php` | مدیریت مشتریان (لیست/افزودن/ویرایش/حذف) | تعریف/مدیریت مشتری | «افزودن مشتری جدید» |
| `/admin/projects.php` | مدیریت پروژهها | انتصاب پروژه به مشتری | «تعریف پروژه» |
| `/admin/products.php` | مدیریت محصولات | انتصاب محصول | «تعریف محصول» |
| `/admin/invoices.php` | مدیریت فاکتورها | صدور/ویرایش فاکتور | «صدور فاکتور» |
| `/admin/tickets.php` | مدیریت تیکتها | پاسخ/تغییر وضعیت تیکت | «پاسخ» |
| `/admin/surveys.php` | مدیریت نظرسنجیها | ساخت/مدیریت نظرسنجی | «نظرسنجی جدید» |
| `/admin/settings.php` | تنظیمات سیستم (تم/ماژول/فیلد اجباری) | پیکربندی | «ذخیره» |
| `/admin/logs.php`, `notifications.php`, `custom_fields.php`, `ticket-departments.php`, `admins.php`, `profile.php` | پنلهای کمکی | مدیریت موردی | — |
| `/customer/index.php` | داشبورد مشتری | دیدن پروژه/محصول/فاکتور | «ارسال تیکت جدید» |
| `/customer/tickets.php` | تیکتهای مشتری | ارسال/پیگیری تیکت | «ارسال تیکت» |
| `/customer/profile.php` | پروفایل مشتری | تکمیل اطلاعات | «ذخیره» |
| `/customer/{projects,products,invoices,surveys,notifications}.php` | پنلهای مشتری | مشاهده/مطالبه | — |

## Design Token Inventory

- **Colors:** پالت ادمین (indigo/emerald/blue/rose/orange/violet/slate) + رنگهای ad-hoc در صفحات (sky, amber, blue, violet, rose, pink).
- **Typography:** فقط Vazirmatn؛ اندازهها ناهموار (`text-[10px]`, `text-[11px]`، پراکندگی size).
- **Spacing:** تا حدی از مقیاس Tailwind (px-4، p-6) اما بیکران (rounded-lg/xl/2xl ناهماهنگ).
- **Radius:** سه مقدار رایج همزمان (lg/xl/2xl) بدون قاعده مشخص.
- **Shadows:** shadow-sm / shadow-lg / shadow-xl / shadow-md پراکنده.

## Inconsistencies

| Type | Where | Evidence | Severity |
|------|-------|----------|----------|
| Magic number | سراسری | `text-[10px]`, `text-[11px]` (29 مورد) خارج از scale | Medium |
| رنگ rainbow | داشبورد ادمین/مشتری | باکس آیکن هر کارت رنگ متفاوت (indigo/emerald/amber/blue/rose/violet) | High |
| Radius نامنظم | سراسری | rounded-lg/xl/2xl برای سطح یکسان | Medium |
| Icon غیراستاندارد | سراسری | ایموجی بهعنوان آیکن کاربردی (📦👥🎫💳☰🔔 …) | High |
| بدون aria-label | سایدبار/زنگوله | آیکنهای تنها بدون label | High |
| Delete غیردسترسپذیر | ۱۰ صفحه | `onsubmit="return confirm(...)"` بدون گفتگوی دسترسپذیر | High |
| چند primary هموزن | برخی صفحات | دکمههای filled متعدد | Medium |
| سایهها/کرنر ناهماهنگ | کارتها | shadow و radius متفاوت برای کارت همجنس | Low |

## Issues by Category

- **Visual Hierarchy:** چند action رقیب؛ رنگ برای تأکید کمتنظیم (رنگینکمانی بهجای یک accent).
- **Component Reuse:** فرمها الگوی input یکسان را کپی میکنند؛ هیچ کلاس کامپوننت (btn/input/card/badge) واحدی نیست.
- **Responsive:** منوی موبایل هک (fixed positioning)؛ جدولها بدون استراتژی موبایل (فقط overflow-x-auto)؛ بدون action-bar پایین فرم.
- **Accessibility:** آیکنهای ایموجی بدون label؛ delete با confirm()؛ بدون skip-link؛ focus ring ناهماهنگ؛ چند `<h1>` در برخی صفحات؟ (بررسی شد: عنوان صفحه `<h2>` در header و h1 در body — خطر چند h1).

## Baseline Score

`CURRENT UI SCORE` به تفکیک ابعاد (برآورد اولیه بر اساس audit) — در فاز ۷ دقیق بازبینی میشود:

| Dimension | Score /10 |
|-----------|-----------|
| visual_hierarchy | 5 |
| typography | 5 |
| spacing | 6 |
| color_system | 5 |
| component_consistency | 4 |
| navigation | 6 |
| interaction | 5 |
| accessibility | 4 |
| responsive_design | 5 |
| cognitive_load | 6 |
| professionalism | 5 |
| **میانگین** | **~5.1 → 51/100** |
