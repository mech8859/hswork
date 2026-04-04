-- Migration 042: Add ragic_id to bank_transactions for Ragic sync
ALTER TABLE bank_transactions ADD COLUMN ragic_id INT UNSIGNED DEFAULT NULL AFTER description;
ALTER TABLE bank_transactions ADD INDEX idx_ragic_id (ragic_id);
ALTER TABLE bank_transactions ADD INDEX idx_upload_number (upload_number);
