-- Create payment_methods table for storing saved payment methods
CREATE TABLE IF NOT EXISTS payment_methods (
    payment_method_id INT AUTO_INCREMENT PRIMARY KEY,
    tenantID INT NOT NULL,
    method_type ENUM('card', 'wallet', 'bank_transfer') NOT NULL,
    
    -- Card fields
    card_brand VARCHAR(50) NULL,
    card_last_four CHAR(4) NULL,
    card_expiry_month INT NULL,
    card_expiry_year INT NULL,
    
    -- Digital Wallet fields
    wallet_provider VARCHAR(100) NULL,
    wallet_identifier VARCHAR(255) NULL,
    
    -- Bank Transfer fields
    bank_name VARCHAR(150) NULL,
    bank_account_number VARCHAR(50) NULL,
    bank_account_type VARCHAR(20) NULL,
    
    -- Common fields
    is_primary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (tenantID) REFERENCES owners(tenantID) ON DELETE CASCADE,
    INDEX idx_tenant_primary (tenantID, is_primary),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
