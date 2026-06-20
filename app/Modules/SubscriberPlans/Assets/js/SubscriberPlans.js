document.addEventListener('DOMContentLoaded', () => {
    if (!window.NX) {
        console.error('NX is not loaded before SubscriberPlans.js');
        return;
    }

    const {
        api,
        ui,
        modal,
        util,
        forms,
        jobs,
        dom,
        render,
        datatable
    } = window.NX;

    const { $, text, html } = dom;
    const { escape, safeArray } = util;

    const appEl = document.getElementById('subscriberPlansApp');
    if (!appEl) return;

    const el = {
        tableView: $('#plansTableView'),
        search: $('#plansSearchInput'),
        type: $('#plansTypeFilter'),
        status: $('#plansStatusFilter'),
        paginationInfo: $('#plansPaginationInfo'),
        addBtn: $('#plansAddBtn'),
        refreshBtn: $('#plansRefreshBtn'),

        summaryTotal: $('#plansSummaryTotal'),
        summaryActive: $('#plansSummaryActive'),
        summaryPrepaid: $('#plansSummaryPrepaid'),
        summaryPostpaid: $('#plansSummaryPostpaid'),

        formModal: $('#planFormModal'),
        formModalTitle: $('#planFormModalTitle'),
        formModalSubtitle: $('#planFormModalSubtitle'),
        form: $('#planForm'),
        formId: $('#planFormId'),
        formName: $('#planName'),
        formDescription: $('#planDescription'),
        formType: $('#planType'),
        formValidity: $('#planValidityDays'),
        formPrice: $('#planPrice'),
        formStatus: $('#planStatus'),
        formSpeed: $('#planSpeed'),
        formSubmitBtn: $('#planFormSubmitBtn'),

        viewModal: $('#planViewModal'),
        viewSubtitle: $('#planViewSubtitle'),
        viewName: $('#viewPlanName'),
        viewDescription: $('#viewPlanDescription'),
        viewType: $('#viewPlanType'),
        viewValidity: $('#viewPlanValidity'),
        viewPrice: $('#viewPlanPrice'),
        viewStatus: $('#viewPlanStatus'),
        viewSpeed: $('#viewPlanSpeed')
    };

    const state = {
        rows: safeArray(window.SUBSCRIBER_PLANS_BOOTSTRAP?.plans),
        filters: {
            search: '',
            type: '',
            status: ''
        }
    };

    let plansDatatable = null;

    function peso(value) {
        return '₱' + Number(value || 0).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function planStatus(row) {
        return Number(row.is_active || 0) === 1 ? 'ACTIVE' : 'INACTIVE';
    }

    function typeBadge(row) {
        const type = String(row.plan_type || '').toUpperCase();
        const cls = type === 'PREPAID' ? 'bg-warning text-dark' : 'bg-info text-dark';
        return `<span class="badge ${cls}">${escape(type || '-')}</span>`;
    }

    function statusBadge(row) {
        const active = Number(row.is_active || 0) === 1;
        return `<span class="badge ${active ? 'bg-success' : 'bg-secondary'}">${active ? 'ACTIVE' : 'INACTIVE'}</span>`;
    }

    function updateSummary() {
        const total = state.rows.length;
        const active = state.rows.filter((r) => Number(r.is_active || 0) === 1).length;
        const prepaid = state.rows.filter((r) => String(r.plan_type || '').toUpperCase() === 'PREPAID').length;
        const postpaid = state.rows.filter((r) => String(r.plan_type || '').toUpperCase() === 'POSTPAID').length;

        text(el.summaryTotal, String(total));
        text(el.summaryActive, String(active));
        text(el.summaryPrepaid, String(prepaid));
        text(el.summaryPostpaid, String(postpaid));
    }

    function filteredRows() {
        return state.rows.filter((row) => {
            const q = String(state.filters.search || '').trim().toLowerCase();
            const type = String(row.plan_type || '').toUpperCase();
            const status = planStatus(row);

            const haystack = [
                row.plan_name,
                row.plan_type,
                row.description,
                row.validity_days,
                row.speed_mbps,
                row.price,
                status
            ].join(' ').toLowerCase();

            const searchOk = !q || haystack.includes(q);
            const typeOk = !state.filters.type || type === state.filters.type;
            const statusOk = !state.filters.status || status === state.filters.status;

            return searchOk && typeOk && statusOk;
        });
    }

    function syncPaginationInfo() {
        if (!plansDatatable || !el.paginationInfo) return;

        const pager = plansDatatable.getPager ? plansDatatable.getPager() : null;
        const currentPage = Number(pager?.currentPage || 1);
        const rowsPerPage = Number(pager?.rowsPerPage || 20);
        const totalRows = filteredRows().length;
        const totalPages = Math.max(1, Math.ceil(totalRows / rowsPerPage));

        text(
            el.paginationInfo,
            `Page ${currentPage} of ${totalPages} (${totalRows} record${totalRows !== 1 ? 's' : ''})`
        );
    }

    function ensureDatatable() {
        if (plansDatatable || !el.tableView) return;

        plansDatatable = datatable.create({
            el: el.tableView,
            rows: [],
            search: false,
            paginate: true,
            pager: { currentPage: 1, rowsPerPage: 20 },
            sort: { key: 'plan_name', dir: 'asc' },
            columns: [
                {
                    key: 'plan_name',
                    label: 'Plan',
                    render: (value) => `<div class="fw-semibold">${escape(value || '-')}</div>`
                },
                {
                    key: 'plan_type',
                    label: 'Type',
                    render: (_value, row) => typeBadge(row)
                },
                {
                    key: 'validity_days',
                    label: 'Validity',
                    render: (value) => `${Number(value || 0)} days`
                },
                {
                    key: 'speed_mbps',
                    label: 'Speed',
                    render: (value) => `${Number(value || 0)} Mbps`
                },
                {
                    key: 'price',
                    label: 'Price',
                    render: (value) => peso(value)
                },
                {
                    key: 'is_active',
                    label: 'Status',
                    render: (_value, row) => statusBadge(row)
                },
                {
                    key: 'description',
                    label: 'Description',
                    render: (value) => `<span class="text-muted">${escape(value || '-')}</span>`
                },
                {
                    key: 'actions',
                    label: 'Actions',
                    render: (_value, row) => `
                        <div class="d-inline-flex plans-table-actions">
                            <button type="button" class="btn btn-sm btn-outline-secondary js-plan-view" data-id="${Number(row.id)}" title="View">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary js-plan-edit" data-id="${Number(row.id)}" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger js-plan-delete" data-id="${Number(row.id)}" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    `
                }
            ]
        });
    }

    function bindTableActions() {
        if (!el.tableView) return;

        el.tableView.querySelectorAll('.js-plan-view').forEach((btn) => {
            btn.onclick = (e) => {
                e.preventDefault();
                openViewModal(Number(btn.dataset.id || 0));
            };
        });

        el.tableView.querySelectorAll('.js-plan-edit').forEach((btn) => {
            btn.onclick = (e) => {
                e.preventDefault();
                openEditModal(Number(btn.dataset.id || 0));
            };
        });

        el.tableView.querySelectorAll('.js-plan-delete').forEach((btn) => {
            btn.onclick = async (e) => {
                e.preventDefault();
                await deletePlan(Number(btn.dataset.id || 0));
            };
        });
    }

    function renderTable() {
        const rows = filteredRows();

        if (!rows.length) {
            if (plansDatatable) {
                plansDatatable.destroy();
                plansDatatable = null;
            }

            html(el.tableView, render.emptyState({
                title: 'No plans found',
                text: 'Create a subscriber plan to get started.'
            }));

            if (el.paginationInfo) {
                text(el.paginationInfo, 'Page 1 of 1 (0 records)');
            }
            return;
        }

        ensureDatatable();
        plansDatatable.setRows(rows);
        bindTableActions();
        syncPaginationInfo();
    }

    function rebuild() {
        updateSummary();
        renderTable();
    }

    function syncValidityControl() {
        if (!el.formType || !el.formValidity) return;

        const type = String(el.formType.value || '').toUpperCase();

        if (type === 'POSTPAID') {
            el.formValidity.value = '30';
            el.formValidity.setAttribute('disabled', 'disabled');
        } else {
            el.formValidity.removeAttribute('disabled');
        }
    }

    function resetForm() {
        if (el.form) el.form.reset();

        if (el.formId) el.formId.value = '';
        if (el.formType) el.formType.value = 'POSTPAID';
        if (el.formValidity) el.formValidity.value = '30';
        if (el.formStatus) el.formStatus.value = '1';

        text(el.formModalTitle, 'Add Subscriber Plan');
        text(el.formModalSubtitle, 'Create a commercial plan for subscriber services');
        html(el.formSubmitBtn, '<i class="bi bi-check-circle"></i><span>Save Plan</span>');

        if (el.formName) el.formName.removeAttribute('readonly');

        syncValidityControl();
    }

    function fillForm(row) {
        if (!row) return;

        if (el.formId) el.formId.value = String(row.id || '');
        if (el.formName) el.formName.value = row.plan_name || '';
        if (el.formDescription) el.formDescription.value = row.description || '';
        if (el.formType) el.formType.value = row.plan_type || 'POSTPAID';
        if (el.formValidity) el.formValidity.value = String(row.validity_days || 30);
        if (el.formPrice) el.formPrice.value = String(row.price || '');
        if (el.formStatus) el.formStatus.value = Number(row.is_active || 0) === 1 ? '1' : '0';
        if (el.formSpeed) el.formSpeed.value = String(row.speed_mbps || '');

        text(el.formModalTitle, 'Edit Subscriber Plan');
        text(el.formModalSubtitle, row.plan_name || 'Update commercial plan settings');
        html(el.formSubmitBtn, '<i class="bi bi-check-circle"></i><span>Save Changes</span>');

        if (el.formName) el.formName.setAttribute('readonly', 'readonly');

        syncValidityControl();
    }

    function openCreateModal() {
        resetForm();
        modal.open(el.formModal);
    }

    function openEditModal(id) {
        const row = state.rows.find((item) => Number(item.id) === Number(id));
        if (!row) {
            ui.toast('error', 'Plan not found.');
            return;
        }

        resetForm();
        fillForm(row);
        modal.open(el.formModal);
    }

    function openViewModal(id) {
        const row = state.rows.find((item) => Number(item.id) === Number(id));
        if (!row) {
            ui.toast('error', 'Plan not found.');
            return;
        }

        text(el.viewSubtitle, row.plan_name || '-');
        text(el.viewName, row.plan_name || '-');
        text(el.viewDescription, row.description || '-');
        text(el.viewType, row.plan_type || '-');
        text(el.viewValidity, `${Number(row.validity_days || 0)} days`);
        text(el.viewPrice, peso(row.price));
        text(el.viewStatus, planStatus(row));
        text(el.viewSpeed, `${Number(row.speed_mbps || 0)} Mbps`);

        modal.open(el.viewModal);
    }

    async function fetchPlans(showToast = false) {
        try {
            const data = await api.get('/api/v1/subscriber-plans');
            state.rows = safeArray(data);
            rebuild();

            if (showToast) {
                ui.toast('success', 'Plans refreshed.');
            }
        } catch (err) {
            ui.toast('error', err?.message || 'Failed to load plans.');
        }
    }

    async function submitForm() {
        if (!el.form) return;

        const id = Number(el.formId?.value || 0);
        const formData = new FormData(el.form);

        if (String(el.formType?.value || '').toUpperCase() === 'POSTPAID') {
            formData.set('validity_days', '30');
        }

        try {
            const result = await jobs.run({
                title: id > 0 ? 'Saving plan...' : 'Creating plan...',
                success: '',
                task: async () => {
                    const endpoint = id > 0
                        ? `/api/v1/subscriber-plans/update/${id}`
                        : '/api/v1/subscriber-plans/store';

                    return await api.form(endpoint, formData);
                }
            });

            modal.close(el.formModal);
            await fetchPlans(false);

            setTimeout(() => {
                ui.toast(
                    'success',
                    result?.message || (id > 0 ? 'Plan updated successfully.' : 'Plan created successfully.')
                );
            }, 250);
        } catch (err) {
            ui.toast('error', err?.message || 'Failed to save plan.');
        }
    }

    async function deletePlan(id) {
        const row = state.rows.find((item) => Number(item.id) === Number(id));
        if (!row) return;

        const confirmed = await ui.confirm({
            title: 'Delete Plan?',
            text: `Delete ${row.plan_name || 'this plan'}? This cannot be undone.`,
            confirmButtonText: 'Yes, delete'
        });

        if (!confirmed.isConfirmed) return;

        try {
            const result = await jobs.run({
                title: 'Deleting plan...',
                success: '',
                task: async () => {
                    return await api.form('/api/v1/subscriber-plans/delete', forms.data({ id }));
                }
            });

            state.rows = state.rows.filter((item) => Number(item.id) !== Number(id));
            rebuild();

            setTimeout(() => {
                ui.toast('success', result?.message || 'Plan deleted successfully.');
            }, 250);
        } catch (err) {
            ui.toast('error', err?.message || 'Failed to delete plan.');
        }
    }

    el.addBtn?.addEventListener('click', openCreateModal);
    el.refreshBtn?.addEventListener('click', () => fetchPlans(true));

    el.search?.addEventListener('input', () => {
        state.filters.search = String(el.search.value || '');
        renderTable();
    });

    el.type?.addEventListener('change', () => {
        state.filters.type = String(el.type.value || '').toUpperCase();
        renderTable();
    });

    el.status?.addEventListener('change', () => {
        state.filters.status = String(el.status.value || '').toUpperCase();
        renderTable();
    });

    el.formType?.addEventListener('change', syncValidityControl);

    el.form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        await submitForm();
    });

    rebuild();
});