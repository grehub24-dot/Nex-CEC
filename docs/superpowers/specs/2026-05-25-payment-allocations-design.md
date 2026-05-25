# Payment Allocations: Full Accounting with Custom Fees

**Date:** 2026-05-25  
**Status:** Approved  
**Files Affected:** `api/admin_payments.php`, `api/admin_fees_debt.php`, `api/migrate_payment_allocations.sql` (new)

---

## 1. Problem

The Record Payment form in `admin_payments.php` requires selecting a single "Fee Type" (e.g., Tuition, PTA Levy) which auto-fills the amount. This has several limitations:

- Cannot pay multiple fee items in one transaction
- Cannot show the total billed amount for the student
- No support for partial payment selection (choose which items to pay)
- No tracking of which fee items a payment covers (needed for financial reporting)
- Custom fees (added via `admin_student_billing.php`) are not visible in the payment flow

## 2. Solution: `payment_allocations` Table

New table to track exactly which fee items each payment covers:

```sql
CREATE TABLE IF NOT EXISTS payment_allocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NOT NULL,
    fee_type VARCHAR(100) NOT NULL,
    fee_title VARCHAR(200) DEFAULT '',
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
);

CREATE INDEX idx_payment_allocations_payment ON payment_allocations(payment_id);
CREATE INDEX idx_payment_allocations_fee_type ON payment_allocations(fee_type);
```

**Key design decisions:**
- No foreign key to `fee_structures` — fee structures can change over time, so we store a snapshot of the fee_type + title + amount at time of payment
- `payment_id` FK with `ON DELETE CASCADE` — deleting a payment cleans up its allocations
- Each payment row splits into 1+ allocation rows (one per fee item covered)

## 3. Record Payment Form Redesign

### 3.1 Data flow

When a class and student are selected, the form fetches the student's bill items from `student_bill_items` for the selected year/term. This includes:
- **Standard fees** (have a `fee_structure_id`) — items selected from fee structures (Tuition, PTA Levy, etc.)
- **Custom fees** (`fee_structure_id IS NULL`) — manually added items (Arrears, Sports Fee, Late Penalty, etc.)

### 3.2 Form layout

Replace the current "Fee Type" dropdown + readonly Amount with:

**Auto-calculated bill summary** (shown after student is selected):
```
Total Bill: GHS 1,250.00
```

**Payment Type toggle (radio buttons):**
```
◉ Full Payment  ○ Partial Payment
```

**When "Partial Payment" is selected**, show a breakdown table:

| Select | Fee Item | Amount | Type |
|--------|----------|--------|------|
| ☑ | Tuition | GHS 550.00 | Standard |
| ☑ | PTA Levy | GHS 100.00 | Standard |
| ☐ | Arrears | GHS 50.00 | Custom Fee |
| ☐ | Late Payment Penalty | GHS 20.00 | Custom Fee |
| | **Selected Total** | **GHS 0.00** | |

- All items checked by default
- Unchecking items reduces the Selected Total
- Custom fees are visually tagged with a "(Custom)" badge
- The Amount field is auto-filled from the selected total, and is editable for manual overrides

**When "Full Payment" is selected:**
- Amount = Total Bill, read-only

### 3.3 JavaScript logic

```javascript
// When student is selected:
// 1. Fetch bill items from pre-loaded JSON or via AJAX
// 2. Calculate total bill
// 3. Render breakdown section
// 4. Set amount = total bill

// On payment type toggle:
// - Full: hide breakdown, set amount = total, make readonly
// - Partial: show breakdown, sum checked items into amount

// On checkbox change:
// - Recalculate selected total
// - Update amount field
```

**Pre-loaded data**: On page load, load all `student_bill_items` for the current year/term into a JS object keyed by student_id. This avoids extra AJAX calls (following existing pattern where `FEE_STRUCTURES`, `STUDENTS`, and `CLASSES` are pre-loaded).

### 3.4 POST handler changes

Current handler inserts:
```php
INSERT INTO payments (student_id, amount, academic_year, term, payment_method, payment_date, receipt_number, recorded_by, fee_type, transaction_reference)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
```

**New behavior:**
1. Determine `fee_type` value:
   - If Full Payment → `"Full Payment"`
   - If Partial Payment → `"Partial Payment"`
   - (In future, could be the primary fee type if only one item selected)

2. Insert into `payments` table with the determined `fee_type`

3. Insert 1+ rows into `payment_allocations`:
```php
foreach (selected items as item) {
    INSERT INTO payment_allocations (payment_id, fee_type, fee_title, amount)
    VALUES (?, ?, ?, ?)
}
```

4. Generate receipt (unchanged — receipt shows total amount)

## 4. Recent Payments Table

**Column change:** Replace "Fee Type" header with "Status"

| Receipt # | Student | Status | Trans. Ref | Amount | Balance | Date | Method | Action |
|-----------|---------|--------|------------|--------|---------|------|--------|--------|
| CEC-... | ABDUL NASAL MALIK | PAID | C1204 | 550.00 | 0.00 | ... | Cash | View |
| CEC-... | ACHEAMPONG LINDA | PARTIAL | C1208 | 300.00 | 250.00 | ... | Cash | View |

