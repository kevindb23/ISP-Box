(() => {
    const page = document.getElementById('apiTokensPage');
    if (!page || page.dataset.ready === '1') return;
    page.dataset.ready = '1';
    const api = window.NX?.api;
    const modalElement = document.getElementById('createApiTokenModal');
    const form = document.getElementById('createApiTokenForm');
    document.getElementById('createApiTokenButton')?.addEventListener('click', () => {
        form?.reset();
        bootstrap.Modal.getOrCreateInstance(modalElement).show();
    });
    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const data = new FormData(form);
        try {
            const result = await api.post('/api/v1/api-tokens/create', {
                name: String(data.get('name') || ''), description: String(data.get('description') || ''),
                purpose: String(data.get('purpose') || 'CUSTOM'), expires_at: String(data.get('expires_at') || '') || null,
                scopes: data.getAll('scopes[]')
            });
            document.getElementById('newApiTokenValue').value = result.data.token;
            document.getElementById('newApiTokenPanel').classList.remove('d-none');
            bootstrap.Modal.getOrCreateInstance(modalElement).hide();
        } catch (error) { window.NX?.ui?.toast('error', error.message); }
    });
    document.getElementById('copyApiTokenButton')?.addEventListener('click', async () => navigator.clipboard.writeText(document.getElementById('newApiTokenValue').value));
    page.querySelectorAll('.revoke-api-token').forEach((button) => button.addEventListener('click', async () => {
        if (!window.confirm('Revoke this token? Existing integrations using it will stop working.')) return;
        try { await api.post(`/api/v1/api-tokens/${button.dataset.id}/revoke`); window.location.reload(); } catch (error) { window.NX?.ui?.toast('error', error.message); }
    }));
})();
