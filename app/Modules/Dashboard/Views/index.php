<div class="container-fluid nx-page">
    <link rel="stylesheet" href="/module-assets/Dashboard/css/Dashboard.css">

    <div id="dashboardPage">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
            <div>
                <h1 class="nx-page-title mb-1">Dashboard</h1>
                <div class="text-muted">Realtime OSS / BSS overview</div>
            </div>
            <div>
                <button type="button" class="btn btn-light border" id="dashboardRefreshBtn">
                    <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                </button>
            </div>
        </div>

        <div class="row g-3" id="dashboardKpiGrid">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-muted">
                        Loading dashboard data...
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="/module-assets/Dashboard/js/Dashboard.js"></script>
</div>