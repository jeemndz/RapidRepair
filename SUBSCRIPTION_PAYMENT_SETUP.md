# RapidRepairCo. Subscription Payment Implementation Guide

## Overview
This guide explains the complete integration of the subscription payment system for the RapidRepairCo. client application. When users register from `clientlanding.php`, they are automatically directed to `clientpayment.php` to complete their payment setup.

## Database Schema

### subscription_payments Table
The main table for storing payment transactions with the following structure:

```sql
CREATE TABLE subscription_payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    tenantID INT NOT NULL,                          -- Reference to the owner/tenant
    subscription_id INT DEFAULT 0,                  -- Future use for subscription management
    plan_id INT NOT NULL,                           -- Reference to subscription plan
    amount DECIMAL(10,2) NOT NULL,                  -- Payment amount
    payment_method ENUM('Cash','GCash','Card','Bank Transfer'),
    payment_status ENUM('Pending','Paid','Failed','Refunded'),
    transaction_reference VARCHAR(100),             -- Unique transaction reference
    gcash_reference VARCHAR(100),                   -- GCash-specific reference
    billing_period_start DATE NOT NULL,             -- Current billing period start
    billing_period_end DATE NOT NULL,               -- Current billing period end
    paid_at DATETIME,                               -- When payment was completed
    next_billing_date DATE,                         -- Next billing date
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (tenantID) REFERENCES owners(tenantID),
    FOREIGN KEY (plan_id) REFERENCES subscription_plans(plan_id),
    INDEX idx_tenantID (tenantID),
    INDEX idx_payment_status (payment_status),
    INDEX idx_created_at (created_at)
)
```

## Installation Steps

### 1. Create Database Table
Run the migration SQL script:
```bash
# Via command line
mysql -u root -p rapidrepair < migrations/create_subscription_payments_table.sql

# Or copy/paste into your database client
```

### 2. Update Database Connection
Ensure `db.php` is properly configured with database credentials:
```php
include __DIR__ . "/db.php";
```

### 3. Include Helper Functions
Both `clientlanding.php` and `clientpayment.php` use the shared helper file:
```php
include __DIR__ . "/../payment_helper.php";
```

## File Structure

```
RapidRepair/
├── db.php                                    # Database connection
├── payment_helper.php                        # Shared payment functions
├── clientapplication/
│   ├── clientlanding.php                    # Registration form → redirects to payment
│   └── clientpayment.php                    # Payment processing
├── migrations/
│   └── create_subscription_payments_table.sql
└── tenant/
    └── (tenant dashboard for payment management)
```

## Application Flow

### Step 1: User Registration (clientlanding.php)
```
User fills registration form with:
- Shop Name
- Shop Address
- Owner Name
- Country (with phone code)
- Phone Number
- Email
- Subscription Plan (starter/professional/enterprise)
- Billing Cycle (monthly/quarterly/yearly)
         ↓
Form validation
         ↓
Creates owner record in `owners` table
         ↓
Redirects to: clientpayment.php?tenantID=XXX&plan=YYY&billingCycle=ZZZ
```

### Step 2: Payment Processing (clientpayment.php)
```
User sees payment form with:
- Selected plan details and price
- Payment method options:
  * Credit/Debit Card
  * GCash
  * Bank Transfer
  * Cash
         ↓
User fills payment details based on method selected
         ↓
Form validation and sanitization
         ↓
Creates payment record in subscription_payments table
         ↓
Updates owner status to "Active"
         ↓
Display success message and next steps
```

## Payment Methods

### 1. Card Payment
- Validates: Card number (Luhn algorithm), Expiry date (MM/YY), CVV
- Transaction Reference Format: `CARD-INI-TIMESTAMP-RANDOM`

### 2. GCash
- Requires: GCash reference number
- Transaction Reference Format: `GCASH-REFERENCE-RANDOM`

### 3. Bank Transfer
- Requires: Bank transfer reference number
- Transaction Reference Format: `BANK-REFERENCE-RANDOM`

### 4. Cash
- No additional validation
- Transaction Reference Format: `CASH-TIMESTAMP-RANDOM`

## Key Features

### 1. Automatic Tenant Creation
When registration is submitted successfully, the system:
- Generates unique tenantID (3-digit padded)
- Creates login slug for URL-friendly access
- Generates temporary password
- Sets initial status to "Active" after payment

### 2. Plan Management
- Load active plans from `subscription_plans` table
- Display in clientlanding.php for selection
- Pass selected plan to clientpayment.php
- Store plan details with payment record

### 3. Billing Cycle Support
- Monthly (1 month billing period)
- Quarterly (3 months billing period)
- Yearly (12 months billing period)
- Automatically calculated next billing date

### 4. Transaction Reference
- Unique reference generated per payment
- Format varies by payment method
- Stored in subscription_payments table for reconciliation
- Includes timestamp and random component for uniqueness

