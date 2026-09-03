<?php
$oltLegacyUi = isset($_GET['legacy_ui']) && (string)$_GET['legacy_ui'] === '1';
if (!$oltLegacyUi):
    $nxNextManifestPath = BASE_PATH . '/public/build-next/.vite/manifest.json'; $nxNextManifest = is_file($nxNextManifestPath) ? (json_decode((string)file_get_contents($nxNextManifestPath), true) ?: []) : []; $nxNextEntry = $nxNextManifest['src/main.ts'] ?? []; $nxNextVersion = is_file($nxNextManifestPath) ? (string)filemtime($nxNextManifestPath) : (string)time();
    foreach (($nxNextEntry['css'] ?? []) as $nxNextCss): ?><link rel="stylesheet" href="/build-next/<?= htmlspecialchars(ltrim((string)$nxNextCss, '/'), ENT_QUOTES, 'UTF-8') ?>?v=<?= htmlspecialchars($nxNextVersion, ENT_QUOTES, 'UTF-8') ?>"><?php endforeach; ?>
    <div class="container-fluid nx-page" data-nx-next-root="olt" data-mode="devices"></div>
    <?php if (!empty($nxNextEntry['file'])): ?><script type="module" src="/build-next/<?= htmlspecialchars(ltrim((string)$nxNextEntry['file'], '/'), ENT_QUOTES, 'UTF-8') ?>?v=<?= htmlspecialchars($nxNextVersion, ENT_QUOTES, 'UTF-8') ?>"></script><?php else: ?><div class="alert alert-warning">The new OLT interface is not built. Use <a href="/olt-management?legacy_ui=1">the legacy interface</a>.</div><?php endif; return;
endif;
?>

