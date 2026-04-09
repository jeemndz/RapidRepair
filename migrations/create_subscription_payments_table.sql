-- Create subscription_payments table for tracking payment transactions
CREATE TABLE IF NOT EXISTS subscription_payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    tenantID INT NOT NULL,
    subscription_id INT DEFAULT 0,
    plan_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('Cash','GCash','Card','Bank Transfer') NOT NULL DEFAULT 'Card',
    payment_status ENUM('Pending','Paid','Failed','Refunded') NOT NULL DEFAULT 'Pending',
    transaction_reference VARCHAR(100),
    gcash_reference VARCHAR(100),
    billing_period_start DATE NOT NULL,
    billing_period_end DATE NOT NULL,
    paid_at DATETIME,
    next_billing_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (tenantID) REFERENCES owners(tenantID) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES subscription_plans(plan_id),
    
    INDEX idx_tenantID (tenantID),
    INDEX idx_payment_status (payment_status),
    INDEX idx_created_at (created_at),
    INDEX idx_paid_at (paid_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add billing cycle and subscription related columns to owners table if they don't exist
ALTER TABLE owners ADD COLUMN IF NOT EXISTS subscription_plan VARCHAR(50);
ALTER TABLE owners ADD COLUMN IF NOT EXISTS billing_cycle ENUM('monthly', 'quarterly', 'yearly') DEFAULT 'monthly';
ALTER TABLE owners ADD COLUMN IF NOT EXISTS subscription_status ENUM('Active', 'Pending', 'Inactive', 'Cancelled') DEFAULT 'Pending';
