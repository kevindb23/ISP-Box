<div class="container-fluid nx-page" id="subscribersPage">
    <link rel="stylesheet" href="/module-assets/Subscribers/css/subscribers.css">

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
                'ppp_password' => (string)($s['ppp_password'] ?? ''),
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
                <button class="btn btn-primary nx-header-btn" data-bs-toggle="modal" data-bs-target="#createModal">
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
            <div class="nx-summary-card nx-summary-primary">
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
            <div class="nx-summary-card nx-summary-success">
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
            <div class="nx-summary-card nx-summary-danger">
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
            <div class="nx-summary-card nx-summary-cyan">
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
        <div class="card-body">
            <div class="subs-toolbar-grid">
                <div class="subs-search-wrap">
                    <i class="bi bi-search subs-search-icon"></i>
                    <input type="text"
                           id="subscribersSearchInput"
                           class="form-control subs-toolbar-control subs-search-control"
                           placeholder="Search by account number, subscriber, PPP username, email, or contact number"
                           value="<?= htmlspecialchars($searchVal) ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm nx-content-card">
        <div class="subs-table-topline"></div>
        <div class="card-body">
            <div id="subscribersTableHost"></div>
        </div>
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

    <script src="/module-assets/Subscribers/js/subscribers.js"></script>
</div>