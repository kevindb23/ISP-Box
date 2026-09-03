<?php
$oltPortsLegacyUi = isset($_GET['legacy_ui']) && (string)$_GET['legacy_ui'] === '1';
if (!$oltPortsLegacyUi):
    $selectedOltId = (int)($selectedOltId ?? ($_GET['olt_id'] ?? 0)); $nxNextManifestPath = BASE_PATH . '/public/build-next/.vite/manifest.json'; $nxNextManifest = is_file($nxNextManifestPath) ? (json_decode((string)file_get_contents($nxNextManifestPath), true) ?: []) : []; $nxNextEntry = $nxNextManifest['src/main.ts'] ?? []; $nxNextVersion = is_file($nxNextManifestPath) ? (string)filemtime($nxNextManifestPath) : (string)time();
    foreach (($nxNextEntry['css'] ?? []) as $nxNextCss): ?><link rel="stylesheet" href="/build-next/<?= htmlspecialchars(ltrim((string)$nxNextCss, '/'), ENT_QUOTES, 'UTF-8') ?>?v=<?= htmlspecialchars($nxNextVersion, ENT_QUOTES, 'UTF-8') ?>"><?php endforeach; ?>
    <div class="container-fluid nx-page" data-nx-next-root="olt" data-mode="ports" data-olt-id="<?= $selectedOltId ?>"></div>
    <?php if (!empty($nxNextEntry['file'])): ?><script type="module" src="/build-next/<?= htmlspecialchars(ltrim((string)$nxNextEntry['file'], '/'), ENT_QUOTES, 'UTF-8') ?>?v=<?= htmlspecialchars($nxNextVersion, ENT_QUOTES, 'UTF-8') ?>"></script><?php else: ?><div class="alert alert-warning">The new OLT Ports interface is not built. Use the legacy interface.</div><?php endif; return;
