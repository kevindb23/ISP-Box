<div id="auditPage" class="container-fluid nx-page">

    <!-- PAGE HEADER -->
    <div class="card border-0 shadow-sm mb-3 nx-page-header-card">
        <div class="card-body nx-page-header">
            <div class="nx-page-header-left">
                <div class="audit-hero-badge">
                    <i class="bi bi-journal-text"></i>
                    Security & Compliance
                </div>

                <h5 class="nx-page-title">Audit Logs</h5>

                <p class="nx-page-subtitle mb-0">
                    View system activity, user actions, and operational history across NexusBox.
                </p>
            </div>

            <div class="nx-page-actions">
                <button class="btn btn-light border nx-header-btn" type="button" id="refreshAuditBtn">
                    <i class="bi bi-arrow-clockwise"></i>
                    <span>Refresh</span>
                </button>

                <button class="btn btn-primary nx-header-btn" type="button" id="exportAuditBtn">
                    <i class="bi bi-download"></i>
                    <span>Export</span>
                </button>
            </div>
        </div>
    </div>

    <!-- FILTERS -->
    <div class="card border-0 shadow-sm mb-3 nx-toolbar-card">
        <div class="card-body">

            <div class="row g-3">

                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input
                        type="text"
                        class="form-control"
                        id="auditUserFilter"
                        placeholder="Search user / module / action / description / IP"
                    >
                </div>

                <div class="col-md-2">
                    <label class="form-label">Module</label>
                    <select class="form-select" id="auditModuleFilter">
                        <option value="">All Modules</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Action</label>
                    <select class="form-select" id="auditActionFilter">
                        <option value="">All Actions</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">From</label>
                    <input type="date" class="form-control" id="auditDateFrom">
                </div>

                <div class="col-md-2">
                    <label class="form-label">To</label>
                    <input type="date" class="form-control" id="auditDateTo">
                </div>

                <div class="col-md-1 d-flex align-items-end">
                    <button class="btn btn-primary w-100" id="applyAuditFilterBtn">
                        Apply
                    </button>
                </div>

            </div>

        </div>
    </div>

    <!-- SUMMARY -->
    <div class="row g-3 mb-3">

        <div class="col-md-3">
            <div class="audit-summary-card">
                <div class="audit-summary-label">Total Logs</div>
                <div class="audit-summary-value" id="auditTotalLogs">0</div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="audit-summary-card">
                <div class="audit-summary-label">Today</div>
                <div class="audit-summary-value" id="auditTodayLogs">0</div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="audit-summary-card">
                <div class="audit-summary-label">This Week</div>
                <div class="audit-summary-value" id="auditWeekLogs">0</div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="audit-summary-card">
                <div class="audit-summary-label">This Month</div>
                <div class="audit-summary-value" id="auditMonthLogs">0</div>
            </div>
        </div>

    </div>

    <!-- TABLE -->
    <div class="card border-0 shadow-sm nx-content-card">
        <div class="card-body">
            <div id="auditContentArea">
                <div class="text-center py-5 text-muted">
                    Loading audit logs...
                </div>
            </div>
        </div>
    </div>

</div>

<link rel="stylesheet" href="/module-assets/Audit/css/Audit.css?v=2">
<script src="/module-assets/Audit/js/Audit.js?v=6"></script>