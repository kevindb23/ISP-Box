<?php $p=BASE_PATH.'/public/build-next/.vite/manifest.json';$m=is_file($p)?(json_decode((string)file_get_contents($p),true)?:[]):[];$e=$m['src/main.ts']??[];$v=is_file($p)?(string)filemtime($p):(string)time();foreach(($e['css']??[])as$c):?><link rel="stylesheet" href="/build-next/<?=htmlspecialchars(ltrim((string)$c,'/'),ENT_QUOTES,'UTF-8')?>?v=<?=htmlspecialchars($v,ENT_QUOTES,'UTF-8')?>"><?php endforeach;?><div class="container-fluid nx-page" data-nx-next-root="technicians"></div><?php if(!empty($e['file'])):?><script type="module" src="/build-next/<?=htmlspecialchars(ltrim((string)$e['file'],'/'),ENT_QUOTES,'UTF-8')?>?v=<?=htmlspecialchars($v,ENT_QUOTES,'UTF-8')?>"></script><?php else:?><div class="alert alert-warning">The technician interface is not built.</div><?php endif;return;?>
<div class="container-fluid nx-page technician-management-page" id="technicianManagementPage" data-technician-management-page="index">
    <div class="card border-0 shadow-sm mb-3 nx-page-header-card technician-hero-card">
        <div class="card-body nx-page-header technician-hero-body">
            <div>
                <div class="text-primary small fw-bold text-uppercase">
                    <i class="bi bi-person-workspace"></i>
                    Field Operations
                </div>
                <h5 class="mb-0 fw-semibold">Technician Management</h5>
                <small class="text-muted">Monitor technician availability, workload, and assigned work orders.</small>
            </div>

            <button id="technicianRefreshBtn" class="btn btn-light border nx-header-btn">
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

    <div class="card border-0 shadow-sm mb-3 nx-toolbar-card technician-toolbar-card">
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

    <div class="card border-0 shadow-sm nx-content-card content-card">
        <div class="technician-table-topline"></div>
        <div class="card-body">
            <ul class="nav nav-tabs technician-main-tabs mb-3" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#techniciansListPane" type="button">
                        <i class="bi bi-people me-1"></i> Technicians
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#technicianDispatchPane" type="button">
                        <i class="bi bi-send-check me-1"></i> Dispatch Board
                        <span class="badge text-bg-primary ms-1" id="technicianDispatchCount">0</span>
                    </button>
                </li>
            </ul>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="techniciansListPane">
                    <div class="technician-section-head">
                        <div><h2>Technicians</h2><p>Availability, assignments, workload, and field profile</p></div>
                        <div class="technician-record-count" id="technicianRecordCount">0 technicians</div>
                    </div>
                    <div id="technicianTableHost">
                        <div class="text-center py-5 text-muted">Loading technicians...</div>
                    </div>
                </div>
                <div class="tab-pane fade" id="technicianDispatchPane">
                    <div class="technician-section-head">
                        <div><h2>Unassigned Work Orders</h2><p>Assign open field work only to clocked-in, available technicians</p></div>
                    </div>
                    <div id="technicianDispatchContent">
                        <div class="text-center py-5 text-muted">Loading dispatch board...</div>
                    </div>
                </div>
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
