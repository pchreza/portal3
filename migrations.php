<?php
/**
 * Versioned migrations.
 * - portal_migrations()  : اجرای مهاجرت‌های در انتظار (توسط نصب‌کننده و اجرای خودکار فراخوانی می‌شود)
 * - portal_auto_migrate(): اجرای خودکار هنگام بارگذاری برنامه — نصب‌های موجود با آپدیت کد،
 *                          به‌صورت خودکار ارتقا می‌یابند (الگوی Lazy Migration).
 */

/** آخرین نسخه اسکیمای دیتابیس — هنگام افزودن مهاجرت جدید، این عدد را یک واحد زیاد کنید. */
if (!defined('PORTAL_SCHEMA_VERSION')) {
    define('PORTAL_SCHEMA_VERSION', 28);
}

function portal_column_exists(PDO $db, string $table, string $column): bool {
    $q=$db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");$q->execute([$table,$column]);return (bool)$q->fetchColumn();
}
function portal_index_exists(PDO $db, string $table, string $index): bool {
    $q=$db->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?");$q->execute([$table,$index]);return (bool)$q->fetchColumn();
}
function portal_column_data_type(PDO $db, string $table, string $column): ?string
{
    $q = $db->prepare('SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $q->execute([$table, $column]);
    $type = $q->fetchColumn();
    return $type === false ? null : strtolower((string) $type);
}
function portal_fk_exists(PDO $db, string $table, string $fk): bool {
    $q=$db->prepare("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME=? AND CONSTRAINT_TYPE='FOREIGN KEY'");$q->execute([$table,$fk]);return (bool)$q->fetchColumn();
}

function portal_schema_version(PDO $db): int
{
    try {
        return (int) $db->query('SELECT COALESCE(MAX(version), 0) FROM schema_versions')->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * اجرای خودکار مهاجرت‌های در انتظار هنگام بارگذاری برنامه.
 *
 * - اگر در همان سشن قبلاً اعمال شده باشد، هیچ کوئری اضافه‌ای اجرا نمی‌شود (کش سشن).
 * - اگر مهاجرتی در انتظار باشد، portal_migrations() آن را با قفل (GET_LOCK) اجرا می‌کند.
 * - شکست مهاجرت باعث توقف برنامه نمی‌شود؛ در لاگ ثبت شده و در درخواست بعدی دوباره تلاش می‌شود.
 */
function portal_auto_migrate(PDO $pdo): bool {
    if (isset($_SESSION['portal_schema_version']) && (int) $_SESSION['portal_schema_version'] >= PORTAL_SCHEMA_VERSION) {
        return false; // این سشن در حال حاضر به‌روز است
    }

    try {
        portal_migrations($pdo);
        $_SESSION['portal_schema_version'] = PORTAL_SCHEMA_VERSION;
        return true;
    } catch (Throwable $e) {
        error_log('[Portal AutoMigrate] ' . $e->getMessage());
        return false;
    }
}

function portal_migrations(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS schema_versions (version INT PRIMARY KEY, applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if((int)$pdo->query("SELECT GET_LOCK('portal_schema_migration',30)")->fetchColumn()!==1) throw new RuntimeException('اجرای ارتقای دیتابیس هم‌زمان است.');
    try {
        $current=(int)$pdo->query('SELECT COALESCE(MAX(version),0) FROM schema_versions')->fetchColumn();
        $m=[
            1=>function($db){if(!portal_column_exists($db,'survey_responses','entity_type'))$db->exec("ALTER TABLE survey_responses ADD COLUMN entity_type VARCHAR(20) NOT NULL DEFAULT 'project'");},
            2=>function($db){if(!portal_column_exists($db,'survey_responses','entity_id'))$db->exec("ALTER TABLE survey_responses ADD COLUMN entity_id INT NOT NULL DEFAULT 0");},
            3=>function($db){if(!portal_column_exists($db,'survey_responses','ip_address'))$db->exec("ALTER TABLE survey_responses ADD COLUMN ip_address VARCHAR(50) DEFAULT ''");},
            4=>function($db){$db->exec("CREATE TABLE IF NOT EXISTS survey_assignments (id INT AUTO_INCREMENT PRIMARY KEY,survey_id INT NOT NULL,customer_id INT NOT NULL,entity_type VARCHAR(20) NOT NULL,entity_id INT NOT NULL,available_at DATETIME NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY unique_assignment(survey_id,customer_id,entity_type,entity_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");},
            5=>function($db){$db->exec("CREATE TABLE IF NOT EXISTS login_attempts (id INT AUTO_INCREMENT PRIMARY KEY,username VARCHAR(100) NOT NULL,ip_address VARCHAR(50) NOT NULL,success TINYINT NOT NULL DEFAULT 0,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX idx_login_lookup(username,ip_address,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");},
            6=>function($db){if(!portal_column_exists($db,'surveys','target_entity'))$db->exec("ALTER TABLE surveys ADD COLUMN target_entity VARCHAR(20) NOT NULL DEFAULT 'project' AFTER description");},
            7=>function($db){if(!portal_column_exists($db,'surveys','is_periodic'))$db->exec("ALTER TABLE surveys ADD COLUMN is_periodic TINYINT NOT NULL DEFAULT 0 AFTER target_entity");},
            8=>function($db){if(!portal_column_exists($db,'surveys','parent_survey_id'))$db->exec("ALTER TABLE surveys ADD COLUMN parent_survey_id INT NULL DEFAULT NULL AFTER is_periodic");},
            9=>function($db){if(!portal_column_exists($db,'surveys','delay_days'))$db->exec("ALTER TABLE surveys ADD COLUMN delay_days INT NOT NULL DEFAULT 0 AFTER parent_survey_id");},
            10=>function($db){if(!portal_column_exists($db,'products','product_status'))$db->exec("ALTER TABLE products ADD COLUMN product_status VARCHAR(50) NOT NULL DEFAULT 'purchased' AFTER price");},
            11=>function($db){
                $db->exec("CREATE TABLE IF NOT EXISTS notifications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    body TEXT,
                    ntype VARCHAR(30) NOT NULL DEFAULT 'info',
                    target_type VARCHAR(30) NOT NULL DEFAULT 'all',
                    target_filter VARCHAR(255) DEFAULT '',
                    created_by INT DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    expires_at DATETIME NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                $db->exec("CREATE TABLE IF NOT EXISTS notification_recipients (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    notification_id INT NOT NULL,
                    user_id INT NOT NULL,
                    is_read TINYINT NOT NULL DEFAULT 0,
                    read_at DATETIME NULL,
                    UNIQUE KEY uniq_notif_user (notification_id, user_id),
                    INDEX idx_user_read (user_id, is_read),
                    FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            },
            12=>function($db){
                // ستون is_active برای جدول notifications (در migration 11 فراموش شده بود)
                if(!portal_column_exists($db,'notifications','is_active'))$db->exec("ALTER TABLE notifications ADD COLUMN is_active TINYINT NOT NULL DEFAULT 1 AFTER expires_at");
            },
            13=>function($db){
                // عکس محصولات و پروژه‌ها
                if(!portal_column_exists($db,'products','image'))$db->exec("ALTER TABLE products ADD COLUMN image VARCHAR(255) DEFAULT '' AFTER product_status");
                if(!portal_column_exists($db,'projects','image'))$db->exec("ALTER TABLE projects ADD COLUMN image VARCHAR(255) DEFAULT '' AFTER status");
                // تصاویر پیش‌فرض (fallback) در تنظیمات
                $db->exec("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('default_product_image',''),('default_project_image','')");
            },
            14=>function($db){
                // دپارتمان‌های تیکت
                $db->exec("CREATE TABLE IF NOT EXISTS ticket_departments (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    description VARCHAR(255) DEFAULT '',
                    sort_order INT NOT NULL DEFAULT 0,
                    is_active TINYINT NOT NULL DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                // دپارتمان‌های پیش‌فرض
                $db->exec("INSERT IGNORE INTO ticket_departments (id, name, description, sort_order) VALUES
                    (1,'پشتیبانی فنی','مشکلات فنی و خطاهای سیستم',1),
                    (2,'فروش و مشاوره','سوالات قبل از خرید و مشاوره',2),
                    (3,'مالی و فاکتور','مسائل مربوط به پرداخت و فاکتور',3),
                    (4,'مدیریت حساب','مدیریت اطلاعات حساب کاربری',4)");
                // ستون department در tickets
                if(!portal_column_exists($db,'tickets','department_id'))$db->exec("ALTER TABLE tickets ADD COLUMN department_id INT NULL DEFAULT NULL AFTER priority");
            },
            15=>function($db){
                // جدول کدهای OTP برای ورود با شماره موبایل
                $db->exec("CREATE TABLE IF NOT EXISTS otp_codes (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    mobile VARCHAR(20) NOT NULL,
                    code VARCHAR(10) NOT NULL,
                    attempts TINYINT NOT NULL DEFAULT 0,
                    is_used TINYINT NOT NULL DEFAULT 0,
                    expires_at DATETIME NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_otp_mobile (mobile, is_used)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                // تنظیمات ورود و پیامک
                $db->exec("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
                    ('login_method','username'),
                    ('sms_api_key',''),
                    ('sms_pattern',''),
                    ('sms_pattern_var','code'),
                    ('sms_from_number','')");
            },
            16=>function($db){
                // نقش‌های مدیران + دسترسی‌ها (RBAC)
                $db->exec("ALTER TABLE users MODIFY role ENUM('admin','customer','super_admin') NOT NULL DEFAULT 'customer'");
                $db->exec("CREATE TABLE IF NOT EXISTS admin_permissions (
                    role VARCHAR(30) NOT NULL,
                    permission VARCHAR(50) NOT NULL,
                    PRIMARY KEY (role, permission)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                // دسترسی‌های پیش‌فرض: super_admin همه را دارد (در کد چک می‌شود) — admin عادی همه را دارد مگر اینکه محدود شود
                $db->exec("INSERT IGNORE INTO admin_permissions (role, permission) VALUES
                    ('admin','dashboard'),('admin','customers'),('admin','projects'),('admin','products'),
                    ('admin','invoices'),('admin','tickets'),('admin','ticket_departments'),('admin','surveys'),
                    ('admin','custom_fields'),('admin','notifications'),('admin','settings'),('admin','logs'),
                    ('admin','admins'),('admin','profile'),('admin','error_reports')");
                // ادمین اول موجود → سوپر ادمین
                $db->exec("UPDATE users SET role = 'super_admin' WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
            },
            17=>function($db){
                // جدول رویدادهای پیامکی (هر رویداد پترن اختصاصی خودش را دارد)
                $db->exec("CREATE TABLE IF NOT EXISTS sms_events (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    event_key VARCHAR(50) NOT NULL UNIQUE,
                    title VARCHAR(150) NOT NULL,
                    is_active TINYINT NOT NULL DEFAULT 0,
                    pattern_code VARCHAR(100) DEFAULT '',
                    pattern_var VARCHAR(50) DEFAULT '',
                    description VARCHAR(255) DEFAULT ''
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                // رویدادهای پیش‌فرض
                $db->exec("INSERT IGNORE INTO sms_events (event_key, title, is_active, description) VALUES
                    ('welcome','خوش‌آمدگویی به مشتری جدید',0,'بعد از ساخت مشتری جدید در پنل ادمین'),
                    ('project_assigned','انتصاب پروژه جدید به مشتری',0,'بعد از ثبت پروژه جدید برای مشتری'),
                    ('product_assigned','ثبت محصول جدید برای مشتری',0,'بعد از ثبت محصول جدید برای مشتری'),
                    ('invoice_created','صدور فاکتور جدید',0,'بعد از صدور فاکتور برای مشتری'),
                    ('ticket_reply','پاسخ به تیکت',0,'بعد از پاسخ ادمین به تیکت مشتری'),
                    ('survey_reminder','یادآوری نظرسنجی ناقص',0,'یادآوری به مشتری برای تکمیل نظرسنجی'),
                    ('otp_login','کد تایید ورود',1,'کد یکبارمصرف ورود با شماره موبایل')");

                // جدول تاریخچه ارسال پیامک
                $db->exec("CREATE TABLE IF NOT EXISTS sms_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    event_key VARCHAR(50) DEFAULT '',
                    mobile VARCHAR(20) NOT NULL,
                    user_id INT NULL,
                    message VARCHAR(500) DEFAULT '',
                    status TINYINT NOT NULL DEFAULT 0,
                    error VARCHAR(255) DEFAULT '',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_sms_logs_mobile (mobile)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            },
            18=>function($db){
                // ستون فهرست متغیرهای هر رویداد (برای پترن‌های چندمتغیره) — با کاما جدا
                if(!portal_column_exists($db,'sms_events','pattern_vars'))$db->exec("ALTER TABLE sms_events ADD COLUMN pattern_vars VARCHAR(500) DEFAULT '' AFTER pattern_var");
            },
            19=>function($db){
                // --- یادآوری خودکار نظرسنجی + لینک عمومی تکمیل ---
                // شمارش یادآوری‌های ارسال‌شده برای هر انتساب + زمان آخرین یادآوری
                if(!portal_column_exists($db,'survey_assignments','reminder_count'))$db->exec("ALTER TABLE survey_assignments ADD COLUMN reminder_count INT NOT NULL DEFAULT 0");
                if(!portal_column_exists($db,'survey_assignments','last_reminder_at'))$db->exec("ALTER TABLE survey_assignments ADD COLUMN last_reminder_at DATETIME NULL");
                // توکن یکتا برای لینک عمومی تکمیل نظرسنجی (بدون نیاز به ورود)
                if(!portal_column_exists($db,'survey_assignments','token'))$db->exec("ALTER TABLE survey_assignments ADD COLUMN token VARCHAR(40) NULL");
                // ساخت توکن برای رکوردهای قدیمی/بدون توکن
                $stale = $db->query("SELECT id FROM survey_assignments WHERE token IS NULL OR token = ''")->fetchAll(PDO::FETCH_COLUMN);
                $upd = $db->prepare("UPDATE survey_assignments SET token = ? WHERE id = ?");
                foreach ($stale as $rid) {
                    $upd->execute([substr(bin2hex(random_bytes(16)), 0, 32), (int) $rid]);
                }
                // تنظیمات پیش‌فرض یادآوری خودکار نظرسنجی (فقط اگر وجود ندارند)
                $seed = [
                    'site_url'                => '',  // آدرس کامل سایت برای ساخت لینک پیامک‌ها
                    'survey_reminder_days'    => '3', // اولین یادآوری چند روز بعد از فعال‌شدن نظرسنجی؟
                    'survey_reminder_interval'=> '7', // فاصله یادآوری‌های بعدی (۰ = تکرار نشود)
                    'survey_reminder_max'     => '3', // حداکثر تعداد یادآوری برای هر فرم
                ];
                $s = $db->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
                foreach ($seed as $k => $v) { $s->execute([$k, $v]); }
                // توضیح به‌روز رویداد یادآوری نظرسنجی
                $db->exec("UPDATE sms_events SET description = 'یادآوری خودکار پس از X روز + لینک تکمیل نظرسنجی' WHERE event_key = 'survey_reminder'");
            },
            20=>function($db){
                // ایندکس‌های گمشده برای کوئری‌های پرتکرار (ورود OTP، دپارتمان تیکت، لاگ‌ها)
                if(!portal_index_exists($db,'users','idx_users_mobile'))$db->exec("CREATE INDEX idx_users_mobile ON users (mobile)");
                if(!portal_index_exists($db,'tickets','idx_tickets_department'))$db->exec("CREATE INDEX idx_tickets_department ON tickets (department_id)");
                if(!portal_index_exists($db,'activity_logs','idx_activity_user'))$db->exec("CREATE INDEX idx_activity_user ON activity_logs (user_id)");
                // یکتاسازی شماره موبایل: ابتدا رکوردهای تکراری (غیر از قدیمی‌ترین) خالی می‌شوند تا ایندکس یکتا ممکن شود
                $db->exec("UPDATE users u
                           JOIN (SELECT mobile, MIN(id) keep_id FROM users WHERE mobile <> '' GROUP BY mobile HAVING COUNT(*) > 1) d ON u.mobile = d.mobile AND u.id <> d.keep_id
                           SET u.mobile = ''");
                // موبایل‌های خالی → NULL (ایندکس یکتا چند NULL مجاز است ولی چند '' مجاز نیست — خطای 1062)
                $db->exec("UPDATE users SET mobile = NULL WHERE mobile = ''");
                if(!portal_index_exists($db,'users','uniq_users_mobile'))$db->exec("CREATE UNIQUE INDEX uniq_users_mobile ON users (mobile)");
            },
            21=>function($db){
                // یکتاسازی شماره فاکتور: رکوردهای تکراری (غیر از قدیمی‌ترین) پسوند می‌گیرند
                $db->exec("UPDATE invoices i
                           JOIN (SELECT invoice_number, MIN(id) keep_id FROM invoices GROUP BY invoice_number HAVING COUNT(*) > 1) d
                             ON i.invoice_number = d.invoice_number AND i.id <> d.keep_id
                           SET i.invoice_number = CONCAT(i.invoice_number, '-DUP-', i.id)");
                if(!portal_index_exists($db,'invoices','uniq_invoice_number'))$db->exec("CREATE UNIQUE INDEX uniq_invoice_number ON invoices (invoice_number)");

                // کلید خارجی روی پیام‌های تیکت و والد نظرسنجی (حذف داده‌های یتیم از قبل)
                $db->exec("DELETE FROM ticket_messages WHERE sender_id NOT IN (SELECT id FROM users)");
                if(!portal_index_exists($db,'ticket_messages','idx_ticket_messages_sender'))$db->exec("CREATE INDEX idx_ticket_messages_sender ON ticket_messages (sender_id)");
                if(!portal_fk_exists($db,'ticket_messages','fk_tm_sender'))$db->exec("ALTER TABLE ticket_messages ADD CONSTRAINT fk_tm_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE");
                // MariaDB خطای 1093 را برای DELETE از همان جدول با subquery مستقیم می‌دهد؛ DELETE JOIN امن است.
                $db->exec("DELETE child FROM surveys child LEFT JOIN surveys parent ON parent.id = child.parent_survey_id WHERE child.parent_survey_id IS NOT NULL AND parent.id IS NULL");
                if(!portal_index_exists($db,'surveys','idx_surveys_parent'))$db->exec("CREATE INDEX idx_surveys_parent ON surveys (parent_survey_id)");
                if(!portal_fk_exists($db,'surveys','fk_surveys_parent'))$db->exec("ALTER TABLE surveys ADD CONSTRAINT fk_surveys_parent FOREIGN KEY (parent_survey_id) REFERENCES surveys(id) ON DELETE SET NULL");
            },
            22=>function($db){
                // گزارش خطا (دکمه شناور)
                $db->exec("CREATE TABLE IF NOT EXISTS error_reports (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NULL,
                    reporter_name VARCHAR(120) DEFAULT '',
                    reporter_role VARCHAR(20) DEFAULT '',
                    url VARCHAR(500) DEFAULT '',
                    message TEXT,
                    status VARCHAR(20) DEFAULT 'new',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            },
            23=>function($db){
                // دسترسی «گزارش خطا» برای مدیران عادی (جدول error_reports در migration 22)
                $db->exec("INSERT IGNORE INTO admin_permissions (role, permission) VALUES ('admin','error_reports')");
            },
            24=>function($db){
                // 1) «تنظیمات» فقط سوپر ادمین — حذف دسترسی مدیران عادی
                $db->exec("DELETE FROM admin_permissions WHERE role = 'admin' AND permission = 'settings'");

                // 2) یکپارچه‌سازی تاریخ‌های شمسی ذخیره‌شده در VARCHAR → میلادی (یک‌بار برای همیشه)
                $conv = static function (string $v): ?string {
                    if ($v === '' || preg_match('#^\d{4}-\d{2}-\d{2}$#', $v)) {
                        return $v === '' ? null : $v;
                    }
                    if (!preg_match('#^(\d{4})[/\-](\d{1,2})[/\-](\d{1,2})$#', trim($v), $m)) {
                        return null;
                    }
                    $jy = (int) $m[1]; $jm = (int) $m[2]; $jd = (int) $m[3];
                    if ($jy < 1300 || $jy > 1500 || $jm < 1 || $jm > 12 || $jd < 1 || $jd > 31) {
                        return null;
                    }
                    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
                    $base = mktime(0, 0, 0, 3, 21, $jy + 621) - 5 * 86400;
                    for ($i = -400; $i <= 400; $i++) {
                        $t = $base + $i * 86400;
                        $gy = (int) date('Y', $t); $gm = (int) date('n', $t); $gd = (int) date('j', $t);
                        $jyy = ($gy <= 1600) ? 0 : 979;
                        $gyb = $gy - (($gy <= 1600) ? 621 : 1600);
                        $gy2 = ($gm > 2) ? ($gyb + 1) : $gyb;
                        $days = (365 * $gyb) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) - 80 + $gd + $g_d_m[$gm - 1];
                        $jyy += 33 * intdiv($days, 12053); $days %= 12053;
                        $jyy += 4 * intdiv($days, 1461); $days %= 1461;
                        $jyy += intdiv($days - 1, 365);
                        if ($days > 365) { $days = ($days - 1) % 365; }
                        $pjm = $days < 186 ? 1 + intdiv($days, 31) : 7 + intdiv($days - 186, 30);
                        $pjd = $days < 186 ? 1 + ($days % 31) : 1 + (($days - 186) % 30);
                        if ($jyy === $jy && $pjm === $jm && $pjd === $jd) {
                            return date('Y-m-d', $t);
                        }
                    }
                    return null;
                };

                if (portal_column_exists($db, 'users', 'birth_date')) {
                    $rows = $db->query("SELECT id, birth_date FROM users WHERE birth_date IS NOT NULL AND birth_date <> ''")->fetchAll();
                    $up = $db->prepare("UPDATE users SET birth_date = ? WHERE id = ?");
                    foreach ($rows as $r) {
                        $c = $conv((string) $r['birth_date']);
                        if ($c !== null && $c !== $r['birth_date']) {
                            $up->execute([$c, $r['id']]);
                        }
                    }
                }
                if (portal_column_exists($db, 'custom_field_values', 'field_value')) {
                    $rows = $db->query("SELECT v.id, v.field_value FROM custom_field_values v JOIN custom_fields f ON f.id = v.field_id WHERE f.field_type = 'date' AND v.field_value IS NOT NULL AND v.field_value <> ''")->fetchAll();
                    $up2 = $db->prepare("UPDATE custom_field_values SET field_value = ? WHERE id = ?");
                    foreach ($rows as $r) {
                        $c = $conv((string) $r['field_value']);
                        if ($c !== null && $c !== $r['field_value']) {
                            $up2->execute([$c, $r['id']]);
                        }
                    }
                }
            },
            25=>function($db){
                // API key پیامک از settings خارج شده و فقط از environment خوانده می‌شود.
                $db->exec("UPDATE settings SET setting_value = '' WHERE setting_key = 'sms_api_key'");
            },
            26=>function($db){
                // تبدیل امن مبلغ و تاریخ از VARCHAR به typeهای قابل محاسبه.
                $date_specs = [
                    ['projects', 'deadline'],
                    ['products', 'purchase_date'],
                    ['invoices', 'due_date'],
                ];
                $legacy_string_types = ['char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext'];
                foreach ($date_specs as [$table, $column]) {
                    // نصب تازه از schema.sql ستون را DATE می‌گیرد؛ فقط نصب‌های قدیمی
                    // که هنوز VARCHAR دارند به تبدیل و پاک‌سازی مقدار خالی نیاز دارند.
                    $data_type = portal_column_data_type($db, $table, $column);
                    if (!in_array($data_type, $legacy_string_types, true)) {
                        continue;
                    }
                    $rows = $db->query("SELECT id, {$column} FROM {$table} WHERE {$column} IS NOT NULL AND TRIM({$column}) <> ''")->fetchAll();
                    $update = $db->prepare("UPDATE {$table} SET {$column} = ? WHERE id = ?");
                    foreach ($rows as $row) {
                        $raw = trim((string) $row[$column]);
                        $converted = function_exists('portal_date_to_db') ? portal_date_to_db($raw) : $raw;
                        if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $converted)) {
                            throw new RuntimeException("مقدار تاریخ ناسالم در {$table}.{$column} برای ID {$row['id']}");
                        }
                        $update->execute([$converted, (int) $row['id']]);
                    }
                    // این update پیش از ALTER انجام می‌شود تا strict SQL mode
                    // مقدار خالی را هنگام تبدیل VARCHAR به DATE رد نکند.
                    $db->exec("UPDATE {$table} SET {$column} = NULL WHERE TRIM({$column}) = ''");
                }

                $money_specs = [
                    ['products', 'price'],
                    ['invoices', 'amount'],
                ];
                foreach ($money_specs as [$table, $column]) {
                    $data_type = portal_column_data_type($db, $table, $column);
                    if (!in_array($data_type, $legacy_string_types, true)) {
                        continue;
                    }
                    $rows = $db->query("SELECT id, {$column} FROM {$table} WHERE {$column} IS NOT NULL AND TRIM({$column}) <> ''")->fetchAll();
                    $update = $db->prepare("UPDATE {$table} SET {$column} = ? WHERE id = ?");
                    foreach ($rows as $row) {
                        $normalized = function_exists('normalize_money_input') ? normalize_money_input((string) $row[$column]) : null;
                        if ($normalized === null) {
                            throw new RuntimeException("مقدار مالی ناسالم در {$table}.{$column} برای ID {$row['id']}");
                        }
                        $update->execute([$normalized === '' ? null : $normalized, (int) $row['id']]);
                    }
                    $db->exec("UPDATE {$table} SET {$column} = NULL WHERE TRIM({$column}) = ''");
                }

                $db->exec("ALTER TABLE projects MODIFY deadline DATE NULL DEFAULT NULL");
                $db->exec("ALTER TABLE products MODIFY price DECIMAL(18,2) NULL DEFAULT NULL, MODIFY purchase_date DATE NULL DEFAULT NULL");
                $db->exec("ALTER TABLE invoices MODIFY amount DECIMAL(18,2) NULL DEFAULT NULL, MODIFY due_date DATE NULL DEFAULT NULL");
            },
            27=>function($db){
                // قبل از ساخت constraint، duplicate/orphan باید explicit گزارش شود و silently حذف نشود.
                $duplicate_checks = [
                    "SELECT COUNT(*) FROM (SELECT target_entity, field_name FROM custom_fields GROUP BY target_entity, field_name HAVING COUNT(*) > 1) d",
                    "SELECT COUNT(*) FROM (SELECT field_id, entity_id FROM custom_field_values GROUP BY field_id, entity_id HAVING COUNT(*) > 1) d",
                ];
                foreach ($duplicate_checks as $sql) {
                    if ((int) $db->query($sql)->fetchColumn() > 0) {
                        throw new RuntimeException('برای اعمال constraint یکتا، دادهٔ تکراری باید ابتدا دستی بررسی شود.');
                    }
                }
                if (!portal_index_exists($db, 'custom_fields', 'uniq_custom_field_name')) {
                    $db->exec("CREATE UNIQUE INDEX uniq_custom_field_name ON custom_fields (target_entity, field_name)");
                }
                if (!portal_index_exists($db, 'custom_field_values', 'uniq_custom_field_value')) {
                    $db->exec("CREATE UNIQUE INDEX uniq_custom_field_value ON custom_field_values (field_id, entity_id)");
                }
                // داده‌های قدیمی ممکن است پیش از فعال‌شدن FKها orphan داشته باشند.
                // برای FKهای CASCADE رکورد orphan قابل نگه‌داری نیست و حذف می‌شود؛
                // برای FKهای SET NULL مقدار نامعتبر به NULL تبدیل می‌شود.
                // تعداد تغییرات در error log ثبت می‌شود تا cleanup قابل ممیزی باشد.
                $orphan_cleanup = [
                    ['survey_assignments.survey_id', "DELETE child FROM survey_assignments child LEFT JOIN surveys parent ON parent.id = child.survey_id WHERE parent.id IS NULL", 'حذف assignmentهای survey orphan'],
                    ['survey_assignments.customer_id', "DELETE child FROM survey_assignments child LEFT JOIN users parent ON parent.id = child.customer_id WHERE parent.id IS NULL", 'حذف assignmentهای customer orphan'],
                    ['tickets.department_id', "UPDATE tickets child LEFT JOIN ticket_departments parent ON parent.id = child.department_id SET child.department_id = NULL WHERE child.department_id IS NOT NULL AND parent.id IS NULL", 'NULL کردن department نامعتبر ticket'],
                    ['activity_logs.user_id', "UPDATE activity_logs child LEFT JOIN users parent ON parent.id = child.user_id SET child.user_id = NULL WHERE child.user_id IS NOT NULL AND parent.id IS NULL", 'NULL کردن user نامعتبر activity log'],
                    ['sms_logs.user_id', "UPDATE sms_logs child LEFT JOIN users parent ON parent.id = child.user_id SET child.user_id = NULL WHERE child.user_id IS NOT NULL AND parent.id IS NULL", 'NULL کردن user نامعتبر SMS log'],
                    ['notifications.created_by', "UPDATE notifications child LEFT JOIN users parent ON parent.id = child.created_by SET child.created_by = NULL WHERE child.created_by IS NOT NULL AND parent.id IS NULL", 'NULL کردن creator نامعتبر notification'],
                ];
                foreach ($orphan_cleanup as [$column, $sql, $description]) {
                    $affected = (int) $db->exec($sql);
                    if ($affected > 0) {
                        error_log("[Portal Migration] {$description}: {$affected} رکورد در {$column}");
                    }
                }

                $foreign_keys = [
                    ['survey_assignments', 'fk_sa_survey', 'ALTER TABLE survey_assignments ADD CONSTRAINT fk_sa_survey FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE'],
                    ['survey_assignments', 'fk_sa_customer', 'ALTER TABLE survey_assignments ADD CONSTRAINT fk_sa_customer FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE'],
                    ['tickets', 'fk_ticket_department', 'ALTER TABLE tickets ADD CONSTRAINT fk_ticket_department FOREIGN KEY (department_id) REFERENCES ticket_departments(id) ON DELETE SET NULL'],
                    ['activity_logs', 'fk_activity_user', 'ALTER TABLE activity_logs ADD CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL'],
                    ['sms_logs', 'fk_sms_user', 'ALTER TABLE sms_logs ADD CONSTRAINT fk_sms_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL'],
                    ['notifications', 'fk_notification_creator', 'ALTER TABLE notifications ADD CONSTRAINT fk_notification_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL'],
                ];
                foreach ($foreign_keys as [$table, $name, $sql]) {
                    if (!portal_fk_exists($db, $table, $name)) {
                        $db->exec($sql);
                    }
                }
            },
            28=>function($db){
                $db->exec("CREATE TABLE IF NOT EXISTS admin_user_permissions (
                    user_id INT NOT NULL,
                    permission VARCHAR(60) NOT NULL,
                    allowed TINYINT(1) NOT NULL DEFAULT 0,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (user_id, permission),
                    CONSTRAINT fk_admin_user_permission_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            }
        ];
        // گارد: مطمئن شو ثابت نسخه با بالاترین مهاجرت هماهنگ است
        if (max(array_keys($m)) !== PORTAL_SCHEMA_VERSION) {
            throw new LogicException('PORTAL_SCHEMA_VERSION باید با بالاترین نسخه مهاجرت ('.max(array_keys($m)).') برابر باشد.');
        }
        foreach($m as $v=>$fn){if($v<=$current)continue;$fn($pdo);$q=$pdo->prepare('INSERT INTO schema_versions(version) VALUES(?)');$q->execute([$v]);}
    } finally {$pdo->query("SELECT RELEASE_LOCK('portal_schema_migration')");}
}
