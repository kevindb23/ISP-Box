<?php

require_once BASE_PATH . '/app/Core/Branding/branding.php';

$branding = \App\Core\Branding::get();

$company = $company ?? [];
$receipt = $receipt ?? [];

$money = static function ($value): string {
    return '₱' . number_format((float)$value, 2);
};

$date = static function ($value): string {
    if (!$value) {
        return '-';
    }

    $ts = strtotime((string)$value);
    return $ts ? date('M d, Y h:i A', $ts) : '-';
};

$e = static function ($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
};

$receiptNo = $receipt['payment_no'] ?? '-';

$companyName = $branding['company_name']
        ?? $branding['client_name']
        ?? $company['name']
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

$status = strtoupper((string)($receipt['payment_status'] ?? 'POSTED'));

?>

<!doctype html>
<html lang="en">
<head>
    <?php require BASE_PATH . '/app/UI/Views/layouts/vite.php'; ?>
    <meta charset="utf-8">
    <title>Payment Receipt <?= $e($receiptNo) ?></title>

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
            font-family: Arial, sans-serif;
            background: #f5f7fb;
            color: var(--ink);
            margin: 0;
            padding: 32px;
        }

        .receipt {
            max-width: 820px;
            margin: 0 auto;
            background: #ffffff;
            border-radius:3px;
            padding: 34px;
            box-shadow: 0 12px 35px rgba(15, 23, 42, 0.08);
            border-top: 7px solid var(--blue);
        }

        .top {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 22px;
        }

        .brand {
            display: flex;
            gap: 14px;
            align-items: flex-start;
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

        .company h1 {
            margin: 0;
            font-size: 24px;
        }

        .company p {
            margin: 4px 0;
            color: #6b7280;
            font-size: 13px;
        }

        .company .tagline {
            color: var(--blue);
            font-weight: 700;
        }

        .receipt-title {
            text-align: right;
        }

        .receipt-title h2 {
            margin: 0;
            font-size: 26px;
            color: var(--blue);
        }

        .receipt-title p {
            margin: 6px 0 0;
            font-size: 13px;
            color: #6b7280;
            font-weight: 700;
        }

        .status {
            display: inline-block;
            margin-top: 10px;
            padding: 6px 12px;
            border-radius:3px;
            background: #dcfce7;
            color: #166534;
            font-weight: bold;
            font-size: 12px;
        }

        .status.PENDING {
            background: #fff7ed;
            color: #c2410c;
        }

        .status.FAILED,
        .status.VOIDED,
        .status.REFUNDED {
            background: #fee2e2;
            color: #b91c1c;
        }

        .section {
            margin-top: 26px;
        }

        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #374151;
            text-transform: uppercase;
            letter-spacing: .04em;
            margin-bottom: 10px;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px 24px;
        }

        .field {
            border: 1px solid #e5e7eb;
            border-radius:3px;
            padding: 12px 14px;
            background: #fafafa;
        }

        .field label {
            display: block;
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 4px;
        }

        .field div {
            font-weight: 600;
            font-size: 14px;
        }

        .amount-box {
            margin-top: 26px;
            border-radius:3px;
            background: #eff6ff;
            padding: 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid #dbeafe;
        }

        .amount-box span {
            color: #1e3a8a;
            font-weight: bold;
        }

        .amount-box strong {
            font-size: 30px;
            color: var(--blue);
        }

        .footer {
            margin-top: 34px;
            padding-top: 18px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 12px;
            text-align: center;
        }

        .actions {
            max-width: 820px;
            margin: 16px auto 0;
            text-align: right;
        }

        .btn {
            border: 0;
            border-radius:3px;
            padding: 10px 16px;
            cursor: pointer;
            font-weight: 600;
        }

        .btn-print {
            background: var(--blue);
            color: #fff;
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }

            .receipt {
                box-shadow: none;
                border-radius: 0;
            }

            .actions {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .top,
            .amount-box {
                flex-direction: column;
                align-items: flex-start;
            }

            .receipt-title {
                text-align: left;
            }

            .grid {
                grid-template-columns: 1fr;
            }
        }

    </style>
</head>

<body>

<div class="receipt">
    <div class="top">
        <div class="brand">
            <div class="logo-box">
                <?php if ($logoUrl !== ''): ?>
                    <img src="<?= $e($logoUrl) ?>" alt="Company Logo">
                <?php else: ?>
                    Logo<br>Ready
                <?php endif; ?>
            </div>

            <div class="company">
                <h1><?= $e($companyName) ?></h1>
                <p class="tagline"><?= $e($companyTagline) ?></p>

                <?php if ($companyAddress !== ''): ?>
                    <p><?= $e($companyAddress) ?></p>
                <?php endif; ?>

                <?php if ($companyContact !== ''): ?>
                    <p><?= $e($companyContact) ?></p>
                <?php endif; ?>

                <?php if ($companyTin !== ''): ?>
                    <p>TIN: <?= $e($companyTin) ?></p>
                <?php endif; ?>

                <?php if ($companyWebsite !== ''): ?>
                    <p><?= $e($companyWebsite) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="receipt-title">
            <h2>Payment Receipt</h2>
            <p><?= $e($receiptNo) ?></p>
            <div class="status <?= $e($status) ?>"><?= $e($status) ?></div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Subscriber</div>
        <div class="grid">
            <div class="field">
                <label>Account Number</label>
                <div><?= $e($receipt['account_number'] ?? '-') ?></div>
            </div>

            <div class="field">
                <label>Subscriber Name</label>
                <div><?= $e($receipt['full_name'] ?? '-') ?></div>
            </div>

            <div class="field">
                <label>Email</label>
                <div><?= $e($receipt['email'] ?? '-') ?></div>
            </div>

            <div class="field">
                <label>Contact Number</label>
                <div><?= $e($receipt['contact_number'] ?? '-') ?></div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Payment Details</div>
        <div class="grid">
            <div class="field">
                <label>Invoice Number</label>
                <div><?= $e($receipt['invoice_no'] ?? '-') ?></div>
            </div>

            <div class="field">
                <label>Payment Date</label>
                <div><?= $e($date($receipt['payment_date'] ?? null)) ?></div>
            </div>

            <div class="field">
                <label>Payment Method</label>
                <div><?= $e($receipt['method'] ?? '-') ?></div>
            </div>

            <div class="field">
                <label>Reference Number</label>
                <div><?= $e($receipt['reference_no'] ?? '-') ?></div>
            </div>

            <div class="field">
                <label>Billing Period</label>
                <div>
                    <?= $e($receipt['billing_period_start'] ?? '-') ?>
                    to
                    <?= $e($receipt['billing_period_end'] ?? '-') ?>
                </div>
            </div>

            <div class="field">
                <label>Plan</label>
                <div><?= $e($receipt['plan_name'] ?? '-') ?></div>
            </div>
        </div>
    </div>

    <div class="amount-box">
        <span>Amount Paid</span>
        <strong><?= $e($money($receipt['amount'] ?? 0)) ?></strong>
    </div>

    <div class="footer">
        Generated by <?= $e($companyName) ?>. This is a system-generated receipt. No signature is required.
    </div>
</div>

<div class="actions">
    <button class="btn btn-print" onclick="window.print()">Print Receipt</button>
</div>

</body>
</html>
