<?php
$bngLegacyUi = isset($_GET['legacy_ui']) && (string)$_GET['legacy_ui'] === '1';
if (!$bngLegacyUi):
    $nxNextManifestPath = BASE_PATH . '/public/build-next/.vite/manifest.json';
    $nxNextManifest = is_file($nxNextManifestPath) ? (json_decode((string)file_get_contents($nxNextManifestPath), true) ?: []) : [];
    $nxNextEntry = $nxNextManifest['src/main.ts'] ?? [];
    $nxNextVersion = is_file($nxNextManifestPath) ? (string)filemtime($nxNextManifestPath) : (string)time();
    foreach (($nxNextEntry['css'] ?? []) as $nxNextCss): ?>
        <link rel="stylesheet" href="/build-next/<?= htmlspecialchars(ltrim((string)$nxNextCss, '/'), ENT_QUOTES, 'UTF-8') ?>?v=<?= htmlspecialchars($nxNextVersion, ENT_QUOTES, 'UTF-8') ?>">
    <?php endforeach; ?>
    <div class="container-fluid nx-page" data-nx-next-root="bng"></div>
    <?php if (!empty($nxNextEntry['file'])): ?>
        <script type="module" src="/build-next/<?= htmlspecialchars(ltrim((string)$nxNextEntry['file'], '/'), ENT_QUOTES, 'UTF-8') ?>?v=<?= htmlspecialchars($nxNextVersion, ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php else: ?>
        <div class="alert alert-warning">The new BNG interface is not built. Use <a href="/bng?legacy_ui=1">the legacy interface</a>.</div>
    <?php endif;
    return;
endif;
?>

