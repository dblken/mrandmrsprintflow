# Payment Rejection Chat Message Fix - CRITICAL BUG RESOLVED

## Problem Identified
Customer received notification about payment rejection but NO message appeared in the chat showing the rejection reason.

## Root Cause Analysis

### Issue 1: Missing sender_id Field
The `printflow_send_order_update()` function was inserting messages into `order_messages` table WITHOUT the required `sender_id` field, causing the INSERT to fail silently.

**Before:**
```sql
INSERT INTO order_messages (order_id, sender, message, message_type, thumbnail, action_type, action_url, meta_json, is_seen, created_at)
```

**After:**
```sql
INSERT INTO order_messages (order_id, sender, sender_id, message, message_type, thumbnail, action_type, action_url, meta_json, read_receipt, created_at)
```

### Issue 2: Wrong Column Name
Used `is_seen` instead of `read_receipt` (the actual column name in the table).

### Issue 3: Missing Product Name
Rejection messages didn't include product_name in metadata, making the chat card display generic text.

## Solution Implemented

### 1. Fixed order_chat_system.php
- Added `sender_id = 0` for System messages
- Changed `is_seen` to `read_receipt`
- Updated SQL parameter types from `isssssss` to `isiisssss`

### 2. Enhanced api_verify_payment.php
- Added product_name retrieval from order_items
- Included product_name in metadata for both approval and rejection
- Ensures chat messages display proper service/product names

### 3. Files Modified
1. `includes/order_chat_system.php` - Fixed message insertion
2. `staff/api_verify_payment.php` - Added product_name to metadata

## How It Works Now

### Payment Rejection Flow:
1. Staff clicks "Reject" and selects reason (e.g., "Blurry image")
2. System updates order status to "To Pay"
3. System retrieves product name from order_items
4. **Chat message is inserted successfully** with:
   - sender: 'System'
   - sender_id: 0
   - message: "Your payment proof was rejected. Reason: Blurry image. Please resubmit your payment proof."
   - message_type: 'order_update'
   - action_type: 'retry_payment'
   - meta_json: Contains order_id, product_name, rejection_reason, etc.
5. Customer sees rejection card in chat with:
   - Product name (e.g., "Glass/Wall")
   - Rejection reason
   - Clear instructions to resubmit

### Payment Approval Flow:
1. Staff clicks "Approve"
2. System updates order status
3. System retrieves product name
4. **Chat message is inserted successfully** with approval message
5. Customer sees approval card in chat

## Testing Verification

### Test Rejection:
1. Submit payment proof as customer
2. Reject as staff with reason "Blurry image"
3. ✅ Customer receives notification
4. ✅ Customer sees message in chat with rejection reason
5. ✅ Message shows product name
6. ✅ Customer can retry payment

### Test Approval:
1. Submit payment proof as customer
2. Approve as staff
3. ✅ Customer receives notification
4. ✅ Customer sees approval message in chat
5. ✅ Message shows product name

## Database Impact

The fix ensures messages are properly inserted into `order_messages` table:

```
message_id | order_id | sender | sender_id | message | message_type | action_type | meta_json | read_receipt
-----------|----------|--------|-----------|---------|--------------|-------------|-----------|-------------
123        | 2612     | System | 0         | Your... | order_update | retry_pay   | {...}     | 0
```

## Expected Results

✅ **Chat Message Appears**: Rejection messages now appear in customer chat
✅ **Reason Displayed**: Specific rejection reason is shown
✅ **Product Name**: Shows which product/service was rejected
✅ **No Silent Failures**: All database insertions succeed
✅ **Proper Notifications**: Both notification AND chat message work

## Critical Fix Summary

The bug was caused by missing `sender_id` field in the SQL INSERT statement. Without this required field, the database rejected the INSERT operation silently, causing messages to never appear in chat even though notifications were sent successfully.

This fix ensures payment rejection messages are properly stored in the database and displayed in the customer chat interface with full context including the rejection reason.