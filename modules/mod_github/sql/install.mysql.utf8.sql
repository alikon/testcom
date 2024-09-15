/**
 * @package   mod_github
 * @copyright Copyright (c) 2024 Alikon
 * @license   GNU General Public License version 3, or later
 */
CREATE TABLE IF NOT EXISTS `#__github_issues` (
  `id` int(10) UNSIGNED NOT NULL,
  `execution` datetime DEFAULT NULL COMMENT 'Timestamp of last run',
  `openi` smallint(6) NOT NULL DEFAULT '0',
  `closedi` smallint(6) NOT NULL DEFAULT '0',
  `openp` smallint(6) NOT NULL DEFAULT '0',
  `closedp` smallint(6) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;