endif;
?>
<div class="container-fluid nx-page" id="oltManagementPage">
    <?php
    $selectedOltId = (int)($selectedOltId ?? ($_GET['olt_id'] ?? 0));
    $selectedSlot = isset($selectedSlot)
            ? $selectedSlot
            : (isset($_GET['selected_slot']) && $_GET['selected_slot'] !== '' ? (int)$_GET['selected_slot'] : null);
    ?>

    <div id="oltManagementApp"
         data-page-mode="ports"
         data-initial-olt-id="<?= (int)$selectedOltId ?>"
         data-initial-slot="<?= $selectedSlot !== null ? (int)$selectedSlot : '' ?>">

        <!-- HERO -->
        <section class="card border-0 mb-0 nx-page-header-card">
            <div class="nx-page-header">
                <div class="nx-page-header-left">
                    <div class="page-hero-badge">
                        <i class="bi bi-diagram-3"></i>
                        <span>Physical & Logical Port Operations</span>
                    </div>

                    <h1 class="nx-page-title">OLT Ports Console</h1>
                    <p class="nx-page-subtitle" id="oltPageSubtitle">
                        Full physical view and operational port management
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

        <!-- CONTEXT -->
        <div id="oltContextChips"></div>

        <!-- PHYSICAL VIEW -->
        <section class="card border-0 nx-content-card" id="oltPhysicalCard">
            <div class="olt-table-topline"></div>

            <div class="card-body">
                <div class="section-head">
                    <div>
                        <h2 class="section-title">Physical View</h2>
                        <p class="section-subtitle">MA5800-X2 front panel with live slot and port coloring</p>
                    </div>
                </div>

                <div class="panel">
                    <div id="oltPhysicalView"></div>
                </div>
            </div>
        </section>

        <!-- PORTS TABLE -->
        <section class="card border-0 nx-content-card" id="oltPortsTableCard">
            <div class="olt-table-topline"></div>

            <div class="card-body">
                <div class="section-head">
                    <div>
                        <h2 class="section-title">OLT Ports</h2>
                        <p class="section-subtitle">Imported ports for the selected OLT with drilldown actions</p>
                    </div>
                </div>

                <div id="oltPortsTableView"></div>
            </div>
        </section>

        <div id="oltDevicesCard" class="d-none"></div>
        <div id="oltDevicesView" class="d-none"></div>
        <div id="oltSlotDetailsCard" class="d-none"></div>
        <div id="oltSlotDetailsView" class="d-none"></div>
    </div>

    <!-- VIEW PORT MODAL -->
    <div class="modal fade" id="viewPortModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered nx-modal">
            <div class="modal-content nx-modal-content">
                <div class="modal-header nx-modal-header">
                    <div>
                        <h5 class="modal-title mb-0">OLT Port Details</h5>
                        <small class="text-muted" id="viewPortSubtitle">-</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body nx-modal-body">
                    <div class="nx-section-card mb-0">
                        <div class="nx-section-title">
                            <i class="bi bi-ethernet"></i>
                            <span>Port Information</span>
                        </div>

                        <div class="olt-detail-grid">
                            <div class="nx-field">
                                <label>OLT Name</label>
                                <div id="viewPortOltName">-</div>
                            </div>

                            <div class="nx-field">
                                <label>Board</label>
                                <div id="viewPortBoard">-</div>
                            </div>

                            <div class="nx-field">
                                <label>Port Path</label>
                                <div id="viewPortPath" class="nx-text-mono">-</div>
                            </div>

                            <div class="nx-field">
                                <label>Port Type</label>
                                <div id="viewPortType">-</div>
                            </div>

                            <div class="nx-field">
                                <label>Link Status</label>
                                <div id="viewPortLink">-</div>
                            </div>

                            <div class="nx-field">
                                <label>Optic Status</label>
                                <div id="viewPortOptic">-</div>
                            </div>

                            <div class="nx-field">
                                <label>Speed</label>
                                <div id="viewPortSpeed">-</div>
                            </div>

                            <div class="nx-field">
                                <label>Duplex</label>
                                <div id="viewPortDuplex">-</div>
                            </div>

                            <div class="nx-field">
                                <label>Active State</label>
                                <div id="viewPortActiveState">-</div>
                            </div>

                            <div class="nx-field">
                                <label id="viewPortSvlanLabel">Assigned SVLAN</label>
                                <div id="viewPortSvlan">-</div>
                            </div>

                            <div class="nx-field" id="viewPortOntCountWrap">
                                <label>ONT Count</label>
                                <div id="viewPortOntCount">-</div>
                            </div>

                            <div class="nx-field" id="viewPortOntOnlineWrap">
                                <label>ONT Online</label>
                                <div id="viewPortOntOnline">-</div>
                            </div>

                            <div class="nx-field" style="grid-column: 1 / -1;">
                                <label>Description</label>
                                <div id="viewPortDescription">-</div>
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

    <!-- CONTROL BOARD VLAN LIST MODAL -->
    <div class="modal fade" id="controlVlanListModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable nx-modal">
            <div class="modal-content nx-modal-content">
                <div class="modal-header nx-modal-header">
                    <div>
                        <h5 class="modal-title mb-0">Allowed VLANs</h5>
                        <small class="text-muted" id="controlVlanListSubtitle">-</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body nx-modal-body">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 olt-vlan-list-table">
                            <thead>
                            <tr>
                                <th>VLAN ID</th>
                                <th>VLAN Name</th>
                                <th>Type</th>
                                <th class="text-end">Actions</th>
                            </tr>
                            </thead>
                            <tbody id="controlVlanListBody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer nx-modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- FETCH PORTS MODAL -->
    <div class="modal fade" id="fetchPortsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered nx-modal">
            <div class="modal-content nx-modal-content">
                <form id="importFetchedPortsForm">
                    <div class="modal-header nx-modal-header">
                        <h5 class="modal-title">Fetched OLT Ports</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body nx-modal-body">
                        <input type="hidden" id="fetchModalOltId" name="olt_id">
                        <input type="hidden" id="fetchPortsJson" name="ports_json">

                        <div class="nx-section-card mb-0">
                            <div class="mb-2 text-muted small" id="fetchSummaryText"></div>
                            <div class="mb-3 text-muted small" id="fetchPortsBoardsInfo"></div>

                            <div class="table-responsive">
                                <table class="table align-middle" id="fetchedPortsTable">
                                    <thead>
                                    <tr>
                                        <th>Port Path</th>
                                        <th>Board</th>
                                        <th>Port Type</th>
                                        <th>Status</th>
                                        <th>Importable</th>
                                        <th>Exists in DB</th>
                                        <th>SVLAN / Allowed SVLANs</th>
                                    </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer nx-modal-footer">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="importFetchedPortsSubmitBtn">
                            <i class="bi bi-download"></i> Import Fetched Ports
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="/module-assets/OltManagement/js/OltManagement.js"></script>
</div>
