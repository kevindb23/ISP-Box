<?php
$plans = $plans ?? [];
$plansLegacyUi = isset($_GET['legacy_ui']) && (string)$_GET['legacy_ui'] === '1';

if (!$plansLegacyUi):
    $nxNextManifestPath = BASE_PATH . '/public/build-next/.vite/manifest.json';
    $nxNextManifest = is_file($nxNextManifestPath)
        ? (json_decode((string)file_get_contents($nxNextManifestPath), true) ?: [])
        : [];
    $nxNextEntry = $nxNextManifest['src/main.ts'] ?? [];
    $nxNextVersion = is_file($nxNextManifestPath) ? (string)filemtime($nxNextManifestPath) : (string)time();

    foreach (($nxNextEntry['css'] ?? []) as $nxNextCss): ?>
        <link rel="stylesheet" href="/build-next/<?= htmlspecialchars(ltrim((string)$nxNextCss, '/'), ENT_QUOTES, 'UTF-8') ?>?v=<?= htmlspecialchars($nxNextVersion, ENT_QUOTES, 'UTF-8') ?>">
    <?php endforeach; ?>

    <div class="container-fluid nx-page" data-nx-next-root="plans">
        <script type="application/json" data-nx-next-props><?= json_encode(
            ['plans' => $plans],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ) ?></script>
    </div>

    <?php if (!empty($nxNextEntry['file'])): ?>
        <script type="module" src="/build-next/<?= htmlspecialchars(ltrim((string)$nxNextEntry['file'], '/'), ENT_QUOTES, 'UTF-8') ?>?v=<?= htmlspecialchars($nxNextVersion, ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php else: ?>
        <div class="alert alert-warning">The new Plans interface is not built. Use <a href="/subscriber-plans?legacy_ui=1">the legacy interface</a>.</div>
    <?php endif;
    return;
endif;
?>

