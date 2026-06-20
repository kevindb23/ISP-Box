<div class="container-fluid nx-page" id="paymentGatewayPage">
    <link rel="stylesheet" href="/module-assets/PaymentGateway/css/payment-gateway.css">

    <div class="card border-0 shadow-sm nx-page-header-card">
        <div class="card-body nx-page-header">
            <div class="nx-page-header-left">
                <div class="pg-hero-badge">
                    <i class="bi bi-credit-card-2-front"></i>
                    <span>PAYMENT GATEWAY</span>
                </div>

                <h5 class="nx-page-title">Payment Gateway</h5>
                <div class="nx-page-subtitle">
                    Configure PayMongo checkout credentials and monitor online payment transactions.
                </div>
            </div>

            <div class="nx-page-actions">
                <button type="button" class="btn btn-primary nx-header-btn" id="btnSaveGatewaySettings">
                    <i class="bi bi-save"></i>
                    <span>Save Settings</span>
                </button>

                <button type="button" class="btn btn-light border nx-header-btn" id="btnRefreshGatewayTransactions">
                    <i class="bi bi-arrow-clockwise"></i>
                    <span>Refresh</span>
                </button>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-5">
            <div class="card border-0 shadow-sm nx-content-card pg-card h-100">
                <div class="card-body">
                    <div class="pg-section-title">
                        <i class="bi bi-sliders text-primary"></i>
                        <span>Gateway Settings</span>
                    </div>

                    <form id="paymentGatewaySettingsForm">
                        <div class="pg-toggle-box mb-3">
                            <div>
                                <div class="fw-semibold">Enable PayMongo</div>
                                <div class="text-muted small">Allow subscribers to pay invoices using PayMongo checkout.</div>
                            </div>

                            <div class="form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" id="paymongoEnabled">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Mode</label>
                            <select class="form-select" id="paymongoMode">
                                <option value="test">TEST</option>
                                <option value="live">LIVE</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Public Key</label>
                            <input type="text" class="form-control" id="paymongoPublicKey" autocomplete="off">
                        </div>

                        <div class="mb-0">
                            <label class="form-label">Secret Key</label>
                            <input type="password" class="form-control" id="paymongoSecretKey" autocomplete="off">
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-7">
            <div class="card border-0 shadow-sm nx-content-card pg-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="pg-section-title mb-0">
                            <i class="bi bi-receipt text-primary"></i>
                            <span>Gateway Transactions</span>
                        </div>

                        <span class="text-muted small" id="paymentGatewayTransactionCount">0 records</span>
                    </div>

                    <div id="paymentGatewayTransactionHost">
                        <div class="text-center text-muted py-5">
                            Loading transactions...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="/module-assets/PaymentGateway/js/payment-gateway.js"></script>
</div>