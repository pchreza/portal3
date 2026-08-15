<?php
// install.php - Simple installation wizard
require_once 'config.php';

/**
 * گارد امنیتی: اگر سیستم قبلاً نصب شده (اتصال DB برقرار و حداقل یک مدیر وجود دارد)
 * اجازه اجرای مجدد ویزارد داده نمی‌شود — مگر اینکه دیتابیس خالی/حذف شده باشد.
 */
function portal_already_installed(): bool
{
    global $pdo;
    if (!$pdo) {
        return false; // اتصال برقرار نشده → نصب مجاز است
    }
    try {
        $q = $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('admin','super_admin')");
        return (int) $q->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false; // جدول هنوز ساخته نشده → نصب مجاز است
    }
}

$step = isset($_GET['step']) ? intval($_GET['step']) : 1;
$error = '';
$success = '';

// بستن ویزارد برای نصب‌های موجود (صفحه ۳ فقط پیام موفقیت است و مجاز)
if (portal_already_installed() && $step !== 3) {
    $isInstalled = true;
} else {
    $isInstalled = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isInstalled) {
    // حفاظت CSRF (مثل بقیه فرم‌های سیستم)
    if (!verify_csrf()) {
        $error = 'درخواست نامعتبر است. صفحه را بازخوانی کنید.';
    } elseif ($step === 1) {
        $db_host = trim($_POST['db_host']);
        $db_name = trim($_POST['db_name']);
        $db_user = trim($_POST['db_user']);
        $db_pass = trim($_POST['db_pass']);

        // اعتبارسنجی نام دیتابیس: فقط حروف/اعداد/زیرخط — جلوگیری از تزریق SQL (بک‌تیک)
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $db_name)) {
            $error = 'نام پایگاه داده فقط می‌تواند شامل حروف انگلیسی، عدد و زیرخط (_) باشد.';
        } else {
        try {
            $test_pdo = new PDO("mysql:host={$db_host};charset=utf8mb4", $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            
            // ساخت دیتابیس در صورت داشتن مجوز؛ در محیط‌های اشتراکی، استفاده از دیتابیس ازپیش‌ساخته نیز مجاز است.
            try {
                $test_pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            } catch (PDOException $create_error) {
                error_log('[Portal Installer] CREATE DATABASE failed: ' . $create_error->getMessage());
                try {
                    $test_pdo->exec("USE `{$db_name}`");
                } catch (PDOException $use_error) {
                    throw new RuntimeException('ساخت پایگاه داده ممکن نشد. یا نام پایگاه داده را درست وارد کنید، یا ابتدا آن را بسازید و به کاربر دیتابیس دسترسی کامل بدهید.');
                }
            }
            $test_pdo->exec("USE `{$db_name}`");

            // پیکربندی فقط پس از موفقیت ساخت schema ذخیره می‌شود تا خطای میانی، نصب را در وضعیت نیمه‌کاره نگذارد.
            $config_content = "<?php\n\$db_host = " . var_export($db_host, true) . ";\n\$db_name = " . var_export($db_name, true) . ";\n\$db_user = " . var_export($db_user, true) . ";\n\$db_pass = " . var_export($db_pass, true) . ";\n?>";

            // Create tables
            $test_pdo->exec("
                CREATE TABLE IF NOT EXISTS `settings` (
                    `setting_key` VARCHAR(100) PRIMARY KEY,
                    `setting_value` TEXT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                CREATE TABLE IF NOT EXISTS `users` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `username` VARCHAR(100) UNIQUE NOT NULL,
                    `password` VARCHAR(255) NOT NULL,
                    `role` ENUM('admin', 'customer', 'super_admin') DEFAULT 'customer',
                    `first_name` VARCHAR(100) DEFAULT '',
                    `last_name` VARCHAR(100) DEFAULT '',
                    `mobile` VARCHAR(20) DEFAULT '',
                    `company_name` VARCHAR(150) DEFAULT '',
                    `job_title` VARCHAR(100) DEFAULT '',
                    `birth_date` VARCHAR(20) DEFAULT '',
                    `gender` VARCHAR(20) DEFAULT '',
                    `profile_skipped` TINYINT DEFAULT 0,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                CREATE TABLE IF NOT EXISTS `projects` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `customer_id` INT NOT NULL,
                    `title` VARCHAR(200) NOT NULL,
                    `description` TEXT,
                    `status` VARCHAR(50) DEFAULT 'in_progress',
                    `budget` VARCHAR(100) DEFAULT '',
                    `deadline` VARCHAR(50) DEFAULT '',
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                CREATE TABLE IF NOT EXISTS `products` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `customer_id` INT NOT NULL,
                    `title` VARCHAR(200) NOT NULL,
                    `description` TEXT,
                    `price` VARCHAR(100) DEFAULT '',
                    `product_status` VARCHAR(50) NOT NULL DEFAULT 'purchased',
                    `license_key` VARCHAR(255) DEFAULT '',
                    `purchase_date` VARCHAR(50) DEFAULT '',
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            // The canonical schema is maintained in schema.sql.
            $schema_sql = file_get_contents(__DIR__ . '/schema.sql');
            if ($schema_sql === false || trim($schema_sql) === '') throw new RuntimeException('فایل schema.sql پیدا نشد یا خالی است.');
            $test_pdo->exec($schema_sql);

            // Default settings (all fields optional by default)
            $default_settings = [
                'req_first_name' => '1',
                'req_last_name' => '1',
                'req_mobile' => '1',
                'req_company_name' => '0',
                'req_job_title' => '0',
                'req_birth_date' => '0',
                'req_gender' => '0',
                // یادآوری خودکار نظرسنجی + آدرس سایت برای لینک پیامک‌ها
                'site_url' => '',
                'survey_reminder_days' => '3',
                'survey_reminder_interval' => '7',
                'survey_reminder_max' => '3',
            ];
            foreach ($default_settings as $k => $v) {
                $stmt = $test_pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$k, $v, $v]);
            }
            if (file_put_contents(__DIR__ . '/db_config.php', $config_content, LOCK_EX) === false) {
                throw new RuntimeException('فایل تنظیمات اتصال ذخیره نشد. دسترسی نوشتن پوشهٔ نصب را بررسی کنید.');
            }

            header('Location: install.php?step=2');
            exit;
        } catch (Exception $e) {
            error_log('[Portal Installer] ' . $e->getMessage());
            $error = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'اتصال یا آماده‌سازی پایگاه داده انجام نشد. تنظیمات اتصال و دسترسی کاربر دیتابیس را بررسی کنید.';
        }
        }
    } elseif ($step === 2) {
        require_once 'db_config.php';
        $pdo = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        require_once __DIR__ . '/migrations.php';
        portal_migrations($pdo);
        $admin_username = trim($_POST['admin_username']);
        $admin_password = $_POST['admin_password'];
        $admin_firstname = trim($_POST['admin_firstname']);
        $admin_lastname = trim($_POST['admin_lastname']);

        if (empty($admin_username) || empty($admin_password)) {
            $error = 'لطفاً نام کاربری و رمز عبور مدیر را وارد کنید.';
        } elseif (strlen($admin_password) < 8) {
            $error = 'رمز عبور مدیر باید حداقل ۸ کاراکتر باشد.';
        } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/u', $admin_username)) {
            $error = 'نام کاربری باید شامل حروف انگلیسی، عدد، نقطه، خط تیره و زیرخط (۳ تا ۵۰ کاراکتر) باشد.';
        } else {
            $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
            // ادمین اول سیستم به‌عنوان «مدیر ارشد (سوپر ادمین)» ساخته می‌شود
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role, first_name, last_name) VALUES (?, ?, 'super_admin', ?, ?) ON DUPLICATE KEY UPDATE password = ?, first_name = ?, last_name = ?");
            $stmt->execute([$admin_username, $hashed_password, $admin_firstname, $admin_lastname, $hashed_password, $admin_firstname, $admin_lastname]);

            header('Location: install.php?step=3');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نصب سیستم پورتال مشتریان</title>
    <link rel="preload" href="assets/fonts/Vazirmatn-v33.003-wght.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="assets/fonts/vazirmatn.css">
    <link rel="stylesheet" href="assets/tailwind.css">
    <style>
        body { font-family: 'Vazirmatn', Tahoma, Arial, sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-xl w-full bg-white rounded-2xl shadow-xl border border-slate-100 overflow-hidden">
        <div class="bg-gradient-to-l from-indigo-600 to-violet-600 p-6 text-white text-center">
            <h1 class="text-2xl font-bold">پورتال هوشمند مشتریان</h1>
            <p class="text-indigo-100 text-sm mt-1">ویزارد نصب و راه‌اندازی اولیه سیستم (فاز اول)</p>
        </div>

        <div class="p-8">
            <?php if ($isInstalled): ?>
                <div class="text-center py-6">
                    <div class="w-16 h-16 bg-amber-100 text-amber-600 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl font-bold">🔒</div>
                    <h2 class="text-xl font-bold text-slate-800 mb-2">سیستم قبلاً نصب شده است</h2>
                    <p class="text-slate-600 text-sm mb-6 leading-relaxed">
                        برای جلوگیری از ربایش و اختلال، ویزارد نصب غیرفعال شده است.<br>
                        اگر واقعاً می‌خواهید نصب مجدد انجام دهید، ابتدا فایل <code dir="ltr" class="font-mono">db_config.php</code> را حذف کنید یا دیتابیس را خالی کنید.
                    </p>
                    <a href="index.php" class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-8 py-3 rounded-lg transition shadow-md shadow-indigo-200">
                        بازگشت به صفحه ورود
                    </a>
                </div>
            <?php elseif ($error): ?>
                <div class="alert alert-danger mb-6">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!$isInstalled): ?>
            <?php if ($step === 1): ?>
                <div class="mb-6 flex items-center justify-between text-xs text-slate-500 font-medium">
                    <span class="text-indigo-600 font-bold">۱. اتصال به دیتابیس</span>
                    <span>۲. حساب ادمین</span>
                    <span>۳. اتمام نصب</span>
                </div>
                <form method="POST" class="space-y-4">
                    <?php echo csrf_input(); ?>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">هاست پایگاه داده (Database Host)</label>
                        <input type="text" name="db_host" value="localhost" required class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">نام پایگاه داده (Database Name)</label>
                        <input type="text" name="db_name" value="client_portal" required class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">نام کاربری پایگاه داده (DB User)</label>
                        <input type="text" name="db_user" value="root" required class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">رمز عبور پایگاه داده (DB Password)</label>
                        <input type="password" name="db_pass" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm">
                    </div>
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-3 rounded-lg transition shadow-md shadow-indigo-200 mt-4 cursor-pointer">
                        مرحله بعد و تست اتصال
                    </button>
                </form>

            <?php elseif ($step === 2): ?>
                <div class="mb-6 flex items-center justify-between text-xs text-slate-500 font-medium">
                    <span class="text-slate-400">۱. اتصال به دیتابیس</span>
                    <span class="text-indigo-600 font-bold">۲. حساب ادمین</span>
                    <span>۳. اتمام نصب</span>
                </div>
                <form method="POST" class="space-y-4">
                    <?php echo csrf_input(); ?>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">نام کاربری ادمین (یا شماره موبایل / ایمیل)</label>
                        <input type="text" name="admin_username" value="admin" required class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">رمز عبور ادمین</label>
                        <input type="password" name="admin_password" required class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">نام ادمین</label>
                            <input type="text" name="admin_firstname" value="مدیر" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">نام خانوادگی ادمین</label>
                            <input type="text" name="admin_lastname" value="سیستم" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm">
                        </div>
                    </div>
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-3 rounded-lg transition shadow-md shadow-indigo-200 mt-4 cursor-pointer">
                        تکمیل نصب و ایجاد حساب
                    </button>
                </form>

            <?php elseif ($step === 3): ?>
                <div class="text-center py-6">
                    <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl font-bold">✓</div>
                    <h2 class="text-xl font-bold text-slate-800 mb-2">سیستم با موفقیت نصب شد!</h2>
                    <p class="text-slate-600 text-sm mb-6">اکنون می‌توانید با حساب کاربری ادمین خود وارد سیستم شوید و مشتریان، پروژه‌ها و محصولات را مدیریت کنید.</p>
                    <a href="index.php" class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-8 py-3 rounded-lg transition shadow-md shadow-indigo-200">
                        ورود به صفحه اصلی (ورود به سیستم)
                    </a>
                </div>
            <?php endif; ?>
            <?php endif; // !isInstalled ?>
        </div>
    </div>
</body>
</html>
