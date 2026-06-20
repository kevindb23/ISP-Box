<div id="usersPage" class="container-fluid nx-page">

    <div class="card border-0 shadow-sm mb-3 nx-page-header-card">
        <div class="card-body nx-page-header">
            <div class="nx-page-header-left">
                <div class="users-hero-badge">
                    <i class="bi bi-person-gear"></i>
                    System Access
                </div>
                <h5 class="nx-page-title">Users</h5>
                <p class="nx-page-subtitle mb-0">
                    Manage internal system users, roles, account status, and password resets.
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

</div>

<link rel="stylesheet" href="/module-assets/Users/css/Users.css">
<script src="/module-assets/Users/js/Users.js"></script>