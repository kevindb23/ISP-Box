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
        passwordWrap: $('#userPasswordWrap')
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
            usersTable?.setSearch(value);
        }, 180));

        el.form?.addEventListener('submit', (e) => submitUserForm(ctx, e));
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
            html(el.content, render.skeletonTable(6, 7));
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
            <div class="card border-0 shadow-sm nx-content-card">
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
                        <div class="users-summary-label">Total Users</div>
                        <div class="users-summary-value">${total}</div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="users-summary-card users-summary-success">
                        <div class="users-summary-label">Active</div>
                        <div class="users-summary-value">${active}</div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="users-summary-card users-summary-danger">
                        <div class="users-summary-label">Disabled</div>
                        <div class="users-summary-value">${disabled}</div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="users-summary-card users-summary-info">
                        <div class="users-summary-label">Superadmins</div>
                        <div class="users-summary-value">${admins}</div>
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

    appPage.start();
});