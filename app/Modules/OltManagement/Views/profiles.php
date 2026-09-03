<?php
$selectedOltId = (int)($selectedOltId ?? 0);
$selectedOlt = $selectedOlt ?? null;
$oltDevices = $oltDevices ?? [];
$activeTab = strtolower((string)($activeTab ?? 'dba'));

$allowedTabs = ['dba', 'line', 'wan', 'tr069', 'srv'];
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'dba';
}
$oltProfilesLegacyUi = isset($_GET['legacy_ui']) && (string)$_GET['legacy_ui'] === '1';
if (!$oltProfilesLegacyUi):
    $nxNextManifestPath = BASE_PATH . '/public/build-next/.vite/manifest.json'; $nxNextManifest = is_file($nxNextManifestPath) ? (json_decode((string)file_get_contents($nxNextManifestPath), true) ?: []) : []; $nxNextEntry = $nxNextManifest['src/main.ts'] ?? []; $nxNextVersion = is_file($nxNextManifestPath) ? (string)filemtime($nxNextManifestPath) : (string)time();
    foreach (($nxNextEntry['css'] ?? []) as $nxNextCss): ?><link rel="stylesheet" href="/build-next/<?= htmlspecialchars(ltrim((string)$nxNextCss, '/'), ENT_QUOTES, 'UTF-8') ?>?v=<?= htmlspecialchars($nxNextVersion, ENT_QUOTES, 'UTF-8') ?>"><?php endforeach; ?>
    <div class="container-fluid nx-page" data-nx-next-root="olt" data-mode="profiles" data-olt-id="<?= $selectedOltId ?>" data-profile-tab="<?= htmlspecialchars($activeTab, ENT_QUOTES, 'UTF-8') ?>"></div>
    <?php if (!empty($nxNextEntry['file'])): ?><script type="module" src="/build-next/<?= htmlspecialchars(ltrim((string)$nxNextEntry['file'], '/'), ENT_QUOTES, 'UTF-8') ?>?v=<?= htmlspecialchars($nxNextVersion, ENT_QUOTES, 'UTF-8') ?>"></script><?php else: ?><div class="alert alert-warning">The new OLT Profiles interface is not built. Use the legacy interface.</div><?php endif; return;
endif;
?>

