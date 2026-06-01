-- Create document_extractions table for storing OCR extracted data per document
CREATE TABLE IF NOT EXISTS document_extractions (
    extraction_id INT AUTO_INCREMENT PRIMARY KEY,
    tenantID VARCHAR(20) NOT NULL,
    document_type ENUM(
        'DTI Registration',
        'SEC Registration',
        'Barangay Clearance',
        'Business Permit',
        'BIR 2303',
        'Government ID'
    ),
    business_name VARCHAR(255),
    owner_name VARCHAR(255),
    permit_number VARCHAR(100),
    issue_date DATE,
    expiry_date DATE,
    address TEXT,
    raw_ocr_text LONGTEXT,
    confidence_score DECIMAL(5,2) DEFAULT 0,
    verified_by_user TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant_doc (tenantID, document_type),
    INDEX idx_expiry_date (expiry_date),
    FOREIGN KEY (tenantID) REFERENCES owners(tenantID)
) CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores OCR extracted data per document';

-- Alter table to add columns if table already exists
ALTER TABLE document_extractions 
ADD COLUMN IF NOT EXISTS raw_ocr_text LONGTEXT AFTER address,
ADD COLUMN IF NOT EXISTS confidence_score DECIMAL(5,2) DEFAULT 0 AFTER raw_ocr_text,
ADD COLUMN IF NOT EXISTS verified_by_user TINYINT(1) DEFAULT 0 AFTER confidence_score;
