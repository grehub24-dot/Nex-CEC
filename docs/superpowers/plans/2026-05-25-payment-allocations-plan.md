# Payment Allocations Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace the single-fee-type payment form with a bill-item breakdown supporting Full/Partial payments, tracked via a new `payment_allocations` table.

**Architecture:** New `payment_allocations` table (1 payment → N allocations). Record Payment form fetches `student_bill_items` to show itemized breakdown with Full/Partial toggle. POST handler inserts payment row + allocation rows. Fee debt report updated to read allocations with legacy fallback.

**Tech Stack:** PHP 7.4+, MySQL (via pg-bridge), vanilla JS

---

### Task 1: Create migration SQL

**Files:**
- Create: `api/migrate_payment_allocations.sql`

- [ ] **Step 1: Create migration file**

```sql
-- Migration: Create payment_allocations table
-- Enables tracking which fee items each payment covers.
-- One payment may have multiple allocations (Full Payment = all items, Partial Payment = selected items).
-- Run this in Supabase Dashboard SQL Editor before deploying code changes.

CREATE TABLE IF NOT EXISTS payment_allocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NOT NULL,
    fee_type VARCHAR(100) NOT NULL,
    fee_title VARCHAR(200) DEFAULT '',
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
);

-- Index for quick lookup by payment
CREATE INDEX IF NOT EXISTS idx_pa_payment ON payment_allocations(payment_id);

-- Index for financial reporting (income by fee_type)
CREATE INDEX IF NOT EXISTS idx_pa_fee_type ON payment_allocations(fee_type);
```

- [ ] **Step 2: Commit**

```bash
git add api/migrate_payment_allocations.sql
git commit -m "feat: add payment_allocations table migration"
```

---

### Task 2: Pre-load bill items into admin_payments.php data layer

**Files:**
- Modify: `api/admin_payments.php` (add bill items query + JS pass-through)

- [ ] **Step 1: Add student_bill_items query after line 27 (fee_structures query)**

Add a query to fetch all `student_bill_items` for the current year/term:

```php
// Line 28 (after fee_structures query):
$all_bill_items = [];
try {
    $stmt = $pdo->prepare("SELECT sbi.*, s.full_name, s.class_name FROM student_bill_items sbi JOIN students s ON sbi.student_id = s.id WHERE sbi.academic_year = ? AND sbi.term = ?");
    $stmt->execute([$current_academic_year, $current_term]);
    $all_bill_items = $stmt->fetchAll();
} catch (Exception $e) {
    $all_bill_items = [];
}
```

- [ ] **Step 2: Add JS variable in the script block (line 490)**

```javascript
// Add after FEE_STRUCTURES line:
var BILL_ITEMS = <?php echo json_encode($all_bill_items); ?>;
```

- [ ] **Step 3: Commit**

```bash
git add api/admin_payments.php
git commit -m "feat: pre-load student_bill_items for payment form"
```

---

### Task 3: Redesign Record Payment form — bill summary + Full/Partial toggle + item breakdown

**Files:**
- Modify: `api/admin_payments.php`

This is the largest change. We replace the "Fee Type" dropdown and readonly Amount with:
- Auto-calculated total bill display
- Full Payment / Partial Payment radio toggle
- Itemized breakdown with checkboxes (shown only on Partial)

- [ ] **Step 1: Replace the Fee Type dropdown + Amount field in the form HTML (lines 436-446)**

**Remove (lines 436-441):**
```html
<div>
    <label>Fee Type</label>
    <select name="fee_type" id="pay_fee_type" class="form-control" required>
        <option value="">-- Select Class &amp; Term First --</option>
    </select>
</div>

<div>
    <label>Amount (GHS)</label>
    <input type="number" step="0.01" name="amount" id="pay_amount" class="form-control" required readonly placeholder="Auto-filled from fee type">
</div>
```

