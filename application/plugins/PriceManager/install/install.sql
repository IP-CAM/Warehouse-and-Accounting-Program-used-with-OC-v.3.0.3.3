CREATE TABLE `plugin_price_manager_categories` (
  `category_config_id` INT NOT NULL,
  `category_id` INT NULL,
  `category_profitability_ratio` FLOAT NULL DEFAULT 1,
  `category_bonus_ratio` FLOAT NULL DEFAULT 1,
  PRIMARY KEY (`category_config_id`));
