document.addEventListener('DOMContentLoaded', () => {
    const app = document.querySelector('[data-technician-management-page="index"]');
    if (!app || !window.NX) return;

    const { api, dom, util } = window.NX;
    const { $, html, text } = dom;
    const { escape, safeArray, formatDateTime } = util;

    const state = {
        technicians: [],
        summary: {},
        workOrderSummary: {},
        selectedTechnician: null,
        selectedDetailsData: null,
        dispatch: {
            unassignedWorkOrders: [],
            availableTechnicians: [],
        },
        technicianPager: { currentPage: 1, rowsPerPage: 10 },
        dispatchPager: { currentPage: 1, rowsPerPage: 5 },
        workOrderPager: { currentPage: 1, rowsPerPage: 5 },
        attendancePager: { currentPage: 1, rowsPerPage: 5 },
    };

    const refs = {
        refreshBtn: $('#technicianRefreshBtn'),
        searchInput: $('#technicianSearchInput'),
        statusFilter: $('#technicianStatusFilter'),
        clearFilterBtn: $('#technicianClearFilterBtn'),
        tableHost: $('#technicianTableHost'),
        alert: $('#technicianManagementAlert'),
        totalCount: $('#technicianTotalCount'),
        availableCount: $('#technicianAvailableCount'),
        onSiteCount: $('#technicianOnSiteCount'),
        offlineCount: $('#technicianOfflineCount'),
        modal: $('#technicianDetailsModal'),
        modalTitle: $('#technicianModalTitle'),
        modalSubtitle: $('#technicianModalSubtitle'),
        detailsContent: $('#technicianDetailsContent'),
    };

    bindEvents();
    loadDashboard();

    function bindEvents() {
        refs.refreshBtn?.addEventListener('click', loadDashboard);

        refs.searchInput?.addEventListener('input', util.debounce(() => {
            state.technicianPager.currentPage = 1;
            renderTable();
        }, 200));

        refs.statusFilter?.addEventListener('change', () => {
            state.technicianPager.currentPage = 1;
            renderTable();
        });

        refs.clearFilterBtn?.addEventListener('click', () => {
            refs.searchInput.value = '';
            refs.statusFilter.value = '';
            state.technicianPager.currentPage = 1;
            renderTable();
        });

        refs.tableHost?.addEventListener('click', event => {
            const viewBtn = event.target.closest('[data-view-technician]');
            const statusBtn = event.target.closest('[data-update-status]');
            const assignBtn = event.target.closest('[data-assign-work-order]');

            if (viewBtn) openTechnicianDetails(viewBtn.dataset.viewTechnician);

            if (statusBtn) {
                updateTechnicianStatus(statusBtn.dataset.userId, statusBtn.dataset.updateStatus);
            }

            if (assignBtn) assignWorkOrder(assignBtn.dataset.assignWorkOrder);
        });

        refs.detailsContent?.addEventListener('submit', event => {
            const form = event.target.closest('#technicianProfileForm');
            if (!form) return;

            event.preventDefault();
            saveTechnicianProfile(form);
        });

        refs.detailsContent?.addEventListener('click', event => {
            const statusBtn = event.target.closest('[data-work-order-status]');
            if (!statusBtn) return;

            updateWorkOrderStatus(
                statusBtn.dataset.workOrderId,
                statusBtn.dataset.workOrderStatus
            );
        });

        document.addEventListener('click', event => {
            const pageBtn = event.target.closest('[data-tech-page]');
            const dispatchPageBtn = event.target.closest('[data-dispatch-page]');
            const woPageBtn = event.target.closest('[data-wo-page]');
            const logPageBtn = event.target.closest('[data-log-page]');

            if (pageBtn) {
                state.technicianPager.currentPage = Number(pageBtn.dataset.techPage || 1);
                renderTable();
            }

            if (dispatchPageBtn) {
                state.dispatchPager.currentPage = Number(dispatchPageBtn.dataset.dispatchPage || 1);
                renderDispatchBoard();
            }

            if (woPageBtn && state.selectedDetailsData) {
                state.workOrderPager.currentPage = Number(woPageBtn.dataset.woPage || 1);
                renderDetails(state.selectedDetailsData);
            }

            if (logPageBtn && state.selectedDetailsData) {
                state.attendancePager.currentPage = Number(logPageBtn.dataset.logPage || 1);
                renderDetails(state.selectedDetailsData);
            }
        });
    }

    async function loadDashboard() {
        setLoading();

        try {
            const response = await api.get('/api/v1/technician-management');

            const data =
                response?.data?.technicians ? response.data :
                    response?.data?.data?.technicians ? response.data.data :
                        response?.technicians ? response :
                            response?.data ? response.data : {};

            state.technicians = Array.isArray(data.technicians) ? data.technicians : [];
            state.summary = data.summary || {};
            state.workOrderSummary = data.work_order_summary || {};

            renderSummary();
            renderTable();

            await loadDispatchBoard();
        } catch (error) {
            console.error('Technician load failed:', error);
            html(refs.tableHost, `<div class="text-center py-5 text-danger">Failed to load technician management data.</div>`);
        }
    }

    async function loadDispatchBoard() {
        try {
            const response = await api.get('/api/v1/technician-management/dispatch');

            const data =
                response?.data?.unassigned_work_orders ? response.data :
                    response?.data?.data?.unassigned_work_orders ? response.data.data :
                        response?.unassigned_work_orders ? response :
                            response?.data ? response.data : {};

            state.dispatch.unassignedWorkOrders = safeArray(data.unassigned_work_orders);
            state.dispatch.availableTechnicians = safeArray(data.available_technicians);

            renderDispatchBoard();
        } catch (error) {
            console.error('Dispatch board load failed:', error);
        }
    }

    function setLoading() {
        html(refs.tableHost, `<div class="text-center py-5 text-muted">Loading technicians...</div>`);
    }

    function renderSummary() {
        text(refs.totalCount, state.summary.total || 0);
        text(refs.availableCount, state.summary.available || 0);
        text(refs.onSiteCount, state.summary.on_site || 0);
        text(refs.offlineCount, state.summary.offline || 0);
    }

    function renderWorkloadDashboardMarkup() {
        const summary = state.workOrderSummary || {};

        return `
            <div class="row g-3 mb-3">
                ${renderWorkloadCard('Active Jobs', summary.active_jobs || 0, 'bi-clipboard-pulse', 'text-primary')}
                ${renderWorkloadCard('On Site Jobs', summary.on_site_jobs || 0, 'bi-geo-alt', 'text-info')}
                ${renderWorkloadCard('Completed Today', summary.completed_today || 0, 'bi-check-circle', 'text-success')}
                ${renderWorkloadCard('Failed Today', summary.failed_today || 0, 'bi-x-circle', 'text-danger')}
            </div>
        `;
    }

    function renderWorkloadCard(label, value, icon, colorClass) {
        return `
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small text-muted">${escape(label)}</div>
                            <div class="fs-4 fw-bold">${escape(value)}</div>
                        </div>
                        <div class="fs-3 ${escape(colorClass)}">
                            <i class="bi ${escape(icon)}"></i>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    function getFilteredTechnicians() {
        const search = String(refs.searchInput?.value || '').toLowerCase().trim();
        const status = String(refs.statusFilter?.value || '').trim();

        return state.technicians.filter(row => {
            const searchable = [
                row.username,
                row.full_name,
                row.email,
                row.availability_status,
                row.employee_no,
                row.mobile_number,
                row.service_area,
                row.skill_level,
                row.vehicle,
                row.vehicle_plate,
            ].join(' ').toLowerCase();

            if (search && !searchable.includes(search)) return false;
            if (status && String(row.availability_status || '') !== status) return false;

            return true;
        });
    }

    function renderTable() {
        const rows = getFilteredTechnicians();

        if (!rows.length) {
            html(refs.tableHost, `
                ${renderWorkloadDashboardMarkup()}
                ${renderDispatchBoardMarkup()}
                <div class="card border-0 shadow-sm">
                    <div class="text-center py-5 text-muted">No technicians found.</div>
                </div>
            `);
            return;
        }

        const pager = paginate(rows, state.technicianPager.currentPage, state.technicianPager.rowsPerPage);
        state.technicianPager.currentPage = pager.currentPage;

        html(refs.tableHost, `
            ${renderWorkloadDashboardMarkup()}
            ${renderDispatchBoardMarkup()}

            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table technician-table mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Technician</th>
                                    <th>Status</th>
                                    <th>Area / Level</th>
                                    <th>Active</th>
                                    <th>Completed</th>
                                    <th>Failed</th>
                                    <th>Success %</th>
                                    <th>Last Login</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>${pager.rows.map(renderRow).join('')}</tbody>
                        </table>
                    </div>
                    ${renderPagination(pager, 'tech')}
                </div>
            </div>
        `);

        renderDispatchBoard();
    }

    function renderDispatchBoardMarkup() {
        return `
            <div class="card border-0 shadow-sm mb-3" id="technicianDispatchBoard">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <div>
                            <div class="text-primary small fw-bold text-uppercase">
                                <i class="bi bi-send-check"></i> Dispatch Board
                            </div>
                            <h6 class="mb-0 fw-semibold">Unassigned Work Orders</h6>
                            <small class="text-muted">Assign open work orders to available technicians.</small>
                        </div>

                        <button class="btn btn-light border btn-sm" type="button" onclick="location.reload()">
                            <i class="bi bi-arrow-clockwise"></i> Refresh
                        </button>
                    </div>

                    <div id="technicianDispatchContent">
                        <div class="text-center py-4 text-muted">Loading dispatch board...</div>
                    </div>
                </div>
            </div>
        `;
    }

    function renderDispatchBoard() {
        const host = $('#technicianDispatchContent');
        if (!host) return;

        const rows = state.dispatch.unassignedWorkOrders;

        if (!rows.length) {
            html(host, `<div class="text-center py-4 text-muted border rounded">No unassigned open work orders.</div>`);
            return;
        }

        const pager = paginate(rows, state.dispatchPager.currentPage, state.dispatchPager.rowsPerPage);
        state.dispatchPager.currentPage = pager.currentPage;

        html(host, `
            <div class="table-responsive border rounded">
                <table class="table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>WO No.</th>
                            <th>Subscriber</th>
                            <th>Type</th>
                            <th>Priority</th>
                            <th>Schedule</th>
                            <th>Assign To</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>${pager.rows.map(renderDispatchRow).join('')}</tbody>
                </table>
            </div>
            ${renderPagination(pager, 'dispatch')}
        `);
    }

    function renderDispatchRow(row) {
        return `
            <tr>
                <td>
                    <div class="fw-semibold">${escape(row.work_order_no || '-')}</div>
                    <div class="small text-muted">${escape(row.title || '-')}</div>
                </td>
                <td>
                    <div class="fw-semibold">${escape(row.subscriber_name || '-')}</div>
                    <div class="small text-muted">${escape(row.account_number || '-')}</div>
                </td>
                <td>${escape(row.work_order_type || '-')}</td>
                <td>${priorityBadge(row.priority)}</td>
                <td>
                    <div>${escape(row.scheduled_date || '-')}</div>
                    <div class="small text-muted">${escape(row.scheduled_time || '')}</div>
                </td>
                <td>
                    <select class="form-select form-select-sm" id="dispatchTechnician_${escape(row.id)}">
                        <option value="">Select technician</option>
                        ${state.dispatch.availableTechnicians.map(tech => `
                            <option value="${escape(tech.id)}">
                                ${escape(tech.full_name || tech.username || 'Technician')}
                                - ${escape(tech.availability_status || 'OFFLINE')}
                                - ${escape(tech.active_work_orders || 0)} active
                            </option>
                        `).join('')}
                    </select>
                </td>
                <td class="text-end">
                    <button class="btn btn-sm btn-primary" type="button" data-assign-work-order="${escape(row.id)}">
                        Assign
                    </button>
                </td>
            </tr>
        `;
    }

    async function assignWorkOrder(workOrderId) {
        const select = document.getElementById(`dispatchTechnician_${workOrderId}`);
        const technicianId = select ? select.value : '';

        if (!technicianId) {
            showAlert('warning', 'Please select a technician first.');
            return;
        }

        try {
            const body = new FormData();
            body.append('work_order_id', workOrderId);
            body.append('technician_id', technicianId);

            await api.post('/api/v1/technician-management/assign-work-order', body);

            showAlert('success', 'Work order assigned successfully.');
            await loadDashboard();
        } catch (error) {
            console.error(error);
            showAlert('danger', 'Failed to assign work order.');
        }
    }

    function renderRow(row) {
        const name = row.full_name || row.username || 'Technician';
        const initials = getInitials(name);

        return `
            <tr>
                <td class="ps-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="technician-avatar">${escape(initials)}</div>
                        <div>
                            <div class="fw-semibold">${escape(name)}</div>
                            <div class="small text-muted">${escape(row.email || row.username || '-')}</div>
                            <div class="small text-muted">Emp No: ${escape(row.employee_no || '-')}</div>
                        </div>
                    </div>
                </td>
                <td>${statusBadge(row.availability_status)}</td>
                <td>
                    <div class="fw-semibold">${escape(row.service_area || '-')}</div>
                    <div class="small text-muted">${escape(row.skill_level || '-')}</div>
                </td>
                <td><span class="fw-semibold">${escape(row.active_work_orders || 0)}</span></td>
                <td><span class="fw-semibold text-success">${escape(row.completed_work_orders || 0)}</span></td>
                <td><span class="fw-semibold text-danger">${escape(row.failed_work_orders || 0)}</span></td>
                <td><span class="badge bg-success">${escape(row.success_rate || 0)}%</span></td>
                <td><span class="small text-muted">${escape(formatDateTime(row.last_login || '') || '-')}</span></td>
                <td class="text-end pe-4">
                    <div class="btn-group">
                        <button class="btn btn-sm btn-light border" data-view-technician="${escape(row.id)}">View</button>
                        <button class="btn btn-sm btn-light border dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown"></button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            ${renderStatusOption(row.id, 'AVAILABLE', 'Available')}
                            ${renderStatusOption(row.id, 'BUSY', 'Busy')}
                            ${renderStatusOption(row.id, 'ON_SITE', 'On Site')}
                            ${renderStatusOption(row.id, 'TRAVELING', 'Traveling')}
                            ${renderStatusOption(row.id, 'ON_BREAK', 'On Break')}
                            ${renderStatusOption(row.id, 'OFFLINE', 'Offline')}
                        </ul>
                    </div>
                </td>
            </tr>
        `;
    }

    function renderStatusOption(userId, status, label) {
        return `
            <li>
                <button class="dropdown-item" data-user-id="${escape(userId)}" data-update-status="${escape(status)}" type="button">
                    ${escape(label)}
                </button>
            </li>
        `;
    }

    async function openTechnicianDetails(id) {
        html(refs.detailsContent, `<div class="text-center py-5 text-muted">Loading details...</div>`);

        state.workOrderPager.currentPage = 1;
        state.attendancePager.currentPage = 1;

        const modal = bootstrap.Modal.getOrCreateInstance(refs.modal);
        modal.show();

        try {
            const response = await api.get(`/api/v1/technician-management/technicians/${id}`);

            const data =
                response?.data?.technician ? response.data :
                    response?.data?.data?.technician ? response.data.data :
                        response?.technician ? response :
                            response?.data ? response.data : {};

            state.selectedTechnician = data.technician || null;
            renderDetails(data);
        } catch (error) {
            console.error(error);
            html(refs.detailsContent, `<div class="text-center py-5 text-danger">Failed to load technician details.</div>`);
        }
    }

    function renderDetails(data) {
        state.selectedDetailsData = data;

        const tech = data.technician || {};
        const workOrders = safeArray(data.work_orders);
        const logs = safeArray(data.attendance_logs);

        text(refs.modalTitle, tech.full_name || tech.username || 'Technician Details');
        text(refs.modalSubtitle, `${tech.email || '-'} • ${tech.availability_status || 'OFFLINE'}`);

        html(refs.detailsContent, `
            <ul class="nav nav-tabs technician-tabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#technicianOverviewTab" type="button">
                        <i class="bi bi-speedometer2 me-1"></i> Overview
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#technicianProfileTab" type="button">
                        <i class="bi bi-person-vcard me-1"></i> Profile
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#technicianWorkOrdersTab" type="button">
                        <i class="bi bi-clipboard-check me-1"></i> Work Orders
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#technicianAttendanceTab" type="button">
                        <i class="bi bi-clock-history me-1"></i> Attendance Logs
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active technician-tab-pane" id="technicianOverviewTab" role="tabpanel">
                    ${renderOverviewTab(tech)}
                </div>

                <div class="tab-pane fade technician-tab-pane" id="technicianProfileTab" role="tabpanel">
                    ${renderProfileForm(tech)}
                </div>

                <div class="tab-pane fade technician-tab-pane" id="technicianWorkOrdersTab" role="tabpanel">
                    <div class="technician-detail-section-title">Assigned Work Orders</div>
                    ${renderWorkOrders(workOrders)}
                </div>

                <div class="tab-pane fade technician-tab-pane" id="technicianAttendanceTab" role="tabpanel">
                    <div class="technician-detail-section-title">Attendance Logs</div>
                    ${renderAttendanceLogs(logs)}
                </div>
            </div>
        `);
    }

    function renderOverviewTab(tech) {
        return `
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="technician-metric-card">
                        <div class="technician-detail-section-title">Profile</div>
                        <div class="technician-info-list">
                            ${renderInfoItem('Name', tech.full_name || '-')}
                            ${renderInfoItem('Email', tech.email || '-')}
                            ${renderInfoItem('Username', tech.username || '-')}
                            <div>${statusBadge(tech.availability_status)}</div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="technician-metric-card">
                        <div class="technician-detail-section-title">Today</div>
                        <div class="technician-info-list">
                            ${renderInfoItem('Time In', formatDateTime(tech.time_in_at || '') || '-')}
                            ${renderInfoItem('Time Out', formatDateTime(tech.time_out_at || '') || '-')}
                            ${renderInfoItem('Notes', tech.notes || 'No notes today.')}
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="technician-metric-card">
                        <div class="technician-detail-section-title">Field Info</div>
                        <div class="technician-info-list">
                            ${renderInfoItem('Service Area', tech.service_area || '-')}
                            ${renderInfoItem('Level', tech.skill_level || '-')}
                            ${renderInfoItem('Vehicle', tech.vehicle || '-')}
                            ${renderInfoItem('Plate No.', tech.vehicle_plate || '-')}
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                ${renderMetricCard('Total Jobs', tech.total_work_orders || 0)}
                ${renderMetricCard('Active Jobs', tech.active_work_orders || 0)}
                ${renderMetricCard('Completed', tech.completed_work_orders || 0, 'text-success')}
                ${renderMetricCard('Failed', tech.failed_work_orders || 0, 'text-danger')}
                ${renderMetricCard('Success Rate', `${tech.success_rate || 0}%`, 'text-primary')}
                ${renderMetricCard('Completion Rate', `${tech.completion_rate || 0}%`, 'text-primary')}
            </div>
        `;
    }

    function renderInfoItem(label, value) {
        return `
            <div class="technician-info-item">
                <div class="technician-info-label">${escape(label)}</div>
                <div class="technician-info-value">${escape(value)}</div>
            </div>
        `;
    }

    function renderMetricCard(label, value, valueClass = '') {
        return `
            <div class="col-md-4">
                <div class="technician-metric-card">
                    <div class="technician-metric-label">${escape(label)}</div>
                    <div class="technician-metric-value ${escape(valueClass)}">${escape(value)}</div>
                </div>
            </div>
        `;
    }

    function renderProfileForm(tech) {
        return `
            <form id="technicianProfileForm" class="card border mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                        <div>
                            <div class="technician-detail-section-title mb-1">Technician Profile</div>
                            <div class="small text-muted">Complete the technician field operations profile.</div>
                        </div>
                        <button class="btn btn-primary btn-sm" type="submit">
                            <i class="bi bi-save"></i> Save Profile
                        </button>
                    </div>

                    <input type="hidden" name="user_id" value="${escape(tech.id || '')}">

                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Employee No.</label>
                            <input class="form-control" name="employee_no" value="${escape(tech.employee_no || '')}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Mobile Number</label>
                            <input class="form-control" name="mobile_number" value="${escape(tech.mobile_number || '')}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Service Area</label>
                            <input class="form-control" name="service_area" value="${escape(tech.service_area || '')}" placeholder="Area / Barangay">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Level</label>
                            <select class="form-select" name="skill_level">
                                ${option('JUNIOR', tech.skill_level)}
                                ${option('SENIOR', tech.skill_level)}
                                ${option('LEAD', tech.skill_level)}
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Vehicle</label>
                            <input class="form-control" name="vehicle" value="${escape(tech.vehicle || '')}" placeholder="Motor / Van">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Plate No.</label>
                            <input class="form-control" name="vehicle_plate" value="${escape(tech.vehicle_plate || '')}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Emergency Contact</label>
                            <input class="form-control" name="emergency_contact" value="${escape(tech.emergency_contact || '')}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Emergency Number</label>
                            <input class="form-control" name="emergency_number" value="${escape(tech.emergency_number || '')}">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-semibold">Profile Notes</label>
                            <input class="form-control" name="notes" value="${escape(tech.profile_notes || '')}" placeholder="Remarks, schedule limitations, field notes">
                        </div>
                    </div>
                </div>
            </form>
        `;
    }

    async function saveTechnicianProfile(form) {
        const button = form.querySelector('button[type="submit"]');
        const originalText = button ? button.innerHTML : '';

        try {
            if (button) {
                button.disabled = true;
                button.innerHTML = 'Saving...';
            }

            const body = new FormData(form);
            const response = await api.post('/api/v1/technician-management/profile', body);

            const data =
                response?.data?.id ? response.data :
                    response?.data?.data?.id ? response.data.data :
                        response?.id ? response :
                            response?.data ? response.data : null;

            if (data && state.selectedDetailsData) {
                state.selectedDetailsData.technician = data;
                state.selectedTechnician = data;
                renderDetails(state.selectedDetailsData);
            }

            showAlert('success', 'Technician profile updated.');
            await loadDashboard();
        } catch (error) {
            console.error(error);
            showAlert('danger', 'Failed to save technician profile.');
        } finally {
            if (button) {
                button.disabled = false;
                button.innerHTML = originalText;
            }
        }
    }

    function renderWorkOrders(rows) {
        if (!rows.length) {
            return `<div class="text-muted border rounded p-3">No work orders assigned.</div>`;
        }

        const pager = paginate(rows, state.workOrderPager.currentPage, state.workOrderPager.rowsPerPage);
        state.workOrderPager.currentPage = pager.currentPage;

        return `
            <div class="table-responsive border rounded">
                <table class="table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>WO No.</th>
                            <th>Subscriber</th>
                            <th>Type</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Schedule</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${pager.rows.map(row => `
                            <tr>
                                <td class="fw-semibold">${escape(row.work_order_no || '-')}</td>
                                <td>${escape(row.subscriber_name || '-')}</td>
                                <td>${escape(row.work_order_type || row.type || '-')}</td>
                                <td>${priorityBadge(row.priority)}</td>
                                <td>${workOrderStatusBadge(row.status)}</td>
                                <td>${escape(row.scheduled_date || '-')} ${escape(row.scheduled_time || '')}</td>
                                <td class="text-end">
                                    ${renderWorkOrderActions(row)}
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
            ${renderPagination(pager, 'wo')}
        `;
    }

    function renderWorkOrderActions(row) {
        const status = String(row.status || '').toUpperCase();
        const id = escape(row.id || '');

        if (['COMPLETED', 'FAILED', 'CANCELLED'].includes(status)) {
            return `<span class="small text-muted">No actions</span>`;
        }

        return `
            <div class="btn-group btn-group-sm">
                ${status === 'ASSIGNED' ? `
                    <button class="btn btn-outline-primary" data-work-order-id="${id}" data-work-order-status="IN_PROGRESS">
                        Start
                    </button>
                ` : ''}

                ${['ASSIGNED', 'IN_PROGRESS'].includes(status) ? `
                    <button class="btn btn-outline-info" data-work-order-id="${id}" data-work-order-status="ON_SITE">
                        On Site
                    </button>
                ` : ''}

                ${['ASSIGNED', 'IN_PROGRESS', 'ON_SITE'].includes(status) ? `
                    <button class="btn btn-outline-success" data-work-order-id="${id}" data-work-order-status="COMPLETED">
                        Complete
                    </button>
                    <button class="btn btn-outline-danger" data-work-order-id="${id}" data-work-order-status="FAILED">
                        Failed
                    </button>
                ` : ''}
            </div>
        `;
    }

    async function updateWorkOrderStatus(workOrderId, status) {
        const note = ['COMPLETED', 'FAILED'].includes(status)
            ? prompt(`Enter note for ${status.replaceAll('_', ' ')}:`) || ''
            : '';

        try {
            const body = new FormData();
            body.append('work_order_id', workOrderId);
            body.append('status', status);
            body.append('note', note);

            await api.post('/api/v1/technician-management/work-order-status', body);

            showAlert('success', `Work order updated to ${status}.`);

            if (state.selectedTechnician?.id) {
                await openTechnicianDetails(state.selectedTechnician.id);
            }

            await loadDashboard();
        } catch (error) {
            console.error(error);
            showAlert('danger', 'Failed to update work order status.');
        }
    }

    function renderAttendanceLogs(rows) {
        if (!rows.length) {
            return `<div class="text-muted border rounded p-3">No attendance logs found.</div>`;
        }

        const pager = paginate(rows, state.attendancePager.currentPage, state.attendancePager.rowsPerPage);
        state.attendancePager.currentPage = pager.currentPage;

        return `
            <div class="table-responsive border rounded">
                <table class="table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Time</th>
                            <th>Action</th>
                            <th>Old Status</th>
                            <th>New Status</th>
                            <th>Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${pager.rows.map(row => `
                            <tr>
                                <td>${escape(formatDateTime(row.created_at || '') || '-')}</td>
                                <td>${escape(row.action || '-')}</td>
                                <td>${escape(row.old_status || '-')}</td>
                                <td>${escape(row.new_status || '-')}</td>
                                <td>${escape(row.note || '-')}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
            ${renderPagination(pager, 'log')}
        `;
    }

    async function updateTechnicianStatus(userId, status) {
        try {
            const body = new FormData();
            body.append('user_id', userId);
            body.append('status', status);

            await api.post('/api/v1/technician-management/status', body);

            showAlert('success', `Technician status updated to ${status}.`);
            await loadDashboard();
        } catch (error) {
            console.error(error);
            showAlert('danger', 'Failed to update technician status.');
        }
    }

    function paginate(rows, currentPage, rowsPerPage) {
        const totalRows = rows.length;
        const totalPages = Math.max(1, Math.ceil(totalRows / rowsPerPage));
        const safePage = Math.min(Math.max(1, currentPage), totalPages);
        const start = (safePage - 1) * rowsPerPage;
        const end = start + rowsPerPage;

        return {
            rows: rows.slice(start, end),
            currentPage: safePage,
            rowsPerPage,
            totalRows,
            totalPages,
            start: totalRows ? start + 1 : 0,
            end: Math.min(end, totalRows),
        };
    }

    function renderPagination(pager, type) {
        if (pager.totalRows <= 0) return '';

        if (pager.totalPages <= 1) {
            return `
                <div class="d-flex justify-content-between align-items-center px-4 py-3 border-top small text-muted">
                    <span>Showing ${pager.start}-${pager.end} of ${pager.totalRows}</span>
                </div>
            `;
        }

        const attr =
            type === 'wo' ? 'data-wo-page' :
                type === 'log' ? 'data-log-page' :
                    type === 'dispatch' ? 'data-dispatch-page' :
                        'data-tech-page';

        const pages = [];

        for (let i = 1; i <= pager.totalPages; i++) {
            pages.push(`
                <button class="btn btn-sm ${i === pager.currentPage ? 'btn-primary' : 'btn-light border'}" ${attr}="${i}" type="button">
                    ${i}
                </button>
            `);
        }

        return `
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-4 py-3 border-top">
                <div class="small text-muted">Showing ${pager.start}-${pager.end} of ${pager.totalRows}</div>
                <div class="btn-group">
                    <button class="btn btn-sm btn-light border" ${attr}="${Math.max(1, pager.currentPage - 1)}" ${pager.currentPage <= 1 ? 'disabled' : ''} type="button">
                        Previous
                    </button>
                    ${pages.join('')}
                    <button class="btn btn-sm btn-light border" ${attr}="${Math.min(pager.totalPages, pager.currentPage + 1)}" ${pager.currentPage >= pager.totalPages ? 'disabled' : ''} type="button">
                        Next
                    </button>
                </div>
            </div>
        `;
    }

    function priorityBadge(priority) {
        const value = String(priority || 'MEDIUM').toUpperCase();

        if (value === 'URGENT') return `<span class="badge bg-danger">${escape(value)}</span>`;
        if (value === 'HIGH') return `<span class="badge bg-warning text-dark">${escape(value)}</span>`;
        if (value === 'LOW') return `<span class="badge bg-secondary">${escape(value)}</span>`;

        return `<span class="badge bg-primary">${escape(value)}</span>`;
    }

    function workOrderStatusBadge(status) {
        const value = String(status || '-').toUpperCase();

        if (value === 'COMPLETED') return `<span class="badge bg-success">${escape(value)}</span>`;
        if (value === 'FAILED') return `<span class="badge bg-danger">${escape(value)}</span>`;
        if (value === 'ON_SITE') return `<span class="badge bg-info text-dark">${escape(value.replaceAll('_', ' '))}</span>`;
        if (value === 'IN_PROGRESS') return `<span class="badge bg-primary">${escape(value.replaceAll('_', ' '))}</span>`;
        if (value === 'ASSIGNED') return `<span class="badge bg-warning text-dark">${escape(value)}</span>`;

        return `<span class="badge bg-secondary">${escape(value.replaceAll('_', ' '))}</span>`;
    }

    function option(value, selectedValue) {
        const selected = String(value) === String(selectedValue || 'JUNIOR') ? 'selected' : '';
        return `<option value="${escape(value)}" ${selected}>${escape(value)}</option>`;
    }

    function statusBadge(status) {
        const value = String(status || 'OFFLINE').toUpperCase();
        const className = `technician-status-${value.toLowerCase().replaceAll('_', '-')}`;

        return `<span class="technician-status-badge ${className}">${escape(value.replaceAll('_', ' '))}</span>`;
    }

    function getInitials(name) {
        return String(name || 'T')
            .split(' ')
            .filter(Boolean)
            .slice(0, 2)
            .map(part => part[0])
            .join('')
            .toUpperCase();
    }

    function showAlert(type, message) {
        html(refs.alert, `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${escape(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `);
    }
});