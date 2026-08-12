# Typography and font asset audit

## Findings

| Area | Current state | Decision |
| --- | --- | --- |
| Font source | Admin, customer and public layouts load `Vazirmatn-font-face.css` from jsDelivr v33.003. | Replace runtime CDN dependency with a pinned local variable WOFF2 asset and local `@font-face`. |
| Font payload | The variable asset `Vazirmatn[wght].woff2` is 111,152 bytes and covers weights 100–900. | Use one `font-display: swap` asset instead of multiple weight requests. |
| License | Upstream v33.003 exposes `OFL.txt`. | Copy OFL text beside the local font asset and document source/version. |
| CSS caching | `portal-ui.css` is linked without an asset version query. | Add file-mtime versioning in the shared helper for cache-safe updates. |
| Text wrapping | Global rules lack explicit overflow-wrap/text-wrap policies; several content cells use `truncate`, `line-clamp-2` or `whitespace-nowrap`. | Keep nowrap only for technical values and compact chips; add content-driven text utilities and targeted mobile overrides for headings/actions/cells. |
| Existing fixed heights | Design-system buttons intentionally use fixed interaction heights; text headings no longer use fixed heights. | Preserve touch target heights while removing fixed heights from text-bearing components. |

## Source measurements

The local candidate was fetched from the pinned upstream release `v33.003`:

- `Vazirmatn[wght].woff2`: 111,152 bytes.
- `OFL.txt`: 4,391 bytes.
- Variable CSS declares `font-weight: 100 900` and `font-display: swap`.

## Mobile verification

| Page | Viewport | Result |
| --- | --- | --- |
| `customer/tickets.php` | 390×844 | PASS؛ نوار بالا عنوان کوتاه «تیکت‌ها» را در یک خط نشان می‌دهد، subjectهای مختلط در کارت‌ها readable هستند و تاریخ/شناسه‌های فنی LTR باقی می‌مانند. |
| `customer/invoices.php` | 390×844 | PASS؛ نوار بالا «فاکتورها» تک‌خط است، heading «فاکتورهای صادرشده (1)» بدون شکست غیرضروری نمایش داده می‌شود و عنوان فاکتور دیگر عمودی نمی‌شکند. |
| `customer/notifications.php` | 390×844 | PASS پس از اصلاح | ردیف عنوان و تاریخ در mobile به ستون مستقل تبدیل شد؛ عنوان اعلان اول از چهار خط فشرده به دو خط خوانا کاهش یافت، تاریخ و URL همچنان LTR و isolate باقی ماندند. |
| `admin/customers.php` | 390×844 | PASS | CTAهای toolbar به «دریافت فایل»، «دریافت نمونه» و «ورود فایل» کوتاه شدند و هرکدام تک‌خطی/قابل لمس باقی ماندند؛ راهنمای XLSX/CSV نیز بدون clipping است. |
| `admin/tickets.php` | 390×844 | PASS | CTA خروجی و راهنمای toolbar تک‌خطی‌اند؛ فیلترها به یک ستون reflow می‌شوند و عنوان صفحهٔ مدیریت بدون شکست ناخواسته نمایش داده می‌شود. |
