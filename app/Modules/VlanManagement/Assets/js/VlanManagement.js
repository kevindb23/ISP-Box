(function () {
    document.addEventListener('DOMContentLoaded', () => {
        const app = document.getElementById('vlanManagementApp');
        if (!app) return;

        const NX = window.NX || {};
        const dom = NX.dom || {};
        const util = NX.util || {};
        const ui = NX.ui || {};

        const $ = dom.$ ? dom.$.bind(dom) : (selector, root = document) => root.querySelector(selector);
        const $$ = dom.$$ ? dom.$$.bind(dom) : (selector, root = document) => Array.from(root.querySelectorAll(selector));
        const escape = util.escape || escapeHtml;

        const STORE_KEY = 'nexusbox-vlan-management-v20';

        const state = {
            tab: loadStoredTab() || 'cvlan',
            summary: {},
            vlans: [],
            mgmtVlans: [],
            olts: [],
            portsByOlt: {},
            search: ''
        };

        const els = {
            btnRefresh: $('#btnRefreshVlanManagement', app),
            btnAddVlan: $('#btnAddVlan', app),
            btnAddMgmtVlan: $('#btnAddMgmtVlan', app),
            search: $('#vlanManagementSearch', app),

            tabs: $$('[data-tab]', app),
            panes: $$('[data-tab-pane]', app),

            cVlanTbody: $('#cVlanTbody', app),
            sVlanTbody: $('#sVlanTbody', app),
            mgmtVlanTbody: $('#mgmtVlanTbody', app),

            summaryTotalVlans: $('#summaryTotalVlans', app),
            summaryTotalCVlans: $('#summaryTotalCVlans', app),
            summaryTotalSVlans: $('#summaryTotalSVlans', app),
            summaryDeployedCount: $('#summaryDeployedCount', app)
        };

        function loadStoredTab() {
            try {
                const saved = localStorage.getItem(STORE_KEY);
                return ['cvlan', 'svlan', 'mgmtvlan'].includes(saved) ? saved : null;
            } catch (e) {
                return null;
            }
        }

        function saveStoredTab(tab) {
            try {
                localStorage.setItem(STORE_KEY, tab);
            } catch (e) {}
        }

        function toast(icon, title) {
            if (ui && typeof ui.toast === 'function') {
                ui.toast(icon, title);
                return;
            }

            if (window.Swal) {
                window.Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: icon || 'info',
                    title: title || '',
                    showConfirmButton: false,
                    timer: 3200,
                    timerProgressBar: true,
                    customClass: { popup: 'nx-toast-popup' }
                });
                return;
            }

            alert(title);
        }

        function showLoading(title = 'Processing...', text = 'Please wait...') {
            if (ui && typeof ui.loading === 'function') {
                ui.loading(title, text);
                return;
            }

            if (window.Swal) {
                window.Swal.fire({
                    title,
                    text,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        window.Swal.showLoading();
                    }
                });
            }
        }

        function closeLoading() {
            if (ui && typeof ui.close === 'function') {
                ui.close();
                return;
            }

            if (window.Swal) {
                window.Swal.close();
            }
        }

        function extractErrorMessage(error, fallback = 'Request failed.') {
            if (!error) return fallback;

            if (typeof error === 'string' && error.trim()) {
                return error;
            }

            if (error instanceof Error && error.message) {
                return error.message;
            }

            if (typeof error === 'object') {
                if (typeof error.error === 'string' && error.error.trim()) return error.error;
                if (typeof error.message === 'string' && error.message.trim()) return error.message;
            }

            return fallback;
        }

        async function request(url, method = 'GET', body = null) {
            const opts = {
                method,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            };

            if (body !== null) {
                opts.headers['Content-Type'] = 'application/json';
                opts.body = JSON.stringify(body);
            }

            const res = await fetch(url, opts);
            const raw = await res.text();

            let json = null;
            try {
                json = raw ? JSON.parse(raw) : null;
            } catch (err) {
                if (!res.ok) {
                    throw new Error(raw || `Request failed with status ${res.status}.`);
                }
                throw new Error(raw || 'Server returned invalid JSON.');
            }

            return normalizeApiResponse(json, res.ok, res.status);
        }

        function normalizeApiResponse(response, isHttpOk = true, statusCode = 200) {
            if (!response) {
                throw new Error('Empty server response.');
            }

            if (typeof response === 'object' && ('success' in response || 'error' in response || 'message' in response || 'data' in response)) {
                const success = response.success === true || response.ok === true || response.status === 'success';

                if (!success) {
                    throw new Error(response.error || response.message || `Request failed with status ${statusCode}.`);
                }

                return response.data ?? [];
            }

            if (!isHttpOk) {
                throw new Error(response?.error || response?.message || `Request failed with status ${statusCode}.`);
            }

            return response;
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function fmtDate(value) {
            if (!value) return '-';
            const normalized = String(value).replace(' ', 'T');
            const d = new Date(normalized);
            if (Number.isNaN(d.getTime())) return escape(String(value));
            return d.toLocaleString();
        }

        function typeLabel(type) {
            return String(type || '').toUpperCase().replace('_', '-');
        }

        function entityLabel(vlanType, vlanId) {
            return `${typeLabel(vlanType)} ${vlanId}`;
        }

        function deploymentBadge(status) {
            const s = String(status || 'PENDING').toUpperCase();

            if (s === 'DEPLOYED') {
                return `<span class="nx-soft-badge nx-soft-badge-success">DEPLOYED</span>`;
            }
            if (s === 'FAILED') {
                return `<span class="nx-soft-badge nx-soft-badge-danger">FAILED</span>`;
            }
            return `<span class="nx-soft-badge nx-soft-badge-warning">PENDING</span>`;
        }

        function filterRows(rows, mapper) {
            const q = String(state.search || '').trim().toLowerCase();
            if (!q) return rows;

            return rows.filter((row) => {
                try {
                    return String(mapper(row) || '').toLowerCase().includes(q);
                } catch (e) {
                    return false;
                }
            });
        }

        function updateSummary() {
            if (els.summaryTotalVlans) els.summaryTotalVlans.textContent = String(state.summary.total_vlans || 0);
            if (els.summaryTotalCVlans) els.summaryTotalCVlans.textContent = String(state.summary.total_c_vlans || 0);
            if (els.summaryTotalSVlans) els.summaryTotalSVlans.textContent = String(state.summary.total_s_vlans || 0);
            if (els.summaryDeployedCount) els.summaryDeployedCount.textContent = String(state.summary.deployed_count || 0);
        }

        function cellStack(title, sub = '') {
            return `
                <div class="nx-cell-stack">
                    <div class="nx-cell-title">${escape(title || '-')}</div>
                    <div class="nx-cell-sub">${escape(sub || '-')}</div>
                </div>
            `;
        }

        function monoStack(title, sub = '') {
            return `
                <div class="nx-cell-stack">
                    <div class="nx-cell-title nx-text-mono">${escape(title || '-')}</div>
                    <div class="nx-cell-sub">${escape(sub || '-')}</div>
                </div>
            `;
        }

        function actionButtons(row) {
            return `
                <div class="nx-vlan-actions">
                    <button
                        type="button"
                        class="btn btn-outline-secondary nx-icon-btn btn-view-output"
                        data-id="${escape(row.id)}"
                        title="View output"
                    >
                        <i class="bi bi-terminal"></i>
                    </button>
                    <button
                        type="button"
                        class="btn btn-outline-primary nx-icon-btn btn-edit-vlan"
                        data-id="${escape(row.id)}"
                        title="Edit VLAN"
                    >
                        <i class="bi bi-pencil-square"></i>
                    </button>
                    <button
                        type="button"
                        class="btn btn-outline-danger nx-icon-btn btn-delete-vlan"
                        data-id="${escape(row.id)}"
                        title="Delete VLAN"
                    >
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            `;
        }

        function mgmtActionButtons(row) {
            return `
                <div class="nx-vlan-actions">
                    <button
                        type="button"
                        class="btn btn-outline-primary nx-icon-btn btn-edit-mgmt-vlan"
                        data-id="${escape(row.id)}"
                        title="Edit MGMT-VLAN"
                    >
                        <i class="bi bi-pencil-square"></i>
                    </button>
                    <button
                        type="button"
                        class="btn btn-outline-danger nx-icon-btn btn-delete-mgmt-vlan"
                        data-id="${escape(row.id)}"
                        title="Delete MGMT-VLAN"
                    >
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            `;
        }

        function renderEmptyState(colspan, icon, title, text) {
            return `
                <tr>
                    <td colspan="${colspan}">
                        <div class="nx-empty-state">
                            <div class="nx-empty-icon">
                                <i class="bi ${escape(icon)}"></i>
                            </div>
                            <div class="nx-empty-title">${escape(title)}</div>
                            <div class="nx-empty-text">${escape(text)}</div>
                        </div>
                    </td>
                </tr>
            `;
        }

        function renderTable(targetTbody, vlanType) {
            if (!targetTbody) return;

            const rows = filterRows(
                state.vlans.filter((row) => String(row.vlan_type || '').toUpperCase() === vlanType),
                (row) => [
                    row.id,
                    row.olt_id,
                    row.olt_ip_address,
                    row.olt_port_id,
                    row.olt_port_path,
                    row.vlan_id,
                    row.vlan_type,
                    row.name,
                    row.description,
                    row.deployment_status,
                    row.deployed_at,
                    row.deployment_output
                ].join(' ')
            );

            if (!rows.length) {
                targetTbody.innerHTML = renderEmptyState(
                    6,
                    vlanType === 'S_VLAN' ? 'bi-layers' : 'bi-person-badge',
                    `No ${typeLabel(vlanType)} records found`,
                    `Create your first ${typeLabel(vlanType)} definition to begin deployment.`
                );
                return;
            }

            targetTbody.innerHTML = rows.map((row) => {
                const vlanTitle = `VLAN ${row.vlan_id || '-'}`;
                const vlanSub = row.vlan_type === 'S_VLAN'
                    ? `Record #${row.id || '-'} · ${typeLabel(row.vlan_type)}${row.olt_ip_address ? ` · ${row.olt_ip_address}` : ''}${row.olt_port_path ? ` · Port ${row.olt_port_path}` : ''}`
                    : `Record #${row.id || '-'} · ${typeLabel(row.vlan_type)}${row.olt_ip_address ? ` · ${row.olt_ip_address}` : ''}`;

                const nameTitle = row.name || '-';
                const nameSub = row.vlan_type === 'S_VLAN'
                    ? `Bound to ${row.olt_port_path ? `OLT Port ${row.olt_port_path}` : 'OLT Port'}`
                    : 'Deploys smart VLAN';

                const descTitle = row.description || '-';
                const descSub = row.deployment_status === 'DEPLOYED'
                    ? 'Last deployment stored in system'
                    : row.deployment_status === 'FAILED'
                        ? 'Last deployment failed'
                        : 'Ready for OLT deployment';

                const deployedTitle = fmtDate(row.deployed_at);
                const deployedSub = row.deployed_at ? 'Last successful push' : 'Not deployed yet';

                return `
                    <tr>
                        <td class="ps-4">${monoStack(vlanTitle, vlanSub)}</td>
                        <td>${deploymentBadge(row.deployment_status)}</td>
                        <td>${cellStack(nameTitle, nameSub)}</td>
                        <td>${cellStack(descTitle, descSub)}</td>
                        <td>${cellStack(deployedTitle, deployedSub)}</td>
                        <td class="pe-4">${actionButtons(row)}</td>
                    </tr>
                `;
            }).join('');
        }

        function renderMgmtTable() {
            if (!els.mgmtVlanTbody) return;

            const rows = filterRows(
                state.mgmtVlans,
                (row) => [
                    row.id,
                    row.olt_id,
                    row.olt_ip_address,
                    row.mgmt_vlan,
                    row.description,
                    row.created_at
                ].join(' ')
            );

            if (!rows.length) {
                els.mgmtVlanTbody.innerHTML = renderEmptyState(
                    5,
                    'bi-hdd-network',
                    'No MGMT-VLAN records found',
                    'Set the TR069 management VLAN per OLT.'
                );
                return;
            }

            els.mgmtVlanTbody.innerHTML = rows.map((row) => {
                const oltTitle = row.olt_ip_address || `OLT #${row.olt_id || '-'}`;
                const oltSub = `OLT ID ${row.olt_id || '-'}`;
                const mgmtTitle = `VLAN ${row.mgmt_vlan || '-'}`;
                const mgmtSub = 'TR069 management path';
                const descTitle = row.description || '-';
                const descSub = 'Per-OLT management VLAN';
                const createdTitle = fmtDate(row.created_at);
                const createdSub = row.created_at ? 'Record created' : '-';

                return `
                    <tr>
                        <td class="ps-4">${cellStack(oltTitle, oltSub)}</td>
                        <td>${monoStack(mgmtTitle, mgmtSub)}</td>
                        <td>${cellStack(descTitle, descSub)}</td>
                        <td>${cellStack(createdTitle, createdSub)}</td>
                        <td class="pe-4">${mgmtActionButtons(row)}</td>
                    </tr>
                `;
            }).join('');
        }

        function renderAllTables() {
            renderTable(els.cVlanTbody, 'C_VLAN');
            renderTable(els.sVlanTbody, 'S_VLAN');
            renderMgmtTable();
        }

        function setTab(tab) {
            const allowed = ['cvlan', 'svlan', 'mgmtvlan'];
            state.tab = allowed.includes(tab) ? tab : 'cvlan';
            saveStoredTab(state.tab);

            els.tabs.forEach((btn) => {
                btn.classList.toggle('active', btn.dataset.tab === state.tab);
            });

            els.panes.forEach((pane) => {
                pane.classList.toggle('d-none', pane.dataset.tabPane !== state.tab);
            });

            if (els.btnAddVlan) {
                els.btnAddVlan.classList.toggle('d-none', state.tab === 'mgmtvlan');
            }
        }

        function formatPortLabel(port) {
            const path = [
                port.frame ?? 0,
                port.slot ?? 0,
                port.port ?? 0
            ].join('/');

            return `Port ${path}`;
        }

        function isAllowedSvlanPort(port) {
            const portType = String(port.port_type || '').toUpperCase();
            const boardType = String(port.board_type || '').toUpperCase();
            const boardName = String(port.board_name || port.board || '').toUpperCase();

            if (portType === 'GPON') return true;
            if (boardType === 'GPON') return true;

            if (boardName.includes('CGHF')) return true;
            if (boardName.includes('CSHF')) return true;
            if (boardName.includes('GPON')) return true;

            return false;
        }

        async function loadOlts() {
            try {
                const data = await request('/api/v1/olt-management/devices', 'GET');
                state.olts = Array.isArray(data) ? data : [];
            } catch (err) {
                console.error('Failed to load OLTs:', err);
                state.olts = [];
            }
        }

        async function loadPortsByOltId(oltId) {
            const key = String(oltId || '');
            if (!key) return [];

            if (Array.isArray(state.portsByOlt[key])) {
                return state.portsByOlt[key];
            }

            try {
                const data = await request(`/api/v1/olt-management/ports/by-olt/${oltId}`, 'GET');
                const ports = Array.isArray(data) ? data : [];
                state.portsByOlt[key] = ports;
                return ports;
            } catch (err) {
                console.error('Failed to load OLT ports:', err);
                state.portsByOlt[key] = [];
                return [];
            }
        }

        async function loadMgmtVlans() {
            try {
                const data = await request('/api/v1/vlan-management/mgmt-vlans', 'GET');
                state.mgmtVlans = Array.isArray(data) ? data : [];
            } catch (err) {
                console.error('Failed to load MGMT-VLANs:', err);
                state.mgmtVlans = [];
            }
        }

        function getOltOptions(selectedId = '') {
            const selected = String(selectedId || '');
            const options = ['<option value="">Select OLT</option>'];

            state.olts.forEach((olt) => {
                const id = String(olt.id ?? '');
                const label = olt.name
                    ? `${olt.name} (${olt.ip_address || 'No IP'})`
                    : (olt.ip_address || `OLT #${id}`);

                options.push(
                    `<option value="${escape(id)}" ${selected === id ? 'selected' : ''}>${escape(label)}</option>`
                );
            });

            return options.join('');
        }

        function getPortOptions(ports, selectedId = '') {
            const selected = String(selectedId || '');
            const options = ['<option value="">Select OLT Port</option>'];

            (ports || [])
                .filter(isAllowedSvlanPort)
                .forEach((port) => {
                    const id = String(port.id ?? '');
                    const label = formatPortLabel(port);
                    options.push(
                        `<option value="${escape(id)}" ${selected === id ? 'selected' : ''}>${escape(label)}</option>`
                    );
                });

            return options.join('');
        }

        function vlanCreateFormHtml(values = {}, forcedType = null, ports = []) {
            const currentType = String(
                forcedType || values.vlan_type || (state.tab === 'svlan' ? 'S_VLAN' : 'C_VLAN')
            ).toUpperCase();

            const showPortSelector = currentType === 'S_VLAN';

            return `
                <div class="text-start">
                    <div class="mb-3">
                        <label class="form-label">OLT</label>
                        <select class="form-select" id="swalVlanOltId">
                            ${getOltOptions(values.olt_id || '')}
                        </select>
                    </div>

                    ${showPortSelector ? `
                        <div class="mb-3" id="swalVlanPortWrap">
                            <label class="form-label">OLT Port</label>
                            <select class="form-select" id="swalVlanOltPortId">
                                ${getPortOptions(ports, values.olt_port_id || '')}
                            </select>
                            <div class="form-text">S-VLAN is bound to one GPON OLT port only.</div>
                        </div>
                    ` : ''}

                    <div class="mb-3">
                        <label class="form-label">VLAN ID</label>
                        <input type="number" class="form-control" id="swalVlanId" value="${escape(values.vlan_id || '')}" min="1" max="4094">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">VLAN Type</label>
                        <select class="form-select" id="swalVlanType" ${forcedType ? 'disabled' : ''}>
                            <option value="C_VLAN" ${currentType === 'C_VLAN' ? 'selected' : ''}>C-VLAN</option>
                            <option value="S_VLAN" ${currentType === 'S_VLAN' ? 'selected' : ''}>S-VLAN</option>
                        </select>
                        ${forcedType ? `<div class="form-text">Type follows the active tab.</div>` : ''}
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" id="swalVlanName" value="${escape(values.name || '')}" maxlength="64">
                    </div>

                    <div class="mb-0">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="swalVlanDescription" rows="4">${escape(values.description || '')}</textarea>
                    </div>
                </div>
            `;
        }

        function vlanEditFormHtml(values = {}) {
            const oltLabel = state.olts.find((o) => String(o.id) === String(values.olt_id))
                ? (() => {
                    const olt = state.olts.find((o) => String(o.id) === String(values.olt_id));
                    return olt.name
                        ? `${olt.name} (${olt.ip_address || 'No IP'})`
                        : (olt.ip_address || `OLT #${olt.id}`);
                })()
                : (values.olt_ip_address || `OLT #${values.olt_id || '-'}`);

            const portLabel = values.olt_port_path
                ? `Port ${values.olt_port_path}`
                : '-';

            return `
                <div class="text-start">
                    <div class="mb-3">
                        <label class="form-label">OLT</label>
                        <input type="text" class="form-control" value="${escape(oltLabel)}" readonly>
                    </div>

                    ${String(values.vlan_type || '').toUpperCase() === 'S_VLAN' ? `
                        <div class="mb-3">
                            <label class="form-label">OLT Port</label>
                            <input type="text" class="form-control" value="${escape(portLabel)}" readonly>
                        </div>
                    ` : ''}

                    <div class="mb-3">
                        <label class="form-label">VLAN ID</label>
                        <input type="text" class="form-control" value="${escape(values.vlan_id || '')}" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">VLAN Type</label>
                        <input type="text" class="form-control" value="${escape(typeLabel(values.vlan_type || ''))}" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" id="swalVlanName" value="${escape(values.name || '')}" maxlength="64">
                    </div>

                    <div class="mb-0">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="swalVlanDescription" rows="4">${escape(values.description || '')}</textarea>
                    </div>
                </div>
            `;
        }

        function mgmtVlanFormHtml(values = {}) {
            return `
                <div class="text-start">
                    <div class="mb-3">
                        <label class="form-label">OLT</label>
                        <select class="form-select" id="swalMgmtOltId">
                            ${getOltOptions(values.olt_id || '')}
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">MGMT-VLAN</label>
                        <input type="number" class="form-control" id="swalMgmtVlanId" value="${escape(values.mgmt_vlan || '')}" min="1" max="4094">
                        <div class="form-text">Used for TR069 management connectivity.</div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="swalMgmtDescription" rows="4">${escape(values.description || '')}</textarea>
                    </div>
                </div>
            `;
        }

        async function openAddVlan() {
            if (!window.Swal) {
                toast('error', 'SweetAlert is not available.');
                return;
            }

            if (!state.olts.length) {
                await loadOlts();
            }

            if (!state.olts.length) {
                toast('error', 'No OLT devices available. Please add an OLT first.');
                return;
            }

            const forcedType = state.tab === 'svlan' ? 'S_VLAN' : 'C_VLAN';
            const defaultOltId = state.olts[0]?.id || '';
            const ports = forcedType === 'S_VLAN' && defaultOltId ? await loadPortsByOltId(defaultOltId) : [];

            const result = await window.Swal.fire({
                title: state.tab === 'svlan' ? 'Add S-VLAN' : 'Add C-VLAN',
                html: vlanCreateFormHtml(
                    {
                        olt_id: defaultOltId,
                        vlan_type: forcedType
                    },
                    forcedType,
                    ports
                ),
                focusConfirm: false,
                showCancelButton: true,
                confirmButtonText: 'Save',
                width: 640,
                didOpen: () => {
                    const oltSelect = document.getElementById('swalVlanOltId');
                    if (forcedType === 'S_VLAN' && oltSelect) {
                        oltSelect.addEventListener('change', async () => {
                            const nextOltId = oltSelect.value;
                            const nextPorts = nextOltId ? await loadPortsByOltId(nextOltId) : [];
                            const portSelect = document.getElementById('swalVlanOltPortId');
                            if (portSelect) {
                                portSelect.innerHTML = getPortOptions(nextPorts, '');
                            }
                        });
                    }
                },
                preConfirm: () => {
                    const payload = {
                        olt_id: $('#swalVlanOltId')?.value.trim(),
                        olt_port_id: $('#swalVlanOltPortId')?.value.trim() || null,
                        vlan_id: $('#swalVlanId')?.value.trim(),
                        vlan_type: forcedType,
                        name: $('#swalVlanName')?.value.trim(),
                        description: $('#swalVlanDescription')?.value.trim()
                    };

                    if (!payload.olt_id) {
                        window.Swal.showValidationMessage('OLT is required.');
                        return false;
                    }

                    if (payload.vlan_type === 'S_VLAN' && !payload.olt_port_id) {
                        window.Swal.showValidationMessage('OLT Port is required for S-VLAN.');
                        return false;
                    }

                    if (!payload.vlan_id) {
                        window.Swal.showValidationMessage('VLAN ID is required.');
                        return false;
                    }

                    if (!payload.name) {
                        window.Swal.showValidationMessage('Name is required.');
                        return false;
                    }

                    return payload;
                }
            });

            if (!result.isConfirmed || !result.value) return;

            showLoading(
                'Creating VLAN',
                `Deploying VLAN ${result.value.vlan_id} to the selected OLT...`
            );

            try {
                const response = await request('/api/v1/vlan-management/vlans', 'POST', result.value);
                await loadAll();
                closeLoading();

                if (response?.auto_deploy_success) {
                    toast('success', `${entityLabel(result.value.vlan_type, result.value.vlan_id)} deployed successfully.`);
                } else {
                    toast('error', `${entityLabel(result.value.vlan_type, result.value.vlan_id)} deployment failed.`);
                }

                const outputText =
                    response?.deployment?.output ||
                    response?.deployment?.message ||
                    response?.record?.deployment_output ||
                    '';

                if (!response?.auto_deploy_success && outputText && window.Swal) {
                    await window.Swal.fire({
                        title: 'Deployment Failed',
                        html: `
                            <div class="text-start">
                                <pre class="p-3 rounded bg-light border small mb-0" style="max-height:320px;overflow:auto;">${escape(outputText)}</pre>
                            </div>
                        `,
                        width: 900
                    });
                }
            } catch (err) {
                closeLoading();
                toast('error', extractErrorMessage(err, 'Failed to create VLAN.'));
            }
        }

        async function openEditVlan(id) {
            const row = state.vlans.find((x) => String(x.id) === String(id));
            if (!row || !window.Swal) return;

            if (!state.olts.length) {
                await loadOlts();
            }

            const result = await window.Swal.fire({
                title: `Edit VLAN ${row.vlan_id}`,
                html: vlanEditFormHtml(row),
                focusConfirm: false,
                showCancelButton: true,
                confirmButtonText: 'Update',
                width: 640,
                preConfirm: () => {
                    const payload = {
                        id: row.id,
                        olt_id: row.olt_id,
                        olt_port_id: row.olt_port_id || null,
                        vlan_id: row.vlan_id,
                        vlan_type: row.vlan_type,
                        name: $('#swalVlanName')?.value.trim(),
                        description: $('#swalVlanDescription')?.value.trim()
                    };

                    if (!payload.name) {
                        window.Swal.showValidationMessage('Name is required.');
                        return false;
                    }

                    return payload;
                }
            });

            if (!result.isConfirmed || !result.value) return;

            try {
                await request(`/api/v1/vlan-management/vlans/${id}/update`, 'POST', result.value);
                toast('success', `${entityLabel(row.vlan_type, row.vlan_id)} updated successfully.`);
                await loadAll();
            } catch (err) {
                toast('error', extractErrorMessage(err, 'Failed to update VLAN.'));
            }
        }

        async function openMgmtVlanModal(id = null) {
    if (!window.Swal) {
        toast('error', 'SweetAlert is not available.');
        return;
    }

    if (!state.olts.length) {
        await loadOlts();
    }

    if (!state.olts.length) {
        toast('error', 'No OLT devices available. Please add an OLT first.');
        return;
    }

    const existing = id
        ? state.mgmtVlans.find((x) => String(x.id) === String(id)) || null
        : null;

    const result = await window.Swal.fire({
        title: existing ? 'Edit MGMT-VLAN' : 'Set MGMT-VLAN',
        html: mgmtVlanFormHtml(existing || { olt_id: state.olts[0]?.id || '' }),
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: existing ? 'Update' : 'Save & Deploy',
        width: 640,
        preConfirm: () => {
            const payload = {
                id: existing?.id || null,
                olt_id: $('#swalMgmtOltId')?.value.trim(),
                mgmt_vlan: $('#swalMgmtVlanId')?.value.trim(),
                description: $('#swalMgmtDescription')?.value.trim()
            };

            if (!payload.olt_id) {
                window.Swal.showValidationMessage('OLT is required.');
                return false;
            }

            if (!payload.mgmt_vlan) {
                window.Swal.showValidationMessage('MGMT-VLAN is required.');
                return false;
            }

            return payload;
        }
    });

    if (!result.isConfirmed || !result.value) return;

    showLoading(
        existing ? 'Updating MGMT-VLAN' : 'Creating MGMT-VLAN',
        `Deploying MGMT-VLAN ${result.value.mgmt_vlan} to the selected OLT...`
    );

    try {
        const response = await request('/api/v1/vlan-management/mgmt-vlans', 'POST', result.value);

        await loadAll();
        closeLoading();

        const vlanNo = response?.record?.mgmt_vlan || result.value.mgmt_vlan;

        if (response?.auto_deploy_success) {
            toast('success', `MGMT-VLAN ${vlanNo} saved and deployed successfully.`);
        } else if (response?.auto_deployed) {
            toast('warning', `MGMT-VLAN ${vlanNo} saved but deployment failed.`);
        } else {
            toast('success', `MGMT-VLAN ${vlanNo} saved successfully.`);
        }

        const outputText =
            response?.deployment?.output ||
            response?.deployment?.message ||
            '';

        if (!response?.auto_deploy_success && outputText && window.Swal) {
            await window.Swal.fire({
                title: 'MGMT-VLAN Deployment Failed',
                html: `
                    <div class="text-start">
                        <pre class="p-3 rounded bg-light border small mb-0" style="max-height:320px;overflow:auto;">${escape(outputText)}</pre>
                    </div>
                `,
                width: 900
            });
        }
    } catch (err) {
        closeLoading();
        toast('error', extractErrorMessage(err, 'Failed to save MGMT-VLAN.'));
    }
}

        async function deleteVlan(id) {
            const row = state.vlans.find((x) => String(x.id) === String(id));
            if (!row || !window.Swal) return;

            const deleteCommand = row.vlan_type === 'S_VLAN'
                ? `vlan forwarding ${row.vlan_id} vlan-mac → undo vlan attrib ${row.vlan_id} → undo vlan ${row.vlan_id}`
                : `undo vlan ${row.vlan_id}`;

            const res = await window.Swal.fire({
                title: 'Delete VLAN?',
                html: `
                    Delete <b>${escape(typeLabel(row.vlan_type))} ${escape(row.vlan_id)}</b>?<br><br>
                    <span class="text-danger">This will also remove the VLAN from the OLT.</span><br>
                    <small class="text-muted">${escape(deleteCommand)}</small>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Delete',
                confirmButtonColor: '#dc3545',
                showLoaderOnConfirm: true,
                preConfirm: async () => {
                    try {
                        return await request(`/api/v1/vlan-management/vlans/${id}/delete`, 'POST', {});
                    } catch (err) {
                        window.Swal.showValidationMessage(extractErrorMessage(err, 'Delete failed.'));
                        return false;
                    }
                },
                allowOutsideClick: () => !window.Swal.isLoading()
            });

            if (!res.isConfirmed || !res.value) return;

            const result = res.value;

            await loadAll();

            toast('success', `${entityLabel(row.vlan_type, row.vlan_id)} deleted successfully.`);

            if (result?.output) {
                await window.Swal.fire({
                    title: 'Delete Output',
                    html: `
                        <div class="text-start">
                            <pre class="p-3 rounded bg-light border small mb-0" style="max-height:280px;overflow:auto;">${escape(result.output)}</pre>
                        </div>
                    `,
                    width: 860
                });
            }
        }

        async function deleteMgmtVlan(id) {
    const row = state.mgmtVlans.find((x) => String(x.id) === String(id));
    if (!row || !window.Swal) return;

    const res = await window.Swal.fire({
        title: 'Delete MGMT-VLAN?',
        html: `
            Delete <b>MGMT-VLAN ${escape(row.mgmt_vlan)}</b> for
            <b>${escape(row.olt_ip_address || `OLT #${row.olt_id}`)}</b>?<br><br>
            <span class="text-danger">This will also remove the MGMT-VLAN from the OLT and save the config.</span>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        confirmButtonColor: '#dc3545'
    });

    if (!res.isConfirmed) return;

    showLoading(
        'Deleting MGMT-VLAN',
        `Removing MGMT-VLAN ${row.mgmt_vlan} from the selected OLT...`
    );

    try {
        const result = await request(`/api/v1/vlan-management/mgmt-vlans/${id}/delete`, 'POST', {});

        await loadAll();
        closeLoading();

        toast('success', `MGMT-VLAN ${row.mgmt_vlan} deleted from OLT successfully.`);

        const outputText =
            result?.deployment?.output ||
            result?.deployment?.message ||
            '';

        if (outputText && window.Swal) {
            await window.Swal.fire({
                title: 'MGMT-VLAN Delete Output',
                html: `
                    <div class="text-start">
                        <pre class="p-3 rounded bg-light border small mb-0" style="max-height:320px;overflow:auto;">${escape(outputText)}</pre>
                    </div>
                `,
                width: 900
            });
        }
    } catch (err) {
        closeLoading();
        toast('error', extractErrorMessage(err, 'Failed to delete MGMT-VLAN.'));
    }
}

        async function viewOutput(id) {
            const row = state.vlans.find((x) => String(x.id) === String(id));
            if (!row || !window.Swal) return;

            let pretty = row.deployment_output || 'No deployment output stored.';
            try {
                const parsed = JSON.parse(pretty);
                pretty = JSON.stringify(parsed, null, 2);
            } catch (e) {}

            await window.Swal.fire({
                title: `Output for VLAN ${row.vlan_id}`,
                html: `
                    <div class="text-start">
                        <pre class="p-3 rounded bg-light border small mb-0" style="max-height:320px;overflow:auto;">${escape(pretty)}</pre>
                    </div>
                `,
                width: 900
            });
        }

        async function loadAll() {
            const [summary, vlans] = await Promise.all([
                request('/api/v1/vlan-management/summary'),
                request('/api/v1/vlan-management/vlans')
            ]);

            state.summary = summary || {};
            state.vlans = Array.isArray(vlans) ? vlans : [];

            await loadMgmtVlans();

            updateSummary();
            renderAllTables();
        }

        function bindEvents() {
            if (els.btnRefresh) {
                els.btnRefresh.addEventListener('click', async () => {
                    await loadAll();
                    toast('success', 'VLAN Management refreshed.');
                });
            }

            if (els.btnAddVlan) {
                els.btnAddVlan.addEventListener('click', openAddVlan);
            }

            if (els.btnAddMgmtVlan) {
                els.btnAddMgmtVlan.addEventListener('click', () => openMgmtVlanModal());
            }

            if (els.search) {
                els.search.addEventListener('input', (e) => {
                    state.search = e.target.value || '';
                    renderAllTables();
                });
            }

            els.tabs.forEach((btn) => {
                btn.addEventListener('click', () => setTab(btn.dataset.tab || 'cvlan'));
            });

            document.addEventListener('click', async (e) => {
                try {
                    const viewBtn = e.target.closest('.btn-view-output');
                    if (viewBtn) {
                        await viewOutput(viewBtn.dataset.id);
                        return;
                    }

                    const editBtn = e.target.closest('.btn-edit-vlan');
                    if (editBtn) {
                        await openEditVlan(editBtn.dataset.id);
                        return;
                    }

                    const deleteBtn = e.target.closest('.btn-delete-vlan');
                    if (deleteBtn) {
                        await deleteVlan(deleteBtn.dataset.id);
                        return;
                    }

                    const editMgmtBtn = e.target.closest('.btn-edit-mgmt-vlan');
                    if (editMgmtBtn) {
                        await openMgmtVlanModal(editMgmtBtn.dataset.id);
                        return;
                    }

                    const deleteMgmtBtn = e.target.closest('.btn-delete-mgmt-vlan');
                    if (deleteMgmtBtn) {
                        await deleteMgmtVlan(deleteMgmtBtn.dataset.id);
                    }
                } catch (err) {
                    console.error(err);
                    toast('error', extractErrorMessage(err, 'Action failed.'));
                }
            });
        }

        (async function init() {
            try {
                await loadOlts();
                bindEvents();
                setTab(state.tab);
                await loadAll();
            } catch (err) {
                console.error(err);
                toast('error', extractErrorMessage(err, 'Failed to initialize VLAN Management.'));
            }
        })();
    });
})();