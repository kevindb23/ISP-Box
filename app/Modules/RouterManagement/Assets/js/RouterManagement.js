(function () {
    document.addEventListener('DOMContentLoaded', () => {
        const app = document.getElementById('routerManagementApp');
        if (!app || !window.NX) return;
        const { api, ui, dom, forms } = window.NX;
        const { $, $$ } = dom;
        const refs = {
            core: $('#routerCoreForm'), frr: $('#routerFrrForm'),
            corePreview: $('#routerCorePreview'), frrPreview: $('#routerFrrPreview'),
            coreRuntime: $('#routerCoreRuntime'), frrRuntime: $('#routerFrrRuntime'),
            coreTrust: $('#routerCoreTrustBtn'), coreRefresh: $('#routerCoreRefreshBtn'),
            frrRefresh: $('#routerFrrRefreshBtn'), coreApply: $('#routerCoreApplyBtn'),
            frrApply: $('#routerFrrApplyBtn')
        };
        const state = { coreHash: null, frrHash: null, coreSaved: false, frrSaved: false, coreTrusted: false };

        $$('[data-router-tab-btn]').forEach(button => button.addEventListener('click', () => {
            $$('[data-router-tab-btn]').forEach(item => item.classList.toggle('active', item === button));
            $$('[data-router-tab]').forEach(item => item.classList.toggle('d-none', item.dataset.routerTab !== button.dataset.routerTabBtn));
        }));
        refs.core.addEventListener('submit', event => { event.preventDefault(); save('core', refs.core); });
        refs.frr.addEventListener('submit', event => { event.preventDefault(); save('frr', refs.frr); });
        refs.core.addEventListener('input', () => markDirty('core'));
        refs.frr.addEventListener('input', () => markDirty('frr'));
        refs.coreTrust.addEventListener('click', trust);
        refs.coreRefresh.addEventListener('click', () => runtime('core', refs.coreRuntime));
        refs.frrRefresh.addEventListener('click', () => runtime('frr', refs.frrRuntime));
        refs.coreApply.addEventListener('click', () => apply('CORE'));
        refs.frrApply.addEventListener('click', () => apply('FRR'));

        function updateButtons() {
            refs.coreApply.disabled = !state.coreSaved || !state.coreHash;
            refs.frrApply.disabled = !state.frrSaved || !state.frrHash;
            refs.coreTrust.disabled = !state.coreSaved;
            refs.coreRefresh.disabled = !state.coreSaved || !state.coreTrusted;
            refs.coreApply.title = refs.coreApply.disabled ? 'Save the current Core Router draft before applying it.' : '';
            refs.frrApply.title = refs.frrApply.disabled ? 'Save the current FRR Router draft before applying it.' : '';
        }

        function markDirty(type) {
            state[`${type}Saved`] = false;
            state[`${type}Hash`] = null;
            updateButtons();
        }

        async function load() {
            try {
                const response = await api.get('/api/v1/routers');
                const core = Object.assign({}, response.core_defaults || {}, response.core?.config || {}, response.core || {});
                const frr = Object.assign({}, response.frr_defaults || {}, response.frr?.config || {});
                delete core.password;
                delete core.known_host_key;
                forms.populate(refs.core, core);
                forms.populate(refs.frr, frr);
                refs.corePreview.textContent = response.core?.rendered_config || 'Save a draft to generate the configuration.';
                refs.frrPreview.textContent = response.frr?.rendered_config || 'Save a draft to generate the configuration.';
                state.coreSaved = Boolean(response.core?.id);
                state.frrSaved = Boolean(response.frr?.id);
                state.coreTrusted = Boolean(response.core?.host_key_trusted);
                state.coreHash = response.core_hash || null;
                state.frrHash = response.frr_hash || null;
                updateButtons();
            } catch (error) { ui.toast('error', error.message); }
        }

        async function save(type, form) {
            try {
                await api.post(`/api/v1/routers/${type}`, forms.toObject(form));
                ui.toast('success', `${type === 'core' ? 'Core' : 'FRR'} Router draft saved.`);
                await load();
            } catch (error) { ui.toast('error', error.message); }
        }

        async function trust() {
            try {
                const scan = await api.get('/api/v1/routers/core/host-key');
                if (!confirm(`Verify this Juniper fingerprint through a trusted channel:\n\n${scan.fingerprint}\n\nTrust this host key?`)) return;
                await api.post('/api/v1/routers/core/host-key/trust', { fingerprint: scan.fingerprint });
                ui.toast('success', 'Core Router host key trusted.');
                await load();
            } catch (error) { ui.toast('error', error.message); }
        }

        async function runtime(type, target) {
            try {
                target.textContent = 'Loading…';
                const response = await api.get(`/api/v1/routers/${type}/runtime`);
                target.textContent = response.output || response.error || 'No output returned.';
            } catch (error) { target.textContent = error.message; ui.toast('error', error.message); }
        }

        async function apply(type) {
            const key = type.toLowerCase();
            const hash = state[`${key}Hash`];
            if (!state[`${key}Saved`] || !hash) { ui.toast('warning', 'Save the current draft before applying it.'); return; }
            const phrase = `APPLY ${type} ROUTER CONFIG`;
            const entered = prompt(`Saved configuration SHA-256:\n${hash}\n\nType ${phrase} to continue.`);
            if (entered !== phrase || !confirm('Final confirmation: push and commit this exact saved Router draft now?')) return;
            try {
                ui.loading(`Applying ${type} Router configuration…`);
                await api.post(`/api/v1/routers/${key}/apply`, { confirmation: phrase, config_hash: hash });
                ui.closeLoading();
                ui.toast('success', `${type} Router configuration applied.`);
                await runtime(key, type === 'CORE' ? refs.coreRuntime : refs.frrRuntime);
            } catch (error) { ui.closeLoading(); ui.toast('error', error.message); }
        }
        load();
    });
})();
