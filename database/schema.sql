-- TachoSystem Database Schema
-- MySQL 5.7+ / MariaDB 10.3+
-- Encoding: utf8mb4

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `companies` (
  `id`             INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `name`           VARCHAR(255)     NOT NULL,
  `nip`            VARCHAR(20)      DEFAULT NULL,
  `address`        VARCHAR(255)     DEFAULT NULL,
  `city`           VARCHAR(100)     DEFAULT NULL,
  `country`        VARCHAR(100)     DEFAULT 'Poland',
  `phone`          VARCHAR(50)      DEFAULT NULL,
  `email`          VARCHAR(255)     DEFAULT NULL,
  `logo`           VARCHAR(255)     DEFAULT NULL,
  `license_secret` VARCHAR(64)      DEFAULT NULL COMMENT 'Per-company HMAC secret for license key verification',
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nip` (`nip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration helper (run once on existing installs):
-- ALTER TABLE `companies` ADD COLUMN `license_secret` VARCHAR(64) DEFAULT NULL COMMENT 'Per-company HMAC secret for license key verification';

CREATE TABLE IF NOT EXISTS `licenses` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED  NOT NULL,
  `license_key`   VARCHAR(30)   NOT NULL,
  `sha256_hash`   VARCHAR(64)   NOT NULL,
  `modules`       JSON          DEFAULT NULL,
  `max_operators` SMALLINT UNSIGNED NOT NULL DEFAULT 5,
  `max_drivers`   SMALLINT UNSIGNED NOT NULL DEFAULT 50,
  `valid_from`    DATE          NOT NULL,
  `valid_to`      DATE          NOT NULL,
  `hardware_id`   VARCHAR(64)   DEFAULT NULL,
  `is_active`     TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_license_key` (`license_key`),
  KEY `fk_lic_company` (`company_id`),
  CONSTRAINT `fk_lic_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED  DEFAULT NULL,
  `name`          VARCHAR(255)  NOT NULL,
  `email`         VARCHAR(255)  NOT NULL,
  `password_hash` VARCHAR(255)  NOT NULL,
  `role`          ENUM('superadmin','admin','operator') NOT NULL DEFAULT 'operator',
  `is_active`     TINYINT(1)    NOT NULL DEFAULT 1,
  `last_login`    DATETIME      DEFAULT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email` (`email`),
  KEY `fk_usr_company` (`company_id`),
  CONSTRAINT `fk_usr_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `drivers` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`     INT UNSIGNED NOT NULL,
  `first_name`     VARCHAR(100) NOT NULL,
  `last_name`      VARCHAR(100) NOT NULL,
  `birth_date`     DATE         DEFAULT NULL,
  `license_number` VARCHAR(50)  DEFAULT NULL,
  `card_number`    VARCHAR(20)  DEFAULT NULL,
  `card_expiry`    DATE         DEFAULT NULL,
  `nationality`    VARCHAR(3)   DEFAULT 'PL',
  `phone`          VARCHAR(50)  DEFAULT NULL,
  `email`          VARCHAR(255) DEFAULT NULL,
  `notes`          TEXT         DEFAULT NULL,
  `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_drv_company` (`company_id`),
  KEY `idx_drv_card` (`card_number`),
  CONSTRAINT `fk_drv_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vehicles` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`        INT UNSIGNED NOT NULL,
  `registration`      VARCHAR(20)  NOT NULL,
  `brand`             VARCHAR(100) DEFAULT NULL,
  `model`             VARCHAR(100) DEFAULT NULL,
  `year`              SMALLINT UNSIGNED DEFAULT NULL,
  `vin`               VARCHAR(17)  DEFAULT NULL,
  `tachograph_serial` VARCHAR(50)  DEFAULT NULL,
  `tachograph_type`   VARCHAR(50)  DEFAULT NULL,
  `notes`             TEXT         DEFAULT NULL,
  `is_active`         TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_veh_company` (`company_id`),
  UNIQUE KEY `uq_reg_company` (`company_id`, `registration`),
  CONSTRAINT `fk_veh_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tacho_files` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED NOT NULL,
  `driver_id`     INT UNSIGNED DEFAULT NULL,
  `vehicle_id`    INT UNSIGNED DEFAULT NULL,
  `file_type`     ENUM('driver_card','tachograph') NOT NULL DEFAULT 'driver_card',
  `original_name` VARCHAR(255) NOT NULL,
  `stored_name`   VARCHAR(255) NOT NULL,
  `file_size`     INT UNSIGNED NOT NULL DEFAULT 0,
  `parsed_at`     DATETIME     DEFAULT NULL,
  `parse_status`  ENUM('pending','success','error') NOT NULL DEFAULT 'pending',
  `parse_error`   TEXT         DEFAULT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_tf_company` (`company_id`),
  KEY `fk_tf_driver`  (`driver_id`),
  KEY `fk_tf_vehicle` (`vehicle_id`),
  CONSTRAINT `fk_tf_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tf_driver`  FOREIGN KEY (`driver_id`)  REFERENCES `drivers`  (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tf_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `activities` (
  `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `tacho_file_id`    INT UNSIGNED  NOT NULL,
  `driver_id`        INT UNSIGNED  DEFAULT NULL,
  `vehicle_id`       INT UNSIGNED  DEFAULT NULL,
  `activity_date`    DATE          NOT NULL,
  `start_time`       TIME          NOT NULL,
  `end_time`         TIME          NOT NULL,
  `duration_minutes` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `activity_type`    ENUM('driving','work','availability','rest','break') NOT NULL,
  `country_code`     VARCHAR(3)    DEFAULT NULL,
  `slot`             TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_act_file`    (`tacho_file_id`),
  KEY `fk_act_driver`  (`driver_id`),
  KEY `fk_act_vehicle` (`vehicle_id`),
  KEY `idx_act_date`   (`activity_date`),
  CONSTRAINT `fk_act_file`    FOREIGN KEY (`tacho_file_id`) REFERENCES `tacho_files` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_act_driver`  FOREIGN KEY (`driver_id`)     REFERENCES `drivers`     (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_act_vehicle` FOREIGN KEY (`vehicle_id`)    REFERENCES `vehicles`    (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `violations` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `activity_id`     INT UNSIGNED DEFAULT NULL,
  `driver_id`       INT UNSIGNED DEFAULT NULL,
  `tacho_file_id`   INT UNSIGNED DEFAULT NULL,
  `violation_type`  VARCHAR(100) NOT NULL,
  `description`     TEXT         NOT NULL,
  `severity`        ENUM('minor','major','critical') NOT NULL DEFAULT 'minor',
  `regulation_ref`  VARCHAR(100) DEFAULT NULL,
  `fine_amount_min` DECIMAL(10,2) DEFAULT NULL,
  `fine_amount_max` DECIMAL(10,2) DEFAULT NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_vio_activity` (`activity_id`),
  KEY `fk_vio_driver`   (`driver_id`),
  KEY `fk_vio_file`     (`tacho_file_id`),
  CONSTRAINT `fk_vio_activity` FOREIGN KEY (`activity_id`)   REFERENCES `activities`  (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_vio_driver`   FOREIGN KEY (`driver_id`)     REFERENCES `drivers`     (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_vio_file`     FOREIGN KEY (`tacho_file_id`) REFERENCES `tacho_files` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reports` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`  INT UNSIGNED NOT NULL,
  `driver_id`   INT UNSIGNED DEFAULT NULL,
  `report_type` VARCHAR(50)  NOT NULL,
  `period_from` DATE         NOT NULL,
  `period_to`   DATE         NOT NULL,
  `data`        JSON         DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`  INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_rep_company` (`company_id`),
  KEY `fk_rep_driver`  (`driver_id`),
  CONSTRAINT `fk_rep_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rep_driver`  FOREIGN KEY (`driver_id`)  REFERENCES `drivers`  (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_log` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED DEFAULT NULL,
  `company_id` INT UNSIGNED DEFAULT NULL,
  `action`     VARCHAR(100) NOT NULL,
  `details`    TEXT         DEFAULT NULL,
  `ip_address` VARCHAR(45)  DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_al_user`    (`user_id`),
  KEY `idx_al_company` (`company_id`),
  KEY `idx_al_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
