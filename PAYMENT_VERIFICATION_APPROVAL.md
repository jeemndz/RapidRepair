# Payment Verification for Pending Applications

## Overview
The superadmin pending applications table has been enhanced to display and verify payment status before approving tenant applications. Only applicants with confirmed payments can be approved.

## Features Added

### 1. Payment Status Column
The pending applications table now includes a **Payment** column that displays:
- **✓ Paid** (Green Badge) - Payment verified with amount and method
- **⏱ Pending Payment** (Amber Badge) - Payment not yet completed
- **✗ Failed** (Red Badge) - Payment processing failed
- **No Payment** (Gray Badge) - No payment record found

### 2. Payment Verification Display
When payment is verified, the table shows:
- Badge: "Paid"
- Amount: PHP [amount]
- Method: Payment method used (Card, GCash, Bank Transfer, Cash)

### 3. Approval Modal Enhancement
The approval modal now includes:

#### Payment Status Alert (if NOT paid):
```
⚠️ Payment Required
This applicant has not completed payment yet. 
Please verify payment before approval.
```
- **Approve button:** DISABLED (grayed out)

#### Payment Verification Alert (if PAID):
```
✓ Payment Verified
Payment confirmed. You may proceed with approval.
```
- **Approve button:** ENABLED (active)

### 4. Automatic Approval Button Control
- **Disabled** when payment status is unpaid/unknown
- **Enabled** when payment status is confirmed as paid
- Prevents accidental approval of unpaid applications

## Database Query Logic

The system checks the `subscription_payments` table for each pending applicant:

```sql
SELECT 
  payment_status,
  paid_at,
  amount,
  payment_method 
FROM subscription_payments 
WHERE tenantID = [tenant_id] 
ORDER BY created_at DESC 
LIMIT 1
```

## Modified Files

### superaddtenants.php
Added helper functions:
- `getTenantPaymentStatus($conn, $tenantID)` - Retrieves latest payment info
- `getPaymentStatusBadge($paymentStatus)` - Returns HTML badge and styling

Updated sections:
- Pending Applications table header (now 6 columns)
- Pending applications loop (added payment status checking)
- Approval modal (added payment verification alerts)
- JavaScript `approveTenant()` function (added payment parameter)

## Usage Workflow

### For Superadmin:

1. **View Pending Applications**
   - Open Superadmin → Tenants → Pending Applications table
   - Review applicant information including new Payment column
   - Green badges indicate ready-to-approve applicants
   - Gray/Amber/Red badges indicate payment issues

2. **Check Payment Status**
   - If badge shows "Paid" → applicant can be approved
   - If badge shows "No Payment" or "Pending" → ask applicant to pay first
   - Contact info is in the Applicant column (email)

3. **Approve Paid Applications**
   - Click "Accept" button next to applicant
   - Approval modal opens only for paid applicants
   - Select subscription plan and billing cycle
   - Click "Approve & Activate"
   - Email sent to applicant with activation details

4. **Reject Unpaid Applications (Optional)**
   - Click "Reject" button even if unpaid
   - Status changes to "Inactive"
   - Applicant can reapply or complete payment

## Payment Status States

| Status | Meaning | Action |
|--------|---------|--------|
| **Paid** | Payment confirmed, verified in system | Approve immediately |
| **Pending** | Payment initiated but not confirmed | Wait/contact applicant |
| **Failed** | Payment processing failed | Ask applicant to retry |
| **Unpaid** | No payment record found | Request payment first |

## Contact Flow

When applicant payment is missing:

1. View applicant email in the Applicant column
2. Contact applicant to complete payment
3. Once paid, refresh the page
4. Payment status badge updates automatically
5. Approve when ready

## Error Handling

If `subscription_payments` table doesn't exist:
- System returns "unknown" status
- Shows "No Payment" badge
- Approval button remains disabled until table is created

## Security Features

✓ SQL injection prevention in payment queries  
✓ Only displays payment info for pending applicants  
✓ Email addresses masked in payment details  
✓ Transaction data read-only in admin panel  
✓ User must have superadmin access to view payments  

## Database Requirements

The feature requires:
- `subscription_payments` table with proper schema
- `payment_status` enum field with values: 'Paid', 'Pending', 'Failed', 'Refunded'
- Foreign key linking to `owners` table via `tenantID`

Run the migration if not done:
```bash
mysql -u root -p rapidrepair < migrations/create_subscription_payments_table.sql
```

## Customization

### Change Payment Status Colors
Edit the `getPaymentStatusBadge()` function to modify:
- Badge colors (currently: green, amber, red, gray)
- Badge text (currently: Paid, Pending Payment, Failed, No Payment)
- Icon symbols (currently: check_circle, schedule, error, warning)

### Modify Payment Alert Text
Edit in the approval modal HTML (lines ~1945):
- "Payment Required" alert message
- "Payment Verified" alert message
- Button disabled/enabled behavior

### Change Required Payment Status
Currently: Requires payment_status = 'Paid'  
To change: Edit `$isPaid = $paymentInfo['status'] === 'paid';` logic

## Testing Checklist

- [ ] Payment status displays correctly for paid applicants
- [ ] Payment status displays correctly for unpaid applicants
- [ ] Approval button disabled when unpaid
- [ ] Approval button enabled when paid
- [ ] Alerts display in approval modal correctly
- [ ] Payment amount and method shown when applicable
- [ ] Search/filter still works with new column
- [ ] Pagination still works correctly
- [ ] Payments with different methods display correctly
- [ ] Payment status updates when manually checking payment table

## Troubleshooting

### Issue: All applicants show "No Payment" badge
**Solution:** Check if `subscription_payments` table exists and has payment records

### Issue: Approval button always disabled
**Solution:** Verify payment has status='Paid' in database, check date format

### Issue: Payment amount not showing
**Solution:** Check if amount field is populated in subscription_payments table

### Issue: Badge colors not displaying
**Solution:** Verify Tailwind CSS classes are properly configured in page

## Next Steps

1. Test with real payment submissions
2. Configure payment gateway integration
3. Add payment retry mechanism
4. Create payment dashboard for admin
5. Add automated payment reminders for unpaid applications
6. Implement refund processing
7. Add payment dispute handling

## Support

For issues or questions about payment verification:
- Check database schema in migration file
- Verify payment records exist in subscription_payments table
- Review browser console for JavaScript errors
- Check server logs for database query issues