<div class="container-fluid nx-page" id="bngManagementApp">
    <div class="card border-0 shadow-sm mb-4 nx-page-header-card page-hero-card"><div class="card-body page-hero-body">
        <div class="page-hero-left"><div class="page-hero-badge"><i class="bi bi-router"></i><span>BNG Interface Console</span></div><h1 class="page-hero-title">BNG Management</h1><p class="page-hero-text">Configure the BNG connection and inspect the live S‑VLAN, C‑VLAN, and PPP interface hierarchy.</p></div>
        <div class="page-hero-actions"><button type="button" class="btn btn-light border" id="bngDetectBtn"><i class="bi bi-arrow-clockwise me-1"></i> Refresh Runtime</button></div>
    </div></div>

    <div class="row g-3 mb-4">
        <?php foreach ([['BNG Connection','bngSummaryStatus','Parent: ','bngSummaryConfigured','bi-hdd-network','primary'],['S-VLAN Interfaces','bngSvlanCount','Outer VLAN interfaces',null,'bi-diagram-3','slate'],['C-VLAN Interfaces','bngCvlanCount','Subscriber VLAN interfaces',null,'bi-layers','cyan'],['PPP Interfaces','bngPppCount','Active PPP sessions',null,'bi-people','success']] as [$title,$id,$subtitle,$subtitleId,$icon,$tone]): ?>
        <div class="col-md-6 col-xl-3"><div class="nx-summary-card nx-summary-<?= $tone ?> h-100"><div class="nx-summary-top"><div><div class="nx-summary-label"><?= $title ?></div><div class="nx-summary-value" id="<?= $id ?>">-</div><div class="nx-summary-text"><?= $subtitle ?><?php if ($subtitleId): ?><span id="<?= $subtitleId ?>">-</span><?php endif; ?></div></div><div class="nx-summary-icon"><i class="bi <?= $icon ?>"></i></div></div></div></div>
        <?php endforeach; ?>
    </div>

    <div class="nx-toolbar-card mb-3"><div class="bng-toolbar-grid"><div class="nx-segment-control" role="tablist" aria-label="BNG views"><button class="nx-segment-control__item active" data-bng-tab-btn="bng" type="button">BNG Settings</button><button class="nx-segment-control__item" data-bng-tab-btn="accel" type="button">Accel-PPP</button><button class="nx-segment-control__item" data-bng-tab-btn="svlan" type="button">S-VLAN BNG Interface</button><button class="nx-segment-control__item" data-bng-tab-btn="cvlan" type="button">C-VLAN BNG Interface</button><button class="nx-segment-control__item" data-bng-tab-btn="ppp" type="button">PPP Interface</button></div><div class="bng-search-wrap"><i class="bi bi-search bng-search-icon"></i><input type="text" id="bngRuntimeSearch" class="form-control bng-search-control" placeholder="Search interface, VLAN, address, subscriber..."></div></div></div>

    <div class="card border-0 shadow-sm nx-content-card bng-surface-panel mb-4" data-bng-tab="bng"><div class="subs-table-topline"></div>
        <div class="card-body border-bottom bng-section-heading"><div><div class="fw-semibold">BNG Settings</div><div class="text-muted small">Registered SSH endpoint and interface automation profile.</div></div><div class="d-flex gap-2 flex-wrap"><button type="button" class="btn btn-light border" id="bngInstallRecoveryBtn"><i class="bi bi-arrow-repeat me-1"></i>Install Boot Recovery</button><button type="button" class="btn btn-light border" id="bngTrustHostBtn"><i class="bi bi-fingerprint me-1"></i>Trust Host Key</button><button type="button" class="btn btn-light border" id="bngBngTestBtn"><i class="bi bi-plug me-1"></i> Test Connection</button><button type="button" class="btn btn-primary" id="bngBngAddBtn"><i class="bi bi-plus-lg me-1"></i> Add BNG</button></div></div>
        <div class="table-responsive nx-table-wrap"><table class="table align-middle mb-0 bng-runtime-table" id="bngBngTable"><thead><tr><th>Host</th><th>Username</th><th>Authentication</th><th>Interfaces</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody id="bngBngTableBody"></tbody></table></div>
    </div>

    <div class="card border-0 shadow-sm nx-content-card d-none mb-4" data-bng-tab="accel">
        <div class="subs-table-topline"></div>
        <div class="card-body border-bottom d-flex justify-content-between align-items-start gap-3 flex-wrap"><div><div class="fw-semibold">Accel-PPP desired configuration</div><div class="text-muted small">Save a draft and stage it for the next maintenance window. Staging never reloads or restarts Accel-PPP.</div></div><span class="badge text-bg-secondary" id="bngAccelStatus">NOT CONFIGURED</span></div>
        <form id="bngAccelForm"><div class="card-body"><div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Changes to loaded modules require a daemon restart and disconnect active PPP sessions. Activation is intentionally unavailable outside a scheduled maintenance action.</div>
            <?php
            $groups = [
                'Modules and Core' => [['modules','Modules (comma separated)','text'],['thread_count','Thread count','number'],['log_error','Error log','text'],['log_debug','Debug log','text'],['log_file','Main log','text'],['log_level','Log level','number']],
                'PPPoE' => [['pppoe_verbose','Verbose (0/1)','number'],['pppoe_interface','Interface expression','text'],['vlan_mon','VLAN monitor','text'],['vlan_name','VLAN name','text'],['vlan_timeout','VLAN timeout','number'],['ifname','PPP interface name','text'],['ac_name','AC name','text'],['service_name','Service name','text'],['accept_any_service','Accept any service (0/1)','number'],['pado_delay','PADO delay','number'],['max_sessions','Maximum sessions','number'],['ip_pool','IP pool','text']],
                'PPP and Address Pool' => [['default_auth','Default authentication','text'],['ipv4','IPv4 policy','text'],['mtu','MTU','number'],['mru','MRU','number'],['min_mtu','Minimum MTU','number'],['lcp_echo_interval','LCP echo interval','number'],['lcp_echo_failure','LCP echo failure','number'],['ipcp','IPCP (0/1)','number'],['ipcp_accept_local','Accept local IPCP (0/1)','number'],['ipcp_accept_remote','Accept remote IPCP (0/1)','number'],['ppp_verbose','PPP verbose (0/1)','number'],['pool_gateway','Pool gateway','text'],['pool_range','Pool range','text'],['pool_name','Pool name','text']],
                'RADIUS' => [['nas_ip_address','NAS IP address','text'],['nas_identifier','NAS identifier','text'],['radius_gateway','Gateway IP address','text'],['radius_server','RADIUS server','text'],['radius_secret','RADIUS secret','password'],['radius_auth_port','Authentication port','number'],['radius_acct_port','Accounting port','number'],['radius_timeout','Timeout','number'],['radius_max_try','Maximum tries','number'],['acct_interim_interval','Interim interval','number'],['radius_verbose','RADIUS verbose (0/1)','number'],['dae_address','DAE address','text'],['dae_port','DAE port','number'],['dae_secret','DAE secret','password']],
                'CLI and Shaper' => [['cli_address','CLI bind address','text'],['cli_port','CLI port','number'],['shaper_attr','Shaper attribute','text'],['down_limiter','Download limiter','text'],['up_limiter','Upload limiter','text'],['leaf_qdisc','Leaf qdisc','text']],
            ];
            foreach ($groups as $title => $fields): ?>
                <h6 class="mt-3 mb-3"><?= htmlspecialchars($title) ?></h6><div class="row g-3 mb-3">
                <?php foreach ($fields as [$name,$label,$type]): ?><div class="col-md-6 col-xl-4"><label class="form-label"><?= htmlspecialchars($label) ?></label><input class="form-control" type="<?= $type ?>" name="<?= $name ?>" <?= $type==='password' ? 'autocomplete="new-password" placeholder="Leave blank to keep saved secret"' : '' ?>></div><?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            <div class="d-flex justify-content-end gap-2 border-top pt-3"><button class="btn btn-light border" type="button" id="bngAccelPreviewBtn">Preview</button><button class="btn btn-primary" type="submit">Save Draft</button><button class="btn btn-warning" type="button" id="bngAccelStageBtn"><i class="bi bi-calendar-check me-1"></i>Stage for Maintenance</button><button class="btn btn-danger" type="button" id="bngAccelActivateBtn"><i class="bi bi-power me-1"></i>Activate Maintenance</button></div>
        </div></form>
        <div class="card-body border-top d-none" id="bngAccelPreviewPanel"><div class="d-flex justify-content-between"><strong>Generated configuration</strong><small class="text-muted">Secrets are redacted</small></div><pre class="bg-dark text-light rounded p-3 mt-3 mb-0 overflow-auto" id="bngAccelPreview"></pre></div>
    </div>

    <div class="card border-0 shadow-sm nx-content-card d-none" data-bng-tab="svlan"><div class="subs-table-topline"></div><div class="card-body border-bottom"><div class="fw-semibold">S-VLAN BNG Interfaces</div><div class="text-muted small">Outer QinQ interfaces created on the configured BNG parent.</div></div><div id="bngSvlanRuntime"><div class="nx-empty-inline m-4">Loading S-VLAN interfaces…</div></div></div>
    <div class="card border-0 shadow-sm nx-content-card d-none" data-bng-tab="cvlan"><div class="subs-table-topline"></div><div class="card-body border-bottom"><div class="fw-semibold">C-VLAN BNG Interfaces</div><div class="text-muted small">Subscriber-side inner VLAN interfaces grouped under each S-VLAN.</div></div><div id="bngCvlanRuntime"><div class="nx-empty-inline m-4">Loading C-VLAN interfaces…</div></div></div>
    <div class="card border-0 shadow-sm nx-content-card d-none" data-bng-tab="ppp"><div class="subs-table-topline"></div><div class="card-body border-bottom"><div class="fw-semibold">PPP Interfaces</div><div class="text-muted small">Live ACCEL-PPP interfaces with local and subscriber peer addresses.</div></div><div id="bngPppRuntime"><div class="nx-empty-inline m-4">Loading PPP interfaces…</div></div></div>

    <div class="modal fade" id="bngBngModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><div><h5 class="modal-title" id="bngBngModalTitle">Add BNG Connection</h5><p class="text-muted small mb-0">Store SSH access and Linux interface automation settings.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <form id="bngBngForm"><div class="modal-body"><div class="row g-3">
            <div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="enabled"><option value="1">Enabled</option><option value="0">Disabled</option></select></div>
            <div class="col-md-6"><label class="form-label">BNG Host / IP</label><input class="form-control" name="host" required placeholder="10.0.10.147"></div><div class="col-md-3"><label class="form-label">SSH Port</label><input class="form-control" name="port" required placeholder="22"></div>
            <div class="col-12"><label class="form-label">Username</label><input class="form-control" name="username" required placeholder="root"></div>
            <div class="col-12"><label class="form-label">Password</label><input type="password" class="form-control" name="password" placeholder="Leave blank to keep the current password"><div class="form-text">Passwords are never displayed in the BNG table.</div></div>
            <div class="col-md-6"><label class="form-label">BNG Parent Interface</label><input class="form-control" name="bng_parent_interface" required placeholder="ens17"></div><div class="col-md-6"><label class="form-label">Preferred Egress Interface</label><input class="form-control" name="preferred_interface" placeholder="ens16"></div>
            <input type="hidden" name="auth_type" value="PASSWORD"><input type="hidden" name="vlan_mode" value="QINQ"><input type="hidden" name="auto_create_svlan_interface" value="1"><input type="hidden" name="remarks">
        </div></div><div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit" id="bngBngSaveBtn">Save BNG</button></div></form>
    </div></div></div>
    <script src="/module-assets/BngManagement/js/BngManagement.js?v=split3"></script>
</div>