<style>
    #subscriberPlansApp .toolbar-grid {
        grid-template-columns: minmax(260px, 1fr) minmax(300px, 340px) auto !important;
    }
    #subscriberPlansApp .plans-filter-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        gap: 12px;
        min-width: 0;
    }
    #subscriberPlansApp .plans-filter-row > div,
    #subscriberPlansApp .plans-filter-row .toolbar-control {
        width: 100%;
        min-width: 0;
    }
    @media (max-width: 991.98px) {
        #subscriberPlansApp .toolbar-grid {
            grid-template-columns: minmax(0, 1fr) !important;
        }
        #subscriberPlansApp .plans-filter-row {
            grid-column: 1;
        }
    }
    html[data-theme="dark"] #subscriberPlansApp .nx-modal-content,
    html[data-theme="dark"] #subscriberPlansApp .nx-modal-content > form {
        background: var(--card, #252728) !important;
        color: var(--foreground, #f5f5f5) !important;
    }
    html[data-theme="dark"] #subscriberPlansApp .nx-modal-header,
    html[data-theme="dark"] #subscriberPlansApp .nx-modal-footer {
        background: var(--card, #252728) !important;
        color: var(--foreground, #f5f5f5) !important;
        border-color: var(--border, #414344) !important;
    }
    html[data-theme="dark"] #subscriberPlansApp .nx-modal-body {
        background: var(--muted, #202122) !important;
        color: var(--foreground, #f5f5f5) !important;
    }
    html[data-theme="dark"] #subscriberPlansApp .nx-modal-body .nx-section-card {
        background: var(--card, #252728) !important;
        color: var(--foreground, #f5f5f5) !important;
        border: 1px solid var(--border, #414344) !important;
        box-shadow: none !important;
    }
    html[data-theme="dark"] #subscriberPlansApp .nx-modal-body :is(.form-control, .form-select) {
        background-color: var(--muted, #202122) !important;
        color: var(--foreground, #f5f5f5) !important;
        border-color: var(--input, #464849) !important;
    }
    html[data-theme="dark"] #subscriberPlansApp .nx-modal-body :is(.form-label, .text-muted) {
        color: var(--muted-foreground, #a3a3a3) !important;
    }
    html[data-theme="dark"] #subscriberPlansApp .nx-modal-header .btn-close {
        filter: invert(1) grayscale(1);
        opacity: .8;
    }
    /* Keep the catalog visible behind Plans dialogs. Some legacy dark-theme
       modal rules paint both the modal viewport and Bootstrap backdrop, which
       makes opening a dialog look like a full-page black screen. */
    html[data-theme="dark"] #planFormModal.modal.show,
    html[data-theme="dark"] #planViewModal.modal.show {
        background: transparent !important;
    }
    html[data-theme="dark"] body:has(#planFormModal.show) > .modal-backdrop.show,
    html[data-theme="dark"] body:has(#planViewModal.show) > .modal-backdrop.show {
        background-color: #000 !important;
        opacity: .28 !important;
    }
</style>

<div class="container-fluid nx-page" id="subscriberPlansApp">
    <script>
        window.SUBSCRIBER_PLANS_BOOTSTRAP = <?= json_encode([
                'plans' => $plans
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    </script>

    <div class="card border-0 shadow-sm mb-4 nx-page-header-card page-hero-card">
        <div class="card-body page-hero-body">
            <div class="page-hero-left">
                <div class="page-hero-badge">
                    <span>Commercial catalog</span>
                </div>

                <h1 class="page-hero-title">Plan inventory</h1>
                <p class="page-hero-text">
                    Commercial service profiles for subscriber activation, billing, and provisioning.
                </p>
            </div>

            <div class="page-hero-actions">
                <button class="btn btn-primary nx-btn-primary" id="plansAddBtn" type="button">
                    <i class="bi bi-plus-lg"></i>
                    <span>New Plan</span>
                </button>

                <button class="btn btn-light border nx-btn-secondary" id="plansRefreshBtn" type="button">
                    <i class="bi bi-arrow-clockwise"></i>
                    <span>Refresh</span>
                </button>
            </div>
        </div>
    </div>

    <div class="summary-row">
        <div>
            <div class="card border-0 shadow-sm summary-card summary-card--primary">
                <div class="summary-accent"></div>
                <div class="card-body">
                    <div class="nx-summary-top">
                        <div>
                            <div class="summary-label">Total Plans</div>
                            <div class="summary-value" id="plansSummaryTotal"><?= count($plans) ?></div>
                            <div class="summary-text">Commercial packages configured</div>
                        </div>

                        <div class="summary-icon">
                            <i class="bi bi-collection"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <div class="card border-0 shadow-sm summary-card summary-card--success">
                <div class="summary-accent"></div>
                <div class="card-body">
                    <div class="nx-summary-top">
                        <div>
                            <div class="summary-label">Postpaid</div>
                            <div class="summary-value" id="plansSummaryPostpaid">0</div>
                            <div class="summary-text">Recurring commercial plans</div>
                        </div>

                        <div class="summary-icon">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <div class="card border-0 shadow-sm summary-card summary-card--cyan">
                <div class="summary-accent"></div>
                <div class="card-body">
                    <div class="nx-summary-top">
                        <div>
                            <div class="summary-label">Prepaid</div>
                            <div class="summary-value" id="plansSummaryPrepaid">0</div>
                            <div class="summary-text">Time-bound service plans</div>
                        </div>

                        <div class="summary-icon">
                            <i class="bi bi-lightning-charge"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <div class="card border-0 shadow-sm summary-card summary-card--slate">
                <div class="summary-accent"></div>
                <div class="card-body">
                    <div class="nx-summary-top">
                        <div>
                            <div class="summary-label">Average ARPU</div>
                            <div class="summary-value" id="plansSummaryArpu">₱0.00</div>
                            <div class="summary-text">Average catalog price</div>
                        </div>

                        <div class="summary-icon">
                            <i class="bi bi-cash-stack"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="toolbar-shell toolbar-shell">
        <div class="toolbar-body">
            <div class="toolbar-grid">
                <div class="plans-search-wrap">
                    <i class="bi bi-search plans-search-icon"></i>
                    <input
                            type="text"
                            id="plansSearchInput"
                            class="form-control toolbar-control plans-search-control toolbar-control"
                            placeholder="Search by plan name, type, description, speed, or status..."
                    >
                </div>

                <div class="plans-filter-row">
                    <div>
                        <select id="plansTypeFilter" class="form-select toolbar-control">
                            <option value="">All Types</option>
                            <option value="PREPAID">PREPAID</option>
                            <option value="POSTPAID">POSTPAID</option>
                        </select>
                    </div>

                    <div>
                        <select id="plansStatusFilter" class="form-select toolbar-control">
                            <option value="">All Statuses</option>
                            <option value="ACTIVE">ACTIVE</option>
                            <option value="INACTIVE">INACTIVE</option>
                        </select>
                    </div>
                </div>

                <div class="toolbar-meta">
                    <small class="text-muted plans-pagination-top" id="plansPaginationInfo"></small>
                </div>
            </div>
        </div>
    </div>

    <div class="plans-table-card table-container">
        <div class="plans-table-topline"></div>
        <div id="plansTableView" class="table-container"></div>
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
                                <div>
                                    <label class="form-label">Plan Name</label>
                                    <input name="plan_name" id="planName" class="form-control" placeholder="Plan Name" required>
                                </div>

                                <div>
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
                                <div>
                                    <label class="form-label">Plan Type</label>
                                    <select name="plan_type" id="planType" class="form-select" required>
                                        <option value="POSTPAID">POSTPAID</option>
                                        <option value="PREPAID">PREPAID</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="form-label">Validity Days</label>
                                    <select name="validity_days" id="planValidityDays" class="form-select" required>
                                        <option value="30">30 Days</option>
                                        <option value="14">14 Days</option>
                                        <option value="7">7 Days</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="form-label">Price</label>
                                    <input name="price" id="planPrice" type="number" step="0.01" min="0" class="form-control" placeholder="Price" required>
                                </div>

                                <div>
                                    <label class="form-label">Status</label>
                                    <select name="is_active" id="planStatus" class="form-select">
                                        <option value="1">ACTIVE</option>
                                        <option value="0">INACTIVE</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="nx-section-card mb-0">
                            <div class="nx-section-title">
                                <i class="bi bi-speedometer2 text-warning"></i>
                                <span>Service Profile</span>
                            </div>

                            <div class="nx-grid-2">
                                <div>
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
