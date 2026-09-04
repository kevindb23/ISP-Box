<?php
$csrf = \App\Core\Security\Csrf::token();
?>
<div class="container-fluid nx-page mfa-page" id="mfaPage" data-csrf="<?= htmlspecialchars($csrf) ?>">
    <link rel="stylesheet" href="/module-assets/Mfa/css/mfa.css?v=1">
    <div class="card border-0 shadow-sm nx-page-header-card"><div class="card-body nx-page-header">
        <div class="nx-page-header-left"><div class="mfa-kicker"><i class="bi bi-shield-lock"></i> ACCOUNT SECURITY</div><h1 class="nx-page-title">Multi-factor authentication</h1><div class="nx-page-subtitle">Enable a second verification step for staff and subscriber accounts.</div></div>
        <div class="mfa-header-note"><i class="bi bi-info-circle"></i><span>Disabled by default</span></div>
    </div></div>
    <div class="mfa-intro-grid"><div class="mfa-method-card"><span class="mfa-method-icon"><i class="bi bi-phone"></i></span><div><strong>Authenticator app</strong><p>Scan a QR code or enter the setup key manually.</p></div></div><div class="mfa-method-card"><span class="mfa-method-icon"><i class="bi bi-envelope"></i></span><div><strong>Email OTP</strong><p>Send a short-lived verification code to the account email.</p></div></div></div>
    <div class="card border-0 shadow-sm nx-content-card"><div class="card-body p-0"><div class="mfa-table-heading"><div><h2>Account coverage</h2><p>Configure MFA for every account type, including subscriber portal users.</p></div><button type="button" class="btn btn-outline-secondary btn-sm" id="mfaRefresh"><i class="bi bi-arrow-clockwise"></i> Refresh</button></div><div class="table-responsive"><table class="table align-middle mb-0" id="mfaUsersTable"><thead><tr><th>Account</th><th>Role</th><th>Email</th><th>Method</th><th>Status</th><th class="text-end">Action</th></tr></thead><tbody><tr><td colspan="6" class="text-center text-muted py-5">Loading security settings…</td></tr></tbody></table></div></div></div>
    <script src="/module-assets/Mfa/js/mfa.js?v=2"></script>
</div>
