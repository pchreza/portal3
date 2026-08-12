# Backlog اجرایی و دروازهٔ فازها — نسخهٔ ۰.۱

## ۱. قاعدهٔ اولویت

Backlog بر اساس وابستگی فنی، ریسک محرمانگی و ارزش عملیاتی مرتب شده است. هر Item باید یک مالک ماژول، معیار پذیرش، تست واحد/Feature و وضعیت داشته باشد. هیچ Item دارای نقص امنیتی Critical یا شکست تست Tenant Isolation قابل Done شدن نیست.

## ۲. Epicها و اولویت‌ها

| اولویت | Epic | Itemهای اصلی | وابستگی |
|---|---|---|---|
| P0 | پایهٔ مهندسی | معماری ماژولار، strict types، تنظیمات محیط، Error ID، CSRF، migrations، logging، RTL shell، Vazirmatn | هیچ |
| P0 | SaaS و نصب | installer، قفل نصب، SA، clinic lifecycle، plan/feature، clinic context، HTTPS check | پایهٔ مهندسی |
| P0 | امنیت و حساب | login، session، brute-force، password reset مدیریتی، چندکلینیکی، role/permission | SaaS و نصب |
| P0 | ساختار کلینیک | branch، room، unit، staff، schedules، clinic settings، branding | SaaS و امنیت |
| P0 | بیمار و QR | persons، memberships، patient profile، QR intake، review queue، allergy، medical history | کلینیک و امنیت |
| P0 | نوبت و تقویم | Jalali conversion، daily/weekly/monthly، schedules، blocks، conflicts، emergency، series | بیمار و ساختار کلینیک |
| P0 | درمان | FDI، tooth state، services، workflow، plans، notes، attachments | بیمار و نوبت |
| P1 | مالی ساده | service price snapshot، invoice، payment، wallet، installments، discount approval، receipt | خدمات و بیمار |
| P1 | گزارش | dashboard widgets، five reports، filters، CSV/XLSX/print | نوبت و مالی |
| P1 | اعلان و پشتیبانی | in-app notification، conversation/ticket، error reports، support, training | حساب و نقش |
| P1 | عملیات | backup/restore، maintenance mode، upgrade wizard، health check | installer و migrations |
| P2 | IPPanel | provider adapter، patterns، usage quota، logs، retries | اعلان و plan |
| P2 | قابلیت آینده | PWA، زرین‌پال، رادیولوژی link/file، بیمه کامل، API/Webhook، import | هستهٔ پایدار |

## ۳. فاز ۰ — موارد تکمیل‌شده

| شناسه | خروجی | وضعیت |
|---|---|---|
| SPEC-001 | SRS قابل آزمون | انجام شد در `docs/SRS-v0.1.md` |
| SPEC-002 | ماتریس نقش/مجوز | انجام شد در `docs/permission-matrix-v0.1.md` |
| SPEC-003 | مدل منطقی داده و Tenant Isolation | انجام شد در `docs/data-model-v0.1.md` |
| SPEC-004 | Backlog اولویت‌دار | همین سند |
| SPEC-005 | سناریوهای پذیرش | در `docs/acceptance-tests-v0.1.md` |
| SPEC-006 | baseline repository و ریسک مهاجرت | در `docs/phase-0-report.md` |

## ۴. فاز ۱ — پایهٔ مهندسی

| شناسه | Item | معیار Done |
|---|---|---|
| F1-001 | قفل‌کردن runtime/dependencies | PHP target، Composer و extensions در مستندات و CI مشخص باشند. |
| F1-002 | ساختار modular PHP | مرزهای Domain/Application/Infrastructure/Presentation و autoload واضح باشند. |
| F1-003 | env/config hardening | secrets خارج از repo، config validation و production defaults امن باشند. |
| F1-004 | migration runner | version table، transaction policy، status و lock رقابت داشته باشد. |
| F1-005 | error handling | خطای کاربر فارسی با tracking code و خطای فنی در log امن ثبت شود. |
| F1-006 | security primitives | CSRF، escaping، validation، session hardening و rate limiting reusable باشند. |
| F1-007 | RTL shell | `lang=fa`, `dir=rtl`, logical CSS، local font و layout responsive آماده باشد. |
| F1-008 | test harness | PHPUnit unit/feature، fixtures، test database strategy و static analysis قابل اجرا باشند. |

## ۵. فاز ۲ و ۳ — هسته و دسترسی

| شناسه | Item | معیار Done |
|---|---|---|
| F2-001 | install wizard | نصب خالی cPanel بدون SSH، تست DB، ساخت SA و قفل installer. |
| F2-002 | clinic lifecycle | create/status/archive/restore، plan/feature و limits. |
| F2-003 | tenant context | هر request کلینیکی context اجباری و cross-tenant tests. |
| F3-001 | authentication | login/logout, password policy, lockout, sessions. |
| F3-002 | membership | کاربر چندکلینیکی و انتخاب clinic فعال. |
| F3-003 | RBAC/overrides | permission matrix، per-user override و audit. |
| F3-004 | clinic organization | branch/room/unit/staff/schedule. |

## ۶. فاز ۴ تا ۶ — جریان اصلی کلینیک

| شناسه | Item | معیار Done |
|---|---|---|
| F4-001 | QR intake | token، فرم، duplicate check، queue و review actions. |
| F4-002 | patient record | هویت، پزشکی، دندان‌پزشکی، allergies، notes و files. |
| F4-003 | patient portal | permissions، profile change approval و clinic selector. |
| F5-001 | Jalali calendar | conversion tests، day/week/month و local datepicker. |
| F5-002 | appointment rules | schedules، blocks، conflicts، series، emergency، waitlist. |
| F5-003 | time tracking | start/stop، duration و audit. |
| F6-001 | tooth chart | FDI، dentition، states، surfaces و accessibility alternative. |
| F6-002 | treatment | service catalog، workflow، treatment plan و status history. |
| F6-003 | image/note rules | permission، max size/type، public/private و audit. |

## ۷. فاز ۷ تا ۹ — ارزش افزوده و عملیات

| شناسه | Item | معیار Done |
|---|---|---|
| F7-001 | invoice/payments | snapshot price، partial allocation، wallet و receipt. |
| F7-002 | installments | manual/auto schedule، statuses و in-app reminder. |
| F7-003 | reports | five reports، common filters، CSV/XLSX/print. |
| F8-001 | notifications | recipient states، event routing، read/action/expiry. |
| F8-002 | support/error | report button، ticket flow، tracking code و SA console. |
| F8-003 | training | SA authoring، member visibility و Persian content. |
| F8-004 | backup/restore | full clinic/system backup، retention و restore drill. |
| F9-001 | IPPanel adapter | secret isolation، pattern mapping، quota، logs/retry. |

## ۸. قواعد انتقال فاز

فاز جاری باید کد و مستنداتش را داشته باشد، test suite سبز باشد، آسیب‌پذیری Critical/High بدون تصمیم باز باقی نماند، گزارش دیباگ تولید شود و سناریوهای پذیرش همان فاز توسط کاربر قابل اجرا باشند. انتقال به فاز بعد تنها پس از تأیید صریح کاربر انجام می‌شود. در صورت شکست، Item به حالت Fix با علت ریشه‌ای و regression test برمی‌گردد.
