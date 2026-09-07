<<<<<<< HEAD
<?php
$maintenanceState = is_array($maintenanceState ?? null) ? $maintenanceState : [];
if (!empty($maintenanceState['active'])):
    $maintenanceMessage = trim((string)($maintenanceState['message'] ?? '')) ?: 'We are performing scheduled maintenance. Please try again soon.';
?>
<div class="container-fluid nx-page subscriber-portal-page d-flex min-vh-75 align-items-center justify-content-center" id="subscriberMaintenancePage">
    <div class="card border-0 shadow-sm text-center" style="max-width: 680px;">
        <div class="card-body p-4 p-md-5">
            <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary" style="width: 64px; height: 64px;">
                <i class="bi bi-tools fs-3" aria-hidden="true"></i>
            </div>
            <div class="text-primary small fw-semibold text-uppercase" style="letter-spacing: .08em;">System maintenance</div>
            <h1 class="h3 mt-2">We are sorry for the interruption</h1>
            <p class="lead mb-0"><?= nl2br(htmlspecialchars($maintenanceMessage, ENT_QUOTES, 'UTF-8')) ?></p>
            <p class="text-muted mt-3 mb-0">Thank you for your patience. Please check back shortly.</p>
        </div>
    </div>
</div>
<?php return; endif; ?>
=======
>>>>>>> origin/main
<div class="container-fluid nx-page subscriber-portal-page sp-security-page" id="subscriberSecurityPage">
    <div id="subscriberSecurityAlert" role="status" aria-live="polite"></div>

    <div class="card border-0 shadow-sm mb-3 sp-security-header-card">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <div class="sp-security-kicker"><i class="bi bi-shield-lock" aria-hidden="true"></i> ACCOUNT SECURITY</div>
                    <h1 class="h3 mb-2">Security</h1>
                    <p class="text-muted mb-0">Protect your subscriber portal account with a second verification step.</p>
                </div>
                <a class="btn btn-sm btn-light border" href="/subscriber-portal">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i> Back to My Account
                </a>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="sp-security-status-icon" id="spMfaStatusIcon" aria-hidden="true"><i class="bi bi-shield-exclamation"></i></div>
                    <div>
                        <h2 class="h5 mb-1" id="spMfaStatusTitle">Loading MFA status…</h2>
                        <p class="text-muted mb-0" id="spMfaStatusMessage">Checking your account security settings.</p>
                    </div>
                </div>
                <span class="badge bg-secondary" id="spMfaStatusBadge">Loading</span>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm" id="spMfaSetupCard">
        <div class="card-header bg-white border-0">
            <h2 class="h5 mb-1">Choose your verification method</h2>
            <p class="text-muted mb-0">You can use an authenticator app or receive a one-time code by email.</p>
        </div>
        <div class="card-body">
            <form id="spMfaMethodForm">
                <fieldset>
                    <legend class="visually-hidden">MFA verification method</legend>
                    <div class="row g-3">
                        <div class="col-12 col-lg-6">
                            <label class="sp-security-method h-100" for="spMfaAuthenticator">
                                <input class="form-check-input" type="radio" name="method" id="spMfaAuthenticator" value="AUTHENTICATOR" checked>
                                <span class="sp-security-method-icon" aria-hidden="true"><i class="bi bi-phone"></i></span>
                                <span>
                                    <strong class="d-block">Authenticator app</strong>
                                    <small class="text-muted">Scan a QR code with Google Authenticator, Microsoft Authenticator, or a compatible app.</small>
                                </span>
                            </label>
                        </div>
                        <div class="col-12 col-lg-6">
                            <label class="sp-security-method h-100" for="spMfaEmail">
                                <input class="form-check-input" type="radio" name="method" id="spMfaEmail" value="EMAIL">
                                <span class="sp-security-method-icon" aria-hidden="true"><i class="bi bi-envelope"></i></span>
                                <span>
                                    <strong class="d-block">Email OTP</strong>
                                    <small class="text-muted" id="spMfaEmailHelp">Receive a short-lived six-digit code at your account email.</small>
                                </span>
                            </label>
                        </div>
                    </div>
                </fieldset>
                <button class="btn btn-primary mt-4" type="submit" id="spMfaStartBtn">
                    <i class="bi bi-shield-check" aria-hidden="true"></i> Set up MFA
                </button>
            </form>

            <div class="sp-security-setup mt-4" id="spMfaSetupPanel" hidden>
                <p class="text-muted small mb-3" id="spMfaSetupNotice">Your current MFA method remains active until this new method is verified.</p>
                <div class="sp-security-setup-content" id="spMfaSetupContent"></div>
                <label class="form-label mt-3" for="spMfaCode">Verification code</label>
                <input class="form-control" id="spMfaCode" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="000000">
                <div class="form-text">Enter the current six-digit code to enable MFA on this account.</div>
                <button class="btn btn-primary mt-3" type="button" id="spMfaCompleteBtn">
                    <i class="bi bi-check2-circle" aria-hidden="true"></i> Verify and enable
                </button>
            </div>

            <div class="sp-security-disable mt-4" id="spMfaDisablePanel" hidden>
                <div>
                    <strong>MFA is protecting this account.</strong>
                    <p class="text-muted mb-0" id="spMfaCurrentMethod">Current method: —</p>
                </div>
                <button class="btn btn-outline-danger" type="button" id="spMfaDisableBtn">
                    <i class="bi bi-shield-x" aria-hidden="true"></i> Disable MFA
                </button>
            </div>
        </div>
    </div>
</div>
<link rel="stylesheet" href="/module-assets/SubscriberPortal/css/SubscriberPortalSecurity.css?v=3">
<script src="/module-assets/SubscriberPortal/js/SubscriberPortalSecurity.js?v=3" defer></script>
