# Payment Rejection Flow Fix - Complete Solution

## Problem Identified
When staff rejects a payment proof, customers were NOT receiving chat notifications about the rejection and the reason.

## Solution Implemented

### 1. Fixed Payment Approval Message
Changed from: "Your payment has been verified. Your order is now in production!"
Changed to: "Your payment has been approved, and our team is currently working on it."

### 2. Fixed Payment Rejection Flow
Updated `staff/api_verify_payment.php` to:
- Properly call `printflow_send_order_update()` with correct parameters
- Send order update chat message with rejection reason
- Include metadata for proper rendering
- Set action_type to 'retry_payment' for rejection messages

### 3. Enhanced Order Card Data
Updated `customer/chat.php` to include:
- `rejectionReason`: Extracted from meta_json
- `actionType`: Determines card behavior (view_status, retry_payment, etc.)

### 4. Files Modified
1. `staff/api_verify_payment.php` - Fixed both approval and rejection message sending
2. `customer/chat.php` - Added rejection reason and action type to order card data
3. `includes/order_chat_system.php` - Already properly configured

## How It Works Now

### Payment Approval Flow:
1. Staff clicks "Approve" in verification modal
2. Order status updated to "Processing" or "Ready for Pickup"
3. Chat message sent: "Your payment has been approved, and our team is currently working on it."
4. Customer sees order update card in chat

### Payment Rejection Flow:
1. Staff clicks "Reject" and selects reason
2. Order status updated to "To Pay"
3. Payment proof cleared from database
4. Chat message sent: "Your payment proof was rejected. Reason: [reason]. Please resubmit your payment proof."
5. Customer sees rejection card with:
   - Clear rejection message
   - Specific reason for rejection
   - Action type set to 'retry_payment'
6. Customer can click to retry payment

## Message Format

### Approval Message:
```
Order Update Card:
- Badge: "Order update"
- Status: "Processing" or "Ready for Pickup"
- Message: "Your payment has been approved, and our team is currently working on it."
- Action: View order details
```

### Rejection Message:
```
Order Update Card:
- Badge: "Order update"  
- Status: "To Pay"
- Message: "Your payment proof was rejected. Reason: [specific reason]. Please resubmit your payment proof."
- Metadata includes: rejection_reason, step: 'payment_rejected'
- Action: Retry payment
```

## Testing Steps

1. **Test Approval:**
   - Submit payment proof as customer
   - Approve as staff
   - Verify customer receives approval message in chat
   - Verify message says "approved" not "verified"

2. **Test Rejection:**
   - Submit payment proof as customer
   - Reject as staff with reason
   - Verify customer receives rejection message in chat
   - Verify rejection reason is displayed
   - Verify customer can retry payment

## Expected Results

✅ **Payment Approval**: Customer receives clear approval message
✅ **Payment Rejection**: Customer receives rejection message with reason
✅ **Chat Integration**: All messages appear in order chat immediately
✅ **No Silent Failures**: Every action sends a notification
✅ **Proper Metadata**: Messages include all necessary data for rendering

## Technical Details

- Uses `printflow_send_order_update()` function from `order_chat_system.php`
- Messages stored in `order_messages` table with type 'order_update'
- Metadata stored as JSON in `meta_json` column
- Real-time notifications via `printflow_notify_chat_message()` if available

The payment rejection flow is now complete and customers will receive proper notifications when their payment is rejected.