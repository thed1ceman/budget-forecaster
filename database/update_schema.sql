-- Update user_settings table
ALTER TABLE user_settings
    ADD COLUMN IF NOT EXISTS currency ENUM('GBP', 'USD', 'EUR') DEFAULT 'GBP' AFTER user_id,
    MODIFY COLUMN current_balance DECIMAL(10,2) DEFAULT 0.00,
    MODIFY COLUMN payday INT DEFAULT 1;

-- Update payments table
ALTER TABLE payments
    DROP COLUMN IF EXISTS due_day,
    DROP COLUMN IF EXISTS is_active,
    ADD COLUMN IF NOT EXISTS due_date DATE NOT NULL AFTER amount,
    ADD COLUMN IF NOT EXISTS frequency ENUM('monthly', 'weekly', 'yearly') NOT NULL AFTER due_date;

-- Update audit_log table
ALTER TABLE audit_log
    MODIFY COLUMN action VARCHAR(50) NOT NULL,
    MODIFY COLUMN ip_address VARCHAR(45) NOT NULL;

-- Handle foreign key constraints separately
SET FOREIGN_KEY_CHECKS=0;

-- Drop existing foreign key if it exists
ALTER TABLE audit_log
    DROP FOREIGN KEY IF EXISTS audit_log_ibfk_1;

-- Add new foreign key
ALTER TABLE audit_log
    ADD CONSTRAINT audit_log_ibfk_1 
    FOREIGN KEY (user_id) REFERENCES users(id) 
    ON DELETE CASCADE;

SET FOREIGN_KEY_CHECKS=1; 