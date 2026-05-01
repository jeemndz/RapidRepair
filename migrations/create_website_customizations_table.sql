-- Create website_customizations table
CREATE TABLE IF NOT EXISTS website_customizations (
    customizationID INT AUTO_INCREMENT PRIMARY KEY,
    tenantID INT NOT NULL,
    shopName VARCHAR(255),
    primaryColor VARCHAR(7),
    shopLogo VARCHAR(500),
    heroHeading TEXT,
    heroSubtext TEXT,
    heroBackground VARCHAR(500),
    servicesData JSON,
    ctaButtonText VARCHAR(255),
    createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_tenant (tenantID),
    FOREIGN KEY (tenantID) REFERENCES owners(tenantID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
