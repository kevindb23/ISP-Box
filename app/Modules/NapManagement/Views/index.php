<div class="container-fluid nx-page">
    <link rel="stylesheet" href="/module-assets/NapManagement/css/NapManagement.css">

    <style>
        #napManagementApp {
            visibility: hidden;
        }
        #napManagementApp.nap-ready {
            visibility: visible;
        }
    </style>

    <script>
        (function () {
            try {
                var key = 'nap-management-ui-v10';
                var raw = localStorage.getItem(key);
                var saved = raw ? JSON.parse(raw) : {};
                var tab = saved.tab || 'planner';

                window.__NAP_BOOT_STATE__ = saved;
                window.__NAP_BOOT_TAB__ = tab;
            } catch (e) {
                window.__NAP_BOOT_STATE__ = {};
                window.__NAP_BOOT_TAB__ = 'planner';
            }
        })();
    </script>

    <?php
    $tab = $tab ?? ($_GET['tab'] ?? 'planner');
    if (!in_array($tab, ['planner', 'nodes', 'links'], true)) {
        $tab = 'planner';
    }
    ?>

    <div id="napManagementApp" data-initial-tab="<?= htmlspecialchars((string)$tab) ?>">
        <script>
            (function () {
                var app = document.getElementById('napManagementApp');
                if (!app) return;

                var tab = window.__NAP_BOOT_TAB__ || 'planner';
                app.dataset.initialTab = tab;

                document.querySelectorAll('[data-tab-link]').forEach(function (link) {
                    link.classList.toggle('active', link.dataset.tabLink === tab);
                });
            })();
        </script>

        <!-- PAGE HEADER -->
        <div class="card border-0 nx-page-header-card">
            <div class="card-body">
                <div class="d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3">
                    <div>
                        <div class="nx-page-kicker">FTTH Infrastructure</div>
                        <h1 class="nx-page-title mb-1">NAP Management</h1>
                        <div class="nx-page-subtitle" id="napPageSubtitle">
                            Plan, manage, and visualize ODF, LCP, NAP, and physical network link infrastructure.
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 align-items-center" id="napHeaderActions">
                        <span class="nx-soft-badge nx-soft-badge-info">
                            <i class="bi bi-diagram-3 me-1"></i> Visual Planner Ready
                        </span>
                        <span class="nx-soft-badge nx-soft-badge-success">
                            <i class="bi bi-geo-alt me-1"></i> Map Picker Enabled
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- SEGMENT -->
        <div class="nx-page-section">
            <div class="nx-segment-control nx-segment-control-wide">
                <a href="#" class="nx-segment-item <?= $tab === 'planner' ? 'active' : '' ?>" data-tab-link="planner">
                    <i class="bi bi-bezier2 me-1"></i> Visual Planner
                </a>
                <a href="#" class="nx-segment-item <?= $tab === 'nodes' ? 'active' : '' ?>" data-tab-link="nodes">
                    <i class="bi bi-boxes me-1"></i> Nodes
                </a>
                <a href="#" class="nx-segment-item <?= $tab === 'links' ? 'active' : '' ?>" data-tab-link="links">
                    <i class="bi bi-diagram-2 me-1"></i> Links
                </a>
            </div>
        </div>

        <!-- TOP TOOLBAR -->
        <div class="card border-0 nx-toolbar-card">
            <div class="card-body py-3">
                <div id="napToolbarArea"></div>
            </div>
        </div>

        <!-- SUMMARY -->
        <div id="napSummarySection" class="nx-page-section <?= $tab === 'planner' ? 'd-none' : '' ?>">
            <div class="row g-3">
                <div class="col-md-6 col-xl-2">
                    <div class="nx-summary-card nx-summary-cyan">
                        <div class="nx-summary-top">
                            <div>
                                <div class="nx-summary-label">ODF Nodes</div>
                                <div class="nx-summary-value" id="summaryOdfs">0</div>
                            </div>
                            <div class="nx-summary-icon"><i class="bi bi-hdd-network"></i></div>
                        </div>
                        <div class="nx-summary-foot">Optical distribution frames</div>
                    </div>
                </div>

                <div class="col-md-6 col-xl-2">
                    <div class="nx-summary-card nx-summary-info">
                        <div class="nx-summary-top">
                            <div>
                                <div class="nx-summary-label">ODF Ports</div>
                                <div class="nx-summary-value" id="summaryOdfPorts">0 / 0</div>
                            </div>
                            <div class="nx-summary-icon"><i class="bi bi-plug"></i></div>
                        </div>
                        <div class="nx-summary-foot">Used / total ODF ports</div>
                    </div>
                </div>

                <div class="col-md-6 col-xl-2">
                    <div class="nx-summary-card nx-summary-primary">
                        <div class="nx-summary-top">
                            <div>
                                <div class="nx-summary-label">LCP Boxes</div>
                                <div class="nx-summary-value" id="summaryLcps">0</div>
                            </div>
                            <div class="nx-summary-icon"><i class="bi bi-diagram-3"></i></div>
                        </div>
                        <div class="nx-summary-foot">Local convergence points</div>
                    </div>
                </div>

                <div class="col-md-6 col-xl-2">
                    <div class="nx-summary-card nx-summary-warning">
                        <div class="nx-summary-top">
                            <div>
                                <div class="nx-summary-label">LCP Ports</div>
                                <div class="nx-summary-value" id="summaryLcpPorts">0 / 0</div>
                            </div>
                            <div class="nx-summary-icon"><i class="bi bi-plug-fill"></i></div>
                        </div>
                        <div class="nx-summary-foot">Used / total LCP ports</div>
                    </div>
                </div>

                <div class="col-md-6 col-xl-2">
                    <div class="nx-summary-card nx-summary-success">
                        <div class="nx-summary-top">
                            <div>
                                <div class="nx-summary-label">NAP Boxes</div>
                                <div class="nx-summary-value" id="summaryNaps">0</div>
                            </div>
                            <div class="nx-summary-icon"><i class="bi bi-box-seam"></i></div>
                        </div>
                        <div class="nx-summary-foot">Network access points</div>
                    </div>
                </div>

                <div class="col-md-6 col-xl-2">
                    <div class="nx-summary-card nx-summary-danger">
                        <div class="nx-summary-top">
                            <div>
                                <div class="nx-summary-label">NAP Ports</div>
                                <div class="nx-summary-value" id="summaryNapPorts">0 / 0</div>
                            </div>
                            <div class="nx-summary-icon"><i class="bi bi-bezier2"></i></div>
                        </div>
                        <div class="nx-summary-foot">Used / total NAP ports</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CONTENT -->
        <div class="card border-0 nx-content-card">
            <div class="card-body p-0">
                <div id="napContentArea" class="nx-content-body"></div>
            </div>
        </div>
    </div>

    <!-- ODF MODAL -->
    <div class="modal fade nx-modal" id="odfModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content nx-modal-content">
                <div class="modal-header nx-modal-header">
                    <div>
                        <div class="nx-modal-kicker">ODF Configuration</div>
                        <h5 class="modal-title mb-1" id="odfModalTitle">Create ODF</h5>
                        <div class="text-muted small" id="odfModalSubtitle">Add an optical distribution frame.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <form id="odfForm">
                    <div class="modal-body nx-modal-body">
                        <input type="hidden" id="odfIdInput" value="">

                        <div class="alert alert-warning d-none mb-3" id="odfEditNotice">
                            This ODF is in edit mode.
                        </div>

                        <div class="row g-3">
                            <div class="col-lg-7">
                                <div class="nx-section-card h-100">
                                    <div class="nx-section-head">
                                        <div>
                                            <h6 class="nx-section-title mb-1">ODF Details</h6>
                                            <div class="nx-section-subtitle">Basic identity, uplink source, and port setup.</div>
                                        </div>
                                    </div>

                                    <div class="nx-grid-2">
                                        <div class="nx-field">
                                            <label for="odfNameInput">ODF Name</label>
                                            <input type="text" class="form-control" id="odfNameInput" placeholder="Enter ODF name" required>
                                        </div>

                                        <div class="nx-field">
                                            <label for="odfCodeInput">ODF Code</label>
                                            <input type="text" class="form-control" id="odfCodeInput" placeholder="Enter ODF code">
                                        </div>

                                        <div class="nx-field">
                                            <label for="odfPortsInput">ODF Ports</label>
                                            <input type="number" min="1" class="form-control" id="odfPortsInput" placeholder="e.g. 24" required>
                                        </div>

                                        <div class="nx-field">
                                            <label for="odfStatusSelect">Status</label>
                                            <select class="form-select" id="odfStatusSelect">
                                                <option value="ACTIVE">ACTIVE</option>
                                                <option value="INACTIVE">INACTIVE</option>
                                                <option value="MAINTENANCE">MAINTENANCE</option>
                                                <option value="FAULTY">FAULTY</option>
                                            </select>
                                        </div>

                                        <div class="nx-field">
                                            <label for="odfOltSelect">OLT Device</label>
                                            <select class="form-select" id="odfOltSelect">
                                                <option value="">Select OLT</option>
                                            </select>
                                        </div>

                                        <div class="nx-field">
                                            <label for="odfOltPortSelect">OLT Port</label>
                                            <select class="form-select" id="odfOltPortSelect">
                                                <option value="">Select OLT port</option>
                                            </select>
                                        </div>

                                        <div class="nx-field nx-span-2">
                                            <label for="odfInputPortSelect">ODF Input Port</label>
                                            <select class="form-select" id="odfInputPortSelect">
                                                <option value="">Select ODF input port</option>
                                            </select>
                                        </div>

                                        <div class="nx-field nx-span-2">
                                            <label for="odfRemarksInput">Remarks</label>
                                            <textarea class="form-control" id="odfRemarksInput" rows="4" placeholder="Optional remarks"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-5">
                                <div class="nx-section-card h-100">
                                    <div class="nx-section-head">
                                        <div>
                                            <h6 class="nx-section-title mb-1">Map Location</h6>
                                            <div class="nx-section-subtitle">Pin the exact ODF location on the map.</div>
                                        </div>
                                    </div>

                                    <div class="nx-field mb-3">
                                        <label for="odfLocationInput">Location</label>
                                        <input type="text" class="form-control" id="odfLocationInput" placeholder="Enter ODF location or pin on map">
                                    </div>

                                    <div class="nx-grid-2 mb-3">
                                        <div class="nx-field">
                                            <label for="odfLatitudeInput">Latitude</label>
                                            <input type="text" class="form-control" id="odfLatitudeInput" placeholder="e.g. 14.123456">
                                        </div>

                                        <div class="nx-field">
                                            <label for="odfLongitudeInput">Longitude</label>
                                            <input type="text" class="form-control" id="odfLongitudeInput" placeholder="e.g. 120.123456">
                                        </div>
                                    </div>

                                    <div class="nx-field">
                                        <label>Map Picker</label>
                                        <div id="odfMapPicker" class="nx-map-picker nx-map-picker-lg"></div>
                                        <div class="nx-map-picker-hint">
                                            Click the map to drop a pin. Drag the marker to refine the location.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer nx-modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="odfSubmitBtn">
                            <i class="bi bi-save me-1"></i> Save ODF
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- LCP MODAL -->
    <div class="modal fade nx-modal" id="lcpModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content nx-modal-content">
                <div class="modal-header nx-modal-header">
                    <div>
                        <div class="nx-modal-kicker">LCP Configuration</div>
                        <h5 class="modal-title mb-1" id="lcpModalTitle">Create LCP</h5>
                        <div class="text-muted small" id="lcpModalSubtitle">Add a local convergence point.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <form id="lcpForm">
                    <div class="modal-body nx-modal-body">
                        <input type="hidden" id="lcpIdInput" value="">

                        <div class="alert alert-warning d-none mb-3" id="lcpEditNotice">
                            This LCP is in edit mode.
                        </div>

                        <div class="row g-3">
                            <div class="col-lg-7">
                                <div class="nx-section-card h-100">
                                    <div class="nx-section-head">
                                        <div>
                                            <h6 class="nx-section-title mb-1">LCP Details</h6>
                                            <div class="nx-section-subtitle">Parent ODF, splitter sizing, and input mapping.</div>
                                        </div>
                                    </div>

                                    <div class="nx-grid-2">
                                        <div class="nx-field">
                                            <label for="lcpNameInput">LCP Name</label>
                                            <input type="text" class="form-control" id="lcpNameInput" placeholder="Enter LCP name" required>
                                        </div>

                                        <div class="nx-field">
                                            <label for="lcpCodeInput">LCP Code</label>
                                            <input type="text" id="lcpCodeInput" class="form-control" placeholder="e.g. LCP-01">
                                        </div>

                                        <div class="nx-field">
                                            <label for="lcpPortsInput">Splitter Ports</label>
                                            <input type="number" min="1" class="form-control" id="lcpPortsInput" placeholder="e.g. 8 or 16" required>
                                        </div>

                                        <div class="nx-field nx-span-2">
                                            <label for="lcpParentOdfSelect">Parent ODF</label>
                                            <select class="form-select" id="lcpParentOdfSelect">
                                                <option value="">Select ODF</option>
                                            </select>
                                        </div>

                                        <div class="nx-field">
                                            <label for="lcpParentOdfPortSelect">ODF Port</label>
                                            <select class="form-select" id="lcpParentOdfPortSelect">
                                                <option value="">Select ODF port</option>
                                            </select>
                                        </div>

                                        <div class="nx-field">
                                            <label for="lcpInputPortSelect">LCP Input Port</label>
                                            <select class="form-select" id="lcpInputPortSelect">
                                                <option value="">Select LCP input port</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-5">
                                <div class="nx-section-card h-100">
                                    <div class="nx-section-head">
                                        <div>
                                            <h6 class="nx-section-title mb-1">Map Location</h6>
                                            <div class="nx-section-subtitle">Pin the LCP cabinet placement.</div>
                                        </div>
                                    </div>

                                    <div class="nx-field mb-3">
                                        <label for="lcpLocationInput">Location</label>
                                        <input type="text" class="form-control" id="lcpLocationInput" placeholder="Enter LCP location or pin on map">
                                    </div>

                                    <div class="nx-grid-2 mb-3">
                                        <div class="nx-field">
                                            <label for="lcpLatitudeInput">Latitude</label>
                                            <input type="text" class="form-control" id="lcpLatitudeInput" placeholder="e.g. 14.123456">
                                        </div>

                                        <div class="nx-field">
                                            <label for="lcpLongitudeInput">Longitude</label>
                                            <input type="text" class="form-control" id="lcpLongitudeInput" placeholder="e.g. 120.123456">
                                        </div>
                                    </div>

                                    <div class="nx-field">
                                        <label>Map Picker</label>
                                        <div id="lcpMapPicker" class="nx-map-picker nx-map-picker-lg"></div>
                                        <div class="nx-map-picker-hint">
                                            Click the map to drop a pin. Drag the marker to refine the location.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer nx-modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="lcpSubmitBtn">
                            <i class="bi bi-save me-1"></i> Save LCP
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- NAP MODAL -->
    <div class="modal fade nx-modal" id="napModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content nx-modal-content">
                <div class="modal-header nx-modal-header">
                    <div>
                        <div class="nx-modal-kicker">NAP Configuration</div>
                        <h5 class="modal-title mb-1" id="napModalTitle">Create NAP</h5>
                        <div class="text-muted small" id="napModalSubtitle">Add a network access point.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <form id="napForm">
                    <div class="modal-body nx-modal-body">
                        <input type="hidden" id="napIdInput" value="">

                        <div class="alert alert-warning d-none mb-3" id="napEditNotice">
                            This NAP is in edit mode.
                        </div>

                        <div class="row g-3">
                            <div class="col-lg-7">
                                <div class="nx-section-card h-100">
                                    <div class="nx-section-head">
                                        <div>
                                            <h6 class="nx-section-title mb-1">NAP Details</h6>
                                            <div class="nx-section-subtitle">Parent feed, splitter capacity, and service edge mapping.</div>
                                        </div>
                                    </div>

                                    <div class="nx-grid-2">
                                        <div class="nx-field">
                                            <label for="napNameInput">NAP Name</label>
                                            <input type="text" class="form-control" id="napNameInput" placeholder="Enter NAP name" required>
                                        </div>

                                        <div class="nx-field">
                                            <label for="napCodeInput">NAP Code</label>
                                            <input type="text" class="form-control" id="napCodeInput" placeholder="e.g. NAP-01">
                                        </div>

                                        <div class="nx-field">
                                            <label for="napSplitterPortsInput">Splitter Ports</label>
                                            <input type="number" min="1" class="form-control" id="napSplitterPortsInput" placeholder="e.g. 8 or 16" required>
                                        </div>

                                        <div class="nx-field">
                                            <label for="napParentTypeSelect">Parent Type</label>
                                            <select class="form-select" id="napParentTypeSelect">
                                                <option value="LCP">LCP</option>
                                                <option value="NAP">NAP</option>
                                            </select>
                                        </div>

                                        <div class="nx-field">
                                            <label for="napParentBoxSelect">Parent Box</label>
                                            <select class="form-select" id="napParentBoxSelect">
                                                <option value="">Select parent box</option>
                                            </select>
                                        </div>

                                        <div class="nx-field">
                                            <label for="napParentPortSelect">Parent Port</label>
                                            <select class="form-select" id="napParentPortSelect">
                                                <option value="">Select parent port</option>
                                            </select>
                                        </div>

                                        <div class="nx-field nx-span-2">
                                            <label for="napFeedModeSelect">Feed Mode</label>
                                            <select class="form-select" id="napFeedModeSelect">
                                                <option value="DIRECT_FROM_LCP">DIRECT_FROM_LCP</option>
                                                <option value="CASCADE_FROM_NAP">CASCADE_FROM_NAP</option>
                                                <option value="FIBER">FIBER</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-5">
                                <div class="nx-section-card h-100">
                                    <div class="nx-section-head">
                                        <div>
                                            <h6 class="nx-section-title mb-1">Map Location</h6>
                                            <div class="nx-section-subtitle">Pin the exact NAP box location.</div>
                                        </div>
                                    </div>

                                    <div class="nx-field mb-3">
                                        <label for="napLocationInput">Location</label>
                                        <input type="text" class="form-control" id="napLocationInput" placeholder="Enter NAP location or pin on map">
                                    </div>

                                    <div class="nx-grid-2 mb-3">
                                        <div class="nx-field">
                                            <label for="napLatitudeInput">Latitude</label>
                                            <input type="text" class="form-control" id="napLatitudeInput" placeholder="e.g. 14.123456">
                                        </div>

                                        <div class="nx-field">
                                            <label for="napLongitudeInput">Longitude</label>
                                            <input type="text" class="form-control" id="napLongitudeInput" placeholder="e.g. 120.123456">
                                        </div>
                                    </div>

                                    <div class="nx-field">
                                        <label>Map Picker</label>
                                        <div id="napMapPicker" class="nx-map-picker nx-map-picker-lg"></div>
                                        <div class="nx-map-picker-hint">
                                            Click the map to drop a pin. Drag the marker to refine the location.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer nx-modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="napSubmitBtn">
                            <i class="bi bi-save me-1"></i> Save NAP
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- BOX VIEWER -->
    <div class="modal fade nx-modal" id="boxViewerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content nx-modal-content">
                <div class="modal-header nx-modal-header">
                    <div class="d-flex align-items-start justify-content-between w-100 gap-3">
                        <div class="min-w-0">
                            <div class="nx-modal-kicker">Infrastructure Viewer</div>
                            <h5 class="modal-title mb-1" id="boxViewerTitle">Node Viewer</h5>
                            <div class="text-muted small" id="boxViewerSubtitle">Inspect node details and physical ports.</div>
                        </div>

                        <button type="button"
                                class="btn-close flex-shrink-0"
                                data-bs-dismiss="modal"
                                aria-label="Close"></button>
                    </div>
                </div>

                <div class="modal-body nx-modal-body">
                    <div class="box-viewer-stage">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6 col-lg-3">
                                <div class="nx-meta-tile">
                                    <div class="nx-meta-label">Type</div>
                                    <div class="nx-meta-value" id="boxViewerType">-</div>
                                </div>
                            </div>

                            <div class="col-md-6 col-lg-3">
                                <div class="nx-meta-tile">
                                    <div class="nx-meta-label">Name</div>
                                    <div class="nx-meta-value" id="boxViewerName">-</div>
                                </div>
                            </div>

                            <div class="col-md-6 col-lg-3">
                                <div class="nx-meta-tile">
                                    <div class="nx-meta-label">Location</div>
                                    <div class="nx-meta-value" id="boxViewerLocation">-</div>
                                </div>
                            </div>

                            <div class="col-md-6 col-lg-3">
                                <div class="nx-meta-tile">
                                    <div class="nx-meta-label">Status</div>
                                    <div class="nx-meta-value" id="boxViewerStatus">-</div>
                                </div>
                            </div>
                        </div>

                        <div class="cabinet-scene">
                            <div class="cabinet" id="boxViewerCabinet">
                                <div class="cabinet-badge">
                                    <div class="cabinet-badge-name" id="boxViewerBadgeName">NODE</div>
                                    <div class="cabinet-badge-type" id="boxViewerBadgeType">TYPE</div>
                                </div>

                                <div class="cabinet-inside">
                                    <div class="cabinet-inside-top">
                                        <div class="splitter-panel">
                                            <div>
                                                <div class="splitter-panel-title">Port Capacity</div>
                                                <div class="splitter-panel-ratio" id="boxViewerRatio">-</div>
                                            </div>
                                        </div>

                                        <div class="splitter-status-legend">
                                            <span class="legend-chip"><span class="legend-dot green"></span> Available</span>
                                            <span class="legend-chip"><span class="legend-dot red"></span> Used</span>
                                            <span class="legend-chip"><span class="legend-dot orange"></span> Reserved</span>
                                            <span class="legend-chip"><span class="legend-dot gray"></span> Maintenance</span>
                                        </div>
                                    </div>

                                    <div id="boxViewerPortsArea"></div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3" id="boxViewerExtraInfo"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- PLANNER TEMPLATE -->
    <template id="napPlannerTemplate">
        <div class="nx-workspace-card nx-workspace-card-full">
            <div class="nx-workspace-canvas-container nx-workspace-canvas-container-xl">

                <div class="nx-floating-panel nx-floating-toolbar" id="plannerTopbar">
                    <div class="nx-floating-toolbar-row">
                        <div class="nx-floating-toolbar-main">
                            <button type="button" class="nx-panel-drag-handle" id="plannerDragHandle" title="Drag toolbar">
                                <i class="bi bi-grip-vertical"></i>
                            </button>

                            <div class="nx-toggle-group">
                                <button type="button" class="active" data-planner-view="logical">Logical View</button>
                                <button type="button" data-planner-view="map">Map View</button>
                            </div>

                            <div class="nx-floating-toolbar-center">
                                <div class="nx-toggle-group" id="plannerScopeToggle">
                                    <button type="button" class="active" data-planner-scope="ALL">View All</button>
                                    <button type="button" data-planner-scope="OLT">View By OLT</button>
                                </div>

                                <select id="plannerOltSelect" class="nx-toolbar-select-md">
                                    <option value="">OLT Device</option>
                                </select>

                                <select id="plannerOltPortSelect" class="nx-toolbar-select-sm">
                                    <option value="">OLT Port</option>
                                </select>
                            </div>

                            <div class="nx-floating-toolbar-actions">
                                <button type="button" class="nx-tool-icon nx-tool-icon-primary" id="plannerAddObjectBtn" title="Add object">
                                    <i class="bi bi-plus-square"></i>
                                </button>

                                <button type="button" class="nx-tool-icon nx-tool-icon-success" id="plannerAddLinkBtn" title="Add link">
                                    <i class="bi bi-diagram-2"></i>
                                </button>

                                <button type="button" class="nx-tool-icon nx-tool-icon-danger" id="plannerDeleteObjectBtn" title="Delete selected object">
                                    <i class="bi bi-trash"></i>
                                </button>

                                <button type="button" class="nx-tool-icon nx-tool-icon-danger" id="plannerDeleteLinkBtn" title="Delete selected link">
                                    <i class="bi bi-share-fill"></i>
                                </button>

                                <button type="button" class="nx-tool-icon nx-tool-icon-neutral" id="plannerRefreshBtn" title="Refresh planner">
                                    <i class="bi bi-arrow-clockwise"></i>
                                </button>

                                <button type="button" class="nx-tool-icon nx-tool-icon-neutral" id="plannerClearHighlightBtn" title="Clear highlight">
                                    <i class="bi bi-x-circle"></i>
                                </button>

                                <button type="button" class="nx-tool-icon nx-tool-icon-neutral" id="plannerResetLayoutBtn" title="Reset planner layout">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </button>
                            </div>
                        </div>

                        <button type="button" class="nx-toolbar-toggle" id="plannerToolbarToggle" title="Collapse toolbar">
                            <i class="bi bi-layout-sidebar-inset"></i>
                        </button>
                    </div>
                </div>

                <div id="plannerLogicalCanvas" class="nx-workspace-canvas-surface"></div>

                <div id="plannerMapCanvas" class="nx-workspace-canvas-surface d-none">
                    <div class="nx-workspace-map-placeholder">
                        <div class="nx-workspace-map-placeholder-icon">
                            <i class="bi bi-geo-alt"></i>
                        </div>
                        <div class="nx-workspace-map-placeholder-title">Map View Placeholder</div>
                        <div class="nx-workspace-map-placeholder-text">
                            Geographic plotting for ODF, LCP, and NAP will render here.
                        </div>
                    </div>
                </div>

                <div class="nx-inspector d-none" id="plannerInspector">
                    <div class="nx-inspector-header">
                        <div>
                            <div class="nx-inspector-title">Planner Inspector</div>
                            <div class="nx-inspector-subtitle">Selected node and link details</div>
                        </div>
                    </div>

                    <div class="nx-inspector-section">
                        <div class="nx-inspector-section-title">Selection</div>
                        <div id="plannerSelectionInfo" class="nx-inspector-body text-muted small">
                            Select a node or link in the planner to view details.
                        </div>
                    </div>

                    <div class="nx-inspector-section">
                        <div class="nx-inspector-section-title">Node Properties</div>
                        <div id="plannerNodeProperties" class="nx-inspector-body text-muted small">
                            No node selected.
                        </div>
                    </div>

                    <div class="nx-inspector-section">
                        <div class="nx-inspector-section-title">Link Identification</div>
                        <div id="plannerLinkIdentification" class="nx-inspector-body text-muted small">
                            No link selected.<br>
                            <span class="text-muted">Example: Link from OLT Port 1 to ODF Core 1</span>
                        </div>
                    </div>

                    <div class="nx-inspector-section">
                        <div class="nx-inspector-section-title">Link Properties</div>
                        <div id="plannerLinkProperties" class="nx-inspector-body text-muted small">
                            No link selected.
                        </div>
                    </div>
                </div>

                <div id="plannerHoverTooltip" class="nx-hover-tooltip d-none"></div>
            </div>
        </div>
    </template>

    <!-- PLANNER OBJECT PICKER -->
    <div class="modal fade nx-modal" id="plannerObjectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content nx-modal-content">
                <div class="modal-header nx-modal-header">
                    <div>
                        <div class="nx-modal-kicker">Planner Object</div>
                        <h5 class="modal-title mb-1">Add Planner Object</h5>
                        <div class="text-muted small">Choose the object type to place on the planner.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body nx-modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <button type="button" class="btn btn-outline-primary w-100 py-3" data-planner-create-type="odf">
                                <i class="bi bi-hdd-network d-block mb-2 fs-4"></i>
                                ODF
                            </button>
                        </div>
                        <div class="col-md-4">
                            <button type="button" class="btn btn-outline-primary w-100 py-3" data-planner-create-type="lcp">
                                <i class="bi bi-diagram-3 d-block mb-2 fs-4"></i>
                                LCP
                            </button>
                        </div>
                        <div class="col-md-4">
                            <button type="button" class="btn btn-outline-primary w-100 py-3" data-planner-create-type="nap">
                                <i class="bi bi-box-seam d-block mb-2 fs-4"></i>
                                NAP
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="/module-assets/NapManagement/js/cytoscape.min.js"></script>
    <script src="/module-assets/NapManagement/js/NapManagement.js"></script>
</div>