**Replace with:**
```html
<div style="grid-column: span 2;">
    <div id="bill_summary" style="display:none; background:#f0f8ff; border:1px solid #d4e6f1; border-radius:8px; padding:15px; margin-bottom:10px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
            <strong style="font-size:16px;">Total Bill: GHS <span id="total_bill_amount">0.00</span></strong>
            <span id="bill_source" style="font-size:12px; color:#666;"></span>
        </div>
        <div style="border-top:1px solid #d4e6f1; padding-top:10px;">
            <label style="display:flex; align-items:center; gap:10px; margin-bottom:8px; cursor:pointer;">
                <input type="radio" name="payment_type" value="full" id="pay_full" checked onchange="togglePaymentType()">
                <strong>Full Payment</strong> <span style="color:#666; font-size:13px;">— Pay entire bill</span>
            </label>
            <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                <input type="radio" name="payment_type" value="partial" id="pay_partial" onchange="togglePaymentType()">
                <strong>Partial Payment</strong> <span style="color:#666; font-size:13px;">— Select specific items to pay</span>
            </label>
        </div>
        <div id="partial_breakdown" style="display:none; margin-top:12px; max-height:250px; overflow-y:auto; border:1px solid #e0e0e0; border-radius:6px;">
            <table style="width:100%; border-collapse:collapse; font-size:13px;">
                <thead>
                    <tr style="background:#f8f9fa;">
                        <th style="padding:8px 10px; width:40px; text-align:center;">Pay</th>
                        <th style="padding:8px 10px; text-align:left;">Fee Item</th>
                        <th style="padding:8px 10px; text-align:center; width:80px;">Amount (GHS)</th>
                    </tr>
                </thead>
                <tbody id="breakdown_items"></tbody>
            </table>
        </div>
    </div>
    <div id="no_bill_msg" style="display:none; background:#fef9e7; border:1px solid #f9e79f; border-radius:8px; padding:15px; text-align:center; color:#7d6608;">
        No bill items found for this student in the selected term.
    </div>
</div>

<div>
    <label>Amount (GHS)</label>
    <input type="number" step="0.01" name="amount" id="pay_amount" class="form-control" required placeholder="Auto-calculated from bill">
</div>
```

- [ ] **Step 2: Update the JavaScript to render the breakdown and handle toggles**

**Replace the `feeTypeSelect` change handler and `refreshFeeTypes` references** with new logic.

Find and replace the `refreshFeeTypes()` function and `feeTypeSelect` change handler with:

```javascript
// ====== Render bill breakdown when student changes ======
function renderBillBreakdown() {
    var studentId = parseInt(studentSelect.value);
    var year = academicYearInput.value;
    var term = termSelect.value;
    var billSummary = document.getElementById('bill_summary');
    var noBillMsg = document.getElementById('no_bill_msg');
    var breakdownBody = document.getElementById('breakdown_items');
    var totalBillSpan = document.getElementById('total_bill_amount');
    var billSource = document.getElementById('bill_source');
    var amountInput = document.getElementById('pay_amount');

    // Reset
    billSummary.style.display = 'none';
    noBillMsg.style.display = 'none';
    breakdownBody.innerHTML = '';

    if (!studentId || !year || !term) return;

    // Filter bill items for this student/year/term
    var items = BILL_ITEMS.filter(function(b) {
        return parseInt(b.student_id) === studentId
            && (b.academic_year || '') === year
            && (b.term || '') === term;
    });

    if (items.length === 0) {
        noBillMsg.style.display = 'block';
        billSummary.style.display = 'none';
        amountInput.value = '0';
        return;
    }

    billSummary.style.display = 'block';
    noBillMsg.style.display = 'none';

    var total = 0;
    items.forEach(function(item) {
        var amt = parseFloat(item.amount || 0);
        total += amt;
        var isCustom = (item.fee_structure_id === null || item.fee_structure_id === '');
        var tr = document.createElement('tr');
        tr.style.borderBottom = '1px solid #f0f0f0';
        if (isCustom) tr.style.background = '#fef9e7';
        tr.innerHTML = '<td style="padding:8px 10px;text-align:center;">'
            + '<input type="checkbox" class="item-checkbox" checked data-title="' + htmlspecialchars(item.title || item.fee_type)
            + '" data-type="' + htmlspecialchars(item.fee_type || 'General')
            + '" data-amount="' + amt.toFixed(2) + '"></td>'
            + '<td style="padding:8px 10px;">' + htmlspecialchars(item.title || item.fee_type)
            + (isCustom ? ' <span style="font-size:10px;color:#e8a317;font-weight:600;">(Custom)</span>' : '')
            + '</td>'
            + '<td style="padding:8px 10px;text-align:center;font-weight:600;">' + amt.toFixed(2) + '</td>';
        breakdownBody.appendChild(tr);
    });

    totalBillSpan.textContent = total.toFixed(2);
    billSource.textContent = items.length + ' item(s)';

    // Default: Full Payment → amount = total
    document.getElementById('pay_full').checked = true;
    document.getElementById('partial_breakdown').style.display = 'none';
    amountInput.value = total.toFixed(2);
    amountInput.readOnly = true;

    // Set checkboxes to all checked by default
    var checkboxes = breakdownBody.querySelectorAll('.item-checkbox');
    checkboxes.forEach(function(cb) { cb.checked = true; });
}

// ====== Toggle between Full and Partial Payment ======
function togglePaymentType() {
    var isPartial = document.getElementById('pay_partial').checked;
    var breakdown = document.getElementById('partial_breakdown');
    var amountInput = document.getElementById('pay_amount');
    var totalBill = parseFloat(document.getElementById('total_bill_amount').textContent) || 0;

    if (isPartial) {
        breakdown.style.display = 'block';
        amountInput.readOnly = false;
        updatePartialTotal();
    } else {
        breakdown.style.display = 'none';
        amountInput.value = totalBill.toFixed(2);
        amountInput.readOnly = true;
    }
}

// ====== Update amount when partial checkboxes change ======
function updatePartialTotal() {
    var checkboxes = document.querySelectorAll('.item-checkbox:checked');
    var total = 0;
    checkboxes.forEach(function(cb) {
        total += parseFloat(cb.getAttribute('data-amount') || 0);
    });
    document.getElementById('pay_amount').value = total.toFixed(2);
}

// ====== Wire up event delegation for checkbox changes ======
document.addEventListener('change', function(e) {
    if (e.target && e.target.classList.contains('item-checkbox')) {
        updatePartialTotal();
    }
});

// ====== Helper: htmlspecialchars for JS ======
function htmlspecialchars(str) {
    if (!str) return '';
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(String(str)));
    return div.innerHTML;
}
```

