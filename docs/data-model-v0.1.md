# مدل منطقی داده و جداسازی مستأجر — نسخهٔ ۰.۱

## ۱. اصول طراحی

پایگاه داده MySQL با موتور InnoDB و `utf8mb4` استفاده می‌شود. شناسهٔ داخلی برای Join و کارایی عددی است و شناسهٔ عمومی غیرقابل‌حدس برای URL و خروجی‌ها ایجاد می‌شود. تمام جداول عملیاتی مستأجرمحور باید `clinic_id` داشته باشند یا از یک رابطهٔ قطعی به رکوردی دارای `clinic_id` برسند. حذف‌های آبشاری برای داده‌های درمانی، مالی، ممیزی و اسناد اصلی ممنوع است؛ بایگانی و وضعیت حذف‌شده باید جایگزین حذف مستقیم شود.

هر زمان متنی از کاربر ذخیره می‌شود، `created_by`، `updated_by` و زمان‌های ایجاد/ویرایش در نظر گرفته می‌شوند. عملیات مالی از تراکنش دیتابیس استفاده می‌کنند. مبلغ‌ها در واحد تومان با Decimal دقیق و بدون Float ذخیره می‌شوند. زمان‌های محاسباتی با UTC/منطقهٔ زمانی سیستم و تاریخ‌های نمایشی با جلالی مدیریت می‌شوند.

## ۲. هستهٔ هویت و مستأجری

| موجودیت | فیلدهای کلیدی | روابط و قواعد |
|---|---|---|
| `platform_settings` | key, value, type, updated_by | تنظیمات محصول، برند سراسری، فونت، حالت تعمیرات |
| `clinics` | public_id, code, name, status, plan_id, starts_at, ends_at, branding, archived_at | تنها SA ایجاد می‌کند؛ code در کل سامانه یکتا |
| `clinic_memberships` | clinic_id, person_id/user_id, role, status, joined_at, archived_at | جداسازی عضویت کاربر/بیمار در هر کلینیک |
| `users` | public_id, username/mobile, password_hash, role, status, password_changed_at | هویت کارکنان و مدیران؛ نام کاربری یکتا |
| `persons` | public_id, mobile_normalized, national_id_hash, name, birth_date, gender | هویت قابل عضویت در چند کلینیک؛ کد ملی خام حداقل نگهداری شود |
| `roles` | clinic_id nullable, code, name, is_system | نقش سیستم یا نقش سفارشی کلینیک |
| `permissions` | code, module, action, label | فهرست کنترل‌شدهٔ مجوزها |
| `user_permission_overrides` | clinic_id, user_id, permission_id, effect, reason | Override فردی، کلید یکتا برای کاربر/کلینیک/مجوز |
| `sessions` | user_id, clinic_id, token_hash, device, last_activity_at, revoked_at | نشست‌های قابل لغو و خروج از همهٔ دستگاه‌ها |
| `login_attempts` | username, ip, success, occurred_at, reason | کنترل بروت‌فورس و ممیزی |
| `support_impersonations` | admin_id, clinic_id, reason, started_at, ended_at | ورود پشتیبانی با لاگ اجباری |

## ۳. ساختار کلینیک

| موجودیت | فیلدهای کلیدی | نکته |
|---|---|---|
| `branches` | clinic_id, code, name, address, status | شماره/کد در محدودهٔ کلینیک یکتا |
| `rooms` | clinic_id, branch_id, name, status | اتاق دارای شعبهٔ مشخص |
| `units` | clinic_id, branch_id, room_id, name, status | یونیت منبع مستقل در نوبت |
| `staff_schedules` | clinic_id, staff_id, branch_id, day_of_week, shift, start/end | ساعت کاری قابل ترکیب با استثنا |
| `schedule_exceptions` | clinic_id, scope_type/id, date, type, reason, start/end | تعطیلی یا مسدودسازی برای کلینیک/شعبه/پزشک/یونیت |
| `clinic_settings` | clinic_id, key, value, type | برند، شماره‌گذاری، سیاست بیمار، اعلان و پنل |
| `clinic_features` | clinic_id, feature_code, enabled, starts/ends_at, source | پلن یا استثنای زمان‌دار |
| `plans` | name, price, period, status, limits_json | تعریف پلن توسط SA |
| `plan_features` | plan_id, feature_code, limit_value | ماژول و سهمیهٔ پلن |
| `clinic_usage_counters` | clinic_id, counter_key, value, period | بیمار، کاربر، شعبه، پیامک و فضای فایل |

