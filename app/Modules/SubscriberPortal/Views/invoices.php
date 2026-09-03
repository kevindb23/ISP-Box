<div class="container-fluid nx-page subscriber-portal-page sp-page" data-sp-page="invoices">
    <div id="subscriberPortalAlert"></div>

    <div class="card border-0 shadow-sm mb-3 sp-page-title-card">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="sp-page-kicker">
                    <i class="bi bi-receipt"></i>
                    Billing Records
                </div>
                <h5 class="mb-0 fw-semibold">My Invoices</h5>
                <small class="text-muted">Review your invoice history, balances, due dates, and payment status.</small>
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
                    <div class="text-muted small">Open Balance</div>
                    <div class="fs-4 fw-bold" id="spInvoiceOpenBalance">₱0.00</div>
                    <div class="small text-muted">Total unpaid balance</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm sp-stat-card">
                <div class="card-body">
                    <div class="text-muted small">Overdue Balance</div>
                    <div class="fs-4 fw-bold" id="spInvoiceOverdueBalance">₱0.00</div>
                    <div class="small text-muted">Past due balance</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm sp-stat-card">
                <div class="card-body">
                    <div class="text-muted small">Invoice Count</div>
                    <div class="fs-4 fw-bold" id="spInvoiceCount">0</div>
                    <div class="small text-muted">Total billing records</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white border-0 d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <div>
                <h6 class="mb-0 fw-semibold">Invoice List</h6>
                <small class="text-muted">Click an invoice row to view details</small>
            </div>

            <div class="d-flex gap-2 sp-filter-bar">
                <select id="spInvoiceStatusFilter" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    <option value="UNPAID">Unpaid</option>
                    <option value="PARTIAL">Partial</option>
                    <option value="PAID">Paid</option>
                    <option value="OVERDUE">Overdue</option>
                    <option value="CANCELLED">Cancelled</option>
                </select>

                <input id="spInvoiceSearch" type="text" class="form-control form-control-sm" placeholder="Search invoice...">
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

            <div id="spInvoicesPager" class="sp-table-pager"></div>
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
                            <h6 class="mb-2 fw-semibold">Invoice Items</h6>

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
                            <h6 class="mb-2 fw-semibold">Payment History</h6>

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
                    <button type="button" class="btn btn-outline-primary d-none" id="spManualPayBtn" disabled data-bs-toggle="modal" data-bs-target="#spManualPaymentModal">
                        <i class="bi bi-upload"></i><span>Submit Manual Payment</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade nx-modal" id="spManualPaymentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content nx-modal-content">
            <form id="spManualPaymentForm" enctype="multipart/form-data">
                <div class="modal-header nx-modal-header"><h5 class="modal-title">Submit Payment Proof</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body nx-modal-body">
                    <input type="hidden" name="invoice_id" id="spManualPaymentInvoiceId">
                    <div class="mb-3"><label class="form-label">Payment Method</label><select class="form-select" name="method" required><option value="CASH">Cash</option><option value="BANK_TRANSFER">Bank Transfer</option><option value="GCASH">GCash</option><option value="MAYA">Maya</option></select></div>
                    <div class="mb-3"><label class="form-label">Amount</label><input type="number" min="0.01" step="0.01" class="form-control" name="amount" id="spManualPaymentAmount" required></div>
                    <div class="mb-3"><label class="form-label">Reference Number</label><input type="text" class="form-control" name="reference_no" maxlength="255"></div>
                    <div><label class="form-label">Payment Screenshot</label><input type="file" class="form-control" name="payment_proof" accept="image/jpeg,image/png,image/webp" required><div class="form-text">JPG, PNG, or WEBP, maximum 5 MB. Payment remains pending until Billing confirms receipt.</div></div>
                </div>
                <div class="modal-footer nx-modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" id="spManualPaymentSubmitBtn">Submit for Review</button></div>
            </form>
        </div></div>
    </div>

    <script src="/module-assets/SubscriberPortal/js/SubscriberPortal.js"></script>
</div>
