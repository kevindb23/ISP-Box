document.addEventListener('DOMContentLoaded', () => {
    const app = document.querySelector('[data-tickets-page="index"]');
    if (!app || !window.NX) return;

    const {
        api,
        dom,
        util,
        datatable,
        render,
        tooltip
    } = window.NX;

    const { $, html, text } = dom;
    const { escape, safeArray, upper } = util;

    let tableInstance = null;

    const state = {
        tickets: [],
        selectedTicket: null,
        selectedMessages: [],
        assignableUsers: [],
    };

    const refs = {
        refreshBtn: null,
        statusFilter: null,
        searchInput: null,
        tableHost: null,

        openCount: null,
        progressCount: null,
        waitingCount: null,
        resolvedCount: null,

        ticketAssignForm: null,
        ticketStatusForm: null,
        ticketPriorityForm: null,
        ticketReplyForm: null,
        ticketInternalNoteForm: null,
        createWorkOrderBtn: null,
        requestScheduleBtn: null,
    };

    cacheDom();
    bindEvents();
    loadTickets();

    function cacheDom() {
        refs.refreshBtn = $('#ticketsRefreshBtn');
        refs.statusFilter = $('#ticketsStatusFilter');
        refs.searchInput = $('#ticketsSearchInput');
        refs.tableHost = $('#ticketsTableHost');

        refs.openCount = $('#ticketsOpenCount');
        refs.progressCount = $('#ticketsProgressCount');
        refs.waitingCount = $('#ticketsWaitingCount');
        refs.resolvedCount = $('#ticketsResolvedCount');

        refs.ticketAssignForm = $('#ticketAssignForm');
        refs.ticketStatusForm = $('#ticketStatusForm');
        refs.ticketPriorityForm = $('#ticketPriorityForm');
        refs.ticketReplyForm = $('#ticketReplyForm');
        refs.ticketInternalNoteForm = $('#ticketInternalNoteForm');
        refs.createWorkOrderBtn = $('#ticketCreateWorkOrderBtn');
        refs.requestScheduleBtn = $('#ticketRequestScheduleBtn');
    }

    function bindEvents() {
        refs.refreshBtn?.addEventListener('click', async () => {
            await loadTickets();
            nxToast('success', 'Tickets refreshed.');
        });

        refs.statusFilter?.addEventListener('change', loadTickets);
        refs.searchInput?.addEventListener('input', debounce(loadTickets, 350));

        refs.ticketAssignForm?.addEventListener('submit', assignTicket);
        refs.ticketStatusForm?.addEventListener('submit', updateTicketStatus);
        refs.ticketPriorityForm?.addEventListener('submit', updateTicketPriority);
        refs.ticketReplyForm?.addEventListener('submit', sendTicketReply);
        refs.ticketInternalNoteForm?.addEventListener('submit', addInternalNote);
        refs.createWorkOrderBtn?.addEventListener('click', createWorkOrderFromTicket);
        refs.requestScheduleBtn?.addEventListener('click', requestScheduleFromSubscriber);

        dom.on(refs.tableHost, 'click', '.js-ticket-view', (_e, btn) => {
            const ticketId = btn.dataset.id;
            if (ticketId) {
                loadTicketDetails(ticketId);
            }
        });
    }

    async function loadTickets() {
        const status = refs.statusFilter?.value || '';
        const search = refs.searchInput?.value || '';

        const params = new URLSearchParams();

        if (status) params.set('status', status);
        if (search) params.set('search', search);

        params.set('limit', '200');
        params.set('offset', '0');

        if (refs.tableHost && !state.tickets.length) {
            html(refs.tableHost, render.skeletonTable(6, 8));
        }

        try {
            const response = await api.get(`/api/v1/tickets?${params.toString()}`);
            const data = response?.data || response || {};

            state.tickets = safeArray(data.items || []);
            renderSummary(data.summary || {});
            renderTicketsTable(state.tickets);
        } catch (error) {
            console.error('[Tickets] Load failed', error);
            nxToast('error', error?.message || 'Unable to load tickets.');

            if (refs.tableHost) {
                html(refs.tableHost, render.emptyState({
                    title: 'Unable to load tickets',
                    text: error?.message || 'Please refresh the page and try again.',
                    iconClass: 'bi bi-exclamation-triangle'
                }));
            }
        }
    }

    function renderSummary(summary) {
        if (refs.openCount) text(refs.openCount, String(summary.open_count || 0));
        if (refs.progressCount) text(refs.progressCount, String(summary.in_progress_count || 0));
        if (refs.waitingCount) text(refs.waitingCount, String(summary.waiting_count || 0));
        if (refs.resolvedCount) text(refs.resolvedCount, String(summary.resolved_count || 0));
    }

    function renderTicketsTable(items) {
        if (!refs.tableHost) return;

        const rows = safeArray(items);

        if (!rows.length) {
            if (tableInstance) {
                tableInstance.destroy();
                tableInstance = null;
            }

            html(refs.tableHost, render.emptyState({
                title: 'No tickets found',
                text: 'Subscriber concerns will appear here once submitted.',
                iconClass: 'bi bi-ticket-detailed'
            }));

            return;
        }

        html(refs.tableHost, '<div id="ticketsDataTable"></div>');

        if (tableInstance) {
            tableInstance.destroy();
            tableInstance = null;
        }

        const columns = [
            {
                key: 'ticket_no',
                label: 'Ticket No.',
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
                key: 'subject',
                label: 'Subject',
                render: (value, row) => {
                    const schedule = formatPreferredSchedule(row);

                    return `
                        <div class="nx-cell-stack">
                            <div class="nx-cell-title">${escape(value || '-')}</div>
                            <div class="nx-cell-sub">${escape(formatLabel(row.category || '-'))}</div>
                            ${schedule ? `<div class="nx-cell-sub"><i class="bi bi-calendar-event"></i> ${escape(schedule)}</div>` : ''}
                        </div>
                    `;
                }
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
                key: '__actions',
                label: 'Actions',
                render: (_, row) => `
                    <button type="button"
                            class="btn btn-sm btn-outline-primary nx-icon-btn js-ticket-view"
                            data-id="${escape(row.id || '')}"
                            title="View Ticket">
                        <i class="bi bi-eye"></i>
                    </button>
                `
            }
        ];

        tableInstance = datatable.create({
            el: '#ticketsDataTable',
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
            columns
        });

        tooltip.refresh(refs.tableHost);
    }

    function assignedDisplay(value, row) {
        const assigned = value || row.assigned_username || '';

        if (assigned) {
            return escape(assigned);
        }

        return `
            <span class="nx-soft-badge nx-soft-badge-warning">
                Waiting for Available Personnel
            </span>
        `;
    }

    async function loadTicketDetails(ticketId) {
        if (!ticketId) {
            nxToast('warning', 'Unable to determine ticket ID.');
            return;
        }

        showTicketModalLoading();
        openTicketModal();

        try {
            const response = await api.get(`/api/v1/tickets/show/${ticketId}`);
            const data = response?.data || response || {};

            const ticket = data.ticket || {};
            const messages = safeArray(data.messages || []);

            state.selectedTicket = ticket;
            state.selectedMessages = messages;
            state.assignableUsers = safeArray(data.assignable_users || []);

            renderTicketModal(ticket, messages);
        } catch (error) {
            const message = error?.message || 'Unable to load ticket details.';
            showTicketModalError(message);
            nxToast('error', message);
        }
    }

    function openTicketModal() {
        const modalEl = document.getElementById('ticketDetailsModal');

        if (!modalEl) return;

        if (window.bootstrap && bootstrap.Modal) {
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        }
    }

    function showTicketModalLoading() {
        setText('ticketModalTitle', 'Ticket Details');
        setText('ticketModalSubtitle', 'Loading ticket details...');

        const statusEl = document.getElementById('ticketModalStatus');
        const loadingEl = document.getElementById('ticketModalLoading');
        const contentEl = document.getElementById('ticketModalContent');

        if (statusEl) statusEl.innerHTML = '';
        if (loadingEl) {
            loadingEl.classList.remove('d-none');
            loadingEl.innerHTML = `
                <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                Loading ticket details...
            `;
        }
        if (contentEl) contentEl.classList.add('d-none');

        if (refs.createWorkOrderBtn) {
            refs.createWorkOrderBtn.disabled = true;
            refs.createWorkOrderBtn.dataset.ticketId = '';
        }

        if (refs.requestScheduleBtn) {
            refs.requestScheduleBtn.disabled = true;
            refs.requestScheduleBtn.dataset.ticketId = '';
            refs.requestScheduleBtn.classList.add('d-none');
        }
    }

    function showTicketModalError(message) {
        state.selectedTicket = null;
        state.selectedMessages = [];
        state.assignableUsers = [];

        setText('ticketModalSubtitle', 'Details could not be loaded');
        const loadingEl = document.getElementById('ticketModalLoading');
        const contentEl = document.getElementById('ticketModalContent');
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

    function hideTicketModalLoading() {
        const loadingEl = document.getElementById('ticketModalLoading');
        const contentEl = document.getElementById('ticketModalContent');

        if (loadingEl) loadingEl.classList.add('d-none');
        if (contentEl) contentEl.classList.remove('d-none');
    }

    function renderTicketModal(ticket, messages) {
        const ticketNo = ticket.ticket_no || '-';
        const status = upper(ticket.status || '');
        const priority = upper(ticket.priority || '');
        const category = upper(ticket.category || '');
        const service = ticket.service_number || ticket.ppp_username || '-';
        const schedule = formatPreferredSchedule(ticket);

        setText('ticketModalTitle', ticketNo);
        setText('ticketModalSubtitle', `Created ${formatDateTime(ticket.created_at || '-')}`);

        const statusEl = document.getElementById('ticketModalStatus');
        if (statusEl) statusEl.innerHTML = statusBadge(status);

        setText('ticketSubject', ticket.subject || '-');
        setText('ticketCategory', formatLabel(ticket.category || '-'));
        setHtml('ticketAssigned', assignedDisplay(ticket.assigned_user_name, ticket));
        setText('ticketDescription', ticket.description || '-');

        setText('ticketSubscriberName', ticket.subscriber_name || '-');
        setText('ticketSubscriberAccount', ticket.account_number ? `Account # ${ticket.account_number}` : 'Account # -');
        setText('ticketSubscriberEmail', ticket.email || '-');
        setText('ticketSubscriberContact', ticket.contact_number || '-');
        setText('ticketService', service);

        setText('ticketPreferredVisit', schedule || '-');

        const priorityEl = document.getElementById('ticketPriority');
        if (priorityEl) priorityEl.innerHTML = priorityBadge(priority);

        setValue('ticketAssignTicketId', ticket.id || '');
        setValue('ticketStatusTicketId', ticket.id || '');
        setValue('ticketPriorityTicketId', ticket.id || '');
        setValue('ticketReplyTicketId', ticket.id || '');
        setValue('ticketInternalNoteTicketId', ticket.id || '');

        if (refs.createWorkOrderBtn) {
            const canCreateWorkOrder = canShowCreateWorkOrder(ticket);

            refs.createWorkOrderBtn.disabled = !canCreateWorkOrder;
            refs.createWorkOrderBtn.dataset.ticketId = ticket.id || '';

            if (canCreateWorkOrder) {
                refs.createWorkOrderBtn.classList.remove('d-none');
            } else {
                refs.createWorkOrderBtn.classList.add('d-none');
            }
        }

        if (refs.requestScheduleBtn) {
            const canRequestSchedule = canShowRequestSchedule(ticket);

            refs.requestScheduleBtn.disabled = !canRequestSchedule;
            refs.requestScheduleBtn.dataset.ticketId = ticket.id || '';

            if (canRequestSchedule) {
                refs.requestScheduleBtn.classList.remove('d-none');
            } else {
                refs.requestScheduleBtn.classList.add('d-none');
            }
        }

        setValue('ticketStatusSelect', status || 'OPEN');
        setValue('ticketPrioritySelect', priority || 'MEDIUM');

        renderAssignableUsers(ticket.assigned_user_id || 0, state.assignableUsers);
        renderTicketMessages(messages);
        resetTicketForms();

        hideTicketModalLoading();
    }

    function canShowRequestSchedule(ticket) {
        const category = upper(ticket.category || '');
        const status = upper(ticket.status || '');

        if (category !== 'INTERNET') {
            return false;
        }

        if (ticket.preferred_visit_date || ticket.preferred_visit_time) {
            return false;
        }

        return ['OPEN', 'IN_PROGRESS', 'WAITING_CUSTOMER', 'WAITING_TECHNICIAN'].includes(status);
    }

    function canShowCreateWorkOrder(ticket) {
        const status = upper(ticket.status || '');

        if (!ticket.preferred_visit_date || !ticket.preferred_visit_time) {
            return false;
        }

        if (ticket.work_order_id) {
            return false;
        }

        return ['VISIT_SCHEDULED', 'WAITING_TECHNICIAN', 'IN_PROGRESS', 'OPEN'].includes(status);
    }

    function renderAssignableUsers(selectedUserId, users) {
        const select = document.getElementById('ticketAssignUserSelect');
        if (!select) return;

        const selected = Number(selectedUserId || 0);
        const rows = safeArray(users);

        select.innerHTML = `
            <option value="0">Unassigned</option>
            ${rows.map((user) => {
            const id = Number(user.id || 0);
            const name = user.full_name || user.username || `User #${id}`;
            const role = user.role || '';

            return `
                    <option value="${id}" ${id === selected ? 'selected' : ''}>
                        ${escape(name)}${role ? ` (${escape(role)})` : ''}
                    </option>
                `;
        }).join('')}
        `;
    }

    function renderTicketMessages(messages) {
        const host = document.getElementById('ticketMessages');
        const countEl = document.getElementById('ticketMessageCount');

        if (!host) return;

        const rows = safeArray(messages);

        if (countEl) {
            countEl.textContent = `${rows.length} message${rows.length === 1 ? '' : 's'}`;
        }

        if (!rows.length) {
            host.innerHTML = `<div class="text-muted text-center py-3">No messages yet.</div>`;
            return;
        }

        host.innerHTML = rows.map((item) => {
            const senderType = upper(item.sender_type || 'SYSTEM');
            const isInternal = Number(item.is_internal || 0) === 1;
            const senderName = item.full_name || item.username || senderType;

            return `
                <div class="ticket-message ${isInternal ? 'ticket-message-internal' : ''}">
                    <div class="d-flex justify-content-between gap-3 mb-1">
                        <div class="fw-semibold">
                            ${escape(senderName)}
                            ${isInternal ? '<span class="badge bg-dark ms-1">Internal</span>' : ''}
                        </div>
                        <div class="small text-muted">${escape(formatDateTime(item.created_at || '-'))}</div>
                    </div>
                    <div class="text-muted">${escape(item.message || '-')}</div>
                </div>
            `;
        }).join('');
    }

    function resetTicketForms() {
        resetFormKeepTicketId('ticketReplyForm', 'ticketReplyTicketId');
        resetFormKeepTicketId('ticketInternalNoteForm', 'ticketInternalNoteTicketId');
    }

    function resetFormKeepTicketId(formId, inputId) {
        const form = document.getElementById(formId);
        const ticketId = document.getElementById(inputId)?.value || '';

        if (form) {
            form.reset();
            setValue(inputId, ticketId);
        }
    }

    async function assignTicket(event) {
        event.preventDefault();

        await submitTicketAction({
            form: event.currentTarget,
            btn: document.getElementById('ticketAssignSubmitBtn'),
            url: '/api/v1/tickets/assign',
            loadingText: 'Assigning...',
            successFallback: 'Ticket assigned successfully.',
            reloadTicketId: getFormTicketId(event.currentTarget),
        });
    }

    async function updateTicketStatus(event) {
        event.preventDefault();

        await submitTicketAction({
            form: event.currentTarget,
            btn: document.getElementById('ticketStatusSubmitBtn'),
            url: '/api/v1/tickets/status',
            loadingText: 'Updating...',
            successFallback: 'Ticket status updated.',
            reloadTicketId: getFormTicketId(event.currentTarget),
        });
    }

    async function updateTicketPriority(event) {
        event.preventDefault();

        await submitTicketAction({
            form: event.currentTarget,
            btn: document.getElementById('ticketPrioritySubmitBtn'),
            url: '/api/v1/tickets/priority',
            loadingText: 'Updating...',
            successFallback: 'Ticket priority updated.',
            reloadTicketId: getFormTicketId(event.currentTarget),
        });
    }

    async function sendTicketReply(event) {
        event.preventDefault();

        const form = event.currentTarget;
        const message = String(form.querySelector('[name="message"]')?.value || '').trim();

        if (!message) {
            nxToast('warning', 'Please type a public reply.');
            return;
        }

        await submitTicketAction({
            form,
            btn: document.getElementById('ticketReplySubmitBtn'),
            url: '/api/v1/tickets/reply',
            loadingText: 'Sending...',
            successFallback: 'Reply sent successfully.',
            reloadTicketId: getFormTicketId(form),
        });
    }

    async function addInternalNote(event) {
        event.preventDefault();

        const form = event.currentTarget;
        const message = String(form.querySelector('[name="message"]')?.value || '').trim();

        if (!message) {
            nxToast('warning', 'Please type an internal note.');
            return;
        }

        await submitTicketAction({
            form,
            btn: document.getElementById('ticketInternalNoteSubmitBtn'),
            url: '/api/v1/tickets/internal-note',
            loadingText: 'Adding...',
            successFallback: 'Internal note added.',
            reloadTicketId: getFormTicketId(form),
        });
    }

    async function requestScheduleFromSubscriber() {
        const ticketId = refs.requestScheduleBtn?.dataset.ticketId || state.selectedTicket?.id || '';

        if (!ticketId) {
            nxToast('warning', 'Unable to determine ticket ID.');
            return;
        }

        const confirmed = await nxConfirm({
            title: 'Request subscriber schedule?',
            text: 'This will ask the subscriber to choose an available technical visit schedule from their portal.',
            confirmButtonText: 'Request Schedule',
        });

        if (!confirmed) {
            return;
        }

        const formData = new FormData();
        formData.append('ticket_id', ticketId);

        setButtonLoading(refs.requestScheduleBtn, true, 'Requesting...');

        try {
            const response = await apiPost('/api/v1/tickets/request-schedule', formData);
            const data = response?.data || response || {};

            nxToast('success', data.message || 'Schedule request sent to subscriber.');

            await loadTickets();
            await loadTicketDetails(ticketId);
        } catch (error) {
            nxToast('error', error?.message || 'Unable to request schedule.');
        } finally {
            setButtonLoading(refs.requestScheduleBtn, false);
        }
    }

    async function createWorkOrderFromTicket() {
        const ticketId = refs.createWorkOrderBtn?.dataset.ticketId || state.selectedTicket?.id || '';

        if (!ticketId) {
            nxToast('warning', 'Unable to determine ticket ID.');
            return;
        }

        const formData = new FormData();
        formData.append('ticket_id', ticketId);

        setButtonLoading(refs.createWorkOrderBtn, true, 'Creating...');

        try {
            const response = await apiPost('/api/v1/work-orders/create-from-ticket', formData);
            const data = response?.data || response || {};

            nxToast(
                'success',
                data.work_order_no
                    ? `Work Order ${data.work_order_no} created.`
                    : (data.message || 'Work order created.')
            );

            await loadTickets();
            await loadTicketDetails(ticketId);
        } catch (error) {
            nxToast('error', error?.message || 'Unable to create work order.');
        } finally {
            setButtonLoading(refs.createWorkOrderBtn, false);
        }
    }

    async function submitTicketAction({ form, btn, url, loadingText, successFallback, reloadTicketId }) {
        setButtonLoading(btn, true, loadingText);

        try {
            const response = await apiPost(url, new FormData(form));
            const data = response?.data || response || {};

            nxToast('success', data.message || response.message || successFallback);

            await loadTickets();

            if (reloadTicketId) {
                await loadTicketDetails(reloadTicketId);
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

    function statusBadge(status) {
        const value = upper(status || '-');

        let cls = 'nx-soft-badge';

        if (value === 'OPEN') cls = 'nx-soft-badge nx-soft-badge-info';
        if (value === 'IN_PROGRESS') cls = 'nx-soft-badge nx-soft-badge-primary';
        if (value === 'WAITING_CUSTOMER' || value === 'WAITING_TECHNICIAN') cls = 'nx-soft-badge nx-soft-badge-warning';
        if (value === 'WAITING_CUSTOMER_SCHEDULE') cls = 'nx-soft-badge nx-soft-badge-warning';
        if (value === 'VISIT_SCHEDULED') cls = 'nx-soft-badge nx-soft-badge-primary';
        if (value === 'RESOLVED') cls = 'nx-soft-badge nx-soft-badge-success';
        if (value === 'CLOSED') cls = 'nx-soft-badge';
        if (value === 'CANCELLED') cls = 'nx-soft-badge nx-soft-badge-danger';

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

    async function nxConfirm({
                                 title = 'Are you sure?',
                                 text = '',
                                 confirmButtonText = 'Confirm',
                             } = {}) {
        if (typeof Swal === 'undefined') {
            return window.confirm(text || title);
        }

        const result = await Swal.fire({
            title,
            text,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText,
            cancelButtonText: 'Cancel',
            reverseButtons: true,
        });

        return result.isConfirmed === true;
    }

    function nxToast(icon = 'success', title = '') {
        if (typeof Swal === 'undefined') {
            console.log(`[${icon}] ${title}`);
            return;
        }

        let timer = 3200;
        if (icon === 'error') timer = 5000;
        if (String(title || '').length > 90) timer = 5400;

        Swal.fire({
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

    function formatPreferredSchedule(ticket) {
        const date = ticket?.preferred_visit_date || ticket?.scheduled_date || '';
        const time = ticket?.preferred_visit_time || ticket?.scheduled_time || '';

        if (!date && !time) return '';

        return `${date || '-'} ${time ? formatTimeLabel(time) : ''}`.trim();
    }

    function formatTimeLabel(value) {
        if (!value) return '-';

        const parts = String(value).split(':');
        if (parts.length < 2) return value;

        const date = new Date();
        date.setHours(Number(parts[0]), Number(parts[1]), 0, 0);

        return date.toLocaleTimeString('en-PH', {
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function formatLabel(value) {
        return String(value || '-')
            .replaceAll('_', ' ')
            .toLowerCase()
            .replace(/\b\w/g, (char) => char.toUpperCase());
    }

    function formatDateTime(value) {
        if (!value || value === '-') return '-';

        const date = new Date(String(value).replace(' ', 'T'));

        if (Number.isNaN(date.getTime())) {
            return value;
        }

        if (util.formatDateTime) {
            return util.formatDateTime(value);
        }

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

    function getFormTicketId(form) {
        return form?.querySelector('[name="ticket_id"]')?.value || '';
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
