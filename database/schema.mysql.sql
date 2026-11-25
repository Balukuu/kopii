-- Kopii Marketplace — MySQL / MariaDB Schema (InnoDB, utf8mb4)
-- Optimized for shared LiteSpeed & cPanel hosting (kopii.online)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `full_name` VARCHAR(120) NOT NULL,
    `email` VARCHAR(190) NOT NULL,
    `phone` VARCHAR(40) DEFAULT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` VARCHAR(20) NOT NULL DEFAULT 'provider',
    `avatar_path` VARCHAR(255) DEFAULT NULL,
    `email_verified_at` DATETIME DEFAULT NULL,
    `suspended_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL,
    `icon` VARCHAR(50) NOT NULL DEFAULT 'briefcase',
    `description` VARCHAR(255) DEFAULT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_categories_slug` (`slug`),
    KEY `idx_categories_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `providers` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED NOT NULL,
    `business_name` VARCHAR(150) NOT NULL,
    `slug` VARCHAR(170) NOT NULL,
    `provider_type` VARCHAR(20) NOT NULL DEFAULT 'freelancer',
    `headline` VARCHAR(200) NOT NULL,
    `bio` TEXT NOT NULL,
    `city` VARCHAR(80) NOT NULL DEFAULT 'Kampala',
    `neighborhood` VARCHAR(120) DEFAULT NULL,
    `service_areas` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(40) NOT NULL,
    `whatsapp` VARCHAR(40) NOT NULL,
    `public_email` VARCHAR(190) DEFAULT NULL,
    `website` VARCHAR(255) DEFAULT NULL,
    `instagram` VARCHAR(120) DEFAULT NULL,
    `starting_price_ugx` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `years_experience` INT UNSIGNED NOT NULL DEFAULT 1,
    `response_time` VARCHAR(60) NOT NULL DEFAULT 'Within 2 hours',
    `specialties` VARCHAR(500) DEFAULT NULL,
    `avatar_path` VARCHAR(255) DEFAULT NULL,
    `cover_path` VARCHAR(255) DEFAULT NULL,
    `is_verified` TINYINT(1) NOT NULL DEFAULT 0,
    `verified_at` DATETIME DEFAULT NULL,
    `verified_by` INT UNSIGNED DEFAULT NULL,
    `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
    `featured_until` DATE DEFAULT NULL,
    `plan_id` INT UNSIGNED DEFAULT NULL,
    `plan_expires_on` DATE DEFAULT NULL,
    `is_available` TINYINT(1) NOT NULL DEFAULT 1,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
    `views_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_providers_user` (`user_id`),
    UNIQUE KEY `uq_providers_slug` (`slug`),
    KEY `idx_providers_category` (`category_id`),
    KEY `idx_providers_city` (`city`),
    KEY `idx_providers_status_featured` (`status`, `is_featured`, `is_verified`),
    CONSTRAINT `fk_providers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_providers_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `portfolio_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `provider_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(160) NOT NULL,
    `project_tag` VARCHAR(80) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `image_path` VARCHAR(255) NOT NULL,
    `client_location` VARCHAR(120) DEFAULT NULL,
    `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_portfolio_provider` (`provider_id`, `sort_order`),
    KEY `idx_portfolio_featured` (`is_featured`, `created_at`),
    CONSTRAINT `fk_portfolio_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `service_packages` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `provider_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(140) NOT NULL,
    `price_ugx` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `price_unit` VARCHAR(50) NOT NULL DEFAULT 'per session',
    `turnaround` VARCHAR(80) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `deliverables` TEXT DEFAULT NULL,
    `is_popular` TINYINT(1) NOT NULL DEFAULT 0,
    `sort_order` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_packages_provider` (`provider_id`, `sort_order`),
    CONSTRAINT `fk_packages_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inquiries` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `provider_id` INT UNSIGNED NOT NULL,
    `client_user_id` INT UNSIGNED DEFAULT NULL,
    `package_id` INT UNSIGNED DEFAULT NULL,
    `client_name` VARCHAR(120) NOT NULL,
    `client_email` VARCHAR(190) NOT NULL,
    `client_phone` VARCHAR(40) NOT NULL,
    `event_date` VARCHAR(30) DEFAULT NULL,
    `event_location` VARCHAR(140) DEFAULT NULL,
    `budget_ugx` BIGINT UNSIGNED DEFAULT NULL,
    `project_details` TEXT NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'new',
    `provider_notes` TEXT DEFAULT NULL,
    `product_id` INT UNSIGNED DEFAULT NULL,
    `product_name` VARCHAR(160) DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_inquiries_provider_status` (`provider_id`, `status`, `created_at`),
    KEY `idx_inquiries_client` (`client_user_id`),
    CONSTRAINT `fk_inquiries_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reviews` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `provider_id` INT UNSIGNED NOT NULL,
    `reviewer_user_id` INT UNSIGNED DEFAULT NULL,
    `reviewer_name` VARCHAR(120) NOT NULL,
    `reviewer_city` VARCHAR(80) DEFAULT NULL,
    `service_hired` VARCHAR(120) DEFAULT NULL,
    `rating` TINYINT UNSIGNED NOT NULL,
    `comment` TEXT NOT NULL,
    `status` VARCHAR(12) NOT NULL DEFAULT 'published',
    `verified_request` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_reviews_provider` (`provider_id`, `created_at`),
    CONSTRAINT `fk_reviews_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_resets` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `token_hash` CHAR(64) NOT NULL,
    `ip` VARCHAR(45) DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_password_resets_token` (`token_hash`),
    KEY `idx_password_resets_user` (`user_id`, `created_at`),
    CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rate_limits` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `bucket` VARCHAR(40) NOT NULL,
    `subject` CHAR(64) NOT NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_rate_limits_lookup` (`bucket`, `subject`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_verifications` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `token_hash` CHAR(64) NOT NULL,
    `created_at` DATETIME NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_email_verifications_token` (`token_hash`),
    KEY `idx_email_verifications_user` (`user_id`, `created_at`),
    CONSTRAINT `fk_email_verifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every action an administrator takes (verify, pause, suspend, delete a review...). `target_label` is a
-- snapshot of the name at the time, so the record still reads properly after the target is deleted.
CREATE TABLE IF NOT EXISTS `admin_log` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `admin_user_id` INT UNSIGNED DEFAULT NULL,
    `action` VARCHAR(40) NOT NULL,
    `target_type` VARCHAR(20) NOT NULL,
    `target_id` INT UNSIGNED DEFAULT NULL,
    `target_label` VARCHAR(190) NOT NULL DEFAULT '',
    `note` VARCHAR(500) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_admin_log_created` (`created_at`),
    KEY `idx_admin_log_target` (`target_type`, `target_id`),
    CONSTRAINT `fk_admin_log_user` FOREIGN KEY (`admin_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The shop. Products belong to a provider profile (a freelancer or a business) and are bought by
-- contacting the seller: an enquiry goes into the same inbox as quote requests (inquiries.product_id).
CREATE TABLE IF NOT EXISTS `product_categories` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(120) NOT NULL,
    `icon` VARCHAR(40) NOT NULL DEFAULT 'briefcase',
    `sort_order` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_product_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `products` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `provider_id` INT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(160) NOT NULL,
    `slug` VARCHAR(190) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `price_ugx` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `price_unit` VARCHAR(30) NOT NULL DEFAULT 'each',
    `item_condition` VARCHAR(20) NOT NULL DEFAULT 'new',
    `stock_status` VARCHAR(20) NOT NULL DEFAULT 'in_stock',
    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
    `views_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_products_slug` (`slug`),
    KEY `idx_products_provider` (`provider_id`, `status`),
    KEY `idx_products_category` (`category_id`, `status`),
    CONSTRAINT `fk_products_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `product_categories` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_images` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT UNSIGNED NOT NULL,
    `image_path` VARCHAR(255) NOT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_product_images_product` (`product_id`, `sort_order`),
    CONSTRAINT `fk_product_images_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The opportunities board. Anyone with a confirmed email can post what they need; providers answer
-- with a proposal. The poster's contact details are never shown publicly: proposals reach them
-- through the dashboard and by email.
CREATE TABLE IF NOT EXISTS `opportunities` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED DEFAULT NULL,
    `kind` VARCHAR(12) NOT NULL DEFAULT 'project',
    `title` VARCHAR(160) NOT NULL,
    `description` TEXT NOT NULL,
    `budget_min_ugx` BIGINT UNSIGNED DEFAULT NULL,
    `budget_max_ugx` BIGINT UNSIGNED DEFAULT NULL,
    `city` VARCHAR(60) NOT NULL DEFAULT 'Kampala',
    `closes_on` DATE NOT NULL,
    `status` VARCHAR(12) NOT NULL DEFAULT 'open',
    `views_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_opportunities_board` (`status`, `closes_on`),
    KEY `idx_opportunities_user` (`user_id`, `created_at`),
    CONSTRAINT `fk_opportunities_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_opportunities_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `opportunity_responses` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `opportunity_id` INT UNSIGNED NOT NULL,
    `provider_id` INT UNSIGNED NOT NULL,
    `message` TEXT NOT NULL,
    `quote_ugx` BIGINT UNSIGNED DEFAULT NULL,
    `timeline` VARCHAR(80) DEFAULT NULL,
    `status` VARCHAR(12) NOT NULL DEFAULT 'new',
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_opportunity_responses_pair` (`opportunity_id`, `provider_id`),
    KEY `idx_opportunity_responses_provider` (`provider_id`, `created_at`),
    CONSTRAINT `fk_opportunity_responses_opportunity` FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_opportunity_responses_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Messages to the Kopii team: the contact form (kind = contact) and "report this listing" (kind = report).
-- A report names what it is about (target_type + target_id) and keeps the title as it was, so the
-- admin inbox still reads properly after the listing is deleted. user_id is set when the sender was signed in.
CREATE TABLE IF NOT EXISTS `support_messages` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `kind` VARCHAR(10) NOT NULL DEFAULT 'contact',
    `user_id` INT UNSIGNED DEFAULT NULL,
    `name` VARCHAR(120) NOT NULL DEFAULT '',
    `email` VARCHAR(190) NOT NULL DEFAULT '',
    `subject` VARCHAR(160) NOT NULL DEFAULT '',
    `message` TEXT NOT NULL,
    `target_type` VARCHAR(12) DEFAULT NULL,
    `target_id` INT UNSIGNED DEFAULT NULL,
    `target_label` VARCHAR(190) NOT NULL DEFAULT '',
    `status` VARCHAR(10) NOT NULL DEFAULT 'open',
    `handled_by` INT UNSIGNED DEFAULT NULL,
    `handled_at` DATETIME DEFAULT NULL,
    `note` VARCHAR(500) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_support_messages_status` (`status`, `created_at`),
    KEY `idx_support_messages_target` (`target_type`, `target_id`),
    CONSTRAINT `fk_support_messages_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Plans an administrator can put a listing on. The default plan (is_default = 1) is the free one every
-- listing falls back to; it cannot be retired. A paid plan costs price_ugx for period_months, and
-- product_limit is how many shop products a listing on it may have (NULL = the site-wide limit).
-- Kopii does not take payment yet: an administrator records a payment received by hand, then assigns
-- the plan to the listing (providers.plan_id, providers.plan_expires_on).
CREATE TABLE IF NOT EXISTS `plans` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(60) NOT NULL,
    `description` VARCHAR(255) NOT NULL DEFAULT '',
    `price_ugx` INT UNSIGNED NOT NULL DEFAULT 0,
    `period_months` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `product_limit` INT UNSIGNED DEFAULT NULL,
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_plans_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Site-wide numbers an administrator can change without editing files (the commission rates).
CREATE TABLE IF NOT EXISTS `platform_settings` (
    `setting_key` VARCHAR(60) NOT NULL,
    `setting_value` VARCHAR(255) NOT NULL DEFAULT '',
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
