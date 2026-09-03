document.addEventListener('DOMContentLoaded', () => {
    const app = document.getElementById('usersPage');
    if (!app || !window.NX) return;

    const {
        api,
        ui,
        dom,
        util,
        render,
        actions,
        forms,
        modal,
        page,
        datatable
    } = window.NX;

    const { $, html, text } = dom;
    const { escape, upper, safeArray, formatDateTime } = util;

    let usersTable = null;

    const el = {
        content: $('#usersContentArea'),
        search: $('#usersSearchInput'),
        loadedAt: $('#usersLoadedAt'),
        addBtn: $('#openAddUserBtn'),
        refreshBtn: $('#refreshUsersBtn'),

        modal: $('#userModal'),
        form: $('#userForm'),
        title: $('#userModalTitle'),
        subtitle: $('#userModalSubtitle'),

        username: $('#userUsernameInput'),
        fullName: $('#userFullNameInput'),
        email: $('#userEmailInput'),
        role: $('#userRoleInput'),
        status: $('#userStatusInput'),
        password: $('#userPasswordInput'),
        passwordWrap: $('#userPasswordWrap'),
        permissionsModal: $('#userPermissionsModal'),
        permissionsForm: $('#userPermissionsForm'),
        permissionsSubtitle: $('#userPermissionsSubtitle'),
        permissionsContent: $('#userPermissionsContent')
    };

    const appPage = page.create({
        state: {
            users: [],
            loading: true,
            search: ''
        },

        init(ctx) {
            bindStaticEvents(ctx);
        },

        async load(ctx) {
            await loadUsers(ctx);
        },

        render(ctx) {
            renderPage(ctx);
        }
    });

    function bindStaticEvents(ctx) {
        el.addBtn?.addEventListener('click', () => openCreateModal(ctx));
        el.refreshBtn?.addEventListener('click', () => refreshUsers(ctx));

        el.search?.addEventListener('input', util.debounce((e) => {
            const value = e.target.value || '';
            ctx.patch({ search: value });
        }, 180));

        el.form?.addEventListener('submit', (e) => submitUserForm(ctx, e));
        el.permissionsForm?.addEventListener('submit', (e) => submitPermissions(ctx, e));
        el.permissionsContent?.addEventListener('change', (e) => {
            const input = e.target.closest('input[data-permission]');
            if (!input) return;
            const row = input.closest('tr');
            const roleAllowed = row?.dataset.roleAllowed === '1';
            const allowed = input.value === 'ALLOW' || (input.value === 'INHERIT' && roleAllowed);
            const badge = row?.querySelector('.js-effective-access');
            const source = row?.querySelector('.js-effective-source');
            if (badge) {
                badge.className = `badge js-effective-access ${allowed ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'}`;
                badge.textContent = allowed ? 'ALLOWED' : 'DENIED';
            }
            if (source) source.textContent = input.value === 'INHERIT' ? 'Role default' : 'User override';
        });
    }

    function extractRows(response) {
        if (Array.isArray(response)) return response;
        if (Array.isArray(response?.data)) return response.data;
        return [];
    }

    async function loadUsers(ctx) {
        ctx.patch({ loading: true });

        const response = await api.get('/api/v1/users');

        ctx.patch({
            users: extractRows(response),
            loading: false
        });
    }

    async function refreshUsers(ctx) {
        try {
            await loadUsers(ctx);
            ui.toast('success', 'Users refreshed.');
        } catch (err) {
            ui.toast('error', err.message || 'Failed to refresh users.');
        }
    }

    function renderPage(ctx) {
        text(el.loadedAt, ctx.state.loading ? 'Loading users...' : `Last loaded: ${new Date().toLocaleTimeString()}`);

        if (ctx.state.loading) {
            html(el.content, render.skeletonTable(6, 8));
            return;
        }

        const rows = safeArray(ctx.state.users);

        if (!rows.length) {
            html(el.content, render.emptyState({
                title: 'No system users found',
                text: 'Create your first internal system user.',
                buttonLabel: 'Add User',
                buttonId: 'usersEmptyAddBtn',
                iconClass: 'bi bi-person-gear'
            }));

            $('#usersEmptyAddBtn')?.addEventListener('click', () => openCreateModal(ctx));
            return;
        }

        html(el.content, `
            ${renderSummaryCards(rows)}
            <div class="card border-0 shadow-sm nx-content-card users-directory-card">
                <div class="users-directory-header">
                    <div>
                        <div class="users-directory-kicker">Identity directory</div>
                        <h6 class="users-directory-title">System users</h6>
                    </div>
                    <div class="users-directory-count"><strong>${rows.length}</strong> accounts</div>
                </div>
                <div class="card-body">
                    <div id="usersTable"></div>
                </div>
            </div>
        `);

        usersTable?.destroy();
        usersTable = datatable.create({
            el: '#usersTable',
            rows,
            search: false,
            paginate: true,
            pager: { currentPage: 1, rowsPerPage: 20 },
            sort: { key: 'id', dir: 'desc' },
            columns: [
                {
                    key: 'username',
                    label: 'Username',
                    render: (_, row) => `
                        <div class="users-main-cell">
                            <div class="users-avatar">${escape(getInitial(row))}</div>
                            <div>
                                <div class="fw-semibold">${escape(row.username || '-')}</div>
                                <div class="small text-muted">${escape(row.full_name || '-')}</div>
                            </div>
                        </div>
                    `
                },
                {
                    key: 'email',
                    label: 'Email',
                    render: (v) => escape(v || '-')
                },
                {
                    key: 'role',
                    label: 'Role',
                    render: (v) => roleBadge(v)
                },
                {
                    key: 'status',
                    label: 'Status',
                    render: (v) => statusBadge(v)
                },
                {
                    key: '__access',
                    label: 'Access',
                    render: (_, row) => `
                        <button type="button" class="btn btn-sm btn-outline-primary js-user-permissions" data-id="${parseInt(row.id, 10)}" title="Manage Access" aria-label="Manage access for ${escape(row.username || 'user')}">
                            <i class="bi bi-shield-lock"></i>
                            <span>Access</span>
                        </button>
                    `
                },
                {
                    key: 'last_login',
                    label: 'Last Login',
                    render: (v) => escape(formatDateTime(v || '-'))
                },
                {
                    key: 'created_at',
                    label: 'Created',
                    render: (v) => escape(formatDateTime(v || '-'))
                },
                {
                    key: '__actions',
                    label: 'Actions',
                    render: (_, row) => {
                        const currentUsername = getCurrentUsername();
                        const isCurrentUser = String(row.username || '').toLowerCase() === currentUsername.toLowerCase();

                        return `
                            <div class="d-flex gap-1 flex-wrap justify-content-end">
                                <button type="button" class="btn btn-sm btn-outline-primary nx-icon-btn js-edit-user" data-id="${parseInt(row.id, 10)}" title="Edit User">
                                    <i class="bi bi-pencil"></i>
                                </button>

                                <button type="button" class="btn btn-sm btn-outline-warning nx-icon-btn js-reset-password" data-id="${parseInt(row.id, 10)}" title="Reset Password">
                                    <i class="bi bi-key"></i>
                                </button>

                                ${
                            isCurrentUser
                                ? `
                                            <button type="button" class="btn btn-sm btn-outline-secondary nx-icon-btn" disabled title="You cannot disable your own account">
                                                <i class="bi bi-shield-lock"></i>
                                            </button>
                                        `
                                : `
                                            <button type="button" class="btn btn-sm btn-outline-danger nx-icon-btn js-disable-user" data-id="${parseInt(row.id, 10)}" title="Disable User">
                                                <i class="bi bi-person-x"></i>
                                            </button>
                                        `
                        }
                            </div>
                        `;
                    }
                }
            ]
        });

        usersTable.setSearch(ctx.state.search || '');
        bindRowActions(ctx);
    }

    function renderSummaryCards(rows) {
        const total = rows.length;
        const active = rows.filter(r => upper(r.status) === 'ACTIVE').length;
        const disabled = rows.filter(r => upper(r.status) === 'DISABLED').length;
        const admins = rows.filter(r => upper(r.role) === 'SUPERADMIN').length;

        return `
            <div class="row g-3 mb-3">
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="users-summary-card users-summary-primary">
                        <div class="users-summary-icon"><i class="bi bi-people"></i></div>
                        <div><div class="users-summary-value">${total}</div><div class="users-summary-label">Total users</div></div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="users-summary-card users-summary-success">
                        <div class="users-summary-icon"><i class="bi bi-person-check"></i></div>
                        <div><div class="users-summary-value">${active}</div><div class="users-summary-label">Active accounts</div></div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="users-summary-card users-summary-danger">
                        <div class="users-summary-icon"><i class="bi bi-person-slash"></i></div>
                        <div><div class="users-summary-value">${disabled}</div><div class="users-summary-label">Disabled</div></div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="users-summary-card users-summary-info">
                        <div class="users-summary-icon"><i class="bi bi-shield-check"></i></div>
                        <div><div class="users-summary-value">${admins}</div><div class="users-summary-label">Superadmins</div></div>
                    </div>
                </div>
            </div>
        `;
    }

    function bindRowActions(ctx) {
        document.querySelectorAll('.js-edit-user').forEach(btn => {
            btn.addEventListener('click', () => openEditModal(ctx, parseInt(btn.dataset.id, 10)));
        });

        document.querySelectorAll('.js-reset-password').forEach(btn => {
            btn.addEventListener('click', () => resetPassword(ctx, parseInt(btn.dataset.id, 10)));
        });

        document.querySelectorAll('.js-user-permissions').forEach(btn => {
            btn.addEventListener('click', () => openPermissions(ctx, parseInt(btn.dataset.id, 10)));
        });

        document.querySelectorAll('.js-disable-user').forEach(btn => {
            btn.addEventListener('click', () => disableUser(ctx, parseInt(btn.dataset.id, 10)));
        });
    }

    function getCurrentUsername() {
        return document.querySelector('.sidebar-footer .user-name')?.textContent?.trim()
            || document.querySelector('.user-name')?.textContent?.trim()
            || '';
    }

    function getInitial(row) {
        const source = row.full_name || row.username || 'U';
        return String(source).trim().substring(0, 1).toUpperCase();
    }

    function roleBadge(role) {
        const value = upper(role || 'SUPPORT');
        return `<span class="badge rounded-pill bg-primary-subtle text-primary">${escape(value)}</span>`;
    }

    function statusBadge(status) {
        const value = upper(status || 'ACTIVE');

        if (value === 'ACTIVE') {
            return '<span class="badge rounded-pill bg-success-subtle text-success">ACTIVE</span>';
        }

        return '<span class="badge rounded-pill bg-danger-subtle text-danger">DISABLED</span>';
    }

    function findUser(ctx, id) {
        return safeArray(ctx.state.users).find(u => parseInt(u.id, 10) === parseInt(id, 10)) || null;
    }

    function resetForm() {
        forms.reset(el.form);

        el.form.dataset.mode = 'create';
        el.form.dataset.id = '';

        text(el.title, 'Add User');
        text(el.subtitle, 'Create system user');

        el.passwordWrap.classList.remove('d-none');
        el.password.required = true;
        el.role.value = 'SUPPORT';
        el.status.value = 'ACTIVE';
    }

    function openCreateModal(ctx) {
        void ctx;
        resetForm();
        modal.open(el.modal);
    }

    function openEditModal(ctx, id) {
        const row = findUser(ctx, id);
        if (!row) return ui.toast('error', 'User not found.');

        resetForm();

        el.form.dataset.mode = 'edit';
        el.form.dataset.id = String(id);

        text(el.title, 'Edit User');
        text(el.subtitle, row.username || '-');

        el.passwordWrap.classList.add('d-none');
        el.password.required = false;
        el.password.value = '';

        forms.fill([
            [el.username, row.username || ''],
            [el.fullName, row.full_name || ''],
            [el.email, row.email || ''],
            [el.role, row.role || 'SUPPORT'],
            [el.status, row.status || 'ACTIVE']
        ]);

        modal.open(el.modal);
    }

    async function submitUserForm(ctx, e) {
        e.preventDefault();

        const mode = el.form.dataset.mode || 'create';
        const id = el.form.dataset.id || '';
        const fd = new FormData(el.form);

        const url = mode === 'edit'
            ? `/api/v1/users/update/${id}`
            : '/api/v1/users/store';

        const result = await actions.run({
            task: () => api.form(url, fd)
        });

        if (!result?.ok) return;

        modal.close(el.modal);
        await loadUsers(ctx);
        ui.toast('success', result.result.message || 'User saved.');
    }

    async function resetPassword(ctx, id) {
        const row = findUser(ctx, id);
        if (!row) return ui.toast('error', 'User not found.');

        const result = await ui.swal({
            title: `Reset Password`,
            html: `
                <div class="text-start">
                    <div class="mb-2 small text-muted">User: <strong>${escape(row.username || '-')}</strong></div>
                    <input type="password" id="resetPasswordInput" class="form-control" placeholder="New password">
                    <div class="form-text">Minimum 8 characters.</div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Reset Password',
            preConfirm: async () => {
                const password = document.getElementById('resetPasswordInput')?.value || '';

                if (password.length < 8) {
                    Swal.showValidationMessage('Password must be at least 8 characters.');
                    return false;
                }

                try {
                    return await api.urlEncoded(`/api/v1/users/reset-password/${id}`, { password });
                } catch (err) {
                    Swal.showValidationMessage(err.message || 'Failed to reset password.');
                    return false;
                }
            }
        });

        if (!result.isConfirmed) return;

        ui.toast('success', result.value?.message || 'Password reset successfully.');
    }

    async function disableUser(ctx, id) {
        const row = findUser(ctx, id);
        if (!row) return ui.toast('error', 'User not found.');

        const currentUsername = getCurrentUsername();
        const isCurrentUser = String(row.username || '').toLowerCase() === currentUsername.toLowerCase();

        if (isCurrentUser) {
            ui.toast('error', 'You cannot disable your own account.');
            return;
        }

        const fd = forms.data({ id });

        const result = await actions.run({
            confirm: {
                title: 'Disable user?',
                text: `Disable ${row.username || 'this user'}?`,
                confirmButtonText: 'Disable'
            },
            task: () => api.form('/api/v1/users/delete', fd)
        });

        if (!result?.ok) return;

        await loadUsers(ctx);
        ui.toast('success', result.result.message || 'User disabled.');
    }

    async function openPermissions(ctx, id) {
        const row = findUser(ctx, id);
        if (!row) return ui.toast('error', 'User not found.');
        el.permissionsForm.dataset.id = String(id);
        text(el.permissionsSubtitle, `${row.username || '-'} · ${upper(row.role || '-')}`);
        html(el.permissionsContent, render.skeletonTable(5, 5));
        modal.open(el.permissionsModal);
        try {
            const response = await api.get(`/api/v1/users/${id}/permissions`);
            const payload = response?.data || response || {};
            renderPermissions(payload.permissions || [], upper(row.role || '') === 'SUPERADMIN');
        } catch (err) {
            html(el.permissionsContent, `<div class="alert alert-danger">${escape(err.message || 'Failed to load permissions.')}</div>`);
        }
    }

    function renderPermissions(rows, protectedRole) {
        if (protectedRole) {
            html(el.permissionsContent, '<div class="alert alert-warning mb-0"><strong>Protected role:</strong> Superadmin always has full access and cannot receive per-user overrides.</div>');
            el.permissionsForm.querySelector('[type="submit"]').disabled = true;
            return;
        }
        el.permissionsForm.querySelector('[type="submit"]').disabled = false;
        const groups = safeArray(rows).reduce((all, permission) => {
            const key = permission.module_key || 'other';
            (all[key] ||= []).push(permission);
            return all;
        }, {});
        const modules = Object.keys(groups);
        html(el.permissionsContent, `
            <div class="users-access-workspace">
                <aside class="users-access-nav" aria-label="Permission modules">
                    <div class="users-access-nav-label">Modules</div>
                    ${modules.map((moduleKey, index) => `<button type="button" class="users-access-nav-item ${index === 0 ? 'is-active' : ''}" data-access-module="${escape(moduleKey)}">
                        <span>${escape(moduleKey.replaceAll('-', ' '))}</span><small>${groups[moduleKey].length}</small>
                    </button>`).join('')}
                </aside>
                <div class="users-access-panels">
                ${Object.entries(groups).map(([moduleKey, permissions], index) => `
            <section class="users-permission-group ${index === 0 ? 'is-active' : ''}" data-access-panel="${escape(moduleKey)}" ${index === 0 ? '' : 'hidden'}>
                <div class="users-permission-heading">
                    <div><div class="users-directory-kicker">Module permissions</div><h6>${escape(moduleKey.replaceAll('-', ' '))}</h6></div>
                    <span>${permissions.length} actions</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Action</th><th class="text-center">Inherit</th><th class="text-center">Allow</th><th class="text-center">Deny</th><th>Effective Access</th></tr></thead>
                        <tbody>${permissions.map(permission => {
                            const key = String(permission.permission_key || '');
                            const selected = upper(permission.override_effect || 'INHERIT');
                            const effective = Boolean(permission.effective_allowed);
                            return `<tr data-role-allowed="${permission.role_allowed ? '1' : '0'}">
                                <td><div class="fw-semibold">${escape(String(permission.action_key || '').replaceAll('-', ' '))}</div>${permission.is_sensitive ? '<small class="text-warning"><i class="bi bi-exclamation-triangle"></i> Sensitive action</small>' : ''}</td>
                                ${['INHERIT','ALLOW','DENY'].map(effect => `<td class="text-center"><input class="form-check-input" type="radio" name="permission_${escape(key)}" data-permission="${escape(key)}" value="${effect}" ${selected === effect ? 'checked' : ''} aria-label="${effect} ${escape(key)}"></td>`).join('')}
                                <td><span class="badge js-effective-access ${effective ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'}">${effective ? 'ALLOWED' : 'DENIED'}</span><small class="d-block text-muted js-effective-source">${selected === 'INHERIT' ? 'Role default' : 'User override'}</small></td>
                            </tr>`;
                        }).join('')}</tbody>
                    </table>
                </div>
            </section>
                `).join('')}
                </div>
            </div>
        `);
        el.permissionsContent.querySelectorAll('[data-access-module]').forEach(button => {
            button.addEventListener('click', () => {
                const moduleKey = button.dataset.accessModule;
                el.permissionsContent.querySelectorAll('[data-access-module]').forEach(item => item.classList.toggle('is-active', item === button));
                el.permissionsContent.querySelectorAll('[data-access-panel]').forEach(panel => {
                    const active = panel.dataset.accessPanel === moduleKey;
                    panel.hidden = !active;
                    panel.classList.toggle('is-active', active);
                });
            });
        });
    }

    async function submitPermissions(ctx, event) {
        event.preventDefault();
        const id = parseInt(el.permissionsForm.dataset.id || '0', 10);
        if (!id) return;
        const effects = {};
        el.permissionsForm.querySelectorAll('input[data-permission]:checked').forEach(input => {
            effects[input.dataset.permission] = input.value;
        });
        const result = await actions.run({
            task: () => api.form(`/api/v1/users/${id}/permissions`, forms.data({ effects: JSON.stringify(effects) }))
        });
        if (!result?.ok) return;
        modal.close(el.permissionsModal);
        ui.toast('success', result.result?.message || 'User permissions updated.');
        await loadUsers(ctx);
    }

    appPage.start();
});
