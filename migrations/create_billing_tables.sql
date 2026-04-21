-- Create subscription plans table
CREATE TABLE IF NOT EXISTS subscription_plans (
    plan_id INT PRIMARY KEY AUTO_INCREMENT,
    plan_name VARCHAR(100) NOT NULL,
    monthly_price DECIMAL(10, 2) NOT NULL,
    yearly_price DECIMAL(10, 2),
    max_technicians INT,
    max_vehicles INT,
    max_services INT,
    features TEXT,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create shop subscriptions table
CREATE TABLE IF NOT EXISTS shop_subscriptions (
    subscription_id INT PRIMARY KEY AUTO_INCREMENT,
    tenantID INT NOT NULL,
    plan_id INT NOT NULL,
    billing_cycle ENUM('monthly', 'yearly') DEFAULT 'monthly',
    subscription_status ENUM('active', 'cancelled', 'suspended', 'expired') DEFAULT 'active',
    start_date DATE,
    renewal_date DATE,
    next_billing_date DATE,
    cancel_date DATE,
    payment_method_id INT,
    auto_renew BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenantID) REFERENCES owners(tenantID),
    FOREIGN KEY (plan_id) REFERENCES subscription_plans(plan_id)
);

-- Create payment methods table
CREATE TABLE IF NOT EXISTS payment_methods (
    payment_method_id INT PRIMARY KEY AUTO_INCREMENT,
    tenantID INT NOT NULL,
    method_type ENUM('card', 'wallet', 'bank_transfer') NOT NULL,
    
    -- Card specific fields
    card_last_four VARCHAR(4),
    card_brand VARCHAR(50),
    card_holder_name VARCHAR(100),
    card_expiry_month INT,
    card_expiry_year INT,
    
    -- Digital Wallet specific fields
    wallet_type VARCHAR(50),
    wallet_provider VARCHAR(50),
    wallet_identifier VARCHAR(255),
    
    -- Bank Transfer specific fields
    bank_account_number VARCHAR(100),
    bank_routing_number VARCHAR(100),
    bank_account_holder_name VARCHAR(100),
    bank_name VARCHAR(100),
    bank_country VARCHAR(50),
    
    -- Common fields
    is_primary BOOLEAN DEFAULT FALSE,
    is_verified BOOLEAN DEFAULT FALSE,
    last_used DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenantID) REFERENCES owners(tenantID)
);

-- Create invoices table
CREATE TABLE IF NOT EXISTS invoices (
    invoice_id INT PRIMARY KEY AUTO_INCREMENT,
    tenantID INT NOT NULL,
    subscription_id INT,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    status ENUM('draft', 'sent', 'paid', 'partially_paid', 'overdue', 'cancelled') DEFAULT 'draft',
    invoice_date DATE,
    due_date DATE,
    paid_date DATE,
    payment_method_id INT,
    description TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenantID) REFERENCES owners(tenantID),
    FOREIGN KEY (subscription_id) REFERENCES shop_subscriptions(subscription_id),
    FOREIGN KEY (payment_method_id) REFERENCES payment_methods(payment_method_id)
);

-- Insert default subscription plans
INSERT INTO subscription_plans (plan_name, monthly_price, yearly_price, max_technicians, max_vehicles, max_services, description) VALUES
('Starter', 49.00, 490.00, 3, 10, 20, 'Perfect for small shops'),
('Professional', 149.00, 1490.00, 10, 50, 100, 'For growing businesses'),
('Enterprise', 299.00, 2990.00, 50, 500, 500, 'For large operations');
