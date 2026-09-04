(function () {
    const page = document.getElementById('subscriberSecurityPage');
    if (!page || !window.NX?.api) return;

    const alertBox = document.getElementById('subscriberSecurityAlert');
    const statusIcon = document.getElementById('spMfaStatusIcon');
    const statusTitle = document.getElementById('spMfaStatusTitle');
    const statusMessage = document.getElementById('spMfaStatusMessage');
    const statusBadge = document.getElementById('spMfaStatusBadge');
    const methodForm = document.getElementById('spMfaMethodForm');
    const startButton = document.getElementById('spMfaStartBtn');
    const setupPanel = document.getElementById('spMfaSetupPanel');
    const setupContent = document.getElementById('spMfaSetupContent');
    const codeInput = document.getElementById('spMfaCode');
    const completeButton = document.getElementById('spMfaCompleteBtn');
    const disablePanel = document.getElementById('spMfaDisablePanel');
    const disableButton = document.getElementById('spMfaDisableBtn');
    const currentMethod = document.getElementById('spMfaCurrentMethod');
    const emailChoice = document.getElementById('spMfaEmail');
    const emailHelp = document.getElementById('spMfaEmailHelp');
    const methodChoices = [...methodForm.querySelectorAll('input[name="method"]')];
    let setup = null;

    function syncMethodSelection() {
        methodChoices.forEach((choice) => {
            choice.closest('.sp-security-method')?.classList.toggle('is-selected', choice.checked);
        });
    }

    methodChoices.forEach((choice) => choice.addEventListener('change', syncMethodSelection));
    syncMethodSelection();

    function escapeHtml(value) {
        const node = document.createElement('div');
        node.textContent = value ?? '';
        return node.innerHTML;
    }

    function showAlert(message, type = 'danger') {
        if (!alertBox) return;
        alertBox.innerHTML = `<div class="alert alert-${type}" role="alert">${escapeHtml(message)}</div>`;
    }

    function setBusy(button, busy, label) {
        if (!button) return;
        button.disabled = busy;
        if (busy) button.dataset.originalLabel = button.innerHTML;
        button.innerHTML = busy ? '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Working…' : (button.dataset.originalLabel || label);
    }

    function methodLabel(method) {
        return method === 'EMAIL' ? 'Email OTP' : 'Authenticator app';
    }

    function renderStatus(data) {
        const enabled = data.enabled === true;
        const method = String(data.method || '').toUpperCase();

        statusTitle.textContent = enabled ? 'MFA is enabled' : 'MFA is disabled';
        statusMessage.textContent = enabled
            ? `Your account uses ${methodLabel(method)} for sign-in verification.`
            : 'Add a second verification step to help protect your account.';
        statusBadge.textContent = enabled ? 'Enabled' : 'Disabled';
        statusBadge.className = `badge ${enabled ? 'bg-success' : 'bg-secondary'}`;
        statusIcon.innerHTML = `<i class="bi ${enabled ? 'bi-shield-check' : 'bi-shield-exclamation'}" aria-hidden="true"></i>`;
        methodForm.hidden = false;
        setupPanel.hidden = !setup;
        disablePanel.hidden = !enabled;
        currentMethod.textContent = `Current method: ${methodLabel(method)}`;

        if (method === 'AUTHENTICATOR' || method === 'EMAIL') {
            methodChoices.forEach((choice) => {
                choice.checked = choice.value === method;
            });
            syncMethodSelection();
        }
        startButton.innerHTML = `<i class="bi bi-${enabled ? 'arrow-repeat' : 'shield-check'}" aria-hidden="true"></i> ${enabled ? 'Change MFA method' : 'Set up MFA'}`;

        if (!data.email_available) {
            emailChoice.disabled = true;
            emailHelp.textContent = 'Add a valid email address to your account before using Email OTP.';
        } else {
            emailChoice.disabled = false;
            emailHelp.textContent = `Receive a short-lived six-digit code at ${data.email}.`;
        }
    }

    async function load() {
        try {
            renderStatus(await window.NX.api.get('/api/v1/subscriber-portal/security'));
        } catch (error) {
            showAlert(error.message || 'Unable to load security settings.');
        }
    }

    methodForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const method = methodForm.querySelector('input[name="method"]:checked')?.value || '';
        showAlert('', 'info');
        setBusy(startButton, true, 'Set up MFA');

        try {
            const response = await window.NX.api.post('/api/v1/subscriber-portal/security/enroll', { method });
            setup = response.data || {};
            setupContent.innerHTML = setup.qr_data_uri
                ? `<img class="sp-mfa-qr" src="${escapeHtml(setup.qr_data_uri)}" alt="Authenticator setup QR code"><div><strong>Scan the QR code</strong><p class="text-muted small mt-1">Or enter this setup key manually:</p><div class="sp-mfa-secret">${escapeHtml(setup.secret)}</div></div>`
                : '<p><strong>Check your email.</strong><br><span class="text-muted">A six-digit verification code was sent to your account email.</span></p>';
            codeInput.value = '';
            setupPanel.hidden = false;
            codeInput.focus();
            showAlert(enabledMessage(), 'success');
        } catch (error) {
            showAlert(error.message || 'Unable to start MFA enrollment.');
        } finally {
            setBusy(startButton, false, 'Set up MFA');
        }
    });

    function enabledMessage() {
        return setup && setup.method
            ? 'Enrollment started. Complete verification to enable this MFA method.'
            : 'Enrollment started. Complete verification to enable MFA.';
    }

    completeButton.addEventListener('click', async () => {
        if (!setup) return;
        setBusy(completeButton, true, 'Verify and enable');

        try {
            await window.NX.api.post('/api/v1/subscriber-portal/security/complete', {
                code: codeInput.value,
                challenge_token: setup.challenge_token || null,
            });
            setup = null;
            setupPanel.hidden = true;
            showAlert('MFA is now enabled on your account.', 'success');
            await load();
        } catch (error) {
            showAlert(error.message || 'The verification code is invalid or expired.');
        } finally {
            setBusy(completeButton, false, 'Verify and enable');
        }
    });

    disableButton.addEventListener('click', async () => {
        if (!window.confirm('Disable MFA for your subscriber portal account?')) return;
        setBusy(disableButton, true, 'Disable MFA');

        try {
            await window.NX.api.post('/api/v1/subscriber-portal/security/disable', {});
            showAlert('MFA has been disabled on your account.', 'success');
            await load();
        } catch (error) {
            showAlert(error.message || 'Unable to disable MFA.');
        } finally {
            setBusy(disableButton, false, 'Disable MFA');
        }
    });

    load();
}());
