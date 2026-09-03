document.addEventListener('DOMContentLoaded', () => {
    const app = document.querySelector('[data-work-orders-page="index"]');
    if (!app || !window.NX) return;

    const { api, dom, util, datatable, render, tooltip } = window.NX;
    const { $, html, text } = dom;
    const { escape, safeArray, upper } = util;

    let tableInstance = null;

    const state = {
        workOrders: [],
        selectedWorkOrder: null,
        selectedTasks: [],
        selectedLogs: [],
        assignableTechnicians: [],
    };

    const refs = {
        refreshBtn: $('#workOrdersRefreshBtn'),
        statusFilter: $('#workOrdersStatusFilter'),
        typeFilter: $('#workOrdersTypeFilter'),
        searchInput: $('#workOrdersSearchInput'),
        tableHost: $('#workOrdersTableHost'),

        openCount: $('#workOrdersOpenCount'),
        assignedCount: $('#workOrdersAssignedCount'),
        activeCount: $('#workOrdersActiveCount'),
        completedCount: $('#workOrdersCompletedCount'),
        issueCount: $('#workOrdersIssueCount'),

        assignForm: $('#workOrderAssignForm'),
        statusForm: $('#workOrderStatusForm'),
    };

    bindEvents();
    loadWorkOrders();

    function bindEvents() {
        refs.refreshBtn?.addEventListener('click', async () => {
            await loadWorkOrders();
            nxToast('success', 'Work orders refreshed.');
        });

        refs.statusFilter?.addEventListener('change', loadWorkOrders);
        refs.typeFilter?.addEventListener('change', loadWorkOrders);
        refs.searchInput?.addEventListener('input', debounce(loadWorkOrders, 350));

        refs.assignForm?.addEventListener('submit', assignWorkOrder);
        refs.statusForm?.addEventListener('submit', updateWorkOrderStatus);

        dom.on(refs.tableHost, 'click', '.js-work-order-view', (_e, btn) => {
            const id = btn.dataset.id;
            if (id) loadWorkOrderDetails(id);
        });

        dom.on(document, 'click', '.work-order-status-shortcut', (_e, btn) => {
            const status = btn.dataset.status || '';
            if (status) quickUpdateStatus(status, btn);
        });

        dom.on(document, 'click', '.js-work-order-task-complete', (_e, btn) => {
            const taskId = btn.dataset.taskId || '';
            if (taskId) completeTask(taskId, btn);
        });

        dom.on(document, 'click', '.js-work-order-task-reopen', (_e, btn) => {
            const taskId = btn.dataset.taskId || '';
            if (taskId) reopenTask(taskId, btn);
        });
    }

    async function loadWorkOrders() {
        const params = new URLSearchParams();

        if (refs.statusFilter?.value) params.set('status', refs.statusFilter.value);
        if (refs.typeFilter?.value) params.set('work_order_type', refs.typeFilter.value);
        if (refs.searchInput?.value) params.set('search', refs.searchInput.value);

        params.set('limit', '200');
        params.set('offset', '0');

        if (refs.tableHost && !state.workOrders.length) {
            html(refs.tableHost, render.skeletonTable(6, 8));
        }

        try {
            const response = await api.get(`/api/v1/work-orders?${params.toString()}`);
            const data = response?.data || response || {};

            state.workOrders = safeArray(data.items || []);

            renderSummary(data.summary || {});
            renderWorkOrdersTable(state.workOrders);
        } catch (error) {
            nxToast('error', error?.message || 'Unable to load work orders.');

            if (refs.tableHost) {
                html(refs.tableHost, render.emptyState({
                    title: 'Unable to load work orders',
                    text: error?.message || 'Please refresh the page and try again.',
                    iconClass: 'bi bi-exclamation-triangle'
                }));
            }
        }
    }

    function renderSummary(summary) {
        if (refs.openCount) text(refs.openCount, String(summary.open_count || 0));
        if (refs.assignedCount) text(refs.assignedCount, String(summary.assigned_count || 0));
        if (refs.activeCount) text(refs.activeCount, String(summary.active_count || 0));
        if (refs.completedCount) text(refs.completedCount, String(summary.completed_count || 0));
        if (refs.issueCount) text(refs.issueCount, String(summary.issue_count || 0));
    }

    function renderWorkOrdersTable(items) {
        if (!refs.tableHost) return;

        const rows = safeArray(items);

        if (!rows.length) {
            if (tableInstance) {
                tableInstance.destroy();
                tableInstance = null;
            }

            html(refs.tableHost, render.emptyState({
                title: 'No work orders found',
                text: 'Field jobs will appear here once created from tickets or provisioning.',
                iconClass: 'bi bi-clipboard-check'
            }));

            return;
        }

        html(refs.tableHost, '<div id="workOrdersDataTable"></div>');

        if (tableInstance) {
            tableInstance.destroy();
            tableInstance = null;
        }

        tableInstance = datatable.create({
            el: '#workOrdersDataTable',
            rows,
            search: false,
            paginate: true,
            pager: {
                currentPage: 1,
                rowsPerPage: 20
            },
            sort: {
                key: 'created_at',
                dir: 'desc'
            },
            columns: [
                {
                    key: 'work_order_no',
                    label: 'WO No.',
                    render: (value, row) => `
                        <div class="nx-cell-stack">
                            <div class="nx-cell-title nx-text-mono">${escape(value || '-')}</div>
                            <div class="nx-cell-sub">${escape(formatDateTime(row.created_at || '-'))}</div>
                        </div>
                    `
                },
                {
                    key: 'subscriber_name',
                    label: 'Subscriber',
                    render: (value, row) => `
                        <div class="nx-cell-stack">
                            <div class="nx-cell-title">${escape(value || '-')}</div>
                            <div class="nx-cell-sub">${escape(row.account_number || '-')}</div>
                        </div>
                    `
                },
                {
                    key: 'title',
                    label: 'Title',
                    render: (value, row) => `
                        <div class="nx-cell-stack">
                            <div class="nx-cell-title">${escape(value || '-')}</div>
                            <div class="nx-cell-sub">${escape(formatLabel(row.work_order_type || '-'))}</div>
                        </div>
                    `
                },
                {
                    key: 'priority',
                    label: 'Priority',
                    render: (value) => priorityBadge(value)
                },
                {
                    key: 'status',
                    label: 'Status',
                    render: (value) => statusBadge(value)
                },
                {
                    key: 'assigned_user_name',
                    label: 'Assigned',
                    render: (value, row) => assignedDisplay(value, row)
                },
                {
                    key: 'scheduled_date',
                    label: 'Schedule',
                    render: (value, row) => {
                        const date = value || '-';
                        const time = row.scheduled_time || '';
                        return escape(time ? `${date} ${time}` : date);
                    }
                },
                {
                    key: '__actions',
                    label: 'Actions',
                    render: (_, row) => `
                        <button type="button"
                                class="btn btn-sm btn-outline-primary nx-icon-btn js-work-order-view"
                                data-id="${escape(row.id || '')}"
                                title="View Work Order">
                            <i class="bi bi-eye"></i>
                        </button>
                    `
                }
            ]
        });

        tooltip.refresh(refs.tableHost);
    }

    async function loadWorkOrderDetails(id) {
        showModalLoading();
        openModal();

        try {
            const response = await api.get(`/api/v1/work-orders/show/${id}`);
            const data = response?.data || response || {};

            const workOrder = data.work_order || {};
            const tasks = safeArray(data.tasks || []);
            const logs = safeArray(data.status_logs || []);
            const technicians = safeArray(data.assignable_technicians || []);

            state.selectedWorkOrder = workOrder;
            state.selectedTasks = tasks;
            state.selectedLogs = logs;
            state.assignableTechnicians = technicians;

            renderModal(workOrder, tasks, logs, technicians);
        } catch (error) {
            const message = error?.message || 'Unable to load work order details.';
            showModalError(message);
            nxToast('error', message);
        }
    }

    function openModal() {
        const modalEl = document.getElementById('workOrderDetailsModal');
        if (!modalEl) return;

        if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    }

    function showModalLoading() {
        setText('workOrderModalTitle', 'Work Order Details');
        setText('workOrderModalSubtitle', 'Loading work order details...');

        const statusEl = document.getElementById('workOrderModalStatus');
        const loadingEl = document.getElementById('workOrderModalLoading');
        const contentEl = document.getElementById('workOrderModalContent');

        if (statusEl) statusEl.innerHTML = '';
        if (loadingEl) {
            loadingEl.classList.remove('d-none');
            loadingEl.innerHTML = `
                <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                Loading work order details...
            `;
        }
        if (contentEl) contentEl.classList.add('d-none');
    }

    function showModalError(message) {
        state.selectedWorkOrder = null;
        state.selectedTasks = [];
        state.selectedLogs = [];
        state.assignableTechnicians = [];

        setText('workOrderModalSubtitle', 'Details could not be loaded');
        const loadingEl = document.getElementById('workOrderModalLoading');
        const contentEl = document.getElementById('workOrderModalContent');
        if (contentEl) contentEl.classList.add('d-none');
        if (loadingEl) {
            loadingEl.classList.remove('d-none');
            loadingEl.innerHTML = `
                <div class="alert alert-danger mb-0 text-start" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>${escape(message)}
                </div>
            `;
        }
    }

    function hideModalLoading() {
        document.getElementById('workOrderModalLoading')?.classList.add('d-none');
        document.getElementById('workOrderModalContent')?.classList.remove('d-none');
    }

    function renderModal(workOrder, tasks, logs, technicians) {
        const woNo = workOrder.work_order_no || '-';
        const status = upper(workOrder.status || '');
        const service = workOrder.service_number || workOrder.ppp_username || '-';

        setText('workOrderModalTitle', woNo);
        setText('workOrderModalSubtitle', `Created ${formatDateTime(workOrder.created_at || '-')}`);

        const statusEl = document.getElementById('workOrderModalStatus');
        if (statusEl) statusEl.innerHTML = statusBadge(status);

        setValue('workOrderId', workOrder.id || '');
        setValue('workOrderAssignId', workOrder.id || '');
        setValue('workOrderStatusId', workOrder.id || '');
        setValue('workOrderStatusSelect', status || 'OPEN');
        configureStatusControls(status, workOrder);

        setText('workOrderTitle', workOrder.title || '-');
        setText('workOrderType', formatLabel(workOrder.work_order_type || '-'));
        setHtml('workOrderPriority', priorityBadge(workOrder.priority || '-'));
        setHtml('workOrderAssigned', assignedDisplay(workOrder.assigned_user_name, workOrder));
        setText('workOrderDescription', workOrder.description || workOrder.notes || '-');

        setText('workOrderSubscriberName', workOrder.subscriber_name || '-');
        setText('workOrderSubscriberAccount', workOrder.account_number ? `Account # ${workOrder.account_number}` : 'Account # -');
        setText('workOrderSubscriberContact', workOrder.contact_number || workOrder.subscriber_contact_number || '-');
        setText('workOrderService', service);
        setText('workOrderTicket', workOrder.ticket_no
            ? `${workOrder.ticket_no}${workOrder.ticket_subject ? ` · ${workOrder.ticket_subject}` : ''}`
            : 'Not linked to a ticket');
        setText('workOrderLocation', workOrder.location || workOrder.subscriber_address || '-');

        renderAssignableTechnicians(workOrder.assigned_user_id || 0, technicians);
        renderTasks(tasks, status);
        renderStatusLogs(logs);

        hideModalLoading();
    }

    function renderAssignableTechnicians(selectedUserId, technicians) {
        const select = document.getElementById('workOrderAssignUserSelect');
        if (!select) return;

        const selected = Number(selectedUserId || 0);
        const rows = safeArray(technicians);

        select.innerHTML = `
            <option value="0">Unassigned</option>
            ${rows.map((user) => {
            const id = Number(user.id || 0);
            const name = user.full_name || user.username || `User #${id}`;
            const attendanceRaw = upper(user.attendance_status || 'OFFLINE');
            const attendance = formatLabel(attendanceRaw);
            const activeCount = Number(user.active_work_order_count || 0);
            const eligible = attendanceRaw === 'AVAILABLE' || id === selected;

            return `
                    <option value="${id}" ${id === selected ? 'selected' : ''} ${eligible ? '' : 'disabled'}>
                        ${escape(name)} - ${escape(attendance)} (${activeCount} active)
                    </option>
                `;
        }).join('')}
        `;
    }

    function renderTasks(tasks, workOrderStatus = '') {
        const host = document.getElementById('workOrderTasks');
        const countEl = document.getElementById('workOrderTaskCount');

        if (!host) return;

        const rows = safeArray(tasks);

        if (countEl) {
            countEl.textContent = `${rows.length} task${rows.length === 1 ? '' : 's'}`;
        }

        if (!rows.length) {
            host.innerHTML = `<div class="text-muted text-center py-3">No tasks yet.</div>`;
            return;
        }

        host.innerHTML = rows.map((task) => {
            const taskId = task.id || '';
            const completed = Number(task.is_completed || 0) === 1;
            const terminal = ['COMPLETED', 'CANCELLED'].includes(upper(workOrderStatus));

            return `
                <div class="work-order-list-item">
                    <div class="d-flex justify-content-between gap-3">
                        <div>
                            <div class="fw-semibold">${escape(task.task_name || '-')}</div>
                            <div class="small text-muted">
                                ${Number(task.is_required || 0) === 1 ? 'Required' : 'Optional'}
                                ${completed && task.completed_at ? ` · Completed ${escape(formatDateTime(task.completed_at))}` : ''}
                            </div>
                            ${task.notes ? `<div class="small text-muted mt-1">${escape(task.notes)}</div>` : ''}
                        </div>

                        <div class="text-end">
                            ${
                completed
                    ? '<span class="nx-soft-badge nx-soft-badge-success d-inline-block mb-2">Done</span>'
                    : '<span class="nx-soft-badge nx-soft-badge-warning d-inline-block mb-2">Pending</span>'
            }

                            <div>
                                ${
                completed
                    ? `
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-secondary js-work-order-task-reopen"
                                                    ${terminal ? 'disabled' : ''}
                                                    data-task-id="${escape(taskId)}">
                                                Reopen
                                            </button>
                                        `
                    : `
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-success js-work-order-task-complete"
                                                    ${terminal ? 'disabled' : ''}
                                                    data-task-id="${escape(taskId)}">
                                                Mark Done
                                            </button>
                                        `
            }
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    function renderStatusLogs(logs) {
        const host = document.getElementById('workOrderStatusLogs');
        const countEl = document.getElementById('workOrderLogCount');

        if (!host) return;

        const rows = safeArray(logs);

        if (countEl) {
            countEl.textContent = `${rows.length} log${rows.length === 1 ? '' : 's'}`;
        }

        if (!rows.length) {
            host.innerHTML = `<div class="text-muted text-center py-3">No status logs yet.</div>`;
            return;
        }

        host.innerHTML = rows.map((log) => {
            const user = log.full_name || log.username || 'System';

            return `
                <div class="work-order-list-item">
                    <div class="d-flex justify-content-between gap-3">
                        <div>
                            <div class="fw-semibold">
                                ${escape(formatLabel(log.old_status || '-'))}
                                →
                                ${escape(formatLabel(log.new_status || '-'))}
                            </div>
                            <div class="small text-muted">${escape(user)}</div>
                            ${log.note ? `<div class="small text-muted mt-1">${escape(log.note)}</div>` : ''}
                        </div>

                        <div class="small text-muted text-end">
                            ${escape(formatDateTime(log.created_at || '-'))}
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    async function assignWorkOrder(event) {
        event.preventDefault();

        const form = event.currentTarget;
        const workOrderId = getFormWorkOrderId(form);

        await submitWorkOrderAction({
            form,
            btn: document.getElementById('workOrderAssignSubmitBtn'),
            url: '/api/v1/work-orders/assign',
            loadingText: 'Assigning...',
            successFallback: 'Work order assigned.',
            reloadWorkOrderId: workOrderId,
        });
    }

    async function updateWorkOrderStatus(event) {
        event.preventDefault();

        const form = event.currentTarget;
        const workOrderId = getFormWorkOrderId(form);

        await submitWorkOrderAction({
            form,
            btn: document.getElementById('workOrderStatusSubmitBtn'),
            url: '/api/v1/work-orders/status',
            loadingText: 'Updating...',
            successFallback: 'Work order status updated.',
            reloadWorkOrderId: workOrderId,
        });
    }

    async function quickUpdateStatus(status, btn) {
        const workOrderId = state.selectedWorkOrder?.id || document.getElementById('workOrderId')?.value || '';

        if (!workOrderId) {
            nxToast('warning', 'Unable to determine work order ID.');
            return;
        }

        const formData = new FormData();
        formData.append('work_order_id', workOrderId);
        formData.append('status', status);
        let note = `Quick action: ${formatLabel(status)}.`;
        if (['COMPLETED', 'FAILED', 'CANCELLED'].includes(upper(status))) {
            note = prompt(`Enter a required note for ${formatLabel(status)}:`) || '';
            if (!note.trim()) { nxToast('warning', 'A note is required.'); return; }
        }
        formData.append('note', note);

        setButtonLoading(btn, true, 'Updating...');

        try {
            const response = await apiPost('/api/v1/work-orders/status', formData);
            const data = response?.data || response || {};

            nxToast('success', data.message || 'Work order status updated.');

            await loadWorkOrders();
            await loadWorkOrderDetails(workOrderId);
        } catch (error) {
            nxToast('error', error?.message || 'Unable to update status.');
        } finally {
            setButtonLoading(btn, false);
        }
    }

    function configureStatusControls(currentStatus, workOrder = {}) {
        const transitions = {
            OPEN: ['ASSIGNED', 'IN_PROGRESS', 'CANCELLED', 'FAILED'],
            ASSIGNED: ['OPEN', 'IN_PROGRESS', 'CANCELLED', 'FAILED'],
            IN_PROGRESS: ['ON_SITE', 'COMPLETED', 'CANCELLED', 'FAILED'],
            ON_SITE: ['IN_PROGRESS', 'COMPLETED', 'CANCELLED', 'FAILED'],
            FAILED: ['OPEN', 'ASSIGNED', 'CANCELLED'],
            COMPLETED: [], CANCELLED: []
        };
        const current = upper(currentStatus);
        const allowed = (transitions[current] || []).filter(status => !['IN_PROGRESS','ON_SITE','COMPLETED'].includes(status) || Number(workOrder.assigned_user_id || 0) > 0);
        document.querySelectorAll('#workOrderStatusSelect option').forEach(option => {
            option.disabled = option.value !== current && !allowed.includes(option.value);
        });
        document.querySelectorAll('.work-order-status-shortcut').forEach(button => {
            button.disabled = !allowed.includes(upper(button.dataset.status || ''));
        });
        const submit = document.getElementById('workOrderStatusSubmitBtn');
        if (submit) submit.disabled = allowed.length === 0;
    }

    async function completeTask(taskId, btn) {
        const workOrderId = state.selectedWorkOrder?.id || '';

        const formData = new FormData();
        formData.append('task_id', taskId);

        setButtonLoading(btn, true, 'Saving...');

        try {
            const response = await apiPost('/api/v1/work-orders/tasks/complete', formData);
            const data = response?.data || response || {};

            nxToast('success', data.message || 'Task completed.');

            await loadWorkOrderDetails(data.work_order_id || workOrderId);
            await loadWorkOrders();
        } catch (error) {
            nxToast('error', error?.message || 'Unable to complete task.');
        } finally {
            setButtonLoading(btn, false);
        }
    }

    async function reopenTask(taskId, btn) {
        const workOrderId = state.selectedWorkOrder?.id || '';

        const formData = new FormData();
        formData.append('task_id', taskId);

        setButtonLoading(btn, true, 'Saving...');

        try {
            const response = await apiPost('/api/v1/work-orders/tasks/reopen', formData);
            const data = response?.data || response || {};

            nxToast('success', data.message || 'Task reopened.');

            await loadWorkOrderDetails(data.work_order_id || workOrderId);
            await loadWorkOrders();
        } catch (error) {
            nxToast('error', error?.message || 'Unable to reopen task.');
        } finally {
            setButtonLoading(btn, false);
        }
    }

    async function submitWorkOrderAction({ form, btn, url, loadingText, successFallback, reloadWorkOrderId }) {
        setButtonLoading(btn, true, loadingText);

        try {
            const response = await apiPost(url, new FormData(form));
            const data = response?.data || response || {};

            nxToast('success', data.message || response.message || successFallback);

            await loadWorkOrders();

            if (reloadWorkOrderId) {
                await loadWorkOrderDetails(reloadWorkOrderId);
            }
        } catch (error) {
            nxToast('error', error?.message || 'Action failed.');
        } finally {
            setButtonLoading(btn, false);
        }
    }

    async function apiPost(url, formData) {
        return api.form(url, formData);
    }

    function assignedDisplay(value, row) {
        const assigned = value || row.assigned_username || '';

        if (assigned) {
            return escape(assigned);
        }

        return `
            <span class="nx-soft-badge nx-soft-badge-warning">
                Waiting for Available Technician
            </span>
        `;
    }

    function statusBadge(status) {
        const value = upper(status || '-');

        let cls = 'nx-soft-badge';

        if (value === 'OPEN') cls = 'nx-soft-badge nx-soft-badge-info';
        if (value === 'ASSIGNED') cls = 'nx-soft-badge nx-soft-badge-primary';
        if (value === 'IN_PROGRESS' || value === 'ON_SITE') cls = 'nx-soft-badge nx-soft-badge-warning';
        if (value === 'COMPLETED') cls = 'nx-soft-badge nx-soft-badge-success';
        if (value === 'CANCELLED' || value === 'FAILED') cls = 'nx-soft-badge nx-soft-badge-danger';

        return `<span class="${cls}">${escape(formatLabel(value))}</span>`;
    }

    function priorityBadge(priority) {
        const value = upper(priority || '-');

        let cls = 'nx-soft-badge';

        if (value === 'LOW') cls = 'nx-soft-badge';
        if (value === 'MEDIUM') cls = 'nx-soft-badge nx-soft-badge-info';
        if (value === 'HIGH') cls = 'nx-soft-badge nx-soft-badge-warning';
        if (value === 'URGENT') cls = 'nx-soft-badge nx-soft-badge-danger';

        return `<span class="${cls}">${escape(value)}</span>`;
    }

    function nxToast(icon = 'success', title = '') {
        if (typeof Swal === 'undefined') {
            console.log(`[${icon}] ${title}`);
            return;
        }

        Swal.fire({
            toast: true,
            position: 'top-end',
            icon,
            title,
            showConfirmButton: false,
            timer: icon === 'error' ? 5000 : 3200,
            timerProgressBar: true,
            customClass: {
                popup: 'nx-toast-popup'
            }
        });
    }

    function setButtonLoading(button, isLoading, loadingText = 'Loading...') {
        if (!button) return;

        if (isLoading) {
            button.dataset.originalHtml = button.innerHTML;
            button.disabled = true;
            button.innerHTML = `
                <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                ${escape(loadingText)}
            `;
            return;
        }

        button.disabled = false;

        if (button.dataset.originalHtml) {
            button.innerHTML = button.dataset.originalHtml;
            delete button.dataset.originalHtml;
        }
    }

    function formatLabel(value) {
        return String(value || '-')
            .replaceAll('_', ' ')
            .toLowerCase()
            .replace(/\b\w/g, (char) => char.toUpperCase());
    }

    function formatDateTime(value) {
        if (!value || value === '-') return '-';

        if (util.formatDateTime) {
            return util.formatDateTime(value);
        }

        const date = new Date(String(value).replace(' ', 'T'));

        if (Number.isNaN(date.getTime())) return value;

        return date.toLocaleString('en-PH', {
            year: 'numeric',
            month: 'short',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function debounce(fn, delay = 300) {
        let timer = null;

        return (...args) => {
            clearTimeout(timer);
            timer = setTimeout(() => fn(...args), delay);
        };
    }

    function getFormWorkOrderId(form) {
        return form?.querySelector('[name="work_order_id"]')?.value || '';
    }

    function setText(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    }

    function setHtml(id, value) {
        const el = document.getElementById(id);
        if (el) el.innerHTML = value;
    }

    function setValue(id, value) {
        const el = document.getElementById(id);
        if (el) el.value = value;
    }
});
