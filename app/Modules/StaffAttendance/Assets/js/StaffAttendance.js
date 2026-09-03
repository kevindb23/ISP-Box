(function () {
    const state = {
        attendance: null,
        staff: [],
        myLogs: [],
        logs: [],
    };

    document.addEventListener('DOMContentLoaded', () => {
        bindEvents();
        loadAttendance();
    });

    function bindEvents() {
        document.getElementById('staffAttendanceRefreshBtn')?.addEventListener('click', loadAttendance);
        document.getElementById('staffTimeInBtn')?.addEventListener('click', timeIn);
        document.getElementById('staffTimeOutBtn')?.addEventListener('click', timeOut);
        document.getElementById('staffDutyStatusBtn')?.addEventListener('click', updateDutyStatus);
        document.getElementById('staffHistoryLoadBtn')?.addEventListener('click', loadHistory);
    }

    async function loadAttendance() {
        clearAlert();

        try {
            const response = await apiGet('/api/v1/staff-attendance/today');
            const data = response.data || response;

            state.attendance = data.attendance || null;
            state.staff = data.staff || [];
            state.myLogs = data.my_logs || [];
            state.logs = data.logs || [];

            renderMyAttendance(state.attendance);
            renderStaffTable(state.staff);
            renderMyLogs(state.myLogs);
            renderAllLogs(state.logs);
            if (document.getElementById('staffHistoryBody')) await loadHistory();
        } catch (error) {
            showAlert(error.message || 'Unable to load attendance.', 'danger');
        }
    }

    async function loadHistory() {
        const body=document.getElementById('staffHistoryBody'); if(!body) return;
        const from=document.getElementById('staffHistoryFrom')?.value||'';
        const to=document.getElementById('staffHistoryTo')?.value||'';
        body.innerHTML='<tr><td colspan="7" class="text-center text-muted py-4">Loading history...</td></tr>';
        try {
            const response=await apiGet(`/api/v1/staff-attendance/history?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`);
            const rows=response?.data?.items||response?.items||[];
            if(!rows.length){body.innerHTML='<tr><td colspan="7" class="text-center text-muted py-4">No attendance records in this period.</td></tr>';return;}
            body.innerHTML=rows.map(row=>`<tr><td><div class="fw-semibold">${escapeHtml(row.full_name||row.username||'-')}</div><div class="small text-muted">${escapeHtml(row.email||'')}</div></td><td>${escapeHtml(row.role||'-')}</td><td>${escapeHtml(row.attendance_date||'-')}</td><td>${escapeHtml(formatDateTime(row.time_in_at||'-'))}</td><td>${escapeHtml(formatDateTime(row.time_out_at||'-'))}</td><td>${escapeHtml(formatDuration(row.duty_minutes))}</td><td>${statusBadge(row.time_out_at?'OFFLINE':row.status)}</td></tr>`).join('');
        } catch(error){body.innerHTML=`<tr><td colspan="7" class="text-center text-danger py-4">${escapeHtml(error.message||'Unable to load history.')}</td></tr>`;}
    }

    function formatDuration(minutes){const value=Number(minutes||0);return `${Math.floor(value/60)}h ${value%60}m`;}

    async function timeIn() {
        const btn = document.getElementById('staffTimeInBtn');

        setButtonLoading(btn, true, 'Timing in...');

        try {
            const response = await apiPost('/api/v1/staff-attendance/time-in', new FormData());
            const data = response.data || response;

            showToast('success', data.message || response.message || 'Time in successful.');
            await loadAttendance();
        } catch (error) {
            showToast('error', error.message || 'Unable to time in.');
        } finally {
            setButtonLoading(btn, false);
        }
    }

    async function timeOut() {
        const btn = document.getElementById('staffTimeOutBtn');

        setButtonLoading(btn, true, 'Timing out...');

        try {
            const response = await apiPost('/api/v1/staff-attendance/time-out', new FormData());
            const data = response.data || response;

            showToast('success', data.message || response.message || 'Time out successful.');
            await loadAttendance();
        } catch (error) {
            showToast('error', error.message || 'Unable to time out.');
        } finally {
            setButtonLoading(btn, false);
        }
    }

    async function updateDutyStatus() {
        const btn = document.getElementById('staffDutyStatusBtn');
        const select = document.getElementById('staffDutyStatusSelect');
        const status = select?.value || 'AVAILABLE';

        const formData = new FormData();
        formData.append('status', status);

        setButtonLoading(btn, true, 'Updating...');

        try {
            const response = await apiPost('/api/v1/staff-attendance/status', formData);
            const data = response.data || response;

            showToast('success', data.message || response.message || 'Duty status updated.');
            await loadAttendance();
        } catch (error) {
            showToast('error', error.message || 'Unable to update duty status.');
        } finally {
            setButtonLoading(btn, false);
        }
    }

    function renderMyAttendance(attendance) {
        const status = attendance?.status || 'OFFLINE';

        setText('staffMyStatus', formatStatus(status));
        setText('staffMyTimeIn', formatDateTime(attendance?.time_in_at || '-'));
        setText('staffMyTimeOut', formatDateTime(attendance?.time_out_at || '-'));

        const statusSelect = document.getElementById('staffDutyStatusSelect');
        if (statusSelect && status !== 'OFFLINE') {
            statusSelect.value = status;
        }

        const hasTimedIn = attendance && attendance.time_in_at && !attendance.time_out_at;
        const hasTimedOut = attendance && attendance.time_out_at;

        const timeInBtn = document.getElementById('staffTimeInBtn');
        const timeOutBtn = document.getElementById('staffTimeOutBtn');
        const dutyBtn = document.getElementById('staffDutyStatusBtn');

        if (timeInBtn) timeInBtn.disabled = Boolean(hasTimedIn || hasTimedOut);
        if (timeOutBtn) timeOutBtn.disabled = !hasTimedIn;
        if (dutyBtn) dutyBtn.disabled = !hasTimedIn;
        if (statusSelect) statusSelect.disabled = !hasTimedIn;
    }

    function renderStaffTable(items) {
        const body = document.getElementById('staffAttendanceBody');

        if (!body) return;

        const rows = Array.isArray(items) ? items : [];

        if (!rows.length) {
            body.innerHTML = `<tr><td colspan="5" class="text-muted text-center py-4">No staff attendance records today.</td></tr>`;
            return;
        }

        body.innerHTML = rows.map((item) => {
            const name = item.full_name || item.username || '-';

            return `
                <tr>
                    <td class="ps-4">
                        <div class="fw-semibold">${escapeHtml(name)}</div>
                        <div class="small text-muted">${escapeHtml(item.email || '')}</div>
                    </td>
                    <td>${escapeHtml(item.role || '-')}</td>
                    <td>${statusBadge(item.status)}</td>
                    <td>${escapeHtml(formatDateTime(item.time_in_at || '-'))}</td>
                    <td class="pe-4">${escapeHtml(formatDateTime(item.time_out_at || '-'))}</td>
                </tr>
            `;
        }).join('');
    }

    function renderMyLogs(items) {
        renderLogList('staffMyLogs', items, false);
    }

    function renderAllLogs(items) {
        renderLogList('staffAttendanceLogs', items, true);
    }

    function renderLogList(hostId, items, showStaffName) {
        const host = document.getElementById(hostId);

        if (!host) return;

        const rows = Array.isArray(items) ? items : [];

        if (!rows.length) {
            host.innerHTML = `<div class="text-muted text-center py-4">No activity logs found.</div>`;
            return;
        }

        host.innerHTML = rows.map((item) => {
            const name = item.full_name || item.username || 'Staff';
            const action = String(item.action || '-').toUpperCase();
            const oldStatus = item.old_status || '-';
            const newStatus = item.new_status || '-';

            return `
                <div class="staff-attendance-log-item">
                    <div class="d-flex justify-content-between gap-3">
                        <div>
                            <div class="fw-semibold">
                                ${showStaffName ? `${escapeHtml(name)} · ` : ''}
                                ${actionBadge(action)}
                            </div>
                            <div class="small text-muted mt-1">
                                ${escapeHtml(formatLogText(action, oldStatus, newStatus))}
                            </div>
                            ${item.ip_address ? `<div class="small text-muted">IP: ${escapeHtml(item.ip_address)}</div>` : ''}
                        </div>

                        <div class="small text-muted text-end">
                            ${escapeHtml(formatDateTime(item.created_at || '-'))}
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    function formatLogText(action, oldStatus, newStatus) {
        if (action === 'TIME_IN') {
            return 'Timed in and became Available';
        }

        if (action === 'TIME_OUT') {
            return `Timed out from ${formatStatus(oldStatus)}`;
        }

        if (action === 'STATUS_CHANGE') {
            return `${formatStatus(oldStatus)} → ${formatStatus(newStatus)}`;
        }

        return action;
    }

    function actionBadge(action) {
        let cls = 'bg-secondary';

        if (action === 'TIME_IN') cls = 'bg-success';
        if (action === 'TIME_OUT') cls = 'bg-danger';
        if (action === 'STATUS_CHANGE') cls = 'bg-primary';

        return `<span class="badge ${cls}">${escapeHtml(formatStatus(action))}</span>`;
    }

    async function apiGet(url) {
        return { ok: true, success: true, data: await window.NX.api.get(url) };
    }

    async function apiPost(url, formData) {
        return window.NX.api.form(url, formData);
    }

    function statusBadge(status) {
        const value = String(status || 'OFFLINE').toUpperCase();

        let cls = 'bg-secondary';

        if (value === 'AVAILABLE') cls = 'bg-success';
        if (value === 'BUSY') cls = 'bg-warning text-dark';
        if (value === 'ON_BREAK') cls = 'bg-info text-dark';
        if (value === 'TRAVELING') cls = 'bg-primary';
        if (value === 'ON_SITE') cls = 'bg-dark';
        if (value === 'OFFLINE') cls = 'bg-secondary';

        return `<span class="badge ${cls}">${escapeHtml(formatStatus(value))}</span>`;
    }

    function formatStatus(status) {
        return String(status || 'OFFLINE')
            .replaceAll('_', ' ')
            .toLowerCase()
            .replace(/\b\w/g, (char) => char.toUpperCase());
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

    function showAlert(message, type = 'danger') {
        const el = document.getElementById('staffAttendanceAlert');

        if (!el) return;

        el.innerHTML = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${escapeHtml(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
    }

    function clearAlert() {
        const el = document.getElementById('staffAttendanceAlert');
        if (el) el.innerHTML = '';
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

    function setText(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }
})();
