<div class="container-fluid nx-page subscriber-portal-page sp-page" data-sp-page="services">

    <link rel="stylesheet" href="/module-assets/SubscriberPortal/css/SubscriberPortal.css">

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