<?php

require_once BASE_PATH . '/app/Core/Branding/branding.php';

$branding = \App\Core\Branding::get();

$company = $company ?? [];
$payment = $payment ?? [];
$invoice = $invoice ?? [];
$items = $items ?? [];

$money = static function ($value): string {
    return '₱' . number_format((float)($value ?? 0), 2);
};

$dateTime = static function ($value): string {
    if (!$value) return '—';
    $ts = strtotime((string)$value);
    return $ts ? date('M d, Y h:i A', $ts) : (string)$value;
};

$date = static function ($value): string {
    if (!$value) return '—';
    $ts = strtotime((string)$value);
    return $ts ? date('M d, Y', $ts) : (string)$value;
};

$e = static function ($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
};

$status = strtoupper((string)($payment['payment_status'] ?? 'POSTED'));

$companyName = $branding['company_name']
        ?? $branding['client_name']
        ?? $company['name']
        ?? $company['company_name']
        ?? 'NexusBox ISP';

$companyTagline = $branding['portal_title']
        ?? $company['tagline']
        ?? 'Internet Service Provider';

$companyAddress = $branding['company_address']
        ?? $company['address']
        ?? '';

$companyContact = trim(
        (string)($branding['support_email'] ?? '') .
        (
        !empty($branding['support_email']) && !empty($branding['support_phone'])
                ? ' / '
                : ''
        ) .
        (string)($branding['support_phone'] ?? '')
);

if ($companyContact === '') {
    $companyContact = $company['contact'] ?? '';
}

$companyTin = $branding['tin']
        ?? $company['tin']
        ?? '';

$companyWebsite = $branding['website']
        ?? $company['website']
        ?? '';

$logoUrl = trim((string)(
        $branding['logo_path']
        ?? $company['logo_url']
        ?? $company['logo_path']
        ?? ''
));

$primaryColor = trim((string)($branding['primary_color'] ?? '#2563eb'));

if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $primaryColor)) {
    $primaryColor = '#2563eb';
}

$subscriberName = $payment['subscriber_name'] ?? ($invoice['subscriber_name'] ?? '—');

$accountNumber = trim((string)($payment['account_number'] ?? ($invoice['account_number'] ?? '')));
$serviceNumber = trim((string)($payment['service_number'] ?? ($invoice['service_number'] ?? '')));

if ($serviceNumber === '') {
    $serviceNumber = trim((string)($payment['service_id'] ?? ($invoice['service_id'] ?? '')));
}

$invoiceNo = $payment['invoice_no'] ?? ($invoice['invoice_no'] ?? '—');
$invoiceStatus = $payment['invoice_status'] ?? ($invoice['status'] ?? '—');