<div class="container-fluid nx-page" id="oltManagementPage">
    <div id="oltManagementApp"
         data-page-mode="profiles"
         data-initial-olt-id="<?= (int)$selectedOltId ?>"
         data-active-tab="<?= htmlspecialchars($activeTab) ?>">

        <!-- HERO -->
        <section class="card border-0 mb-0 nx-page-header-card">
            <div class="nx-page-header">
                <div class="nx-page-header-left">
                    <div class="page-hero-badge">
                        <i class="bi bi-sliders2"></i>
                        <span>Provisioning Profiles Workspace</span>
                    </div>

                    <h1 class="nx-page-title">OLT Profiles</h1>
                    <p class="nx-page-subtitle" id="oltPageSubtitle">
                        Manage DBA, Line, WAN, TR069, and Service profiles for the selected OLT
                    </p>
                </div>

                <div class="nx-page-actions" id="oltHeaderActions">
                    <!-- NX rendered -->
                </div>
            </div>
        </section>

        <!-- SUMMARY -->
        <div class="row g-3 nx-olt-summary-row" id="oltKpiStrip"></div>

        <!-- TOOLBAR -->
        <section class="card border-0 nx-toolbar-card" id="oltToolbarCard">
            <div class="card-body">
                <div id="oltToolbarArea">
                    <!-- NX rendered -->
                </div>
            </div>
        </section>

        <!-- PROFILES WORKSPACE -->
        <section class="card border-0 nx-content-card" id="oltProfilesCard">
            <div class="olt-table-topline"></div>

            <div class="card-body">
                <div class="section-head mb-3">
                    <div>
                        <h2 class="section-title">Provisioning Profiles</h2>
                        <p class="section-subtitle">
                            Switch between Huawei profile objects inside one OLT-scoped workspace
                        </p>
                    </div>
                </div>

                <!-- EMPTY STATE -->
                <div id="oltProfilesEmptyState" class="<?= $selectedOltId > 0 ? 'd-none' : '' ?>">
                    <div class="nx-empty-state">
                        <div class="nx-empty-icon">
                            <i class="bi bi-router"></i>
                        </div>
                        <h3 class="nx-empty-title">No OLT selected</h3>
                        <p class="nx-empty-text">
                            Choose an OLT from the selector above, or go back to the device list and click Profiles from a specific OLT row.
                        </p>
                    </div>
                </div>

                <!-- TAB PILLS -->
                <div class="nx-segment-control mb-4 <?= $selectedOltId > 0 ? '' : 'd-none' ?>" id="oltProfilesTabs">
                    <button type="button"
                            class="nx-segment-item <?= $activeTab === 'dba' ? 'active' : '' ?>"
                            data-profile-tab="dba">
                        DBA
                    </button>

                    <button type="button"
                            class="nx-segment-item <?= $activeTab === 'line' ? 'active' : '' ?>"
                            data-profile-tab="line">
                        Line
                    </button>

                    <button type="button"
                            class="nx-segment-item <?= $activeTab === 'wan' ? 'active' : '' ?>"
                            data-profile-tab="wan">
                        WAN
                    </button>

                    <button type="button"
                            class="nx-segment-item <?= $activeTab === 'tr069' ? 'active' : '' ?>"
                            data-profile-tab="tr069">
                        TR069
                    </button>

                    <button type="button"
                            class="nx-segment-item <?= $activeTab === 'srv' ? 'active' : '' ?>"
                            data-profile-tab="srv">
                        SRV
                    </button>
                </div>

                <!-- TAB CONTENT -->
                <div id="oltProfilesWorkspace" class="<?= $selectedOltId > 0 ? '' : 'd-none' ?>">

                    <div class="olt-profile-pane <?= $activeTab === 'dba' ? '' : 'd-none' ?>" data-profile-pane="dba">
                        <div id="oltProfilesDbaWorkspace">
                            <div class="nx-section-card">
                                <div class="nx-section-title">
                                    <i class="bi bi-speedometer2"></i>
                                    <span>DBA Profiles</span>
                                </div>

                                <div class="nx-empty-state">
                                    <div class="nx-empty-icon">
                                        <i class="bi bi-speedometer2"></i>
                                    </div>
                                    <h3 class="nx-empty-title">DBA workspace ready</h3>
                                    <p class="nx-empty-text">
                                        DBA profile table and forms will be rendered here.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="olt-profile-pane <?= $activeTab === 'line' ? '' : 'd-none' ?>" data-profile-pane="line">
                        <div id="oltProfilesLineWorkspace">
                            <div class="nx-section-card">
                                <div class="nx-section-title">
                                    <i class="bi bi-diagram-3"></i>
                                    <span>Line Profiles</span>
                                </div>

                                <div class="nx-empty-state">
                                    <div class="nx-empty-icon">
                                        <i class="bi bi-diagram-3"></i>
                                    </div>
                                    <h3 class="nx-empty-title">Line workspace ready</h3>
                                    <p class="nx-empty-text">
                                        Per-CVLAN line profile table and forms will be rendered here.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="olt-profile-pane <?= $activeTab === 'wan' ? '' : 'd-none' ?>" data-profile-pane="wan">
                        <div id="oltProfilesWanWorkspace">
                            <div class="nx-section-card">
                                <div class="nx-section-title">
                                    <i class="bi bi-globe2"></i>
                                    <span>WAN Profiles</span>
                                </div>

                                <div class="nx-empty-state">
                                    <div class="nx-empty-icon">
                                        <i class="bi bi-globe2"></i>
                                    </div>
                                    <h3 class="nx-empty-title">WAN workspace ready</h3>
                                    <p class="nx-empty-text">
                                        PPPoE and DHCP WAN profile table and forms will be rendered here.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="olt-profile-pane <?= $activeTab === 'tr069' ? '' : 'd-none' ?>" data-profile-pane="tr069">
                        <div id="oltProfilesTr069Workspace">
                            <div class="nx-section-card">
                                <div class="nx-section-title">
                                    <i class="bi bi-cloud-check"></i>
                                    <span>TR069 Profiles</span>
                                </div>

                                <div class="nx-empty-state">
                                    <div class="nx-empty-icon">
                                        <i class="bi bi-cloud-check"></i>
                                    </div>
                                    <h3 class="nx-empty-title">TR069 workspace ready</h3>
                                    <p class="nx-empty-text">
                                        TR069 server profile table and forms will be rendered here.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="olt-profile-pane <?= $activeTab === 'srv' ? '' : 'd-none' ?>" data-profile-pane="srv">
                        <div id="oltProfilesSrvWorkspace">
                            <div class="nx-section-card">
                                <div class="nx-section-title">
                                    <i class="bi bi-ethernet"></i>
                                    <span>Service Profiles</span>
                                </div>

                                <div class="nx-empty-state">
                                    <div class="nx-empty-icon">
                                        <i class="bi bi-ethernet"></i>
                                    </div>
                                    <h3 class="nx-empty-title">Service workspace ready</h3>
                                    <p class="nx-empty-text">
                                        ONT service profile table and forms will be rendered here.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>
    </div>

    <!-- =========================================================
         LINE PROFILE MODALS
    ========================================================== -->

    <div class="modal fade" id="lineProfileCreateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content nx-modal">
                <div class="modal-header nx-modal-header">
                    <div>
                        <h5 class="modal-title mb-1">Create Line Profile</h5>
                        <div class="nx-modal-subtitle">Define a reusable Huawei line profile for subscriber CVLAN provisioning.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <form id="createLineProfileForm">
                    <div class="modal-body nx-modal-body">
                        <div class="row g-3">
                            <input type="hidden" name="olt_id" id="createLineProfileOltId" value="<?= (int)$selectedOltId ?>">

                            <div class="col-12 col-md-4">
                                <label class="form-label">Profile ID</label>
                                <input type="number" class="form-control" name="profile_id" id="createLineProfileProfileId" required>
                            </div>

                            <div class="col-12 col-md-8">
                                <label class="form-label">Profile Name</label>
                                <input type="text" class="form-control" name="profile_name" id="createLineProfileProfileName" required>
                            </div>

                            <div class="col-12 col-md-3">
                                <label class="form-label">Customer CVLAN</label>
                                <input type="number" class="form-control" name="customer_cvlan" id="createLineProfileCustomerCvlan" required>
                            </div>

                            <div class="col-12 col-md-3">
                                <label class="form-label">Management VLAN</label>
                                <input type="number" class="form-control" name="management_vlan" id="createLineProfileManagementVlan" value="25" required>
                            </div>

                            <div class="col-12 col-md-3">
                                <label class="form-label">DBA Profile ID</label>
                                <input type="number" class="form-control" name="dba_profile_id" id="createLineProfileDbaProfileId" required>
                            </div>

                            <div class="col-12 col-md-3">
                                <label class="form-label">TCONT ID</label>
                                <input type="number" class="form-control" name="tcont_id" id="createLineProfileTcontId" value="4" required>
                            </div>

                            <div class="col-12 col-md-3">
                                <label class="form-label">Subscriber GEM ID</label>
                                <input type="number" class="form-control" name="gem_subscriber_id" id="createLineProfileGemSubscriberId" value="10" required>
                            </div>

                            <div class="col-12 col-md-3">
                                <label class="form-label">Management GEM ID</label>
                                <input type="number" class="form-control" name="gem_management_id" id="createLineProfileGemManagementId" value="11" required>
                            </div>

                            <div class="col-12 col-md-3">
                                <label class="form-label">TR069 IP Index</label>
                                <input type="number" class="form-control" name="tr069_ip_index" id="createLineProfileTr069IpIndex" value="1">
                            </div>

                            <div class="col-12 col-md-3">
                                <label class="form-label">OMCC Encrypt</label>
                                <select class="form-select" name="omcc_encrypt" id="createLineProfileOmccEncrypt">
                                    <option value="1" selected>Enabled</option>
                                    <option value="0">Disabled</option>
                                </select>
                            </div>

                            <div class="col-12 col-md-3">
                                <label class="form-label">TR069 Management</label>
                                <select class="form-select" name="tr069_management_enable" id="createLineProfileTr069Enable">
                                    <option value="1" selected>Enabled</option>
                                    <option value="0">Disabled</option>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" id="createLineProfileDescription" rows="3" placeholder="Optional notes for provisioning or operations"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer nx-modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2-circle"></i>
                            <span>Create Line Profile</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="lineProfileEditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content nx-modal">
                <div class="modal-header nx-modal-header">
                    <div>
                        <h5 class="modal-title mb-1">Edit Line Profile</h5>
                        <div class="nx-modal-subtitle" id="editLineProfileSubtitle">Update provisioning mapping and GEM definitions.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <form id="editLineProfileForm" data-id="">
                    <div class="modal-body nx-modal-body">
                        <div class="row g-3">
                            <input type="hidden" name="olt_id" id="editLineProfileOltId" value="<?= (int)$selectedOltId ?>">

                            <div class="col-12 col-md-4">
                                <label class="form-label">Profile ID</label>
                                <input type="number" class="form-control" name="profile_id" id="editLineProfileProfileId" required>
                            </div>

                            <div class="col-12 col-md-8">
                                <label class="form-label">Profile Name</label>
                                <input type="text" class="form-control" name="profile_name" id="editLineProfileProfileName" required>
                            </div>

                            <div class="col-12 col-md-3">
                                <label class="form-label">Customer CVLAN</label>
                                <input type="number" class="form-control" name="customer_cvlan" id="editLineProfileCustomerCvlan" required>
                            </div>

                            <div class="col-12 col-md-3">
                                <label class="form-label">Management VLAN</label>
                                <input type="number" class="form-control" name="management_vlan" id="editLineProfileManagementVlan" required>
                            </div>

                            <div class="col-12 col-md-3">
                                <label class="form-label">DBA Profile ID</label>
                                <input type="number" class="form-control" name="dba_profile_id" id="editLineProfileDbaProfileId" required>
                            </div>

                            <div class="col-12 col-md-3">
                                <label class="form-label">TCONT ID</label>
                                <input type="number" class="form-control" name="tcont_id" id="editLineProfileTcontId" required>
                            </div>

                            <div class="col-12 col-md-3">
                                <label class="form-label">Subscriber GEM ID</label>
                                <input type="number" class="form-control" name="gem_subscriber_id" id="editLineProfileGemSubscriberId" required>
                            </div>

                            <div class="col-12 col-md-3">
                                <label class="form-label">Management GEM ID</label>
                                <input type="number" class="form-control" name="gem_management_id" id="editLineProfileGemManagementId" required>
                            </div>

                            <div class="col-12 col-md-3">
                                <label class="form-label">TR069 IP Index</label>
                                <input type="number" class="form-control" name="tr069_ip_index" id="editLineProfileTr069IpIndex">
                            </div>

                            <div class="col-12 col-md-3">
                                <label class="form-label">OMCC Encrypt</label>
                                <select class="form-select" name="omcc_encrypt" id="editLineProfileOmccEncrypt">
                                    <option value="1">Enabled</option>
                                    <option value="0">Disabled</option>
                                </select>
                            </div>

                            <div class="col-12 col-md-3">
                                <label class="form-label">TR069 Management</label>
                                <select class="form-select" name="tr069_management_enable" id="editLineProfileTr069Enable">
                                    <option value="1">Enabled</option>
                                    <option value="0">Disabled</option>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" id="editLineProfileDescription" rows="3"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer nx-modal-footer">
                        <button type="button" class="btn btn-outline-danger me-auto" id="deleteLineProfileBtn">
                            <i class="bi bi-trash"></i>
                            <span>Delete</span>
                        </button>
                        <button type="button" class="btn btn-outline-primary" id="previewLineProfileCliBtn">
                            <i class="bi bi-terminal"></i>
                            <span>CLI Preview</span>
                        </button>
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i>
                            <span>Save Changes</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="lineProfileViewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content nx-modal">
                <div class="modal-header nx-modal-header">
                    <div>
                        <h5 class="modal-title mb-1">Line Profile Details</h5>
                        <div class="nx-modal-subtitle" id="viewLineProfileSubtitle">Inspect the selected provisioning profile.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body nx-modal-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <div class="nx-detail-item">
                                <div class="nx-detail-label">Profile Name</div>
                                <div class="nx-detail-value" id="viewLineProfileName">-</div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="nx-detail-item">
                                <div class="nx-detail-label">Profile ID</div>
                                <div class="nx-detail-value" id="viewLineProfileId">-</div>
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="nx-detail-item">
                                <div class="nx-detail-label">Customer CVLAN</div>
                                <div class="nx-detail-value" id="viewLineProfileCustomerCvlan">-</div>
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="nx-detail-item">
                                <div class="nx-detail-label">Management VLAN</div>
                                <div class="nx-detail-value" id="viewLineProfileManagementVlan">-</div>
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="nx-detail-item">
                                <div class="nx-detail-label">DBA Profile</div>
                                <div class="nx-detail-value" id="viewLineProfileDbaProfileId">-</div>
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="nx-detail-item">
                                <div class="nx-detail-label">TCONT ID</div>
                                <div class="nx-detail-value" id="viewLineProfileTcontId">-</div>
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="nx-detail-item">
                                <div class="nx-detail-label">TR069 IP Index</div>
                                <div class="nx-detail-value" id="viewLineProfileTr069IpIndex">-</div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="nx-detail-item">
                                <div class="nx-detail-label">Subscriber GEM ID</div>
                                <div class="nx-detail-value" id="viewLineProfileGemSubscriberId">-</div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="nx-detail-item">
                                <div class="nx-detail-label">Management GEM ID</div>
                                <div class="nx-detail-value" id="viewLineProfileGemManagementId">-</div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="nx-detail-item">
                                <div class="nx-detail-label">TR069 Management</div>
                                <div class="nx-detail-value" id="viewLineProfileTr069Enable">-</div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="nx-detail-item">
                                <div class="nx-detail-label">OMCC Encrypt</div>
                                <div class="nx-detail-value" id="viewLineProfileOmccEncrypt">-</div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="nx-detail-item">
                                <div class="nx-detail-label">Description</div>
                                <div class="nx-detail-value" id="viewLineProfileDescription">-</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer nx-modal-footer">
                    <button type="button" class="btn btn-outline-primary" id="openEditLineProfileFromViewBtn">
                        <i class="bi bi-pencil"></i>
                        <span>Edit</span>
                    </button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="lineProfileCliPreviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content nx-modal">
                <div class="modal-header nx-modal-header">
                    <div>
                        <h5 class="modal-title mb-1">CLI Preview</h5>
                        <div class="nx-modal-subtitle" id="lineProfileCliPreviewSubtitle">Generated Huawei CLI for the selected line profile.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body nx-modal-body">
                    <div class="nx-code-block-wrap">
                        <pre class="nx-code-block mb-0" id="lineProfileCliPreviewContent">No preview loaded.</pre>
                    </div>
                </div>

                <div class="modal-footer nx-modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-outline-primary" id="copyLineProfileCliPreviewBtn">
                        <i class="bi bi-clipboard"></i>
                        <span>Copy CLI</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- =========================================================
         DBA PROFILE MODALS
    ========================================================== -->

    <div class="modal fade" id="dbaProfileCreateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content nx-modal">
                <div class="modal-header nx-modal-header">
                    <div>
                        <h5 class="modal-title mb-1">Create DBA Profile</h5>
                        <div class="nx-modal-subtitle">Define bandwidth behavior for Huawei DBA profile objects.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <form id="createDbaProfileForm">
                    <div class="modal-body nx-modal-body">
                        <div class="row g-3">
                            <input type="hidden" name="olt_id" id="createDbaProfileOltId" value="<?= (int)$selectedOltId ?>">

                            <div class="col-12 col-md-4">
                                <label class="form-label">Profile ID</label>
                                <input type="number" class="form-control" name="profile_id" id="createDbaProfileProfileId" required>
                            </div>

                            <div class="col-12 col-md-8">
                                <label class="form-label">Profile Name</label>
                                <input type="text" class="form-control" name="profile_name" id="createDbaProfileProfileName" required>
                            </div>

                            <div class="col-12 col-md-3">
                                <label class="form-label">Type</label>
                                <select class="form-select" name="profile_type" id="createDbaProfileType">
                                    <option value="type3" selected>Type 3</option>
                                    <option value="type4">Type 4</option>
                                    <option value="type5">Type 5</option>
                                </select>
                            </div>

                            <div class="col-12 col-md-3">
                                <label class="form-label">Bandwidth Unit</label>
                                <select class="form-select" name="bandwidth_unit" id="createDbaProfileBandwidthUnit">
                                    <option value="kbit" selected>Kbit/s</option>
                                    <option value="mbit">Mbit/s</option>
                                </select>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Assure</label>
                                <input type="number" class="form-control" name="assure" id="createDbaProfileAssure" required>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Max</label>
                                <input type="number" class="form-control" name="max" id="createDbaProfileMax" required>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Priority</label>
                                <input type="number" class="form-control" name="priority" id="createDbaProfilePriority" value="0">
                            </div>

                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" id="createDbaProfileDescription" rows="3"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer nx-modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2-circle"></i>
                            <span>Create DBA Profile</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="dbaProfileEditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content nx-modal">
                <div class="modal-header nx-modal-header">
                    <div>
                        <h5 class="modal-title mb-1">Edit DBA Profile</h5>
                        <div class="nx-modal-subtitle" id="editDbaProfileSubtitle">Update bandwidth behavior and scope.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <form id="editDbaProfileForm" data-id="">
                    <div class="modal-body nx-modal-body">
                        <div class="row g-3">
                            <input type="hidden" name="olt_id" id="editDbaProfileOltId" value="<?= (int)$selectedOltId ?>">

                            <div class="col-12 col-md-4">
                                <label class="form-label">Profile ID</label>
                                <input type="number" class="form-control" name="profile_id" id="editDbaProfileProfileId" required>
                            </div>

                            <div class="col-12 col-md-8">
                                <label class="form-label">Profile Name</label>
                                <input type="text" class="form-control" name="profile_name" id="editDbaProfileProfileName" required>
                            </div>

                            <div class="col-12 col-md-3">
                                <label class="form-label">Type</label>
                                <select class="form-select" name="profile_type" id="editDbaProfileType">
                                    <option value="type3">Type 3</option>
                                    <option value="type4">Type 4</option>
                                    <option value="type5">Type 5</option>
                                </select>
                            </div>

                            <div class="col-12 col-md-3">
                                <label class="form-label">Bandwidth Unit</label>
                                <select class="form-select" name="bandwidth_unit" id="editDbaProfileBandwidthUnit">
                                    <option value="kbit">Kbit/s</option>
                                    <option value="mbit">Mbit/s</option>
                                </select>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Assure</label>
                                <input type="number" class="form-control" name="assure" id="editDbaProfileAssure" required>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Max</label>
                                <input type="number" class="form-control" name="max" id="editDbaProfileMax" required>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Priority</label>
                                <input type="number" class="form-control" name="priority" id="editDbaProfilePriority">
                            </div>

                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" id="editDbaProfileDescription" rows="3"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer nx-modal-footer">
                        <button type="button" class="btn btn-outline-danger me-auto" id="deleteDbaProfileBtn">
                            <i class="bi bi-trash"></i>
                            <span>Delete</span>
                        </button>
                        <button type="button" class="btn btn-outline-primary" id="previewDbaProfileCliBtn">
                            <i class="bi bi-terminal"></i>
                            <span>CLI Preview</span>
                        </button>
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i>
                            <span>Save Changes</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="dbaProfileViewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content nx-modal">
                <div class="modal-header nx-modal-header">
                    <div>
                        <h5 class="modal-title mb-1">DBA Profile Details</h5>
                        <div class="nx-modal-subtitle" id="viewDbaProfileSubtitle">Inspect the selected DBA profile.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body nx-modal-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6"><div class="nx-detail-item"><div class="nx-detail-label">Profile Name</div><div class="nx-detail-value" id="viewDbaProfileName">-</div></div></div>
                        <div class="col-12 col-md-6"><div class="nx-detail-item"><div class="nx-detail-label">Profile ID</div><div class="nx-detail-value" id="viewDbaProfileId">-</div></div></div>
                        <div class="col-12 col-md-4"><div class="nx-detail-item"><div class="nx-detail-label">Type</div><div class="nx-detail-value" id="viewDbaProfileType">-</div></div></div>
                        <div class="col-12 col-md-4"><div class="nx-detail-item"><div class="nx-detail-label">Assure</div><div class="nx-detail-value" id="viewDbaProfileAssure">-</div></div></div>
                        <div class="col-12 col-md-4"><div class="nx-detail-item"><div class="nx-detail-label">Max</div><div class="nx-detail-value" id="viewDbaProfileMax">-</div></div></div>
                        <div class="col-12 col-md-6"><div class="nx-detail-item"><div class="nx-detail-label">Priority</div><div class="nx-detail-value" id="viewDbaProfilePriority">-</div></div></div>
                        <div class="col-12"><div class="nx-detail-item"><div class="nx-detail-label">Description</div><div class="nx-detail-value" id="viewDbaProfileDescription">-</div></div></div>
                    </div>
                </div>

                <div class="modal-footer nx-modal-footer">
                    <button type="button" class="btn btn-outline-primary" id="openEditDbaProfileFromViewBtn">
                        <i class="bi bi-pencil"></i>
                        <span>Edit</span>
                    </button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="dbaProfileCliPreviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content nx-modal">
                <div class="modal-header nx-modal-header">
                    <div>
                        <h5 class="modal-title mb-1">DBA CLI Preview</h5>
                        <div class="nx-modal-subtitle" id="dbaProfileCliPreviewSubtitle">Generated Huawei CLI for the selected DBA profile.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body nx-modal-body">
                    <div class="nx-code-block-wrap">
                        <pre class="nx-code-block mb-0" id="dbaProfileCliPreviewContent">No preview loaded.</pre>
                    </div>
                </div>
                <div class="modal-footer nx-modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-outline-primary" id="copyDbaProfileCliPreviewBtn">
                        <i class="bi bi-clipboard"></i>
                        <span>Copy CLI</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- =========================================================
         WAN PROFILE MODALS
    ========================================================== -->

    <div class="modal fade" id="wanProfileCreateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content nx-modal">
                <div class="modal-header nx-modal-header">
                    <div>
                        <h5 class="modal-title mb-1">Create WAN Profile</h5>
                        <div class="nx-modal-subtitle">Define PPPoE or DHCP WAN behavior for the ONT.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <form id="createWanProfileForm">
                    <div class="modal-body nx-modal-body">
                        <div class="row g-3">
                            <input type="hidden" name="olt_id" id="createWanProfileOltId" value="<?= (int)$selectedOltId ?>">

                            <div class="col-12 col-md-4">
                                <label class="form-label">Profile ID</label>
                                <input type="number" class="form-control" name="profile_id" id="createWanProfileProfileId" required>
                            </div>
                            <div class="col-12 col-md-8">
                                <label class="form-label">Profile Name</label>
                                <input type="text" class="form-control" name="profile_name" id="createWanProfileProfileName" required>
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="form-label">Mode</label>
                                <select class="form-select" name="wan_mode" id="createWanProfileMode">
                                    <option value="pppoe" selected>PPPoE</option>
                                    <option value="dhcp">DHCP</option>
                                    <option value="bridge">Bridge</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="form-label">IP Mode</label>
                                <select class="form-select" name="ip_mode" id="createWanProfileIpMode">
                                    <option value="ipv4" selected>IPv4</option>
                                    <option value="ipv6">IPv6</option>
                                    <option value="dualstack">Dual Stack</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="form-label">NAT</label>
                                <select class="form-select" name="nat_enable" id="createWanProfileNatEnable">
                                    <option value="1" selected>Enabled</option>
                                    <option value="0">Disabled</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="form-label">VLAN Mode</label>
                                <select class="form-select" name="vlan_mode" id="createWanProfileVlanMode">
                                    <option value="transparent" selected>Transparent</option>
                                    <option value="tag">Tag</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="form-label">Service Type</label>
                                <input type="text" class="form-control" name="service_type" id="createWanProfileServiceType" value="INTERNET">
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="form-label">MTU</label>
                                <input type="number" class="form-control" name="mtu" id="createWanProfileMtu" value="1492">
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="form-label">Priority</label>
                                <input type="number" class="form-control" name="priority" id="createWanProfilePriority" value="0">
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="form-label">Bind LAN Ports</label>
                                <input type="text" class="form-control" name="bind_lan_ports" id="createWanProfileBindLanPorts" placeholder="1,2,3,4">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" id="createWanProfileDescription" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer nx-modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2-circle"></i>
                            <span>Create WAN Profile</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="wanProfileEditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content nx-modal">
                <div class="modal-header nx-modal-header">
                    <div>
                        <h5 class="modal-title mb-1">Edit WAN Profile</h5>
                        <div class="nx-modal-subtitle" id="editWanProfileSubtitle">Update WAN behavior and access method.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <form id="editWanProfileForm" data-id="">
                    <div class="modal-body nx-modal-body">
                        <div class="row g-3">
                            <input type="hidden" name="olt_id" id="editWanProfileOltId" value="<?= (int)$selectedOltId ?>">

                            <div class="col-12 col-md-4"><label class="form-label">Profile ID</label><input type="number" class="form-control" name="profile_id" id="editWanProfileProfileId" required></div>
                            <div class="col-12 col-md-8"><label class="form-label">Profile Name</label><input type="text" class="form-control" name="profile_name" id="editWanProfileProfileName" required></div>
                            <div class="col-12 col-md-3"><label class="form-label">Mode</label><select class="form-select" name="wan_mode" id="editWanProfileMode"><option value="pppoe">PPPoE</option><option value="dhcp">DHCP</option><option value="bridge">Bridge</option></select></div>
                            <div class="col-12 col-md-3"><label class="form-label">IP Mode</label><select class="form-select" name="ip_mode" id="editWanProfileIpMode"><option value="ipv4">IPv4</option><option value="ipv6">IPv6</option><option value="dualstack">Dual Stack</option></select></div>
                            <div class="col-12 col-md-3"><label class="form-label">NAT</label><select class="form-select" name="nat_enable" id="editWanProfileNatEnable"><option value="1">Enabled</option><option value="0">Disabled</option></select></div>
                            <div class="col-12 col-md-3"><label class="form-label">VLAN Mode</label><select class="form-select" name="vlan_mode" id="editWanProfileVlanMode"><option value="transparent">Transparent</option><option value="tag">Tag</option></select></div>
                            <div class="col-12 col-md-3"><label class="form-label">Service Type</label><input type="text" class="form-control" name="service_type" id="editWanProfileServiceType"></div>
                            <div class="col-12 col-md-3"><label class="form-label">MTU</label><input type="number" class="form-control" name="mtu" id="editWanProfileMtu"></div>
                            <div class="col-12 col-md-3"><label class="form-label">Priority</label><input type="number" class="form-control" name="priority" id="editWanProfilePriority"></div>
                            <div class="col-12 col-md-3"><label class="form-label">Bind LAN Ports</label><input type="text" class="form-control" name="bind_lan_ports" id="editWanProfileBindLanPorts"></div>
                            <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" id="editWanProfileDescription" rows="3"></textarea></div>
                        </div>
                    </div>
                    <div class="modal-footer nx-modal-footer">
                        <button type="button" class="btn btn-outline-danger me-auto" id="deleteWanProfileBtn"><i class="bi bi-trash"></i><span>Delete</span></button>
                        <button type="button" class="btn btn-outline-primary" id="previewWanProfileCliBtn"><i class="bi bi-terminal"></i><span>CLI Preview</span></button>
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i><span>Save Changes</span></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="wanProfileViewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content nx-modal">
                <div class="modal-header nx-modal-header">
                    <div>
                        <h5 class="modal-title mb-1">WAN Profile Details</h5>
                        <div class="nx-modal-subtitle" id="viewWanProfileSubtitle">Inspect the selected WAN profile.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body nx-modal-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6"><div class="nx-detail-item"><div class="nx-detail-label">Profile Name</div><div class="nx-detail-value" id="viewWanProfileName">-</div></div></div>
                        <div class="col-12 col-md-6"><div class="nx-detail-item"><div class="nx-detail-label">Profile ID</div><div class="nx-detail-value" id="viewWanProfileId">-</div></div></div>
                        <div class="col-12 col-md-4"><div class="nx-detail-item"><div class="nx-detail-label">Mode</div><div class="nx-detail-value" id="viewWanProfileMode">-</div></div></div>
                        <div class="col-12 col-md-4"><div class="nx-detail-item"><div class="nx-detail-label">IP Mode</div><div class="nx-detail-value" id="viewWanProfileIpMode">-</div></div></div>
                        <div class="col-12 col-md-4"><div class="nx-detail-item"><div class="nx-detail-label">NAT</div><div class="nx-detail-value" id="viewWanProfileNatEnable">-</div></div></div>
                        <div class="col-12 col-md-4"><div class="nx-detail-item"><div class="nx-detail-label">VLAN Mode</div><div class="nx-detail-value" id="viewWanProfileVlanMode">-</div></div></div>
                        <div class="col-12 col-md-4"><div class="nx-detail-item"><div class="nx-detail-label">MTU</div><div class="nx-detail-value" id="viewWanProfileMtu">-</div></div></div>
                        <div class="col-12"><div class="nx-detail-item"><div class="nx-detail-label">Description</div><div class="nx-detail-value" id="viewWanProfileDescription">-</div></div></div>
                    </div>
                </div>
                <div class="modal-footer nx-modal-footer">
                    <button type="button" class="btn btn-outline-primary" id="openEditWanProfileFromViewBtn"><i class="bi bi-pencil"></i><span>Edit</span></button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="wanProfileCliPreviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content nx-modal">
                <div class="modal-header nx-modal-header">
                    <div>
                        <h5 class="modal-title mb-1">WAN CLI Preview</h5>
                        <div class="nx-modal-subtitle" id="wanProfileCliPreviewSubtitle">Generated Huawei CLI for the selected WAN profile.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body nx-modal-body">
                    <div class="nx-code-block-wrap">
                        <pre class="nx-code-block mb-0" id="wanProfileCliPreviewContent">No preview loaded.</pre>
                    </div>
                </div>
                <div class="modal-footer nx-modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-outline-primary" id="copyWanProfileCliPreviewBtn"><i class="bi bi-clipboard"></i><span>Copy CLI</span></button>
                </div>
            </div>
        </div>
    </div>

    <!-- =========================================================
         TR069 PROFILE MODALS
    ========================================================== -->

    <div class="modal fade" id="tr069ProfileCreateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content nx-modal">
                <div class="modal-header nx-modal-header">
                    <div>
                        <h5 class="modal-title mb-1">Create TR069 Profile</h5>
                        <div class="nx-modal-subtitle">Define ACS server parameters for TR069 provisioning.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="createTr069ProfileForm">
                    <div class="modal-body nx-modal-body">
                        <div class="row g-3">
                            <input type="hidden" name="olt_id" id="createTr069ProfileOltId" value="<?= (int)$selectedOltId ?>">

                            <div class="col-12 col-md-4"><label class="form-label">Profile ID</label><input type="number" class="form-control" name="profile_id" id="createTr069ProfileProfileId" required></div>
                            <div class="col-12 col-md-8"><label class="form-label">Profile Name</label><input type="text" class="form-control" name="profile_name" id="createTr069ProfileProfileName" required></div>
                            <div class="col-12 col-md-6"><label class="form-label">ACS URL</label><input type="text" class="form-control" name="acs_url" id="createTr069ProfileAcsUrl" required></div>
                            <div class="col-12 col-md-6"><label class="form-label">ACS Username</label><input type="text" class="form-control" name="acs_username" id="createTr069ProfileAcsUsername"></div>
                            <div class="col-12 col-md-6"><label class="form-label">ACS Password</label><input type="text" class="form-control" name="acs_password" id="createTr069ProfileAcsPassword"></div>
                            <div class="col-12 col-md-4"><label class="form-label">Inform Enable</label><select class="form-select" name="inform_enable" id="createTr069ProfileInformEnable"><option value="1" selected>Enabled</option><option value="0">Disabled</option></select></div>
                            <div class="col-12 col-md-4"><label class="form-label">Inform Interval</label><input type="number" class="form-control" name="inform_interval" id="createTr069ProfileInformInterval" value="300"></div>
                            <div class="col-12 col-md-4"><label class="form-label">Connect Request Enable</label><select class="form-select" name="connection_request_enable" id="createTr069ProfileConnectionRequestEnable"><option value="1" selected>Enabled</option><option value="0">Disabled</option></select></div>
                            <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" id="createTr069ProfileDescription" rows="3"></textarea></div>
                        </div>
                    </div>
                    <div class="modal-footer nx-modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle"></i><span>Create TR069 Profile</span></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="tr069ProfileEditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content nx-modal">
                <div class="modal-header nx-modal-header">
                    <div>
                        <h5 class="modal-title mb-1">Edit TR069 Profile</h5>
                        <div class="nx-modal-subtitle" id="editTr069ProfileSubtitle">Update ACS configuration and inform behavior.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editTr069ProfileForm" data-id="">
                    <div class="modal-body nx-modal-body">
                        <div class="row g-3">
                            <input type="hidden" name="olt_id" id="editTr069ProfileOltId" value="<?= (int)$selectedOltId ?>">

                            <div class="col-12 col-md-4"><label class="form-label">Profile ID</label><input type="number" class="form-control" name="profile_id" id="editTr069ProfileProfileId" required></div>
                            <div class="col-12 col-md-8"><label class="form-label">Profile Name</label><input type="text" class="form-control" name="profile_name" id="editTr069ProfileProfileName" required></div>
                            <div class="col-12 col-md-6"><label class="form-label">ACS URL</label><input type="text" class="form-control" name="acs_url" id="editTr069ProfileAcsUrl" required></div>
                            <div class="col-12 col-md-6"><label class="form-label">ACS Username</label><input type="text" class="form-control" name="acs_username" id="editTr069ProfileAcsUsername"></div>
                            <div class="col-12 col-md-6"><label class="form-label">ACS Password</label><input type="text" class="form-control" name="acs_password" id="editTr069ProfileAcsPassword"></div>
                            <div class="col-12 col-md-4"><label class="form-label">Inform Enable</label><select class="form-select" name="inform_enable" id="editTr069ProfileInformEnable"><option value="1">Enabled</option><option value="0">Disabled</option></select></div>
                            <div class="col-12 col-md-4"><label class="form-label">Inform Interval</label><input type="number" class="form-control" name="inform_interval" id="editTr069ProfileInformInterval"></div>
                            <div class="col-12 col-md-4"><label class="form-label">Connect Request Enable</label><select class="form-select" name="connection_request_enable" id="editTr069ProfileConnectionRequestEnable"><option value="1">Enabled</option><option value="0">Disabled</option></select></div>
                            <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" id="editTr069ProfileDescription" rows="3"></textarea></div>
                        </div>
                    </div>
                    <div class="modal-footer nx-modal-footer">
                        <button type="button" class="btn btn-outline-danger me-auto" id="deleteTr069ProfileBtn"><i class="bi bi-trash"></i><span>Delete</span></button>
                        <button type="button" class="btn btn-outline-primary" id="previewTr069ProfileCliBtn"><i class="bi bi-terminal"></i><span>CLI Preview</span></button>
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i><span>Save Changes</span></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="tr069ProfileViewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content nx-modal">
                <div class="modal-header nx-modal-header">
                    <div>
                        <h5 class="modal-title mb-1">TR069 Profile Details</h5>
                        <div class="nx-modal-subtitle" id="viewTr069ProfileSubtitle">Inspect the selected TR069 profile.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body nx-modal-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6"><div class="nx-detail-item"><div class="nx-detail-label">Profile Name</div><div class="nx-detail-value" id="viewTr069ProfileName">-</div></div></div>
                        <div class="col-12 col-md-6"><div class="nx-detail-item"><div class="nx-detail-label">Profile ID</div><div class="nx-detail-value" id="viewTr069ProfileId">-</div></div></div>
                        <div class="col-12"><div class="nx-detail-item"><div class="nx-detail-label">ACS URL</div><div class="nx-detail-value" id="viewTr069ProfileAcsUrl">-</div></div></div>
                        <div class="col-12 col-md-6"><div class="nx-detail-item"><div class="nx-detail-label">ACS Username</div><div class="nx-detail-value" id="viewTr069ProfileAcsUsername">-</div></div></div>
                        <div class="col-12 col-md-4"><div class="nx-detail-item"><div class="nx-detail-label">Inform Enable</div><div class="nx-detail-value" id="viewTr069ProfileInformEnable">-</div></div></div>
                        <div class="col-12 col-md-4"><div class="nx-detail-item"><div class="nx-detail-label">Inform Interval</div><div class="nx-detail-value" id="viewTr069ProfileInformInterval">-</div></div></div>
                        <div class="col-12 col-md-4"><div class="nx-detail-item"><div class="nx-detail-label">Connect Request Enable</div><div class="nx-detail-value" id="viewTr069ProfileConnectionRequestEnable">-</div></div></div>
                        <div class="col-12"><div class="nx-detail-item"><div class="nx-detail-label">Description</div><div class="nx-detail-value" id="viewTr069ProfileDescription">-</div></div></div>
                    </div>
                </div>
                <div class="modal-footer nx-modal-footer">
                    <button type="button" class="btn btn-outline-primary" id="openEditTr069ProfileFromViewBtn"><i class="bi bi-pencil"></i><span>Edit</span></button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="tr069ProfileCliPreviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content nx-modal">
                <div class="modal-header nx-modal-header">
                    <div>
                        <h5 class="modal-title mb-1">TR069 CLI Preview</h5>
                        <div class="nx-modal-subtitle" id="tr069ProfileCliPreviewSubtitle">Generated Huawei CLI for the selected TR069 profile.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body nx-modal-body">
                    <div class="nx-code-block-wrap">
                        <pre class="nx-code-block mb-0" id="tr069ProfileCliPreviewContent">No preview loaded.</pre>
                    </div>
                </div>
                <div class="modal-footer nx-modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-outline-primary" id="copyTr069ProfileCliPreviewBtn"><i class="bi bi-clipboard"></i><span>Copy CLI</span></button>
                </div>
            </div>
        </div>
    </div>

    <!-- =========================================================
         SERVICE PROFILE MODALS
    ========================================================== -->

    <div class="modal fade" id="srvProfileCreateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content nx-modal">
                <div class="modal-header nx-modal-header">
                    <div>
                        <h5 class="modal-title mb-1">Create Service Profile</h5>
                        <div class="nx-modal-subtitle">Define ONT LAN service behavior and port mapping templates.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="createSrvProfileForm">
                    <div class="modal-body nx-modal-body">
                        <div class="row g-3">
                            <input type="hidden" name="olt_id" id="createSrvProfileOltId" value="<?= (int)$selectedOltId ?>">

                            <div class="col-12 col-md-4"><label class="form-label">Profile ID</label><input type="number" class="form-control" name="profile_id" id="createSrvProfileProfileId" required></div>
                            <div class="col-12 col-md-8"><label class="form-label">Profile Name</label><input type="text" class="form-control" name="profile_name" id="createSrvProfileProfileName" required></div>
                            <div class="col-12 col-md-3"><label class="form-label">ETH Ports</label><input type="number" class="form-control" name="eth_ports" id="createSrvProfileEthPorts" value="4"></div>
                            <div class="col-12 col-md-3"><label class="form-label">POTS Ports</label><input type="number" class="form-control" name="pots_ports" id="createSrvProfilePotsPorts" value="0"></div>
                            <div class="col-12 col-md-3"><label class="form-label">CATV</label><select class="form-select" name="catv_enable" id="createSrvProfileCatvEnable"><option value="0" selected>Disabled</option><option value="1">Enabled</option></select></div>
                            <div class="col-12 col-md-3"><label class="form-label">Wi-Fi</label><select class="form-select" name="wifi_enable" id="createSrvProfileWifiEnable"><option value="1" selected>Enabled</option><option value="0">Disabled</option></select></div>
                            <div class="col-12 col-md-6"><label class="form-label">Port VLAN Mode</label><input type="text" class="form-control" name="port_vlan_mode" id="createSrvProfilePortVlanMode" value="transparent"></div>
                            <div class="col-12 col-md-6"><label class="form-label">Native VLAN</label><input type="number" class="form-control" name="native_vlan" id="createSrvProfileNativeVlan" value="0"></div>
                            <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" id="createSrvProfileDescription" rows="3"></textarea></div>
                        </div>
                    </div>
                    <div class="modal-footer nx-modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle"></i><span>Create Service Profile</span></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="srvProfileEditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content nx-modal">
                <div class="modal-header nx-modal-header">
                    <div>
                        <h5 class="modal-title mb-1">Edit Service Profile</h5>
                        <div class="nx-modal-subtitle" id="editSrvProfileSubtitle">Update ONT LAN service mappings and template behavior.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editSrvProfileForm" data-id="">
                    <div class="modal-body nx-modal-body">
                        <div class="row g-3">
                            <input type="hidden" name="olt_id" id="editSrvProfileOltId" value="<?= (int)$selectedOltId ?>">

                            <div class="col-12 col-md-4"><label class="form-label">Profile ID</label><input type="number" class="form-control" name="profile_id" id="editSrvProfileProfileId" required></div>
                            <div class="col-12 col-md-8"><label class="form-label">Profile Name</label><input type="text" class="form-control" name="profile_name" id="editSrvProfileProfileName" required></div>
                            <div class="col-12 col-md-3"><label class="form-label">ETH Ports</label><input type="number" class="form-control" name="eth_ports" id="editSrvProfileEthPorts"></div>
                            <div class="col-12 col-md-3"><label class="form-label">POTS Ports</label><input type="number" class="form-control" name="pots_ports" id="editSrvProfilePotsPorts"></div>
                            <div class="col-12 col-md-3"><label class="form-label">CATV</label><select class="form-select" name="catv_enable" id="editSrvProfileCatvEnable"><option value="0">Disabled</option><option value="1">Enabled</option></select></div>
                            <div class="col-12 col-md-3"><label class="form-label">Wi-Fi</label><select class="form-select" name="wifi_enable" id="editSrvProfileWifiEnable"><option value="1">Enabled</option><option value="0">Disabled</option></select></div>
                            <div class="col-12 col-md-6"><label class="form-label">Port VLAN Mode</label><input type="text" class="form-control" name="port_vlan_mode" id="editSrvProfilePortVlanMode"></div>
                            <div class="col-12 col-md-6"><label class="form-label">Native VLAN</label><input type="number" class="form-control" name="native_vlan" id="editSrvProfileNativeVlan"></div>
                            <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" id="editSrvProfileDescription" rows="3"></textarea></div>
                        </div>
                    </div>
                    <div class="modal-footer nx-modal-footer">
                        <button type="button" class="btn btn-outline-danger me-auto" id="deleteSrvProfileBtn"><i class="bi bi-trash"></i><span>Delete</span></button>
                        <button type="button" class="btn btn-outline-primary" id="previewSrvProfileCliBtn"><i class="bi bi-terminal"></i><span>CLI Preview</span></button>
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i><span>Save Changes</span></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="srvProfileViewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content nx-modal">
                <div class="modal-header nx-modal-header">
                    <div>
                        <h5 class="modal-title mb-1">Service Profile Details</h5>
                        <div class="nx-modal-subtitle" id="viewSrvProfileSubtitle">Inspect the selected service profile.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body nx-modal-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6"><div class="nx-detail-item"><div class="nx-detail-label">Profile Name</div><div class="nx-detail-value" id="viewSrvProfileName">-</div></div></div>
                        <div class="col-12 col-md-6"><div class="nx-detail-item"><div class="nx-detail-label">Profile ID</div><div class="nx-detail-value" id="viewSrvProfileId">-</div></div></div>
                        <div class="col-12 col-md-4"><div class="nx-detail-item"><div class="nx-detail-label">ETH Ports</div><div class="nx-detail-value" id="viewSrvProfileEthPorts">-</div></div></div>
                        <div class="col-12 col-md-4"><div class="nx-detail-item"><div class="nx-detail-label">POTS Ports</div><div class="nx-detail-value" id="viewSrvProfilePotsPorts">-</div></div></div>
                        <div class="col-12 col-md-6"><div class="nx-detail-item"><div class="nx-detail-label">CATV</div><div class="nx-detail-value" id="viewSrvProfileCatvEnable">-</div></div></div>
                        <div class="col-12 col-md-6"><div class="nx-detail-item"><div class="nx-detail-label">Wi-Fi</div><div class="nx-detail-value" id="viewSrvProfileWifiEnable">-</div></div></div>
                        <div class="col-12"><div class="nx-detail-item"><div class="nx-detail-label">Description</div><div class="nx-detail-value" id="viewSrvProfileDescription">-</div></div></div>
                    </div>
                </div>
                <div class="modal-footer nx-modal-footer">
                    <button type="button" class="btn btn-outline-primary" id="openEditSrvProfileFromViewBtn"><i class="bi bi-pencil"></i><span>Edit</span></button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="srvProfileCliPreviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content nx-modal">
                <div class="modal-header nx-modal-header">
                    <div>
                        <h5 class="modal-title mb-1">Service CLI Preview</h5>
                        <div class="nx-modal-subtitle" id="srvProfileCliPreviewSubtitle">Generated Huawei CLI for the selected service profile.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body nx-modal-body">
                    <div class="nx-code-block-wrap">
                        <pre class="nx-code-block mb-0" id="srvProfileCliPreviewContent">No preview loaded.</pre>
                    </div>
                </div>
                <div class="modal-footer nx-modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-outline-primary" id="copySrvProfileCliPreviewBtn"><i class="bi bi-clipboard"></i><span>Copy CLI</span></button>
                </div>
            </div>
        </div>
    </div>

    <script src="/module-assets/OltManagement/js/OltManagement.js"></script>
</div>