- [ ] **Step 3: Wire up the student select change handler to call renderBillBreakdown**

Find the `classSelect.addEventListener('change', ...)` handler and add a call to `renderBillBreakdown()` after `refreshFeeTypes()` call.

In the `classSelect` change handler (around line 545), after `refreshFeeTypes()` is called, add:

```javascript
// Also try to render bill if student was auto-selected
renderBillBreakdown();
```

Also in the `studentSelect` — it's populated dynamically, so we need to add a change listener for it too. Add after `feeTypeSelect` setup:

```javascript
// ====== Render bill breakdown when student is selected ======
studentSelect.addEventListener('change', function() {
    renderBillBreakdown();
});
```

- [ ] **Step 4: Remove old fee-type-dependent code**

Remove the `refreshFeeTypes()` function entirely (it's replaced by `renderBillBreakdown()`).

Remove the `feeTypeSelect.addEventListener('change', ...)` handler.

Remove the `feeTypeSelect` change handler for year/term (`academicYearInput.addEventListener('change', refreshFeeTypes)` → replace with `renderBillBreakdown`).

Change `termSelect.addEventListener('change', refreshFeeTypes)` to `termSelect.addEventListener('change', renderBillBreakdown)`.

Change `academicYearInput.addEventListener('change', refreshFeeTypes)` to `academicYearInput.addEventListener('change', renderBillBreakdown)`.

- [ ] **Step 5: Commit**

```bash
git add api/admin_payments.php
git commit -m "feat: redesign payment form with bill breakdown and Full/Partial toggle"
```

---

### Task 4: Update POST handler to insert payment_allocations

**Files:**
- Modify: `api/admin_payments.php` (POST handler, around line 32-238)

- [ ] **Step 1: Update the POST handler to insert allocations after payment insert**

After the payment INSERT (line 77) and before receipt generation, add allocation logic:

```php
// Insert payment allocations
$selected_types = [];
if ($fee_type === 'Full Payment' || $fee_type === 'Partial Payment') {
    // New-style payment: decode items from posted data
    $fee_items_json = $_POST['fee_items'] ?? '';
    $fee_items = $fee_items_json ? json_decode($fee_items_json, true) : [];
    if (empty($fee_items)) {
        // Fallback: create one allocation for the full amount using the first available fee type
        $fee_items = [['fee_type' => $fee_type, 'fee_title' => $fee_type, 'amount' => $amount]];
    }
} else {
    // Legacy fallback (shouldn't happen with new UI)
    $fee_items = [['fee_type' => $fee_type, 'fee_title' => $fee_type, 'amount' => $amount]];
}

$allocStmt = $pdo->prepare("INSERT INTO payment_allocations (payment_id, fee_type, fee_title, amount) VALUES (?, ?, ?, ?)");
foreach ($fee_items as $item) {
    $allocStmt->execute([
        $payment_id,
        $item['fee_type'] ?? 'General',
        $item['fee_title'] ?? $item['fee_type'] ?? 'General',
        (float)($item['amount'] ?? 0)
    ]);
}
```

Wait — this approach requires passing the selected items from JS to PHP as a JSON-encoded field. That's cleaner than trying to parse multiple form fields.

Actually, let me reconsider. The selected items are checkboxes in the partial breakdown. For a Full Payment, all items are selected. For Partial, only checked items.

I'll add a hidden input that gets populated with the selected items' data on form submit. In the form HTML, add:

```html
<input type="hidden" name="fee_items_json" id="fee_items_json" value="">
```

And in the JavaScript before form submit:

```javascript
// Before form submission, serialize selected items
document.querySelector('form').addEventListener('submit', function() {
    var checkboxes = document.querySelectorAll('.item-checkbox');
    var items = [];
    checkboxes.forEach(function(cb) {
        if (cb.checked) {
            items.push({
                fee_type: cb.getAttribute('data-type'),
                fee_title: cb.getAttribute('data-title'),
                amount: parseFloat(cb.getAttribute('data-amount'))
            });
        }
    });
    document.getElementById('fee_items_json').value = JSON.stringify(items);
});
```

Also update how $fee_type is determined. In the POST handler, set:
```php
$payment_type = sanitize($_POST['payment_type'] ?? 'full');
$fee_type = ($payment_type === 'partial') ? 'Partial Payment' : 'Full Payment';
```

Wait, the current code already has `$fee_type = sanitize($_POST['fee_type']);`. Since we removed the fee_type dropdown, we need to get the payment type from the radio buttons instead.

Let me revise the POST handler (lines 36-44):

```php
$student_id_input = (int)($_POST['student_id'] ?? 0);
$admission_number = sanitize($_POST['admission_number'] ?? '');
$transaction_reference = sanitize($_POST['transaction_reference'] ?? '');
$class_name = sanitize($_POST['class_name']);
$amount = floatval($_POST['amount']);
$year = sanitize($_POST['academic_year']);
$term = sanitize($_POST['term']);
$payment_type = sanitize($_POST['payment_type'] ?? 'full');
$fee_type = ($payment_type === 'partial') ? 'Partial Payment' : 'Full Payment';
$method = sanitize($_POST['payment_method']);
$date = sanitize($_POST['payment_date']);
```

Then after the payment INSERT, add the allocation insertion (before receipt generation):

```php
// Insert payment allocations
$fee_items_json = $_POST['fee_items_json'] ?? '';
$fee_items = $fee_items_json ? json_decode($fee_items_json, true) : [];

if (empty($fee_items)) {
    // Fallback: one allocation for the full amount
    $fee_items = [['fee_type' => $fee_type, 'fee_title' => $fee_type, 'amount' => $amount]];
}

$allocStmt = $pdo->prepare("INSERT INTO payment_allocations (payment_id, fee_type, fee_title, amount) VALUES (?, ?, ?, ?)");
foreach ($fee_items as $item) {
    $allocStmt->execute([
        $payment_id,
        $item['fee_type'] ?? 'General',
        $item['fee_title'] ?? $item['fee_type'] ?? 'General',
        (float)($item['amount'] ?? 0)
    ]);
}
```

Now, for the JS side, I'll remove the hidden field approach and instead use a simpler approach: add a hidden input that stores the fee type of the primary selected item (for backward compat with receipt), and store the full breakdown in the `fee_items_json` hidden field.

Actually wait, let me think about this more carefully. The receipt generator currently uses `$fee_type` to display what was paid. For Full Payment, it should say "Full Payment". For Partial, it should say "Partial Payment". That's fine — the receipt just shows the overall payment type.

For the receipt email HTML (lines 160-226), the `$fee_type` is displayed. With this change, the receipt will show "Full Payment" or "Partial Payment" instead of "Tuition". That's acceptable.

Let me finalize this task's steps.

Actually I realize there's also a line that uses $fee_type in the INSERT:
```php
$stmt = $pdo->prepare("INSERT INTO payments (student_id, amount, academic_year, term, payment_method, payment_date, receipt_number, recorded_by, fee_type, transaction_reference) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([$student['id'], $amount, $year, $term, $method, $date, $receipt_number, $_SESSION['user_id'], $fee_type, $transaction_reference]);
```

This will now store "Full Payment" or "Partial Payment" as the fee_type. That's fine.

Let me finalize the steps.

Also, the `fee_types` setting and dropdown were used in the form. Since we're removing the Fee Type dropdown, I don't need the `$fee_types` variable anymore. But it might be used elsewhere on the page — let me check if it's referenced outside the dropdown. Looking at line 22: `$fee_types = explode(',', $settings['fee_types'] ?? 'Tuition,PTA Levy,...');` — this is just a fallback list. It's not used elsewhere in the code since the fee_types come from fee_structures. So I can leave it or remove it.

Actually, looking more carefully at the code, `$fee_types` doesn't seem to be used anywhere else in the visible code. Let me leave it as-is to be safe.

- [ ] **Step 1: Update POST handler variables**

Change line 43 from:
```php
$fee_type = sanitize($_POST['fee_type']);
```
to:
```php
$payment_type = sanitize($_POST['payment_type'] ?? 'full');
$fee_type = ($payment_type === 'partial') ? 'Partial Payment' : 'Full Payment';
```

- [ ] **Step 2: Add allocation insertion after payment INSERT**

After the line `$payment_id = $pdo->lastInsertId();` (around line 78), before the receipt generation block, add:

```php
// Insert payment allocations
$fee_items_json = $_POST['fee_items_json'] ?? '';
$fee_items = $fee_items_json ? json_decode($fee_items_json, true) : [];

if (empty($fee_items)) {
    // Fallback: one allocation for the full amount
    $fee_items = [['fee_type' => $fee_type, 'fee_title' => $fee_type, 'amount' => $amount]];
}

$allocStmt = $pdo->prepare("INSERT INTO payment_allocations (payment_id, fee_type, fee_title, amount) VALUES (?, ?, ?, ?)");
foreach ($fee_items as $item) {
    $allocStmt->execute([
        $payment_id,
        $item['fee_type'] ?? 'General',
        $item['fee_title'] ?? $item['fee_type'] ?? 'General',
        (float)($item['amount'] ?? 0)
    ]);
}
```

- [ ] **Step 3: Add hidden input + submit handler to form**

**Add hidden input** in the form (anywhere inside the `<form>`, e.g., near the CSRF field):
```html
<input type="hidden" name="fee_items_json" id="fee_items_json" value="">
```

**Add form submit handler** in the JavaScript (after existing event handlers):
```javascript
// ====== Serialize selected fee items on form submit ======
document.querySelector('#paymentModal form').addEventListener('submit', function() {
    var checkboxes = document.querySelectorAll('.item-checkbox');
    var items = [];
    checkboxes.forEach(function(cb) {
        if (cb.checked) {
            items.push({
                fee_type: cb.getAttribute('data-type'),
                fee_title: cb.getAttribute('data-title'),
                amount: parseFloat(cb.getAttribute('data-amount'))
            });
        }
    });
    document.getElementById('fee_items_json').value = JSON.stringify(items);
});
```

- [ ] **Step 4: Commit**

```bash
git add api/admin_payments.php
git commit -m "feat: insert payment_allocations on payment record"
```

---

### Task 5: Add Status column to Recent Payments table

**Files:**
- Modify: `api/admin_payments.php` (table HTML, around lines 334-376)

- [ ] **Step 1: Replace "Fee Type" header with "Status" in the <thead>**

Change line 340 from:
```html
<th>Fee Type</th>
```
to:
```html
<th>Status</th>
```

- [ ] **Step 2: Replace the Fee Type cell in the table body**

Change line 359 from:
```html
<td><?php echo htmlspecialchars($payment['fee_type'] ?? 'General'); ?></td>
```
to:
```html
<td>
    <?php
    $status_balance = max(0, $required_dues - (float)$payment['total_paid']);
    if ($status_balance <= 0): ?>
        <span style="display:inline-block;padding:2px 10px;border-radius:10px;font-size:11px;font-weight:600;background:#d5f5e3;color:#1e8449;">PAID</span>
    <?php else: ?>
        <span style="display:inline-block;padding:2px 10px;border-radius:10px;font-size:11px;font-weight:600;background:#fef9e7;color:#b7950b;">PARTIAL</span>
    <?php endif; ?>
</td>
```

- [ ] **Step 3: Commit**

```bash
git add api/admin_payments.php
git commit -m "feat: add Status column to Recent Payments table"
```

---

### Task 6: Update admin_fees_debt.php to use payment_allocations

**Files:**
- Modify: `api/admin_fees_debt.php`

- [ ] **Step 1: Fetch allocations for the current page's students**

After the `bill_items_by_student` query (around line 146), add:

```php
// Fetch payment_allocations for all students on this page
$allocations_by_payment = [];
if (!empty($student_ids)) {
    $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
    try {
        $stmt = $pdo->prepare("SELECT pa.*, p.student_id FROM payment_allocations pa 
            JOIN payments p ON pa.payment_id = p.id 
            WHERE p.student_id IN ($placeholders) AND p.academic_year = ? AND p.term = ?");
        $stmt->execute(array_merge($student_ids, [$filter_year, $filter_term]));
        foreach ($stmt->fetchAll() as $a) {
            $pid = (int)$a['payment_id'];
            if (!isset($allocations_by_payment[$pid])) $allocations_by_payment[$pid] = [];
            $allocations_by_payment[$pid][] = $a;
        }
    } catch (Exception $e) {
        error_log("allocations query error: " . $e->getMessage());
    }
}
```

- [ ] **Step 2: Update the paid-amount computation to use allocations**

Replace the paid-amount block (lines 192-213):

**Old code:**
```php
// Calculate paid amount
$paid = 0;
if (isset($payments_by_student[$sid])) {
    // Group by fee_type and cap per type (same as student_fees.php logic)
    $paid_by_type = [];
    foreach ($payments_by_student[$sid] as $p) {
        $type = $p['fee_type'] ?? 'General';
        if (!isset($paid_by_type[$type])) $paid_by_type[$type] = 0;
        $paid_by_type[$type] += (float)$p['amount'];
    }
    // Cap per fee type
    $type_fee_map = [];
    foreach ($all_fees as $f) {
        $ft = $f['fee_type'] ?? 'General';
        if (!isset($type_fee_map[$ft])) $type_fee_map[$ft] = 0;
        $type_fee_map[$ft] += (float)$f['amount'];
    }
    foreach ($paid_by_type as $type => $amt) {
        $cap = $type_fee_map[$type] ?? 0;
        $paid += ($cap > 0) ? min($amt, $cap) : $amt;
    }
}
```

**New code:**
```php
// Calculate paid amount
$paid = 0;
if (isset($payments_by_student[$sid])) {
    $paid_by_type = [];

    foreach ($payments_by_student[$sid] as $p) {
        $pid = (int)$p['id'];
        
        // Check if this payment has allocations (new-style)
        if (isset($allocations_by_payment[$pid]) && !empty($allocations_by_payment[$pid])) {
            // Use allocation amounts per fee type
            foreach ($allocations_by_payment[$pid] as $a) {
                $type = $a['fee_type'] ?? 'General';
                if (!isset($paid_by_type[$type])) $paid_by_type[$type] = 0;
                $paid_by_type[$type] += (float)$a['amount'];
            }
        } else {
            // Legacy payment — use the old per-payment fee_type + amount
            $type = $p['fee_type'] ?? 'General';
            if (!isset($paid_by_type[$type])) $paid_by_type[$type] = 0;
            $paid_by_type[$type] += (float)$p['amount'];
        }
    }

    // Cap per fee type (same as before)
    $type_fee_map = [];
    foreach ($all_fees as $f) {
        $ft = $f['fee_type'] ?? 'General';
        if (!isset($type_fee_map[$ft])) $type_fee_map[$ft] = 0;
        $type_fee_map[$ft] += (float)$f['amount'];
    }
    foreach ($paid_by_type as $type => $amt) {
        $cap = $type_fee_map[$type] ?? 0;
        $paid += ($cap > 0) ? min($amt, $cap) : $amt;
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add api/admin_fees_debt.php
git commit -m "feat: use payment_allocations for fee debt computation with legacy fallback"
```

---

### Task 7: Final verification

- [ ] **Step 1: Review all changes**

Check that the migration SQL, form UI, POST handler, and debt report all work together consistently:
- Payment form shows bill items from student_bill_items when student is selected
- Full Payment → amount = total bill, no breakdown shown
- Partial Payment → breakdown shown with checkboxes, selected items summed into amount
- On submit, payment + allocations are inserted
- Recent Payments table shows Status column
- Fee debt report correctly reads allocations with legacy fallback

- [ ] **Step 2: Push**

```bash
git push
```
