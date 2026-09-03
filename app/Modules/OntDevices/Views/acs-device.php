<style>
    /* ACS detail content is rendered dynamically, so keep its theme rules scoped here. */
    #ontAcsDevicePage {
        --acs-bg: #ffffff;
        --acs-bg-muted: #f8fafc;
        --acs-border: #e2e8f0;
        --acs-text: #0f172a;
        --acs-muted: #64748b;
    }

    #ontAcsDevicePage .ont-acs-hero,
    #ontAcsDevicePage .ont-acs-pill,
    #ontAcsDevicePage .acs-stat-card,
    #ontAcsDevicePage .acs-overview-item,
    #ontAcsDevicePage .ont-acs-interface-card,
    #ontAcsDevicePage .ont-acs-info-item,
    #ontAcsDevicePage .ont-acs-metric-card,
    #ontAcsDevicePage .ont-acs-bigpanel,
    #ontAcsDevicePage .ont-acs-subpanel,
    #ontAcsDevicePage .ont-acs-enable-box,
    #ontAcsDevicePage .ont-acs-primary-card,
    #ontAcsDevicePage .ont-acs-danger-card {
        color: var(--acs-text) !important;
        background-color: var(--acs-bg) !important;
        border-color: var(--acs-border) !important;
    }

    #ontAcsDevicePage .ont-acs-info-item,
    #ontAcsDevicePage .ont-acs-metric-card,
    #ontAcsDevicePage .acs-overview-item,
    #ontAcsDevicePage .ont-acs-enable-box {
        background-color: var(--acs-bg-muted) !important;
    }

    #ontAcsDevicePage .ont-acs-hero-serial,
    #ontAcsDevicePage .ont-acs-pill-value,
    #ontAcsDevicePage .acs-stat-value,
    #ontAcsDevicePage .acs-overview-value,
    #ontAcsDevicePage .ont-acs-section-title h4,
    #ontAcsDevicePage .ont-acs-section-title h5,
    #ontAcsDevicePage .ont-acs-interface-name,
    #ontAcsDevicePage .ont-acs-info-value,
    #ontAcsDevicePage .ont-acs-metric-value,
    #ontAcsDevicePage .ont-acs-subpanel-title,
    #ontAcsDevicePage .ont-acs-wifi-title,
    #ontAcsDevicePage .ont-acs-action-card-title,
    #ontAcsDevicePage .ont-acs-action-card-list {
        color: var(--acs-text) !important;
    }

    #ontAcsDevicePage .ont-acs-hero-title,
    #ontAcsDevicePage .ont-acs-pill-label,
    #ontAcsDevicePage .acs-stat-label,
    #ontAcsDevicePage .acs-overview-label,
    #ontAcsDevicePage .ont-acs-info-label,
    #ontAcsDevicePage .ont-acs-metric-label,
    #ontAcsDevicePage .ont-acs-interface-sub,
    #ontAcsDevicePage .ont-acs-wifi-sub,
    #ontAcsDevicePage .ont-acs-action-card-sub {
        color: var(--acs-muted) !important;
    }

    #ontAcsDevicePage input,
    #ontAcsDevicePage select,
    #ontAcsDevicePage textarea {
        color: var(--acs-text) !important;
        background-color: var(--acs-bg) !important;
        border-color: var(--acs-border) !important;
    }

    html[data-theme="dark"] #ontAcsDevicePage {
        --acs-bg: #141f31;
        --acs-bg-muted: #18263a;
        --acs-border: #334155;
        --acs-text: #f8fafc;
        --acs-muted: #a8b3c5;
    }
</style>

<div class="container-fluid nx-page">
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
                <button type="button" class="btn btn-outline-primary" id="acsPingBtn">
                    <i class="bi bi-wifi"></i> Ping WAN
                </button>
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

    <script src="/module-assets/OntDevices/js/OntDeviceDetails.js?v=acs9"></script>
</div>
