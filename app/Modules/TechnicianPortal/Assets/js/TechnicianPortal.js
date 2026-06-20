document.addEventListener('DOMContentLoaded', () => {
    const app = document.querySelector('[data-technician-portal-page="index"]');
    if (!app) return;

    const state = {
        workOrders: [],
        selectedWorkOrder: null,
        selectedNotes: [],
    };

    const refs = {
        refreshBtn: document.getElementById('techRefreshBtn'),
        timeInBtn: document.getElementById('techTimeInBtn'),
        timeOutBtn: document.getElementById('techTimeOutBtn'),
        statusFilter: document.getElementById('techStatusFilter'),
        host: document.getElementById('techWorkOrdersHost'),

        assignedCount: document.getElementById('techAssignedCount'),
        progressCount: document.getElementById('techProgressCount'),
        completedCount: document.getElementById('techCompletedCount'),
        attendanceStatus: document.getElementById('techAttendanceStatus'),
        attendanceMeta: document.getElementById('techAttendanceMeta'),

        checkInBtn: document.getElementById('techCheckInBtn'),
        startWorkBtn: document.getElementById('techStartWorkBtn'),
        completeForm: document.getElementById('techCompleteWorkForm'),
        noteForm: document.getElementById('techAddNoteForm'),
    };

    bindEvents();
    loadDashboard();

    function bindEvents() {
        refs.refreshBtn?.addEventListener('click', loadDashboard);
        refs.statusFilter?.addEventListener('change', loadWorkOrders);
        refs.timeInBtn?.addEventListener('click', timeIn);
        refs.timeOutBtn?.addEventListener('click', timeOut);
        refs.checkInBtn?.addEventListener('click', checkIn);
        refs.startWorkBtn?.addEventListener('click', startWork);
        refs.completeForm?.addEventListener('submit', completeWork);
        refs.noteForm?.addEventListener('submit', addNote);

        refs.host?.addEventListener('click', (event) => {
            const btn = event.target.closest('[data-work-order-id]');
            if (!btn) return;

            loadWorkOrderDetails(btn.getAttribute('data-work-order-id'));
        });
    }

    async function loadDashboard() {
        try {
            const response = await apiGet('/api/v1/technician-portal/dashboard');
            const data = response.data || response;

            renderSummary(data.summary || {});
            renderAttendance(data.attendance || null);

            await loadWorkOrders();
        } catch (error) {
            showToast('error', error.message || 'Unable to load technician dashboard.');
        }
    }

    async function loadWorkOrders() {
        const status = refs.statusFilter?.value || '';
        const params = new URLSearchParams();

        if (status) params.set('status', status);

        params.set('limit', '100');
        params.set('offset', '0');

        if (refs.host) {
            refs.host.innerHTML = `<div class="text-muted text-center py-4">Loading work orders...</div>`;
        }

        try {
            const response = await apiGet(`/api/v1/technician-portal/work-orders?${params.toString()}`);
            const data = response.data || response;

            state.workOrders = Array.isArray(data.items) ? data.items : [];
            renderWorkOrders(state.workOrders);
        } catch (error) {
            if (refs.host) {
                refs.host.innerHTML = `<div class="text-danger text-center py-4">${escapeHtml(error.message || 'Unable to load work orders.')}</div>`;
            }
        }
    }

    function renderSummary(summary) {
        setText(refs.assignedCount, summary.assigned_count || 0);
        setText(refs.progressCount, summary.in_progress_count || 0);
        setText(refs.completedCount, summary.completed_count || 0);
    }

    function renderAttendance(attendance) {
        if (!attendance) {
            setText(refs.attendanceStatus, 'OFF DUTY');
            setText(refs.attendanceMeta, 'Not timed in');
            return;
        }

        setText(refs.attendanceStatus, attendance.status || 'AVAILABLE');

        const timeIn = attendance.time_in_at || '-';
        const timeOut = attendance.time_out_at || '';

        setText(
            refs.attendanceMeta,
            timeOut ? `Timed out: ${formatDateTime(timeOut)}` : `Timed in: ${formatDateTime(timeIn)}`
        );
    }

    function renderWorkOrders(items) {
        if (!refs.host) return;

        if (!items.length) {
            refs.host.innerHTML = `
                <div class="text-muted text-center py-5">
                    <i class="bi bi-clipboard-check fs-2 d-block mb-2"></i>
                    No assigned work orders found.
                </div>
            `;
            return;
        }

        refs.host.innerHTML = `
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Work Order</th>
                        <th>Subscriber</th>
                        <th>Schedule</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    ${items.map((item) => `
                        <tr>
                            <td>
                                <div class="fw-semibold">${escapeHtml(item.work_order_no || '-')}</div>
                                <div class="small text-muted">${escapeHtml(item.title || item.ticket_subject || '-')}</div>
                            </td>
                            <td>
                                <div class="fw-semibold">${escapeHtml(item.subscriber_name || '-')}</div>
                                <div class="small text-muted">${escapeHtml(item.account_number || '-')}</div>
                            </td>
                            <td>
                                <div>${escapeHtml(item.scheduled_date || '-')}</div>
                                <div class="small text-muted">${escapeHtml(formatTime(item.scheduled_time || '-'))}</div>
                            </td>
                            <td>${statusBadge(item.status)}</td>
                            <td class="text-end">
                                <button type="button"
                                        class="btn btn-sm btn-outline-primary"
                                        data-work-order-id="${escapeHtml(item.id)}">
                                    <i class="bi bi-eye"></i>
                                    View
                                </button>
                            </td>
                        </tr>
                    `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    async function loadWorkOrderDetails(workOrderId) {
        if (!workOrderId) {
            showToast('warning', 'Unable to determine work order ID.');
            return;
        }

        showModalLoading();
        openModal();

        try {
            const response = await apiGet(`/api/v1/technician-portal/work-orders/show/${workOrderId}`);
            const data = response.data || response;

            state.selectedWorkOrder = data.work_order || {};
            state.selectedNotes = Array.isArray(data.notes) ? data.notes : [];

            renderModal(state.selectedWorkOrder, state.selectedNotes);
        } catch (error) {
            hideModalLoading();
            showToast('error', error.message || 'Unable to load work order.');
        }
    }

    function renderModal(workOrder, notes) {
        setTextById('techModalTitle', workOrder.work_order_no || '-');
        setTextById('techModalSubtitle', `Scheduled ${workOrder.scheduled_date || '-'} ${formatTime(workOrder.scheduled_time || '')}`);

        const statusEl = document.getElementById('techModalStatus');
        if (statusEl) statusEl.innerHTML = statusBadge(workOrder.status);

        setTextById('techWoTitle', workOrder.title || workOrder.ticket_subject || '-');
        setTextById('techWoDescription', workOrder.description || workOrder.ticket_description || '-');
        setTextById('techWoDate', workOrder.scheduled_date || '-');
        setTextById('techWoTime', formatTime(workOrder.scheduled_time || '-'));
        setTextById('techWoTicket', workOrder.ticket_no || '-');
        setTextById('techWoService', workOrder.service_number || workOrder.ppp_username || '-');

        setTextById('techSubscriberName', workOrder.subscriber_name || '-');
        setTextById('techSubscriberAccount', workOrder.account_number ? `Account # ${workOrder.account_number}` : 'Account # -');
        setTextById('techSubscriberContact', workOrder.contact_number || '-');
        setTextById('techSubscriberEmail', workOrder.email || '-');
        setTextById('techSubscriberAddress', workOrder.address || '-');

        setValueById('techSelectedWorkOrderId', workOrder.id || '');
        setValueById('techCompleteWorkOrderId', workOrder.id || '');
        setValueById('techNoteWorkOrderId', workOrder.id || '');

        renderNotes(notes);
        resetForms();

        hideModalLoading();
    }

    function renderNotes(notes) {
        const host = document.getElementById('techNotesList');
        const count = document.getElementById('techNotesCount');

        if (count) count.textContent = `${notes.length} note${notes.length === 1 ? '' : 's'}`;
        if (!host) return;

        if (!notes.length) {
            host.innerHTML = `<div class="text-muted text-center py-3">No notes yet.</div>`;
            return;
        }

        host.innerHTML = notes.map((note) => `
            <div class="tech-note-item">
                <div class="d-flex justify-content-between gap-2 mb-1">
                    <div class="fw-semibold">${escapeHtml(note.full_name || note.username || 'Technician')}</div>
                    <div class="small text-muted">${escapeHtml(formatDateTime(note.created_at || '-'))}</div>
                </div>
                <div class="text-muted">${escapeHtml(note.note || '-')}</div>
            </div>
        `).join('');
    }

    async function timeIn() {
        const formData = new FormData();

        withLocation(async (coords) => {
            if (coords) {
                formData.append('latitude', coords.latitude);
                formData.append('longitude', coords.longitude);
            }

            await postAction('/api/v1/technician-portal/attendance/time-in', formData, 'Timed in successfully.');
            await loadDashboard();
        });
    }

    async function timeOut() {
        await postAction('/api/v1/technician-portal/attendance/time-out', new FormData(), 'Timed out successfully.');
        await loadDashboard();
    }

    async function checkIn() {
        const workOrderId = getSelectedWorkOrderId();
        if (!workOrderId) return;

        const formData = new FormData();
        formData.append('work_order_id', workOrderId);

        withLocation(async (coords) => {
            if (coords) {
                formData.append('latitude', coords.latitude);
                formData.append('longitude', coords.longitude);
            }

            await postAction('/api/v1/technician-portal/work-orders/check-in', formData, 'GPS check-in saved.');
            await loadWorkOrderDetails(workOrderId);
            await loadDashboard();
        });
    }

    async function startWork() {
        const workOrderId = getSelectedWorkOrderId();
        if (!workOrderId) return;

        const formData = new FormData();
        formData.append('work_order_id', workOrderId);

        await postAction('/api/v1/technician-portal/work-orders/start', formData, 'Work started.');
        await loadWorkOrderDetails(workOrderId);
        await loadDashboard();
    }

    async function completeWork(event) {
        event.preventDefault();

        const form = event.currentTarget;
        const workOrderId = String(form.querySelector('[name="work_order_id"]')?.value || '').trim();
        const notes = String(form.querySelector('[name="completion_notes"]')?.value || '').trim();

        if (!workOrderId || !notes) {
            showToast('warning', 'Completion notes are required.');
            return;
        }

        await postAction('/api/v1/technician-portal/work-orders/complete', new FormData(form), 'Work completed.');
        await loadWorkOrderDetails(workOrderId);
        await loadDashboard();
    }

    async function addNote(event) {
        event.preventDefault();

        const form = event.currentTarget;
        const workOrderId = String(form.querySelector('[name="work_order_id"]')?.value || '').trim();
        const note = String(form.querySelector('[name="note"]')?.value || '').trim();

        if (!workOrderId || !note) {
            showToast('warning', 'Note is required.');
            return;
        }

        await postAction('/api/v1/technician-portal/work-orders/add-note', new FormData(form), 'Note added.');
        await loadWorkOrderDetails(workOrderId);
    }

    async function postAction(url, formData, fallbackMessage) {
        try {
            const response = await apiPost(url, formData);
            const data = response.data || response;

            showToast('success', data.message || fallbackMessage);
            return data;
        } catch (error) {
            showToast('error', error.message || 'Action failed.');
            throw error;
        }
    }

    function withLocation(callback) {
        if (!navigator.geolocation) {
            callback(null);
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                callback({
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                });
            },
            () => callback(null),
            {
                enableHighAccuracy: true,
                timeout: 8000,
                maximumAge: 0,
            }
        );
    }

    function getSelectedWorkOrderId() {
        const value = document.getElementById('techSelectedWorkOrderId')?.value || '';

        if (!value) {
            showToast('warning', 'Unable to determine work order ID.');
            return '';
        }

        return value;
    }

    function openModal() {
        const modalEl = document.getElementById('techWorkOrderModal');
        if (!modalEl) return;

        if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    }

    function showModalLoading() {
        document.getElementById('techModalLoading')?.classList.remove('d-none');
        document.getElementById('techModalContent')?.classList.add('d-none');
    }

    function hideModalLoading() {
        document.getElementById('techModalLoading')?.classList.add('d-none');
        document.getElementById('techModalContent')?.classList.remove('d-none');
    }

    function resetForms() {
        refs.completeForm?.reset();
        refs.noteForm?.reset();

        if (state.selectedWorkOrder?.id) {
            setValueById('techCompleteWorkOrderId', state.selectedWorkOrder.id);
            setValueById('techNoteWorkOrderId', state.selectedWorkOrder.id);
        }
    }

    async function apiGet(url) {
        const response = await fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        const json = await response.json().catch(() => ({}));

        if (!response.ok || json.success === false || json.ok === false) {
            throw new Error(json.error || json.message || `Request failed: ${response.status}`);
        }

        return json;
    }

    async function apiPost(url, formData) {
        const response = await fetch(url, {
            method: 'POST',
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            body: formData,
        });

        const json = await response.json().catch(() => ({}));

        if (!response.ok || json.success === false || json.ok === false) {
            throw new Error(json.error || json.message || `Request failed: ${response.status}`);
        }

        return json;
    }

    function statusBadge(status) {
        const value = String(status || '-').toUpperCase();
        let cls = 'bg-secondary';

        if (value === 'ASSIGNED') cls = 'bg-primary';
        if (value === 'IN_PROGRESS') cls = 'bg-info text-dark';
        if (value === 'COMPLETED') cls = 'bg-success';
        if (value === 'FAILED') cls = 'bg-danger';
        if (value === 'CANCELLED') cls = 'bg-dark';

        return `<span class="badge ${cls}">${escapeHtml(value.replaceAll('_', ' '))}</span>`;
    }

    function formatDateTime(value) {
        if (!value || value === '-') return '-';

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

    function formatTime(value) {
        if (!value || value === '-') return '-';

        const parts = String(value).split(':');
        if (parts.length < 2) return value;

        const date = new Date();
        date.setHours(Number(parts[0]), Number(parts[1]), 0, 0);

        return date.toLocaleTimeString('en-PH', {
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function setText(el, value) {
        if (el) el.textContent = value;
    }

    function setTextById(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    }

    function setValueById(id, value) {
        const el = document.getElementById(id);
        if (el) el.value = value;
    }

    function showToast(icon = 'success', title = '') {
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
            timer: icon === 'error' ? 4500 : 3000,
            timerProgressBar: true,
        });
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }
});