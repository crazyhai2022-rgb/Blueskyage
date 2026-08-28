<?php
require_once __DIR__ . '/functions.php';

/**
 * Renders the invoice document. Used by the client view and the admin view,
 * so both always show exactly the same figures.
 *
 * Plans and products get different bodies: a plan invoice shows the plan
 * comparison and ad-fund lines, a product invoice shows the item and how it
 * is delivered.
 */
function render_invoice(array $o): string {
    $item      = catalog_item($o['plan_slug'] ?? '');
    $recurring = $item['recurring'] ?? (stripos($o['plan'], 'plan') !== false);

    $depositUsd = (int)($o['deposit_usd'] ?? 0);
    $usdRate    = (int)($o['usd_rate'] ?? 0) ?: 105;
    $depositInr = $depositUsd * $usdRate;
    $discount   = (int)($o['discount'] ?? 0);
    $planPrice  = (int)($o['original_amount'] ?? $o['amount']) - $depositInr;
    $total      = (int)$o['amount'];

    $paid   = in_array($o['status'], ['paid_preparing', 'active'], true);
    $status = $paid ? 'Paid' : ($o['status'] === 'cancelled' ? 'Cancelled' : 'Payment Due');
    $statusClass = $paid ? 'ok' : ($o['status'] === 'cancelled' ? 'off' : 'due');

    $invoiceNo = $o['invoice_no'] ?: sprintf('BSA-%04d', (int)$o['id']);
    $orderNo   = 'BSA-ORD-' . sprintf('%04d', (int)$o['id']);
    $date      = date('j M Y', strtotime($o['created_at']));

    $name  = $o['contact_name']  ?: ($o['user_name'] ?? '');
    $phone = $o['contact_phone'] ?: ($o['phone'] ?? '');
    $email = $o['contact_email'] ?: ($o['email'] ?? '');
    $biz   = $o['business_name'] ?: '—';

    $dash = '&mdash;';
    $rows = '';

    /* ---------- charge lines ---------- */
    if ($depositUsd > 0) {
        $rows .= '<tr><td>Ad Fund Add</td>'
               . '<td>Loaded into ad account ' . e($o['slot_id'] ?: 'pending') . '</td>'
               . '<td class="num">$' . $depositUsd . ' &middot; &#8377;' . number_format($depositInr) . '</td>'
               . '<td><span class="chip ' . $statusClass . '">' . $status . '</span></td></tr>';
    }

    $lineLabel = $recurring ? 'Ad Account Monthly Subscription' : 'Product Purchase';
    $lineDesc  = $recurring
        ? e($o['plan']) . ' &mdash; monthly rental charge'
        : e($o['plan']) . ' &mdash; one-time purchase';

    $rows .= '<tr><td>' . $lineLabel . '</td><td>' . $lineDesc . '</td>'
           . '<td class="num">&#8377;' . number_format($planPrice) . '</td>'
           . '<td><span class="chip ' . $statusClass . '">' . $status . '</span></td></tr>';

    if ($discount > 0) {
        $rows .= '<tr><td>Discount</td>'
               . '<td>Coupon <b>' . e($o['coupon_code']) . '</b> applied</td>'
               . '<td class="num minus">&minus;&#8377;' . number_format($discount) . '</td>'
               . '<td><span class="chip ok">Applied</span></td></tr>';
    }

    /* ---------- totals ---------- */
    $totals = '';
    if ($depositUsd > 0) {
        $totals .= '<div class="t-row"><span>Ad Fund Add</span><b>&#8377;' . number_format($depositInr) . '</b></div>';
    }
    $totals .= '<div class="t-row"><span>' . ($recurring ? 'Monthly Subscription' : 'Item Price')
             . '</span><b>&#8377;' . number_format($planPrice) . '</b></div>';
    if ($discount > 0) {
        $totals .= '<div class="t-row"><span>Coupon ' . e($o['coupon_code'])
                 . '</span><b class="minus">&minus;&#8377;' . number_format($discount) . '</b></div>';
    }
    $totals .= '<div class="t-row grand"><span>' . ($paid ? 'Total Paid' : 'Amount Due')
             . '</span><b>&#8377;' . number_format($total) . '</b></div>';

    /* ---------- plan comparison (rental plans only) ---------- */
    $planBlock = '';
    if ($recurring) {
        $isBasic = ($o['plan_slug'] ?? '') === 'basic' || stripos($o['plan'], 'basic') !== false;
        $mk = function (string $title, string $price, array $points, bool $on): string {
            return '<div class="plan-card' . ($on ? ' on' : '') . '">'
                 . '<span class="plan-tag">' . ($on ? 'SELECTED' : 'NOT SELECTED') . '</span>'
                 . '<h4>' . $title . '</h4>'
                 . '<div class="plan-price">&#8377;' . $price . ' <span>/ month</span></div>'
                 . '<ul>' . implode('', array_map(fn($p) => '<li>' . $p . '</li>', $points)) . '</ul></div>';
        };
        $planBlock = '<h3 class="inv-h">Selected Ad Account Plan</h3><div class="plan-grid">'
            . $mk('Basic Plan', '1,499', [
                'Non-Ban Ad Account, USA Base', 'Unlimited Spending Limit',
                'Instant Replacement (Unlimited)', 'Rate: &#8377;105 per $ &middot; Min Deposit $15',
              ], $isBasic)
            . $mk('Pro Plan', '2,499', [
                'USA + Hong Kong Base, High Quality', 'Unlimited Spending Limit',
                'Instant Replacement (Unlimited)', 'Rate: &#8377;104 per $ &middot; Min Deposit $15',
              ], !$isBasic)
            . '</div>';
    }

    /* ---------- account / delivery panel ---------- */
    if ($recurring) {
        $right = '<div class="kv"><span>Business ID (BM ID)</span><b>' . ($o['bm_id'] ? e($o['bm_id']) : $dash) . '</b></div>'
               . '<div class="kv"><span>Ad Account Number</span><b>' . ($o['slot_id'] ? e($o['slot_id']) : $dash) . '</b></div>';
    } else {
        $right = '<div class="kv"><span>Delivery</span><b>Digital &mdash; sent on WhatsApp</b></div>';
        if (!empty($o['profile_link'])) {
            $right .= '<div class="kv"><span>Profile Link</span><b>' . e($o['profile_link']) . '</b></div>';
        }
        if (!empty($o['page_name'])) {
            $right .= '<div class="kv"><span>Requested Page Name</span><b>' . e($o['page_name']) . '</b></div>';
        }
    }

    /* ---------- status strip ---------- */
    $statusRows = '<tr><th>Order Status</th><td>' . status_label($o['status']) . '</td></tr>';
    if ($recurring) {
        $statusRows .= '<tr><th>Minimum Deposit</th><td>$15</td></tr>'
                     . '<tr><th>Deposit On This Invoice</th><td>' . ($depositUsd > 0 ? '$' . $depositUsd : '$0') . '</td></tr>';
        if (!empty($o['ad_account_id'])) {
            $statusRows .= '<tr><th>Ad Account Reference</th><td>' . e($o['ad_account_id']) . '</td></tr>';
        }
    }
    if (!empty($o['admin_note'])) {
        $statusRows .= '<tr><th>Note</th><td>' . e($o['admin_note']) . '</td></tr>';
    }
    if (!empty($o['razorpay_payment_id'])) {
        $statusRows .= '<tr><th>Payment Reference</th><td>' . e($o['razorpay_payment_id']) . '</td></tr>';
    }

    $stampClass = $paid ? 'stamp-paid' : 'stamp-due';
    $stampText  = $paid ? 'PAID' : 'DUE';

    return <<<HTML
<div class="invoice" id="invoiceDoc">

  <div class="inv-top">
    <div class="inv-brand">
      <img src="/assets/img/logo-mark.png" alt="">
      <div>
        <b>BlueSky Agency</b>
        <span>Digital Advertising Partner</span>
      </div>
    </div>
    <div class="inv-title">
      <h2>INVOICE</h2>
      <span>Professional Agency Invoice</span>
    </div>
  </div>

  <div class="inv-meta">
    <div><span>Invoice No.</span><b>{$invoiceNo}</b></div>
    <div><span>Order No.</span><b>{$orderNo}</b></div>
    <div><span>Invoice Date</span><b>{$date}</b></div>
    <div><span>Payment Status</span><b class="chip {$statusClass}">{$status}</b></div>
  </div>

  <div class="inv-panels">
    <div class="inv-panel">
      <h3 class="inv-h">Bill To</h3>
      <div class="kv"><span>Name</span><b>{$name}</b></div>
      <div class="kv"><span>Mobile</span><b>{$phone}</b></div>
      <div class="kv"><span>Business Name</span><b>{$biz}</b></div>
      <div class="kv"><span>Email</span><b>{$email}</b></div>
    </div>
    <div class="inv-panel">
      <h3 class="inv-h">Account Details</h3>
      {$right}
    </div>
  </div>

  {$planBlock}

  <h3 class="inv-h">Charges Breakdown &mdash; This Transaction</h3>
  <table class="inv-table">
    <thead><tr><th>Charge</th><th>Description</th><th class="num">Amount</th><th>Status</th></tr></thead>
    <tbody>{$rows}</tbody>
  </table>

  <div class="inv-totals">
    <div class="t-box">{$totals}</div>
  </div>

  <h3 class="inv-h">Order Status</h3>
  <table class="inv-status">{$statusRows}</table>

  <div class="inv-stamp {$stampClass}">{$stampText}</div>

  <div class="inv-foot">
    <p>This invoice is generated electronically and is valid without a physical
       signature. For billing queries contact info@blueskyage.in.</p>
    <div><b>Authorized by BlueSky Agency</b><span>Thank you for your business</span></div>
  </div>
</div>
HTML;
}
