CREATE TABLE IF NOT EXISTS `#__adboard` (
  `id`               INT(11)       NOT NULL AUTO_INCREMENT,
  `title`            VARCHAR(255)  NOT NULL DEFAULT '',
  `category`         VARCHAR(50)   NOT NULL DEFAULT '',
  `description`      MEDIUMTEXT,
  `contact`          VARCHAR(255)  DEFAULT NULL,
  `images`           VARCHAR(2000) DEFAULT NULL  COMMENT 'JSON array of filenames in media/com_adboard/ads/',
  `state`            TINYINT(3)    NOT NULL DEFAULT 0  COMMENT '0=pending 1=published -1=rejected -2=trashed',
  `created`          DATETIME      NOT NULL,
  `publish_up`       DATETIME      DEFAULT NULL        COMMENT 'Set on first admin approval',
  `publish_down`     DATETIME      DEFAULT NULL        COMMENT 'Expiry date — derived from expires_days at submission',
  `hits`             INT UNSIGNED  NOT NULL DEFAULT 0,
  `ip_address`       VARCHAR(45)   DEFAULT NULL        COMMENT 'For spam tracking only — never displayed publicly',
  PRIMARY KEY (`id`),
  KEY `idx_state`    (`state`),
  KEY `idx_category` (`category`),
  KEY `idx_created`  (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
