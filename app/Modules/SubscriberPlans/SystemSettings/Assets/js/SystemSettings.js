document.addEventListener('DOMContentLoaded', () => {
    const app = document.getElementById('systemSettingsGeneralPage');
    if (!app || !window.NX) return;

    const { api, ui, dom } = window.NX;
    const { html } = dom;

    const form = document.getElementById('generalSettingsForm');

    async function load() {
        const data = await api.get('/api/v1/system-settings/general');

        Object.keys(data).forEach(k => {
            const el = form.querySelector(`[name="${k}"]`);
            if (el) el.value = data[k];
        });
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = Object.fromEntries(new FormData(form).entries());

        try {
            await api.post('/api/v1/system-settings/general', formData);
            ui.toast('success', 'Settings saved');
        } catch (err) {
            ui.toast('error', err.message || 'Failed to save');
        }
    });

    load();
});