## ۴. بیمار و پذیرش

| موجودیت | فیلدهای کلیدی | روابط و قواعد |
|---|---|---|
| `patient_profiles` | clinic_id, person_id, patient_number, status, chart_type, primary_branch_id | شماره پرونده با پیشوند قابل تنظیم کلینیک |
| `patient_intake_requests` | clinic_id, person_id nullable, qr_token, payload, status, submitted_at, reviewed_by | صف QR؛ token زمان‌دار/قابل باطل‌سازی |
| `patient_medical_conditions` | clinic_id, patient_id, condition_id, details, severity | گزینهٔ پایه یا سفارشی کلینیک |
| `medical_condition_definitions` | clinic_id nullable, code, label, is_active | تعریف پیش‌فرض یا کلینیکی |
| `patient_allergies` | clinic_id, patient_id, name, severity, reaction, is_active | Badge هشدار در صفحات درمان |
| `patient_emergency_contacts` | clinic_id, patient_id, name, relation, mobile | اطلاعات تماس اضطراری |
| `patient_notes` | clinic_id, patient_id, visibility, body, created_by | private/public با مجوز مستقل |
| `custom_field_definitions` | clinic_id, entity_type, key, label, type, required, visibility | فقط در محدودهٔ کلینیک |
| `custom_field_values` | clinic_id, definition_id, entity_id, value | کلید یکتا و اعتبارسنجی بر اساس تعریف |
| `files` | clinic_id, patient_id nullable, owner_type/id, disk_path, mime, size, checksum, deleted_at | JPG/PNG، حداکثر ۱ MB، خارج از web root |

## ۵. دندان و درمان

| موجودیت | فیلدهای کلیدی | روابط |
|---|---|---|
| `teeth` | clinic_id, patient_id, fdi_code, dentition_type, state | یک وضعیت جاری برای دندان/بیمار |
| `tooth_surfaces` | clinic_id, tooth_id, surface_code, state, note | فعال‌سازی ماژول سطوح |
| `tooth_annotations` | clinic_id, tooth_id, type, payload, note, created_by | علامت/نقاشی ساده، قابل ویرایش و بایگانی |
| `gum_records` | clinic_id, patient_id, tooth_id, payload | ماژول لثه |
| `root_records` | clinic_id, patient_id, tooth_id, payload | ماژول ریشه |
| `canal_records` | clinic_id, patient_id, tooth_id, payload | ماژول کانال |
| `implant_records` | clinic_id, patient_id, tooth_id, payload | ماژول ایمپلنت |
| `service_categories` | clinic_id nullable, code, name, is_active | دسته پیش‌فرض/کلینیکی |
| `services` | clinic_id, code, name, category_id, duration, sessions, base_price, status, calendar_color | قیمت پایه و وضعیت |
| `service_prices` | clinic_id, service_id, branch_id nullable, doctor_id nullable, amount, starts_at, ends_at | قیمت متغیر با Snapshot در طرح/فاکتور |
| `treatment_workflows` | clinic_id, service_id, name, status | گردش‌کار قابل تنظیم |
| `workflow_steps` | workflow_id, name, sort_order, required, form_definition | مراحل و چک‌لیست |
| `treatment_plans` | clinic_id, patient_id, title, status, active, created_by | چند طرح فعال ممکن است |
| `treatment_plan_items` | clinic_id, plan_id, tooth_id, service_id, doctor_id, price_snapshot, status | دندان/خدمت/پزشک اجباری |
| `treatment_events` | clinic_id, patient_id, plan_id/item_id, event_type, from/to, reason, actor | تاریخچهٔ غیرقابل‌حذف |
| `treatment_sessions` | clinic_id, patient_id, appointment_id, started_at, stopped_at, duration_seconds | زمان واقعی درمان |

## ۶. نوبت و زمان

| موجودیت | فیلدهای کلیدی | قواعد |
|---|---|---|
| `appointments` | clinic_id, patient_id, doctor_id, branch_id, room_id, unit_id, service_id, start_at, end_at, status, emergency, cancellation_reason | ایندکس‌های زمان/منبع و کنترل تداخل |
| `appointment_series` | clinic_id, rule, start/end, count, created_by | تکرار روزانه/هفتگی/ماهانه |
| `appointment_occurrences` | series_id, appointment_id, override_flag | ویرایش مستقل یک جلسه |
| `appointment_services` | clinic_id, appointment_id, service_id, order_no | چند خدمت در یک بازه، ردیف مستقل |
| `waitlist_entries` | clinic_id, patient_id, doctor_id nullable, service_id, preferred_window, status | مدیریت دستی پیشنهاد جای خالی |
| `appointment_status_history` | clinic_id, appointment_id, from/to, reason, actor | علت لغو و تغییرات دائمی |

