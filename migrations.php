<?php
/**
 * Versioned migrations.
 * - portal_migrations()  : اجرای مهاجرت‌های در انتظار (توسط نصب‌کننده و اجرای خودکار فراخوانی می‌شود)
 * - portal_auto_migrate(): اجرای خودکار هنگام بارگذاری برنامه — نصب‌های موجود با آپدیت کد،
 *                          به‌صورت خودکار ارتقا می‌یابند (الگوی Lazy Migration).
 */

/** آخرین نسخه اسکیمای دیتابیس — هنگام افزودن مهاجرت جدید، این عدد را یک واحد زیاد کنید. */
if (!defined('PORTAL_SCHEMA_VERSION')) {
    define('PORTAL_SCHEMA_VERSION', 22);
}

function portal_column_exists(PDO $db, string $table, string $column): bool {
    $q=$db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");$q->execute([$table,$column]);return (bool)$q->fetchColumn();
}

/**
 * اجرای خودکار مهاجرت‌های در انتظار هنگام بارگذاری برنامه.
 *
 * - اگر در همان سشن قبلاً اعمال شده باشد، هیچ کوئری اضافه‌ای اجرا نمی‌شود (کش سشن).
 * - اگر مهاجرتی در انتظار باشد، portal_migrations() آن را با قفل (GET_LOCK) اجرا می‌کند.
 * - شکست مهاجرت باعث توقف برنامه نمی‌شود؛ در لاگ ثبت شده و در درخواست بعدی دوباره تلاش می‌شود.
 */
function portal_auto_migrate(PDO $pdo): void {
    if (isset($_SESSION['portal_schema_version']) && (int) $_SESSION['portal_schema_version'] >= PORTAL_SCHEMA_VERSION) {
        return; // این سشن در حال حاضر به‌روز است
    }

    try {
        portal_migrations($pdo);
        $_SESSION['portal_schema_version'] = PORTAL_SCHEMA_VERSION;
    } catch (Throwable $e) {
        error_log('[Portal AutoMigrate] ' . $e->getMessage());
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
                    ('admin','admins'),('admin','profile')");
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
                $db->exec("CREATE INDEX IF NOT EXISTS idx_users_mobile ON users (mobile)");
                $db->exec("CREATE INDEX IF NOT EXISTS idx_tickets_department ON tickets (department_id)");
                $db->exec("CREATE INDEX IF NOT EXISTS idx_activity_user ON activity_logs (user_id)");
                // یکتاسازی شماره موبایل: ابتدا رکوردهای تکراری (غیر از قدیمی‌ترین) خالی می‌شوند تا ایندکس یکتا ممکن شود
                $db->exec("UPDATE users u
                           JOIN (SELECT mobile, MIN(id) keep_id FROM users WHERE mobile <> '' GROUP BY mobile HAVING COUNT(*) > 1) d ON u.mobile = d.mobile AND u.id <> d.keep_id
                           SET u.mobile = ''");
                $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS uniq_users_mobile ON users (mobile)");
            },
            21=>function($db){
                // یکتاسازی شماره فاکتور: رکوردهای تکراری (غیر از قدیمی‌ترین) پسوند می‌گیرند
                $db->exec("UPDATE invoices i
                           JOIN (SELECT invoice_number, MIN(id) keep_id FROM invoices GROUP BY invoice_number HAVING COUNT(*) > 1) d
                             ON i.invoice_number = d.invoice_number AND i.id <> d.keep_id
                           SET i.invoice_number = CONCAT(i.invoice_number, '-DUP-', i.id)");
                $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS uniq_invoice_number ON invoices (invoice_number)");

                // کلید خارجی روی پیام‌های تیکت و والد نظرسنجی (حذف داده‌های یتیم از قبل)
                $db->exec("DELETE FROM ticket_messages WHERE sender_id NOT IN (SELECT id FROM users)");
                $db->exec("CREATE INDEX IF NOT EXISTS idx_ticket_messages_sender ON ticket_messages (sender_id)");
                $db->exec("ALTER TABLE ticket_messages ADD CONSTRAINT fk_tm_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE");
                $db->exec("DELETE FROM surveys WHERE parent_survey_id IS NOT NULL AND parent_survey_id NOT IN (SELECT id FROM surveys)");
                $db->exec("CREATE INDEX IF NOT EXISTS idx_surveys_parent ON surveys (parent_survey_id)");
                $db->exec("ALTER TABLE surveys ADD CONSTRAINT fk_surveys_parent FOREIGN KEY (parent_survey_id) REFERENCES surveys(id) ON DELETE SET NULL");
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
            }
        ];
        // گارد: مطمئن شو ثابت نسخه با بالاترین مهاجرت هماهنگ است
        if (max(array_keys($m)) !== PORTAL_SCHEMA_VERSION) {
            throw new LogicException('PORTAL_SCHEMA_VERSION باید با بالاترین نسخه مهاجرت ('.max(array_keys($m)).') برابر باشد.');
        }
        foreach($m as $v=>$fn){if($v<=$current)continue;$fn($pdo);$q=$pdo->prepare('INSERT INTO schema_versions(version) VALUES(?)');$q->execute([$v]);}
    } finally {$pdo->query("SELECT RELEASE_LOCK('portal_schema_migration')");}
}
