document.addEventListener('DOMContentLoaded', () => {
    const page = document.getElementById('systemSettingsGeneralPage');
    const form = document.getElementById('generalSettingsForm');
    if (!page || !form || !window.NX) return;

    const setBusy = (busy) => form.querySelectorAll('input, select, button').forEach((el) => { el.disabled = busy; });
    const apply = (settings) => Object.entries(settings || {}).forEach(([key, value]) => {
        const fields = form.querySelectorAll(`[name="${CSS.escape(key)}"]`);
        fields.forEach((field) => {
            if (field.type === 'checkbox') field.checked = String(value) === '1' || value === true;
            else if (field.type !== 'hidden') field.value = value ?? '';
        });
    });

    const load = async () => {
        setBusy(true);
        try { apply(await window.NX.api.get('/api/v1/system-settings/general')); }
        catch (error) { window.NX.ui.toast('error', error.message || 'Unable to load system settings.'); }
        finally { setBusy(false); }
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        setBusy(true);
        try {
            const payload = Object.fromEntries(new FormData(form).entries());
            apply(await window.NX.api.post('/api/v1/system-settings/general', payload));
            window.NX.ui.toast('success', 'System settings saved.');
        } catch (error) { window.NX.ui.toast('error', error.message || 'Unable to save system settings.'); }
        finally { setBusy(false); }
    });

    load();
});
