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
                ('admin','custom_fields'),('admin','notifications'),('admin','settings'),('admin','logs'),
                ('admin','admins'),('admin','profile');

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

            CREATE TABLE IF NOT EXISTS `projects` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `customer_id` INT NOT NULL,
                `title` VARCHAR(200) NOT NULL,
                `description` TEXT,
                `status` VARCHAR(50) DEFAULT 'in_progress',
                `image` VARCHAR(255) DEFAULT '',
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
                `image` VARCHAR(255) DEFAULT '',
                `license_key` VARCHAR(255) DEFAULT '',
                `purchase_date` VARCHAR(50) DEFAULT '',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
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
                `amount` VARCHAR(100) NOT NULL,
                `due_date` VARCHAR(50) DEFAULT '',
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
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `custom_field_values` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `field_id` INT NOT NULL,
                `entity_id` INT NOT NULL,
                `field_value` TEXT,
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
                `question_type` ENUM('rating_1_10', 'yes_no', 'star_rating') NOT NULL,
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
                `answer_value` VARCHAR(255) NOT NULL,
                FOREIGN KEY (`response_id`) REFERENCES `survey_responses`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`question_id`) REFERENCES `survey_questions`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `notifications` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `title` VARCHAR(255) NOT NULL,
                `body` TEXT,
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

