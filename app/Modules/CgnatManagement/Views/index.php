<?php
$cgnatLegacyUi = isset($_GET['legacy_ui']) && (string)$_GET['legacy_ui'] === '1';
if (!$cgnatLegacyUi):
    $nxNextManifestPath = BASE_PATH . '/public/build-next/.vite/manifest.json';
    $nxNextManifest = is_file($nxNextManifestPath) ? (json_decode((string)file_get_contents($nxNextManifestPath), true) ?: []) : [];
    $nxNextEntry = $nxNextManifest['src/main.ts'] ?? [];
    $nxNextVersion = is_file($nxNextManifestPath) ? (string)filemtime($nxNextManifestPath) : (string)time();
    foreach (($nxNextEntry['css'] ?? []) as $nxNextCss): ?>
        <link rel="stylesheet" href="/build-next/<?= htmlspecialchars(ltrim((string)$nxNextCss, '/'), ENT_QUOTES, 'UTF-8') ?>?v=<?= htmlspecialchars($nxNextVersion, ENT_QUOTES, 'UTF-8') ?>">
    <?php endforeach; ?>
    <div class="container-fluid nx-page" data-nx-next-root="cgnat"></div>
    <?php if (!empty($nxNextEntry['file'])): ?>
        <script type="module" src="/build-next/<?= htmlspecialchars(ltrim((string)$nxNextEntry['file'], '/'), ENT_QUOTES, 'UTF-8') ?>?v=<?= htmlspecialchars($nxNextVersion, ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php else: ?>
        <div class="alert alert-warning">The new CGNAT interface is not built. Use <a href="/cgnat?legacy_ui=1">the legacy interface</a>.</div>
    <?php endif;
    return;
endif;
?>
<div class="container-fluid nx-page" id="cgnatManagementApp">
    <div class="card border-0 shadow-sm mb-4 nx-page-header-card page-hero-card">
        <div class="card-body page-hero-body">
            <div class="page-hero-left"><div class="page-hero-badge"><i class="bi bi-shuffle"></i><span>Address Translation</span></div><h1 class="page-hero-title">CGNAT Management</h1><p class="page-hero-text">Manage subscriber address translation and live POSTROUTING state independently from BNG and Accel-PPP.</p></div>
            <div class="page-hero-actions"><a class="btn btn-light border" href="/bng"><i class="bi bi-router me-1"></i>Open BNG Management</a></div>
        </div>
    </div>

    <div class="nx-toolbar-card mb-3"><div class="cgnat-toolbar-grid"><div class="nx-segment-control" role="tablist" aria-label="CGNAT views">
        <button class="nx-segment-control__item active" data-cgnat-tab-btn="config" type="button">CGNAT Configuration</button>
        <button class="nx-segment-control__item" data-cgnat-tab-btn="rules" type="button">Live POSTROUTING Rules</button>
    </div></div></div>

    <div class="card border-0 shadow-sm nx-content-card" data-cgnat-tab="config">
        <div class="subs-table-topline"></div>
        <div class="card-body border-bottom"><div class="fw-semibold">CGNAT desired configuration</div><div class="text-muted small">Configure the subscriber-side BNG interface, public egress interface, public address range, and SNAT source network.</div></div>
        <form id="cgnatConfigForm"><div class="card-body"><div class="row g-3">
            <div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="enabled"><option value="1">Enabled</option><option value="0">Disabled</option></select></div>
            <div class="col-md-5"><label class="form-label">Subscriber network</label><input class="form-control" name="inside_network" placeholder="100.64.0.0/24" required></div>
            <div class="col-md-4"><label class="form-label">BNG interface</label><input class="form-control" name="bng_interface" list="cgnatInterfaceOptions" placeholder="ens17" required><div class="form-text">Physical Linux interface only. VLAN and Accel-PPP session interfaces are excluded.</div></div>
            <div class="col-md-4"><label class="form-label">Public egress interface</label><input class="form-control" name="egress_interface" list="cgnatInterfaceOptions" placeholder="ens16" required><div class="form-text">Used by SNAT and receives the public /32 address bindings.</div></div>
            <div class="col-md-4"><label class="form-label">Public start IP</label><input class="form-control" name="public_start_ip" placeholder="126.209.31.170" required></div>
            <div class="col-md-4"><label class="form-label">Public end IP</label><input class="form-control" name="public_end_ip" placeholder="126.209.31.174" required></div>
        </div><datalist id="cgnatInterfaceOptions"></datalist><div class="d-flex justify-content-end gap-2 border-top pt-3 mt-3"><button class="btn btn-primary" type="submit">Save Configuration</button><button class="btn btn-warning" type="button" id="cgnatApplyConfigBtn">Apply CGNAT State</button></div></div></form>
    </div>

    <div class="card border-0 shadow-sm nx-content-card d-none" data-cgnat-tab="rules">
        <div class="subs-table-topline"></div>
        <div class="card-body border-bottom d-flex justify-content-between align-items-center gap-3"><div><div class="fw-semibold">Live POSTROUTING rules</div><div class="text-muted small">Select exact append rules to remove from the configured BNG. The chain policy is read-only.</div></div><div class="d-flex gap-2"><button class="btn btn-sm btn-outline-danger" type="button" id="cgnatRemoveRulesBtn" disabled><i class="bi bi-trash me-1"></i>Remove Selected</button><button class="btn btn-sm btn-light border" type="button" id="cgnatRefreshRuntimeBtn"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button></div></div>
        <div class="card-body pb-0"><div class="alert alert-warning py-2 small mb-0">If an enabled saved CGNAT configuration owns a removed SNAT rule, BNG reconciliation can restore it. Disable or update the desired configuration for permanent removal.</div></div>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th style="width:42px"><span class="visually-hidden">Select</span></th><th>Rule</th></tr></thead><tbody id="cgnatRuntimeRulesBody"><tr><td></td><td class="text-muted">Loading rules…</td></tr></tbody></table></div>
    </div>

    <script src="/module-assets/CgnatManagement/js/CgnatManagement.js?v=cgnat6"></script>
</div>
