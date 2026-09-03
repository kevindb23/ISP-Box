(function () {
    'use strict';

    function init() {
        const page = document.getElementById('radiusPage');
        if (!page || page.dataset.radiusReady === 'true' || !window.NX) return;
        page.dataset.radiusReady = 'true';

        const { api, ui, dom } = window.NX;
        const form = document.getElementById('radiusForm');
        const modalEl = document.getElementById('radiusFormModal');
        const modal = window.bootstrap?.Modal && modalEl ? window.bootstrap.Modal.getOrCreateInstance(modalEl) : null;
        const search = document.getElementById('radiusServerSearch');

        const field = (id) => document.getElementById(id);
        const reset = () => {
            form?.reset();
            field('radiusId').value = '';
            field('radiusActive').checked = true;
            field('radiusPassword').required = true;
            field('radiusPasswordHint').textContent = 'Required when creating a server.';
            field('radiusModalTitle').textContent = 'Add RADIUS Server';
        };

        document.getElementById('radiusAddBtn')?.addEventListener('click', () => { reset(); modal?.show(); });

        document.getElementById('radiusTableBody')?.addEventListener('click', async (event) => {
            const edit = event.target.closest('.radius-edit-btn');
            const remove = event.target.closest('.radius-delete-btn');
            if (edit) {
                try {
                    const row = await api.get(`/api/v1/radius/settings/${edit.dataset.id}`);
                    reset();
                    field('radiusId').value = row.id;
                    field('radiusHost').value = row.host || '';
                    field('radiusDbName').value = row.db_name || '';
                    field('radiusDbUser').value = row.db_user || '';
                    field('radiusPassword').value = '';
                    field('radiusPassword').required = false;
                    field('radiusPasswordHint').textContent = 'Leave blank to keep the current password.';
                    field('radiusActive').checked = Number(row.is_active) === 1;
                    field('radiusModalTitle').textContent = 'Edit RADIUS Server';
                    modal?.show();
                } catch (error) { ui.toast('error', error.message || 'Unable to load RADIUS settings.'); }
            }
            if (remove) {
                const confirmed = typeof ui.confirmDelete === 'function' ? await ui.confirmDelete('Delete RADIUS server?', 'The saved connection settings will be removed.') : window.confirm('Delete this RADIUS server?');
                if (typeof confirmed === 'object' ? !confirmed.isConfirmed : !confirmed) return;
                try {
                    await api.post('/api/v1/radius/settings/delete', { id: Number(remove.dataset.id) });
                    ui.toast('success', 'RADIUS settings deleted.');
                    window.location.reload();
                } catch (error) { ui.toast('error', error.message || 'Unable to delete RADIUS settings.'); }
            }
        });

        form?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const id = field('radiusId').value;
            const payload = {
                host: field('radiusHost').value.trim(),
                db_name: field('radiusDbName').value.trim(),
                db_user: field('radiusDbUser').value.trim(),
                db_password: field('radiusPassword').value,
                is_active: field('radiusActive').checked ? 1 : 0
            };
            try {
                const response = id ? await api.post(`/api/v1/radius/settings/${id}`, payload) : await api.post('/api/v1/radius/settings', payload);
                modal?.hide();
                ui.toast('success', response.message || 'RADIUS settings saved.');
                window.setTimeout(() => window.location.reload(), 350);
            } catch (error) { ui.toast('error', error.message || 'Unable to save RADIUS settings.'); }
        });

        search?.addEventListener('input', () => {
            const query = search.value.trim().toLowerCase();
            let visible = 0;
            document.querySelectorAll('[data-radius-row]').forEach((row) => {
                const show = !query || (row.dataset.search || '').includes(query);
                row.style.display = show ? '' : 'none';
                if (show) visible++;
            });
            const empty = document.getElementById('radiusEmptyRow');
            if (empty) empty.style.display = visible ? 'none' : '';
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
    else init();
})();
