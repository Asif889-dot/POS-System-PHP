CREATE TABLE IF NOT EXISTS `purchases` (
  `purchase_id`    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `supplier_name`  VARCHAR(150) NOT NULL,
  `invoice_no`     VARCHAR(50) DEFAULT NULL,
  `total_amount`   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `notes`          TEXT DEFAULT NULL,
  `purchased_at`   DATE NOT NULL,
  `created_by`     INT DEFAULT NULL,
  `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- And the table for the items inside each purchase
CREATE TABLE IF NOT EXISTS `purchase_items` (
  `item_id`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `purchase_id`    INT UNSIGNED NOT NULL,
  `product_id`     INT UNSIGNED NOT NULL,
  `quantity`       INT NOT NULL,
  `cost_price`     DECIMAL(10,2) NOT NULL,
  `subtotal`       DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (`purchase_id`) REFERENCES `purchases`(`purchase_id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `sale_items` ADD COLUMN `cost_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `unit_price`;

CREATE TABLE IF NOT EXISTS `expenses` (
  `expense_id`    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `category`      VARCHAR(100) NOT NULL,
  `amount`        DECIMAL(10,2) NOT NULL,
  `expense_date`  DATE NOT NULL,
  `notes`         TEXT DEFAULT NULL,
  `created_by`    INT DEFAULT NULL,
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;