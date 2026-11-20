-- Điểm thưởng · Giới thiệu bạn bè · Thi thử · Gói gia đình — CHỈ dùng khi production không chạy được `php artisan migrate`.
-- Tương đương 4 migration 2026_11_19_000100 → 000400. Lấy DDL từ DB local ngày 19/11/2026.
--
-- CHẠY MỘT LẦN. Các bảng mới dùng CREATE TABLE IF NOT EXISTS, nhưng các lệnh ALTER (thêm cột) chạy lần hai sẽ báo
-- "Duplicate column" — gặp lỗi đó nghĩa là đã chạy rồi, bỏ qua. Sao lưu DB trước khi chạy.
-- Cuối file ghi tên migration vào bảng `migrations` để sau này `php artisan migrate` không tạo lại.

SET NAMES utf8mb4;

-- 1. Điểm thưởng + huy hiệu --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reward_rules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reason` varchar(40) NOT NULL,
  `label` varchar(120) NOT NULL,
  `points` smallint(5) unsigned NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reward_rules_reason_unique` (`reason`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `reward_rules` (`reason`, `label`, `points`, `is_active`, `created_at`, `updated_at`) VALUES
('lesson_completed', 'Học xong một bài học', 10, 1, NOW(), NOW()),
('exam_finished', 'Nộp một đề kiểm tra (lần đầu mỗi đề)', 5, 1, NOW(), NOW()),
('exam_high_score', 'Đạt từ 80% ở một đề kiểm tra', 10, 1, NOW(), NOW()),
('path_session_done', 'Hoàn thành một buổi trong lộ trình', 15, 1, NOW(), NOW()),
('streak_day', 'Một ngày học đủ thời gian (giữ chuỗi)', 5, 1, NOW(), NOW());

CREATE TABLE IF NOT EXISTS `reward_points` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `points` smallint(6) NOT NULL,
  `reason` varchar(40) NOT NULL,
  `source_type` varchar(40) NOT NULL,
  `source_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `reward_points_once` (`user_id`,`reason`,`source_type`,`source_id`),
  KEY `reward_points_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `reward_points_created_at_index` (`created_at`),
  CONSTRAINT `reward_points_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_badges` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `badge_key` varchar(40) NOT NULL,
  `earned_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_badges_user_id_badge_key_unique` (`user_id`,`badge_key`),
  CONSTRAINT `user_badges_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `student_profiles` ADD COLUMN `hide_from_leaderboard` tinyint(1) NOT NULL DEFAULT 0 AFTER `interests`;

-- 2. Giới thiệu bạn bè ------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `referral_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `new_user_percent` tinyint(3) unsigned NOT NULL DEFAULT 10,
  `referrer_percent` tinyint(3) unsigned NOT NULL DEFAULT 20,
  `voucher_valid_days` smallint(5) unsigned NOT NULL DEFAULT 60,
  `max_rewards_per_referrer` smallint(5) unsigned NOT NULL DEFAULT 20,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `referral_settings` (`is_enabled`, `new_user_percent`, `referrer_percent`, `voucher_valid_days`, `max_rewards_per_referrer`, `created_at`, `updated_at`)
SELECT 1, 10, 20, 60, 20, NOW(), NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `referral_settings`);

ALTER TABLE `users`
  ADD COLUMN `referral_code` varchar(20) NULL AFTER `remember_token`,
  ADD COLUMN `referred_by` bigint(20) unsigned NULL AFTER `referral_code`,
  ADD UNIQUE KEY `users_referral_code_unique` (`referral_code`),
  ADD CONSTRAINT `users_referred_by_foreign` FOREIGN KEY (`referred_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `vouchers`
  ADD COLUMN `owner_id` bigint(20) unsigned NULL AFTER `created_by`,
  ADD CONSTRAINT `vouchers_owner_id_foreign` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS `referrals` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `referrer_id` bigint(20) unsigned NOT NULL,
  `referred_id` bigint(20) unsigned NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `welcome_voucher_id` bigint(20) unsigned DEFAULT NULL,
  `reward_voucher_id` bigint(20) unsigned DEFAULT NULL,
  `payment_id` bigint(20) unsigned DEFAULT NULL,
  `rewarded_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `referrals_referred_id_unique` (`referred_id`),
  KEY `referrals_welcome_voucher_id_foreign` (`welcome_voucher_id`),
  KEY `referrals_reward_voucher_id_foreign` (`reward_voucher_id`),
  KEY `referrals_payment_id_foreign` (`payment_id`),
  KEY `referrals_referrer_id_status_index` (`referrer_id`,`status`),
  CONSTRAINT `referrals_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `referrals_referred_id_foreign` FOREIGN KEY (`referred_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `referrals_referrer_id_foreign` FOREIGN KEY (`referrer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `referrals_reward_voucher_id_foreign` FOREIGN KEY (`reward_voucher_id`) REFERENCES `vouchers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `referrals_welcome_voucher_id_foreign` FOREIGN KEY (`welcome_voucher_id`) REFERENCES `vouchers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Thi thử ----------------------------------------------------------------------------------
ALTER TABLE `exams` MODIFY `type` ENUM('practice','quiz','test','mock') NOT NULL DEFAULT 'test';
ALTER TABLE `exams`
  ADD COLUMN `mock_track` varchar(20) NULL AFTER `type`,
  ADD KEY `exams_type_mock_track_status_index` (`type`,`mock_track`,`status`);

-- 4. Gói gia đình -----------------------------------------------------------------------------
ALTER TABLE `packages` ADD COLUMN `max_members` tinyint(3) unsigned NOT NULL DEFAULT 1 AFTER `duration_days`;
ALTER TABLE `subscriptions`
  ADD COLUMN `join_code` varchar(12) NULL AFTER `source`,
  ADD UNIQUE KEY `subscriptions_join_code_unique` (`join_code`);

CREATE TABLE IF NOT EXISTS `subscription_members` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `subscription_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `joined_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subscription_members_subscription_id_user_id_unique` (`subscription_id`,`user_id`),
  KEY `subscription_members_user_id_index` (`user_id`),
  CONSTRAINT `subscription_members_subscription_id_foreign` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `subscription_members_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ghi nhận đã chạy ---------------------------------------------------------------------------
INSERT INTO `migrations` (`migration`, `batch`)
SELECT m.name, (SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`) FROM (
    SELECT '2026_11_19_000100_create_rewards_tables' AS name
    UNION ALL SELECT '2026_11_19_000200_create_referral_tables'
    UNION ALL SELECT '2026_11_19_000300_add_mock_exams'
    UNION ALL SELECT '2026_11_19_000400_create_family_plans'
) m WHERE NOT EXISTS (SELECT 1 FROM `migrations` x WHERE x.`migration` = m.name);
