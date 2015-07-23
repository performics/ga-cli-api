CREATE TABLE `google_analytics_api_fetch_log` (
  `id` smallint unsigned AUTO_INCREMENT PRIMARY KEY,
  `entity` varchar(255),
  `etag` varchar(255),
  `result_count` smallint unsigned,
  `fetch_date` int unsigned
) ENGINE = InnoDB;

CREATE TABLE `google_analytics_api_columns` (
  `id` smallint unsigned AUTO_INCREMENT PRIMARY KEY,
  `name` varchar(64) NOT NULL,
  `type` varchar(64) NOT NULL,
  `data_type` varchar(64) NOT NULL,
  `replaced_by` varchar(64),
  `group` varchar(64) NOT NULL,
  `ui_name` varchar(255),
  `description` varchar(2048),
  `calculation` varchar(255),
  `min_template_index` tinyint unsigned,
  `max_template_index` tinyint unsigned,
  `min_template_index_premium` tinyint unsigned,
  `max_template_index_premium` tinyint unsigned,
  `allowed_in_segments` tinyint unsigned NOT NULL,
  `deprecated` tinyint unsigned NOT NULL,
  `cdate` int unsigned,
  `mdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `name_index` (`name`),
  KEY `group_index` (`group`)
) ENGINE = InnoDB;

CREATE TABLE `google_analytics_api_account_summaries` (
  `id` smallint unsigned AUTO_INCREMENT PRIMARY KEY,
  `gid` varchar(16) NOT NULL,
  `name` varchar(64),
  `visible` tinyint unsigned NOT NULL default '1',
  `cdate` int unsigned,
  `mdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `gid_index` (`gid`),
  KEY `name_index` (`name`)
) ENGINE = InnoDB;

CREATE TABLE `google_analytics_api_web_property_summaries` (
  `id` mediumint unsigned AUTO_INCREMENT PRIMARY KEY,
  `gid` varchar(32) NOT NULL,
  `gaaas_id` smallint unsigned NOT NULL,
  `name` varchar(64),
  `url` varchar(128),
  `level` enum('STANDARD', 'PREMIUM') NOT NULL,
  `visible` tinyint unsigned NOT NULL default '1',
  `cdate` int unsigned,
  `mdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY `gaaas_id_index` (`gaaas_id`) REFERENCES `google_analytics_api_account_summaries` (`id`),
  UNIQUE KEY `gid_index` (`gid`),
  KEY `name_index` (`name`)
) ENGINE = InnoDB;

CREATE TABLE `google_analytics_api_profile_summaries` (
  `id` mediumint unsigned AUTO_INCREMENT PRIMARY KEY,
  `gid` varchar(16) NOT NULL,
  `gaawps_id` mediumint unsigned NOT NULL,
  `name` varchar(64),
  `type` enum('WEB', 'APP') NOT NULL,
  `visible` tinyint unsigned NOT NULL default '1',
  `cdate` int unsigned,
  `mdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY `gaawps_id_index` (`gaawps_id`) REFERENCES `google_analytics_api_web_property_summaries` (`id`),
  UNIQUE KEY `gid_index` (`gid`),
  KEY `name_index` (`name`)
) ENGINE = InnoDB;