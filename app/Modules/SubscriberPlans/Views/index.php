<?php
$plans = $plans ?? [];
?>

<div class="container-fluid nx-page" id="subscriberPlansApp">
    <link rel="stylesheet" href="/module-assets/SubscriberPlans/css/SubscriberPlans.css">

    <script>
        window.SUBSCRIBER_PLANS_BOOTSTRAP = <?= json_encode([
                'plans' => $plans
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    </script>

    <div class="card border-0 shadow-sm mb-4 nx-page-header-card plans-hero-card">
        <div class="card-body plans-hero-body">
            <div class="plans-hero-left">
                <div class="plans-hero-badge">
                    <i class="bi bi-stars"></i>
                    <span>Commercial Catalog</span>
                </div>

                <h1 class="plans-hero-title">Subscriber Plans</h1>
                <p class="plans-hero-text">
                    Build and manage commercial service profiles for subscriber activation, billing, and provisioning.
                </p>
            </div>

            <div class="plans-hero-actions">
                <button class="btn btn-primary nx-btn-primary" id="plansAddBtn" type="button">
                    <i class="bi bi-plus-lg"></i>
                    <span>Add Plan</span>
                </button>

                <button class="btn btn-light border nx-btn-secondary" id="plansRefreshBtn" type="button">
                    <i class="bi bi-arrow-clockwise"></i>
                    <span>Refresh</span>
                </button>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4 plans-summary-row">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm plans-summary-card plans-summary-card--primary">
                <div class="plans-summary-accent"></div>
                <div class="card-body">
                    <div class="nx-summary-top">
                        <div>
                            <div class="plans-summary-label">Total Plans</div>
                            <div class="plans-summary-value" id="plansSummaryTotal"><?= count($plans) ?></div>
                            <div class="plans-summary-text">Commercial packages configured</div>
                        </div>

                        <div class="plans-summary-icon">
                            <i class="bi bi-collection"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm plans-summary-card plans-summary-card--success">
                <div class="plans-summary-accent"></div>
                <div class="card-body">
                    <div class="nx-summary-top">
                        <div>
                            <div class="plans-summary-label">Active</div>
                            <div class="plans-summary-value" id="plansSummaryActive">0</div>
                            <div class="plans-summary-text">Available for new service assignment</div>
                        </div>

                        <div class="plans-summary-icon">
                            <i class="bi bi-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm plans-summary-card plans-summary-card--cyan">
                <div class="plans-summary-accent"></div>
                <div class="card-body">
                    <div class="nx-summary-top">
                        <div>
                            <div class="plans-summary-label">Prepaid</div>
                            <div class="plans-summary-value" id="plansSummaryPrepaid">0</div>
                            <div class="plans-summary-text">Time-bound service plans</div>
                        </div>

                        <div class="plans-summary-icon">
                            <i class="bi bi-lightning-charge"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm plans-summary-card plans-summary-card--slate">
                <div class="plans-summary-accent"></div>
                <div class="card-body">
                    <div class="nx-summary-top">
                        <div>
                            <div class="plans-summary-label">Postpaid</div>
                            <div class="plans-summary-value" id="plansSummaryPostpaid">0</div>
                            <div class="plans-summary-text">Recurring commercial plans</div>
                        </div>

                        <div class="plans-summary-icon">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3 plans-toolbar-card">
        <div class="card-body plans-toolbar-body">
            <div class="plans-toolbar-grid">
                <div class="plans-search-wrap">
                    <i class="bi bi-search plans-search-icon"></i>
                    <input
                            type="text"
                            id="plansSearchInput"
                            class="form-control plans-toolbar-control plans-search-control"
                            placeholder="Search by plan name, type, description, speed, or status..."
                    >
                </div>

                <div>
                    <select id="plansTypeFilter" class="form-select plans-toolbar-control">
                        <option value="">All Types</option>
                        <option value="PREPAID">PREPAID</option>
                        <option value="POSTPAID">POSTPAID</option>
                    </select>
                </div>

                <div>
                    <select id="plansStatusFilter" class="form-select plans-toolbar-control">
                        <option value="">All Statuses</option>
                        <option value="ACTIVE">ACTIVE</option>
                        <option value="INACTIVE">INACTIVE</option>
                    </select>
                </div>

                <div class="plans-toolbar-meta">
                    <small class="text-muted plans-pagination-top" id="plansPaginationInfo"></small>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm plans-table-card">
        <div class="plans-table-topline"></div>
        <div class="card-body p-0">
            <div id="plansTableView"></div>
        </div>
    </div>

    <div class="modal fade nx-modal" id="planFormModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
            <div class="modal-content nx-modal-content">
                <form id="planForm">
                    <input type="hidden" name="id" id="planFormId">

                    <div class="modal-header nx-modal-header">
                        <div>
                            <h5 class="modal-title mb-0" id="planFormModalTitle">Add Subscriber Plan</h5>
                            <small class="text-muted" id="planFormModalSubtitle">Create a commercial plan for subscriber services</small>
                        </div>
                        <button class="btn-close" data-bs-dismiss="modal" type="button"></button>
                    </div>

                    <div class="modal-body nx-modal-body">
                        <div class="nx-section-card">
                            <div class="nx-section-title">
                                <i class="bi bi-box-seam text-primary"></i>
                                <span>Plan Info</span>
                            </div>

                            <div class="nx-grid-2">
                                <div class="mb-2">
                                    <label class="form-label">Plan Name</label>
                                    <input name="plan_name" id="planName" class="form-control" placeholder="Plan Name" required>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" id="planDescription" class="form-control" rows="3" placeholder="Description"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="nx-section-card">
                            <div class="nx-section-title">
                                <i class="bi bi-cash-stack text-success"></i>
                                <span>Commercial Info</span>
                            </div>

                            <div class="nx-grid-2">
                                <div class="mb-2">
                                    <label class="form-label">Plan Type</label>
                                    <select name="plan_type" id="planType" class="form-select" required>
                                        <option value="POSTPAID">POSTPAID</option>
                                        <option value="PREPAID">PREPAID</option>
                                    </select>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label">Validity Days</label>
                                    <select name="validity_days" id="planValidityDays" class="form-select" required>
                                        <option value="30">30 Days</option>
                                        <option value="14">14 Days</option>
                                        <option value="7">7 Days</option>
                                    </select>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label">Price</label>
                                    <input name="price" id="planPrice" type="number" step="0.01" min="0" class="form-control" placeholder="Price" required>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label">Status</label>
                                    <select name="is_active" id="planStatus" class="form-select">
                                        <option value="1">ACTIVE</option>
                                        <option value="0">INACTIVE</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="nx-section-card">
                            <div class="nx-section-title">
                                <i class="bi bi-speedometer2 text-warning"></i>
                                <span>Service Profile</span>
                            </div>

                            <div class="nx-grid-2">
                                <div class="mb-2">
                                    <label class="form-label">Speed (Mbps)</label>
                                    <input name="speed" id="planSpeed" type="number" min="1" class="form-control" placeholder="Speed (Mbps)" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer nx-modal-footer sticky-footer">
                        <button type="button" class="btn btn-light border nx-btn-secondary" data-bs-dismiss="modal">
                            <span>Cancel</span>
                        </button>
                        <button class="btn btn-primary nx-btn-primary" type="submit" id="planFormSubmitBtn">
                            <i class="bi bi-check-circle"></i>
                            <span>Save Plan</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade nx-modal" id="planViewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
            <div class="modal-content nx-modal-content">
                <div class="modal-header nx-modal-header">
                    <div>
                        <h5 class="modal-title mb-0">Plan Details</h5>
                        <small class="text-muted" id="planViewSubtitle">-</small>
                    </div>
                    <button class="btn-close" data-bs-dismiss="modal" type="button"></button>
                </div>

                <div class="modal-body nx-modal-body">
                    <div class="nx-section-card">
                        <div class="nx-section-title">
                            <i class="bi bi-box-seam text-primary"></i>
                            <span>Plan Info</span>
                        </div>

                        <div class="nx-grid-2">
                            <div class="nx-field">
                                <label>Plan Name</label>
                                <div id="viewPlanName">-</div>
                            </div>

                            <div class="nx-field">
                                <label>Description</label>
                                <div id="viewPlanDescription">-</div>
                            </div>
                        </div>
                    </div>

                    <div class="nx-section-card">
                        <div class="nx-section-title">
                            <i class="bi bi-cash-stack text-success"></i>
                            <span>Commercial Info</span>
                        </div>

                        <div class="nx-grid-2">
                            <div class="nx-field">
                                <label>Plan Type</label>
                                <div id="viewPlanType">-</div>
                            </div>

                            <div class="nx-field">
                                <label>Validity Days</label>
                                <div id="viewPlanValidity">-</div>
                            </div>

                            <div class="nx-field">
                                <label>Price</label>
                                <div id="viewPlanPrice">-</div>
                            </div>

                            <div class="nx-field">
                                <label>Status</label>
                                <div id="viewPlanStatus">-</div>
                            </div>
                        </div>
                    </div>

                    <div class="nx-section-card mb-0">
                        <div class="nx-section-title">
                            <i class="bi bi-speedometer2 text-warning"></i>
                            <span>Service Profile</span>
                        </div>

                        <div class="nx-grid-2">
                            <div class="nx-field">
                                <label>Speed</label>
                                <div id="viewPlanSpeed">-</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer nx-modal-footer">
                    <button type="button" class="btn btn-light border nx-btn-secondary" data-bs-dismiss="modal">
                        <span>Close</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="/module-assets/SubscriberPlans/js/SubscriberPlans.js"></script>
</div>