$invoiceTotal = $payment['total_amount'] ?? ($invoice['total_amount'] ?? 0);
$totalPaid = $payment['paid_amount'] ?? ($invoice['paid_amount'] ?? 0);
$remainingBalance = $payment['balance_amount'] ?? ($invoice['balance_amount'] ?? 0);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require BASE_PATH . '/app/UI/Views/layouts/vite.php'; ?>
    <meta charset="UTF-8">
    <title>Receipt <?= $e($payment['payment_no'] ?? '') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        :root {
            --ink: #0f172a;
            --muted: #64748b;
            --line: #e2e8f0;
            --soft: #f8fafc;
            --blue: <?= $e($primaryColor) ?>;
            --green: #16a34a;
            --red: #dc2626;
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
            max-width: 760px;
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
            border-radius:3px;
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
            max-width: 760px;
            margin: 24px auto;
            padding: 0 16px;
        }

        .document {
            background: #ffffff;
            border-radius:3px;
            overflow: hidden;
            box-shadow: 0 18px 50px rgba(15, 23, 42, 0.14);
            border: 1px solid rgba(226, 232, 240, 0.9);
        }

        .doc-topline {
            height: 7px;
            background: var(--blue);
        }

        .doc-body {
            padding: 34px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            border-bottom: 1px solid var(--line);
            padding-bottom: 22px;
        }

        .brand {
            display: flex;
            gap: 14px;
            align-items: flex-start;
            min-width: 0;
        }

        .logo-box {
            width: 76px;
            height: 76px;
            border-radius:3px;
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
            font-size: 21px;
            font-weight: 800;
        }

        .company-tagline {
            margin-top: 3px;
            color: var(--blue);
            font-weight: 700;
        }

        .company-meta {
            margin-top: 10px;
            color: var(--muted);
            max-width: 380px;
        }

        .doc-title {
            text-align: right;
            min-width: 220px;
        }

        .doc-title h1 {
            margin: 0;
            font-size: 28px;
            letter-spacing: 0.06em;
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
            border-radius:3px;
            font-size: 11px;
            font-weight: 900;
            letter-spacing: 0.04em;
            background: #dcfce7;
            color: #15803d;
        }

        .badge.PENDING {
            background: #fff7ed;
            color: #c2410c;
        }

        .badge.FAILED,
        .badge.VOIDED,
        .badge.REFUNDED {
            background: #fee2e2;
            color: #b91c1c;
        }

        .receipt-paid-box {
            margin-top: 24px;
            padding: 24px;
            border-radius:3px;
            background: linear-gradient(135deg, #ecfdf5, #eff6ff);
            border: 1px solid #bbf7d0;
            display: flex;
            justify-content: space-between;
            gap: 20px;
            align-items: center;
        }

        .paid-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--muted);
            font-weight: 900;
        }

        .paid-amount {
            font-size: 34px;
            font-weight: 900;
            color: var(--green);
            line-height: 1.1;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            margin-top: 24px;
        }

        .box {
            border: 1px solid var(--line);
            border-radius:3px;
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
            font-size: 17px;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .muted {
            color: var(--muted);
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 7px 0;
            border-bottom: 1px dashed #e5e7eb;
        }

        .info-row:last-child {
            border-bottom: 0;
        }

        .info-row strong {
            text-align: right;
        }

        .summary-box {
            margin-top: 24px;
            border: 1px solid var(--line);
            border-radius:3px;
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

        .signature {
            margin-top: 34px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
        }

        .sig-line {
            border-top: 1px solid #94a3b8;
            padding-top: 8px;
            text-align: center;
            color: var(--muted);
            font-size: 12px;
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
            .receipt-paid-box,
            .footer {
                flex-direction: column;
                align-items: flex-start;
            }

            .doc-title {
                text-align: left;
            }

            .grid-2,
            .signature {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<div class="print-actions">
    <a href="/billing" class="btn">Back to Billing</a>
    <button type="button" class="btn btn-primary" onclick="window.print()">Print Receipt</button>
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
                        <h2 class="company-name"><?= $e($companyName) ?></h2>
                        <div class="company-tagline"><?= $e($companyTagline) ?></div>
                        <div class="company-meta">
                            <?php if ($companyAddress !== ''): ?>
                                <?= $e($companyAddress) ?><br>
                            <?php endif; ?>

                            <?php if ($companyContact !== ''): ?>
                                <?= $e($companyContact) ?>
                            <?php endif; ?>

                            <?php if (!empty($companyTin)): ?>
                                <br>TIN: <?= $e($companyTin) ?>
                            <?php endif; ?>

                            <?php if (!empty($companyWebsite)): ?>
                                <br><?= $e($companyWebsite) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="doc-title">
                    <h1>RECEIPT</h1>
                    <div class="doc-number"><?= $e($payment['payment_no'] ?? ('#' . ($payment['id'] ?? ''))) ?></div>
                    <div class="badge <?= $e($status) ?>"><?= $e($status) ?></div>
                </div>
            </div>

            <div class="receipt-paid-box">
                <div>
                    <div class="paid-label">Amount Received</div>
                    <div class="paid-amount"><?= $e($money($payment['amount'] ?? 0)) ?></div>
                </div>

                <div class="muted">
                    Payment Date:<br>
                    <strong><?= $e($dateTime($payment['payment_date'] ?? null)) ?></strong>
                </div>
            </div>

            <div class="grid-2">
                <div class="box">
                    <div class="label">Received From</div>
                    <div class="customer-name"><?= $e($subscriberName) ?></div>
                    <div class="muted">
                        Account No: <?= $e($accountNumber !== '' ? $accountNumber : '—') ?><br>
                        Service No: <?= $e($serviceNumber !== '' ? $serviceNumber : '—') ?><br>
                        <?= $e($payment['address'] ?? ($invoice['address'] ?? '')) ?><br>
                        <?= $e($payment['contact_number'] ?? ($invoice['contact_number'] ?? '')) ?>
                    </div>
                </div>

                <div class="box box-soft">
                    <div class="label">Payment Information</div>

                    <div class="info-row">
                        <span>Invoice No.</span>
                        <strong><?= $e($invoiceNo) ?></strong>
                    </div>

                    <div class="info-row">
                        <span>Payment Method</span>
                        <strong><?= $e($payment['method'] ?? '—') ?></strong>
                    </div>

                    <div class="info-row">
                        <span>Reference No.</span>
                        <strong><?= $e($payment['reference_no'] ?? '—') ?></strong>
                    </div>

                    <div class="info-row">
                        <span>Received By</span>
                        <strong><?= $e($payment['received_by'] ?? 'System') ?></strong>
                    </div>
                </div>
            </div>

            <div class="summary-box">
                <div class="label">Invoice Balance Summary</div>

                <div class="summary-row">
                    <span>Invoice Total</span>
                    <strong><?= $e($money($invoiceTotal)) ?></strong>
                </div>

                <div class="summary-row">
                    <span>Total Paid</span>
                    <strong><?= $e($money($totalPaid)) ?></strong>
                </div>

                <div class="summary-row">
                    <span>Remaining Balance</span>
                    <strong><?= $e($money($remainingBalance)) ?></strong>
                </div>

                <div class="summary-row">
                    <span>Invoice Status</span>
                    <strong><?= $e($invoiceStatus) ?></strong>
                </div>
            </div>

            <?php if (!empty($payment['remarks'])): ?>
                <div class="summary-box">
                    <div class="label">Remarks</div>
                    <div class="muted"><?= nl2br($e($payment['remarks'])) ?></div>
                </div>
            <?php endif; ?>

            <div class="signature">
                <div class="sig-line">Customer Signature</div>
                <div class="sig-line">Authorized Representative</div>
            </div>

            <div class="footer">
                <div>
                    Generated by <?= $e($companyName) ?> · <?= $e(date('M d, Y h:i A')) ?> · Asia/Manila
                </div>
                <div>
                    This is a system-generated payment receipt.
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
