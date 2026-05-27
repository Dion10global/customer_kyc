-- ----------------------------------------------------------------------------
-- init.sql
-- Author: Benjamin Kudzai Nyaruviro (BenNyaruz)
-- Cloud Application Development - Practical Test 2
-- Repository: https://github.com/BenNyaruz/customers-app
--
-- Creates the customers table referenced by customers.php and seeds it with
-- a handful of rows so the table renders something meaningful in the browser.
--
-- This script is database-agnostic: it operates on whichever database the
-- connection selects, so the same file seeds the CI test database
-- (companydb_test), a local companydb, or a managed instance (Aiven defaultdb).
-- Select the target database when invoking it, e.g.:
--   mysql -u root -p companydb_test < init.sql
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS customers (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120) NOT NULL,
  email       VARCHAR(190) NOT NULL UNIQUE,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO customers (name, email, created_at) VALUES
  ('Thandi Mokoena',  'thandi.mokoena@example.co.za', '2026-01-12 09:14:00'),
  ('Sipho Dlamini',   'sipho.dlamini@example.co.za',  '2026-02-03 11:42:00'),
  ('Lerato Khumalo',  'lerato.k@example.co.za',       '2026-02-21 16:05:00'),
  ('Johan van Wyk',   'johan.vw@example.co.za',       '2026-03-08 08:30:00'),
  ('Aisha Patel',     'aisha.patel@example.co.za',    '2026-04-15 14:00:00')
ON DUPLICATE KEY UPDATE name = VALUES(name);
