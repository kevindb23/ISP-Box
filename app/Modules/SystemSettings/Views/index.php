<?php $p=BASE_PATH.'/public/build-next/.vite/manifest.json';$m=is_file($p)?(json_decode((string)file_get_contents($p),true)?:[]):[];$e=$m['src/main.ts']??[];$v=is_file($p)?(string)filemtime($p):(string)time();foreach(($e['css']??[])as$c):?><link rel="stylesheet" href="/build-next/<?=htmlspecialchars(ltrim((string)$c,'/'),ENT_QUOTES,'UTF-8')?>?v=<?=htmlspecialchars($v,ENT_QUOTES,'UTF-8')?>"><?php endforeach;?><div class="container-fluid nx-page" data-nx-next-root="system-settings"></div><?php if(!empty($e['file'])):?><script type="module" src="/build-next/<?=htmlspecialchars(ltrim((string)$e['file'],'/'),ENT_QUOTES,'UTF-8')?>?v=<?=htmlspecialchars($v,ENT_QUOTES,'UTF-8')?>"></script><?php else:?><div class="alert alert-warning">The system settings interface is not built.</div><?php endif;return;?>
<div class="container-fluid nx-page" id="systemSettingsGeneralPage">
    <div class="card border-0 shadow-sm nx-page-header-card mb-3">
        <div class="card-body nx-page-header">
            <div><h5 class="nx-page-title">System Settings</h5><div class="nx-page-subtitle">Application-wide operational, billing, security, and provisioning defaults.</div></div>
            <div class="nx-page-actions"><button type="submit" form="generalSettingsForm" class="btn btn-primary"><i class="bi bi-save"></i> Save Settings</button></div>
        </div>
    </div>
    <form id="generalSettingsForm">
        <div class="row g-3">
            <div class="col-12 col-xl-6"><div class="card border-0 shadow-sm h-100"><div class="card-body">
                <h6>General</h6>
                <div class="row g-3">
                    <div class="col-12"><label class="form-label">System name</label><input class="form-control" name="system_name" required></div>
                    <div class="col-md-6"><label class="form-label">Timezone</label><select class="form-select" name="timezone"><option value="Asia/Manila">Asia/Manila</option><option value="UTC">UTC</option></select></div>
                    <div class="col-md-6"><label class="form-label">Currency</label><select class="form-select" name="currency"><option>PHP</option><option>USD</option></select></div>
                    <div class="col-md-6"><label class="form-label">Date format</label><select class="form-select" name="date_format"><option>Y-m-d</option><option>m/d/Y</option><option>d/m/Y</option></select></div>
                    <div class="col-md-6"><label class="form-label">Time format</label><select class="form-select" name="time_format"><option>H:i:s</option><option>h:i A</option></select></div>
                </div>
            </div></div></div>
            <div class="col-12 col-xl-6"><div class="card border-0 shadow-sm h-100"><div class="card-body">
                <h6>Numbering and billing</h6><div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Subscriber prefix</label><input class="form-control" name="subscriber_prefix" required></div>
                    <div class="col-md-4"><label class="form-label">Service prefix</label><input class="form-control" name="service_prefix" required></div>
                    <div class="col-md-4"><label class="form-label">Job prefix</label><input class="form-control" name="job_prefix" required></div>
                    <div class="col-md-6"><label class="form-label">Invoice due days</label><input type="number" min="0" max="365" class="form-control" name="invoice_due_days" required></div>
                    <div class="col-md-6"><label class="form-label">Grace period days</label><input type="number" min="0" max="365" class="form-control" name="grace_period_days" required></div>
                </div>
            </div></div></div>
            <div class="col-12"><div class="card border-0 shadow-sm"><div class="card-body"><h6>Operations and security</h6><div class="row g-3">
                <div class="col-md-3"><label class="form-label">Provisioning mode</label><select class="form-select" name="provisioning_mode"><option>FULL_AUTO</option><option>ASSISTED</option><option>MANUAL</option></select></div>
                <div class="col-md-3"><label class="form-label">Installation assignment</label><select class="form-select" name="installation_assignment_mode"><option value="MANUAL">Manual dispatch</option><option value="AUTO_NEAREST">Automatically assign nearest technician</option></select></div>
                <div class="col-md-3"><label class="form-label">Session timeout (seconds)</label><input type="number" min="300" max="86400" class="form-control" name="session_timeout" required></div>
                <div class="col-md-3"><label class="form-label">Maximum login attempts</label><input type="number" min="1" max="100" class="form-control" name="max_login_attempts" required></div>
                <div class="col-md-3"><label class="form-label">Log retention days</label><input type="number" min="1" max="3650" class="form-control" name="log_retention_days" required></div>
                <div class="col-12"><div class="form-check form-switch"><input type="hidden" name="maintenance_mode" value="0"><input class="form-check-input" type="checkbox" name="maintenance_mode" value="1" id="maintenanceMode" role="switch"><label class="form-check-label" for="maintenanceMode">Maintenance mode</label></div></div>
            </div></div></div></div>
        </div>
    </form>
    <script src="/module-assets/SystemSettings/js/SystemSettings.js?v=<?= time() ?>"></script>
</div>
