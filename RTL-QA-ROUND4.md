# RTL / Accessibility QA — Round 4

## Scope

بازبینی بصری روی صفحهٔ داشبورد مشتری پس از اعمال CSP، assetهای local، خروج POST، و اصلاحات reflow انجام شد.

## Findings

| موضوع | نتیجه |
|---|---|
| زبان و direction سند | `lang="fa"` و `dir="rtl"` صحیح و قابل مشاهده بود. |
| navigation مشتری | روی لبهٔ leading راست قرار دارد؛ ترتیب خواندن و ترتیب focus با ساختار DOM هم‌خوان است. |
| actionهای قابل دسترس | skip-link، خروج، اعلان‌ها، theme toggle و گزارش خطا در tree تعاملی صفحه حاضر بودند. |
| دادهٔ mixed RTL/LTR | URL، شناسهٔ release، شمارهٔ invoice، مبلغ و تاریخ در خروجی قابل تشخیص و جداشده نمایش داده شدند. |
| فونت و asset | فونت Vazirmatn و CSS محلی از مسیر پروژه ارائه شدند و وابستگی runtime به CDN در PHP باقی نمانده است. |
| layout دسکتاپ | کارت‌های dashboard و navigation بدون overflow آشکار در viewport بازبینی‌شده رندر شدند. |

## Follow-up automated checks

اسکن label/aria روی ۲۰ صفحهٔ اصلی مدیر و مشتری با نتیجهٔ `A11Y_LABEL_SCAN_PASS` و اسکن inline event handler با نتیجهٔ صفر مورد انجام شد. برای viewportهای ۳۲۰px نیز breakpoint منطقی در `assets/portal-ui.css` اضافه شده است؛ آزمون دستی ۳۲۰px باید در محیط staging واقعی با Chrome DevTools و screen reader تکمیل شود.
