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
<div class="container-fluid nx-page subscriber-portal-page sp-page" data-sp-page="services">
    <div id="subscriberPortalAlert"></div>

    <div class="card border-0 shadow-sm mb-3 sp-page-title-card">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="sp-page-kicker">
                    <i class="bi bi-wifi"></i>
                    Subscriber Services
                </div>
                <h5 class="mb-0 fw-semibold">My Services</h5>
                <small class="text-muted">View your internet plan, service status, and assigned service details.</small>
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
                    <div class="text-muted small">Total Services</div>
                    <div class="fs-4 fw-bold" id="spServicesTotal">0</div>
                    <div class="small text-muted">Linked service accounts</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm sp-stat-card">
                <div class="card-body">
                    <div class="text-muted small">Active Services</div>
                    <div class="fs-4 fw-bold" id="spServicesActive">0</div>
                    <div class="small text-muted">Currently active subscriptions</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm sp-stat-card">
                <div class="card-body">
                    <div class="text-muted small">Nearest Due Date</div>
                    <div class="fs-5 fw-bold" id="spServicesNearestDue">-</div>
                    <div class="small text-muted">Based on your services</div>
                </div>
            </div>
        </div>
    </div>

    <div id="spServicesCards" class="row g-3">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center text-muted py-5">
                    Loading services...
                </div>
            </div>
        </div>
    </div>

    <script src="/module-assets/SubscriberPortal/js/SubscriberPortal.js"></script>
</div>
