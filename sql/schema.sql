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
    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `idx_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    `carddata`      MEDIUMBLOB,
    `uri`           VARBINARY(2048),
    `lastmodified`  INT UNSIGNED,
    `etag`          VARBINARY(32),
    `size`          INT UNSIGNED NOT NULL,
    FOREIGN KEY (`addressbookid`) REFERENCES `addressbooks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `addressbookchanges` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `uri`           VARBINARY(200) NOT NULL,
    `synctoken`     INT UNSIGNED NOT NULL,
    `addressbookid` INT UNSIGNED NOT NULL,
    `operation`     TINYINT(1) NOT NULL,
    FOREIGN KEY (`addressbookid`) REFERENCES `addressbooks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET foreign_key_checks = 1;
