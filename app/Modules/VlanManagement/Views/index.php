<?php
$vlanLegacyUi = isset($_GET['legacy_ui']) && (string)$_GET['legacy_ui'] === '1';
if (!$vlanLegacyUi):
    $nxNextManifestPath = BASE_PATH . '/public/build-next/.vite/manifest.json';
    $nxNextManifest = is_file($nxNextManifestPath) ? (json_decode((string)file_get_contents($nxNextManifestPath), true) ?: []) : [];
    $nxNextEntry = $nxNextManifest['src/main.ts'] ?? [];
    $nxNextVersion = is_file($nxNextManifestPath) ? (string)filemtime($nxNextManifestPath) : (string)time();
    foreach (($nxNextEntry['css'] ?? []) as $nxNextCss): ?>
        <link rel="stylesheet" href="/build-next/<?= htmlspecialchars(ltrim((string)$nxNextCss, '/'), ENT_QUOTES, 'UTF-8') ?>?v=<?= htmlspecialchars($nxNextVersion, ENT_QUOTES, 'UTF-8') ?>">
    <?php endforeach; ?>
    <div class="container-fluid nx-page" data-nx-next-root="vlans"></div>
    <?php if (!empty($nxNextEntry['file'])): ?>
        <script type="module" src="/build-next/<?= htmlspecialchars(ltrim((string)$nxNextEntry['file'], '/'), ENT_QUOTES, 'UTF-8') ?>?v=<?= htmlspecialchars($nxNextVersion, ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php else: ?>
        <div class="alert alert-warning">The new VLAN interface is not built. Use <a href="/vlan-management?legacy_ui=1">the legacy interface</a>.</div>
    <?php endif;
    return;
endif;

$summary = $summary ?? [
        'total_vlans' => 0,
        'total_c_vlans' => 0,
        'total_s_vlans' => 0,
        'deployed_count' => 0,
];
?>

<div class="container-fluid nx-page" id="vlanManagementApp">
    <div class="card border-0 shadow-sm mb-4 nx-page-header-card page-hero-card">
        <div class="card-body page-hero-body">
            <div class="page-hero-left">
                <div class="page-hero-badge">
                    <i class="bi bi-diagram-3"></i>
                    <span>OLT VLAN Deployment</span>
                </div>

                <h1 class="page-hero-title">VLAN Management</h1>

                <p class="page-hero-text">
                    Create, deploy, review, and remove C-VLAN and S-VLAN definitions directly on the OLT for subscriber and QinQ service delivery, and manage the ACS MGMT-VLAN per OLT.
                </p>
            </div>

            <div class="page-hero-actions">
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

    <div class="nx-toolbar-card mb-3">
            <div class="toolbar-grid">
                <div class="toolbar-left">
                    <div class="nx-segment-control">
                        <button type="button" class="nx-segment-control__item active" data-tab="cvlan">C-VLAN</button>
                        <button type="button" class="nx-segment-control__item" data-tab="svlan">S-VLAN</button>
                        <button type="button" class="nx-segment-control__item" data-tab="mgmtvlan">MGMT-VLAN</button>
                    </div>
                </div>

                <div class="vlan-search-wrap">
                    <i class="bi bi-search vlan-search-icon"></i>
                    <input
                            type="text"
                            id="vlanManagementSearch"
                            class="form-control toolbar-control vlan-search-control"
                            placeholder="Search VLAN ID, name, status, output..."
                    >
                </div>

                <div class="toolbar-meta">

                </div>
            </div>
    </div>

    <div class="card border-0 shadow-sm nx-content-card" data-tab-pane="cvlan">
        <div class="subs-table-topline"></div>

        <div class="card-body border-bottom">
            <div class="fw-semibold">C-VLAN</div>
            <div class="text-muted small">Customer-side VLAN definitions and deployment state.</div>
        </div>

        <div id="cVlanTableView"></div>
    </div>

    <div class="card border-0 shadow-sm nx-content-card d-none" data-tab-pane="svlan">
        <div class="subs-table-topline"></div>

        <div class="card-body border-bottom">
            <div class="fw-semibold">S-VLAN</div>
            <div class="text-muted small">Service-side QinQ VLAN definitions and deployment state.</div>
        </div>

        <div id="sVlanTableView"></div>
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

        <div id="mgmtVlanTableView"></div>
    </div>
</div>

<script src="/module-assets/VlanManagement/js/VlanManagement.js"></script>
