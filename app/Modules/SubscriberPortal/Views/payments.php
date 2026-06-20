<div class="container-fluid nx-page subscriber-portal-page sp-page" data-sp-page="payments">

    <link rel="stylesheet" href="/module-assets/SubscriberPortal/css/SubscriberPortal.css">

    <div id="subscriberPortalAlert"></div>

    <div class="card border-0 shadow-sm mb-3 sp-page-title-card">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="sp-page-kicker">
                    <i class="bi bi-credit-card"></i>
                    Payment Records
                </div>
                <h5 class="mb-0 fw-semibold">My Payments</h5>
                <small class="text-muted">View your posted, pending, and voided payment transactions.</small>
            </div>

            <button id="subscriberPortalRefreshBtn" class="btn btn-light border">
                <i class="bi bi-arrow-clockwise"></i>
                Refresh
            </button>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm sp-stat-card">
                <div class="card-body">
                    <div class="text-muted small">Total Payments</div>
                    <div class="fs-4 fw-bold" id="spPaymentCount">0</div>
                    <div class="small text-muted">Payment records shown</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm sp-stat-card">
                <div class="card-body">
                    <div class="text-muted small">Posted Amount</div>
                    <div class="fs-4 fw-bold" id="spPaymentPostedAmount">₱0.00</div>
                    <div class="small text-muted">Total posted payments</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm sp-stat-card">
                <div class="card-body">
                    <div class="text-muted small">Latest Payment</div>
                    <div class="fs-5 fw-bold" id="spLatestPaymentDate">-</div>
                    <div class="small text-muted">Most recent transaction</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <div>
                <h6 class="mb-0 fw-semibold">Payment History</h6>
                <small class="text-muted">Recent payment transactions</small>
            </div>

            <div class="sp-filter-bar">
                <input id="spPaymentSearch" type="text" class="form-control form-control-sm" placeholder="Search payment, invoice, method...">
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
                        <th>Reference</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end pe-4">Receipt</th>
                    </tr>
                    </thead>
                    <tbody id="spPaymentsBody">
                    <tr>
                        <td colspan="8" class="text-muted text-center py-4">Loading payments...</td>
                    </tr>
                    </tbody>
                </table>
            </div>

            <div id="spPaymentsPager" class="sp-table-pager"></div>
        </div>
    </div>

    <script src="/module-assets/SubscriberPortal/js/SubscriberPortal.js"></script>
</div>