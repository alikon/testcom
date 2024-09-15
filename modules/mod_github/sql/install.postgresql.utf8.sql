/**
 * @package   mod_github
 * @copyright Copyright (c) 2024 Alikon
 * @license   GNU General Public License version 3, or later
 */
CREATE TABLE IF NOT EXISTS "#__github_issues" (
  "id" serial NOT NULL,
  "execution" timestamp without time zone NOT NULL,
  "openi" int NOT NULL DEFAULT 0,
  "closedi" int NOT NULL DEFAULT 0,
  "openp" int NOT NULL DEFAULT 0,
  "closedp" int NOT NULL DEFAULT 0,
  PRIMARY KEY ("id")
);