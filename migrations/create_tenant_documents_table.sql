-- Create tenant_documents table for storing uploaded documents and extracted data
CREATE TABLE IF NOT EXISTS tenant_documents (
    document_id INT PRIMARY KEY AUTO_INCREMENT,
    tenantID VARCHAR(100) NOT NULL,
    registration_type VARCHAR(50),
    document_type VARCHAR(50) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_extension VARCHAR(10),
    mime_type VARCHAR(100),
    file_size INT,
    extracted_data LONGTEXT COMMENT 'JSON formatted extracted OCR data',
    verification_status ENUM('Pending', 'Verified', 'Rejected') DEFAULT 'Pending',
    verification_notes TEXT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verified_at TIMESTAMP NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant_document_type (tenantID, document_type),
    INDEX idx_verification_status (verification_status)
) CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores tenant uploaded documents with OCR extracted data';

-- Alter table to add extracted_data column if table already exists
ALTER TABLE tenant_documents 
ADD COLUMN IF NOT EXISTS extracted_data LONGTEXT COMMENT 'JSON formatted extracted OCR data' 
AFTER file_size;