## ۷. مالی

| موجودیت | فیلدهای کلیدی | قواعد |
|---|---|---|
| `invoices` | clinic_id, patient_id, number, status, subtotal, discount, total, due_at | فاکتور باز/تجمیعی/ابطال |
| `invoice_items` | clinic_id, invoice_id, service_id nullable, treatment_item_id nullable, description, quantity, unit_price_snapshot, total | قیمت تاریخی |
| `payments` | clinic_id, patient_id, invoice_id, method, amount, reference, paid_at, received_by, status | نقد، کارت‌خوان، کارت‌به‌کارت |
| `payment_allocations` | clinic_id, payment_id, invoice_id, invoice_item_id nullable, amount | تخصیص دقیق/کلی |
| `wallets` | clinic_id, patient_id, balance | کیف پول برای هر کلینیک مستقل |
| `wallet_transactions` | clinic_id, wallet_id, type, amount, source_type/id, balance_after | بدهکار/بستانکار با تراکنش غیرقابل‌حذف |
| `installment_plans` | clinic_id, patient_id, invoice_id, method, total, status | برنامه اقساط |
| `installments` | clinic_id, plan_id, due_at, amount, paid_amount, status | سررسید و اعلان داخلی |
| `discount_approvals` | clinic_id, invoice_id, requested_by, approved_by, amount, reason | سقف تخفیف و تأیید مدیر |

## ۸. اعلان، پشتیبانی و عملیات

`notifications` و `notification_recipients` مرکز اعلان داخلی را می‌سازند و وضعیت خوانده‌شدن، اقدام و انقضا را ذخیره می‌کنند. `conversations`, `conversation_messages` و `support_tickets` گفت‌وگو و تیکت را پوشش می‌دهند. `error_reports` دارای شناسهٔ پیگیری عمومی، جزئیات فنی امن، URL، نقش گزارش‌دهنده، وضعیت و پاسخ سوپرادمین است. `audit_logs` فقط‌افزودنی است و عملیات حساس، قبل/بعد، actor، clinic، IP و زمان را ثبت می‌کند.

برای آموزش، `knowledge_articles`, `knowledge_categories`, `knowledge_article_visibility` و تاریخچهٔ انتشار لازم است. برای بکاپ، `backup_jobs`, `backup_artifacts`, `backup_restores`, `maintenance_windows` و گزارش health check ایجاد می‌شوند. پیامک در فاز میانی با `sms_events`, `sms_patterns`, `sms_usage_counters` و `sms_logs` پیاده می‌شود؛ کلید IPPanel خارج از دیتابیس و در تنظیمات محیطی نگهداری خواهد شد.

## ۹. الزامات جداسازی داده

هر Repository مستأجری باید Context کلینیک فعال را اجباری دریافت کند. Query بدون Scope در ماژول‌های کلینیکی ممنوع است مگر در سرویس SA که به‌صورت صریح و نام‌گذاری‌شدهٔ cross-tenant اجرا شود. Foreign Keyهای داخلی باید `clinic_id` را نیز در قیدهای منطقی لحاظ کنند؛ آزمون‌های امنیتی باید با تغییر شناسهٔ رکورد و کلینیک، تلاش IDOR را رد کنند. فایل، کش، گزارش و خروجی نیز با کلینیک namespace می‌شوند.

## ۱۰. مهاجرت از پورتال موجود

اسکیمای فعلی تک‌مستأجری و متکی بر `users`, `projects`, `products`, `tickets`, `invoices`, `notifications` و `settings` است و موجودیت‌های کلینیک، بیمار، دندان، نوبت و درمان ندارد. تصمیم فاز ۰ این است که جدول‌های دندانی در Migrationهای جدا افزوده شوند و نام‌گذاری حوزهٔ جدید با جداول قدیمی تداخل نکند. رکوردهای پورتال فعلی در توسعه حفظ می‌شوند؛ در صورت نیاز محصول، Migration انتقال فقط پس از تصمیم مستقل کسب‌وکاری نوشته می‌شود و حذف/تغییر مخرب انجام نخواهد شد.
