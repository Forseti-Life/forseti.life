-- St. Louis Integration Database Setup
-- This script creates the database and user for local development

-- Create database
CREATE DATABASE IF NOT EXISTS stlouisintegration_dev 
  CHARACTER SET utf8mb4 
  COLLATE utf8mb4_unicode_ci;

-- Create user (adjust password as needed)
CREATE USER IF NOT EXISTS 'drupal_user'@'localhost' 
  IDENTIFIED BY 'drupal_secure_password';

-- Grant privileges
GRANT ALL PRIVILEGES ON stlouisintegration_dev.* 
  TO 'drupal_user'@'localhost';

-- Apply changes
FLUSH PRIVILEGES;

-- Verify database creation
SHOW DATABASES LIKE 'stlouisintegration_dev';

-- Verify user creation
SELECT User, Host FROM mysql.user WHERE User = 'drupal_user';

-- Show granted privileges
SHOW GRANTS FOR 'drupal_user'@'localhost';