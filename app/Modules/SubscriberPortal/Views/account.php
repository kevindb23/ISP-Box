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
<div class="container-fluid nx-page subscriber-portal-page sp-page" data-sp-page="account">
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

    <div class="row g-3">
        <div class="col-12 col-xl-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-semibold">Profile</h6>
                    <small class="text-muted">Your account information</small>
                </div>

                <div class="card-body">
                    <div class="sp-profile-name" id="spProfileName">-</div>
                    <div class="text-muted small mb-3" id="spAccountNumber">Account # -</div>

                    <div class="sp-profile-list">
                        <div class="sp-profile-item">
                            <div class="text-muted small">Email</div>
                            <div id="spEmail">-</div>
                        </div>

                        <div class="sp-profile-item">
                            <div class="text-muted small">Contact Number</div>
                            <div id="spContactNumber">-</div>
                        </div>

                        <div class="sp-profile-item">
                            <div class="text-muted small">Address</div>
                            <div id="spAddress">-</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-semibold">Quick Access</h6>
                    <small class="text-muted">Go directly to your service, billing, payment, and support options</small>
                </div>

                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <a href="/subscriber-portal/services" class="sp-quick-card">
                                <div class="sp-quick-icon">
                                    <i class="bi bi-wifi"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold">My Services</div>
                                    <div class="small text-muted">View plan and service status</div>
                                </div>
                            </a>
                        </div>

                        <div class="col-12 col-md-4">
                            <a href="/subscriber-portal/invoices" class="sp-quick-card">
                                <div class="sp-quick-icon">
                                    <i class="bi bi-receipt"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold">My Invoices</div>
                                    <div class="small text-muted">View balances and due dates</div>
                                </div>
                            </a>
                        </div>

                        <div class="col-12 col-md-4">
                            <a href="/subscriber-portal/payments" class="sp-quick-card">
                                <div class="sp-quick-icon">
                                    <i class="bi bi-credit-card"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold">My Payments</div>
                                    <div class="small text-muted">View payment history</div>
                                </div>
                            </a>
                        </div>
                    </div>

                    <div class="sp-account-note mt-3">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                            <div class="d-flex gap-2">
                                <i class="bi bi-shield-lock"></i>
                                <div>
                                    <div class="fw-semibold">Portal Security</div>
                                    <div class="small text-muted">
                                        Change your subscriber portal login password.
                                    </div>
                                </div>
                            </div>

                            <button type="button"
                                    class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#spChangePasswordModal">
                                <i class="bi bi-key"></i>
                                Change Password
                            </button>
                        </div>
                    </div>

                    <div class="sp-account-note mt-3">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                            <div class="d-flex gap-2">
                                <i class="bi bi-info-circle"></i>
                                <div>
                                    <div class="fw-semibold">Need help with your account?</div>
                                    <div class="small text-muted">
                                        Raise a concern for internet, billing, payment, or account-related assistance.
                                    </div>
                                </div>
                            </div>

                            <button type="button"
                                    class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#spRaiseConcernModal">
                                <i class="bi bi-headset"></i>
                                Raise a Concern
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-semibold">Latest Billing Summary</h6>
                    <small class="text-muted">Your most recent invoice information</small>
                </div>

                <div class="card-body">
                    <div id="spLatestInvoiceBox" class="text-muted">
                        Loading latest invoice...
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Raise Concern Modal -->
    <div class="modal fade nx-modal" id="spRaiseConcernModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content nx-modal-content">
                <form id="spRaiseConcernForm">
                    <div class="modal-header nx-modal-header">
                        <div>
                            <h5 class="modal-title mb-0">Raise a Concern</h5>
                            <small class="text-muted">Tell us what you need help with.</small>
                        </div>

                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body nx-modal-body">
                        <div class="mb-3">
                            <label class="form-label">Concern Type</label>
                            <select name="category" class="form-select" required>
                                <option value="INTERNET">Internet Issue</option>
                                <option value="BILLING">Billing Concern</option>
                                <option value="ACCOUNT">Account Concern</option>
                                <option value="OTHERS">Others</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Subject</label>
                            <input type="text"
                                   name="subject"
                                   class="form-control"
                                   placeholder="Example: No internet connection"
                                   required>
                        </div>

                        <div class="mb-0">
                            <label class="form-label">Description</label>
                            <textarea name="description"
                                      class="form-control"
                                      rows="5"
                                      placeholder="Please describe your concern..."
                                      required></textarea>
                        </div>
                    </div>

                    <div class="modal-footer nx-modal-footer">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">
                            Cancel
                        </button>

                        <button type="submit" class="btn btn-primary" id="spRaiseConcernSubmitBtn">
                            <i class="bi bi-send"></i>
                            Submit Concern
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Change Portal Password Modal -->
    <div class="modal fade nx-modal" id="spChangePasswordModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content nx-modal-content">
                <form id="spChangePasswordForm">
                    <div class="modal-header nx-modal-header">
                        <div>
                            <h5 class="modal-title mb-0">Change Portal Password</h5>
                            <small class="text-muted">Update your subscriber portal login password.</small>
                        </div>

                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body nx-modal-body">
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password"
                                   name="current_password"
                                   class="form-control"
                                   required
                                   autocomplete="current-password">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password"
                                   name="new_password"
                                   class="form-control"
                                   required
                                   autocomplete="new-password">

                            <div class="form-text">
                                Minimum of 8 characters.
                            </div>
                        </div>

                        <div class="mb-0">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password"
                                   name="confirm_password"
                                   class="form-control"
                                   required
                                   autocomplete="new-password">
                        </div>
                    </div>

                    <div class="modal-footer nx-modal-footer">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">
                            Cancel
                        </button>

                        <button type="submit" class="btn btn-primary" id="spChangePasswordSubmitBtn">
                            <i class="bi bi-check-circle"></i>
                            Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="/module-assets/SubscriberPortal/js/SubscriberPortal.js"></script>
</div>