<div class="container-fluid nx-page" id="oltManagementPage">
    <div id="oltManagementApp" data-page-mode="devices">

        <!-- HERO -->
        <section class="card border-0 mb-0 nx-page-header-card">
            <div class="nx-page-header">
                <div class="nx-page-header-left">
                    <div class="page-hero-badge">
                        <i class="bi bi-hdd-network"></i>
                        <span>Access & Uplink Infrastructure</span>
                    </div>

                    <h1 class="nx-page-title">OLT Management</h1>
                    <p class="nx-page-subtitle" id="oltPageSubtitle">
                        Device registry and connection profiles
                    </p>
                </div>

                <div class="nx-page-actions" id="oltHeaderActions">
                    <!-- NX rendered -->
                </div>
            </div>
        </section>

        <!-- SUMMARY -->
        <div class="row g-3 nx-olt-summary-row" id="oltKpiStrip"></div>

        <!-- TOOLBAR -->
        <section class="card border-0 nx-toolbar-card" id="oltToolbarCard">
            <div class="card-body">
                <div id="oltToolbarArea">
                    <!-- NX rendered -->
                </div>
            </div>
        </section>

        <!-- DEVICES TABLE -->
        <section class="card border-0 nx-content-card" id="oltDevicesCard">
            <div class="olt-table-topline"></div>

            <div class="card-body">
                <div class="section-head">
                    <div>
                        <h2 class="section-title">OLT Devices</h2>
                        <p class="section-subtitle">Registered OLT connection profiles and quick actions</p>
                    </div>
                </div>

                <div id="oltDevicesView"></div>
            </div>
        </section>
    </div>

    <!-- CREATE DEVICE MODAL -->
    <div class="modal fade" id="createDeviceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered nx-modal">
            <div class="modal-content nx-modal-content">
                <form id="createOltDeviceForm">
                    <div class="modal-header nx-modal-header">
                        <h5 class="modal-title">Add OLT Device</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body nx-modal-body">
                        <div class="nx-section-card">
                            <div class="nx-section-title">
                                <i class="bi bi-router"></i>
                                <span>Device Profile</span>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Device Name</label>
                                    <input type="text" class="form-control" name="name" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">IP Address</label>
                                    <input type="text" class="form-control" name="ip_address" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Vendor</label>
                                    <input type="text" class="form-control" name="vendor" value="Huawei">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control" name="username" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Password</label>
                                    <input type="password" class="form-control" name="password" required>
                                </div>

                                <div class="col-12">
                                    <div class="nx-section-card bg-light border mt-2 mb-0">
                                        <div class="nx-section-title">
                                            <i class="bi bi-cpu"></i>
                                            <span>Huawei OMCI Options</span>
                                        </div>

                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                   id="createDeviceOmci"
                                                   name="enable_home_gateway_omci"
                                                   value="1">
                                            <label class="form-check-label" for="createDeviceOmci">
                                                Enable Home Gateway OMCI
                                            </label>
                                        </div>

                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                   id="createDeviceOmciAutoDetect"
                                                   name="auto_detect_omci_support"
                                                   value="1"
                                                   checked>
                                            <label class="form-check-label" for="createDeviceOmciAutoDetect">
                                                Auto-detect OMCI support
                                            </label>
                                        </div>

                                        <small class="text-muted d-block mt-2">
                                            Used for Huawei command: <code>gpon ont home-gateway config-method omci</code>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer nx-modal-footer">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Device</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- VIEW DEVICE MODAL -->
    <div class="modal fade" id="viewDeviceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered nx-modal">
            <div class="modal-content nx-modal-content">
                <div class="modal-header nx-modal-header">
                    <div>
                        <h5 class="modal-title mb-0">OLT Device Details</h5>
                        <small class="text-muted" id="viewDeviceSubtitle">-</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body nx-modal-body">
                    <div class="nx-section-card mb-0">
                        <div class="nx-section-title">
                            <i class="bi bi-info-circle"></i>
                            <span>Connection Details</span>
                        </div>

                        <div class="olt-detail-grid">
                            <div class="nx-field">
                                <label>Device Name</label>
                                <div id="viewDeviceName">-</div>
                            </div>

                            <div class="nx-field">
                                <label>IP Address</label>
                                <div id="viewDeviceIp">-</div>
                            </div>

                            <div class="nx-field">
                                <label>Vendor</label>
                                <div id="viewDeviceVendor">-</div>
                            </div>

                            <div class="nx-field">
                                <label>Username</label>
                                <div id="viewDeviceUsername">-</div>
                            </div>

                            <div class="nx-field">
                                <label>Password</label>
                                <div id="viewDevicePassword" class="password-mask">-</div>
                            </div>

                            <div class="nx-field">
                                <label>Home Gateway OMCI</label>
                                <div id="viewDeviceOmci">-</div>
                            </div>

                            <div class="nx-field">
                                <label>OMCI Auto-detect</label>
                                <div id="viewDeviceOmciAutoDetect">-</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer nx-modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- EDIT DEVICE MODAL -->
    <div class="modal fade" id="editDeviceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered nx-modal">
            <div class="modal-content nx-modal-content">
                <form id="editOltDeviceForm">
                    <div class="modal-header nx-modal-header">
                        <div>
                            <h5 class="modal-title mb-0">Edit OLT Device</h5>
                            <small class="text-muted" id="editDeviceSubtitle">-</small>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body nx-modal-body">
                        <div class="nx-section-card">
                            <div class="nx-section-title">
                                <i class="bi bi-pencil-square"></i>
                                <span>Device Profile</span>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Device Name</label>
                                    <input type="text" class="form-control" id="editDeviceName" name="name" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">IP Address</label>
                                    <input type="text" class="form-control" id="editDeviceIp" name="ip_address" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Vendor</label>
                                    <input type="text" class="form-control" id="editDeviceVendor" name="vendor">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control" id="editDeviceUsername" name="username" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Password</label>
                                    <input type="text" class="form-control" id="editDevicePassword" name="password" required>
                                </div>

                                <div class="col-12">
                                    <div class="nx-section-card bg-light border mt-2 mb-0">
                                        <div class="nx-section-title">
                                            <i class="bi bi-cpu"></i>
                                            <span>Huawei OMCI Options</span>
                                        </div>

                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                   id="editDeviceOmci"
                                                   name="enable_home_gateway_omci"
                                                   value="1">
                                            <label class="form-check-label" for="editDeviceOmci">
                                                Enable Home Gateway OMCI
                                            </label>
                                        </div>

                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                   id="editDeviceOmciAutoDetect"
                                                   name="auto_detect_omci_support"
                                                   value="1">
                                            <label class="form-check-label" for="editDeviceOmciAutoDetect">
                                                Auto-detect OMCI support
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer nx-modal-footer">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Device</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="/module-assets/OltManagement/js/OltManagement.js"></script>
</div>
