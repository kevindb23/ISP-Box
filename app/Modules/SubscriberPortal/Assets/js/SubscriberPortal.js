(function () {
    const state = {
        page: 'legacy',
        invoices: [],
        payments: [],
        services: [],
        tickets: [],
        invoiceTotal: 0,
        paymentTotal: 0,
        ticketTotal: 0,
        selectedInvoice: null,
        selectedInvoiceItems: [],
        selectedInvoicePayments: [],
        selectedTicket: null,
        selectedTicketMessages: [],
        visitSlots: [],
        visitSlotsLoading: false,

        invoicePager: {
            currentPage: 1,
            rowsPerPage: 5,
        },

        paymentPager: {
            currentPage: 1,
            rowsPerPage: 5,
        },

        ticketPager: {
            currentPage: 1,
            rowsPerPage: 5,
        },
    };

    document.addEventListener('DOMContentLoaded', () => {
        state.page = getCurrentPage();

        bindEvents();
        verifyPayMongoReturnIfNeeded();
        loadCurrentPage();
    });

    function getCurrentPage() {
        const pageEl = document.querySelector('[data-sp-page]');
        return pageEl ? pageEl.getAttribute('data-sp-page') : 'legacy';
    }

    function bindEvents() {
        const refreshBtn = document.getElementById('subscriberPortalRefreshBtn');
        const statusFilter = document.getElementById('spInvoiceStatusFilter');
        const invoiceSearchInput = document.getElementById('spInvoiceSearch');
        const paymentSearchInput = document.getElementById('spPaymentSearch');
        const printBtn = document.getElementById('spPrintInvoiceBtn');
        const payNowBtn = document.getElementById('spPayNowBtn');
        const manualPaymentForm = document.getElementById('spManualPaymentForm');
        const changePasswordForm = document.getElementById('spChangePasswordForm');
        const raiseConcernForm = document.getElementById('spRaiseConcernForm');
        const ticketReplyForm = document.getElementById('spTicketReplyForm');
        const scheduleVisitForm = document.getElementById('spTicketScheduleVisitForm');
        const scheduleVisitDate = document.getElementById('spScheduleVisitDate');

        if (scheduleVisitForm) {
            scheduleVisitForm.addEventListener('submit', submitTicketVisitSchedule);
        }

        if (scheduleVisitDate) {
            scheduleVisitDate.setAttribute('min', new Date().toISOString().slice(0, 10));
            scheduleVisitDate.addEventListener('change', loadScheduleVisitSlots);
        }

        if (refreshBtn) {
            refreshBtn.addEventListener('click', loadCurrentPage);
        }

        if (statusFilter) {
            statusFilter.addEventListener('change', () => {
                state.invoicePager.currentPage = 1;
                loadInvoicesPage();
            });
        }

        if (invoiceSearchInput) {
            let timer = null;

            invoiceSearchInput.addEventListener('input', () => {
                clearTimeout(timer);
                timer = setTimeout(() => {
                    state.invoicePager.currentPage = 1;
                    loadInvoicesPage();
                }, 350);
            });
        }

        if (paymentSearchInput) {
            let timer = null;

            paymentSearchInput.addEventListener('input', () => {
                clearTimeout(timer);
                timer = setTimeout(() => {
                    state.paymentPager.currentPage = 1;
                    loadPaymentsPage();
                }, 350);
            });
        }

        if (printBtn) {
            printBtn.addEventListener('click', printSelectedInvoice);
        }

        if (payNowBtn) {
            payNowBtn.addEventListener('click', paySelectedInvoice);
        }
        if (manualPaymentForm) manualPaymentForm.addEventListener('submit', submitManualPayment);

        if (changePasswordForm) {
            changePasswordForm.addEventListener('submit', changePortalPassword);
        }

        if (raiseConcernForm) {
            raiseConcernForm.addEventListener('submit', submitRaiseConcern);
        }

        if (ticketReplyForm) {
            ticketReplyForm.addEventListener('submit', submitTicketReply);
        }
    }

    async function loadCurrentPage() {
        clearAlert();

        try {
            if (state.page === 'account') {
                await loadAccountPage();
                return;
            }

            if (state.page === 'services') {
                await loadServicesPage();
                return;
            }

            if (state.page === 'invoices') {
                await loadInvoicesPageWithSummary();
                return;
            }

            if (state.page === 'payments') {
                await loadPaymentsPage();
                return;
            }

            if (state.page === 'tickets') {
                await loadTicketsPage();
                return;
            }

            await loadLegacyPage();
        } catch (error) {
            showAlert(error.message || 'Unable to load subscriber portal data.', 'danger');
        }
    }

    async function loadLegacyPage() {
        await Promise.all([
            loadDashboard(),
            loadInvoicesPage(),
            loadPaymentsPage(),
        ]);
    }

    async function loadAccountPage() {
        await loadDashboard();
    }

    async function loadServicesPage() {
        const [response, dashboardResponse] = await Promise.all([
            apiGet('/api/v1/subscriber-portal/services'),
            apiGet('/api/v1/subscriber-portal/summary'),
        ]);
        const data = response.data || response;
        const dashboard = dashboardResponse.data || dashboardResponse;
        const overview = dashboard.overview || {};
        const serviceSummary = overview.service_summary || {};
        const services = data.items || [];

        state.services = services;

        renderServicesSummary(serviceSummary, services);
        renderServiceCards(services);
    }

    async function loadInvoicesPageWithSummary() {
        await Promise.all([
            loadDashboardSummaryForInvoices(),
            loadInvoicesPage(),
        ]);
    }

    async function loadDashboardSummaryForInvoices() {
        const response = await apiGet('/api/v1/subscriber-portal/summary');
        const data = response.data || response;

        const overview = data.overview || {};
        const summary = overview.summary || {};

        setText('spInvoiceOpenBalance', money(summary.total_balance || 0));
        setText('spInvoiceOverdueBalance', money(summary.overdue_balance || 0));
        setText('spInvoiceCount', summary.invoice_count || 0);
    }

    async function loadDashboard() {
        const response = await apiGet('/api/v1/subscriber-portal/summary');
        const data = response.data || response;

        const profile = data.profile || {};
        const overview = data.overview || {};
        const summary = overview.summary || {};
        const serviceSummary = overview.service_summary || {};
        const nextDueInvoice = overview.next_due_invoice || null;
        const latestInvoice = overview.latest_invoice || null;

        renderAccountStatusBanner(profile, summary, serviceSummary, nextDueInvoice);
        renderLatestInvoiceBox(latestInvoice, nextDueInvoice);

        setText('spProfileName', profile.full_name || '-');
        setText('spAccountNumber', profile.account_number ? `Account # ${profile.account_number}` : 'Account # -');
        setText('spEmail', profile.email || '-');
        setText('spContactNumber', profile.contact_number || '-');
        setText('spAddress', profile.address || '-');

        setText('spTotalBalance', money(summary.total_balance || 0));
        setText('spOverdueBalance', money(summary.overdue_balance || 0));
        setText('spActiveServices', serviceSummary.active_service_count || 0);

        setText(
            'spNextDueDate',
            nextDueInvoice?.due_date || serviceSummary.nearest_next_due_date || '-'
        );

        state.services = data.services || [];

        if (document.getElementById('spServicesBody')) {
            renderServicesTable(state.services);
        }
    }

    async function loadInvoicesPage() {
        const status = document.getElementById('spInvoiceStatusFilter')?.value || '';
        const search = document.getElementById('spInvoiceSearch')?.value || '';

        const params = new URLSearchParams();

        if (status) params.set('status', status);
        if (search) params.set('search', search);

        params.set('limit', String(state.invoicePager.rowsPerPage));
        params.set('offset', String((state.invoicePager.currentPage - 1) * state.invoicePager.rowsPerPage));

        const response = await apiGet(`/api/v1/subscriber-portal/invoices?${params.toString()}`);
        const data = response.data || response;

        state.invoices = data.items || [];
        state.invoiceTotal = Number(data.total || 0);
        renderInvoices(state.invoices);
    }

    async function loadPaymentsPage() {
        const search = document.getElementById('spPaymentSearch')?.value || '';
        const params = new URLSearchParams();

        if (search) params.set('search', search);

        params.set('limit', String(state.paymentPager.rowsPerPage));
        params.set('offset', String((state.paymentPager.currentPage - 1) * state.paymentPager.rowsPerPage));

        const response = await apiGet(`/api/v1/subscriber-portal/payments?${params.toString()}`);
        const data = response.data || response;

        state.payments = data.items || [];
        state.paymentTotal = Number(data.total || 0);

        renderPayments(state.payments);
        renderPaymentsSummary(data.summary || {});
    }

    async function loadTicketsPage() {
        const params = new URLSearchParams();
        params.set('limit', String(state.ticketPager.rowsPerPage));
        params.set('offset', String((state.ticketPager.currentPage - 1) * state.ticketPager.rowsPerPage));

        const [response, servicesResponse] = await Promise.all([
            apiGet(`/api/v1/subscriber-portal/tickets?${params.toString()}`),
            state.services.length ? Promise.resolve(null) : apiGet('/api/v1/subscriber-portal/services'),
        ]);
        const data = response.data || response;

        state.tickets = data.items || [];
        state.ticketTotal = Number(data.total || 0);
        if (servicesResponse) {
            const servicesData = servicesResponse.data || servicesResponse;
            state.services = servicesData.items || [];
        }
        populateTicketServiceSelect();

        renderTickets(state.tickets);
    }

    async function submitRaiseConcern(event) {
        event.preventDefault();

        const form = event.currentTarget;
        const submitBtn = document.getElementById('spRaiseConcernSubmitBtn');

        const subject = String(form.querySelector('[name="subject"]')?.value || '').trim();
        const description = String(form.querySelector('[name="description"]')?.value || '').trim();

        if (!subject || !description) {
            showToast('warning', 'Please complete the concern details.');
            return;
        }

        setButtonLoading(submitBtn, true, 'Submitting...');

        try {
            const formData = new FormData(form);

            formData.delete('preferred_visit_date');
            formData.delete('preferred_visit_time');
            formData.delete('preferred_visit_notes');

            const response = await apiPost('/api/v1/subscriber-portal/tickets', formData);
            const data = response.data || response;

            const ticketNo = data.ticket_no || data?.data?.ticket_no || '';

            form.reset();
            closeBootstrapModalAndCleanup('spRaiseConcernModal');

            showToast('success', ticketNo
                ? `Ticket ${ticketNo} created successfully.`
                : 'Concern submitted successfully.'
            );

            if (state.page === 'tickets') {
                state.ticketPager.currentPage = 1;
                await loadTicketsPage();
            }
        } catch (error) {
            showToast('error', error.message || 'Unable to submit concern.');
        } finally {
            setButtonLoading(submitBtn, false);
        }
    }

    function populateTicketServiceSelect() {
        const select = document.getElementById('spTicketServiceId');
        if (!select) return;
        const selected = select.value;
        select.innerHTML = '<option value="">General account concern</option>' + state.services.map((service) => {
            const label = [service.service_number || `Service ${service.id}`, service.plan_name, service.ppp_username]
                .filter(Boolean).join(' · ');
            return `<option value="${escapeHtml(service.id)}">${escapeHtml(label)}</option>`;
        }).join('');
        if ([...select.options].some((option) => option.value === selected)) select.value = selected;
    }

    function renderTickets(items) {
        const body = document.getElementById('spTicketsBody');
        const pager = document.getElementById('spTicketsPager');

        if (!body) return;

        const rows = Array.isArray(items) ? items : [];
        const pagedRows = rows;

        if (!rows.length) {
            body.innerHTML = `<tr><td colspan="6" class="text-muted text-center py-4">No tickets found.</td></tr>`;
            if (pager) pager.innerHTML = '';
            return;
        }

        body.innerHTML = pagedRows.map((item) => {
            const schedule = formatPreferredSchedule(item);

            return `
            <tr class="sp-clickable-row" data-ticket-id="${escapeHtml(item.id)}">
                <td class="ps-4 fw-semibold">${escapeHtml(item.ticket_no || '-')}</td>
                <td>
                    <div class="fw-semibold">${escapeHtml(item.subject || '-')}</div>
                    ${schedule ? `<div class="small text-muted"><i class="bi bi-calendar-event"></i> ${escapeHtml(schedule)}</div>` : ''}
                </td>
                <td>${escapeHtml(formatTicketCategory(item.category || '-'))}</td>
                <td>${priorityBadge(item.priority)}</td>
                <td>${statusBadge(item.status)}</td>
                <td class="text-end pe-4">${escapeHtml(formatDateTime(item.created_at || '-'))}</td>
            </tr>
        `;
        }).join('');

        body.querySelectorAll('[data-ticket-id]').forEach((row) => {
            row.addEventListener('click', () => {
                loadTicketDetails(row.getAttribute('data-ticket-id'));
            });
        });

        renderPager({
            el: pager,
            totalRows: state.ticketTotal,
            currentPage: state.ticketPager.currentPage,
            rowsPerPage: state.ticketPager.rowsPerPage,
            onPageChange: (page) => {
                state.ticketPager.currentPage = page;
                loadTicketsPage();
            },
        });
    }

    async function loadTicketDetails(ticketId) {
        if (!ticketId) {
            showToast('warning', 'Unable to determine ticket ID.');
            return;
        }

        clearAlert();
        showTicketModalLoading();
        openTicketModal();

        try {
            const response = await apiGet(`/api/v1/subscriber-portal/tickets/show/${ticketId}`);
            const data = response.data || response;

            const ticket = data.ticket || {};
            const messages = data.messages || [];

            state.selectedTicket = ticket;
            state.selectedTicketMessages = messages;

            renderTicketModal(ticket, messages);
        } catch (error) {
            hideTicketModalLoading();
            showToast('error', error.message || 'Unable to load ticket details.');
        }
    }

    function openTicketModal() {
        const modalEl = document.getElementById('spTicketDetailsModal');

        if (!modalEl) return;

        if (window.bootstrap && bootstrap.Modal) {
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
            return;
        }

        modalEl.classList.add('show');
        modalEl.style.display = 'block';
        modalEl.removeAttribute('aria-hidden');
    }

    function showTicketModalLoading() {
        setText('spTicketModalTitle', 'Ticket Details');
        setText('spTicketModalSubtitle', 'Loading ticket details...');

        const statusEl = document.getElementById('spTicketModalStatus');
        const loadingEl = document.getElementById('spTicketModalLoading');
        const contentEl = document.getElementById('spTicketModalContent');

        if (statusEl) statusEl.innerHTML = '';
        if (loadingEl) loadingEl.classList.remove('d-none');
        if (contentEl) contentEl.classList.add('d-none');
    }

    function hideTicketModalLoading() {
        const loadingEl = document.getElementById('spTicketModalLoading');
        const contentEl = document.getElementById('spTicketModalContent');

        if (loadingEl) loadingEl.classList.add('d-none');
        if (contentEl) contentEl.classList.remove('d-none');
    }

    function renderTicketModal(ticket, messages) {
        const ticketNo = ticket.ticket_no || '-';
        const status = String(ticket.status || '').toUpperCase();

        setText('spTicketModalTitle', ticketNo);
        setText('spTicketModalSubtitle', `Created ${formatDateTime(ticket.created_at || '-')}`);

        const statusEl = document.getElementById('spTicketModalStatus');
        if (statusEl) {
            statusEl.innerHTML = statusBadge(status);
        }

        setText('spTicketSubject', ticket.subject || '-');
        setText('spTicketCategory', formatTicketCategory(ticket.category || '-'));
        setText('spTicketCreated', formatDateTime(ticket.created_at || '-'));
        setText('spTicketUpdated', formatDateTime(ticket.updated_at || '-'));

        const preferredVisitEl = document.getElementById('spTicketPreferredVisit');
        if (preferredVisitEl) {
            preferredVisitEl.textContent = formatPreferredSchedule(ticket) || '-';
        }

        const priorityEl = document.getElementById('spTicketPriority');
        if (priorityEl) {
            priorityEl.innerHTML = priorityBadge(ticket.priority);
        }

        const descriptionEl = document.getElementById('spTicketDescription');
        if (descriptionEl) {
            descriptionEl.textContent = ticket.description || '-';
        }

        const replyTicketId = document.getElementById('spTicketReplyTicketId');
        if (replyTicketId) {
            replyTicketId.value = ticket.id || '';
        }

        renderTicketMessages(messages);
        renderTicketScheduleForm(ticket);

        const replyForm = document.getElementById('spTicketReplyForm');
        const closedStatuses = ['CLOSED', 'CANCELLED'];

        if (replyForm) {
            if (closedStatuses.includes(status)) {
                replyForm.classList.add('d-none');
            } else {
                replyForm.classList.remove('d-none');
            }

            replyForm.reset();

            if (replyTicketId) {
                replyTicketId.value = ticket.id || '';
            }
        }

        hideTicketModalLoading();
    }

    function renderTicketScheduleForm(ticket) {
        const form = document.getElementById('spTicketScheduleVisitForm');
        const ticketIdInput = document.getElementById('spScheduleVisitTicketId');
        const status = String(ticket.status || '').toUpperCase();

        if (!form) return;

        if (status === 'WAITING_CUSTOMER_SCHEDULE') {
            form.classList.remove('d-none');

            if (ticketIdInput) {
                ticketIdInput.value = ticket.id || '';
            }

            resetScheduleVisitFields();
            return;
        }

        form.classList.add('d-none');
    }

    async function loadScheduleVisitSlots() {
        const dateInput = document.getElementById('spScheduleVisitDate');
        const timeSelect = document.getElementById('spScheduleVisitTime');
        const help = document.getElementById('spScheduleVisitSlotHelp');

        if (!dateInput || !timeSelect) return;

        const date = String(dateInput.value || '').trim();

        timeSelect.innerHTML = `<option value="">Select preferred time</option>`;
        timeSelect.disabled = true;

        if (!date) {
            if (help) help.textContent = 'Choose a date to load available slots.';
            return;
        }

        if (help) help.textContent = 'Checking technician availability...';

        try {
            const response = await apiGet(`/api/v1/subscriber-portal/tickets/visit-slots?date=${encodeURIComponent(date)}`);
            const data = response.data || response;

            state.visitSlots = Array.isArray(data.slots) ? data.slots : [];

            const availableSlots = state.visitSlots.filter((slot) => Number(slot.available || 0) > 0);

            if (!availableSlots.length) {
                timeSelect.innerHTML = `<option value="">No available slots</option>`;
                if (help) help.textContent = 'No available technician schedule for this date.';
                return;
            }

            timeSelect.innerHTML = `
            <option value="">Select preferred time</option>
            ${availableSlots.map((slot) => `
                <option value="${escapeHtml(slot.value || '')}">
                    ${escapeHtml(slot.label || formatTimeLabel(slot.value || ''))} · ${escapeHtml(slot.available || 0)} available
                </option>
            `).join('')}
        `;

            timeSelect.disabled = false;

            if (help) help.textContent = 'Available slots are based on technician workload.';
        } catch (error) {
            timeSelect.innerHTML = `<option value="">Unable to load slots</option>`;
            if (help) help.textContent = error.message || 'Unable to load available slots.';
        }
    }

    async function submitTicketVisitSchedule(event) {
        event.preventDefault();

        const form = event.currentTarget;
        const submitBtn = document.getElementById('spScheduleVisitSubmitBtn');

        const ticketId = String(form.querySelector('[name="ticket_id"]')?.value || '').trim();
        const date = String(form.querySelector('[name="preferred_visit_date"]')?.value || '').trim();
        const time = String(form.querySelector('[name="preferred_visit_time"]')?.value || '').trim();

        if (!ticketId || !date || !time) {
            showToast('warning', 'Please select your preferred visit date and time.');
            return;
        }

        setButtonLoading(submitBtn, true, 'Submitting...');

        try {
            const response = await apiPost('/api/v1/subscriber-portal/tickets/schedule-visit', new FormData(form));
            const data = response.data || response;

            showToast('success', data.message || 'Visit schedule submitted.');

            await loadTicketDetails(ticketId);

            if (state.page === 'tickets') {
                await loadTicketsPage();
            }
        } catch (error) {
            showToast('error', error.message || 'Unable to submit visit schedule.');
        } finally {
            setButtonLoading(submitBtn, false);
        }
    }

    function resetScheduleVisitFields() {
        state.visitSlots = [];

        const dateInput = document.getElementById('spScheduleVisitDate');
        const timeSelect = document.getElementById('spScheduleVisitTime');
        const help = document.getElementById('spScheduleVisitSlotHelp');

        if (dateInput) {
            dateInput.value = '';
            dateInput.setAttribute('min', new Date().toISOString().slice(0, 10));
        }

        if (timeSelect) {
            timeSelect.innerHTML = `<option value="">Select date first</option>`;
            timeSelect.disabled = true;
        }

        if (help) {
            help.textContent = 'Choose a date to load available slots.';
        }
    }

    function renderTicketMessages(messages) {
        const host = document.getElementById('spTicketMessages');
        const countEl = document.getElementById('spTicketMessageCount');

        if (!host) return;

        const rows = Array.isArray(messages) ? messages : [];

        if (countEl) {
            countEl.textContent = `${rows.length} message${rows.length === 1 ? '' : 's'}`;
        }

        if (!rows.length) {
            host.innerHTML = `<div class="text-muted text-center py-3">No replies yet.</div>`;
            return;
        }

        host.innerHTML = rows.map((item) => {
            const senderType = String(item.sender_type || 'SYSTEM').toUpperCase();
            const senderName = senderType === 'SUBSCRIBER'
                ? 'You'
                : (item.full_name || item.username || senderType);

            return `
                <div class="border rounded-3 p-3 mb-2 bg-white">
                    <div class="d-flex justify-content-between gap-3 mb-1">
                        <div class="fw-semibold">${escapeHtml(senderName)}</div>
                        <div class="small text-muted">${escapeHtml(formatDateTime(item.created_at || '-'))}</div>
                    </div>
                    <div class="text-muted">${escapeHtml(item.message || '-')}</div>
                </div>
            `;
        }).join('');
    }

    async function submitTicketReply(event) {
        event.preventDefault();

        const form = event.currentTarget;
        const submitBtn = document.getElementById('spTicketReplySubmitBtn');

        const ticketId = String(form.querySelector('[name="ticket_id"]')?.value || '').trim();
        const message = String(form.querySelector('[name="message"]')?.value || '').trim();

        if (!ticketId || !message) {
            showToast('warning', 'Please type your reply.');
            return;
        }

        setButtonLoading(submitBtn, true, 'Sending...');

        try {
            const formData = new FormData(form);

            const response = await apiPost('/api/v1/subscriber-portal/tickets/reply', formData);
            const data = response.data || response;

            showToast('success', data.message || 'Reply sent successfully.');

            form.reset();

            await loadTicketDetails(ticketId);

            if (state.page === 'tickets') {
                await loadTicketsPage();
            }
        } catch (error) {
            showToast('error', error.message || 'Unable to send reply.');
        } finally {
            setButtonLoading(submitBtn, false);
        }
    }

    function formatTicketCategory(value) {
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

        return date.toLocaleString('en-PH', {
            year: 'numeric',
            month: 'short',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function priorityBadge(priority) {
        const value = String(priority || '-').toUpperCase();

        let cls = 'bg-secondary';

        if (value === 'LOW') cls = 'bg-light text-dark border';
        if (value === 'MEDIUM') cls = 'bg-primary';
        if (value === 'HIGH') cls = 'bg-warning text-dark';
        if (value === 'URGENT') cls = 'bg-danger';

        return `<span class="badge badge-status ${cls}">${escapeHtml(value)}</span>`;
    }

    async function changePortalPassword(event) {
        event.preventDefault();

        const form = event.currentTarget;
        const submitBtn = document.getElementById('spChangePasswordSubmitBtn');

        const currentPassword = String(form.querySelector('[name="current_password"]')?.value || '');
        const newPassword = String(form.querySelector('[name="new_password"]')?.value || '');
        const confirmPassword = String(form.querySelector('[name="confirm_password"]')?.value || '');

        if (!currentPassword || !newPassword || !confirmPassword) {
            showToast('warning', 'Please complete all password fields.');
            return;
        }

        if (newPassword.length < 8) {
            showToast('warning', 'New password must be at least 8 characters.');
            return;
        }

        if (newPassword !== confirmPassword) {
            showToast('warning', 'New password and confirmation do not match.');
            return;
        }

        setButtonLoading(submitBtn, true, 'Updating...');

        try {
            const formData = new FormData(form);

            const response = await apiPost('/api/v1/subscriber-portal/change-password', formData);

            form.reset();

            closeBootstrapModalAndCleanup('spChangePasswordModal');

            showToast('success', response.message || 'Password changed successfully.');
        } catch (error) {
            showToast('error', error.message || 'Unable to change password.');
        } finally {
            setButtonLoading(submitBtn, false);
        }
    }

    function closeBootstrapModalAndCleanup(modalId) {
        const modalEl = document.getElementById(modalId);

        if (modalEl && window.bootstrap && bootstrap.Modal) {
            const modalInstance = bootstrap.Modal.getInstance(modalEl) || bootstrap.Modal.getOrCreateInstance(modalEl);
            modalInstance.hide();
        }

        setTimeout(() => {
            document.querySelectorAll('.modal-backdrop').forEach((backdrop) => {
                backdrop.remove();
            });

            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('overflow');
            document.body.style.removeProperty('padding-right');
        }, 300);
    }

    function renderAccountStatusBanner(profile, summary, serviceSummary, nextDueInvoice) {
        const titleEl = document.getElementById('spStatusTitle');
        const messageEl = document.getElementById('spStatusMessage');
        const badgeEl = document.getElementById('spStatusBadge');
        const iconEl = document.getElementById('spStatusIcon');
        const bannerEl = document.getElementById('spStatusBanner');

        if (!titleEl || !messageEl || !badgeEl || !iconEl || !bannerEl) {
            return;
        }

        const name = profile.full_name || 'Subscriber';
        const accountNumber = profile.account_number || '-';
        const totalBalance = Number(summary.total_balance || 0);
        const overdueBalance = Number(summary.overdue_balance || 0);
        const activeServices = Number(serviceSummary.active_service_count || 0);
        const nextDueDate = nextDueInvoice?.due_date || serviceSummary.nearest_next_due_date || null;
        const nextDueInvoiceNo = nextDueInvoice?.invoice_no || null;

        bannerEl.classList.remove('sp-status-good', 'sp-status-warning', 'sp-status-danger');
        iconEl.innerHTML = '';

        titleEl.textContent = `Welcome, ${name}`;

        if (overdueBalance > 0) {
            bannerEl.classList.add('sp-status-danger');
            iconEl.innerHTML = '<i class="bi bi-exclamation-triangle"></i>';
            badgeEl.innerHTML = '<span class="badge bg-danger">ACTION NEEDED</span>';
            messageEl.textContent = `Account #${accountNumber} has ${money(overdueBalance)} overdue balance. Please settle your overdue invoices to avoid service interruption.`;
            return;
        }

        if (totalBalance > 0) {
            bannerEl.classList.add('sp-status-warning');
            iconEl.innerHTML = '<i class="bi bi-info-circle"></i>';
            badgeEl.innerHTML = '<span class="badge bg-warning text-dark">BALANCE DUE</span>';

            if (nextDueDate) {
                messageEl.textContent = `Account #${accountNumber} has ${money(totalBalance)} outstanding balance. Next due date is ${nextDueDate}${nextDueInvoiceNo ? ` for ${nextDueInvoiceNo}` : ''}.`;
            } else {
                messageEl.textContent = `Account #${accountNumber} has ${money(totalBalance)} outstanding balance.`;
            }

            return;
        }

        if (activeServices > 0) {
            bannerEl.classList.add('sp-status-good');
            iconEl.innerHTML = '<i class="bi bi-check-circle"></i>';
            badgeEl.innerHTML = '<span class="badge bg-success">GOOD STANDING</span>';
            messageEl.textContent = `Account #${accountNumber} is in good standing. You currently have ${activeServices} active service${activeServices === 1 ? '' : 's'}.`;
            return;
        }

        bannerEl.classList.add('sp-status-warning');
        iconEl.innerHTML = '<i class="bi bi-wifi-off"></i>';
        badgeEl.innerHTML = '<span class="badge bg-secondary">NO ACTIVE SERVICE</span>';
        messageEl.textContent = `Account #${accountNumber} has no active service at the moment.`;
    }

    function renderLatestInvoiceBox(latestInvoice, nextDueInvoice) {
        const box = document.getElementById('spLatestInvoiceBox');

        if (!box) {
            return;
        }

        const invoice = nextDueInvoice || latestInvoice;

        if (!invoice) {
            box.innerHTML = `<div class="text-muted">No invoice records found yet.</div>`;
            return;
        }

        box.innerHTML = `
            <div class="sp-latest-invoice">
                <div>
                    <div class="fw-semibold">${escapeHtml(invoice.invoice_no || '-')}</div>
                    <div class="small text-muted">
                        ${escapeHtml(invoice.billing_period_start || '-')} to ${escapeHtml(invoice.billing_period_end || '-')}
                    </div>
                </div>

                <div class="text-end">
                    <div>${statusBadge(invoice.status)}</div>
                    <div class="fw-semibold mt-1">${money(invoice.balance_amount || 0)}</div>
                    <div class="small text-muted">Due: ${escapeHtml(invoice.due_date || '-')}</div>
                </div>
            </div>

            <div class="mt-3">
                <a href="/subscriber-portal/invoices" class="btn btn-sm btn-primary">
                    <i class="bi bi-receipt"></i>
                    View Invoices
                </a>
            </div>
        `;
    }

    function renderServicesSummary(serviceSummary, services) {
        const total = services.length;
        const active = services.filter((item) => String(item.status || '').toUpperCase() === 'ACTIVE').length;

        setText('spServicesTotal', total);
        setText('spServicesActive', active);
        setText('spServicesNearestDue', serviceSummary.nearest_next_due_date || getNearestDueDate(services) || '-');
    }

    function renderServiceCards(items) {
        const host = document.getElementById('spServicesCards');

        if (!host) {
            return;
        }

        if (!items.length) {
            host.innerHTML = `
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center text-muted py-5">
                            No services found.
                        </div>
                    </div>
                </div>
            `;
            return;
        }

        host.innerHTML = items.map((item) => {
            const speed = formatSpeed(item);
            const status = String(item.status || '-').toUpperCase();
            const napBoxName = item.nap_name || item.nap_box_code || '-';
            const napLocation = item.nap_location || item.nap_address || '-';
            const napPort = item.nap_box_port ? `Port ${item.nap_box_port}` : '-';

            return `
                <div class="col-12 col-xl-6">
                    <div class="card border-0 shadow-sm sp-service-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                <div>
                                    <div class="text-muted small">Service No.</div>
                                    <div class="fs-5 fw-bold">${escapeHtml(item.service_number || '-')}</div>
                                </div>
                                <div>${statusBadge(status)}</div>
                            </div>

                            <div class="sp-service-plan mb-3">
                                <div class="sp-service-icon">
                                    <i class="bi bi-wifi"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold">${escapeHtml(item.plan_name || '-')}</div>
                                    <div class="small text-muted">${escapeHtml(speed)} · ${money(item.price || 0)} / ${escapeHtml(item.account_type || '-')}</div>
                                </div>
                            </div>

                            <div class="row g-2">
                                <div class="col-12 col-md-6">
                                    <div class="sp-mini-info">
                                        <div class="text-muted small">PPP Username</div>
                                        <div class="fw-semibold">${escapeHtml(item.ppp_username || '-')}</div>
                                    </div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <div class="sp-mini-info">
                                        <div class="text-muted small">Next Due</div>
                                        <div class="fw-semibold">${escapeHtml(item.next_due_date || '-')}</div>
                                    </div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <div class="sp-mini-info">
                                        <div class="text-muted small">ONT Serial</div>
                                        <div class="fw-semibold">${escapeHtml(item.ont_serial || '-')}</div>
                                    </div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <div class="sp-mini-info">
                                        <div class="text-muted small">ACS Status</div>
                                        <div class="fw-semibold">${statusBadge(item.acs_status || 'UNKNOWN')}</div>
                                    </div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <div class="sp-mini-info">
                                        <div class="text-muted small">WAN IP</div>
                                        <div class="fw-semibold">${escapeHtml(item.wan_ip || '-')}</div>
                                    </div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <div class="sp-mini-info">
                                        <div class="text-muted small">NAP Box Name</div>
                                        <div class="fw-semibold">${escapeHtml(napBoxName)}</div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="sp-mini-info">
                                        <div class="text-muted small">NAP Box Location</div>
                                        <div class="fw-semibold">${escapeHtml(napLocation)}</div>
                                    </div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <div class="sp-mini-info">
                                        <div class="text-muted small">NAP Box Port</div>
                                        <div class="fw-semibold">${escapeHtml(napPort)}</div>
                                    </div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <div class="sp-mini-info">
                                        <div class="text-muted small">Port Status</div>
                                        <div class="fw-semibold">${escapeHtml(item.nap_box_port_status || '-')}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    function renderServicesTable(items) {
        const body = document.getElementById('spServicesBody');
        const count = document.getElementById('spServiceCount');

        if (count) {
            count.textContent = `${items.length} service${items.length === 1 ? '' : 's'}`;
        }

        if (!body) return;

        if (!items.length) {
            body.innerHTML = `<tr><td colspan="5" class="text-muted text-center py-4">No services found.</td></tr>`;
            return;
        }

        body.innerHTML = items.map((item) => {
            const speed = formatSpeed(item);

            return `
                <tr>
                    <td class="ps-4">${escapeHtml(item.service_number || '-')}</td>
                    <td>
                        <div class="fw-semibold">${escapeHtml(item.plan_name || '-')}</div>
                        <div class="small text-muted">${money(item.price || 0)} / ${escapeHtml(item.account_type || '-')}</div>
                    </td>
                    <td>${escapeHtml(speed)}</td>
                    <td>${statusBadge(item.status)}</td>
                    <td>${escapeHtml(item.next_due_date || '-')}</td>
                </tr>
            `;
        }).join('');
    }

    function renderInvoices(items) {
        const body = document.getElementById('spInvoicesBody');
        const pager = document.getElementById('spInvoicesPager');

        if (!body) return;

        const rows = Array.isArray(items) ? items : [];
        const pagedRows = rows;

        if (!rows.length) {
            body.innerHTML = `<tr><td colspan="7" class="text-muted text-center py-4">No invoices found.</td></tr>`;

            if (pager) {
                pager.innerHTML = '';
            }

            return;
        }

        body.innerHTML = pagedRows.map((item) => {
            const period = `${item.billing_period_start || '-'} to ${item.billing_period_end || '-'}`;

            return `
                <tr class="sp-clickable-row" data-invoice-id="${escapeHtml(item.id)}">
                    <td class="ps-4">
                        <div class="fw-semibold">${escapeHtml(item.invoice_no || '-')}</div>
                        <div class="small text-muted">${escapeHtml(item.plan_name || '')}</div>
                    </td>
                    <td>${escapeHtml(period)}</td>
                    <td>${escapeHtml(item.due_date || '-')}</td>
                    <td>${statusBadge(item.status)}</td>
                    <td class="text-end">${money(item.total_amount || 0)}</td>
                    <td class="text-end">${money(item.paid_amount || 0)}</td>
                    <td class="text-end pe-4 fw-semibold">${money(item.balance_amount || 0)}</td>
                </tr>
            `;
        }).join('');

        body.querySelectorAll('[data-invoice-id]').forEach((row) => {
            row.addEventListener('click', () => {
                const invoiceId = row.getAttribute('data-invoice-id');
                loadInvoiceDetails(invoiceId);
            });
        });

        renderPager({
            el: pager,
            totalRows: state.invoiceTotal,
            currentPage: state.invoicePager.currentPage,
            rowsPerPage: state.invoicePager.rowsPerPage,
            onPageChange: (page) => {
                state.invoicePager.currentPage = page;
                loadInvoicesPage();
            },
        });
    }

    async function loadInvoiceDetails(invoiceId) {
        clearAlert();
        showInvoiceModalLoading();

        try {
            openInvoiceModal();

            const response = await apiGet(`/api/v1/subscriber-portal/invoices/show/${invoiceId}`);
            const data = response.data || response;

            const invoice = data.invoice || {};
            const items = data.items || [];
            const payments = data.payments || [];

            state.selectedInvoice = invoice;
            state.selectedInvoiceItems = items;
            state.selectedInvoicePayments = payments;

            renderInvoiceModal(invoice, items, payments);
        } catch (error) {
            hideInvoiceModalLoading();
            showAlert(error.message || 'Unable to load invoice details.', 'danger');
        }
    }

    function openInvoiceModal() {
        const modalEl = document.getElementById('spInvoiceDetailsModal');

        if (!modalEl) {
            return;
        }

        if (window.bootstrap && bootstrap.Modal) {
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
            return;
        }

        modalEl.classList.add('show');
        modalEl.style.display = 'block';
        modalEl.removeAttribute('aria-hidden');
    }

    function showInvoiceModalLoading() {
        setText('spInvoiceModalTitle', 'Invoice Details');
        setText('spInvoiceModalSubtitle', 'Loading invoice details...');

        const statusEl = document.getElementById('spInvoiceModalStatus');
        const loadingEl = document.getElementById('spInvoiceModalLoading');
        const contentEl = document.getElementById('spInvoiceModalContent');
        const payBtn = document.getElementById('spPayNowBtn');

        if (statusEl) statusEl.innerHTML = '';
        if (loadingEl) loadingEl.classList.remove('d-none');
        if (contentEl) contentEl.classList.add('d-none');

        if (payBtn) {
            payBtn.classList.add('d-none');
            payBtn.disabled = true;
            payBtn.removeAttribute('data-invoice-id');
        }
    }

    function hideInvoiceModalLoading() {
        const loadingEl = document.getElementById('spInvoiceModalLoading');
        const contentEl = document.getElementById('spInvoiceModalContent');

        if (loadingEl) loadingEl.classList.add('d-none');
        if (contentEl) contentEl.classList.remove('d-none');
    }

    function renderInvoiceModal(invoice, items, payments) {
        const invoiceNo = invoice.invoice_no || '-';
        const period = `${invoice.billing_period_start || '-'} to ${invoice.billing_period_end || '-'}`;
        const balance = Number(invoice.balance_amount || 0);
        const status = String(invoice.status || '').toUpperCase();

        setText('spInvoiceModalTitle', invoiceNo);
        setText('spInvoiceModalSubtitle', `Billing period ${period}`);

        const statusEl = document.getElementById('spInvoiceModalStatus');
        if (statusEl) {
            statusEl.innerHTML = statusBadge(status);
        }

        setText('spModalInvoiceNo', invoiceNo);
        setText('spModalPlanName', invoice.plan_name || '-');
        setText('spModalBillingPeriod', period);
        setText('spModalIssueDate', invoice.issue_date || '-');
        setText('spModalDueDate', invoice.due_date || '-');

        setText('spModalTotal', money(invoice.total_amount || 0));
        setText('spModalPaid', money(invoice.paid_amount || 0));
        setText('spModalBalance', money(invoice.balance_amount || 0));

        renderInvoiceModalItems(items);
        renderInvoiceModalPayments(payments);
        renderPayNowButton(invoice, balance, status);

        hideInvoiceModalLoading();
    }

    function renderInvoiceModalItems(items) {
        const body = document.getElementById('spModalInvoiceItemsBody');

        if (!body) return;

        if (!items.length) {
            body.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-3">No line items.</td></tr>`;
            return;
        }

        body.innerHTML = items.map((item) => `
            <tr>
                <td>${escapeHtml(item.description || '-')}</td>
                <td>${escapeHtml(item.item_type || '-')}</td>
                <td class="text-end">${escapeHtml(item.quantity || '1')}</td>
                <td class="text-end">${money(item.unit_price || 0)}</td>
                <td class="text-end fw-semibold">${money(item.line_total || 0)}</td>
            </tr>
        `).join('');
    }

    function renderInvoiceModalPayments(payments) {
        const body = document.getElementById('spModalPaymentsBody');

        if (!body) return;

        if (!payments.length) {
            body.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-3">No payments yet.</td></tr>`;
            return;
        }

        body.innerHTML = payments.map((payment) => `
            <tr>
                <td>${escapeHtml(payment.payment_no || '-')}</td>
                <td>${escapeHtml(payment.payment_date || '-')}</td>
                <td>${escapeHtml(payment.method || '-')}</td>
                <td>${escapeHtml(payment.reference_no || '-')}</td>
                <td>${statusBadge(payment.payment_status)}</td>
                <td class="text-end fw-semibold">${money(payment.amount || payment.allocated_amount || 0)}</td>
            </tr>
        `).join('');
    }

    function renderPayNowButton(invoice, balance, status) {
        const payBtn = document.getElementById('spPayNowBtn');
        const manualBtn = document.getElementById('spManualPayBtn');

        if (!payBtn) return;

        const canPay = balance > 0 && ['UNPAID', 'PARTIAL', 'OVERDUE'].includes(status);

        if (!canPay) {
            payBtn.classList.add('d-none');
            payBtn.disabled = true;
            payBtn.removeAttribute('data-invoice-id');
            if(manualBtn){manualBtn.classList.add('d-none');manualBtn.disabled=true;}
            return;
        }

        payBtn.classList.remove('d-none');
        payBtn.disabled = false;
        payBtn.setAttribute('data-invoice-id', invoice.id || invoice.invoice_id || '');
        payBtn.innerHTML = `
            <i class="bi bi-credit-card"></i>
            <span>Pay Now ${money(balance)}</span>
        `;
        if(manualBtn){manualBtn.classList.remove('d-none');manualBtn.disabled=false;document.getElementById('spManualPaymentInvoiceId').value=invoice.id||invoice.invoice_id||'';document.getElementById('spManualPaymentAmount').value=balance.toFixed(2);document.getElementById('spManualPaymentAmount').max=balance.toFixed(2);}
    }

    async function submitManualPayment(event) {
        event.preventDefault(); const form=event.currentTarget; const btn=document.getElementById('spManualPaymentSubmitBtn'); setButtonLoading(btn,true,'Submitting...');
        try { const response=await apiPost('/api/v1/subscriber-portal/payments/submit',new FormData(form)); showToast('success',response.message||'Payment submitted for Billing review.'); form.reset(); closeBootstrapModalAndCleanup('spManualPaymentModal'); if(state.selectedInvoice) await loadInvoiceDetails(state.selectedInvoice.id||state.selectedInvoice.invoice_id); }
        catch(error){showToast('error',error.message||'Unable to submit payment.');} finally{setButtonLoading(btn,false);}
    }

    async function paySelectedInvoice() {
        const payBtn = document.getElementById('spPayNowBtn');

        if (!payBtn) {
            return;
        }

        const invoiceId = payBtn.getAttribute('data-invoice-id');

        if (!invoiceId) {
            showToast('warning', 'Unable to determine invoice ID.');
            return;
        }

        setButtonLoading(payBtn, true, 'Opening PayMongo...');

        try {
            const response = await apiPostJson('/api/v1/payment-gateway/paymongo/checkout', {
                invoice_id: Number(invoiceId),
            });

            const data = response.data || response;

            if (!data.checkout_url) {
                throw new Error('PayMongo checkout URL was not returned.');
            }

            window.location.href = data.checkout_url;
        } catch (error) {
            showToast('error', error.message || 'Unable to start PayMongo payment.');
            setButtonLoading(payBtn, false);
        }
    }

    function renderPayments(items) {
        const body = document.getElementById('spPaymentsBody');
        const pager = document.getElementById('spPaymentsPager');

        if (!body) return;

        const rows = Array.isArray(items) ? items : [];
        const colspan = state.page === 'payments' ? 8 : 6;
        const pagedRows = rows;

        if (!rows.length) {
            body.innerHTML = `<tr><td colspan="${colspan}" class="text-muted text-center py-4">No payments found.</td></tr>`;

            if (pager) {
                pager.innerHTML = '';
            }

            return;
        }

        body.innerHTML = pagedRows.map((item) => {
            const paymentId = Number(item.id || item.payment_id || 0);
            const receiptBtn = paymentId > 0 && String(item.payment_status || '').toUpperCase() === 'POSTED'
                ? `
                <a href="/subscriber-portal/payments/receipt/${paymentId}"
                   target="_blank"
                   class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-printer"></i>
                    Receipt
                </a>
            `
                : '<span class="text-muted">-</span>';

            if (state.page === 'payments') {
                return `
                <tr>
                    <td class="ps-4">${escapeHtml(item.payment_no || '-')}</td>
                    <td>${escapeHtml(item.invoice_no || '-')}</td>
                    <td>${escapeHtml(item.payment_date || '-')}</td>
                    <td>${escapeHtml(item.method || '-')}</td>
                    <td>${statusBadge(item.payment_status)}</td>
                    <td>${escapeHtml(item.reference_no || '-')}</td>
                    <td class="text-end">${money(item.amount || 0)}</td>
                    <td class="text-end pe-4">${receiptBtn}</td>
                </tr>
            `;
            }

            return `
            <tr>
                <td class="ps-4">${escapeHtml(item.payment_no || '-')}</td>
                <td>${escapeHtml(item.invoice_no || '-')}</td>
                <td>${escapeHtml(item.payment_date || '-')}</td>
                <td>${escapeHtml(item.method || '-')}</td>
                <td>${statusBadge(item.payment_status)}</td>
                <td class="text-end pe-4">${money(item.amount || 0)}</td>
            </tr>
        `;
        }).join('');

        renderPager({
            el: pager,
            totalRows: state.paymentTotal,
            currentPage: state.paymentPager.currentPage,
            rowsPerPage: state.paymentPager.rowsPerPage,
            onPageChange: (page) => {
                state.paymentPager.currentPage = page;
                loadPaymentsPage();
            },
        });
    }

    function renderPaymentsSummary(summary) {
        setText('spPaymentCount', Number(summary.payment_count || 0));
        setText('spPaymentPostedAmount', money(summary.posted_amount || 0));
        setText('spLatestPaymentDate', summary.latest_payment_date || '-');
    }

    function printSelectedInvoice() {
        if (!state.selectedInvoice) {
            showAlert('No invoice selected for printing.', 'warning');
            return;
        }

        const invoiceId = state.selectedInvoice.id || state.selectedInvoice.invoice_id;

        if (!invoiceId) {
            showAlert('Unable to determine invoice ID for printing.', 'warning');
            return;
        }

        window.open(`/subscriber-portal/invoices/print/${invoiceId}`, '_blank');
    }

    function formatSpeed(item) {
        if (item.speed_mbps) {
            return `${item.speed_mbps} Mbps`;
        }

        const down = item.speed_down || 0;
        const up = item.speed_up || 0;

        return `${down}/${up} Mbps`;
    }

    function getNearestDueDate(items) {
        const dates = items
            .map((item) => item.next_due_date)
            .filter((value) => value && value !== '0000-00-00')
            .sort();

        return dates.length ? dates[0] : null;
    }

    function paginateRows(rows, currentPage, rowsPerPage) {
        const safeRows = Array.isArray(rows) ? rows : [];
        const page = Math.max(1, Number(currentPage || 1));
        const limit = Math.max(1, Number(rowsPerPage || 10));
        const start = (page - 1) * limit;

        return safeRows.slice(start, start + limit);
    }

    function renderPager({ el, totalRows, currentPage, rowsPerPage, onPageChange }) {
        if (!el) return;

        const total = Number(totalRows || 0);
        const limit = Math.max(1, Number(rowsPerPage || 10));
        const totalPages = Math.max(1, Math.ceil(total / limit));
        const page = Math.min(Math.max(1, Number(currentPage || 1)), totalPages);

        if (total <= limit) {
            el.innerHTML = `
                <div class="sp-pager-summary">
                    Showing ${total} record${total === 1 ? '' : 's'}
                </div>
            `;
            return;
        }

        const startRecord = ((page - 1) * limit) + 1;
        const endRecord = Math.min(page * limit, total);
        const pages = getPagerPages(page, totalPages);

        el.innerHTML = `
            <div class="sp-pager-summary">
                Showing ${startRecord}-${endRecord} of ${total} records
            </div>

            <div class="sp-pager-controls">
                <button type="button"
                        class="btn btn-sm btn-light border sp-pager-btn"
                        data-page="${page - 1}"
                        ${page <= 1 ? 'disabled' : ''}>
                    <i class="bi bi-chevron-left"></i>
                </button>

                ${pages.map((item) => {
            if (item === '...') {
                return `<span class="sp-pager-ellipsis">...</span>`;
            }

            return `
                        <button type="button"
                                class="btn btn-sm ${item === page ? 'btn-primary' : 'btn-light border'} sp-pager-btn"
                                data-page="${item}">
                            ${item}
                        </button>
                    `;
        }).join('')}

                <button type="button"
                        class="btn btn-sm btn-light border sp-pager-btn"
                        data-page="${page + 1}"
                        ${page >= totalPages ? 'disabled' : ''}>
                    <i class="bi bi-chevron-right"></i>
                </button>
            </div>
        `;

        el.querySelectorAll('[data-page]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const nextPage = Number(btn.getAttribute('data-page') || 1);

                if (nextPage < 1 || nextPage > totalPages || nextPage === page) {
                    return;
                }

                onPageChange(nextPage);
            });
        });
    }

    function getPagerPages(currentPage, totalPages) {
        const page = Number(currentPage || 1);
        const total = Number(totalPages || 1);

        if (total <= 7) {
            return Array.from({ length: total }, (_, index) => index + 1);
        }

        const pages = [1];

        if (page > 4) {
            pages.push('...');
        }

        const start = Math.max(2, page - 1);
        const end = Math.min(total - 1, page + 1);

        for (let i = start; i <= end; i += 1) {
            pages.push(i);
        }

        if (page < total - 3) {
            pages.push('...');
        }

        pages.push(total);

        return pages;
    }

    async function apiGet(url) {
        return { ok: true, success: true, data: await window.NX.api.get(url) };
    }

    async function apiPost(url, formData) {
        return window.NX.api.form(url, formData);
    }

    async function apiPostJson(url, payload) {
        return window.NX.api.post(url, payload || {});
    }

    function setText(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    }

    function money(value) {
        const amount = Number(value || 0);

        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP',
        }).format(amount);
    }

    function statusBadge(status) {
        const value = String(status || '-').toUpperCase();

        let cls = 'bg-secondary';

        if (value === 'ACTIVE' || value === 'PAID' || value === 'POSTED') cls = 'bg-success';
        if (value === 'UNPAID' || value === 'PARTIAL' || value === 'PENDING') cls = 'bg-warning text-dark';
        if (value === 'OVERDUE' || value === 'SUSPENDED') cls = 'bg-danger';
        if (value === 'VOIDED' || value === 'CANCELLED' || value === 'TERMINATED') cls = 'bg-dark';

        if (value === 'OPEN') cls = 'bg-primary';
        if (value === 'IN_PROGRESS') cls = 'bg-info text-dark';

        if (
            value === 'WAITING_CUSTOMER' ||
            value === 'WAITING_TECHNICIAN' ||
            value === 'WAITING_CUSTOMER_SCHEDULE'
        ) cls = 'bg-warning text-dark';

        if (value === 'VISIT_SCHEDULED') cls = 'bg-info text-dark';
        if (value === 'RESOLVED') cls = 'bg-success';
        if (value === 'CLOSED') cls = 'bg-dark';
        if (value === 'CANCELLED') cls = 'bg-dark';

        return `<span class="badge badge-status ${cls}">${escapeHtml(value)}</span>`;
    }

    function showAlert(message, type = 'danger') {
        const el = document.getElementById('subscriberPortalAlert');

        if (!el) return;

        el.innerHTML = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${escapeHtml(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
    }

    function clearAlert() {
        const el = document.getElementById('subscriberPortalAlert');
        if (el) el.innerHTML = '';
    }

    function showToast(icon = 'success', title = '') {
        if (typeof Swal === 'undefined') {
            showAlert(title, icon === 'error' ? 'danger' : icon);
            return;
        }

        Swal.fire({
            toast: true,
            position: 'top-end',
            icon,
            title,
            showConfirmButton: false,
            timer: icon === 'error' ? 4500 : 3000,
            timerProgressBar: true,
            customClass: {
                popup: 'nx-toast-popup',
            },
        });
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

    function setButtonLoading(button, isLoading, loadingText = 'Loading...') {
        if (!button) return;

        if (isLoading) {
            button.dataset.originalHtml = button.innerHTML;
            button.disabled = true;
            button.innerHTML = `
                <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                ${escapeHtml(loadingText)}
            `;
            return;
        }

        button.disabled = false;

        if (button.dataset.originalHtml) {
            button.innerHTML = button.dataset.originalHtml;
            delete button.dataset.originalHtml;
        }
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    async function verifyPayMongoReturnIfNeeded() {
        const params = new URLSearchParams(window.location.search);
        const payment = String(params.get('payment') || '').toLowerCase();

        if (payment !== 'success') {
            return;
        }

        showToast('info', 'Verifying PayMongo payment...');

        try {
            const invoiceId = Number(params.get('invoice_id') || 0);
            const response = await apiPostJson('/api/v1/payment-gateway/paymongo/verify', invoiceId > 0 ? { invoice_id: invoiceId } : {});
            const data = response.data || response;

            if (data.paid === true || data?.data?.paid === true) {
                showToast('success', 'Payment verified and invoice updated.');

                await loadCurrentPage();

                window.history.replaceState({}, document.title, window.location.pathname);
                return;
            }

            showToast('warning', data.message || 'Payment is not confirmed yet.');
        } catch (error) {
            showToast('error', error.message || 'Unable to verify PayMongo payment.');
        }
    }
})();
