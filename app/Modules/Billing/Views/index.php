<div id="billingPage" class="container-fluid nx-page billing-page">
    <link rel="stylesheet" href="/module-assets/Billing/css/billing.css?v=<?= time() ?>">

    <style>
        #billingPage .billing-panel {
            display: none;
        }

        #billingPage .billing-panel.active {
            display: block;
        }
    </style>

    <!-- =========================================================
         PAGE HEADER
    ========================================================== -->
    <div class="card border-0 shadow-sm nx-page-header-card billing-hero mb-3">
        <div class="card-body nx-page-header">
            <div class="nx-page-header-left">
                <div class="billing-eyebrow">
                    <i class="bi bi-receipt-cutoff"></i>
                    <span>ISP Billing Center</span>
                </div>

                <h5 class="nx-page-title">Billing</h5>

                <div class="nx-page-subtitle">
                    Manage invoices, payments, adjustments, balances, collections aging, billing runs, and billing settings.
                </div>

                <div class="billing-ph-note">
                    <i class="bi bi-geo-alt-fill"></i>
                    <span>Philippines billing standard · PHP · Asia/Manila</span>
                </div>
            </div>

            <div class="nx-page-actions billing-hero-actions">
                <button type="button" class="btn btn-light border nx-header-btn" id="btnBillingRefresh">
                    <i class="bi bi-arrow-clockwise"></i>
                    <span>Refresh</span>
                </button>

                <button type="button" class="btn btn-outline-primary nx-header-btn" id="btnGenerateDueInvoices">
                    <i class="bi bi-calendar2-check"></i>
                    <span>Generate Due Invoices</span>
                </button>

                <button type="button" class="btn btn-primary nx-header-btn" id="btnOpenCreateInvoice">
                    <i class="bi bi-plus-lg"></i>
                    <span>New Invoice</span>
                </button>

                <button type="button" class="btn btn-success nx-header-btn" id="btnOpenRecordPayment">
                    <i class="bi bi-cash-coin"></i>
                    <span>Record Payment</span>
                </button>

                <button type="button" class="btn btn-warning nx-header-btn" id="btnOpenCreateAdjustment">
                    <i class="bi bi-sliders"></i>
                    <span>Adjustment</span>
                </button>
            </div>
        </div>
    </div>

    <!-- =========================================================
         STATS
    ========================================================== -->
    <div class="row g-3 mb-3 billing-stats-grid">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="billing-stat-card billing-stat-primary">
                <div class="billing-stat-icon">
                    <i class="bi bi-file-earmark-text"></i>
                </div>
                <div>
                    <div class="billing-stat-label">Total Billed</div>
                    <div class="billing-stat-value" id="statTotalBilled">₱0.00</div>
                    <div class="billing-stat-help">All non-cancelled invoices</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="billing-stat-card billing-stat-success">
                <div class="billing-stat-icon">
                    <i class="bi bi-wallet2"></i>
                </div>
                <div>
                    <div class="billing-stat-label">Collected</div>
                    <div class="billing-stat-value" id="statCollected">₱0.00</div>
                    <div class="billing-stat-help">Posted payments</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="billing-stat-card billing-stat-warning">
                <div class="billing-stat-icon">
                    <i class="bi bi-exclamation-circle"></i>
                </div>
                <div>
                    <div class="billing-stat-label">Outstanding</div>
                    <div class="billing-stat-value" id="statBalance">₱0.00</div>
                    <div class="billing-stat-help">Remaining invoice balance</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="billing-stat-card billing-stat-danger">
                <div class="billing-stat-icon">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div>
                    <div class="billing-stat-label">Overdue</div>
                    <div class="billing-stat-value" id="statOverdue">0</div>
                    <div class="billing-stat-help">Invoices past due</div>
                </div>
            </div>
        </div>
    </div>

    <!-- =========================================================
         TABS / SEARCH
    ========================================================== -->
    <div class="card border-0 shadow-sm nx-toolbar-card billing-tabs-card mb-3">
        <div class="card-body">
            <div class="billing-tabs-wrap">
                <div class="nx-segment-control billing-tabs" role="tablist">
                    <button type="button" class="nx-segment-item active" data-billing-tab="overview">
                        <i class="bi bi-grid-1x2"></i>
                        <span>Overview</span>
                    </button>

                    <button type="button" class="nx-segment-item" data-billing-tab="invoices">
                        <i class="bi bi-receipt"></i>
                        <span>Invoices</span>
                    </button>

                    <button type="button" class="nx-segment-item" data-billing-tab="payments">
                        <i class="bi bi-cash-stack"></i>
                        <span>Payments</span>
                    </button>

                    <button type="button" class="nx-segment-item" data-billing-tab="adjustments">
                        <i class="bi bi-sliders"></i>
                        <span>Adjustments</span>
                    </button>

                    <button type="button" class="nx-segment-item" data-billing-tab="collections">
                        <i class="bi bi-hourglass-split"></i>
                        <span>Collections</span>
                    </button>

                    <button type="button" class="nx-segment-item" data-billing-tab="runs">
                        <i class="bi bi-clock-history"></i>
                        <span>Billing Runs</span>
                    </button>

                    <button type="button" class="nx-segment-item" data-billing-tab="settings">
                        <i class="bi bi-gear"></i>
                        <span>Settings</span>
                    </button>
                </div>

                <div class="billing-tab-tools">
                    <div class="billing-search-box">
                        <i class="bi bi-search billing-search-box-icon"></i>
                        <input type="text"
                               class="form-control billing-search-control"
                               id="billingSearch"
                               placeholder="Search invoice, subscriber, payment, adjustment...">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- =========================================================
         OVERVIEW PANEL
    ========================================================== -->
    <section class="billing-panel active" data-billing-panel="overview">
        <div class="card border-0 shadow-sm nx-content-card billing-card billing-overview-card mb-3">
            <div class="billing-card-header">
                <div>
                    <h6 class="mb-0 fw-semibold">Billing Workspace</h6>
                    <small class="text-muted">Quick actions for daily billing operations</small>
                </div>

                <div class="billing-panel-actions">
                    <button type="button"
                            class="btn btn-sm btn-outline-primary"
                            data-billing-quick-action="collections">
                        <i class="bi bi-hourglass-split me-1"></i>
                        Collections
                    </button>

                    <button type="button"
                            class="btn btn-sm btn-outline-primary"
                            data-billing-quick-action="runs">
                        <i class="bi bi-clock-history me-1"></i>
                        Billing Runs
                    </button>
                </div>
            </div>

            <div class="billing-workspace">
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <button type="button"
                                class="billing-action-tile"
                                id="btnWorkspaceCreateInvoice"
                                data-billing-quick-action="create-invoice">
                            <div class="billing-action-icon bg-primary-subtle text-primary">
                                <i class="bi bi-receipt"></i>
                            </div>
                            <div>
                                <div class="billing-action-title">Create Invoice</div>
                                <div class="billing-action-text">Generate subscriber billing</div>
                            </div>
                        </button>
                    </div>

                    <div class="col-12 col-md-4">
                        <button type="button"
                                class="billing-action-tile"
                                id="btnWorkspaceRecordPayment"
                                data-billing-quick-action="record-payment">
                            <div class="billing-action-icon bg-success-subtle text-success">
                                <i class="bi bi-cash-coin"></i>
                            </div>
                            <div>
                                <div class="billing-action-title">Record Payment</div>
                                <div class="billing-action-text">Post cash or online payment</div>
                            </div>
                        </button>
                    </div>

                    <div class="col-12 col-md-4">
                        <button type="button"
                                class="billing-action-tile"
                                id="btnWorkspaceCreateAdjustment"
                                data-billing-quick-action="create-adjustment">
                            <div class="billing-action-icon bg-warning-subtle text-warning">
                                <i class="bi bi-sliders"></i>
                            </div>
                            <div>
                                <div class="billing-action-title">Adjustment</div>
                                <div class="billing-action-text">Credit, rebate, or correction</div>
                            </div>
                        </button>
                    </div>
                </div>

                <div class="billing-workspace-note mt-3">
                    <div>
                        <i class="bi bi-shield-check"></i>
                        <span>Recommended workflow: generate invoice → record payment → review adjustments → monitor collections aging.</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-xxl-6">
                <div class="card border-0 shadow-sm nx-content-card billing-card">
                    <div class="billing-card-header">
                        <div>
                            <h6 class="mb-0 fw-semibold">Recent Invoices</h6>
                            <small class="text-muted">Latest billing records</small>
                        </div>
                        <button type="button"
                                class="btn btn-sm btn-light border"
                                id="btnViewAllInvoices"
                                data-billing-quick-action="invoices">
                            View All
                        </button>
                    </div>

                    <div class="table-responsive nx-table-wrap">
                        <table class="table align-middle mb-0 billing-table">
                            <thead>
                            <tr>
                                <th class="ps-4">Invoice</th>
                                <th>Subscriber</th>
                                <th>Amount</th>
                                <th>Balance</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody id="recentInvoicesBody">
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Loading recent invoices...</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xxl-6">
                <div class="card border-0 shadow-sm nx-content-card billing-card">
                    <div class="billing-card-header">
                        <div>
                            <h6 class="mb-0 fw-semibold">Recent Payments</h6>
                            <small class="text-muted">Latest posted collections</small>
                        </div>
                        <button type="button"
                                class="btn btn-sm btn-light border"
                                id="btnViewAllPayments"
                                data-billing-quick-action="payments">
                            View All
                        </button>
                    </div>

                    <div class="billing-payment-feed" id="recentPaymentsBody">
                        <div class="billing-empty-mini">Loading recent payments...</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- =========================================================
         INVOICES PANEL
    ========================================================== -->
    <section class="billing-panel" data-billing-panel="invoices">
        <div class="card border-0 shadow-sm nx-content-card billing-card">
            <div class="billing-card-header">
                <div>
                    <h6 class="mb-0 fw-semibold">Invoices</h6>
                    <small class="text-muted" id="invoiceCountLabel">0 records</small>
                </div>

                <div class="billing-panel-actions">
                    <select class="form-select form-select-sm billing-filter" id="invoiceStatusFilter">
                        <option value="">All Status</option>
                        <option value="UNPAID">Unpaid</option>
                        <option value="PARTIAL">Partial</option>
                        <option value="PAID">Paid</option>
                        <option value="OVERDUE">Overdue</option>
                        <option value="CANCELLED">Cancelled</option>
                    </select>

                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnGenerateDueInvoices2">
                        <i class="bi bi-calendar2-check me-1"></i>
                        Generate Due
                    </button>

                    <button type="button" class="btn btn-sm btn-primary" id="btnOpenCreateInvoice2">
                        <i class="bi bi-plus-lg me-1"></i>
                        Invoice
                    </button>
                </div>
            </div>

            <div class="table-responsive nx-table-wrap">
                <table class="table align-middle mb-0 billing-table">
                    <thead>
                    <tr>
                        <th class="ps-4">Invoice</th>
                        <th>Subscriber</th>
                        <th>Plan / Service</th>
                        <th>Due Date</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Balance</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                    </thead>
                    <tbody id="invoiceTableBody">
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">Loading invoices...</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- =========================================================
         PAYMENTS PANEL
    ========================================================== -->
    <section class="billing-panel" data-billing-panel="payments">
        <div class="card border-0 shadow-sm nx-content-card billing-card">
            <div class="billing-card-header">
                <div>
                    <h6 class="mb-0 fw-semibold">Payments</h6>
                    <small class="text-muted" id="paymentCountLabel">0 records</small>
                </div>

                <div class="billing-panel-actions">
                    <select class="form-select form-select-sm billing-filter" id="paymentStatusFilter">
                        <option value="">All Status</option>
                        <option value="POSTED">Posted</option>
                        <option value="PENDING">Pending</option>
                        <option value="VOIDED">Voided</option>
                        <option value="FAILED">Failed</option>
                        <option value="REFUNDED">Refunded</option>
                    </select>

                    <button type="button" class="btn btn-sm btn-success" id="btnOpenRecordPayment2">
                        <i class="bi bi-plus-lg me-1"></i>
                        Payment
                    </button>
                </div>
            </div>

            <div class="table-responsive nx-table-wrap">
                <table class="table align-middle mb-0 billing-table">
                    <thead>
                    <tr>
                        <th class="ps-4">Payment</th>
                        <th>Invoice</th>
                        <th>Subscriber</th>
                        <th>Method</th>
                        <th>Reference</th>
                        <th>Date</th>
                        <th class="text-end">Amount</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                    </thead>
                    <tbody id="paymentTableBody">
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">Loading payments...</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- =========================================================
         ADJUSTMENTS PANEL
    ========================================================== -->
    <section class="billing-panel" data-billing-panel="adjustments">
        <div class="card border-0 shadow-sm nx-content-card billing-card">
            <div class="billing-card-header">
                <div>
                    <h6 class="mb-0 fw-semibold">Billing Adjustments</h6>
                    <small class="text-muted" id="adjustmentCountLabel">0 records</small>
                </div>

                <div class="billing-panel-actions billing-panel-actions-wide">
                    <select class="form-select form-select-sm billing-filter" id="adjustmentStatusFilter">
                        <option value="">All Status</option>
                        <option value="POSTED">Posted</option>
                        <option value="PENDING">Pending</option>
                        <option value="VOIDED">Voided</option>
                    </select>

                    <select class="form-select form-select-sm billing-filter" id="adjustmentTypeFilter">
                        <option value="">All Type</option>
                        <option value="CREDIT">Credit</option>
                        <option value="DEBIT">Debit</option>
                        <option value="DISCOUNT">Discount</option>
                        <option value="REBATE">Rebate</option>
                        <option value="WAIVER">Waiver</option>
                        <option value="CORRECTION">Correction</option>
                    </select>

                    <button type="button" class="btn btn-sm btn-warning" id="btnOpenCreateAdjustment2">
                        <i class="bi bi-plus-lg me-1"></i>
                        Adjustment
                    </button>
                </div>
            </div>

            <div class="table-responsive nx-table-wrap">
                <table class="table align-middle mb-0 billing-table">
                    <thead>
                    <tr>
                        <th class="ps-4">Adjustment</th>
                        <th>Invoice</th>
                        <th>Subscriber</th>
                        <th>Type</th>
                        <th>Reason</th>
                        <th>Date</th>
                        <th class="text-end">Amount</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                    </thead>
                    <tbody id="adjustmentTableBody">
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">Loading adjustments...</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- =========================================================
         COLLECTIONS PANEL
    ========================================================== -->
    <section class="billing-panel" data-billing-panel="collections">
        <div class="row g-3 mb-3">
            <div class="col-12 col-sm-6 col-xl">
                <button type="button" class="billing-aging-card billing-aging-current" data-collections-bucket="current">
                    <div>
                        <div class="billing-aging-label">Current</div>
                        <div class="billing-aging-amount" id="agingCurrentAmount">₱0.00</div>
                        <div class="billing-aging-count" id="agingCurrentCount">0 invoices</div>
                    </div>
                    <div class="billing-aging-icon">
                        <i class="bi bi-check-circle"></i>
                    </div>
                </button>
            </div>

            <div class="col-12 col-sm-6 col-xl">
                <button type="button" class="billing-aging-card billing-aging-warning" data-collections-bucket="overdue_1_7">
                    <div>
                        <div class="billing-aging-label">1-7 Days</div>
                        <div class="billing-aging-amount" id="aging17Amount">₱0.00</div>
                        <div class="billing-aging-count" id="aging17Count">0 invoices</div>
                    </div>
                    <div class="billing-aging-icon">
                        <i class="bi bi-clock"></i>
                    </div>
                </button>
            </div>

            <div class="col-12 col-sm-6 col-xl">
                <button type="button" class="billing-aging-card billing-aging-orange" data-collections-bucket="overdue_8_30">
                    <div>
                        <div class="billing-aging-label">8-30 Days</div>
                        <div class="billing-aging-amount" id="aging830Amount">₱0.00</div>
                        <div class="billing-aging-count" id="aging830Count">0 invoices</div>
                    </div>
                    <div class="billing-aging-icon">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                </button>
            </div>

            <div class="col-12 col-sm-6 col-xl">
                <button type="button" class="billing-aging-card billing-aging-danger" data-collections-bucket="overdue_31_60">
                    <div>
                        <div class="billing-aging-label">31-60 Days</div>
                        <div class="billing-aging-amount" id="aging3160Amount">₱0.00</div>
                        <div class="billing-aging-count" id="aging3160Count">0 invoices</div>
                    </div>
                    <div class="billing-aging-icon">
                        <i class="bi bi-exclamation-octagon"></i>
                    </div>
                </button>
            </div>

            <div class="col-12 col-sm-6 col-xl">
                <button type="button" class="billing-aging-card billing-aging-critical" data-collections-bucket="overdue_60_plus">
                    <div>
                        <div class="billing-aging-label">60+ Days</div>
                        <div class="billing-aging-amount" id="aging60Amount">₱0.00</div>
                        <div class="billing-aging-count" id="aging60Count">0 invoices</div>
                    </div>
                    <div class="billing-aging-icon">
                        <i class="bi bi-fire"></i>
                    </div>
                </button>
            </div>
        </div>

        <div class="card border-0 shadow-sm nx-content-card billing-card">
            <div class="billing-card-header">
                <div>
                    <h6 class="mb-0 fw-semibold">Collections Aging</h6>
                    <small class="text-muted" id="collectionsCountLabel">0 records</small>
                </div>

                <div class="billing-panel-actions billing-panel-actions-wide">
                    <input type="date" class="form-control form-control-sm billing-filter" id="collectionsAsOfDate">

                    <select class="form-select form-select-sm billing-filter" id="collectionsBucketFilter">
                        <option value="">All Buckets</option>
                        <option value="current">Current</option>
                        <option value="overdue_1_7">1-7 Days</option>
                        <option value="overdue_8_30">8-30 Days</option>
                        <option value="overdue_31_60">31-60 Days</option>
                        <option value="overdue_60_plus">60+ Days</option>
                    </select>

                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnRefreshCollections">
                        <i class="bi bi-arrow-clockwise me-1"></i>
                        Refresh
                    </button>
                </div>
            </div>

            <div class="billing-collections-note">
                <div>
                    <i class="bi bi-info-circle"></i>
                    <span>
                        Collections aging includes invoices with remaining balance only:
                        <strong>UNPAID</strong>, <strong>PARTIAL</strong>, and <strong>OVERDUE</strong>.
                    </span>
                </div>

                <div class="billing-collections-asof">
                    As of: <strong id="collectionsAsOfLabel">—</strong>
                </div>
            </div>

            <div class="table-responsive nx-table-wrap">
                <table class="table align-middle mb-0 billing-table">
                    <thead>
                    <tr>
                        <th class="ps-4">Invoice</th>
                        <th>Subscriber</th>
                        <th>Plan / Service</th>
                        <th>Due Date</th>
                        <th>Aging</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Paid</th>
                        <th class="text-end">Balance</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                    </thead>
                    <tbody id="collectionsTableBody">
                    <tr>
                        <td colspan="10" class="text-center text-muted py-4">Loading collections aging...</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- =========================================================
         BILLING RUNS PANEL
    ========================================================== -->
    <section class="billing-panel" data-billing-panel="runs">
        <div class="card border-0 shadow-sm nx-content-card billing-card">
            <div class="billing-card-header">
                <div>
                    <h6 class="mb-0 fw-semibold">Billing Runs</h6>
                    <small class="text-muted" id="billingRunCountLabel">0 records</small>
                </div>

                <div class="billing-panel-actions billing-panel-actions-wide">
                    <select class="form-select form-select-sm billing-filter" id="billingRunStatusFilter">
                        <option value="">All Status</option>
                        <option value="SUCCESS">Success</option>
                        <option value="PARTIAL">Partial</option>
                        <option value="FAILED">Failed</option>
                    </select>

                    <select class="form-select form-select-sm billing-filter" id="billingRunTypeFilter">
                        <option value="">All Type</option>
                        <option value="MANUAL">Manual</option>
                        <option value="CRON">Cron</option>
                        <option value="SYSTEM">System</option>
                    </select>

                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnGenerateDueInvoices3">
                        <i class="bi bi-calendar2-check me-1"></i>
                        Generate Due
                    </button>
                </div>
            </div>

            <div class="table-responsive nx-table-wrap">
                <table class="table align-middle mb-0 billing-table">
                    <thead>
                    <tr>
                        <th class="ps-4">Run No.</th>
                        <th>Type</th>
                        <th>As Of</th>
                        <th class="text-end">Checked</th>
                        <th class="text-end">Created</th>
                        <th class="text-end">Skipped</th>
                        <th class="text-end">Failed</th>
                        <th>Status</th>
                        <th>Started</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                    </thead>
                    <tbody id="billingRunTableBody">
                    <tr>
                        <td colspan="10" class="text-center text-muted py-4">Loading billing runs...</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- =========================================================
         SETTINGS PANEL
    ========================================================== -->
    <section class="billing-panel" data-billing-panel="settings">
        <div class="card border-0 shadow-sm nx-content-card billing-card">
            <div class="billing-card-header">
                <div>
                    <h6 class="mb-0 fw-semibold">Billing Settings</h6>
                    <small class="text-muted">Invoice numbering, due dates, currency, timezone, and tax options</small>
                </div>

                <div class="billing-panel-actions">
                    <button type="button" class="btn btn-sm btn-primary" id="btnSaveBillingSettings">
                        <i class="bi bi-save me-1"></i>
                        Save Settings
                    </button>
                </div>
            </div>

            <form id="billingSettingsForm" class="billing-settings-form">
                <div class="alert alert-success border-0 mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Billing computations are intended for Philippine operations:
                    <strong>PHP currency</strong> and <strong>Asia/Manila timezone</strong>.
                </div>

                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label">Invoice Prefix</label>
                        <input type="text" class="form-control" name="invoice_prefix" id="settingInvoicePrefix" placeholder="INV">
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label">Payment Prefix</label>
                        <input type="text" class="form-control" name="payment_prefix" id="settingPaymentPrefix" placeholder="PAY">
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label">Adjustment Prefix</label>
                        <input type="text" class="form-control" name="adjustment_prefix" id="settingAdjustmentPrefix" placeholder="ADJ">
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label">Currency</label>
                        <input type="text" class="form-control" name="currency" id="settingCurrency" placeholder="PHP" value="PHP">
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label">Timezone</label>
                        <input type="text" class="form-control" id="settingTimezone" value="Asia/Manila" readonly>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label">Default Due Days</label>
                        <input type="number" class="form-control" name="default_due_days" id="settingDefaultDueDays" min="0" placeholder="10">
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label">Grace Period Days</label>
                        <input type="number" class="form-control" name="grace_period_days" id="settingGracePeriodDays" min="0" placeholder="3">
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label">Tax Rate (%)</label>
                        <input type="number" class="form-control" name="tax_rate" id="settingTaxRate" min="0" step="0.01" placeholder="0">
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label">Current Server Date</label>
                        <input type="text"
                               class="form-control"
                               id="billingCurrentPhDate"
                               value="<?= htmlspecialchars(date('Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8') ?>"
                               readonly>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label">Payment Methods</label>
                        <input type="text" class="form-control" value="Cash, GCash, Maya, Bank Transfer, Check" readonly>
                    </div>

                    <div class="col-12 col-md-6">
                        <div class="billing-toggle-card">
                            <div>
                                <div class="fw-semibold">Enable Tax Computation</div>
                                <small class="text-muted">Apply tax rate during invoice calculation later.</small>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="settingTaxEnabled" name="tax_enabled">
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6">
                        <div class="billing-toggle-card">
                            <div>
                                <div class="fw-semibold">Auto Suspend Overdue Accounts</div>
                                <small class="text-muted">Keep disabled until billing workflow is stable.</small>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="settingAutoSuspend" name="auto_suspend_enabled">
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <!-- =========================================================
         CREATE INVOICE MODAL
    ========================================================== -->
    <div class="modal fade nx-modal" id="createInvoiceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content nx-modal-content billing-modal">
                <form id="createInvoiceForm">
                    <div class="modal-header nx-modal-header">
                        <div>
                            <h5 class="modal-title mb-0">Create Invoice</h5>
                            <small class="text-muted">Generate invoice from subscriber service.</small>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body nx-modal-body">
                        <div class="alert alert-light border mb-3">
                            <i class="bi bi-clock me-1"></i>
                            Dates are computed using <strong>Asia/Manila</strong>. Currency is <strong>PHP</strong>.
                        </div>

                        <div class="nx-section-card">
                            <div class="nx-section-title mb-3">
                                <i class="bi bi-person-vcard text-primary"></i>
                                <span>Subscriber Service</span>
                            </div>

                            <label class="form-label">Subscriber Service</label>
                            <select class="form-select" id="invoiceServiceId" name="service_id" required>
                                <option value="">Select service...</option>
                            </select>
                            <small class="text-muted">Plan price will be used automatically if no custom item is added.</small>
                        </div>

                        <div class="nx-section-card">
                            <div class="nx-section-title mb-3">
                                <i class="bi bi-calendar-range text-success"></i>
                                <span>Billing Dates</span>
                            </div>

                            <div class="nx-grid-2">
                                <div>
                                    <label class="form-label">Billing Period Start</label>
                                    <input type="date" class="form-control" name="billing_period_start" id="invoicePeriodStart">
                                </div>

                                <div>
                                    <label class="form-label">Billing Period End</label>
                                    <input type="date" class="form-control" name="billing_period_end" id="invoicePeriodEnd">
                                </div>

                                <div>
                                    <label class="form-label">Issue Date</label>
                                    <input type="date" class="form-control" name="issue_date" id="invoiceIssueDate">
                                </div>

                                <div>
                                    <label class="form-label">Due Date</label>
                                    <input type="date" class="form-control" name="due_date" id="invoiceDueDate">
                                </div>
                            </div>
                        </div>

                        <div class="nx-section-card">
                            <div class="nx-section-title mb-3">
                                <i class="bi bi-list-check text-warning"></i>
                                <span>Invoice Item</span>
                            </div>

                            <small class="text-muted d-block mb-2">
                                Optional. Leave price blank to use the selected plan price.
                            </small>

                            <div class="row g-2">
                                <div class="col-12 col-md-7">
                                    <input type="text" class="form-control" id="invoiceItemDescription" placeholder="Monthly internet service">
                                </div>

                                <div class="col-12 col-md-2">
                                    <input type="number" class="form-control" id="invoiceItemQty" placeholder="Qty" value="1" min="1" step="1">
                                </div>

                                <div class="col-12 col-md-3">
                                    <input type="number" class="form-control" id="invoiceItemPrice" placeholder="Unit price" min="0" step="0.01">
                                </div>
                            </div>
                        </div>

                        <div class="nx-section-card">
                            <div class="nx-section-title mb-3">
                                <i class="bi bi-calculator text-danger"></i>
                                <span>Manual Invoice Values</span>
                            </div>

                            <div class="nx-grid-2">
                                <div>
                                    <label class="form-label">Discount Amount</label>
                                    <input type="number" class="form-control" name="discount_amount" id="invoiceDiscount" min="0" step="0.01" value="0">
                                </div>

                                <div>
                                    <label class="form-label">Tax Amount</label>
                                    <input type="number" class="form-control" name="tax_amount" id="invoiceTax" min="0" step="0.01" value="0">
                                </div>

                                <div class="nx-span-2">
                                    <label class="form-label">Notes</label>
                                    <textarea class="form-control" name="notes" id="invoiceNotes" rows="3" placeholder="Optional notes..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer nx-modal-footer">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2-circle"></i>
                            <span>Create Invoice</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- =========================================================
         RECORD PAYMENT MODAL
    ========================================================== -->
    <div class="modal fade nx-modal" id="recordPaymentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content nx-modal-content billing-modal">
                <form id="recordPaymentForm">
                    <div class="modal-header nx-modal-header">
                        <div>
                            <h5 class="modal-title mb-0">Record Payment</h5>
                            <small class="text-muted">Post manual payment and update invoice balance.</small>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body nx-modal-body">
                        <div class="alert alert-light border mb-3">
                            <i class="bi bi-cash-coin me-1"></i>
                            Payment date is recorded using <strong>Philippine time</strong>.
                        </div>

                        <div class="nx-section-card">
                            <div class="nx-section-title mb-3">
                                <i class="bi bi-receipt text-primary"></i>
                                <span>Invoice Payment</span>
                            </div>

                            <div class="nx-grid-2">
                                <div class="nx-span-2">
                                    <label class="form-label">Invoice</label>
                                    <select class="form-select" id="paymentInvoiceId" name="invoice_id" required>
                                        <option value="">Select unpaid invoice...</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="form-label">Amount</label>
                                    <input type="number" class="form-control" id="paymentAmount" name="amount" min="0.01" step="0.01" required>
                                    <small class="text-muted" id="paymentBalanceHint">Select invoice to see balance.</small>
                                </div>

                                <div>
                                    <label class="form-label">Method</label>
                                    <select class="form-select" id="paymentMethod" name="method">
                                        <option value="CASH">Cash</option>
                                        <option value="GCASH">GCash</option>
                                        <option value="MAYA">Maya</option>
                                        <option value="BANK_TRANSFER">Bank Transfer</option>
                                        <option value="CHECK">Check</option>
                                        <option value="XENDIT">Xendit</option>
                                        <option value="OTHER">Other</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="form-label">Payment Date</label>
                                    <input type="datetime-local" class="form-control" id="paymentDate" name="payment_date">
                                </div>

                                <div>
                                    <label class="form-label">Reference No.</label>
                                    <input type="text" class="form-control" id="paymentReferenceNo" name="reference_no" placeholder="OR / GCash / Bank ref no.">
                                </div>

                                <div class="nx-span-2">
                                    <label class="form-label">Remarks</label>
                                    <textarea class="form-control" id="paymentRemarks" name="remarks" rows="3" placeholder="Optional remarks..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer nx-modal-footer">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check2-circle"></i>
                            <span>Record Payment</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- =========================================================
         CREATE ADJUSTMENT MODAL
    ========================================================== -->
    <div class="modal fade nx-modal" id="createAdjustmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content nx-modal-content billing-modal">
                <form id="createAdjustmentForm">
                    <div class="modal-header nx-modal-header">
                        <div>
                            <h5 class="modal-title mb-0">Create Billing Adjustment</h5>
                            <small class="text-muted">Apply credit, debit, waiver, rebate, discount, or correction to an invoice.</small>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body nx-modal-body">
                        <div class="alert alert-warning border-0 mb-3">
                            <i class="bi bi-info-circle me-1"></i>
                            Posted adjustments immediately update the invoice total and balance.
                        </div>

                        <div class="nx-section-card">
                            <div class="nx-section-title mb-3">
                                <i class="bi bi-receipt text-primary"></i>
                                <span>Invoice</span>
                            </div>

                            <label class="form-label">Invoice</label>
                            <select class="form-select" id="adjustmentInvoiceId" name="invoice_id" required>
                                <option value="">Select invoice...</option>
                            </select>

                            <small class="text-muted" id="adjustmentInvoiceHint">
                                Select an invoice to apply adjustment.
                            </small>
                        </div>

                        <div class="nx-section-card">
                            <div class="nx-section-title mb-3">
                                <i class="bi bi-sliders text-warning"></i>
                                <span>Adjustment Details</span>
                            </div>

                            <div class="nx-grid-2">
                                <div>
                                    <label class="form-label">Adjustment Type</label>
                                    <select class="form-select" id="adjustmentType" name="adjustment_type" required>
                                        <option value="CREDIT">Credit</option>
                                        <option value="DEBIT">Debit</option>
                                        <option value="DISCOUNT">Discount</option>
                                        <option value="REBATE">Rebate</option>
                                        <option value="WAIVER">Waiver</option>
                                        <option value="CORRECTION">Correction</option>
                                    </select>
                                    <small class="text-muted">
                                        Credit, Discount, Rebate, and Waiver reduce invoice balance. Debit and Correction increase it.
                                    </small>
                                </div>

                                <div>
                                    <label class="form-label">Amount</label>
                                    <input type="number"
                                           class="form-control"
                                           id="adjustmentAmount"
                                           name="amount"
                                           min="0.01"
                                           step="0.01"
                                           required>
                                </div>

                                <div class="nx-span-2">
                                    <label class="form-label">Reason</label>
                                    <textarea class="form-control"
                                              id="adjustmentReason"
                                              name="reason"
                                              rows="3"
                                              placeholder="Example: Goodwill credit, billing correction, service outage rebate..."
                                              required></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer nx-modal-footer">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-check2-circle"></i>
                            <span>Post Adjustment</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- =========================================================
         INVOICE DETAIL MODAL
    ========================================================== -->
    <div class="modal fade nx-modal" id="invoiceDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content nx-modal-content billing-modal">
                <div class="modal-header nx-modal-header">
                    <div>
                        <h5 class="modal-title mb-0" id="invoiceDetailTitle">Invoice Details</h5>
                        <small class="text-muted" id="invoiceDetailSubtitle">Billing information</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body nx-modal-body" id="invoiceDetailBody">
                    <div class="text-center text-muted py-5">Loading invoice...</div>
                </div>

                <div class="modal-footer nx-modal-footer">
                    <button type="button" class="btn btn-outline-primary" id="btnPrintInvoiceFromDetail">
                        <i class="bi bi-printer"></i>
                        <span>Print Invoice</span>
                    </button>

                    <button type="button" class="btn btn-warning" id="btnAdjustmentFromInvoiceDetail">
                        <i class="bi bi-sliders"></i>
                        <span>Adjustment</span>
                    </button>

                    <button type="button" class="btn btn-light border ms-auto" data-bs-dismiss="modal">Close</button>

                    <button type="button" class="btn btn-success" id="btnPayFromInvoiceDetail">
                        <i class="bi bi-cash-coin"></i>
                        <span>Record Payment</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- =========================================================
         PAYMENT DETAIL MODAL
    ========================================================== -->
    <div class="modal fade nx-modal" id="paymentDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content nx-modal-content billing-modal">
                <div class="modal-header nx-modal-header">
                    <div>
                        <h5 class="modal-title mb-0" id="paymentDetailTitle">Payment Details</h5>
                        <small class="text-muted" id="paymentDetailSubtitle">Collection and allocation information</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body nx-modal-body" id="paymentDetailBody">
                    <div class="text-center text-muted py-5">Loading payment...</div>
                </div>

                <div class="modal-footer nx-modal-footer">
                    <button type="button" class="btn btn-outline-primary" id="btnPrintReceiptFromDetail">
                        <i class="bi bi-printer"></i>
                        <span>Print Receipt</span>
                    </button>

                    <button type="button" class="btn btn-outline-danger" id="btnVoidPaymentFromDetail">
                        <i class="bi bi-x-circle"></i>
                        <span>Void Payment</span>
                    </button>

                    <button type="button" class="btn btn-light border ms-auto" data-bs-dismiss="modal">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- =========================================================
         ADJUSTMENT DETAIL MODAL
    ========================================================== -->
    <div class="modal fade nx-modal" id="adjustmentDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content nx-modal-content billing-modal">
                <div class="modal-header nx-modal-header">
                    <div>
                        <h5 class="modal-title mb-0" id="adjustmentDetailTitle">Adjustment Details</h5>
                        <small class="text-muted" id="adjustmentDetailSubtitle">Billing adjustment information</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body nx-modal-body" id="adjustmentDetailBody">
                    <div class="text-center text-muted py-5">Loading adjustment...</div>
                </div>

                <div class="modal-footer nx-modal-footer">
                    <button type="button" class="btn btn-light border ms-auto" data-bs-dismiss="modal">
                        Close
                    </button>

                    <button type="button" class="btn btn-outline-danger" id="btnVoidAdjustmentFromDetail">
                        <i class="bi bi-x-circle"></i>
                        <span>Void Adjustment</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- =========================================================
         BILLING RUN DETAIL MODAL
    ========================================================== -->
    <div class="modal fade nx-modal" id="billingRunDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content nx-modal-content billing-modal">
                <div class="modal-header nx-modal-header">
                    <div>
                        <h5 class="modal-title mb-0" id="billingRunDetailTitle">Billing Run Details</h5>
                        <small class="text-muted" id="billingRunDetailSubtitle">Run audit trail</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body nx-modal-body" id="billingRunDetailBody">
                    <div class="text-center text-muted py-5">Loading billing run...</div>
                </div>

                <div class="modal-footer nx-modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="/module-assets/Billing/js/billing.js?v=<?= time() ?>"></script>
</div>