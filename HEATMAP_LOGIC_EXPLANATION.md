# Seasonal Demand Heatmap - Logic Explanation

## 🐛 **CRITICAL BUG FIX: Color Intensity Calculation**

### Problem
The heatmap was showing **all cells as dark blue (High)** because the JavaScript used **hardcoded thresholds** instead of calculating based on the maximum value in the dataset.

**Before (BROKEN)**:
```javascript
function pfHmValueTier(v) {
    v = Number(v) || 0;
    if (v <= 5) return 'low';    // Hardcoded!
    if (v <= 15) return 'med';   // Hardcoded!
    return 'high';
}
```

**After (FIXED)**:
```javascript
function pfHmValueTier(v, maxV) {
    v = Number(v) || 0;
    maxV = Number(maxV) || 0;
    if (v <= 0 || maxV <= 0) return 'low';
    var pct = (v / maxV) * 100;  // Calculate percentage!
    if (pct <= 25) return 'low';
    if (pct <= 65) return 'med';
    return 'high';
}
```

### Root Cause
The JavaScript function didn't match the PHP logic in `pf_reports_heatmap_value_tier()`. The PHP version correctly calculates percentages based on the maximum value across all cells, but the JavaScript version used fixed thresholds (5, 15).

### Solution
1. **Updated `pfHmValueTier()`** to accept `maxValue` parameter
2. **Calculate max value** in `pfReportsMountHeatmapFromApi()` before rendering cells
3. **Pass max value** to `pfHmValueTier()` for each cell

### Result
✅ Colors now correctly reflect **relative intensity** within the dataset
✅ Low values (0-25% of max) = Light blue
✅ Medium values (26-65% of max) = Cyan
✅ High values (66-100% of max) = Dark blue

---

## Overview
The heatmap displays monthly transaction volumes for the top 8 services/products across a selected year, with color-coded intensity levels.

## Data Sources

### 1. Product Orders (Store Orders)
**Query:** `pf_reports_heatmap_sums_for_year()` in `reports_dashboard_queries.php`

```sql
SELECT p.name AS product, 
       MONTH(o.order_date) AS mo, 
       SUM(oi.quantity) AS qty
FROM order_items oi
JOIN products p ON oi.product_id = p.product_id
JOIN orders o ON oi.order_id = o.order_id
WHERE YEAR(o.order_date) = ?
  AND o.payment_status = 'Paid'
  AND (
    YEAR(o.order_date) < YEAR(CURDATE())
    OR MONTH(o.order_date) <= MONTH(CURDATE())
  )
GROUP BY p.product_id, p.name, MONTH(o.order_date)
```

**Key Points:**
- ✅ Only counts **PAID orders** (`payment_status = 'Paid'`)
- ✅ Excludes **future months** in the current year
- ✅ Groups by product and month
- ✅ Sums the quantity from `order_items`

### 2. Customization Jobs
**Query:** Same function, second part

```sql
SELECT COALESCE(NULLIF(TRIM(jo.service_type), ''), 'Customization') AS product,
       MONTH(jo.created_at) AS mo,
       SUM(COALESCE(jo.quantity, 1)) AS qty
FROM job_orders jo
WHERE YEAR(jo.created_at) = ?
  AND (
    YEAR(jo.created_at) < YEAR(CURDATE())
    OR MONTH(jo.created_at) <= MONTH(CURDATE())
  )
GROUP BY COALESCE(NULLIF(TRIM(jo.service_type), ''), 'Customization'), MONTH(jo.created_at)
```

**Key Points:**
- ✅ Counts **ALL job orders** (no payment filter - jobs have different workflow)
- ✅ Excludes **future months** in the current year
- ✅ Groups by service type (or "Customization" if blank)
- ✅ Uses `created_at` date

## Cell Classification Logic

### Cell Types (Kind)
Each cell is classified into one of three types:

1. **`future`** - Month hasn't occurred yet
   - Condition: `year === currentYear && month > currentMonth`
   - Display: Diagonal stripes pattern
   - Label: "Not yet"

2. **`empty`** - Month has passed but no transactions
   - Condition: Month has occurred but `qty = 0`
   - Display: Light gray with dashed border
   - Label: "No transactions"

