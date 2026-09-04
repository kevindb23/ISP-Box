(function () {
    const page = document.getElementById('mfaPage');
    if (!page) return;
    const tableBody = document.querySelector('#mfaUsersTable tbody');
    const csrf = page.dataset.csrf;

    async function request(url, options = {}) {
        const response = await fetch(url, { ...options, headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, ...(options.headers || {}) } });
        const data = await response.json();
        if (!response.ok || data.success === false) throw new Error(data.message || 'Request failed.');
        return data.data || {};
    }

    function escapeHtml(value) {
        const node = document.createElement('div');
        node.textContent = value ?? '';
        return node.innerHTML;
    }

    function render(users) {
        tableBody.innerHTML = users.length ? users.map(user => {
            const enabled = Number(user.mfa_enabled) === 1 && user.verified_at;
            const method = user.mfa_method === 'EMAIL' ? 'Email OTP' : 'Authenticator';
            const name = user.full_name || user.username;
            return `<tr><td><div class="mfa-account-name">${escapeHtml(name)}</div><div class="mfa-account-id">@${escapeHtml(user.username)}</div></td><td>${escapeHtml(user.role)}</td><td>${escapeHtml(user.email || 'No email')}</td><td>${method}</td><td><span class="mfa-status ${enabled ? 'mfa-status--on' : 'mfa-status--off'}"><i class="bi ${enabled ? 'bi-check-circle' : 'bi-dash-circle'}" aria-hidden="true"></i>${enabled ? 'Enabled' : 'Disabled'}</span></td><td class="text-end"><button type="button" class="btn btn-sm btn-light border text-danger" data-action="reset" data-id="${user.id}" data-name="${escapeHtml(name)}" title="Reset MFA" aria-label="Reset MFA for ${escapeHtml(name)}"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i></button></td></tr>`;
        }).join('') : '<tr><td colspan="6" class="text-center text-muted py-5">No user accounts found.</td></tr>';
    }

    async function load() {
        tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-5">Loading security settings…</td></tr>';
        render((await request('/api/v1/mfa')).users || []);
    }

    async function confirmReset(name) {
        const message = `Reset MFA for ${name}? This disables MFA, removes the saved factor, and requires the user to set it up again.`;
        if (window.Swal) {
            return (await Swal.fire({ title: 'Reset MFA?', text: message, icon: 'warning', showCancelButton: true, confirmButtonText: 'Reset MFA', confirmButtonColor: '#dc3545' })).isConfirmed;
        }
        return window.confirm(message);
    }

    tableBody.addEventListener('click', async event => {
        const button = event.target.closest('[data-action="reset"]');
        if (!button || !(await confirmReset(button.dataset.name))) return;
        button.disabled = true;
        try {
            await request(`/api/v1/mfa/${button.dataset.id}/reset`, { method: 'POST', body: '{}' });
            await load();
            window.Swal?.fire({ icon: 'success', title: 'MFA reset', text: `${button.dataset.name} can set up MFA again from the Security page.` });
        } catch (error) {
            button.disabled = false;
            window.Swal?.fire({ icon: 'error', title: 'Unable to reset MFA', text: error.message });
        }
    });

    document.getElementById('mfaRefresh').addEventListener('click', () => load().catch(error => {
        tableBody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-5">${escapeHtml(error.message)}</td></tr>`;
    }));
    load().catch(error => { tableBody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-5">${escapeHtml(error.message)}</td></tr>`; });
}());
