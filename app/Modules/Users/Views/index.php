<?php $p=BASE_PATH.'/public/build-next/.vite/manifest.json';$m=is_file($p)?(json_decode((string)file_get_contents($p),true)?:[]):[];$e=$m['src/main.ts']??[];$v=is_file($p)?(string)filemtime($p):(string)time();foreach(($e['css']??[])as$c):?><link rel="stylesheet" href="/build-next/<?=htmlspecialchars(ltrim((string)$c,'/'),ENT_QUOTES,'UTF-8')?>?v=<?=htmlspecialchars($v,ENT_QUOTES,'UTF-8')?>"><?php endforeach;?><div class="container-fluid nx-page" data-nx-next-root="users"></div><?php if(!empty($e['file'])):?><script type="module" src="/build-next/<?=htmlspecialchars(ltrim((string)$e['file'],'/'),ENT_QUOTES,'UTF-8')?>?v=<?=htmlspecialchars($v,ENT_QUOTES,'UTF-8')?>"></script><?php else:?><div class="alert alert-warning">The users interface is not built.</div><?php endif;return;?>
<div id="usersPage" class="container-fluid nx-page">

    <div class="card border-0 shadow-sm mb-3 nx-page-header-card">
        <div class="card-body nx-page-header">
            <div class="nx-page-header-left">
                <div class="users-hero-badge"><i class="bi bi-shield-lock"></i> Identity & access</div>
                <h5 class="nx-page-title">Users & Access</h5>
                <p class="nx-page-subtitle mb-0">
                    Manage internal users, roles, account status, and individual RBAC permissions.
                </p>
            </div>

            <div class="nx-page-actions" id="usersHeaderActions">
                <button class="btn btn-primary nx-header-btn" type="button" id="openAddUserBtn">
                    <i class="bi bi-plus-lg"></i>
                    <span>Add User</span>
                </button>

                <button class="btn btn-light border nx-header-btn" type="button" id="refreshUsersBtn">
                    <i class="bi bi-arrow-clockwise"></i>
                    <span>Refresh</span>
                </button>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3 nx-toolbar-card">
        <div class="card-body">
            <div class="users-toolbar-grid">
                <div class="users-toolbar-search">
                    <div class="users-search-wrap">
                        <i class="bi bi-search users-search-icon"></i>
                        <input
                                type="text"
                                id="usersSearchInput"
                                class="form-control users-toolbar-control users-search-control"
                                placeholder="Search username / name / email / role / status"
                        >
                    </div>
                </div>

                <div class="users-toolbar-meta">
                    <span class="users-toolbar-note" id="usersLoadedAt">Loading users...</span>
                </div>
            </div>
        </div>
    </div>

    <div id="usersContentArea"></div>

    <!-- USER FORM MODAL -->
    <div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form class="modal-content nx-modal-content" id="userForm">
                <div class="modal-header nx-modal-header">
                    <div>
                        <h5 class="modal-title fw-bold" id="userModalTitle">Add User</h5>
                        <div class="small text-muted" id="userModalSubtitle">Create system user</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body nx-modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" id="userUsernameInput" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" id="userFullNameInput" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="userEmailInput" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Role</label>
                            <select name="role" id="userRoleInput" class="form-select">
                                <option value="SUPERADMIN">SUPERADMIN</option>
                                <option value="NOC">NOC</option>
                                <option value="SUPPORT">SUPPORT</option>
                                <option value="BILLING">BILLING</option>
                                <option value="TECHNICIAN">TECHNICIAN</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" id="userStatusInput" class="form-select">
                                <option value="ACTIVE">ACTIVE</option>
                                <option value="DISABLED">DISABLED</option>
                            </select>
                        </div>

                        <div class="col-md-6" id="userPasswordWrap">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" id="userPasswordInput" class="form-control" autocomplete="new-password">
                            <div class="form-text">Minimum 8 characters.</div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer nx-modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Save User
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="userPermissionsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable users-access-dialog">
            <form class="modal-content nx-modal-content" id="userPermissionsForm">
                <div class="modal-header nx-modal-header">
                    <div>
                        <h5 class="modal-title fw-bold">User Access</h5>
                        <div class="small text-muted" id="userPermissionsSubtitle">Role defaults and individual overrides</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body nx-modal-body">
                    <div class="alert alert-info py-2 small">
                        <strong>Inherit</strong> follows the selected role. <strong>Allow</strong> grants access only to this user.
                        <strong>Deny</strong> removes access only from this user.
                    </div>
                    <div id="userPermissionsContent"></div>
                </div>
                <div class="modal-footer nx-modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveUserPermissionsBtn">
                        <i class="bi bi-shield-check"></i> Save Access
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
<style>
#usersPage { --users-accent:#2563eb; --users-border:#e2e8f0; --users-soft:#f8fafc; }
#usersPage .nx-page-header-card { background:linear-gradient(120deg,#fff 0%,#f8fbff 72%,#eef5ff 100%)!important; border:1px solid #dbe6f3!important; }
#usersPage .nx-page-header { min-height:138px; }
#usersPage .nx-page-title { font-size:24px; letter-spacing:-.035em; }
#usersPage .users-hero-badge { margin-bottom:10px; border:1px solid #bfdbfe; background:#eff6ff; }
#usersPage .users-summary-card { min-height:92px; display:flex; align-items:center; gap:14px; padding:16px 18px; border:1px solid var(--users-border); box-shadow:0 4px 14px rgba(15,23,42,.035); }
#usersPage .users-summary-card::before { width:3px; }
#usersPage .users-summary-icon { width:42px; height:42px; display:grid; place-items:center; border-radius:6px; font-size:18px; background:#eff6ff; color:#2563eb; }
#usersPage .users-summary-success .users-summary-icon { background:#ecfdf5; color:#16a34a; }
#usersPage .users-summary-danger .users-summary-icon { background:#fef2f2; color:#dc2626; }
#usersPage .users-summary-info .users-summary-icon { background:#ecfeff; color:#0891b2; }
#usersPage .users-summary-value { font-size:24px; line-height:1; margin-bottom:6px; }
#usersPage .users-summary-label { margin:0; font-size:11px; text-transform:uppercase; letter-spacing:.055em; }
#usersPage .nx-toolbar-card { border-color:var(--users-border)!important; }
#usersPage .users-directory-card { border:1px solid var(--users-border)!important; }
#usersPage .users-directory-header { min-height:68px; padding:14px 18px; display:flex; align-items:center; justify-content:space-between; gap:16px; border-bottom:1px solid var(--users-border); background:#fff; }
#usersPage .users-directory-kicker { color:#64748b; font-size:10px; font-weight:800; letter-spacing:.09em; text-transform:uppercase; }
#usersPage .users-directory-title { margin:3px 0 0; color:#0f172a; font-size:16px; font-weight:800; }
#usersPage .users-directory-count { padding:6px 10px; border-radius:4px; background:#f1f5f9; color:#64748b; font-size:12px; }
#usersPage .users-main-cell .fw-semibold { color:#0f172a; font-size:13px; }
#usersPage .users-avatar { border-radius:7px; border:1px solid #dbeafe; }
#usersPage .js-user-permissions { width:84px!important; min-width:84px!important; padding-inline:9px!important; display:inline-flex; align-items:center; justify-content:center; gap:6px; font-weight:700; white-space:nowrap; }
#usersPage .table th:nth-child(5), #usersPage .table td:nth-child(5) { width:104px; white-space:nowrap; }
#usersPage .table tbody tr { transition:background-color .15s ease; }
#usersPage .table tbody tr:hover td { background:#f8fbff!important; }
#usersPage .users-access-dialog { max-width:min(1180px,calc(100vw - 32px)); }
#usersPage .users-access-workspace { display:grid; grid-template-columns:220px minmax(0,1fr); min-height:480px; margin:-4px; border:1px solid var(--users-border); background:#fff; }
#usersPage .users-access-nav { padding:14px 10px; border-right:1px solid var(--users-border); background:#f8fafc; overflow-y:auto; max-height:58vh; }
#usersPage .users-access-nav-label { padding:4px 10px 10px; color:#94a3b8; font-size:10px; font-weight:800; letter-spacing:.1em; text-transform:uppercase; }
#usersPage .users-access-nav-item { width:100%; min-height:38px; padding:8px 10px; display:flex; align-items:center; justify-content:space-between; gap:8px; border:0; border-radius:4px; background:transparent; color:#475569; font-size:12px; font-weight:700; text-align:left; text-transform:capitalize; }
#usersPage .users-access-nav-item small { min-width:22px; padding:2px 5px; border-radius:3px; background:#e2e8f0; color:#64748b; text-align:center; }
#usersPage .users-access-nav-item:hover { background:#eef2f7; color:#0f172a; }
#usersPage .users-access-nav-item.is-active { background:#2563eb; color:#fff; box-shadow:0 4px 10px rgba(37,99,235,.18); }
#usersPage .users-access-nav-item.is-active small { background:rgba(255,255,255,.18); color:#fff; }
#usersPage .users-access-panels { min-width:0; padding:18px; background:#fff; }
#usersPage .users-permission-group[hidden] { display:none!important; }
#usersPage .users-permission-heading { min-height:50px; display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; }
#usersPage .users-permission-heading h6 { margin:3px 0 0; color:#0f172a; font-size:17px; font-weight:800; text-transform:capitalize; }
#usersPage .users-permission-heading>span { padding:5px 9px; border-radius:4px; background:#eff6ff; color:#2563eb; font-size:11px; font-weight:800; }
#usersPage .users-permission-group .table { box-shadow:none!important; border:1px solid var(--users-border)!important; }
#usersPage .users-permission-group .table { table-layout:fixed; width:100%!important; min-width:0!important; }
#usersPage .users-permission-group .table th:nth-child(1) { width:29%; }
#usersPage .users-permission-group .table th:nth-child(2), #usersPage .users-permission-group .table th:nth-child(3), #usersPage .users-permission-group .table th:nth-child(4) { width:13%; text-align:center; }
#usersPage .users-permission-group .table th:nth-child(5) { width:32%; }
#usersPage .users-permission-group .table th { padding:11px 12px; font-size:10px; }
#usersPage .users-permission-group .table td { padding:12px 10px; overflow-wrap:anywhere; }
#usersPage .users-permission-group input[type=radio] { width:17px; height:17px; cursor:pointer; }
html[data-theme="dark"] #usersPage .nx-page-header-card { background:linear-gradient(120deg,#252728,#20252d)!important; border-color:#34383d!important; }
html[data-theme="dark"] #usersPage .users-summary-icon { background:#30343a; }
html[data-theme="dark"] #usersPage .users-directory-header, html[data-theme="dark"] #usersPage .users-access-workspace, html[data-theme="dark"] #usersPage .users-access-panels { background:var(--card)!important; border-color:var(--border)!important; }
html[data-theme="dark"] #usersPage .users-directory-title, html[data-theme="dark"] #usersPage .users-main-cell .fw-semibold, html[data-theme="dark"] #usersPage .users-permission-heading h6 { color:var(--foreground)!important; }
html[data-theme="dark"] #usersPage .users-access-nav { background:var(--muted)!important; border-color:var(--border)!important; }
html[data-theme="dark"] #usersPage .users-access-nav-item { color:var(--muted-foreground); }
html[data-theme="dark"] #usersPage .users-access-nav-item:hover { background:var(--accent); color:var(--foreground); }
@media(max-width:767px){#usersPage .users-access-workspace{grid-template-columns:1fr}.users-access-nav{display:flex!important;gap:6px;overflow-x:auto;max-height:none!important;border-right:0!important;border-bottom:1px solid var(--users-border)}#usersPage .users-access-nav-label{display:none}#usersPage .users-access-nav-item{width:auto;flex:0 0 auto}#usersPage .users-access-panels{padding:12px}}
</style>
<script src="/module-assets/Users/js/Users.js?v=4"></script>
