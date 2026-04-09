-- =====================================================================
-- CFM v3 migration: closes the architecture gaps
-- (lockout / MFA / RBAC / hardware audit / ESP32 enrollment plumbing)
-- Run this against the eu_db database in phpMyAdmin.
-- All statements are idempotent so it is safe to re-run.
-- =====================================================================

-- 1. Failed-login throttling --------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(150) NOT NULL,
  `ip` VARCHAR(64) DEFAULT NULL,
  `attempts` INT(11) NOT NULL DEFAULT 0,
  `locked_until` DATETIME DEFAULT NULL,
  `last_attempt` TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. MFA flag on users --------------------------------------------------
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `mfa_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `mfa_method`  ENUM('none','esp32','passcode') NOT NULL DEFAULT 'none';

-- 3. Hardware verification audit (3-factor RFID/Fingerprint/Password) ---
CREATE TABLE IF NOT EXISTS `hardware_auth_log` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) DEFAULT NULL,
  `rfid_uid` VARCHAR(100) DEFAULT NULL,
  `fingerprint_id` INT(11) DEFAULT NULL,
  `passcode_ok` TINYINT(1) NOT NULL DEFAULT 0,
  `rfid_ok` TINYINT(1) NOT NULL DEFAULT 0,
  `fingerprint_ok` TINYINT(1) NOT NULL DEFAULT 0,
  `result` ENUM('granted','denied') NOT NULL,
  `device_ip` VARCHAR(64) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. Per-user enrollment state for the ESP32 (replaces single-row table) -
CREATE TABLE IF NOT EXISTS `enroll_requests` (
  `user_id` INT(11) NOT NULL,
  `step` INT(11) NOT NULL DEFAULT 0,
  `active` TINYINT(1) NOT NULL DEFAULT 0,
  `updated_at` TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5. Fingerprint storage: keep BLOB template AND a sensor-side slot id --
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `fingerprint_id` INT(11) DEFAULT NULL;

-- 6. Relay auto-disarm — record when the relay was last armed so the
--    check endpoint can clear it after RELAY_HOLD_SECONDS.
ALTER TABLE `relay`
  ADD COLUMN IF NOT EXISTS `fired_at` DATETIME DEFAULT NULL;