**Status logic:** For each payment row, determine status:
- **PAID** (green badge) — if after this payment, student's remaining balance <= 0
- **PARTIAL** (amber badge) — if student still has a balance
- For backward compatibility, compute from `amount` vs total bill, or use the stored `fee_type` value

**Computation:** Since we already compute `$balance = max(0, $required_dues - (float)$payment['total_paid'])` for each row, use the same logic:
- `$balance <= 0` → **PAID**
- `$balance > 0` → **PARTIAL**

## 5. Fee Debt Report Updates (`admin_fees_debt.php`)

### 5.1 Current paid-amount logic

```php
// Groups payments by fee_type, caps per type based on fee_structures
$paid_by_type = [];
foreach ($payments_by_student[$sid] as $p) {
    $type = $p['fee_type'] ?? 'General';
    $paid_by_type[$type] += (float)$p['amount'];
}
// Caps per fee type
foreach ($paid_by_type as $type => $amt) {
    $cap = $type_fee_map[$type] ?? 0;
    $paid += ($cap > 0) ? min($amt, $cap) : $amt;
}
```

### 5.2 New paid-amount logic

Fetch `payment_allocations` for all student_ids in the current page:

```php
// Fetch allocations for the current page's students
$allocations_by_payment = [];
if (!empty($student_ids)) {
    $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
    $stmt = $pdo->prepare("SELECT pa.*, p.student_id FROM payment_allocations pa 
        JOIN payments p ON pa.payment_id = p.id 
        WHERE p.student_id IN ($placeholders) AND p.academic_year = ? AND p.term = ?");
    $stmt->execute(array_merge($student_ids, [$filter_year, $filter_term]));
    foreach ($stmt->fetchAll() as $a) {
        $pid = (int)$a['payment_id'];
        if (!isset($allocations_by_payment[$pid])) $allocations_by_payment[$pid] = [];
        $allocations_by_payment[$pid][] = $a;
    }
}
```

Then in the per-student computation:

```php
// Calculate paid amount
$paid = 0;
if (isset($payments_by_student[$sid])) {
    $has_allocations = false;
    $paid_by_type_from_allocations = [];
    
    foreach ($payments_by_student[$sid] as $p) {
        $pid = (int)$p['id'];
        if (isset($allocations_by_payment[$pid]) && !empty($allocations_by_payment[$pid])) {
            // Use allocations
            $has_allocations = true;
            foreach ($allocations_by_payment[$pid] as $a) {
                $type = $a['fee_type'] ?? 'General';
                if (!isset($paid_by_type_from_allocations[$type])) {
                    $paid_by_type_from_allocations[$type] = 0;
                }
                $paid_by_type_from_allocations[$type] += (float)$a['amount'];
            }
        } else {
            // Legacy payment — use old fee_type grouping
            $type = $p['fee_type'] ?? 'General';
            if (!isset($paid_by_type_from_allocations[$type])) {
                $paid_by_type_from_allocations[$type] = 0;
            }
            $paid_by_type_from_allocations[$type] += (float)$p['amount'];
        }
    }
    
    // Apply capping per fee type
    foreach ($paid_by_type_from_allocations as $type => $amt) {
        $cap = $type_fee_map[$type] ?? 0;
        $paid += ($cap > 0) ? min($amt, $cap) : $amt;
    }
}
```

This ensures backward compatibility — new payments use allocations, legacy payments fall back to the old method.

## 6. Migration

New SQL migration file: `api/migrate_payment_allocations.sql`

Sequence:
1. Run migration before deploying code changes
2. All existing payments remain untouched (no backfill needed — legacy path handles them)
3. New payments recorded after deployment will create allocations

## 7. Future: Financial Reporting Page

The `payment_allocations` table enables per-fee-type income reporting:

```sql
SELECT 
    pa.fee_type,
    SUM(pa.amount) as total_collected,
    COUNT(DISTINCT pa.payment_id) as transaction_count
FROM payment_allocations pa
JOIN payments p ON pa.payment_id = p.id
WHERE p.academic_year = ? AND p.term = ?
GROUP BY pa.fee_type
ORDER BY total_collected DESC
```

This powers the upcoming income/expense financial dashboard.

## 8. Implementation Plan

### Phase 1: Migration
- Create `api/migrate_payment_allocations.sql`

### Phase 2: admin_payments.php
- Pre-load `student_bill_items` data into JS
- Rewrite Record Payment form UI (bill summary, Full/Partial toggle, item breakdown)
- Update POST handler to insert allocations
- Add Status column to Recent Payments table

### Phase 3: admin_fees_debt.php
- Fetch allocations for current page
- Update paid-amount computation to use allocations with legacy fallback

### Phase 4: Verify
- Record a Full Payment → verify allocations created
- Record a Partial Payment with selected items → verify only those items allocated
- Verify fee debt report shows correct paid amounts
- Verify legacy payments still show correct data
