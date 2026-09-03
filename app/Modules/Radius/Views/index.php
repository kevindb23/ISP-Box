<?php
$radiusLegacyUi = isset($_GET['legacy_ui']) && (string)$_GET['legacy_ui'] === '1';
if (!$radiusLegacyUi):
    $nxNextManifestPath = BASE_PATH . '/public/build-next/.vite/manifest.json';
    $nxNextManifest = is_file($nxNextManifestPath) ? (json_decode((string)file_get_contents($nxNextManifestPath), true) ?: []) : [];
    $nxNextEntry = $nxNextManifest['src/main.ts'] ?? [];
    $nxNextVersion = is_file($nxNextManifestPath) ? (string)filemtime($nxNextManifestPath) : (string)time();
    foreach (($nxNextEntry['css'] ?? []) as $nxNextCss): ?>
        <link rel="stylesheet" href="/build-next/<?= htmlspecialchars(ltrim((string)$nxNextCss, '/'), ENT_QUOTES, 'UTF-8') ?>?v=<?= htmlspecialchars($nxNextVersion, ENT_QUOTES, 'UTF-8') ?>">
    <?php endforeach; ?>
    <div class="container-fluid nx-page" data-nx-next-root="radius"></div>
    <?php if (!empty($nxNextEntry['file'])): ?>
        <script type="module" src="/build-next/<?= htmlspecialchars(ltrim((string)$nxNextEntry['file'], '/'), ENT_QUOTES, 'UTF-8') ?>?v=<?= htmlspecialchars($nxNextVersion, ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php else: ?>
        <div class="alert alert-warning">The new RADIUS interface is not built. Use <a href="/radius?legacy_ui=1">the legacy interface</a>.</div>
    <?php endif;
    return;
endif;

$settings = is_array($settings ?? null) ? $settings : [];
$esc = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
?>
<div class="container-fluid nx-page" id="radiusPage" data-settings-count="<?= count($settings) ?>">
    <section class="card border-0 shadow-sm mb-4 nx-page-header-card page-hero-card">
            <div class="card-body nx-page-header page-hero-body">
                <div class="nx-page-header-left page-hero-left">
                    <div class="page-hero-badge"><i class="bi bi-shield-lock"></i><span>Access &amp; Authentication Infrastructure</span></div>
                    <h1 class="nx-page-title page-hero-title">RADIUS Management</h1>
                    <p class="nx-page-subtitle page-hero-text">Register the database endpoints used by subscriber authentication, authorization, and accounting.</p>
                </div>
                <div class="nx-page-actions page-hero-actions">
                    <button class="btn btn-primary nx-header-btn" type="button" id="radiusAddBtn"><i class="bi bi-plus-lg"></i><span>Add Server</span></button>
                    <a class="btn btn-light nx-header-btn" href="/radius"><i class="bi bi-arrow-clockwise"></i><span>Refresh</span></a>
                </div>
            </div>
    </section>

        <section class="card border-0 nx-toolbar-card">
            <div class="card-body">
                <div class="toolbar-grid">
                    <div class="toolbar-group">
                        <label class="toolbar-label" for="radiusServerSearch">Search Servers</label>
                        <div class="radius-search-wrap"><i class="bi bi-search radius-search-icon"></i><input class="form-control toolbar-control radius-search-control" id="radiusServerSearch" type="search" placeholder="Search by host, username, or database"></div>
                    </div>
                    <div class="toolbar-group"><div class="toolbar-label">Notes</div><div class="toolbar-note">Passwords are never displayed in the server list. Leave the password blank when editing to keep it unchanged.</div></div>
                    <div class="toolbar-meta" id="radiusCount"><?= count($settings) ?> server<?= count($settings) === 1 ? '' : 's' ?></div>
                </div>
            </div>
    </section>

        <section class="card border-0 nx-content-card">
            <div class="radius-table-topline"></div>
            <div class="card-body">
                <div class="section-head"><div><h2 class="section-title">RADIUS Servers</h2><p class="section-subtitle">Registered database endpoints and connection profiles</p></div></div>
                <div class="nx-table-wrap">
                    <table class="table align-middle" id="radiusTable">
                        <thead><tr><th>Host</th><th>Database</th><th>Username</th><th>Status</th><th>Updated</th><th class="text-end">Actions</th></tr></thead>
                        <tbody id="radiusTableBody">
                        <?php foreach ($settings as $row): ?>
                            <tr data-radius-row data-search="<?= $esc(strtolower(($row['host'] ?? '') . ' ' . ($row['db_name'] ?? '') . ' ' . ($row['db_user'] ?? ''))) ?>">
                                <td><div class="nx-cell-title"><?= $esc($row['host']) ?></div><div class="nx-cell-sub">Connection endpoint</div></td>
                                <td><?= $esc($row['db_name']) ?></td>
                                <td><?= $esc($row['db_user']) ?></td>
                                <td><span class="badge <?= !empty($row['is_active']) ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= !empty($row['is_active']) ? 'Active' : 'Inactive' ?></span></td>
                                <td><?= $esc($row['updated_at'] ?? $row['created_at'] ?? '') ?></td>
                                <td class="text-end text-nowrap"><button class="btn btn-sm btn-light border radius-edit-btn" data-id="<?= (int)$row['id'] ?>" title="Edit"><i class="bi bi-pencil"></i></button> <button class="btn btn-sm btn-light border text-danger radius-delete-btn" data-id="<?= (int)$row['id'] ?>" title="Delete"><i class="bi bi-trash"></i></button></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="radius-empty-row" id="radiusEmptyRow"<?= $settings ? ' style="display:none"' : '' ?>><td colspan="6"><div class="nx-empty-state"><div class="nx-empty-icon"><i class="bi bi-shield-lock"></i></div><div class="nx-empty-title">No RADIUS servers configured</div><div class="nx-empty-text">Add a database endpoint to enable RADIUS-backed authentication.</div></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
    </section>

    <div class="modal fade" id="radiusFormModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
            <div class="modal-header"><div><h5 class="modal-title" id="radiusModalTitle">Add RADIUS Server</h5><p class="text-muted small mb-0">Store the endpoint in the portal database.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <form id="radiusForm"><div class="modal-body"><input type="hidden" id="radiusId" name="id">
                <div class="row g-3"><div class="col-md-6"><label class="form-label" for="radiusHost">Host</label><input class="form-control" id="radiusHost" name="host" required placeholder="10.0.10.154"></div><div class="col-md-6"><label class="form-label" for="radiusDbName">Database name</label><input class="form-control" id="radiusDbName" name="db_name" required placeholder="radius"></div><div class="col-md-6"><label class="form-label" for="radiusDbUser">Database username</label><input class="form-control" id="radiusDbUser" name="db_user" required placeholder="portal_radius"></div><div class="col-md-6"><label class="form-label" for="radiusPassword">Database password</label><input class="form-control" id="radiusPassword" name="db_password" type="password" autocomplete="new-password"><div class="form-text" id="radiusPasswordHint">Required when creating a server.</div></div><div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="radiusActive" name="is_active" value="1" checked><label class="form-check-label" for="radiusActive">Use this endpoint for runtime RADIUS connections</label></div></div></div>
            </div><div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit" id="radiusSaveBtn">Save Server</button></div></form>
        </div></div>
    </div>
    <script src="/module-assets/Radius/js/Radius.js?v=1"></script>
</div>