## Helper Functions (payment_helper.php)

### loadSubscriptionPlans($conn)
Retrieves all active plans from the database.

### getPlanByCode($conn, $planCode)
Gets plan details by plan code (starter/professional/enterprise).

### getTenantDetails($conn, $tenantID)
Retrieves tenant information from owners table.

### createPaymentRecord($conn, $tenantID, $planID, $amount, $paymentMethod, $transactionRef, $gcashRef, $bankRef)
Creates payment record and updates owner status.

### generateTransactionReference($paymentMethod, $cardholderName, $gcashRef, $bankRef)
Generates unique transaction reference based on payment method.

### getPaymentHistory($conn, $tenantID, $limit)
Retrieves payment history for a tenant.

### validateCardNumber($cardNumber)
Validates card number using Luhn algorithm.

### validateExpiryDate($expiryDate)
Validates expiry date format and currency.

### validateCVV($cvv)
Validates CVV is 3-4 digits.

## Configuration Options

### Default Plans (if table not populated)
```php
[
    'plan_id' => 1,
    'plan_code' => 'starter',
    'plan_name' => 'Starter',
    'monthly_price' => 149,
]

[
    'plan_id' => 2,
    'plan_code' => 'professional',
    'plan_name' => 'Professional',
    'monthly_price' => 399,
]

[
    'plan_id' => 3,
    'plan_code' => 'enterprise',
    'plan_name' => 'Enterprise',
    'monthly_price' => 0,
]
```

## Error Handling

### registration Errors (clientlanding.php)
- Missing required fields
- Invalid email format
- Invalid phone number format
- Email already registered
- Invalid country code
- Invalid subscription plan
- Invalid billing cycle

### Payment Errors (clientpayment.php)
- Missing cardholder name
- Invalid card number
- Invalid expiry date format
- Invalid CVV
- Invalid GCash reference
- Invalid bank reference
- Missing payment method fields
- Payment processing failure

## Session Management

### Tenant Identification
- Passed via GET parameters: `tenantID` and `plan`
- Validated on payment page load
- Error displayed if invalid

### Payment Success
- Session variables set:
  - `$_SESSION['payment_success'] = true`
  - `$_SESSION['tenant_id'] = $tenantID`
- Can be used for post-payment redirect or confirmation

## Security Considerations

### Data Validation
- All user inputs are trimmed and validated
- SQL injection prevention using `mysqli_real_escape_string()`
- Email validated using `filter_var()`
- Phone number validated with regex
- Card data validated with Luhn algorithm

### Payment Information
- CVV is stored (consider using third-party processor)
- Card numbers should be tokenized in production
- Implement SSL/TLS encryption
- PCI DSS compliance recommended

### Future Enhancements
- Integrate with payment gateway (Stripe, PayPal)
- Webhook integration for payment confirmation
- Email notifications for payment success/failure
- Invoice generation
- Automated billing cycle management
- Payment retry mechanism
- Refund processing

## Testing Checklist

- [ ] Database table created successfully
- [ ] Registration form submits correctly
- [ ] Tenant record created in owners table
- [ ] Redirects to clientpayment.php with correct parameters
- [ ] Payment page displays correct plan details
- [ ] All payment methods work (Card, GCash, Bank, Cash)
- [ ] Payment record created in subscription_payments table
- [ ] Owner status updated to "Active"
- [ ] Error messages display for invalid inputs
- [ ] Card validation works (Luhn algorithm)
- [ ] Expiry date validation works
- [ ] CVV validation works
- [ ] Transaction references are generated correctly
- [ ] Session variables set on success

## Troubleshooting

### Issue: "Invalid tenant information" error
**Solution:** Ensure tenantID parameter is passed from clientlanding.php

### Issue: Payment record not created
**Solution:** Check `subscription_payments` table exists and foreign keys are valid

### Issue: Owner status not updating
**Solution:** Verify `owners` table has `status` and `subscription_status` columns

### Issue: Plans not loading
**Solution:** Check `subscription_plans` table exists or use default plans

### Issue: Redirect not working
**Solution:** Ensure no output before `header()` call, check `session_start()` at top

## Next Steps

1. **Payment Gateway Integration**
   - Integrate Stripe, PayPal, or similar service
   - Store tokenized card information instead of full card number

2. **Dashboard Implementation**
   - Create tenant dashboard to view payment history
   - Allow plan upgrades/downgrades
   - Manage billing information

3. **Admin Panel**
   - View all payments and subscription status
   - Manual payment processing
   - Refund management
   - Revenue reports

4. **Automated Billing**
   - Implement scheduled billing for recurring charges
   - Automatic invoice generation
   - Email notifications
   - Late payment handling

## Support

For issues or questions, contact the development team or refer to the codebase comments.
