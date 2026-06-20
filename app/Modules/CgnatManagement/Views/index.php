<?php
$pools = $pools ?? [];
$deployments = $deployments ?? [];
$usage = $usage ?? [];
?>

<div class="container-fluid nx-page" id="cgnatManagementApp">
    <link rel="stylesheet" href="/module-assets/CgnatManagement/css/CgnatManagement.css">

    <div class="card border-0 shadow-sm mb-4 nx-page-header-card cgnat-hero-card">
        <div class="card-body cgnat-hero-body">
            <div class="cgnat-hero-left">
                <div class="cgnat-hero-badge">
                    <i class="bi bi-router"></i>
                    <span>BNG Runtime Automation</span>
                </div>

                <h1 class="cgnat-hero-title">CGNAT / BNG Management</h1>
                <p class="cgnat-hero-text">
                    Configure the BNG host, detect interfaces, and create Linux S-VLAN interfaces used by ACCEL-PPP QinQ subscribers.
                </p>
            </div>

            <div class="cgnat-hero-actions">
                <button type="button" class="btn btn-light border" id="cgnatDetectBtn">
                    <i class="bi bi-broadcast-pin me-1"></i> Detect Runtime
                </button>
                <button type="button" class="btn btn-primary" id="cgnatBngSaveBtn">
                    <i class="bi bi-save me-1"></i> Save BNG
                </button>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="nx-summary-card nx-summary-primary">
                <div class="nx-summary-top">
                    <div>
                        <div class="nx-summary-title">BNG Host</div>
                        <div class="nx-summary-value" id="cgnatSummaryStatus">-</div>
                        <div class="nx-summary-subtitle">Configured BNG SSH target</div>
                    </div>
                    <div class="nx-summary-icon">
                        <i class="bi bi-hdd-network"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="nx-summary-card nx-summary-slate">
                <div class="nx-summary-top">
                    <div>
                        <div class="nx-summary-title">BNG Parent Interface</div>
                        <div class="nx-summary-value" id="cgnatSummaryConfigured">-</div>
                        <div class="nx-summary-subtitle">Example: ens17</div>
                    </div>
                    <div class="nx-summary-icon">
                        <i class="bi bi-ethernet"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="nx-summary-card nx-summary-success">
                <div class="nx-summary-top">
                    <div>
                        <div class="nx-summary-title">VLAN Mode</div>
                        <div class="nx-summary-value" id="cgnatSummaryRules">QINQ</div>
                        <div class="nx-summary-subtitle">Outer S-VLAN interface creation</div>
                    </div>
                    <div class="nx-summary-icon">
                        <i class="bi bi-layers"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="nx-segment-control mb-4" role="tablist">
        <button class="nx-segment-control__item is-active" data-cgnat-tab-btn="bng" type="button">BNG Settings</button>
        <button class="nx-segment-control__item" data-cgnat-tab-btn="svlan" type="button">S-VLAN Interfaces</button>
        <button class="nx-segment-control__item" data-cgnat-tab-btn="pools" type="button">NAT Pools</button>
        <button class="nx-segment-control__item" data-cgnat-tab-btn="runtime" type="button">Runtime</button>
    </div>

    <div data-cgnat-tab="bng">
        <div class="card border-0 shadow-sm nx-content-card mb-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-4">
                    <div>
                        <h3 class="nx-section-title mb-1">BNG Connection</h3>
                        <div class="nx-section-subtitle">
                            Define SSH access and the physical parent interface that receives QinQ traffic from the OLT.
                        </div>
                    </div>

                    <div class="nx-toolbar-inline">
                        <button type="button" class="btn btn-light border" id="cgnatBngTestBtn">
                            <i class="bi bi-plug me-1"></i> Test Connection
                        </button>
                        <button type="button" class="btn btn-primary" id="cgnatBngSaveBtnSecondary">
                            <i class="bi bi-save me-1"></i> Save BNG
                        </button>
                    </div>
                </div>

                <form id="cgnatBngForm" class="row g-3">
                    <div class="col-12 col-md-2">
                        <label class="form-label">Enabled</label>
                        <select class="form-select" name="enabled">
                            <option value="1">Enabled</option>
                            <option value="0">Disabled</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label">BNG Host / IP</label>
                        <input class="form-control" name="host" placeholder="10.0.10.147">
                    </div>

                    <div class="col-12 col-md-2">
                        <label class="form-label">SSH Port</label>
                        <input class="form-control" name="port" placeholder="22">
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label">Username</label>
                        <input class="form-control" name="username" placeholder="root">
                    </div>

                    <div class="col-12 col-md-3">
                        <label class="form-label">Auth Type</label>
                        <select class="form-select" name="auth_type" id="cgnatBngAuthType">
                            <option value="PASSWORD">PASSWORD</option>
                            <option value="KEY">KEY</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-5" id="cgnatBngPasswordWrap">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" placeholder="Leave blank to keep existing">
                    </div>

                    <div class="col-12 col-md-5 d-none" id="cgnatBngKeyPathWrap">
                        <label class="form-label">SSH Key Path</label>
                        <input class="form-control" name="ssh_key_path" placeholder="/home/www-data/.ssh/id_rsa">
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label">BNG Parent Interface</label>
                        <input class="form-control" name="bng_parent_interface" placeholder="ens17">
                        <div class="form-text">This creates interfaces like ens17.3001.</div>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label">Preferred Egress Interface</label>
                        <input class="form-control" name="preferred_interface" placeholder="ens16">
                        <div class="form-text">Used for CGNAT/public egress detection.</div>
                    </div>

                    <div class="col-12 col-md-2">
                        <label class="form-label">VLAN Mode</label>
                        <select class="form-select" name="vlan_mode">
                            <option value="QINQ">QINQ</option>
                            <option value="DOT1Q">DOT1Q</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-2">
                        <label class="form-label">Auto Create</label>
                        <select class="form-select" name="auto_create_svlan_interface">
                            <option value="1">Enabled</option>
                            <option value="0">Disabled</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Remarks</label>
                        <input class="form-control" name="remarks" placeholder="Optional notes">
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="d-none" data-cgnat-tab="svlan">
        <div class="card border-0 shadow-sm nx-content-card mb-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-4">
                    <div>
                        <h3 class="nx-section-title mb-1">S-VLAN Interface Creator</h3>
                        <div class="nx-section-subtitle">
                            Create the Linux outer VLAN interface only for S-VLANs already defined in VLAN Management.
                        </div>
                    </div>
                </div>

                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-4">
                        <label class="form-label">S-VLAN ID</label>
                        <select class="form-select" id="cgnatSvlanId"></select>
                        <div class="form-text">Must exist as S_VLAN in VLAN Management.</div>
                    </div>

                    <div class="col-12 col-md-4">
                        <button type="button" class="btn btn-primary w-100" id="cgnatCreateSvlanInterfaceBtn">
                            <i class="bi bi-plus-circle me-1"></i> Create / Verify Interface
                        </button>
                    </div>

                    <div class="col-12 col-md-4">
                        <button type="button" class="btn btn-light border w-100" id="cgnatDetectBtnSecondary">
                            <i class="bi bi-arrow-clockwise me-1"></i> Refresh Runtime
                        </button>
                    </div>
                </div>

                <div class="mt-4" id="cgnatSvlanResultBox">
                    <div class="nx-empty-inline">No S-VLAN interface action yet.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-none" data-cgnat-tab="pools">
        <div class="card border-0 shadow-sm mb-3 nx-toolbar-card">
            <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div>
                    <h3 class="nx-section-title mb-1">NAT Pools</h3>
                    <div class="nx-section-subtitle">Keep NAT pool records for CGNAT range tracking.</div>
                </div>

                <div class="nx-toolbar-inline">
                    <input type="text" id="cgnatPoolsSearch" class="form-control" placeholder="Search NAT pools...">
                    <button type="button" class="btn btn-primary" id="cgnatPoolAddBtn">
                        <i class="bi bi-plus-lg me-1"></i> Add NAT Pool
                    </button>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm nx-content-card mb-4">
            <div class="card-body p-0">
                <div class="table-responsive nx-table-wrap">
                    <table class="table table-hover align-middle mb-0" id="cgnatPoolsTable">
                        <thead>
                        <tr>
                            <th class="ps-4">Pool</th>
                            <th>Type</th>
                            <th>Network</th>
                            <th>Range</th>
                            <th>Gateway</th>
                            <th>ACCEL Pool</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                        </thead>
                        <tbody id="cgnatPoolsTbody">
                        <?php if (empty($pools)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No NAT pools found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pools as $pool): ?>
                                <tr data-pool-id="<?= (int)($pool['id'] ?? 0) ?>">
                                    <td class="ps-4">
                                        <div class="fw-semibold"><?= htmlspecialchars((string)($pool['pool_name'] ?? '')) ?></div>
                                        <div class="small text-muted"><?= htmlspecialchars((string)($pool['remarks'] ?? '')) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars((string)($pool['type'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars((string)($pool['network'] ?? '')) ?></td>
                                    <td>
                                        <?= htmlspecialchars((string)($pool['range_start'] ?? '')) ?>
                                        <?php if (!empty($pool['range_start']) || !empty($pool['range_end'])): ?> - <?php endif; ?>
                                        <?= htmlspecialchars((string)($pool['range_end'] ?? '')) ?>
                                    </td>
                                    <td><?= htmlspecialchars((string)($pool['gateway'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars((string)($pool['accel_pool_name'] ?? '')) ?></td>
                                    <td>
                                        <span class="badge text-bg-light border"><?= htmlspecialchars((string)($pool['status'] ?? '')) ?></span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-secondary cgnat-edit-pool-btn" data-pool-id="<?= (int)($pool['id'] ?? 0) ?>">Edit</button>
                                            <button type="button" class="btn btn-outline-primary cgnat-preview-btn" data-pool-id="<?= (int)($pool['id'] ?? 0) ?>">Preview</button>
                                            <button type="button" class="btn btn-outline-success cgnat-apply-btn" data-pool-id="<?= (int)($pool['id'] ?? 0) ?>">Apply</button>
                                            <button type="button" class="btn btn-outline-danger cgnat-delete-btn" data-pool-id="<?= (int)($pool['id'] ?? 0) ?>">Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="d-none" data-cgnat-tab="runtime">
        <div class="card border-0 shadow-sm nx-content-card mb-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-3">
                    <div>
                        <h3 class="nx-section-title mb-1">Runtime Snapshot</h3>
                        <div class="nx-section-subtitle">Detected BNG interfaces, routes, and NAT runtime details.</div>
                    </div>
                    <button type="button" class="btn btn-light border" id="cgnatDetectBtnRuntime">
                        <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                    </button>
                </div>

                <div id="cgnatRuntimeBox">
                    <div class="nx-empty-inline">Click Detect Runtime to load BNG state.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- NAT Pool Modal -->
    <div class="modal fade" id="cgnatPoolModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content nx-modal-content">
                <div class="modal-header nx-modal-header">
                    <h5 class="modal-title" id="cgnatPoolModalTitle">Add NAT Pool</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <form id="cgnatPoolForm">
                    <div class="modal-body nx-modal-body">
                        <input type="hidden" name="id" id="natPoolId">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Pool Name</label>
                                <input type="text" class="form-control" name="pool_name" id="natPoolName" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Type</label>
                                <select class="form-select" name="type" id="natPoolType" required>
                                    <option value="CGNAT">CGNAT</option>
                                    <option value="PUBLIC">PUBLIC</option>
                                    <option value="MANAGEMENT">MANAGEMENT</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Network / CIDR</label>
                                <input type="text" class="form-control" name="network" id="natPoolNetwork" placeholder="100.64.0.0/24" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Gateway</label>
                                <input type="text" class="form-control" name="gateway" id="natPoolGateway" placeholder="100.64.0.1">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Range Start</label>
                                <input type="text" class="form-control" name="range_start" id="natPoolRangeStart" placeholder="100.64.0.2">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Range End</label>
                                <input type="text" class="form-control" name="range_end" id="natPoolRangeEnd" placeholder="100.64.0.254">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">ACCEL Pool Name</label>
                                <input type="text" class="form-control" name="accel_pool_name" id="natPoolAccelName" placeholder="pool1">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" id="natPoolStatus">
                                    <option value="DRAFT">DRAFT</option>
                                    <option value="ACTIVE">ACTIVE</option>
                                    <option value="INACTIVE">INACTIVE</option>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Remarks</label>
                                <textarea class="form-control" name="remarks" id="natPoolRemarks" rows="3"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer nx-modal-footer">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="cgnatPoolSaveBtn">Save NAT Pool</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div class="modal fade" id="cgnatPreviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content nx-modal-content">
                <div class="modal-header nx-modal-header">
                    <h5 class="modal-title">NAT Deployment Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body nx-modal-body">
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <label class="form-label fw-semibold">ACCEL Preview</label>
                            <pre class="nx-code-block" id="cgnatAccelPreview">No preview loaded.</pre>
                        </div>

                        <div class="col-lg-6">
                            <label class="form-label fw-semibold">FRR Preview</label>
                            <pre class="nx-code-block" id="cgnatFrrPreview">No FRR preview loaded.</pre>
                        </div>
                    </div>
                </div>

                <div class="modal-footer nx-modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.CGNAT_MANAGEMENT_BOOTSTRAP = <?= json_encode([
                'pools' => $pools,
                'deployments' => $deployments,
                'usage' => $usage,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    </script>

    <script src="/module-assets/CgnatManagement/js/CgnatManagement.js"></script>
</div>