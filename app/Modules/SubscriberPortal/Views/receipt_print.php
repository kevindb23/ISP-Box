<?php
$money = static function ($value): string {
    return '₱' . number_format((float)$value, 2);
};

$date = static function ($value): string {
    if (!$value) {
        return '-';
    }

    return date('M d, Y h:i A', strtotime((string)$value));
};

$receiptNo = $receipt['payment_no'] ?? '-';
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payment Receipt <?= htmlspecialchars($receiptNo) ?></title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f7fb;
            color: #111827;
            margin: 0;
            padding: 32px;
        }

        .receipt {
            max-width: 820px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 14px;
            padding: 34px;
            box-shadow: 0 12px 35px rgba(15, 23, 42, 0.08);
        }

        .top {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 22px;
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

        .receipt-title {
            text-align: right;
        }

        .receipt-title h2 {
            margin: 0;
            font-size: 26px;
            color: #2563eb;
        }

        .receipt-title p {
            margin: 6px 0 0;
            font-size: 13px;
            color: #6b7280;
        }

        .status {
            display: inline-block;
            margin-top: 10px;
            padding: 6px 12px;
            border-radius: 999px;
            background: #dcfce7;
            color: #166534;
            font-weight: bold;
            font-size: 12px;
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
            border-radius: 10px;
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
            border-radius: 14px;
            background: #eff6ff;
            padding: 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .amount-box span {
            color: #1e3a8a;
            font-weight: bold;
        }

        .amount-box strong {
            font-size: 30px;
            color: #1d4ed8;
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
            border-radius: 10px;
            padding: 10px 16px;
            cursor: pointer;
            font-weight: 600;
        }

        .btn-print {
            background: #2563eb;
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
    </style>
</head>

<body>
<div class="receipt">
    <div class="top">
        <div class="company">
            <h1><?= htmlspecialchars($company['name'] ?? 'NexusBox ISP') ?></h1>
            <p><?= htmlspecialchars($company['tagline'] ?? 'Internet Service Provider') ?></p>
            <p><?= htmlspecialchars($company['address'] ?? '') ?></p>
            <p><?= htmlspecialchars($company['contact'] ?? '') ?></p>
            <?php if (!empty($company['tin'])): ?>
                <p>TIN: <?= htmlspecialchars($company['tin']) ?></p>
            <?php endif; ?>
        </div>

        <div class="receipt-title">
            <h2>Payment Receipt</h2>
            <p><?= htmlspecialchars($receiptNo) ?></p>
            <div class="status"><?= htmlspecialchars($receipt['payment_status'] ?? 'POSTED') ?></div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Subscriber</div>
        <div class="grid">
            <div class="field">
                <label>Account Number</label>
                <div><?= htmlspecialchars($receipt['account_number'] ?? '-') ?></div>
            </div>

            <div class="field">
                <label>Subscriber Name</label>
                <div><?= htmlspecialchars($receipt['full_name'] ?? '-') ?></div>
            </div>

            <div class="field">
                <label>Email</label>
                <div><?= htmlspecialchars($receipt['email'] ?? '-') ?></div>
            </div>

            <div class="field">
                <label>Contact Number</label>
                <div><?= htmlspecialchars($receipt['contact_number'] ?? '-') ?></div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Payment Details</div>
        <div class="grid">
            <div class="field">
                <label>Invoice Number</label>
                <div><?= htmlspecialchars($receipt['invoice_no'] ?? '-') ?></div>
            </div>

            <div class="field">
                <label>Payment Date</label>
                <div><?= htmlspecialchars($date($receipt['payment_date'] ?? null)) ?></div>
            </div>

            <div class="field">
                <label>Payment Method</label>
                <div><?= htmlspecialchars($receipt['method'] ?? '-') ?></div>
            </div>

            <div class="field">
                <label>Reference Number</label>
                <div><?= htmlspecialchars($receipt['reference_no'] ?? '-') ?></div>
            </div>

            <div class="field">
                <label>Billing Period</label>
                <div>
                    <?= htmlspecialchars($receipt['billing_period_start'] ?? '-') ?>
                    to
                    <?= htmlspecialchars($receipt['billing_period_end'] ?? '-') ?>
                </div>
            </div>

            <div class="field">
                <label>Plan</label>
                <div><?= htmlspecialchars($receipt['plan_name'] ?? '-') ?></div>
            </div>
        </div>
    </div>

    <div class="amount-box">
        <span>Amount Paid</span>
        <strong><?= htmlspecialchars($money($receipt['amount'] ?? 0)) ?></strong>
    </div>

    <div class="footer">
        This is a system-generated receipt. No signature is required.
    </div>
</div>

<div class="actions">
    <button class="btn btn-print" onclick="window.print()">Print Receipt</button>
</div>
</body>
</html>