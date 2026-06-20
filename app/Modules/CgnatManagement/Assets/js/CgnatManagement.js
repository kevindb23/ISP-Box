(function () {
    document.addEventListener('DOMContentLoaded', () => {
        const app = document.getElementById('cgnatManagementApp');
        if (!app || !window.NX) return;

        const { api, ui, dom, forms, modal, util } = window.NX;
        const { $, $$, html, text, val } = dom;
        const { escape } = util;

        const state = {
            pools: Array.isArray(window.CGNAT_MANAGEMENT_BOOTSTRAP?.pools) ? window.CGNAT_MANAGEMENT_BOOTSTRAP.pools : [],
            deployments: Array.isArray(window.CGNAT_MANAGEMENT_BOOTSTRAP?.deployments) ? window.CGNAT_MANAGEMENT_BOOTSTRAP.deployments : [],
            usage: Array.isArray(window.CGNAT_MANAGEMENT_BOOTSTRAP?.usage) ? window.CGNAT_MANAGEMENT_BOOTSTRAP.usage : [],
            bng: {
                setting: {},
                runtime: {}
            },
            activeTab: 'bng'
        };

        const refs = {
            addBtn: $('#cgnatPoolAddBtn'),
            detectBtn: $('#cgnatDetectBtn'),
            detectBtnSecondary: $('#cgnatDetectBtnSecondary'),
            detectBtnRuntime: $('#cgnatDetectBtnRuntime'),

            poolsCount: $('#cgnatPoolsCount'),
            poolsTbody: $('#cgnatPoolsTbody'),
            poolsSearch: $('#cgnatPoolsSearch'),

            poolModalEl: $('#cgnatPoolModal'),
            poolModalTitle: $('#cgnatPoolModalTitle'),
            poolForm: $('#cgnatPoolForm'),

            previewModalEl: $('#cgnatPreviewModal'),
            accelPreview: $('#cgnatAccelPreview'),
            frrPreview: $('#cgnatFrrPreview'),

            poolId: $('#natPoolId'),
            poolName: $('#natPoolName'),
            poolType: $('#natPoolType'),
            poolNetwork: $('#natPoolNetwork'),
            poolGateway: $('#natPoolGateway'),
            poolRangeStart: $('#natPoolRangeStart'),
            poolRangeEnd: $('#natPoolRangeEnd'),
            poolAccelName: $('#natPoolAccelName'),
            poolStatus: $('#natPoolStatus'),
            poolRemarks: $('#natPoolRemarks'),

            bngForm: $('#cgnatBngForm'),
            bngSaveBtn: $('#cgnatBngSaveBtn'),
            bngSaveBtnSecondary: $('#cgnatBngSaveBtnSecondary'),
            bngTestBtn: $('#cgnatBngTestBtn'),
            bngAuthType: $('#cgnatBngAuthType'),
            bngPasswordWrap: $('#cgnatBngPasswordWrap'),
            bngKeyPathWrap: $('#cgnatBngKeyPathWrap'),

            svlanInput: $('#cgnatSvlanId'),
            createSvlanBtn: $('#cgnatCreateSvlanInterfaceBtn'),
            svlanResultBox: $('#cgnatSvlanResultBox'),

            runtimeBox: $('#cgnatRuntimeBox'),

            summaryHost: $('#cgnatSummaryStatus'),
            summaryParent: $('#cgnatSummaryConfigured'),
            summaryVlanMode: $('#cgnatSummaryRules')
        };

        const poolModal = modal.instance(refs.poolModalEl);
        const previewModal = modal.instance(refs.previewModalEl);

        init();

        function init() {
            bindEvents();
            renderPools();
            loadSvlans();
            switchTab('bng');
            loadBngSetting(false);
            detectRuntime(false);
        }

        function bindEvents() {
            refs.addBtn?.addEventListener('click', openCreateModal);

            refs.detectBtn?.addEventListener('click', () => detectRuntime(true));
            refs.detectBtnSecondary?.addEventListener('click', () => detectRuntime(true));
            refs.detectBtnRuntime?.addEventListener('click', () => detectRuntime(true));

            refs.poolForm?.addEventListener('submit', async (e) => {
                e.preventDefault();
                await savePool();
            });

            refs.poolsSearch?.addEventListener('input', () => {
                filterTable('#cgnatPoolsTable', refs.poolsSearch.value);
            });

            $$('[data-cgnat-tab-btn]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    switchTab(btn.dataset.cgnatTabBtn || 'bng');
                });
            });

            refs.poolsTbody?.addEventListener('click', async (e) => {
                const editBtn = e.target.closest('.cgnat-edit-pool-btn');
                const previewBtn = e.target.closest('.cgnat-preview-btn');
                const applyBtn = e.target.closest('.cgnat-apply-btn');
                const deleteBtn = e.target.closest('.cgnat-delete-btn');

                if (editBtn) return openEditModal(Number(editBtn.dataset.poolId || 0));
                if (previewBtn) return previewPool(Number(previewBtn.dataset.poolId || 0));
                if (applyBtn) return applyPool(Number(applyBtn.dataset.poolId || 0));
                if (deleteBtn) return deletePool(Number(deleteBtn.dataset.poolId || 0));
            });

            refs.bngSaveBtn?.addEventListener('click', saveBngSetting);
            refs.bngSaveBtnSecondary?.addEventListener('click', saveBngSetting);
            refs.bngTestBtn?.addEventListener('click', testBngConnection);
            refs.bngAuthType?.addEventListener('change', syncBngAuthUi);

            refs.createSvlanBtn?.addEventListener('click', createSvlanInterface);
        }

        function switchTab(tabName) {
            state.activeTab = tabName;

            $$('[data-cgnat-tab-btn]').forEach((btn) => {
                btn.classList.toggle('is-active', btn.dataset.cgnatTabBtn === tabName);
            });

            $$('[data-cgnat-tab]').forEach((panel) => {
                panel.classList.toggle('d-none', panel.dataset.cgnatTab !== tabName);
            });
        }

        async function loadBngSetting(showErrorToast = false) {
            if (!refs.bngForm) return false;

            try {
                const data = await api.get('/api/v1/cgnat/bng-setting');
                state.bng.setting = data || {};

                forms.populate(refs.bngForm, data || {});
                syncBngAuthUi();
                renderSummary();

                return true;
            } catch (e) {
                state.bng.setting = {};
                syncBngAuthUi();
                renderSummary();

                if (showErrorToast) {
                    ui.toast('error', e.message || 'Failed to load BNG setting.');
                }

                return false;
            }
        }

        async function saveBngSetting() {
            if (!refs.bngForm) return;

            const payload = forms.toObject(refs.bngForm);

            try {
                ui.loading('Saving BNG connection...');
                const result = await api.post('/api/v1/cgnat/bng-setting', payload);
                ui.closeLoading();

                ui.toast('success', result?.message || 'BNG connection saved successfully.');
                await loadBngSetting(false);
                await detectRuntime(false);
            } catch (e) {
                ui.closeLoading();
                ui.toast('error', e.message || 'Failed to save BNG connection.');
            }
        }

        async function testBngConnection() {
            try {
                ui.loading('Testing BNG connection...');
                const result = await api.post('/api/v1/cgnat/bng-setting/test', {});
                ui.closeLoading();

                const data = result?.data || result || {};
                ui.toast(Boolean(data.ok) ? 'success' : 'error', Boolean(data.ok) ? 'BNG connection successful.' : 'BNG connection test failed.');
            } catch (e) {
                ui.closeLoading();
                ui.toast('error', e.message || 'Failed to test BNG connection.');
            }
        }

        function syncBngAuthUi() {
            const authType = String(refs.bngAuthType?.value || 'PASSWORD').toUpperCase();
            refs.bngPasswordWrap?.classList.toggle('d-none', authType !== 'PASSWORD');
            refs.bngKeyPathWrap?.classList.toggle('d-none', authType !== 'KEY');
        }

        async function detectRuntime(showToast = true) {
            try {
                const runtime = await api.get('/api/v1/cgnat/bng-runtime');
                state.bng.runtime = runtime || {};

                renderRuntime(runtime || {});
                renderSummary();

                if (showToast) {
                    ui.toast('success', 'BNG runtime refreshed.');
                }

                return true;
            } catch (e) {
                state.bng.runtime = {};
                renderRuntime({});
                renderSummary();

                if (showToast) {
                    ui.toast('error', e.message || 'Failed to detect BNG runtime.');
                }

                return false;
            }
        }

        async function createSvlanInterface() {
            const vlanId = Number(refs.svlanInput?.value || 0);

            if (!vlanId || vlanId < 1 || vlanId > 4094) {
                ui.toast('error', 'Enter a valid S-VLAN ID.');
                return;
            }

            try {
                ui.loading(`Creating S-VLAN interface ${vlanId}...`);

                const result = await api.post('/api/v1/cgnat/bng/create-svlan-interface', {
                    vlan_id: vlanId
                });

                ui.closeLoading();

                const data = result?.data || result || {};
                renderSvlanResult(data);

                ui.toast('success', result?.message || `S-VLAN ${vlanId} interface checked.`);
                await detectRuntime(false);
            } catch (e) {
                ui.closeLoading();
                renderSvlanError(e.message || 'Failed to create S-VLAN interface.');
                ui.toast('error', e.message || 'Failed to create S-VLAN interface.');
            }
        }

        function renderSvlanResult(data) {
            if (!refs.svlanResultBox) return;

            const svlan = data?.svlan || {};
            const bng = data?.bng_result || {};
            const results = bng?.results || {};

            html(refs.svlanResultBox, `
                <div class="nx-runtime-block">
                    <div class="nx-runtime-block__title">S-VLAN Interface Result</div>
                    <div class="nx-chip-list mb-3">
                        <span class="nx-chip nx-chip--success">OK</span>
                        <span class="nx-chip">VLAN: <strong class="ms-1">${escape(String(svlan.vlan_id || bng.svlan || '-'))}</strong></span>
                        <span class="nx-chip">Interface: <strong class="ms-1">${escape(bng.interface || '-')}</strong></span>
                    </div>
                    <div class="nx-runtime-rules">
                        ${Object.keys(results).length ? Object.entries(results).map(([key, row]) => `
                            <div class="nx-runtime-rule">
                                <strong>${escape(key)}:</strong>
                                exit=${escape(String(row.exit_code ?? '-'))}
                                ${row.stdout ? `<br>${escape(row.stdout)}` : ''}
                                ${row.stderr ? `<br>${escape(row.stderr)}` : ''}
                            </div>
                        `).join('') : `<div class="nx-runtime-rule">${escape(bng.message || 'Interface checked.')}</div>`}
                    </div>
                </div>
            `);
        }

        function renderSvlanError(message) {
            if (!refs.svlanResultBox) return;

            html(refs.svlanResultBox, `
                <div class="nx-runtime-block">
                    <div class="nx-runtime-block__title">S-VLAN Interface Result</div>
                    <div class="nx-chip-list mb-3">
                        <span class="nx-chip nx-chip--danger">FAILED</span>
                    </div>
                    <div class="nx-runtime-rule">${escape(message)}</div>
                </div>
            `);
        }

        function renderRuntime(runtime) {
            if (!refs.runtimeBox) return;

            const interfaces = runtime.interfaces || [];
            const parent = runtime.connection?.bng_parent_interface || '-';
            const detected = runtime.default_interface || '-';

            const vlanInterfaces = interfaces
                .filter(i => i.includes('.'))
                .map(i => {
                    const vlan = i.split('.')[1];
                    return `
                <div class="nx-vlan-row">
                    <div class="nx-vlan-id">VLAN ${escape(vlan)}</div>
                    <div class="nx-vlan-iface">${escape(i)}</div>
                    <div class="nx-vlan-status success">UP</div>
                </div>
            `;
                }).join('');

            html(refs.runtimeBox, `
        <div class="nx-runtime-panel">

            <div class="nx-runtime-card">
                <div class="nx-runtime-title">BNG Interface</div>
                <div class="nx-runtime-main">
                    <span class="nx-chip">${escape(parent)}</span>
                    <span class="nx-runtime-arrow">→</span>
                    <span class="nx-chip nx-chip--info">${escape(detected)}</span>
                </div>
            </div>

            <div class="nx-runtime-card">
                <div class="nx-runtime-title">S-VLAN Interfaces</div>
                <div class="nx-vlan-list">
                    ${vlanInterfaces || '<div class="nx-empty-inline">No VLAN interfaces</div>'}
                </div>
            </div>

        </div>
    `);
        }

        function renderSummary() {
            const setting = state.bng.setting || {};
            const runtime = state.bng.runtime || {};
            const connection = runtime.connection || {};

            text(refs.summaryHost, setting.host || connection.host || '-');
            text(refs.summaryParent, setting.bng_parent_interface || connection.bng_parent_interface || runtime.bng_parent_interface || '-');
            text(refs.summaryVlanMode, setting.vlan_mode || connection.vlan_mode || 'QINQ');
        }

        function renderPools() {
            if (!refs.poolsTbody) return;

            if (refs.poolsCount) {
                text(refs.poolsCount, String(state.pools.length));
            }

            if (!state.pools.length) {
                html(refs.poolsTbody, `
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No NAT pools found.</td>
                    </tr>
                `);
                return;
            }

            html(refs.poolsTbody, state.pools.map((pool) => `
                <tr data-pool-id="${Number(pool.id || 0)}">
                    <td class="ps-4">
                        <div class="fw-semibold">${escape(pool.pool_name || '')}</div>
                        <div class="small text-muted">${escape(pool.remarks || '')}</div>
                    </td>
                    <td>${escape(pool.type || '')}</td>
                    <td>${escape(pool.network || '')}</td>
                    <td>${escape(formatRange(pool.range_start, pool.range_end))}</td>
                    <td>${escape(pool.gateway || '')}</td>
                    <td>${escape(pool.accel_pool_name || '')}</td>
                    <td><span class="badge text-bg-light border">${escape(pool.status || '')}</span></td>
                    <td class="text-end pe-4">
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-secondary cgnat-edit-pool-btn" data-pool-id="${Number(pool.id || 0)}">Edit</button>
                            <button type="button" class="btn btn-outline-primary cgnat-preview-btn" data-pool-id="${Number(pool.id || 0)}">Preview</button>
                            <button type="button" class="btn btn-outline-success cgnat-apply-btn" data-pool-id="${Number(pool.id || 0)}">Apply</button>
                            <button type="button" class="btn btn-outline-danger cgnat-delete-btn" data-pool-id="${Number(pool.id || 0)}">Delete</button>
                        </div>
                    </td>
                </tr>
            `).join(''));
        }

        function openCreateModal() {
            resetForm();
            text(refs.poolModalTitle, 'Add NAT Pool');
            poolModal?.show();
        }

        function openEditModal(id) {
            const row = state.pools.find((x) => Number(x.id) === Number(id));
            if (!row) {
                ui.toast('error', 'NAT pool not found.');
                return;
            }

            resetForm();
            text(refs.poolModalTitle, 'Edit NAT Pool');

            val(refs.poolId, row.id || '');
            val(refs.poolName, row.pool_name || '');
            val(refs.poolType, row.type || 'CGNAT');
            val(refs.poolNetwork, row.network || '');
            val(refs.poolGateway, row.gateway || '');
            val(refs.poolRangeStart, row.range_start || '');
            val(refs.poolRangeEnd, row.range_end || '');
            val(refs.poolAccelName, row.accel_pool_name || '');
            val(refs.poolStatus, row.status || 'DRAFT');
            val(refs.poolRemarks, row.remarks || '');

            poolModal?.show();
        }

        function resetForm() {
            refs.poolForm?.reset();
            val(refs.poolId, '');
            val(refs.poolType, 'CGNAT');
            val(refs.poolStatus, 'DRAFT');
        }

        async function savePool() {
            const id = Number(val(refs.poolId) || 0);

            const payload = {
                pool_name: String(val(refs.poolName) || '').trim(),
                type: String(val(refs.poolType) || 'CGNAT').trim(),
                network: String(val(refs.poolNetwork) || '').trim(),
                gateway: toNull(val(refs.poolGateway)),
                range_start: toNull(val(refs.poolRangeStart)),
                range_end: toNull(val(refs.poolRangeEnd)),
                accel_pool_name: toNull(val(refs.poolAccelName)),
                status: String(val(refs.poolStatus) || 'DRAFT').trim(),
                remarks: toNull(val(refs.poolRemarks))
            };

            if (!payload.pool_name || !payload.type || !payload.network) {
                ui.toast('error', 'Pool name, type, and network are required.');
                return;
            }

            try {
                ui.loading('Saving NAT pool...');

                const result = id > 0
                    ? await api.post(`/api/v1/cgnat/pools/${id}/update`, payload)
                    : await api.post('/api/v1/cgnat/pools', payload);

                ui.closeLoading();
                poolModal?.hide();

                ui.toast('success', result?.message || 'NAT pool saved successfully.');
                await refreshPools();
            } catch (err) {
                ui.closeLoading();
                ui.toast('error', err.message || 'Failed to save NAT pool.');
            }
        }

        async function previewPool(id) {
            if (!id) return;

            try {
                ui.loading('Loading preview...');
                const data = await api.get(`/api/v1/cgnat/pools/${id}/preview`);
                ui.closeLoading();

                text(refs.accelPreview, data?.accel || 'No ACCEL preview available.');
                text(refs.frrPreview, data?.frr || 'No FRR preview available.');

                previewModal?.show();
            } catch (err) {
                ui.closeLoading();
                ui.toast('error', err.message || 'Failed to load preview.');
            }
        }

        async function applyPool(id) {
            if (!id) return;

            const confirmResult = await ui.confirm({
                title: 'Apply NAT Pool?',
                text: 'ACCEL will be staged only. FRR may be applied live depending on backend behavior.',
                confirmButtonText: 'Apply'
            });

            if (!confirmResult.isConfirmed) return;

            try {
                ui.loading('Applying NAT pool...');
                const result = await api.post(`/api/v1/cgnat/pools/${id}/apply`, {
                    apply_accel: 1,
                    apply_frr: 1
                });
                ui.closeLoading();

                ui.toast('success', result?.message || 'NAT pool apply completed.');
                await refreshPools();
                await detectRuntime(false);
            } catch (err) {
                ui.closeLoading();
                ui.toast('error', err.message || 'Failed to apply NAT pool.');
            }
        }

        async function deletePool(id) {
            if (!id) return;

            const confirmResult = await ui.confirmDelete('Delete NAT Pool?', 'This will permanently delete the selected NAT pool.');
            if (!confirmResult.isConfirmed) return;

            try {
                ui.loading('Deleting NAT pool...');
                const result = await api.post(`/api/v1/cgnat/pools/${id}/delete`, {});
                ui.closeLoading();

                ui.toast('success', result?.message || 'NAT pool deleted successfully.');
                await refreshPools();
            } catch (err) {
                ui.closeLoading();
                ui.toast('error', err.message || 'Failed to delete NAT pool.');
            }
        }

        async function refreshPools() {
            const pools = await api.get('/api/v1/cgnat/pools');
            state.pools = Array.isArray(pools) ? pools : [];
            renderPools();
        }

        function filterTable(tableSelector, queryText) {
            const tableEl = $(tableSelector);
            if (!tableEl) return;

            const tbody = tableEl.querySelector('tbody');
            if (!tbody) return;

            const query = String(queryText || '').trim().toLowerCase();
            const rows = Array.from(tbody.querySelectorAll('tr'));

            rows.forEach((row) => {
                const singleCell = row.querySelector('td[colspan]');
                if (singleCell) {
                    row.classList.remove('d-none');
                    return;
                }

                const haystack = row.innerText.toLowerCase();
                row.classList.toggle('d-none', !!query && !haystack.includes(query));
            });
        }

        async function loadSvlans() {
            try {
                const data = await api.get('/api/v1/cgnat/svlans');

                const rows = (data || []).map(v => ({
                    id: v.vlan_id,
                    name: `VLAN ${v.vlan_id} — ${v.name || ''}`
                }));

                NX.select.fill(refs.svlanInput, rows, {
                    valueKey: 'id',
                    label: r => r.name,
                    placeholder: 'Select S-VLAN'
                });

            } catch (e) {
                ui.toast('error', 'Failed to load S-VLANs');
            }
        }

        function formatRange(start, end) {
            const s = String(start || '').trim();
            const e = String(end || '').trim();
            if (!s && !e) return '';
            if (s && e) return `${s} - ${e}`;
            return s || e;
        }

        function toNull(v) {
            const value = String(v || '').trim();
            return value === '' ? null : value;
        }
    });
})();