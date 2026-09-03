(function () {
    document.addEventListener('DOMContentLoaded', () => {
        const app = document.getElementById('bngManagementApp');
        if (!app || !window.NX) return;

        const { api, ui, dom, forms, util } = window.NX;
        const { $, $$, html } = dom;
        const escape = util.escape;
        const state = { setting: {}, runtime: {}, accel: {}, activeTab: 'bng', search: '' };
        const refs = {
            form: $('#bngBngForm'), add: $('#bngBngAddBtn'), tableBody: $('#bngBngTableBody'), modalEl: $('#bngBngModal'), modalTitle: $('#bngBngModalTitle'), test: $('#bngBngTestBtn'), refresh: $('#bngDetectBtn'),
            trustHost: $('#bngTrustHostBtn'), installRecovery: $('#bngInstallRecoveryBtn'),
            host: $('#bngSummaryStatus'), parent: $('#bngSummaryConfigured'), svlanCount: $('#bngSvlanCount'),
            cvlanCount: $('#bngCvlanCount'), pppCount: $('#bngPppCount'),
            svlan: $('#bngSvlanRuntime'), cvlan: $('#bngCvlanRuntime'), ppp: $('#bngPppRuntime'), search: $('#bngRuntimeSearch'),
            accelForm: $('#bngAccelForm'), accelStatus: $('#bngAccelStatus'), accelPreviewBtn: $('#bngAccelPreviewBtn'), accelStageBtn: $('#bngAccelStageBtn'), accelActivateBtn: $('#bngAccelActivateBtn'), accelPreviewPanel: $('#bngAccelPreviewPanel'), accelPreview: $('#bngAccelPreview')
        };
        const bngModal = window.bootstrap?.Modal && refs.modalEl ? window.bootstrap.Modal.getOrCreateInstance(refs.modalEl) : null;

        bindEvents();
        switchTab('bng');
        initialize();

        async function initialize() {
            await Promise.all([loadSetting(), loadAccel()]);
            if (state.setting?.id && state.setting?.host_key_trusted) {
                await refreshRuntime(false);
            } else {
                state.runtime = {};
                renderRuntime(state.setting?.id
                    ? 'Live runtime is unavailable until the BNG SSH host fingerprint is verified and trusted.'
                    : 'Configure a BNG connection to load live runtime interfaces.');
                renderSummary();
            }
        }

        function bindEvents() {
            refs.form?.addEventListener('submit', (event) => { event.preventDefault(); saveSetting(); });
            refs.add?.addEventListener('click', () => openSettingModal(false));
            refs.tableBody?.addEventListener('click', (event) => {
                if (event.target.closest('.bng-bng-edit')) openSettingModal(true);
                if (event.target.closest('.bng-bng-delete')) deleteSetting();
            });
            refs.test?.addEventListener('click', testConnection);
            refs.trustHost?.addEventListener('click', trustHostKey);
            refs.installRecovery?.addEventListener('click', installBootRecovery);
            refs.refresh?.addEventListener('click', () => refreshRuntime(true));
            refs.search?.addEventListener('input', () => { state.search = refs.search.value || ''; renderSettingRow(); renderRuntime(); });
            refs.accelForm?.addEventListener('submit', (event) => { event.preventDefault(); saveAccel(); });
            refs.accelPreviewBtn?.addEventListener('click', previewAccel);
            refs.accelStageBtn?.addEventListener('click', stageAccel);
            refs.accelActivateBtn?.addEventListener('click', activateAccel);
            $$('[data-bng-tab-btn]').forEach((button) => button.addEventListener('click', () => switchTab(button.dataset.bngTabBtn)));
        }

        function switchTab(name) {
            state.activeTab = name;
            $$('[data-bng-tab-btn]').forEach((button) => {
                const active = button.dataset.bngTabBtn === name;
                button.classList.toggle('active', active);
                button.classList.toggle('is-active', active);
            });
            $$('[data-bng-tab]').forEach((panel) => panel.classList.toggle('d-none', panel.dataset.bngTab !== name));
        }

        async function loadSetting() {
            try {
                state.setting = await api.get('/api/v1/bng/setting') || {};
                renderSettingRow();
                renderSummary();
            } catch (error) {
                renderSummary();
                ui.toast('error', error.message || 'Failed to load BNG settings.');
            }
        }

        async function saveSetting() {
            try {
                ui.loading('Saving BNG settings…');
                const result = await api.post('/api/v1/bng/setting', forms.toObject(refs.form));
                ui.closeLoading();
                bngModal?.hide();
                ui.toast('success', result?.message || 'BNG settings saved.');
                await loadSetting();
                await refreshRuntime(false);
            } catch (error) {
                ui.closeLoading();
                ui.toast('error', error.message || 'Failed to save BNG settings.');
            }
        }

        async function testConnection() {
            try {
                ui.loading('Testing BNG connection…');
                const result = await api.post('/api/v1/bng/setting/test', {});
                ui.closeLoading();
                const data = result?.data || result || {};
                ui.toast(data.ok ? 'success' : 'error', data.ok ? 'BNG connection successful.' : 'BNG connection failed.');
            } catch (error) {
                ui.closeLoading();
                ui.toast('error', error.message || 'BNG connection test failed.');
            }
        }

        async function trustHostKey() {
            try {
                const scan=await api.get('/api/v1/bng/setting/host-key');
                if(!window.confirm(`Verify this fingerprint through a trusted channel before continuing:\n\n${scan.fingerprint}\n\nTrust this BNG host key?`))return;
                await api.post('/api/v1/bng/setting/host-key/trust',{fingerprint:scan.fingerprint});
                ui.toast('success','BNG SSH host key trusted.');await loadSetting();
            } catch(error){ui.toast('error',error.message||'Unable to trust the BNG host key.');}
        }

        async function installBootRecovery() {
            const phrase = 'INSTALL BNG BOOT RECOVERY';
            const entered = window.prompt(`This installs and enables reboot recovery for saved VLAN and CGNAT desired state. It will not execute recovery or restart Accel-PPP now.\n\nType ${phrase} to continue.`);
            if (entered !== phrase) return;
            try {
                ui.loading('Installing BNG boot recovery…');
                await api.post('/api/v1/bng/reconciliation/install', { confirmation: phrase });
                ui.closeLoading();
                ui.toast('success', 'BNG boot recovery installed and enabled.');
            } catch (error) { ui.closeLoading(); ui.toast('error', error.message || 'Unable to install BNG boot recovery.'); }
        }

        async function deleteSetting() {
            const confirmed = typeof ui.confirmDelete === 'function'
                ? await ui.confirmDelete('Delete BNG connection?', 'The saved BNG connection settings will be removed.')
                : window.confirm('Delete this BNG connection?');
            if (typeof confirmed === 'object' ? !confirmed.isConfirmed : !confirmed) return;

            try {
                await api.post('/api/v1/bng/setting/delete', {});
                ui.toast('success', 'BNG connection deleted.');
                state.setting = {};
                refs.form.reset();
                renderSettingRow();
                renderSummary();
            } catch (error) {
                ui.toast('error', error.message || 'Failed to delete BNG connection.');
            }
        }

        function openSettingModal(editing) {
            refs.form.reset();
            if (editing && state.setting.id) forms.populate(refs.form, state.setting);
            refs.form.elements.auth_type.value = 'PASSWORD';
            refs.form.elements.vlan_mode.value = 'QINQ';
            refs.form.elements.auto_create_svlan_interface.value = '1';
            refs.modalTitle.textContent = editing ? 'Edit BNG Connection' : 'Add BNG Connection';
            bngModal?.show();
        }

        function renderSettingRow() {
            if (!refs.tableBody) return;
            const row = state.setting || {};
            const query = state.search.trim().toLowerCase();
            const matches = !query || JSON.stringify(row).toLowerCase().includes(query);
            refs.add?.classList.toggle('d-none', Boolean(row.id));
            refs.test?.toggleAttribute('disabled', !row.id);
            refs.trustHost?.toggleAttribute('disabled', !row.id);
            refs.installRecovery?.toggleAttribute('disabled', !row.id || !row.host_key_trusted);
            if (!row.id || !matches) {
                html(refs.tableBody, '<tr><td colspan="6"><div class="nx-empty-state py-4"><div class="nx-empty-icon"><i class="bi bi-router"></i></div><div class="nx-empty-title">No BNG connection configured</div><div class="nx-empty-text">Add an SSH endpoint to enable live BNG interface monitoring.</div></div></td></tr>');
                return;
            }
            html(refs.tableBody, `<tr><td><div class="nx-cell-title">${escape(row.host || '—')}:${escape(row.port || 22)}</div><div class="nx-cell-sub">${row.host_key_trusted ? escape(row.host_key_fingerprint||'Host key trusted') : '<span class="text-warning">Host key not trusted</span>'}</div></td><td>${escape(row.username || '—')}</td><td>Password</td><td><div class="nx-cell-title">${escape(row.bng_parent_interface || '—')}</div><div class="nx-cell-sub">Egress: ${escape(row.preferred_interface || 'Auto')}</div></td><td><span class="badge ${Number(row.enabled) === 1 ? 'text-bg-success' : 'text-bg-secondary'}">${Number(row.enabled) === 1 ? 'Active' : 'Inactive'}</span></td><td class="text-end text-nowrap"><button class="btn btn-sm btn-light border bng-bng-edit" type="button" title="Edit"><i class="bi bi-pencil"></i></button> <button class="btn btn-sm btn-light border text-danger bng-bng-delete" type="button" title="Delete"><i class="bi bi-trash"></i></button></td></tr>`);
        }

        async function refreshRuntime(showToast) {
            setRuntimeLoading();
            try {
                state.runtime = await api.get('/api/v1/bng/runtime') || {};
                renderRuntime();
                renderSummary();
                if (showToast) ui.toast('success', 'BNG runtime refreshed.');
            } catch (error) {
                state.runtime = {};
                renderRuntime(error.message || 'Unable to read BNG runtime.');
                renderSummary();
                if (showToast) ui.toast('error', error.message || 'BNG runtime refresh failed.');
            }
        }

        function renderSummary() {
            const runtime = state.runtime || {};
            refs.host.textContent = state.setting.host || 'Not configured';
            refs.parent.textContent = state.setting.bng_parent_interface || 'Not configured';
            refs.svlanCount.textContent = list(runtime.svlan_groups).length;
            refs.cvlanCount.textContent = list(runtime.client_vlan_interfaces).length;
            refs.pppCount.textContent = list(runtime.bng_interfaces).length;
        }

        function renderRuntime(error) {
            if (error) {
                const message = `<div class="alert alert-danger m-4">${escape(error)}</div>`;
                [refs.svlan, refs.cvlan, refs.ppp].forEach((node) => html(node, message));
                return;
            }
            renderSvlan();
            renderCvlan();
            renderPpp();
        }

        function renderSvlan() {
            const rows = filtered(state.runtime.svlan_groups);
            renderTable(refs.svlan, ['Interface', 'Parent', 'S-VLAN', 'State', 'C-VLANs'], rows.map((row) => [
                code(row.interface), code(row.parent_interface), escape(row.svlan ?? '—'), stateBadge(row.status), escape(list(row.clients).length)
            ]), 'No S-VLAN BNG interfaces were detected.');
        }

        function renderCvlan() {
            const rows = filtered(state.runtime.client_vlan_interfaces);
            renderTable(refs.cvlan, ['Interface', 'Parent', 'C-VLAN', 'State'], rows.map((row) => [
                code(row.interface), code(`${row.parent_interface}.${row.svlan}`), escape(row.cvlan ?? '—'), stateBadge(row.status)
            ]), 'No C-VLAN BNG interfaces were detected.');
        }

        function renderPpp() {
            const rows = filtered(state.runtime.bng_interfaces);
            renderTable(refs.ppp, ['PPP Interface', 'Local Address', 'Peer Address', 'PPP Username', 'Subscriber', 'Service ID'], rows.map((row) => [
                code(row.interface), code(row.local_address || '—'), code(row.peer_address || '—'), escape(row.ppp_username || '—'), escape(row.subscriber_name || '—'), escape(row.service_id || '—')
            ]), 'No active PPP interfaces were detected.');
        }

        function renderTable(target, headers, rows, emptyText) {
            if (!target) return;
            if (!rows.length) return html(target, `<div class="nx-empty-inline m-4">${escape(emptyText)}</div>`);
            html(target, `<div class="table-responsive nx-table-wrap"><table class="table table-hover align-middle mb-0 bng-runtime-table"><thead><tr>${headers.map((header) => `<th>${escape(header)}</th>`).join('')}</tr></thead><tbody>${rows.map((cells) => `<tr>${cells.map((cell) => `<td>${cell}</td>`).join('')}</tr>`).join('')}</tbody></table></div>`);
        }

        function setRuntimeLoading() {
            [refs.svlan, refs.cvlan, refs.ppp].forEach((node) => html(node, '<div class="nx-empty-inline m-4">Reading BNG interfaces…</div>'));
        }

        function list(value) { return Array.isArray(value) ? value : []; }
        function filtered(value) {
            const rows = list(value);
            const query = state.search.trim().toLowerCase();
            return query ? rows.filter((row) => JSON.stringify(row).toLowerCase().includes(query)) : rows;
        }
        function code(value) { return `<code>${escape(String(value ?? '—'))}</code>`; }
        function stateBadge(value) { const state = String(value || 'UNKNOWN').toUpperCase(); return `<span class="badge ${state === 'UP' ? 'text-bg-success' : state === 'DOWN' ? 'text-bg-danger' : 'text-bg-secondary'}">${escape(state)}</span>`; }

        async function loadAccel() {
            if (!refs.accelForm) return;
            try {
                const response = await api.get('/api/v1/bng/accel-ppp') || {};
                state.accel = response.profile || {};
                const values = Object.assign({}, response.defaults || {}, state.accel.config || {});
                if (Array.isArray(values.modules)) values.modules = values.modules.join(',');
                delete values.radius_secret; delete values.dae_secret;
                forms.populate(refs.accelForm, values);
                refs.accelStatus.textContent = state.accel.status || 'DRAFT';
                refs.accelStatus.className = `badge ${state.accel.status === 'STAGED' ? 'text-bg-warning' : state.accel.status === 'ACTIVE' ? 'text-bg-success' : 'text-bg-secondary'}`;
                refs.accelStageBtn.disabled = !state.accel.id;
                refs.accelActivateBtn.disabled = state.accel.status !== 'STAGED';
                (response.integration_warnings||[]).forEach((warning)=>ui.toast('warning',warning));
            } catch (error) { ui.toast('error', error.message || 'Unable to load Accel-PPP settings.'); }
        }

        async function saveAccel() {
            try {
                ui.loading('Saving Accel-PPP draft…');
                await api.post('/api/v1/bng/accel-ppp', forms.toObject(refs.accelForm));
                ui.closeLoading(); ui.toast('success', 'Accel-PPP draft saved. No restart was performed.'); await loadAccel();
            } catch (error) { ui.closeLoading(); ui.toast('error', error.message || 'Unable to save Accel-PPP draft.'); }
        }

        async function previewAccel() {
            try {
                const result = await api.get('/api/v1/bng/accel-ppp/preview');
                refs.accelPreview.textContent = result.config || '';
                refs.accelPreviewPanel.classList.remove('d-none');
            } catch (error) { ui.toast('error', error.message || 'Save the draft before previewing it.'); }
        }

        async function stageAccel() {
            const confirmed = window.confirm('Stage this configuration on the BNG for the next maintenance window? This will not restart Accel-PPP or disconnect subscribers.');
            if (!confirmed) return;
            try {
                ui.loading('Staging Accel-PPP configuration…');
                await api.post('/api/v1/bng/accel-ppp/stage', {});
                ui.closeLoading(); ui.toast('success', 'Configuration staged. No daemon restart was performed.'); await loadAccel();
            } catch (error) { ui.closeLoading(); ui.toast('error', error.message || 'Unable to stage Accel-PPP configuration.'); }
        }

        async function activateAccel() {
            const phrase=window.prompt('This restarts Accel-PPP and disconnects active subscribers. During the approved maintenance window, type ACTIVATE DURING MAINTENANCE to continue.');
            if(phrase!=='ACTIVATE DURING MAINTENANCE')return;
            if(!window.confirm('Final confirmation: disconnect current PPP sessions and activate the staged configuration now?'))return;
            try{ui.loading('Activating staged Accel-PPP configuration…');await api.post('/api/v1/bng/accel-ppp/activate-maintenance',{confirmation:phrase,acknowledge_disconnect:1});ui.closeLoading();ui.toast('success','Maintenance activation completed.');await loadAccel();await refreshRuntime(false);}catch(error){ui.closeLoading();ui.toast('error',error.message||'Maintenance activation failed.');}
        }
    });
})();
