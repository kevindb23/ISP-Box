<?php
$company = $company ?? [];
$invoice = $invoice ?? [];
$items = $items ?? [];
$payments = $payments ?? [];

$money = static function ($value): string {
    return '₱' . number_format((float)($value ?? 0), 2);
};

$date = static function ($value): string {
    if (!$value) return '—';
    $ts = strtotime((string)$value);
    return $ts ? date('M d, Y', $ts) : (string)$value;
};

$dateTime = static function ($value): string {
    if (!$value) return '—';
    $ts = strtotime((string)$value);
    return $ts ? date('M d, Y h:i A', $ts) : (string)$value;
};

$e = static function ($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
};

$status = strtoupper((string)($invoice['status'] ?? 'UNPAID'));
$logoUrl = trim((string)($company['logo_url'] ?? ''));

$serviceNumber = trim((string)($invoice['service_number'] ?? ''));
if ($serviceNumber === '') {
    $serviceNumber = trim((string)($invoice['service_id'] ?? ''));
}

$accountNumber = trim((string)($invoice['account_number'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice <?= $e($invoice['invoice_no'] ?? '') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        :root {
            --ink: #0f172a;
            --muted: #64748b;
            --line: #e2e8f0;
            --soft: #f8fafc;
            --blue: #2563eb;
            --green: #16a34a;
            --red: #dc2626;
            --amber: #d97706;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #e5e7eb;
            color: var(--ink);
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
            line-height: 1.45;
        }

        .print-actions {
            max-width: 920px;
            margin: 20px auto 0;
            padding: 0 16px;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        .btn {
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #0f172a;
            border-radius: 10px;
            padding: 10px 14px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }

        .btn-primary {
            background: var(--blue);
            border-color: var(--blue);
            color: #ffffff;
        }

        .print-shell {
            max-width: 920px;
            margin: 24px auto;
            padding: 0 16px;
        }

        .document {
            background: #ffffff;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 18px 50px rgba(15, 23, 42, 0.14);
            border: 1px solid rgba(226, 232, 240, 0.9);
        }

        .doc-topline {
            height: 7px;
            background: linear-gradient(90deg, #2563eb, #16a34a);
        }

        .doc-body {
            padding: 36px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            gap: 28px;
            border-bottom: 1px solid var(--line);
            padding-bottom: 24px;
        }

        .brand {
            display: flex;
            gap: 16px;
            align-items: flex-start;
            min-width: 0;
        }

        .logo-box {
            width: 82px;
            height: 82px;
            border-radius: 18px;
            border: 1px solid var(--line);
            background: var(--soft);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex: 0 0 auto;
            color: var(--muted);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            text-align: center;
            padding: 8px;
        }

        .logo-box img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .company-name {
            margin: 0;
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.02em;
        }

        .company-tagline {
            margin-top: 3px;
            color: var(--blue);
            font-weight: 700;
        }

        .company-meta {
            margin-top: 10px;
            color: var(--muted);
            max-width: 430px;
        }

        .doc-title {
            text-align: right;
            min-width: 240px;
        }

        .doc-title h1 {
            margin: 0;
            font-size: 34px;
            letter-spacing: 0.08em;
            font-weight: 900;
        }

        .doc-number {
            margin-top: 4px;
            color: var(--muted);
            font-weight: 700;
        }

        .badge {
            display: inline-flex;
            margin-top: 12px;
            padding: 7px 12px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 900;
            letter-spacing: 0.04em;
            background: #eff6ff;
            color: #1d4ed8;
        }

        .badge.PAID {
            background: #dcfce7;
            color: #15803d;
        }

        .badge.UNPAID,
        .badge.PARTIAL {
            background: #fff7ed;
            color: #c2410c;
        }

        .badge.OVERDUE,
        .badge.CANCELLED {
            background: #fee2e2;
            color: #b91c1c;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 18px;
            margin-top: 24px;
        }

        .box {
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 18px;
            background: #ffffff;
        }

        .box-soft {
            background: var(--soft);
        }

        .label {
            font-size: 11px;
            font-weight: 900;
            letter-spacing: 0.08em;
            color: var(--muted);
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .customer-name {
            font-size: 18px;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .muted {
            color: var(--muted);
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            padding: 6px 0;
            border-bottom: 1px dashed #e5e7eb;
        }

        .info-row:last-child {
            border-bottom: 0;
        }

        .info-row strong {
            text-align: right;
        }

        .items {
            margin-top: 24px;
            border: 1px solid var(--line);
            border-radius: 16px;
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .items th {
            background: var(--soft);
            color: #475569;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            text-align: left;
            padding: 13px 14px;
            border-bottom: 1px solid var(--line);
        }

        .items td {
            padding: 14px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
        }

        .items tr:last-child td {
            border-bottom: 0;
        }

        .text-end {
            text-align: right;
        }

        .summary-wrap {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 24px;
            margin-top: 24px;
            align-items: start;
        }

        .notes-box {
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 18px;
            min-height: 150px;
        }

        .summary-box {
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 18px;
            background: var(--soft);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dashed #dbe3ef;
        }

        .summary-row:last-child {
            border-bottom: 0;
        }

        .summary-total {
            font-size: 18px;
            font-weight: 900;
            color: var(--ink);
        }

        .balance {
            color: var(--red);
        }

        .payments-box {
            margin-top: 24px;
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 18px;
            background: #ffffff;
        }

        .payment-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 9px 0;
            border-bottom: 1px dashed #e5e7eb;
        }

        .payment-row:last-child {
            border-bottom: 0;
        }

        .footer {
            margin-top: 28px;
            padding-top: 18px;
            border-top: 1px solid var(--line);
            color: var(--muted);
            display: flex;
            justify-content: space-between;
            gap: 20px;
            font-size: 12px;
        }

        @media print {
            body {
                background: #ffffff;
            }

            .print-actions {
                display: none !important;
            }

            .print-shell {
                margin: 0;
                max-width: none;
                padding: 0;
            }

            .document {
                box-shadow: none;
                border: 0;
                border-radius: 0;
            }

            .doc-body {
                padding: 24px;
            }

            @page {
                size: A4;
                margin: 12mm;
            }
        }

        @media (max-width: 768px) {
            .header,
            .footer {
                flex-direction: column;
            }

            .doc-title {
                text-align: left;
            }

            .grid-2,
            .summary-wrap {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="print-actions">
    <a href="/subscriber-portal#invoices" class="btn">Back to Portal</a>
    <button type="button" class="btn btn-primary" onclick="window.print()">Print Invoice</button>
</div>

<div class="print-shell">
    <div class="document">
        <div class="doc-topline"></div>

        <div class="doc-body">
            <div class="header">
                <div class="brand">
                    <div class="logo-box">
                        <?php if ($logoUrl !== ''): ?>
                            <img src="<?= $e($logoUrl) ?>" alt="Company Logo">
                        <?php else: ?>
                            Logo<br>Ready
                        <?php endif; ?>
                    </div>

                    <div>
                        <h2 class="company-name"><?= $e($company['name'] ?? 'NexusBox ISP') ?></h2>
                        <div class="company-tagline"><?= $e($company['tagline'] ?? 'Internet Service Provider') ?></div>
                        <div class="company-meta">
                            <?= $e($company['address'] ?? '') ?><br>
                            <?= $e($company['contact'] ?? '') ?>
                            <?php if (!empty($company['tin'])): ?>
                                <br>TIN: <?= $e($company['tin']) ?>
                            <?php endif; ?>
                            <?php if (!empty($company['website'])): ?>
                                <br><?= $e($company['website']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="doc-title">
                    <h1>INVOICE</h1>
                    <div class="doc-number"><?= $e($invoice['invoice_no'] ?? ('#' . ($invoice['id'] ?? ''))) ?></div>
                    <div class="badge <?= $e($status) ?>"><?= $e($status) ?></div>
                </div>
            </div>

            <div class="grid-2">
                <div class="box">
                    <div class="label">Bill To</div>
                    <div class="customer-name"><?= $e($invoice['subscriber_name'] ?? '—') ?></div>
                    <div class="muted">
                        Account No: <?= $e($accountNumber !== '' ? $accountNumber : '—') ?><br>
                        Service No: <?= $e($serviceNumber !== '' ? $serviceNumber : '—') ?><br>
                        <?= $e($invoice['address'] ?? '') ?><br>
                        <?= $e($invoice['contact_number'] ?? '') ?>
                        <?php if (!empty($invoice['email'])): ?>
                            <br><?= $e($invoice['email']) ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="box box-soft">
                    <div class="label">Invoice Information</div>

                    <div class="info-row">
                        <span>Issue Date</span>
                        <strong><?= $e($date($invoice['issue_date'] ?? null)) ?></strong>
                    </div>

                    <div class="info-row">
                        <span>Due Date</span>
                        <strong><?= $e($date($invoice['due_date'] ?? null)) ?></strong>
                    </div>

                    <div class="info-row">
                        <span>Billing Period</span>
                        <strong>
                            <?= $e($date($invoice['billing_period_start'] ?? null)) ?>
                            -
                            <?= $e($date($invoice['billing_period_end'] ?? null)) ?>
                        </strong>
                    </div>

                    <div class="info-row">
                        <span>Plan</span>
                        <strong><?= $e($invoice['plan_name'] ?? '—') ?></strong>
                    </div>
                </div>
            </div>

            <div class="items">
                <table>
                    <thead>
                    <tr>
                        <th>Description</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Unit Price</th>
                        <th class="text-end">Line Total</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($items)): ?>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <strong><?= $e($item['description'] ?? 'Billing Item') ?></strong>
                                    <div class="muted"><?= $e($item['item_type'] ?? '') ?></div>
                                </td>
                                <td class="text-end"><?= $e($item['quantity'] ?? '1') ?></td>
                                <td class="text-end"><?= $e($money($item['unit_price'] ?? 0)) ?></td>
                                <td class="text-end"><strong><?= $e($money($item['line_total'] ?? 0)) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-end muted">No invoice items.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="summary-wrap">
                <div class="notes-box">
                    <div class="label">Notes / Payment Instructions</div>
                    <div class="muted">
                        <?php if (!empty($invoice['notes'])): ?>
                            <?= nl2br($e($invoice['notes'])) ?>
                        <?php else: ?>
                            Please settle your invoice on or before the due date. Keep this invoice for your records.
                        <?php endif; ?>
                    </div>
                </div>

                <div class="summary-box">
                    <div class="summary-row">
                        <span>Subtotal</span>
                        <strong><?= $e($money($invoice['subtotal'] ?? 0)) ?></strong>
                    </div>

                    <div class="summary-row">
                        <span>Discount</span>
                        <strong><?= $e($money($invoice['discount_amount'] ?? 0)) ?></strong>
                    </div>

                    <div class="summary-row">
                        <span>Tax</span>
                        <strong><?= $e($money($invoice['tax_amount'] ?? 0)) ?></strong>
                    </div>

                    <div class="summary-row summary-total">
                        <span>Total</span>
                        <strong><?= $e($money($invoice['total_amount'] ?? 0)) ?></strong>
                    </div>

                    <div class="summary-row">
                        <span>Paid</span>
                        <strong><?= $e($money($invoice['paid_amount'] ?? 0)) ?></strong>
                    </div>

                    <div class="summary-row summary-total balance">
                        <span>Balance</span>
                        <strong><?= $e($money($invoice['balance_amount'] ?? 0)) ?></strong>
                    </div>
                </div>
            </div>

            <div class="payments-box">
                <div class="label">Payment History</div>

                <?php if (!empty($payments)): ?>
                    <?php foreach ($payments as $payment): ?>
                        <div class="payment-row">
                            <div>
                                <strong><?= $e($payment['payment_no'] ?? ('#' . ($payment['id'] ?? ''))) ?></strong>
                                <div class="muted">
                                    <?= $e($payment['method'] ?? '—') ?>
                                    ·
                                    <?= $e($dateTime($payment['payment_date'] ?? null)) ?>
                                </div>
                            </div>
                            <div class="text-end">
                                <strong><?= $e($money($payment['allocated_amount'] ?? $payment['amount'] ?? 0)) ?></strong>
                                <div class="muted"><?= $e($payment['payment_status'] ?? '—') ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="muted">No payments recorded.</div>
                <?php endif; ?>
            </div>

            <div class="footer">
                <div>
                    Generated by NexusBox · <?= $e(date('M d, Y h:i A')) ?> · Asia/Manila
                </div>
                <div>
                    This is a system-generated invoice.
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>