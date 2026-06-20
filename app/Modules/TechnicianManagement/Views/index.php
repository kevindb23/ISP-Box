<div class="container-fluid nx-page technician-management-page" data-technician-management-page="index">

    <link rel="stylesheet" href="/module-assets/TechnicianManagement/css/TechnicianManagement.css">

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="text-primary small fw-bold text-uppercase">
                    <i class="bi bi-person-workspace"></i>
                    Field Operations
                </div>
                <h5 class="mb-0 fw-semibold">Technician Management</h5>
                <small class="text-muted">Monitor technician availability, workload, and assigned work orders.</small>
            </div>

            <button id="technicianRefreshBtn" class="btn btn-light border">
                <i class="bi bi-arrow-clockwise"></i>
                Refresh
            </button>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Total Technicians</div>
                    <div class="fs-4 fw-bold" id="technicianTotalCount">0</div>
                    <div class="small text-muted">Registered technician users</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Available</div>
                    <div class="fs-4 fw-bold text-success" id="technicianAvailableCount">0</div>
                    <div class="small text-muted">Ready for dispatch</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">On Site</div>
                    <div class="fs-4 fw-bold text-primary" id="technicianOnSiteCount">0</div>
                    <div class="small text-muted">Currently on field work</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Offline</div>
                    <div class="fs-4 fw-bold text-secondary" id="technicianOfflineCount">0</div>
                    <div class="small text-muted">No active attendance today</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2">
                <div class="col-12 col-md-6">
                    <input id="technicianSearchInput" class="form-control" placeholder="Search technician name, username, or email">
                </div>

                <div class="col-12 col-md-3">
                    <select id="technicianStatusFilter" class="form-select">
                        <option value="">All Status</option>
                        <option value="AVAILABLE">Available</option>
                        <option value="BUSY">Busy</option>
                        <option value="ON_SITE">On Site</option>
                        <option value="TRAVELING">Traveling</option>
                        <option value="ON_BREAK">On Break</option>
                        <option value="OFFLINE">Offline</option>
                    </select>
                </div>

                <div class="col-12 col-md-3">
                    <button id="technicianClearFilterBtn" class="btn btn-light border w-100">
                        Clear Filters
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="technicianManagementAlert"></div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div id="technicianTableHost">
                <div class="text-center py-5 text-muted">Loading technicians...</div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="technicianDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-0" id="technicianModalTitle">Technician Details</h5>
                        <small class="text-muted" id="technicianModalSubtitle"></small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div id="technicianDetailsContent">
                        <div class="text-center py-5 text-muted">Loading details...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="/module-assets/TechnicianManagement/js/TechnicianManagement.js"></script>
</div>