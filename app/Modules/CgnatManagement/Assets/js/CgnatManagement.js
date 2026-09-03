(function () {
    document.addEventListener('DOMContentLoaded', () => {
        const app = document.getElementById('cgnatManagementApp');
        if (!app || !window.NX) return;
        const { api, ui, dom, forms, util } = window.NX;
        const { $, $$, html } = dom;
        const escape = util.escape;
        const refs = {
            config: $('#cgnatConfigForm'), apply: $('#cgnatApplyConfigBtn'), refresh: $('#cgnatRefreshRuntimeBtn'),
            removeRules: $('#cgnatRemoveRulesBtn'), rules: $('#cgnatRuntimeRulesBody'), interfaces: $('#cgnatInterfaceOptions')
        };
        let hasSavedConfig = false;

        $$('[data-cgnat-tab-btn]').forEach((button) => button.addEventListener('click', () => {
            $$('[data-cgnat-tab-btn]').forEach((item) => item.classList.toggle('active', item === button));
            $$('[data-cgnat-tab]').forEach((panel) => panel.classList.toggle('d-none', panel.dataset.cgnatTab !== button.dataset.cgnatTabBtn));
        }));
        refs.config?.addEventListener('submit', async (event) => {
            event.preventDefault();
            try { await api.post('/api/v1/cgnat/config', forms.toObject(refs.config)); ui.toast('success', 'CGNAT configuration saved. Apply it separately when ready.'); await loadState(); }
            catch (error) { ui.toast('error', error.message); }
        });
        refs.config?.addEventListener('input', () => {
            if (!hasSavedConfig) return;
            refs.apply.disabled = true;
            refs.apply.title = 'Save the edited configuration before applying it.';
        });
        refs.apply?.addEventListener('click', async () => {
            const value = forms.toObject(refs.config);
            if (!window.confirm(`Apply CGNAT state on the BNG server?\n\nBNG interface: ${value.bng_interface || '—'}\nEgress interface: ${value.egress_interface || '—'}\nSubscriber network: ${value.inside_network || '—'}\n\nThis can add public IP bindings and an iptables SNAT rule.`)) return;
            try { ui.loading('Applying CGNAT state…'); await api.post('/api/v1/cgnat/config/apply', {}); ui.closeLoading(); ui.toast('success', 'CGNAT state applied and verified.'); await loadState(); }
            catch (error) { ui.closeLoading(); ui.toast('error', error.message); }
        });
        refs.refresh?.addEventListener('click', loadState);
        refs.rules?.addEventListener('change', () => { refs.removeRules.disabled = selectedRuleHashes().length === 0; });
        refs.removeRules?.addEventListener('click', async () => {
            const hashes = selectedRuleHashes();
            if (!hashes.length) return;
            if (!window.confirm(`Remove ${hashes.length} selected POSTROUTING rule${hashes.length === 1 ? '' : 's'} from the BNG server?\n\nTraffic using these rules may lose NAT or management-network access immediately.`)) return;
            try { ui.loading('Removing selected POSTROUTING rules…'); await api.post('/api/v1/cgnat/postrouting/remove', { rule_hashes: hashes }); ui.closeLoading(); ui.toast('success', 'Selected POSTROUTING rules removed and verified.'); await loadState(); }
            catch (error) { ui.closeLoading(); ui.toast('error', error.message); }
        });

        async function loadState() {
            try {
                const response = await api.get('/api/v1/cgnat/config');
                forms.populate(refs.config, response.config || {});
                hasSavedConfig = Boolean(response.config?.id);
                refs.apply.disabled = !hasSavedConfig;
                refs.apply.title = refs.apply.disabled ? 'Save the CGNAT configuration before applying it.' : '';
                const runtime = response.runtime || {}, interfaces = runtime.interfaces || [], rules = runtime.nat_rules || [];
                html(refs.interfaces, interfaces.map((name) => `<option value="${escape(name)}"></option>`).join(''));
                html(refs.rules, runtime.warning ? `<tr><td></td><td class="text-danger py-4 text-center">${escape(runtime.warning)}</td></tr>` : (rules.length ? rules.map((item) => `<tr><td>${item.removable ? `<input class="form-check-input cgnat-rule-select" type="checkbox" value="${escape(item.hash)}" aria-label="Select POSTROUTING rule">` : ''}</td><td><code class="text-wrap">${escape(item.rule)}</code>${item.removable ? '' : '<div class="small text-muted">Chain policy is read-only.</div>'}</td></tr>`).join('') : '<tr><td></td><td class="text-muted py-4 text-center">No live POSTROUTING rules are configured.</td></tr>'));
                refs.removeRules.disabled = true;
                if (runtime.warning) ui.toast('warning', runtime.warning);
            } catch (error) { html(refs.rules, `<tr><td></td><td class="text-danger">${escape(error.message)}</td></tr>`); ui.toast('error', error.message); }
        }
        function selectedRuleHashes() { return [...refs.rules.querySelectorAll('.cgnat-rule-select:checked')].map((input) => input.value); }
        loadState();
    });
})();
