(function () {
    'use strict';

    function initBranding() {
    const page = document.getElementById('brandingPage');

    if (!page || page.dataset.ready === 'true' || !window.NX) {
        return;
    }

    page.dataset.ready = 'true';

    const { api, ui } = window.NX;

    const refs = {
        form: document.getElementById('brandingForm'),

        saveBtn: document.getElementById('btnSaveBranding'),
        refreshBtn: document.getElementById('btnRefreshBranding'),

        companyName: document.getElementById('companyName'),
        logoText: document.getElementById('logoText'),
        companyAddress: document.getElementById('companyAddress'),
        supportEmail: document.getElementById('supportEmail'),
        supportPhone: document.getElementById('supportPhone'),
        companyTin: document.getElementById('companyTin'),
        companyWebsite: document.getElementById('companyWebsite'),

        logoPath: document.getElementById('logoPath'),
        removeLogo: document.getElementById('removeLogo'),
        companyLogoInput: document.getElementById('companyLogoInput'),
        removeLogoBtn: document.getElementById('btnRemoveLogo'),

        previewName: document.getElementById('brandingPreviewName'),
        previewLogo: document.getElementById('brandingPreviewLogo'),
        logoPreview: document.getElementById('brandingLogoPreview'),
    };

    bindEvents();
    loadBranding(false);

    function bindEvents() {
        refs.refreshBtn?.addEventListener('click', () => loadBranding(true));
        refs.saveBtn?.addEventListener('click', saveBranding);

        refs.companyName?.addEventListener('input', updatePreview);
        refs.logoText?.addEventListener('input', updatePreview);

        refs.companyLogoInput?.addEventListener('change', () => {
            const file = refs.companyLogoInput.files?.[0];

            if (!file) {
                return;
            }

            if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) {
                refs.companyLogoInput.value = '';
                ui.toast('error', 'Logo must be PNG, JPG, or WEBP.');
                return;
            }

            if (file.size > 2 * 1024 * 1024) {
                refs.companyLogoInput.value = '';
                ui.toast('error', 'Logo must not exceed 2MB.');
                return;
            }

            refs.removeLogo.value = '0';

            const reader = new FileReader();

            reader.onload = () => {
                renderLogo(reader.result);
            };

            reader.readAsDataURL(file);
        });

        refs.removeLogoBtn?.addEventListener('click', () => {
            refs.companyLogoInput.value = '';
            refs.logoPath.value = '';
            refs.removeLogo.value = '1';

            renderLogo('');
        });
    }

    async function loadBranding(showToast = true) {
        try {
            const data = await api.get('/api/v1/branding');

            setForm(data || {});
            updatePreview();

            if (showToast) {
                ui.toast('success', 'Branding loaded');
            }
        } catch (error) {
            ui.swal({
                icon: 'error',
                title: 'Branding Error',
                text: error.message || 'Failed to load branding.',
            });
        }
    }

    async function saveBranding() {
        try {
            const fd = new FormData(refs.form);

            const result = await api.post('/api/v1/branding', fd);
            const savedBranding = result.data || {};

            ui.toast('success', result.message || 'Branding saved');
            setForm(savedBranding);
            updateLiveBrand(savedBranding);
        } catch (error) {
            ui.swal({
                icon: 'error',
                title: 'Save Failed',
                text: error.message || 'Failed to save branding.',
            });
        }
    }

    function setForm(data) {
        refs.companyName.value = data.company_name || '';
        refs.logoText.value = data.logo_text ?? data.portal_title ?? '';
        refs.companyAddress.value = data.company_address || '';
        refs.supportEmail.value = data.support_email || '';
        refs.supportPhone.value = data.support_phone || '';
        refs.companyTin.value = data.tin || '';
        refs.companyWebsite.value = data.website || '';

        refs.logoPath.value = data.logo_path || '';
        refs.removeLogo.value = '0';

        renderLogo(data.logo_path || '');
    }

    function updatePreview() {
        const logoText = refs.logoText.value.trim();

        refs.previewName.textContent = logoText;
    }

    function updateLiveBrand(data) {
        const companyName = String(data.company_name || '').trim() || 'ISP-In-A-BOX';
        const logoUrl = String(data.logo_path || '').trim();

        document.querySelectorAll('[data-brand-company-name]').forEach((element) => {
            element.textContent = companyName;
            element.title = companyName;
        });

        document.querySelectorAll('[data-brand-logo]').forEach((element) => {
            const replacement = createBrandLogo(logoUrl, companyName);
            element.replaceWith(replacement);
        });

        document.title = `${companyName} · Operations console`;
        updateFavicon(logoUrl);

        window.dispatchEvent(new CustomEvent('nexusbox:branding-updated', {
            detail: { ...data, company_name: companyName, logo_path: logoUrl },
        }));
    }

    function createBrandLogo(logoUrl, companyName) {
        if (logoUrl) {
            const image = document.createElement('img');
            image.src = logoUrl;
            image.alt = `${companyName} logo`;
            image.className = 'sidebar-brand-logo';
            image.dataset.brandLogo = '';
            return image;
        }

        const icon = document.createElement('i');
        icon.className = 'bi bi-hdd-network';
        icon.dataset.brandLogo = '';
        icon.setAttribute('aria-hidden', 'true');
        return icon;
    }

    function updateFavicon(logoUrl) {
        const current = document.querySelector('link[rel="icon"]');

        if (!logoUrl) {
            current?.remove();
            return;
        }

        const favicon = current || document.createElement('link');
        favicon.rel = 'icon';
        favicon.href = logoUrl;

        if (!current) {
            document.head.appendChild(favicon);
        }
    }

    function renderLogo(logoUrl) {
        const safeLogoUrl = String(logoUrl || '').trim();

        const fallbackIcon = '<i class="bi bi-hdd-network"></i>';

        if (safeLogoUrl) {
            const img = `<img src="${escapeAttr(safeLogoUrl)}" alt="Company Logo">`;

            refs.logoPreview.innerHTML = img;
            refs.previewLogo.innerHTML = img;
        } else {
            refs.logoPreview.innerHTML = fallbackIcon;
            refs.previewLogo.innerHTML = fallbackIcon;
        }

        updatePreview();
    }

    function escapeAttr(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBranding, { once: true });
    } else {
        initBranding();
    }
})();
