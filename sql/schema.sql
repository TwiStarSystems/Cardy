-- Cardy Database Schema
-- Compatible with SabreDAV PDO backends

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- -------------------------------------------------------
-- Custom users table (used by Web UI and DAV auth)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `username`      VARCHAR(50) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `email`         VARCHAR(255) DEFAULT '',
    `display_name`  VARCHAR(255) DEFAULT '',
    `is_admin`      TINYINT(1) DEFAULT 0,
    `role`          VARCHAR(16) NOT NULL DEFAULT 'user',
    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `idx_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `role` VARCHAR(16) NOT NULL DEFAULT 'user' AFTER `is_admin`;
UPDATE `users` SET `role` = CASE WHEN `is_admin` = 1 THEN 'admin' ELSE 'user' END WHERE `role` IS NULL OR `role` = '';

-- -------------------------------------------------------
-- SabreDAV: Principal tables
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `principals` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `uri`         VARBINARY(200) NOT NULL,
    `email`       VARBINARY(80),
    `displayname` VARCHAR(80),
    `vcardurl`    VARCHAR(255),
    UNIQUE KEY `idx_uri` (`uri`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `groupmembers` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `principal_id` INT UNSIGNED NOT NULL,
    `member_id`    INT UNSIGNED NOT NULL,
    UNIQUE KEY `idx_unique` (`principal_id`, `member_id`),
    FOREIGN KEY (`principal_id`) REFERENCES `principals` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`member_id`)    REFERENCES `principals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- SabreDAV: CalDAV tables
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `calendarobjects` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `calendardata`    MEDIUMBLOB,
    `uri`             VARBINARY(200),
    `calendarid`      INT UNSIGNED NOT NULL,
    `lastmodified`    INT UNSIGNED,
    `etag`            VARBINARY(32),
    `size`            INT UNSIGNED NOT NULL,
    `componenttype`   VARBINARY(8),
    `firstoccurence`  INT UNSIGNED,
    `lastoccurence`   INT UNSIGNED,
    `uid`             VARBINARY(200),
    UNIQUE KEY `idx_calendarid_uri` (`calendarid`, `uri`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `calendars` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `synctoken`   INT UNSIGNED NOT NULL DEFAULT 1,
    `components`  VARBINARY(21)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `calendarinstances` (
    `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `calendarid`          INT UNSIGNED NOT NULL,
    `principaluri`        VARBINARY(100),
    `access`              TINYINT(1) NOT NULL DEFAULT 1,
    `displayname`         VARCHAR(100),
    `uri`                 VARBINARY(200),
    `description`         MEDIUMTEXT,
    `calendarcolor`       VARBINARY(10),
    `timezone`            MEDIUMTEXT,
    `transparent`         TINYINT(1) NOT NULL DEFAULT 0,
    `share_href`          VARBINARY(100),
    `share_displayname`   VARCHAR(100),
    `share_invitestatus`  TINYINT(1) NOT NULL DEFAULT 2,
    UNIQUE KEY `idx_principaluri_uri` (`principaluri`, `uri`),
    FOREIGN KEY (`calendarid`) REFERENCES `calendars` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `calendarchanges` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `uri`         VARBINARY(200) NOT NULL,
    `synctoken`   INT UNSIGNED NOT NULL,
    `calendarid`  INT UNSIGNED NOT NULL,
    `operation`   TINYINT(1) NOT NULL,
    FOREIGN KEY (`calendarid`) REFERENCES `calendars` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `calendarsubscriptions` (
    `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `uri`                VARBINARY(200) NOT NULL,
    `principaluri`       VARBINARY(100) NOT NULL,
    `source`             TEXT,
    `displayname`        VARCHAR(100),
    `refreshrate`        VARCHAR(10),
    `calendarorder`      INT UNSIGNED NOT NULL DEFAULT 0,
    `calendarcolor`      VARBINARY(10),
    `striptodos`         TINYINT(1) NULL,
    `stripalarms`        TINYINT(1) NULL,
    `stripattachments`   TINYINT(1) NULL,
    `lastmodified`       INT UNSIGNED,
    UNIQUE KEY `idx_principaluri_uri` (`principaluri`, `uri`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `schedulingobjects` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `principaluri`  VARBINARY(255),
    `calendardata`  MEDIUMBLOB,
    `uri`           VARBINARY(200),
    `lastmodified`  INT UNSIGNED,
    `etag`          VARBINARY(32),
    `size`          INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- SabreDAV: CardDAV tables
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `addressbooks` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `principaluri` VARBINARY(100),
    `displayname`  VARCHAR(255),
    `uri`          VARBINARY(200),
    `description`  MEDIUMTEXT,
    `synctoken`    INT UNSIGNED NOT NULL DEFAULT 1,
    UNIQUE KEY `idx_principaluri_uri` (`principaluri`, `uri`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cards` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `addressbookid` INT UNSIGNED NOT NULL,
    `local_id`      INT UNSIGNED NULL,
    `carddata`      MEDIUMBLOB,
    `uri`           VARBINARY(2048),
    `lastmodified`  INT UNSIGNED,
    `etag`          VARBINARY(32),
    `size`          INT UNSIGNED NOT NULL,
    UNIQUE KEY `idx_addressbook_local_id` (`addressbookid`, `local_id`),
    FOREIGN KEY (`addressbookid`) REFERENCES `addressbooks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `cards` ADD COLUMN IF NOT EXISTS `local_id` INT UNSIGNED NULL AFTER `addressbookid`;

CREATE TABLE IF NOT EXISTS `addressbookchanges` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `uri`           VARBINARY(200) NOT NULL,
    `synctoken`     INT UNSIGNED NOT NULL,
    `addressbookid` INT UNSIGNED NOT NULL,
    `operation`     TINYINT(1) NOT NULL,
    FOREIGN KEY (`addressbookid`) REFERENCES `addressbooks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- Contact groups / labels
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contact_groups` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `addressbook_id` INT UNSIGNED NOT NULL,
    `name`           VARCHAR(100) NOT NULL,
    `color`          VARCHAR(20) DEFAULT '',
    `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `idx_ab_name` (`addressbook_id`, `name`),
    FOREIGN KEY (`addressbook_id`) REFERENCES `addressbooks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `contact_group_members` (
    `group_id` INT UNSIGNED NOT NULL,
    `card_id`  INT UNSIGNED NOT NULL,
    PRIMARY KEY (`group_id`, `card_id`),
    FOREIGN KEY (`group_id`) REFERENCES `contact_groups` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`card_id`)  REFERENCES `cards` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- Contact history / activity log
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contact_history` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `card_id`    INT UNSIGNED NOT NULL,
    `action`     VARCHAR(30) NOT NULL,
    `detail`     TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_card_id` (`card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `totp_secret`    VARCHAR(32)    NULL     DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `totp_enabled`  TINYINT(1)   NOT NULL DEFAULT 0;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `contact_quota` INT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `event_quota`   INT UNSIGNED NOT NULL DEFAULT 0;

-- -------------------------------------------------------
-- Audit log
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_log` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `username`   VARCHAR(50)  NOT NULL DEFAULT '',
    `action`     VARCHAR(60)  NOT NULL,
    `detail`     TEXT,
    `ip`         VARCHAR(45)  NOT NULL DEFAULT '',
    `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_username` (`username`),
    KEY `idx_action`   (`action`),
    KEY `idx_created`  (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- Login rate limiting
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `username`     VARCHAR(50)  NOT NULL DEFAULT '',
    `ip`           VARCHAR(45)  NOT NULL,
    `attempted_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_ip_time`       (`ip`, `attempted_at`),
    KEY `idx_username_time` (`username`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- App-specific passwords (for DAV clients)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `app_passwords` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `username`     VARCHAR(50) NOT NULL,
    `name`         VARCHAR(100) NOT NULL,
    `token_hash`   VARCHAR(255) NOT NULL,
    `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `last_used_at` TIMESTAMP NULL DEFAULT NULL,
    KEY `idx_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- Address book sharing (web UI only)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `addressbook_shares` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `addressbook_id` INT UNSIGNED NOT NULL,
    `shared_with`    VARCHAR(50)  NOT NULL,
    `can_write`      TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `idx_ab_user` (`addressbook_id`, `shared_with`),
    FOREIGN KEY (`addressbook_id`) REFERENCES `addressbooks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- User invite tokens (admin-generated sign-up links)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `invite_tokens` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `token`       VARCHAR(64)  NOT NULL,
    `created_by`  VARCHAR(50)  NOT NULL,
    `note`        VARCHAR(255) NOT NULL DEFAULT '',
    `max_uses`    INT UNSIGNED NOT NULL DEFAULT 1,
    `uses_count`  INT UNSIGNED NOT NULL DEFAULT 0,
    `expires_at`  TIMESTAMP    NULL DEFAULT NULL,
    `created_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `idx_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- Password reset tokens
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `username`   VARCHAR(50)  NOT NULL,
    `token_hash` VARCHAR(64)  NOT NULL,
    `expires_at` TIMESTAMP    NOT NULL,
    `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `idx_token`    (`token_hash`),
    KEY        `idx_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;
