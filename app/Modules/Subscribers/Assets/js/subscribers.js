document.addEventListener('DOMContentLoaded', () => {
    const app = document.getElementById('subscribersPage');
    if (!app || !window.NX) return;

    const {
        api,
        ui,
        dom,
        util,
        forms,
        modal,
        datatable,
        page,
        actions,
        formBuilder,
        render,
        tooltip,
        poller,
        select
    } = window.NX;

    const { $, html, text } = dom;
    const { escape, upper, safeArray } = util;

    let tableInstance = null;
    let subscribersPoller = null;
    let isCreateOpen = false;
    let isEditOpen = false;
    let isViewOpen = false;

    const refs = {
        searchInput: null,
        refreshBtn: null,
        tableHost: null,
        dataJson: null,
        plansJson: null,
        createForm: null,
        createPlanSelect: null,
        editForm: null,
        editPlanSelect: null,
        viewModal: null,
        viewBody: null,
        viewSubtitle: null,
        editModal: null,
        editSubtitle: null,
        resetPasswordBtn: null,
        statTotal: null,
        statActive: null,
        statSuspended: null,
        statOnline: null
    };

    const subscribersPage = page.create({
        state: {
            search: '',
            rows: [],
            plans: [],
            loading: false,
            loaded: false,
            pollEnabled: true,
            currentViewId: null,
            currentEditId: null
        },

        async init(ctx) {
            cacheDom();
            bindDomState(ctx);
            bindModalTracking(ctx);
            hydratePlans(ctx);
            setupForms(ctx);
        },

        async load(ctx) {
            await Promise.all([
                fetchSubscribers(ctx, { silent: false }),
                refreshPlans(ctx)
            ]);
            startPolling(ctx);
        },

        events(ctx) {
            bindStaticEvents(ctx);
            bindDelegatedActions(ctx);
        },

        render(ctx) {
            renderSummary(ctx);
            renderTable(ctx);
        }
    });

    function cacheDom() {
        refs.searchInput = $('#subscribersSearchInput');
        refs.refreshBtn = $('#subscribersRefreshBtn');
        refs.tableHost = $('#subscribersTableHost');
        refs.dataJson = $('#subscribersTableData');
        refs.plansJson = $('#subscriberPlanOptions');
        refs.createForm = $('#createSubscriberForm');
        refs.createPlanSelect = refs.createForm?.querySelector('[name="plan_id"]') || null;
        refs.editForm = $('#subscriberEditForm');
        refs.editPlanSelect = $('#subscriberEditPlanSelect');
        refs.viewModal = document.getElementById('subscriberViewModal');
        refs.viewBody = document.getElementById('subscriberViewBody');
        refs.viewSubtitle = document.getElementById('subscriberViewSubtitle');
        refs.editModal = document.getElementById('subscriberEditModal');
        refs.editSubtitle = document.getElementById('subscriberEditSubtitle');
        refs.resetPasswordBtn = refs.editForm?.querySelector('.js-dynamic-reset-password') || null;

        refs.statTotal = $('#subscribersStatTotal');
        refs.statActive = $('#subscribersStatActive');
        refs.statSuspended = $('#subscribersStatSuspended');
        refs.statOnline = $('#subscribersStatOnline');
    }

    function bindDomState(ctx) {
        if (refs.searchInput) {
            refs.searchInput.value = ctx.state.search || '';
        }
    }

    function bindModalTracking(ctx) {
        const createModalEl = document.getElementById('createModal');
        if (createModalEl) {
            createModalEl.addEventListener('shown.bs.modal', async () => {
                isCreateOpen = true;
                await refreshPlans(ctx);
            });

            createModalEl.addEventListener('hidden.bs.modal', () => {
                isCreateOpen = false;
            });
        }

        if (refs.editModal) {
            refs.editModal.addEventListener('shown.bs.modal', () => {
                isEditOpen = true;
            });

            refs.editModal.addEventListener('hidden.bs.modal', () => {
                isEditOpen = false;
            });
        }

        if (refs.viewModal) {
            refs.viewModal.addEventListener('shown.bs.modal', () => {
                isViewOpen = true;
            });

            refs.viewModal.addEventListener('hidden.bs.modal', () => {
                isViewOpen = false;
            });
        }
    }

    function closeModalFully(modalEl) {
        return new Promise((resolve) => {
            if (!modalEl) {
                resolve();
                return;
            }

            let settled = false;
            const finish = () => {
                if (settled) return;
                settled = true;

                if (!document.querySelector('.modal.show')) {
                    document.querySelectorAll('.modal-backdrop').forEach((backdrop) => backdrop.remove());
                    document.body.classList.remove('modal-open');
                    document.body.style.removeProperty('overflow');
                    document.body.style.removeProperty('padding-right');
                }
                resolve();
            };

            modalEl.addEventListener('hidden.bs.modal', finish, { once: true });
            modal.close(modalEl);
            window.setTimeout(finish, 500);
        });
    }

    function hydratePlans(ctx) {
        const raw = refs.plansJson?.textContent || '[]';

        try {
            const plans = safeArray(JSON.parse(raw));
            ctx.set('plans', plans);
            populatePlanOptions(plans);
        } catch (err) {
            console.error('[Subscribers] Failed to parse plans JSON', err);
            ctx.set('plans', []);
            populatePlanOptions([]);
        }
    }

    async function refreshPlans(ctx) {
        try {
            const plans = safeArray(await api.get('/api/v1/subscribers/plans'));
            ctx.set('plans', plans);
            populatePlanOptions(plans);
            return plans;
        } catch (err) {
            console.error('[Subscribers] Failed to refresh plans', err);
            return safeArray(ctx.state.plans);
        }
    }

    function populatePlanOptions(plans) {
        const createSelected = refs.createPlanSelect?.value || '';
        if (refs.createPlanSelect) {
            select.fill(refs.createPlanSelect, plans, {
                valueKey: 'id',
                placeholder: 'Select Plan',
                selected: createSelected,
                label: (plan) => `${plan.plan_name} - ${plan.speed_mbps} Mbps - ${plan.plan_type}`
            });
        }
        populateEditPlanOptions(plans, refs.editPlanSelect?.value || '');
    }

    function populateEditPlanOptions(plans, selected = '') {
        if (!refs.editPlanSelect) return;

        select.fill(refs.editPlanSelect, plans, {
            valueKey: 'id',
            placeholder: 'Select Plan',
            selected,
            label: (plan) => `${plan.plan_name} - ${plan.speed_mbps} Mbps - ${plan.plan_type}`
        });
    }

    async function fetchSubscribers(ctx, { silent = true } = {}) {
        if (!silent) {
            ctx.patch({ loading: true });

            if (refs.tableHost) {
                html(refs.tableHost, render.skeletonTable(6, 7));
            }
        }

        try {
            const search = String(ctx.state.search || '').trim();
            const url = search
                ? `/api/v1/subscribers?search=${encodeURIComponent(search)}`
                : '/api/v1/subscribers';

            const rows = safeArray(await api.get(url));

            ctx.patch({
                rows: normalizeRows(rows),
                loading: false,
                loaded: true
            });
        } catch (err) {
            console.error('[Subscribers] API load failed, trying DOM fallback.', err);

            const fallbackRows = loadRowsFromDomFallback();

            ctx.patch({
                rows: fallbackRows,
                loading: false,
                loaded: true
            });

            if (!fallbackRows.length) {
                nxToast('error', err?.message || 'Failed to load subscribers.');
            }
        }
    }

    async function refreshSessionStates(ctx) {
        const search = String(ctx.state.search || '').trim();
        const url = search
            ? `/api/v1/subscribers/sessions?search=${encodeURIComponent(search)}`
            : '/api/v1/subscribers/sessions';

        const sessionRows = safeArray(await api.get(url));
        const currentRows = safeArray(ctx.state.rows);

        if (!currentRows.length || !sessionRows.length) {
            return;
        }

        const sessionMap = new Map(
            sessionRows.map((row) => [
                String(row.id),
                {
                    online: Number(row.online || 0) === 1 ? 1 : 0,
                    online_text: String(row.online_text || 'OFFLINE')
                }
            ])
        );

        let changed = false;

        const nextRows = currentRows.map((row) => {
            const session = sessionMap.get(String(row.id));
            if (!session) return row;

            const currentOnline = Number(row.online || 0) === 1 ? 1 : 0;
            const currentText = String(row.online_text || (currentOnline ? 'ONLINE' : 'OFFLINE'));

            if (currentOnline === session.online && currentText === session.online_text) {
                return row;
            }

            changed = true;

            return {
                ...row,
                online: session.online,
                online_text: session.online_text
            };
        });

        if (changed) {
            ctx.set('rows', nextRows);
        }
    }

    function loadRowsFromDomFallback() {
        const raw = refs.dataJson?.textContent || '[]';

        try {
            return normalizeRows(JSON.parse(raw));
        } catch (err) {
            console.error('[Subscribers] Failed to parse fallback JSON', err);
            return [];
        }
    }

    function normalizeRows(rows) {
        return safeArray(rows).map((row) => {
            const online = Number(row.online || 0) === 1 ? 1 : 0;

            return {
                id: row.id ?? 0,
                account_number: row.account_number ?? '',
                full_name: row.full_name ?? '',
                contact_number: row.contact_number ?? '',
                email: row.email ?? '',
                address: row.address ?? '',
                ppp_username: row.ppp_username ?? '',
                plan_id: row.plan_id ?? 0,
                plan_name: row.plan_name ?? '',
                account_type: row.account_type ?? '',
                service_status: row.service_status ?? '',
                service_number: row.service_number ?? '',
                next_due_date: row.next_due_date ?? '',
                expires_at: row.expires_at ?? '',
                nap_name: row.nap_name ?? '',
                nap_splitter_port: row.nap_splitter_port ?? '',
                ont_serial: row.ont_serial ?? '',
                installed_at: row.installed_at ?? '',
                cvlan: row.cvlan ?? '',
                svlan: row.svlan ?? '',
                last_seen: row.last_seen ?? null,
                online,
                online_text: row.online_text ?? (online === 1 ? 'ONLINE' : 'OFFLINE')
            };
        });
    }

    function nxToast(icon = 'success', title = '') {
        if (typeof Swal === 'undefined') {
            console.log(`[${icon}] ${title}`);
            return;
        }

        let timer = 3200;
        if (icon === 'error') timer = 5000;
        if (String(title || '').length > 90) timer = 5400;

        return Swal.fire({
            toast: true,
            position: 'top-end',
            icon,
            title,
            showConfirmButton: false,
            timer,
            timerProgressBar: true,
            customClass: {
                popup: 'nx-toast-popup'
            },
            didOpen: (toastEl) => {
                toastEl.addEventListener('mouseenter', Swal.stopTimer);
                toastEl.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });
    }

    function renderBadge(value, type = 'default') {
        const safe = upper(value || '-');

        if (type === 'service_status') {
            if (safe === 'ACTIVE') return '<span class="nx-soft-badge nx-soft-badge-success">ACTIVE</span>';
            if (safe === 'SUSPENDED') return '<span class="nx-soft-badge nx-soft-badge-danger">SUSPENDED</span>';
            if (safe === 'TERMINATED') return '<span class="nx-soft-badge nx-soft-badge-danger">TERMINATED</span>';
            if (safe === 'PENDING') return '<span class="nx-soft-badge nx-soft-badge-warning">PENDING</span>';
            if (safe === 'UNKNOWN') return '<span class="nx-soft-badge">UNKNOWN</span>';
        }

        if (type === 'account_type') {
            if (safe === 'POSTPAID') return '<span class="nx-soft-badge">POSTPAID</span>';
            if (safe === 'PREPAID') return '<span class="nx-soft-badge nx-soft-badge-info">PREPAID</span>';
        }

        if (type === 'online') {
            if (safe === 'ONLINE') return '<span class="nx-soft-badge nx-soft-badge-info">ONLINE</span>';
            if (safe === 'OFFLINE') return '<span class="nx-soft-badge nx-soft-badge-danger">OFFLINE</span>';
        }

        return `<span class="nx-soft-badge">${escape(safe || '-')}</span>`;
    }

    function renderActions(row) {
        const id = parseInt(row.id, 10) || 0;
        const isSuspended = upper(row.service_status) === 'SUSPENDED';

        return `
            <div class="nx-subscriber-actions">
                <button type="button"
                        class="btn btn-sm btn-outline-secondary nx-icon-btn js-view"
                        data-id="${id}"
                        title="View Details">
                    <i class="bi bi-eye"></i>
                </button>

                <button type="button"
                        class="btn btn-sm btn-outline-primary nx-icon-btn js-edit"
                        data-id="${id}"
                        title="Edit Subscriber">
                    <i class="bi bi-pencil"></i>
                </button>

                ${
            isSuspended
                ? `
                            <button type="button"
                                    class="btn btn-sm btn-outline-success nx-icon-btn js-subscriber-reactivate"
                                    data-id="${id}"
                                    title="Reactivate Subscriber">
                                <i class="bi bi-play-circle"></i>
                            </button>
                        `
                : `
                            <button type="button"
                                    class="btn btn-sm btn-outline-warning nx-icon-btn js-subscriber-suspend"
                                    data-id="${id}"
                                    title="Suspend Subscriber">
                                <i class="bi bi-pause-circle"></i>
                            </button>
                        `
        }

                <button type="button"
                        class="btn btn-sm btn-outline-dark nx-icon-btn js-reset-portal-password"
                        data-id="${id}"
                        title="Reset Portal Password">
                    <i class="bi bi-person-lock"></i>
                </button>

                <button type="button"
                        class="btn btn-sm btn-outline-danger nx-icon-btn js-subscriber-delete"
                        data-id="${id}"
                        title="Delete Subscriber">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        `;
    }

    function getSummary(rows) {
        const safeRows = safeArray(rows);

        return {
            total: safeRows.length,
            active: safeRows.filter((row) => upper(row.service_status) === 'ACTIVE').length,
            suspended: safeRows.filter((row) => upper(row.service_status) === 'SUSPENDED').length,
            online: safeRows.filter((row) => upper(row.online_text) === 'ONLINE').length
        };
    }

    function renderSummary(ctx) {
        const summary = getSummary(ctx.state.rows);

        if (refs.statTotal) text(refs.statTotal, String(summary.total));
        if (refs.statActive) text(refs.statActive, String(summary.active));
        if (refs.statSuspended) text(refs.statSuspended, String(summary.suspended));
        if (refs.statOnline) text(refs.statOnline, String(summary.online));
    }

    function renderTable(ctx) {
        if (!refs.tableHost) return;
        if (!ctx.state.loaded) return;

        const rows = safeArray(ctx.state.rows);

        if (!rows.length) {
            if (tableInstance) {
                tableInstance.destroy();
                tableInstance = null;
            }

            html(refs.tableHost, render.emptyState({
                title: 'No subscribers found',
                text: 'Create your first subscriber to begin service onboarding.',
                buttonLabel: 'Add Subscriber',
                buttonId: 'subscribersEmptyAddBtn',
                iconClass: 'bi bi-people'
            }));

            $('#subscribersEmptyAddBtn')?.addEventListener('click', () => {
                const modalEl = document.getElementById('createModal');
                if (modalEl) modal.open(modalEl);
            });

            return;
        }

        html(refs.tableHost, '<div id="subscribersDataTable"></div>');

        if (tableInstance) {
            tableInstance.destroy();
            tableInstance = null;
        }

        const columns = [
            {
                key: 'account_number',
                label: 'Account Number',
                render: (value, row) => `
                    <div class="nx-cell-stack">
                        <div class="nx-cell-title">${escape(value || '-')}</div>
                        <div class="nx-cell-sub">Service # ${escape(row.service_number || '-')}</div>
                    </div>
                `
            },
            {
                key: 'full_name',
                label: 'Subscriber',
                render: (value, row) => `
                    <div class="nx-cell-stack">
                        <div class="nx-cell-title">${escape(value || '-')}</div>
                        <div class="nx-cell-sub">${escape(row.contact_number || row.email || '-')}</div>
                    </div>
                `
            },
            {
                key: 'ppp_username',
                label: 'PPP Username',
                render: (value) => `<span class="nx-text-mono fw-semibold">${escape(value || '-')}</span>`
            },
            {
                key: 'plan_name',
                label: 'Plan',
                render: (value, row) => `
                    <div class="nx-cell-stack">
                        <div class="nx-cell-title">${escape(value || '-')}</div>
                        <div class="nx-cell-sub">${escape(row.account_type || '-')}</div>
                    </div>
                `
            },
            {
                key: 'service_status',
                label: 'Service Status',
                render: (value) => renderBadge(value, 'service_status')
            },
            {
                key: 'online_text',
                label: 'Online',
                render: (value) => renderBadge(value, 'online')
            }
        ];

        const hasLastSeen = rows.some((row) => row.last_seen);
        if (hasLastSeen) {
            columns.push({
                key: 'last_seen',
                label: 'Last Seen',
                render: (value) => value ? util.formatDateTime(value) : '-'
            });
        }

        columns.push({
            key: '__actions',
            label: 'Actions',
            render: (_, row) => renderActions(row)
        });

        tableInstance = datatable.create({
            el: '#subscribersDataTable',
            rows,
            search: false,
            paginate: true,
            pager: { currentPage: 1, rowsPerPage: 20 },
            sort: { key: 'account_number', dir: 'desc' },
            columns
        });

        appendMobileCards(rows, hasLastSeen);
        tooltip.refresh(refs.tableHost);
    }

    function appendMobileCards(rows, hasLastSeen = false) {
        const root = document.getElementById('subscribersDataTable');
        if (!root) return;

        const mobileHtml = `
            <div class="subs-mobile-list">
                ${rows.map((row) => renderMobileCard(row, hasLastSeen)).join('')}
            </div>
        `;

        root.insertAdjacentHTML('beforeend', mobileHtml);
    }

    function renderMobileCard(row, hasLastSeen = false) {
        const lastSeenBlock = hasLastSeen ? `
            <div class="subs-mobile-item">
                <div class="subs-mobile-item__label">Last Seen</div>
                <div class="subs-mobile-item__value">${row.last_seen ? escape(util.formatDateTime(row.last_seen)) : '-'}</div>
            </div>
        ` : '';

        return `
            <article class="subs-mobile-card">
                <div class="subs-mobile-card__header">
                    <div class="subs-mobile-card__identity">
                        <div class="subs-mobile-card__title">${escape(row.full_name || '-')}</div>
                        <div class="subs-mobile-card__meta">Account # ${escape(row.account_number || '-')}</div>
                    </div>
                    <div class="subs-mobile-card__status">
                        ${renderBadge(row.service_status, 'service_status')}
                    </div>
                </div>

                <div class="subs-mobile-card__grid">
                    <div class="subs-mobile-item">
                        <div class="subs-mobile-item__label">PPP Username</div>
                        <div class="subs-mobile-item__value nx-text-mono">${escape(row.ppp_username || '-')}</div>
                    </div>

                    <div class="subs-mobile-item">
                        <div class="subs-mobile-item__label">Plan</div>
                        <div class="subs-mobile-item__value">${escape(row.plan_name || '-')}</div>
                        <div class="subs-mobile-item__sub">${escape(row.account_type || '-')}</div>
                    </div>

                    <div class="subs-mobile-item">
                        <div class="subs-mobile-item__label">Contact</div>
                        <div class="subs-mobile-item__value">${escape(row.contact_number || row.email || '-')}</div>
                    </div>

                    <div class="subs-mobile-item">
                        <div class="subs-mobile-item__label">Online State</div>
                        <div class="subs-mobile-item__value">${renderBadge(row.online_text, 'online')}</div>
                    </div>

                    <div class="subs-mobile-item">
                        <div class="subs-mobile-item__label">Service Number</div>
                        <div class="subs-mobile-item__value">${escape(row.service_number || '-')}</div>
                    </div>

                    ${lastSeenBlock}
                </div>

                <div class="subs-mobile-card__actions">
                    ${renderActions(row)}
                </div>
            </article>
        `;
    }

    function bindStaticEvents(ctx) {
        refs.searchInput?.addEventListener('input', util.debounce(async (e) => {
            const search = e.target.value || '';
            ctx.set('search', search);
            await fetchSubscribers(ctx, { silent: true });
        }, 250));

        refs.refreshBtn?.addEventListener('click', async () => {
            await Promise.all([
                fetchSubscribers(ctx, { silent: false }),
                refreshPlans(ctx)
            ]);
            nxToast('success', 'Subscribers refreshed.');
        });
    }

    function setupForms(ctx) {
        setupCreateForm(ctx);
        setupDynamicEditForm(ctx);
    }

    function setupCreateForm(ctx) {
        if (!refs.createForm) return;

        formBuilder.create({
            el: refs.createForm,
            rules: {
                full_name: ['required'],
                plan_id: ['required']
            },
            submit: async (_data, _e, formEl) => {
                ui.loading('Creating subscriber...');

                try {
                    return await api.form('/subscribers/create', new FormData(formEl));
                } finally {
                    ui.closeLoading();
                }
            },
            onSuccess: async (res, _e, formEl) => {
                const data = res?.data || {};

                const pppUsername = data.ppp_username || '';
                const pppPassword = data.ppp_password || '';
                const portalUsername = data.portal_username || '';
                const portalPassword = data.portal_password || '';

                const htmlMessage = `
                    <div class="text-start">
                        <div class="mb-3">
                            <div class="fw-semibold text-success">
                                ${escape(res.message || 'Subscriber created successfully.')}
                            </div>
                        </div>

                        <div class="border rounded p-3 mb-3 bg-light">
                            <div class="fw-bold mb-2">
                                <i class="bi bi-person-lock"></i>
                                Subscriber Portal Login
                            </div>

                            <div class="small text-muted">Portal Username</div>
                            <div class="fw-semibold mb-2">${escape(portalUsername || '-')}</div>

                            <div class="small text-muted">Portal Password</div>
                            <div class="fw-semibold">${escape(portalPassword || '-')}</div>
                        </div>

                        <div class="border rounded p-3">
                            <div class="fw-bold mb-2">
                                <i class="bi bi-router"></i>
                                PPPoE Credentials
                            </div>

                            <div class="small text-muted">PPP Username</div>
                            <div class="fw-semibold mb-2">${escape(pppUsername || '-')}</div>

                            <div class="small text-muted">PPP Password</div>
                            <div class="fw-semibold">${escape(pppPassword || '-')}</div>
                        </div>

                        <div class="small text-muted mt-3">
                            Please copy these credentials now. The portal password may not be shown again.
                        </div>
                    </div>
                `;

                const createModalEl = document.getElementById('createModal');
                await closeModalFully(createModalEl);

                forms.reset(formEl);
                await fetchSubscribers(ctx, { silent: true });

                if (typeof Swal !== 'undefined') {
                    await Swal.fire({
                        icon: 'success',
                        title: 'Subscriber Created',
                        html: htmlMessage,
                        width: 560,
                        confirmButtonText: 'Close',
                        customClass: {
                            popup: 'nx-swal-popup'
                        }
                    });
                } else {
                    nxToast(
                        'success',
                        `Subscriber created. Portal Username: ${portalUsername || '-'} Portal Password: ${portalPassword || '-'} PPP Username: ${pppUsername || '-'} PPP Password: ${pppPassword || '-'}`
                    );
                }

            },
            onError: async (payload) => {
                ui.closeLoading();

                if (payload?.type === 'validation') {
                    nxToast('error', 'Please fix the highlighted fields.');
                    return;
                }

                nxToast('error', payload?.error?.message || 'Failed to create subscriber.');
            }
        });
    }

    function setupDynamicEditForm(ctx) {
        if (!refs.editForm) return;

        formBuilder.create({
            el: refs.editForm,
            rules: {
                full_name: ['required'],
                plan_id: ['required']
            },
            submit: async (_data, _e, formEl) => {
                const id = formEl.querySelector('[name="id"]')?.value || '';

                if (!id) {
                    throw new Error('Missing subscriber ID.');
                }

                ui.loading('Saving subscriber...');

                try {
                    return await api.form(`/subscribers/update/${id}`, new FormData(formEl));
                } finally {
                    ui.closeLoading();
                }
            },
            onSuccess: async (res, _e, formEl) => {
                const id = formEl.querySelector('[name="id"]')?.value || '';
                if (!id) return;

                patchRow(ctx, id, {
                    full_name: formEl.querySelector('[name="full_name"]')?.value?.trim() || '-',
                    email: formEl.querySelector('[name="email"]')?.value?.trim() || '',
                    contact_number: formEl.querySelector('[name="contact_number"]')?.value?.trim() || '',
                    address: formEl.querySelector('[name="address"]')?.value?.trim() || '',
                    plan_id: formEl.querySelector('[name="plan_id"]')?.value || '',
                    plan_name: getPlanLabel(formEl)
                });

                nxToast('success', res.message || 'Subscriber updated successfully.');
                modal.close(refs.editModal);
            },
            onError: async (payload) => {
                ui.closeLoading();

                if (payload?.type === 'validation') {
                    nxToast('error', 'Please fix the highlighted fields.');
                    return;
                }

                nxToast('error', payload?.error?.message || 'Failed to update subscriber.');
            }
        });
    }

    function bindDelegatedActions(ctx) {
        dom.on(refs.tableHost, 'click', '.js-subscriber-suspend', async (_e, btn) => {
            const id = btn.dataset.id;
            if (!id) return;

            await actions.run({
                confirm: {
                    title: 'Suspend Subscriber?',
                    text: 'The PPP session will be disconnected.',
                    confirmButtonText: 'Yes, suspend'
                },
                loading: 'Suspending subscriber...',
                task: async () => api.form('/subscribers/suspend', forms.data({ id })),
                onSuccess: async (res) => {
                    patchRow(ctx, id, {
                        service_status: 'SUSPENDED',
                        online: 0,
                        online_text: 'OFFLINE'
                    });

                    nxToast('success', res.message || 'Subscriber suspended. PPP session terminated.');
                },
                onError: async (err) => {
                    nxToast('error', err?.message || 'Suspend failed.');
                }
            });
        });

        dom.on(refs.tableHost, 'click', '.js-subscriber-reactivate', async (_e, btn) => {
            const id = btn.dataset.id;
            if (!id) return;

            await actions.run({
                confirm: {
                    title: 'Reactivate Subscriber?',
                    text: 'This will reactivate the subscriber service.',
                    confirmButtonText: 'Yes, reactivate'
                },
                loading: 'Reactivating subscriber...',
                task: async () => api.form('/subscribers/reactivate', forms.data({ id })),
                onSuccess: async (res) => {
                    patchRow(ctx, id, {
                        service_status: 'ACTIVE',
                        online: 0,
                        online_text: 'OFFLINE'
                    });

                    nxToast('success', res.message || 'Subscriber reactivated.');
                },
                onError: async (err) => {
                    nxToast('error', err?.message || 'Reactivation failed.');
                }
            });
        });

        dom.on(refs.tableHost, 'click', '.js-reset-portal-password', async (_e, btn) => {
            const id = btn.dataset.id;
            if (!id) return;

            await actions.run({
                confirm: {
                    title: 'Reset Portal Password?',
                    text: 'This will generate a new subscriber portal login password. PPPoE password will not be changed.',
                    confirmButtonText: 'Yes, reset'
                },
                loading: 'Resetting portal password...',
                task: async () => api.form('/subscribers/reset-portal-password', forms.data({ id })),
                onSuccess: async (res) => {
                    const data = res?.data || res || {};
                    const portalUsername = data.portal_username || res?.portal_username || '-';
                    const portalPassword = data.portal_password || res?.portal_password || '-';

                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Portal Password Reset',
                            html: `
                                <div class="text-start">
                                    <div class="border rounded p-3 bg-light">
                                        <div class="fw-bold mb-2">
                                            <i class="bi bi-person-lock"></i>
                                            Subscriber Portal Login
                                        </div>

                                        <div class="small text-muted">Portal Username</div>
                                        <div class="fw-semibold mb-2">${escape(portalUsername)}</div>

                                        <div class="small text-muted">New Portal Password</div>
                                        <div class="fw-semibold">${escape(portalPassword)}</div>
                                    </div>

                                    <div class="small text-muted mt-3">
                                        Please copy this password now. It may not be shown again.
                                    </div>
                                </div>
                            `,
                            width: 560,
                            confirmButtonText: 'Close',
                            customClass: {
                                popup: 'nx-swal-popup'
                            }
                        });
                    } else {
                        nxToast('success', `Portal Username: ${portalUsername} New Password: ${portalPassword}`);
                    }
                },
                onError: async (err) => {
                    nxToast('error', err?.message || 'Portal password reset failed.');
                }
            });
        });

        dom.on(refs.tableHost, 'click', '.js-subscriber-delete', async (_e, btn) => {
            const id = btn.dataset.id;
            if (!id) return;

            await actions.run({
                confirm: {
                    title: 'Delete Subscriber?',
                    text: 'This will terminate the service and remove Radius auth.',
                    confirmButtonText: 'Yes, delete'
                },
                loading: 'Deleting subscriber...',
                task: async () => api.form('/subscribers/delete', forms.data({ id })),
                onSuccess: async (res) => {
                    removeRow(ctx, id);
                    nxToast('success', res.message || 'Subscriber deleted.');
                },
                onError: async (err) => {
                    nxToast('error', err?.message || 'Delete failed.');
                }
            });
        });

        dom.on(document, 'click', '.js-dynamic-reset-password', async (_e, btn) => {
            const id = btn.dataset.id;
            if (!id) return;

            await actions.run({
                confirm: {
                    title: 'Reset PPP Password?',
                    text: 'The current PPP session will be disconnected.',
                    confirmButtonText: 'Yes, reset'
                },
                loading: 'Resetting PPP password...',
                task: async () => api.form('/subscribers/reset-password', forms.data({ id })),
                onSuccess: async (res) => {
                    const newPassword = res?.data?.ppp_password || res?.ppp_password || '';

                    if (refs.editForm) {
                        const passwordEl = refs.editForm.querySelector('[name="ppp_password_display"]');
                        if (passwordEl && newPassword) {
                            passwordEl.value = newPassword;
                        }
                    }

                    patchRow(ctx, id, {
                        ppp_password: newPassword || undefined
                    });

                    nxToast(
                        'success',
                        newPassword
                            ? `New PPP Password: ${newPassword}`
                            : (res.message || 'PPP password reset.')
                    );
                },
                onError: async (err) => {
                    nxToast('error', err?.message || 'Reset failed.');
                }
            });
        });

        dom.on(refs.tableHost, 'click', '.js-view', async (_e, btn) => {
            const id = btn.dataset.id;
            if (!id || !refs.viewModal || !refs.viewBody) return;

            subscribersPage.store.set('currentViewId', id);

            if (refs.viewSubtitle) {
                refs.viewSubtitle.textContent = 'Loading...';
            }

            refs.viewBody.innerHTML = '<div class="text-center py-4 text-muted">Loading...</div>';
            modal.open(refs.viewModal);

            try {
                const data = await api.get(`/api/v1/subscribers/${id}`);

                if (refs.viewSubtitle) {
                    refs.viewSubtitle.textContent = data.full_name || 'Subscriber';
                }

                refs.viewBody.innerHTML = `
                    <div class="nx-section-card">
                        <div class="nx-section-title mb-3">
                            <i class="bi bi-person text-primary"></i>
                            <span>Subscriber Info</span>
                        </div>

                        <div class="nx-subscriber-detail-grid">
                            <div class="nx-field">
                                <label>Account Number</label>
                                <div>${escape(data.account_number || '-')}</div>
                            </div>

                            <div class="nx-field">
                                <label>Full Name</label>
                                <div>${escape(data.full_name || '-')}</div>
                            </div>

                            <div class="nx-field">
                                <label>Contact Number</label>
                                <div>${escape(data.contact_number || '-')}</div>
                            </div>

                            <div class="nx-field">
                                <label>Email</label>
                                <div>${escape(data.email || '-')}</div>
                            </div>

                            <div class="nx-field nx-span-2">
                                <label>Address</label>
                                <div>${escape(data.address || '-')}</div>
                            </div>
                        </div>
                    </div>

                    <div class="nx-section-card">
                        <div class="nx-section-title mb-3">
                            <i class="bi bi-diagram-3 text-success"></i>
                            <span>Service Info</span>
                        </div>

                        <div class="nx-subscriber-detail-grid">
                            <div class="nx-field">
                                <label>Service Number</label>
                                <div>${escape(data.service_number || '-')}</div>
                            </div>

                            <div class="nx-field">
                                <label>Subscriber Services</label>
                                <div>${escape(data.service_count || 1)} total · showing preferred active service</div>
                            </div>

                            <div class="nx-field">
                                <label>Plan</label>
                                <div>${escape(data.plan_name || '-')}</div>
                            </div>

                            <div class="nx-field">
                                <label>Account Type</label>
                                <div>${escape(data.account_type || '-')}</div>
                            </div>

                            <div class="nx-field">
                                <label>Service Status</label>
                                <div>${escape(data.service_status || '-')}</div>
                            </div>

                            <div class="nx-field">
                                <label>Next Due Date</label>
                                <div>${escape(data.next_due_date || '-')}</div>
                            </div>

                            <div class="nx-field">
                                <label>Expires At</label>
                                <div>${escape(data.expires_at || '-')}</div>
                            </div>
                        </div>
                    </div>

                    <div class="nx-section-card">
                        <div class="nx-section-title mb-3">
                            <i class="bi bi-key text-warning"></i>
                            <span>PPP Credentials</span>
                        </div>

                        <div class="nx-subscriber-credentials">
                            <div class="nx-field">
                                <label>PPP Username</label>
                                <div>${escape(data.ppp_username || '-')}</div>
                            </div>

                        </div>
                    </div>

                    <div class="nx-section-card">
                        <div class="nx-section-title mb-3">
                            <i class="bi bi-hdd-network text-info"></i>
                            <span>Provisioning Info</span>
                        </div>

                        <div class="nx-subscriber-detail-grid">
                            <div class="nx-field">
                                <label>Provisioning Status</label>
                                <div>${escape(data.provisioning_status || 'Not provisioned')}</div>
                            </div>

                            <div class="nx-field">
                                <label>Provisioning Job</label>
                                <div>${escape(data.provisioning_job_no || 'No provisioning job')}</div>
                            </div>

                            <div class="nx-field">
                                <label>Provisioning Date</label>
                                <div>${escape(data.provisioning_date || 'Not provisioned')}</div>
                            </div>

                            <div class="nx-field">
                                <label>Installation Date</label>
                                <div>${escape(data.installed_at || 'Not installed')}</div>
                            </div>

                            <div class="nx-field"><label>OLT</label><div>${escape(data.olt_name || 'Not assigned')}</div></div>
                            <div class="nx-field"><label>OLT Address</label><div>${escape(data.olt_ip_address || 'Not assigned')}</div></div>
                            <div class="nx-field"><label>PON</label><div>${escape(data.olt_port_name || 'Not assigned')}</div></div>
                            <div class="nx-field"><label>ONT ID</label><div>${escape(data.ont_assigned_id ?? 'Not assigned')}</div></div>
                            <div class="nx-field"><label>ONT Serial</label><div>${escape(data.ont_serial || 'Not assigned')}</div></div>
                            <div class="nx-field"><label>ONT Status</label><div>${escape(data.ont_status || 'Unknown')}</div></div>
                            <div class="nx-field"><label>NAP</label><div>${escape(data.nap_name || 'Not assigned')}</div></div>
                            <div class="nx-field"><label>NAP Code</label><div>${escape(data.nap_code || 'Not assigned')}</div></div>
                            <div class="nx-field"><label>Splitter</label><div>${escape(data.splitter_model || (data.splitter_ratio ? `1:${data.splitter_ratio}` : 'Not assigned'))}</div></div>
                            <div class="nx-field"><label>Splitter Port</label><div>${escape(data.nap_splitter_port ?? 'Not assigned')}</div></div>

                            <div class="nx-field">
                                <label>CVLAN</label>
                                <div>${escape(data.cvlan ?? 'Not assigned')}</div>
                            </div>

                            <div class="nx-field">
                                <label>SVLAN</label>
                                <div>${escape(data.svlan ?? 'Not assigned')}</div>
                            </div>
                            <div class="nx-field"><label>ACS Status</label><div>${escape(data.acs_status || 'Not discovered in ACS')}</div></div>
                            <div class="nx-field"><label>WAN IP</label><div>${escape(data.acs_wan_ip || 'No ACS WAN address')}</div></div>
                            <div class="nx-field"><label>PPP Session</label><div>${escape(data.online ? 'ONLINE' : 'OFFLINE')}</div></div>
                        </div>
                    </div>
                `;
            } catch (err) {
                refs.viewBody.innerHTML = '<div class="text-center py-4 text-danger">Failed to load subscriber details.</div>';
            }
        });

        dom.on(refs.tableHost, 'click', '.js-edit', async (_e, btn) => {
            const id = btn.dataset.id;
            if (!id || !refs.editModal || !refs.editForm) return;

            subscribersPage.store.set('currentEditId', id);

            if (refs.editSubtitle) {
                refs.editSubtitle.textContent = 'Loading...';
            }

            refs.editForm.reset();
            refs.editForm.querySelector('[name="id"]').value = '';
            refs.editForm.querySelector('[name="service_status_display"]').value = '';
            refs.editForm.querySelector('[name="ppp_username_display"]').value = '';
            refs.editForm.querySelector('[name="ppp_password_display"]').value = '';

            if (refs.resetPasswordBtn) {
                refs.resetPasswordBtn.dataset.id = '';
            }

            populateEditPlanOptions(subscribersPage.store.get('plans') || []);
            modal.open(refs.editModal);

            try {
                const data = await api.get(`/api/v1/subscribers/${id}`);

                refs.editForm.querySelector('[name="id"]').value = data.id || '';
                refs.editForm.querySelector('[name="full_name"]').value = data.full_name || '';
                refs.editForm.querySelector('[name="email"]').value = data.email || '';
                refs.editForm.querySelector('[name="contact_number"]').value = data.contact_number || '';
                refs.editForm.querySelector('[name="address"]').value = data.address || '';
                refs.editForm.querySelector('[name="service_status_display"]').value = data.service_status || '';
                refs.editForm.querySelector('[name="ppp_username_display"]').value = data.ppp_username || '';
                refs.editForm.querySelector('[name="ppp_password_display"]').value = data.ppp_password || '';

                populateEditPlanOptions(subscribersPage.store.get('plans') || [], String(data.plan_id || ''));

                if (refs.resetPasswordBtn) {
                    refs.resetPasswordBtn.dataset.id = String(data.id || '');
                }

                if (refs.editSubtitle) {
                    refs.editSubtitle.textContent = data.full_name || 'Subscriber';
                }
            } catch (err) {
                nxToast('error', 'Failed to load subscriber.');
                modal.close(refs.editModal);
            }
        });
    }

    function startPolling(ctx) {
        if (subscribersPoller) {
            subscribersPoller.destroy();
            subscribersPoller = null;
        }

        subscribersPoller = poller.create({
            interval: 30000,
            pauseWhenHidden: true,
            autoStart: true,
            task: async () => {
                if (!ctx.state.pollEnabled) return null;
                if (isCreateOpen || isEditOpen || isViewOpen) return null;

                await refreshSessionStates(ctx);
                return true;
            },
            onError: (err) => {
                console.warn('[Subscribers session poller]', err);
            }
        });
    }

    function patchRow(ctx, id, patch = {}) {
        const nextRows = safeArray(ctx.state.rows).map((row) => {
            if (String(row.id) !== String(id)) return row;
            return { ...row, ...patch };
        });

        ctx.set('rows', nextRows);
    }

    function removeRow(ctx, id) {
        const nextRows = safeArray(ctx.state.rows).filter((row) => String(row.id) !== String(id));
        ctx.set('rows', nextRows);
    }

    function getPlanLabel(formEl) {
        const selectEl = formEl?.querySelector('[name="plan_id"]');
        if (!selectEl) return '-';

        const opt = selectEl.options[selectEl.selectedIndex];
        if (!opt) return '-';

        return String(opt.textContent || '').split(' - ')[0].trim() || '-';
    }

    subscribersPage.start();
});
