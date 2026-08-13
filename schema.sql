-- Portal base schema. Run once during installation.

            CREATE TABLE IF NOT EXISTS `settings` (
                `setting_key` VARCHAR(100) PRIMARY KEY,
                `setting_value` TEXT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `admin_permissions` (
                `role` VARCHAR(30) NOT NULL,
                `permission` VARCHAR(50) NOT NULL,
                PRIMARY KEY (`role`, `permission`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            INSERT IGNORE INTO `admin_permissions` (`role`, `permission`) VALUES
                ('admin','dashboard'),('admin','customers'),('admin','projects'),('admin','products'),
                ('admin','invoices'),('admin','tickets'),('admin','ticket_departments'),('admin','surveys'),
                ('admin','custom_fields'),('admin','notifications'),('admin','logs'),
                ('admin','admins'),('admin','profile'),('admin','error_reports');

            CREATE TABLE IF NOT EXISTS `schema_versions` (
                `version` INT PRIMARY KEY,
                `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `login_attempts` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `username` VARCHAR(100) NOT NULL,
                `ip_address` VARCHAR(50) NOT NULL,
                `success` TINYINT NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_login_lookup (username, ip_address, created_at)
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

            CREATE TABLE IF NOT EXISTS `admin_user_permissions` (
                `user_id` INT NOT NULL,
                `permission` VARCHAR(60) NOT NULL,
                `allowed` TINYINT(1) NOT NULL DEFAULT 0,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`user_id`, `permission`),
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `projects` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `customer_id` INT NOT NULL,
                `title` VARCHAR(200) NOT NULL,
                `description` TEXT,
                `status` VARCHAR(50) DEFAULT 'in_progress',
                `image` VARCHAR(255) DEFAULT '',
                `budget` VARCHAR(100) DEFAULT '',
                `deadline` DATE DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_projects_customer_status_id` (`customer_id`, `status`, `id`),
                FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `products` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `customer_id` INT NOT NULL,
                `title` VARCHAR(200) NOT NULL,
                `description` TEXT,
                `price` DECIMAL(18,2) DEFAULT NULL,
                `product_status` VARCHAR(50) NOT NULL DEFAULT 'purchased',
                `image` VARCHAR(255) DEFAULT '',
                `license_key` VARCHAR(255) DEFAULT '',
                `purchase_date` DATE DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_products_customer_status_id` (`customer_id`, `product_status`, `id`),
                FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `tickets` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `customer_id` INT NOT NULL,
                `subject` VARCHAR(255) NOT NULL,
                `status` ENUM('open', 'answered', 'closed') DEFAULT 'open',
                `priority` ENUM('low', 'medium', 'high') DEFAULT 'medium',
                `department_id` INT NULL DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `ticket_departments` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(100) NOT NULL,
                `description` VARCHAR(255) DEFAULT '',
                `sort_order` INT NOT NULL DEFAULT 0,
                `is_active` TINYINT NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            INSERT IGNORE INTO `ticket_departments` (`id`, `name`, `description`, `sort_order`) VALUES
                (1, 'پشتیبانی فنی', 'مشکلات فنی و خطاهای سیستم', 1),
                (2, 'فروش و مشاوره', 'سوالات قبل از خرید و مشاوره', 2),
                (3, 'مالی و فاکتور', 'مسائل مربوط به پرداخت و فاکتور', 3),
                (4, 'مدیریت حساب', 'مدیریت اطلاعات حساب کاربری', 4);

            CREATE TABLE IF NOT EXISTS `ticket_messages` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `ticket_id` INT NOT NULL,
                `sender_id` INT NOT NULL,
                `sender_role` ENUM('admin', 'customer') NOT NULL,
                `message` TEXT NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `invoices` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `customer_id` INT NOT NULL,
                `invoice_number` VARCHAR(50) NOT NULL,
                `title` VARCHAR(255) NOT NULL,
                `amount` DECIMAL(18,2) DEFAULT NULL,
                `due_date` DATE DEFAULT NULL,
                `status` ENUM('unpaid', 'paid', 'cancelled') DEFAULT 'unpaid',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `activity_logs` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT DEFAULT NULL,
                `action` VARCHAR(255) NOT NULL,
                `ip_address` VARCHAR(50) DEFAULT '',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `custom_fields` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `target_entity` ENUM('customer', 'project', 'product') NOT NULL,
                `field_name` VARCHAR(100) NOT NULL,
                `field_label` VARCHAR(150) NOT NULL,
                `field_type` ENUM('text', 'textarea', 'number', 'date') DEFAULT 'text',
                `is_required` TINYINT DEFAULT 0,
                `show_in_customer_panel` TINYINT DEFAULT 1,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uniq_custom_field_name` (`target_entity`, `field_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `custom_field_values` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `field_id` INT NOT NULL,
                `entity_id` INT NOT NULL,
                `field_value` TEXT,
                UNIQUE KEY `uniq_custom_field_value` (`field_id`, `entity_id`),
                FOREIGN KEY (`field_id`) REFERENCES `custom_fields`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `surveys` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `title` VARCHAR(255) NOT NULL,
                `description` TEXT,
                `target_entity` VARCHAR(20) NOT NULL DEFAULT 'project',
                `is_periodic` TINYINT NOT NULL DEFAULT 0,
                `parent_survey_id` INT NULL DEFAULT NULL,
                `delay_days` INT NOT NULL DEFAULT 0,
                `target_scope` VARCHAR(50) DEFAULT 'general',
                `target_id` INT DEFAULT 0,
                `is_active` TINYINT DEFAULT 1,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `survey_questions` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `survey_id` INT NOT NULL,
                `question_text` TEXT NOT NULL,
                `question_type` ENUM('rating_1_10', 'yes_no', 'star_rating', 'satisfaction_5', 'text_free', 'multiple_choice') NOT NULL,
                `question_options` TEXT NULL,
                `sort_order` INT DEFAULT 0,
                FOREIGN KEY (`survey_id`) REFERENCES `surveys`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `survey_responses` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `survey_id` INT NOT NULL,
                `customer_id` INT NOT NULL,
                `entity_type` VARCHAR(20) NOT NULL DEFAULT 'project',
                `entity_id` INT NOT NULL DEFAULT 0,
                `ip_address` VARCHAR(50) DEFAULT '',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`survey_id`) REFERENCES `surveys`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `survey_answers` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `response_id` INT NOT NULL,
                `question_id` INT NOT NULL,
                `answer_value` TEXT NOT NULL,
                FOREIGN KEY (`response_id`) REFERENCES `survey_responses`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`question_id`) REFERENCES `survey_questions`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `notifications` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `title` VARCHAR(255) NOT NULL,
                `body` TEXT,
                `action_url` VARCHAR(500) NULL,
                `ntype` VARCHAR(30) NOT NULL DEFAULT 'info',
                `target_type` VARCHAR(30) NOT NULL DEFAULT 'all',
                `target_filter` VARCHAR(255) DEFAULT '',
                `created_by` INT DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `expires_at` DATETIME NULL,
                `is_active` TINYINT NOT NULL DEFAULT 1
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `notification_recipients` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `notification_id` INT NOT NULL,
                `user_id` INT NOT NULL,
                `is_read` TINYINT NOT NULL DEFAULT 0,
                `read_at` DATETIME NULL,
                UNIQUE KEY `uniq_notif_user` (`notification_id`, `user_id`),
                INDEX `idx_user_read` (`user_id`, `is_read`),
                FOREIGN KEY (`notification_id`) REFERENCES `notifications`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            -- (parity with migrations — fresh installs get these from day one)
            CREATE TABLE IF NOT EXISTS `survey_assignments` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `survey_id` INT NOT NULL,
                `customer_id` INT NOT NULL,
                `entity_type` VARCHAR(20) NOT NULL,
                `entity_id` INT NOT NULL,
                `available_at` DATETIME NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `unique_assignment` (`survey_id`, `customer_id`, `entity_type`, `entity_id`),
                FOREIGN KEY (`survey_id`) REFERENCES `surveys`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `otp_codes` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `mobile` VARCHAR(20) NOT NULL,
                `code` VARCHAR(10) NOT NULL,
                `attempts` TINYINT NOT NULL DEFAULT 0,
                `is_used` TINYINT NOT NULL DEFAULT 0,
                `expires_at` DATETIME NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_otp_mobile` (`mobile`, `is_used`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `sms_events` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `event_key` VARCHAR(50) NOT NULL UNIQUE,
                `title` VARCHAR(150) NOT NULL,
                `is_active` TINYINT NOT NULL DEFAULT 0,
                `pattern_code` VARCHAR(100) DEFAULT '',
                `pattern_var` VARCHAR(50) DEFAULT '',
                `description` VARCHAR(255) DEFAULT ''
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `sms_logs` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `event_key` VARCHAR(50) DEFAULT '',
                `mobile` VARCHAR(20) NOT NULL,
                `user_id` INT NULL,
                `message` VARCHAR(500) DEFAULT '',
                `status` TINYINT NOT NULL DEFAULT 0,
                `error` VARCHAR(255) DEFAULT '',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_sms_logs_mobile` (`mobile`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `error_reports` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NULL,
                `reporter_name` VARCHAR(120) DEFAULT '',
                `reporter_role` VARCHAR(20) DEFAULT '',
                `url` VARCHAR(500) DEFAULT '',
                `message` TEXT,
                `status` VARCHAR(20) DEFAULT 'new',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



            CREATE TABLE IF NOT EXISTS `gamification_rules` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `event_key` VARCHAR(60) NOT NULL UNIQUE,
                `points` INT NOT NULL DEFAULT 0,
                `daily_cap` INT NOT NULL DEFAULT 0,
                `cooldown_seconds` INT NOT NULL DEFAULT 0,
                `is_active` TINYINT(1) NOT NULL DEFAULT 0,
                `description` VARCHAR(255) DEFAULT '',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT chk_gam_rule_points CHECK (`points` >= 0 AND `points` <= 100000),
                CONSTRAINT chk_gam_rule_caps CHECK (`daily_cap` >= 0 AND `cooldown_seconds` >= 0)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `customer_point_wallets` (
                `customer_id` INT PRIMARY KEY,
                `balance` INT NOT NULL DEFAULT 0,
                `total_earned` INT NOT NULL DEFAULT 0,
                `total_spent` INT NOT NULL DEFAULT 0,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT chk_gam_wallet_nonnegative CHECK (`balance` >= 0 AND `total_earned` >= 0 AND `total_spent` >= 0),
                FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `customer_point_ledger` (
                `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
                `customer_id` INT NOT NULL,
                `delta` INT NOT NULL,
                `balance_after` INT NOT NULL,
                `event_key` VARCHAR(60) NOT NULL,
                `reference_type` VARCHAR(60) DEFAULT '',
                `reference_id` VARCHAR(100) DEFAULT '',
                `idempotency_key` VARCHAR(160) NOT NULL UNIQUE,
                `description` VARCHAR(255) DEFAULT '',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_gam_ledger_customer_time` (`customer_id`, `created_at`),
                INDEX `idx_gam_ledger_event_time` (`event_key`, `created_at`),
                INDEX `idx_gam_ledger_customer_event_id` (`customer_id`, `event_key`, `id`),
                FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `bonus_code_campaigns` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(150) NOT NULL,
                `code_hash` CHAR(64) NOT NULL UNIQUE,
                `points` INT NOT NULL DEFAULT 0,
                `starts_at` DATETIME NULL,
                `expires_at` DATETIME NULL,
                `max_redemptions` INT NOT NULL DEFAULT 0,
                `max_per_customer` INT NOT NULL DEFAULT 1,
                `redemptions_count` INT NOT NULL DEFAULT 0,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_by` INT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_gam_bonus_active_expiry` (`is_active`, `expires_at`),
                CONSTRAINT chk_gam_bonus_points CHECK (`points` > 0 AND `points` <= 1000000),
                CONSTRAINT chk_gam_bonus_caps CHECK (`max_redemptions` >= 0 AND `max_per_customer` > 0),
                FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `bonus_code_redemptions` (
                `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
                `campaign_id` INT NOT NULL,
                `customer_id` INT NOT NULL,
                `ledger_id` BIGINT NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uniq_gam_bonus_customer` (`campaign_id`, `customer_id`),
                FOREIGN KEY (`campaign_id`) REFERENCES `bonus_code_campaigns`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`ledger_id`) REFERENCES `customer_point_ledger`(`id`) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `reward_sites` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(150) NOT NULL,
                `base_url` VARCHAR(500) NOT NULL,
                `provider` VARCHAR(40) NOT NULL DEFAULT 'manual_redirect',
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_by` INT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_gam_site_active` (`is_active`),
                FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `reward_catalog` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `site_id` INT NOT NULL,
                `title` VARCHAR(180) NOT NULL,
                `description` VARCHAR(500) DEFAULT '',
                `points_cost` INT NOT NULL,
                `coupon_mode` ENUM('pool', 'fixed') NOT NULL DEFAULT 'pool',
                `fixed_coupon_code` VARCHAR(255) DEFAULT '',
                `valid_days` INT NOT NULL DEFAULT 30,
                `max_per_customer` INT NOT NULL DEFAULT 1,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_by` INT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_gam_reward_active` (`is_active`, `points_cost`),
                CONSTRAINT chk_gam_reward_cost CHECK (`points_cost` > 0 AND `points_cost` <= 100000000),
                CONSTRAINT chk_gam_reward_days CHECK (`valid_days` >= 0 AND `valid_days` <= 3650),
                CONSTRAINT chk_gam_reward_customer_cap CHECK (`max_per_customer` > 0),
                FOREIGN KEY (`site_id`) REFERENCES `reward_sites`(`id`) ON DELETE RESTRICT,
                FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `reward_coupon_pool` (
                `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
                `reward_id` INT NOT NULL,
                `coupon_code` VARCHAR(255) NOT NULL,
                `coupon_hash` CHAR(64) NOT NULL,
                `status` ENUM('available', 'issued', 'disabled') NOT NULL DEFAULT 'available',
                `expires_at` DATETIME NULL,
                `assigned_customer_id` INT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `issued_at` DATETIME NULL,
                UNIQUE KEY `uniq_gam_coupon_hash` (`coupon_hash`),
                INDEX `idx_gam_coupon_available` (`reward_id`, `status`, `expires_at`),
                FOREIGN KEY (`reward_id`) REFERENCES `reward_catalog`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`assigned_customer_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `reward_redemptions` (
                `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
                `reward_id` INT NOT NULL,
                `customer_id` INT NOT NULL,
                `coupon_id` BIGINT NULL,
                `coupon_code_snapshot` VARCHAR(255) DEFAULT '',
                `points_cost` INT NOT NULL,
                `site_id` INT NOT NULL,
                `redirect_url_snapshot` VARCHAR(500) NOT NULL,
                `status` ENUM('issued', 'cancelled') NOT NULL DEFAULT 'issued',
                `idempotency_key` VARCHAR(160) NOT NULL UNIQUE,
                `expires_at` DATETIME NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_gam_redemption_customer` (`customer_id`, `created_at`),
                INDEX `idx_gam_redemption_reward` (`reward_id`, `customer_id`, `status`),
                FOREIGN KEY (`reward_id`) REFERENCES `reward_catalog`(`id`) ON DELETE RESTRICT,
                FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`coupon_id`) REFERENCES `reward_coupon_pool`(`id`) ON DELETE SET NULL,
                FOREIGN KEY (`site_id`) REFERENCES `reward_sites`(`id`) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
                ('module_gamification', '0'),
                ('gamification_store_message', 'کد تخفیف را هنگام خرید در سایت مقصد وارد کنید.');

            INSERT IGNORE INTO `admin_permissions` (`role`, `permission`) VALUES ('admin', 'gamification');

            INSERT IGNORE INTO `gamification_rules` (`event_key`, `points`, `daily_cap`, `cooldown_seconds`, `is_active`, `description`) VALUES
                ('profile_completed', 100, 0, 0, 0, 'امتیاز یک‌باره برای تکمیل همهٔ فیلدهای اجباری پروفایل'),
                ('survey_submitted', 50, 0, 0, 0, 'امتیاز برای تکمیل موفق هر نظرسنجی'),
                ('ticket_created', 10, 30, 3600, 0, 'امتیاز ثبت تیکت جدید با سقف ضداسپم'),
                ('ticket_customer_reply', 5, 20, 1800, 0, 'امتیاز پاسخ مشتری به تیکت با cooldown'),
                ('bonus_code_redeemed', 0, 0, 0, 0, 'امتیاز campaignهای کد هدیه به‌صورت اختصاصی campaign تعیین می‌شود');
