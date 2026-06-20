<div class="container-fluid nx-page">
    <link rel="stylesheet" href="/module-assets/OntDevices/css/OntDevices.css?v=acs6">

    <div id="ontAcsDevicePage" data-device-id="<?= htmlspecialchars((string)$deviceId) ?>">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h4 class="mb-1 fw-semibold">ONT Device Details</h4>
                <div class="text-muted small">Remote Monitoring and Management</div>
            </div>

            <div class="d-flex gap-2 flex-wrap">
                <a href="/ont-devices?tab=remote-management" class="btn btn-light border">
                    <i class="bi bi-arrow-left"></i> Back to Remote Management
                </a>
                <button type="button" class="btn btn-outline-secondary" id="acsDetailRefreshBtn">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
            </div>
        </div>

        <div id="acsDeviceHeader"></div>

        <div class="card border-0 shadow-sm nx-surface-card mb-3">
            <div class="card-body">
                <div class="nx-segment-control" id="acsDeviceTabs">
                    <a href="#" class="nx-segment-item active" data-acs-tab="summary">
                        <i class="bi bi-card-text"></i> Summary
                    </a>
                    <a href="#" class="nx-segment-item" data-acs-tab="wan">
                        <i class="bi bi-bar-chart-steps"></i> WAN
                    </a>
                    <a href="#" class="nx-segment-item" data-acs-tab="wifi">
                        <i class="bi bi-wifi"></i> WiFi
                    </a>
                    <a href="#" class="nx-segment-item" data-acs-tab="system">
                        <i class="bi bi-gear"></i> System
                    </a>
                    <a href="#" class="nx-segment-item" data-acs-tab="clients">
                        <i class="bi bi-people"></i> Clients
                    </a>
                </div>
            </div>
        </div>

        <div id="acsDeviceContent"></div>
    </div>

    <script src="/module-assets/OntDevices/js/OntDeviceDetails.js?v=acs6"></script>
</div>