3. **`value`** - Month has transactions
   - Condition: Month has occurred and `qty > 0`
   - Display: Color-coded by intensity (Low/Medium/High)
   - Shows actual quantity number

### Intensity Levels (for `value` cells)
Based on percentage of maximum value across all cells:

- **Low** (Light blue `#a5e3f2`): 0-25% of max
- **Medium** (Cyan `#53C5E0`): 26-65% of max
- **High** (Dark blue `#00232b`): 66-100% of max

**Function:** `pf_reports_heatmap_value_tier()` in `reports_dashboard_queries.php`

```php
function pf_reports_heatmap_value_tier(int $v, int $max_v): string {
    if ($v <= 0 || $max_v <= 0) return 'low';
    $pct = ($v / $max_v) * 100;
    if ($pct <= 25) return 'low';
    if ($pct <= 65) return 'med';
    return 'high';
}
```

## Top 8 Services Selection

**Function:** `pf_reports_heatmap_matrix()` in `reports_dashboard_queries.php`

1. Calculates total quantity for each service across all 12 months
2. Sorts services by total quantity (descending)
3. Takes top 8 services only
4. Returns matrix with cell classification

## Year Change Behavior

### Available Years
**Function:** `pf_reports_heatmap_available_years()` in `reports_dashboard_queries.php`

- Returns years that have at least one paid order OR one job order
- Only includes years ≤ current year (no future years)
- Sorted newest first

### AJAX Update
When year dropdown changes:
1. Sends request to `api_reports_heatmap.php?year=YYYY&branch_id=X`
2. API validates year is in available years list
3. Fetches data using same logic as initial page load
4. Renders new heatmap HTML without page reload

## Legend Toggle Feature

### Interactive Legend
Each legend item is clickable:
- **Click** to hide/show all cells of that type
- **Visual feedback**: Strikethrough + 30% opacity when hidden
- **Keyboard support**: Enter or Space key

### Implementation
```javascript
// Toggle legend item
item.classList.toggle('pf-hm-hidden');

// Toggle all matching cells
var cells = document.querySelectorAll('.pf-hm-cell--' + kind);
cells.forEach(function(cell) {
    cell.classList.toggle('pf-hm-hidden');
});
```

**CSS:**
```css
.pf-hm-legend-item.pf-hm-hidden {
    opacity: 0.3;
    text-decoration: line-through;
}

.pf-hm-cell.pf-hm-hidden {
    opacity: 0.15 !important;
    pointer-events: none;
}
```

## Accuracy Verification

### Why the data is accurate:

1. **Paid Orders Only**: Only counts orders where money was received
2. **No Future Data**: Explicitly excludes months that haven't occurred
3. **Proper Date Comparison**: Uses database `CURDATE()` for server-side accuracy
4. **Merged Sources**: Combines both store orders and customization jobs
5. **Quantity-Based**: Uses actual `quantity` field, not just order count

### Common Scenarios:

**Q: Why do I see data for current month?**
A: The query includes `MONTH(o.order_date) <= MONTH(CURDATE())`, so current month is included.

**Q: Why don't I see next month?**
A: The query explicitly excludes future months with the condition above.

**Q: Why is a service missing?**
A: Only top 8 services by total yearly quantity are shown. Lower-volume services are excluded.

**Q: Why do customization jobs show even if unpaid?**
A: Job orders have a different workflow. They're counted from creation, not payment.

**Q: Why does changing year not update?**
A: Fixed! The AJAX request now properly includes branch context and handles errors.

## Files Modified

1. **reports.php** - Added legend toggle UI and CSS
2. **reports_analytics_scripts.php** - Added legend toggle JavaScript and improved year change handler
3. **api_reports_heatmap.php** - Ensured branch_id parameter is properly handled
4. **reports_dashboard_queries.php** - Core logic (no changes needed - already correct)

## Testing Checklist

- [x] Verify only paid orders appear in heatmap
- [x] Verify future months show as "Not yet"
- [x] Verify past months with no data show as "No transactions"
- [x] Verify intensity colors match quantity levels
- [x] Verify year dropdown updates heatmap
- [x] Verify legend items are clickable
- [x] Verify clicking legend hides/shows cells
- [x] Verify keyboard navigation works (Tab + Enter/Space)
- [x] Verify branch filter applies to heatmap
