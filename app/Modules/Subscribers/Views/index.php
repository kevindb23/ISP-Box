<?php
$subscribers = $subscribers ?? [];
$plans = $plans ?? [];
$subscribersLegacyUi = isset($_GET['legacy_ui']) && (string)$_GET['legacy_ui'] === '1';

if (!$subscribersLegacyUi):
    $subscriberRows = array_map(static function (array $s): array {
        $online = (int)($s['online'] ?? 0);
        return [
            'id' => (int)($s['id'] ?? 0),
            'account_number' => (string)($s['account_number'] ?? ''),
            'full_name' => (string)($s['full_name'] ?? ''),
            'contact_number' => (string)($s['contact_number'] ?? ''),
            'email' => (string)($s['email'] ?? ''),
            'address' => (string)($s['address'] ?? ''),
            'ppp_username' => (string)($s['ppp_username'] ?? ''),
            'plan_id' => (int)($s['plan_id'] ?? 0),
            'plan_name' => (string)($s['plan_name'] ?? ''),
            'account_type' => (string)($s['account_type'] ?? 'POSTPAID'),
            'service_status' => (string)($s['service_status'] ?? 'UNKNOWN'),
            'service_number' => (string)($s['service_number'] ?? ''),
            'next_due_date' => (string)($s['next_due_date'] ?? ''),
            'expires_at' => (string)($s['expires_at'] ?? ''),
            'nap_name' => (string)($s['nap_name'] ?? ''),
            'nap_splitter_port' => (string)($s['nap_splitter_port'] ?? ''),
            'ont_serial' => (string)($s['ont_serial'] ?? ''),
            'installed_at' => (string)($s['installed_at'] ?? ''),
            'cvlan' => (string)($s['cvlan'] ?? ''),
            'svlan' => (string)($s['svlan'] ?? ''),
            'online' => $online,
            'online_text' => $online === 1 ? 'ONLINE' : 'OFFLINE',
            'last_seen' => $s['last_seen'] ?? null,
        ];
    }, $subscribers);
    $subscriberPlanOptions = array_map(static fn(array $p): array => [
        'id' => (int)($p['id'] ?? 0),
        'plan_name' => (string)($p['plan_name'] ?? ''),
        'plan_type' => (string)($p['plan_type'] ?? ''),
        'is_active' => (int)($p['is_active'] ?? 1),
    ], $plans);
    $nxNextManifestPath = BASE_PATH . '/public/build-next/.vite/manifest.json';
    $nxNextManifest = is_file($nxNextManifestPath) ? (json_decode((string)file_get_contents($nxNextManifestPath), true) ?: []) : [];
    $nxNextEntry = $nxNextManifest['src/main.ts'] ?? [];
    $nxNextVersion = is_file($nxNextManifestPath) ? (string)filemtime($nxNextManifestPath) : (string)time();
    foreach (($nxNextEntry['css'] ?? []) as $nxNextCss): ?>
        <link rel="stylesheet" href="/build-next/<?= htmlspecialchars(ltrim((string)$nxNextCss, '/'), ENT_QUOTES, 'UTF-8') ?>?v=<?= htmlspecialchars($nxNextVersion, ENT_QUOTES, 'UTF-8') ?>">
    <?php endforeach; ?>
    <div class="container-fluid nx-page" data-nx-next-root="subscribers">
        <script type="application/json" data-nx-next-props><?= json_encode(['subscribers' => $subscriberRows, 'plans' => $subscriberPlanOptions], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
    </div>
    <?php if (!empty($nxNextEntry['file'])): ?>
        <script type="module" src="/build-next/<?= htmlspecialchars(ltrim((string)$nxNextEntry['file'], '/'), ENT_QUOTES, 'UTF-8') ?>?v=<?= htmlspecialchars($nxNextVersion, ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php else: ?>
        <div class="alert alert-warning">The new Subscribers interface is not built. Use <a href="/subscribers?legacy_ui=1">the legacy interface</a>.</div>
    <?php endif;
    return;
endif;
?>

<style>
    body#nxApplication #nxRectilinearTheme #subscribersPage .subscribers-add-btn {
        background: #2563eb !important;
        border-color: #2563eb !important;
        color: #fff !important;
    }
    body#nxApplication #nxRectilinearTheme #subscribersPage .subscribers-add-btn:hover,
    body#nxApplication #nxRectilinearTheme #subscribersPage .subscribers-add-btn:focus-visible {
        background: #1d4ed8 !important;
        border-color: #1d4ed8 !important;
        color: #fff !important;
    }
    body#nxApplication #nxRectilinearTheme #subscribersPage .nx-toolbar-card .toolbar-shell {
        padding-right: 0 !important;
        padding-left: 0 !important;
    }
    body#nxApplication #nxRectilinearTheme #subscribersPage .subs-toolbar-grid,
    body#nxApplication #nxRectilinearTheme #subscribersPage .subs-search-wrap,
    body#nxApplication #nxRectilinearTheme #subscribersPage .subs-search-control {
        width: 100% !important;
        max-width: none !important;
    }
    body#nxApplication #nxRectilinearTheme #subscribersPage .subs-toolbar-grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    html:not([data-theme="dark"]) #subscribersPage .nx-modal-content,
    html:not([data-theme="dark"]) #subscribersPage .nx-modal-content > form,
    html:not([data-theme="dark"]) #subscribersPage .nx-modal-header,
    html:not([data-theme="dark"]) #subscribersPage .nx-modal-footer,
    html:not([data-theme="dark"]) #subscribersPage .nx-modal-body,
    html:not([data-theme="dark"]) #subscribersPage .nx-modal-body .nx-section-card {
        background-color: var(--card, #fff) !important;
        color: var(--foreground, #171717) !important;
    }
    html:not([data-theme="dark"]) #subscribersPage .nx-modal-header,
    html:not([data-theme="dark"]) #subscribersPage .nx-modal-footer,
    html:not([data-theme="dark"]) #subscribersPage .nx-modal-body .nx-section-card {
        border-color: var(--border, #d5d5d2) !important;
    }
    html:not([data-theme="dark"]) #subscribersPage .nx-modal-body .nx-field > div,
    html:not([data-theme="dark"]) #subscribersPage .nx-modal-body .nx-section-title,
    html:not([data-theme="dark"]) #subscribersPage .modal-title {
        color: var(--foreground, #171717) !important;
    }
    html:not([data-theme="dark"]) #subscribersPage .nx-modal-body :is(.nx-field > label, .form-label, .text-muted),
    html:not([data-theme="dark"]) #subscribersPage .nx-modal-header .text-muted {
        color: var(--muted-foreground, #737373) !important;
    }

    html[data-theme="dark"] #subscribersPage .nx-modal-content,
    html[data-theme="dark"] #subscribersPage .nx-modal-content > form,
    html[data-theme="dark"] #subscribersPage .nx-modal-header,
    html[data-theme="dark"] #subscribersPage .nx-modal-footer {
        background-color: var(--card, #252728) !important;
        color: var(--foreground, #f5f5f5) !important;
        border-color: var(--border, #414344) !important;
    }
    html[data-theme="dark"] #subscribersPage .nx-modal-body {
        background-color: var(--muted, #202122) !important;
        color: var(--foreground, #f5f5f5) !important;
    }
    html[data-theme="dark"] #subscribersPage .nx-modal-body .nx-section-card {
        background-color: var(--card, #252728) !important;
        color: var(--foreground, #f5f5f5) !important;
        border: 1px solid var(--border, #414344) !important;
        box-shadow: none !important;
    }
    html[data-theme="dark"] #subscribersPage .nx-modal-body .nx-field > div,
    html[data-theme="dark"] #subscribersPage .nx-modal-body .nx-section-title,
    html[data-theme="dark"] #subscribersPage .modal-title {
        color: var(--foreground, #f5f5f5) !important;
    }
    html[data-theme="dark"] #subscribersPage .nx-modal-body :is(.nx-field > label, .form-label, .text-muted),
    html[data-theme="dark"] #subscribersPage .nx-modal-header .text-muted {
        color: var(--muted-foreground, #a3a3a3) !important;
    }
    html[data-theme="dark"] #subscribersPage .nx-modal-header .btn-close {
        filter: invert(1) grayscale(1);
        opacity: .8;
    }
    html[data-theme="dark"] :is(#subscriberViewModal, #subscriberEditModal, #createModal).modal.show {
        background: transparent !important;
    }
    html[data-theme="dark"] body:has(#subscriberViewModal.show) > .modal-backdrop.show,
    html[data-theme="dark"] body:has(#subscriberEditModal.show) > .modal-backdrop.show,
    html[data-theme="dark"] body:has(#createModal.show) > .modal-backdrop.show {
        background-color: #000 !important;
        opacity: .28 !important;
    }
    /* Subscriber details are injected after the modal opens. Keep these final
       selectors stronger than legacy module text-color declarations. */
    html:not([data-theme="dark"]) body#nxApplication #nxRectilinearTheme #subscriberViewModal :is(.modal-title, .nx-section-title, .nx-field > div) {
        color: var(--foreground, #171717) !important;
    }
    html:not([data-theme="dark"]) body#nxApplication #nxRectilinearTheme #subscriberViewModal .nx-field > div {
        background-color: #f8fafc !important;
        border-color: #e2e8f0 !important;
        color: #0f172a !important;
        -webkit-text-fill-color: #0f172a !important;
        opacity: 1 !important;
    }
    html:not([data-theme="dark"]) body#nxApplication #nxRectilinearTheme #subscriberViewModal :is(input, select, textarea, .form-control, .form-select) {
        background-color: #fff !important;
        border-color: #cbd5e1 !important;
        color: #0f172a !important;
        -webkit-text-fill-color: #0f172a !important;
        opacity: 1 !important;
    }
    html:not([data-theme="dark"]) body#nxApplication #nxRectilinearTheme #subscriberViewModal :is(.text-muted, .nx-field > label) {
        color: var(--muted-foreground, #737373) !important;
    }
    html[data-theme="dark"] body#nxApplication #nxRectilinearTheme #subscriberViewModal :is(.modal-title, .nx-section-title, .nx-field > div) {
        color: var(--foreground, #f5f5f5) !important;
    }
    html[data-theme="dark"] body#nxApplication #nxRectilinearTheme #subscriberViewModal .nx-field > div {
        background-color: #202122 !important;
        border-color: #414344 !important;
        color: #f8fafc !important;
        -webkit-text-fill-color: #f8fafc !important;
        opacity: 1 !important;
    }
    html[data-theme="dark"] body#nxApplication #nxRectilinearTheme #subscriberViewModal :is(input, select, textarea, .form-control, .form-select) {
        background-color: #202122 !important;
        border-color: #464849 !important;
        color: #f8fafc !important;
        -webkit-text-fill-color: #f8fafc !important;
        opacity: 1 !important;
    }
    html[data-theme="dark"] body#nxApplication #nxRectilinearTheme #subscriberViewModal :is(.text-muted, .nx-field > label) {
        color: var(--muted-foreground, #a3a3a3) !important;
    }
</style>

<div class="container-fluid nx-page" id="subscribersPage">
    <?php
    $subscribers = $subscribers ?? [];
    $plans = $plans ?? [];
    $searchVal = $searchVal ?? '';

    $subscriberTableRows = array_map(static function ($s) {
        $id = (int)($s['id'] ?? 0);
        $serviceStatus = (string)($s['service_status'] ?? 'UNKNOWN');
        $accountType = (string)($s['account_type'] ?? 'POSTPAID');
        $online = (int)($s['online'] ?? 0);

        return [
                'id' => $id,
                'account_number' => (string)($s['account_number'] ?? ''),
                'full_name' => (string)($s['full_name'] ?? ''),
                'contact_number' => (string)($s['contact_number'] ?? ''),
                'email' => (string)($s['email'] ?? ''),
                'address' => (string)($s['address'] ?? ''),
                'ppp_username' => (string)($s['ppp_username'] ?? ''),
                'plan_id' => (int)($s['plan_id'] ?? 0),
                'plan_name' => (string)($s['plan_name'] ?? ''),
                'account_type' => $accountType,
                'service_status' => $serviceStatus,
                'service_number' => (string)($s['service_number'] ?? ''),
                'next_due_date' => (string)($s['next_due_date'] ?? ''),
                'expires_at' => (string)($s['expires_at'] ?? ''),
                'nap_name' => (string)($s['nap_name'] ?? ''),
                'nap_splitter_port' => (string)($s['nap_splitter_port'] ?? ''),
                'ont_serial' => (string)($s['ont_serial'] ?? ''),
                'installed_at' => (string)($s['installed_at'] ?? ''),
                'cvlan' => (string)($s['cvlan'] ?? ''),
                'svlan' => (string)($s['svlan'] ?? ''),
                'online' => $online,
                'online_text' => $online === 1 ? 'ONLINE' : 'OFFLINE',
                'last_seen' => $s['last_seen'] ?? null,
        ];
    }, $subscribers);

    $totalSubscribers = count($subscribers);
    $activeSubscribers = count(array_filter($subscribers, static fn($s) => strtoupper((string)($s['service_status'] ?? '')) === 'ACTIVE'));
    $suspendedSubscribers = count(array_filter($subscribers, static fn($s) => strtoupper((string)($s['service_status'] ?? '')) === 'SUSPENDED'));
    $onlineSubscribers = count(array_filter($subscribers, static fn($s) => (int)($s['online'] ?? 0) === 1));
    ?>

    <div class="card border-0 shadow-sm nx-page-header-card">
        <div class="card-body nx-page-header">
            <div class="nx-page-header-left">
                <div class="subs-hero-badge">
                    <i class="bi bi-stars"></i>
                    <span>Subscriber Registry</span>
                </div>

                <h5 class="nx-page-title">Subscribers</h5>
                <div class="nx-page-subtitle">
                    Manage subscriber accounts, PPP credentials, plan assignments, and service status across your access network.
                </div>
            </div>

            <div class="nx-page-actions">
                <button class="btn btn-primary nx-header-btn subscribers-add-btn" data-bs-toggle="modal" data-bs-target="#createModal">
                    <i class="bi bi-person-plus"></i>
                    <span>Add Subscriber</span>
                </button>

                <button type="button" class="btn btn-light border nx-header-btn" id="subscribersRefreshBtn">
                    <i class="bi bi-arrow-clockwise"></i>
                    <span>Refresh</span>
                </button>
            </div>
        </div>
    </div>

    <div class="row g-3 nx-subscriber-summary-row">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="nx-summary-card summary-item nx-summary-primary">
                <div class="nx-summary-top">
                    <div>
                        <div class="nx-summary-label">Total Subscribers</div>
                        <div class="nx-summary-value" id="subscribersStatTotal"><?= (int)$totalSubscribers ?></div>
                        <div class="nx-summary-text">Registered commercial accounts</div>
                    </div>
                    <div class="nx-summary-icon">
                        <i class="bi bi-people"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="nx-summary-card summary-item nx-summary-success">
                <div class="nx-summary-top">
                    <div>
                        <div class="nx-summary-label">Active Services</div>
                        <div class="nx-summary-value" id="subscribersStatActive"><?= (int)$activeSubscribers ?></div>
                        <div class="nx-summary-text">Commercially active subscriber lines</div>
                    </div>
                    <div class="nx-summary-icon">
                        <i class="bi bi-check-circle"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="nx-summary-card summary-item nx-summary-danger">
                <div class="nx-summary-top">
                    <div>
                        <div class="nx-summary-label">Suspended</div>
                        <div class="nx-summary-value" id="subscribersStatSuspended"><?= (int)$suspendedSubscribers ?></div>
                        <div class="nx-summary-text">Temporarily disabled subscriber services</div>
                    </div>
                    <div class="nx-summary-icon">
                        <i class="bi bi-pause-circle"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="nx-summary-card summary-item nx-summary-cyan">
                <div class="nx-summary-top">
                    <div>
                        <div class="nx-summary-label">Currently Online</div>
                        <div class="nx-summary-value" id="subscribersStatOnline"><?= (int)$onlineSubscribers ?></div>
                        <div class="nx-summary-text">Live PPP sessions seen in Radius</div>
                    </div>
                    <div class="nx-summary-icon">
                        <i class="bi bi-wifi"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm nx-toolbar-card">
        <div class="card-body toolbar-shell">
            <div class="subs-toolbar-grid">
                <div class="subs-search-wrap">
                    <i class="bi bi-search subs-search-icon"></i>
                    <input type="text"
                           id="subscribersSearchInput"
                           class="form-control subs-toolbar-control subs-search-control toolbar-control"
                           placeholder="Search by account number, subscriber, PPP username, email, or contact number"
                           value="<?= htmlspecialchars($searchVal) ?>">
                </div>
            </div>
        </div>
    </div>

    <div id="subscribersTableShell" class="table-container">
        <div id="subscribersTableHost"></div>
    </div>

    <script id="subscribersTableData" type="application/json"><?= json_encode($subscriberTableRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

    <script id="subscriberPlanOptions" type="application/json"><?= json_encode(array_map(static function ($p) {
            return [
                    'id' => (int)($p['id'] ?? 0),
                    'plan_name' => (string)($p['plan_name'] ?? ''),
                    'plan_type' => (string)($p['plan_type'] ?? ''),
                    'speed_mbps' => (int)($p['speed_mbps'] ?? 0),
            ];
        }, $plans), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

    <div class="modal fade nx-modal" id="subscriberViewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
            <div class="modal-content nx-modal-content">
                <div class="modal-header nx-modal-header">
                    <div>
                        <h5 class="modal-title mb-0">Subscriber Details</h5>
                        <small class="text-muted" id="subscriberViewSubtitle">Loading...</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body nx-modal-body" id="subscriberViewBody">
                    <div class="text-center py-4 text-muted">Loading...</div>
                </div>

                <div class="modal-footer nx-modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade nx-modal" id="subscriberEditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
            <div class="modal-content nx-modal-content">
                <form id="subscriberEditForm">
                    <div class="modal-header nx-modal-header">
                        <div>
                            <h5 class="modal-title mb-0">Edit Subscriber</h5>
                            <small class="text-muted" id="subscriberEditSubtitle">Loading...</small>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body nx-modal-body">
                        <input type="hidden" name="id">

                        <div class="nx-section-card">
                            <div class="nx-section-title mb-3">
                                <i class="bi bi-person text-primary"></i>
                                <span>Subscriber Info</span>
                            </div>

                            <div class="nx-grid-2">
                                <div>
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control" name="full_name" required>
                                </div>

                                <div>
                                    <label class="form-label">Email</label>
                                    <input type="text" class="form-control" name="email">
                                </div>

                                <div>
                                    <label class="form-label">Contact Number</label>
                                    <input type="text" class="form-control" name="contact_number">
                                </div>

                                <div class="nx-span-2">
                                    <label class="form-label">Address</label>
                                    <input type="text" class="form-control" name="address">
                                </div>
                            </div>
                        </div>

                        <div class="nx-section-card">
                            <div class="nx-section-title mb-3">
                                <i class="bi bi-diagram-3 text-success"></i>
                                <span>Service Info</span>
                            </div>

                            <div class="nx-grid-2">
                                <div>
                                    <label class="form-label">Plan</label>
                                    <select class="form-select" name="plan_id" id="subscriberEditPlanSelect" required>
                                        <option value="">Select Plan</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="form-label">Current Status</label>
                                    <input type="text" class="form-control" name="service_status_display" readonly>
                                </div>

                                <div>
                                    <label class="form-label">PPP Username</label>
                                    <input type="text" class="form-control" name="ppp_username_display" readonly>
                                </div>

                                <div>
                                    <label class="form-label">PPP Password</label>
                                    <input type="text" class="form-control" name="ppp_password_display" readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer nx-modal-footer">
                        <button type="button"
                                class="btn btn-outline-dark js-dynamic-reset-password"
                                data-id="">
                            <i class="bi bi-key"></i>
                            <span>Reset PPP Password</span>
                        </button>

                        <div class="ms-auto d-flex gap-2 flex-wrap">
                            <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-check-circle"></i>
                                <span>Save Changes</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade nx-modal" id="createModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content nx-modal-content">
                <form id="createSubscriberForm">
                    <div class="modal-header nx-modal-header">
                        <div>
                            <h5 class="modal-title mb-0">Create Subscriber</h5>
                            <small class="text-muted">Create the subscriber record first. ONT and service path assignment will be handled in provisioning.</small>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body nx-modal-body">
                        <div class="nx-section-card">
                            <div class="nx-section-title mb-3">
                                <i class="bi bi-person text-primary"></i>
                                <span>Subscriber Info</span>
                            </div>

                            <div class="nx-grid-2">
                                <div>
                                    <label class="form-label">Full Name</label>
                                    <input type="text" name="full_name" class="form-control" required>
                                </div>

                                <div>
                                    <label class="form-label">Email</label>
                                    <input type="text" name="email" class="form-control">
                                </div>

                                <div>
                                    <label class="form-label">Contact Number</label>
                                    <input type="text" name="contact_number" class="form-control">
                                </div>

                                <div class="nx-span-2">
                                    <label class="form-label">Address</label>
                                    <input type="text" name="address" class="form-control">
                                </div>
                            </div>
                        </div>

                        <div class="nx-section-card">
                            <div class="nx-section-title mb-3">
                                <i class="bi bi-diagram-3 text-success"></i>
                                <span>Service Info</span>
                            </div>

                            <div class="nx-grid-2">
                                <div class="nx-span-2">
                                    <label class="form-label">Plan</label>
                                    <select name="plan_id" class="form-select" required>
                                        <option value="">Select Plan</option>
                                        <?php foreach ($plans as $p): ?>
                                            <option value="<?= (int)$p['id'] ?>">
                                                <?= htmlspecialchars((string)$p['plan_name']) ?>
                                                - <?= (int)$p['speed_mbps'] ?> Mbps
                                                - <?= htmlspecialchars((string)$p['plan_type']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer nx-modal-footer">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-check-circle"></i>
                            <span>Create Subscriber</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="/module-assets/Subscribers/js/subscribers.js?v=plans3"></script>
</div>
