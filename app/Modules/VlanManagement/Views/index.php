<?php
$summary = $summary ?? [
        'total_vlans' => 0,
        'total_c_vlans' => 0,
        'total_s_vlans' => 0,
        'deployed_count' => 0,
];
?>

<div class="container-fluid nx-page" id="vlanManagementApp">
    <link rel="stylesheet" href="/module-assets/VlanManagement/css/VlanManagement.css">

    <div class="card border-0 shadow-sm mb-4 nx-page-header-card vlan-hero-card">
        <div class="card-body vlan-hero-body">
            <div class="vlan-hero-left">
                <div class="vlan-hero-badge">
                    <i class="bi bi-diagram-3"></i>
                    <span>OLT VLAN Deployment</span>
                </div>

                <h1 class="vlan-hero-title">VLAN Management</h1>

                <p class="vlan-hero-text">
                    Create, deploy, review, and remove C-VLAN and S-VLAN definitions directly on the OLT for subscriber and QinQ service delivery, and manage the ACS MGMT-VLAN per OLT.
                </p>
            </div>

            <div class="vlan-hero-actions">
                <button type="button" class="btn btn-light border" id="btnRefreshVlanManagement">
                    <i class="bi bi-arrow-clockwise"></i>
                    Refresh
                </button>

                <button type="button" class="btn btn-primary" id="btnAddVlan">
                    <i class="bi bi-plus-lg"></i>
                    New VLAN
                </button>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="nx-summary-card nx-summary-primary">
                <div class="nx-summary-top">
                    <div>
                        <div class="nx-summary-label">Total VLANs</div>
                        <div class="nx-summary-value" id="summaryTotalVlans"><?= (int)$summary['total_vlans'] ?></div>
                        <div class="nx-summary-text">All deployable VLAN definitions stored in NexusBox.</div>
                    </div>
                    <div class="nx-summary-icon">
                        <i class="bi bi-diagram-3"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="nx-summary-card nx-summary-cyan">
                <div class="nx-summary-top">
                    <div>
                        <div class="nx-summary-label">C-VLAN</div>
                        <div class="nx-summary-value" id="summaryTotalCVlans"><?= (int)$summary['total_c_vlans'] ?></div>
                        <div class="nx-summary-text">Customer-side VLAN definitions.</div>
                    </div>
                    <div class="nx-summary-icon">
                        <i class="bi bi-person-badge"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="nx-summary-card nx-summary-slate">
                <div class="nx-summary-top">
                    <div>
                        <div class="nx-summary-label">S-VLAN</div>
                        <div class="nx-summary-value" id="summaryTotalSVlans"><?= (int)$summary['total_s_vlans'] ?></div>
                        <div class="nx-summary-text">Service-side QinQ VLAN definitions.</div>
                    </div>
                    <div class="nx-summary-icon">
                        <i class="bi bi-layers"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="nx-summary-card nx-summary-success">
                <div class="nx-summary-top">
                    <div>
                        <div class="nx-summary-label">Deployed</div>
                        <div class="nx-summary-value" id="summaryDeployedCount"><?= (int)$summary['deployed_count'] ?></div>
                        <div class="nx-summary-text">Successfully pushed to the OLT.</div>
                    </div>
                    <div class="nx-summary-icon">
                        <i class="bi bi-check2-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3 nx-toolbar-card">
        <div class="card-body">
            <div class="vlan-toolbar-grid">
                <div class="vlan-toolbar-left">
                    <div class="nx-segment-control">
                        <button type="button" class="nx-segment-item active" data-tab="cvlan">C-VLAN</button>
                        <button type="button" class="nx-segment-item" data-tab="svlan">S-VLAN</button>
                        <button type="button" class="nx-segment-item" data-tab="mgmtvlan">MGMT-VLAN</button>
                    </div>
                </div>

                <div class="vlan-search-wrap">
                    <i class="bi bi-search vlan-search-icon"></i>
                    <input
                            type="text"
                            id="vlanManagementSearch"
                            class="form-control vlan-toolbar-control vlan-search-control"
                            placeholder="Search VLAN ID, name, status, output..."
                    >
                </div>

                <div class="vlan-toolbar-meta">

                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm nx-content-card" data-tab-pane="cvlan">
        <div class="subs-table-topline"></div>

        <div class="card-body border-bottom">
            <div class="fw-semibold">C-VLAN</div>
            <div class="text-muted small">Customer-side VLAN definitions and deployment state.</div>
        </div>

        <div class="table-responsive nx-table-wrap">
            <table class="table align-middle mb-0" id="cVlanTable">
                <thead>
                <tr>
                    <th class="ps-4">VLAN</th>
                    <th>Status</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Deployed At</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
                </thead>
                <tbody id="cVlanTbody">
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">Loading C-VLAN records...</td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card border-0 shadow-sm nx-content-card d-none" data-tab-pane="svlan">
        <div class="subs-table-topline"></div>

        <div class="card-body border-bottom">
            <div class="fw-semibold">S-VLAN</div>
            <div class="text-muted small">Service-side QinQ VLAN definitions and deployment state.</div>
        </div>

        <div class="table-responsive nx-table-wrap">
            <table class="table align-middle mb-0" id="sVlanTable">
                <thead>
                <tr>
                    <th class="ps-4">VLAN</th>
                    <th>Status</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Deployed At</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
                </thead>
                <tbody id="sVlanTbody">
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">Loading S-VLAN records...</td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card border-0 shadow-sm nx-content-card d-none" data-tab-pane="mgmtvlan">
        <div class="subs-table-topline"></div>

        <div class="card-body border-bottom d-flex justify-content-between align-items-center gap-3">
            <div>
                <div class="fw-semibold">MGMT-VLAN</div>
                <div class="text-muted small">TR069 VLAN used for ONT onboarding and management connectivity per OLT.</div>
            </div>

            <button type="button" class="btn btn-primary" id="btnAddMgmtVlan">
                <i class="bi bi-plus-lg"></i>
                Set MGMT-VLAN
            </button>
        </div>

        <div class="table-responsive nx-table-wrap">
            <table class="table align-middle mb-0" id="mgmtVlanTable">
                <thead>
                <tr>
                    <th class="ps-4">OLT</th>
                    <th>MGMT-VLAN</th>
                    <th>Description</th>
                    <th>Created At</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
                </thead>
                <tbody id="mgmtVlanTbody">
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">Loading MGMT-VLAN records...</td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="/module-assets/VlanManagement/js/VlanManagement.js"></script>