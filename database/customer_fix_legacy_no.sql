SET NAMES utf8mb4;

-- Copy original_customer_no to legacy_customer_no
UPDATE customers SET legacy_customer_no = original_customer_no WHERE original_customer_no IS NOT NULL AND original_customer_no != "";
