<?php
$maintenanceState = is_array($maintenanceState ?? null) ? $maintenanceState : [];
if (!empty($maintenanceState['active'])):
    $maintenanceMessage = trim((string)($maintenanceState['message'] ?? '')) ?: 'We are performing scheduled maintenance. Please try again soon.';
?>
<div class="container-fluid nx-page subscriber-portal-page d-flex min-vh-75 align-items-center justify-content-center" id="subscriberMaintenancePage">
    <div class="card border-0 shadow-sm text-center" style="max-width: 680px;">
        <div class="card-body p-4 p-md-5">
            <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary" style="width: 64px; height: 64px;">
                <i class="bi bi-tools fs-3" aria-hidden="true"></i>
            </div>
            <div class="text-primary small fw-semibold text-uppercase" style="letter-spacing: .08em;">System maintenance</div>
            <h1 class="h3 mt-2">We are sorry for the interruption</h1>
            <p class="lead mb-0"><?= nl2br(htmlspecialchars($maintenanceMessage, ENT_QUOTES, 'UTF-8')) ?></p>
            <p class="text-muted mt-3 mb-0">Thank you for your patience. Please check back shortly.</p>
        </div>
    </div>
</div>
<?php return; endif; ?>
<div class="container-fluid nx-page subscriber-portal-page">
    <div id="subscriberPortalAlert"></div>

    <div class="card border-0 shadow-sm mb-3 sp-status-banner" id="spStatusBanner">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="sp-status-icon" id="spStatusIcon">
                        <i class="bi bi-person-check"></i>
                    </div>

                    <div>
                        <div class="sp-status-title" id="spStatusTitle">
                            Welcome to your subscriber portal
                        </div>
                        <div class="sp-status-message text-muted" id="spStatusMessage">
                            Loading account status...
                        </div>
                    </div>
                </div>

                <div class="sp-status-meta text-end">
                    <div class="small text-muted">Account Status</div>
                    <div id="spStatusBadge" class="mb-2">
                        <span class="badge bg-secondary">LOADING</span>
                    </div>

                    <button id="subscriberPortalRefreshBtn" class="btn btn-sm btn-light border">
                        <i class="bi bi-arrow-clockwise"></i>
                        Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm sp-stat-card">
                <div class="card-body">
                    <div class="text-muted small">Total Balance</div>
                    <div class="fs-4 fw-bold" id="spTotalBalance">₱0.00</div>
                    <div class="small text-muted">Open billing balance</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm sp-stat-card">
                <div class="card-body">
                    <div class="text-muted small">Overdue Balance</div>
                    <div class="fs-4 fw-bold" id="spOverdueBalance">₱0.00</div>
                    <div class="small text-muted">Past due invoices</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm sp-stat-card">
                <div class="card-body">
                    <div class="text-muted small">Active Services</div>
                    <div class="fs-4 fw-bold" id="spActiveServices">0</div>
                    <div class="small text-muted">Current active subscriptions</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm sp-stat-card">
                <div class="card-body">
                    <div class="text-muted small">Next Due Date</div>
                    <div class="fs-5 fw-bold" id="spNextDueDate">-</div>
                    <div class="small text-muted">Nearest upcoming due date</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-semibold">Profile</h6>
                </div>
                <div class="card-body">
                    <div class="sp-profile-name" id="spProfileName">-</div>
                    <div class="text-muted small mb-3" id="spAccountNumber">Account # -</div>

                    <div class="mb-2">
                        <div class="text-muted small">Email</div>
                        <div id="spEmail">-</div>
                    </div>

                    <div class="mb-2">
                        <div class="text-muted small">Contact Number</div>
                        <div id="spContactNumber">-</div>
                    </div>

                    <div>
                        <div class="text-muted small">Address</div>
                        <div id="spAddress">-</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-8" id="services">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold">My Services</h6>
                    <span class="badge bg-light text-dark border" id="spServiceCount">0 services</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                            <tr>
                                <th class="ps-4">Service No.</th>
                                <th>Plan</th>
                                <th>Speed</th>
                                <th>Status</th>
                                <th>Next Due</th>
                            </tr>
                            </thead>
                            <tbody id="spServicesBody">
                            <tr>
                                <td colspan="5" class="text-muted text-center py-4">Loading services...</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3" id="invoices">
        <div class="card-header bg-white border-0 d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <div>
                <h6 class="mb-0 fw-semibold">My Invoices</h6>
                <small class="text-muted">Latest billing records</small>
            </div>

            <div class="d-flex gap-2">
                <select id="spInvoiceStatusFilter" class="form-select form-select-sm" style="width: 150px;">
                    <option value="">All Status</option>
                    <option value="UNPAID">Unpaid</option>
                    <option value="PARTIAL">Partial</option>
                    <option value="PAID">Paid</option>
                    <option value="OVERDUE">Overdue</option>
                    <option value="CANCELLED">Cancelled</option>
                </select>

                <input id="spInvoiceSearch" type="text" class="form-control form-control-sm" placeholder="Search invoice..." style="width: 220px;">
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                    <tr>
                        <th class="ps-4">Invoice No.</th>
                        <th>Period</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Paid</th>
                        <th class="text-end pe-4">Balance</th>
                    </tr>
                    </thead>
                    <tbody id="spInvoicesBody">
                    <tr>
                        <td colspan="7" class="text-muted text-center py-4">Loading invoices...</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm" id="payments">
        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
            <div>
                <h6 class="mb-0 fw-semibold">Payment History</h6>
                <small class="text-muted">Recent payment transactions</small>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                    <tr>
                        <th class="ps-4">Payment No.</th>
                        <th>Invoice</th>
                        <th>Date</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Amount</th>
                    </tr>
                    </thead>
                    <tbody id="spPaymentsBody">
                    <tr>
                        <td colspan="6" class="text-muted text-center py-4">Loading payments...</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Invoice Details Modal -->
    <div class="modal fade nx-modal sp-invoice-modal" id="spInvoiceDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
            <div class="modal-content nx-modal-content">
                <div class="modal-header nx-modal-header">
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <h5 class="modal-title mb-0" id="spInvoiceModalTitle">Invoice Details</h5>
                            <span id="spInvoiceModalStatus"></span>
                        </div>
                        <small class="text-muted" id="spInvoiceModalSubtitle">Loading invoice details...</small>
                    </div>

                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body nx-modal-body">
                    <div id="spInvoiceModalLoading" class="text-center text-muted py-5">
                        <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                        Loading invoice details...
                    </div>

                    <div id="spInvoiceModalContent" class="d-none">
                        <div class="row g-3 mb-3">
                            <div class="col-12 col-lg-8">
                                <div class="sp-detail-card h-100">
                                    <div class="sp-detail-label">Billing Information</div>

                                    <div class="row g-2 mt-1">
                                        <div class="col-12 col-md-6">
                                            <div class="text-muted small">Invoice No.</div>
                                            <div class="fw-semibold" id="spModalInvoiceNo">-</div>
                                        </div>

                                        <div class="col-12 col-md-6">
                                            <div class="text-muted small">Plan</div>
                                            <div class="fw-semibold" id="spModalPlanName">-</div>
                                        </div>

                                        <div class="col-12 col-md-6">
                                            <div class="text-muted small">Billing Period</div>
                                            <div id="spModalBillingPeriod">-</div>
                                        </div>

                                        <div class="col-12 col-md-3">
                                            <div class="text-muted small">Issue Date</div>
                                            <div id="spModalIssueDate">-</div>
                                        </div>

                                        <div class="col-12 col-md-3">
                                            <div class="text-muted small">Due Date</div>
                                            <div id="spModalDueDate">-</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-lg-4">
                                <div class="sp-detail-card sp-balance-card h-100">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">Total</span>
                                        <strong id="spModalTotal">₱0.00</strong>
                                    </div>

                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">Paid</span>
                                        <strong id="spModalPaid">₱0.00</strong>
                                    </div>

                                    <hr>

                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">Balance</span>
                                        <strong class="fs-5" id="spModalBalance">₱0.00</strong>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="sp-detail-card mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0 fw-semibold">Invoice Items</h6>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                    <tr>
                                        <th>Description</th>
                                        <th>Type</th>
                                        <th class="text-end">Qty</th>
                                        <th class="text-end">Unit Price</th>
                                        <th class="text-end">Line Total</th>
                                    </tr>
                                    </thead>
                                    <tbody id="spModalInvoiceItemsBody">
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-3">No line items.</td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="sp-detail-card">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0 fw-semibold">Payment History</h6>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                    <tr>
                                        <th>Payment No.</th>
                                        <th>Date</th>
                                        <th>Method</th>
                                        <th>Reference</th>
                                        <th>Status</th>
                                        <th class="text-end">Amount</th>
                                    </tr>
                                    </thead>
                                    <tbody id="spModalPaymentsBody">
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-3">No payments yet.</td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer nx-modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>

                    <button type="button" class="btn btn-outline-dark" id="spPrintInvoiceBtn">
                        <i class="bi bi-printer"></i>
                        <span>Print Invoice</span>
                    </button>

                    <button type="button" class="btn btn-primary d-none" id="spPayNowBtn" disabled>
                        <i class="bi bi-credit-card"></i>
                        <span>Pay Now</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="/module-assets/SubscriberPortal/js/SubscriberPortal.js"></script>
</div>
