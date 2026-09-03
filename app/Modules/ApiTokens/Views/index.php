<?php $items=is_array($items??null)?$items:[];$monitoringIdentity=is_array($monitoringIdentity??null)?$monitoringIdentity:[];$p=BASE_PATH.'/public/build-next/.vite/manifest.json';$m=is_file($p)?(json_decode((string)file_get_contents($p),true)?:[]):[];$e=$m['src/main.ts']??[];$v=is_file($p)?(string)filemtime($p):(string)time();foreach(($e['css']??[])as$c):?><link rel="stylesheet" href="/build-next/<?=htmlspecialchars(ltrim((string)$c,'/'),ENT_QUOTES,'UTF-8')?>?v=<?=htmlspecialchars($v,ENT_QUOTES,'UTF-8')?>"><?php endforeach;?><div class="container-fluid nx-page" data-nx-next-root="api-tokens"><script type="application/json" data-nx-next-props><?=json_encode(['tokens'=>$items,'monitoringIdentity'=>$monitoringIdentity],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?></script></div><?php if(!empty($e['file'])):?><script type="module" src="/build-next/<?=htmlspecialchars(ltrim((string)$e['file'],'/'),ENT_QUOTES,'UTF-8')?>?v=<?=htmlspecialchars($v,ENT_QUOTES,'UTF-8')?>"></script><?php else:?><div class="alert alert-warning">The API Tokens interface is not built.</div><?php endif;return;?>
<?php $items = is_array($items ?? null) ? $items : []; ?>
<div class="container-fluid nx-page" id="apiTokensPage">
    <div class="card border-0 shadow-sm mb-3 nx-page-header-card">
        <div class="card-body nx-page-header d-flex justify-content-between align-items-center">
            <div><h5 class="mb-0 fw-semibold">API tokens</h5><small class="text-muted"><?= count($items) ?> token<?= count($items) === 1 ? '' : 's' ?></small></div>
            <button class="btn btn-primary" id="createApiTokenButton" type="button"><i class="bi bi-plus-lg"></i> Create token</button>
        </div>
    </div>
    <div class="alert alert-warning d-none" id="newApiTokenPanel">
        <strong>Copy this token now.</strong> It will not be displayed again.
        <div class="input-group mt-2"><input class="form-control font-monospace" id="newApiTokenValue" readonly><button class="btn btn-outline-secondary" id="copyApiTokenButton" type="button">Copy</button></div>
    </div>
    <div class="card border-0 shadow-sm nx-content-card"><div class="table-responsive nx-table-wrap"><table class="table align-middle mb-0">
        <thead><tr><th class="ps-4">Token</th><th>Purpose / scopes</th><th>Created</th><th>Last used</th><th>Expires</th><th>Status</th><th class="text-end pe-4">Action</th></tr></thead><tbody>
        <?php if ($items === []): ?><tr><td colspan="7" class="text-center text-muted py-4">No integration tokens have been created.</td></tr>
        <?php else: foreach ($items as $item): $active = ($item['status'] ?? '') === 'ACTIVE'; ?>
            <tr>
                <td class="ps-4"><strong><?= htmlspecialchars((string)($item['name'] ?? '')) ?></strong><div class="small text-muted">#<?= (int)$item['id'] ?> · <?= htmlspecialchars((string)($item['description'] ?? '')) ?></div></td>
                <td><span class="badge text-bg-info"><?= htmlspecialchars((string)($item['purpose'] ?? 'CUSTOM')) ?></span><div class="small text-muted mt-1"><?= htmlspecialchars(implode(', ', $item['scopes'] ?? [])) ?></div></td>
                <td><?= htmlspecialchars((string)($item['created_at'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string)($item['last_used_at'] ?? 'Never')) ?><div class="small text-muted"><?= htmlspecialchars((string)($item['last_used_ip'] ?? '')) ?></div></td>
                <td><?= htmlspecialchars((string)($item['expires_at'] ?? 'Never')) ?></td>
                <td><span class="badge <?= $active ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= htmlspecialchars((string)($item['status'] ?? 'UNKNOWN')) ?></span></td>
                <td class="text-end pe-4"><?php if ($active): ?><button class="btn btn-sm btn-outline-danger revoke-api-token" data-id="<?= (int)$item['id'] ?>" type="button">Revoke</button><?php endif; ?></td>
            </tr>
        <?php endforeach; endif; ?></tbody>
    </table></div></div>

    <div class="modal fade" id="createApiTokenModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg"><div class="modal-content">
        <form id="createApiTokenForm"><div class="modal-header"><h5 class="modal-title">Create integration token</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><div class="row g-3">
            <div class="col-md-6"><label class="form-label">Token name</label><input class="form-control" name="name" maxlength="100" required placeholder="Central monitoring"></div>
            <div class="col-md-6"><label class="form-label">Purpose</label><select class="form-select" name="purpose" required><option value="CENTRAL_MONITORING">Central monitoring</option><option value="READ_ONLY_MONITORING">Read-only monitoring</option><option value="NOC_INTEGRATION">NOC integration</option><option value="EXTERNAL_INTEGRATION">External integration</option><option value="CUSTOM">Custom</option></select></div>
            <div class="col-12"><label class="form-label">Description</label><input class="form-control" name="description" maxlength="255" placeholder="Where and why this token is used"></div>
            <div class="col-md-6"><label class="form-label">Expires at (optional)</label><input class="form-control" type="datetime-local" name="expires_at"></div>
            <div class="col-12"><label class="form-label d-block">Read-only scopes</label><div class="row g-2">
                <?php foreach (['infrastructure.monitoring.read','monitoring.read','dashboard.read','subscribers.read','sessions.read','billing.summary.read','network.status.read','olt.status.read','ont.status.read','provisioning.read','tickets.read'] as $scope): ?>
                    <div class="col-md-4"><label class="form-check"><input class="form-check-input" type="checkbox" name="scopes[]" value="<?= htmlspecialchars($scope) ?>"><span class="form-check-label font-monospace small"><?= htmlspecialchars($scope) ?></span></label></div>
                <?php endforeach; ?>
            </div></div>
        </div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Create token</button></div></form>
    </div></div></div>
</div>
<script src="/module-assets/ApiTokens/js/ApiTokens.js"></script>
