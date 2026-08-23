-- =====================================================================
-- BLUESKY AGENCY — DATABASE SCHEMA
-- Import this file in cPanel → phpMyAdmin, on the database you created.
-- =====================================================================

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  phone VARCHAR(20) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  invoice_no VARCHAR(30) DEFAULT NULL,
  plan VARCHAR(50) NOT NULL,                 -- 'Basic' or 'Pro'
  amount INT NOT NULL,                       -- in rupees
  bm_id VARCHAR(100) DEFAULT NULL,
  business_name VARCHAR(150) DEFAULT NULL,
  slot_id VARCHAR(20) DEFAULT NULL,          -- client-facing account number, e.g. A7
  ad_account_id VARCHAR(100) DEFAULT NULL,   -- backend Meta ad account ID (admin only sets this)
  status ENUM('pending_payment','paid_preparing','active','cancelled') NOT NULL DEFAULT 'pending_payment',
  razorpay_order_id VARCHAR(100) DEFAULT NULL,
  razorpay_payment_id VARCHAR(100) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default admin login: username "admin", password "bluesky@2026"
-- IMPORTANT: log in once and change this password immediately (see README).
INSERT INTO admins (username, password_hash) VALUES
('admin', '$2y$10$817EyyN/XVIILif5GvZUOu84pJZornL5knutZ7Xi7GoXpnrzPAQku')
ON DUPLICATE KEY UPDATE username=username;
