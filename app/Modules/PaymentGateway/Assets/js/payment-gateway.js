document.addEventListener('DOMContentLoaded', () => {
    const app = document.getElementById('paymentGatewayPage');
    if (!app || !window.NX) return;

    const {
        api,
        ui,
        dom,
        util,
        page,
        render
    } = window.NX;

    const { $, html, text } = dom;
    const { escape, safeArray, formatDateTime, upper } = util;

    let tableInstance = null;

    const API = {
        settings: '/api/v1/payment-gateway/settings',
        settingsSave: '/api/v1/payment-gateway/settings/save',
        transactions: '/api/v1/payment-gateway/transactions'
    };

    const refs = {
        enabled: null,
        mode: null,
        publicKey: null,
        secretKey: null,

        saveBtn: null,
        refreshBtn: null,

        transactionHost: null,
        transactionBody: null,
        transactionCount: null
    };

    const gatewayPage = page.create({
        storageKey: 'nexusbox_payment_gateway_ui_state_v1',
        state: {
            settings: {},
            transactions: [],
            loading: false,
            loaded: false
        },

        async init(ctx) {
            cacheDom();
            bindDomState(ctx);
        },

        async load(ctx) {
            await loadSettings(ctx);
            await loadTransactions(ctx, { silent: false });
        },

        events(ctx) {
            bindEvents(ctx);
        },

        render(ctx) {
            renderTransactions(ctx);
        }
    });

    function cacheDom() {
        refs.enabled = $('#paymongoEnabled');
        refs.mode = $('#paymongoMode');
        refs.publicKey = $('#paymongoPublicKey');
        refs.secretKey = $('#paymongoSecretKey');

        refs.saveBtn = $('#btnSaveGatewaySettings');
        refs.refreshBtn = $('#btnRefreshGatewayTransactions');

        refs.transactionHost = $('#paymentGatewayTransactionHost');
        refs.transactionBody = $('#paymentGatewayTransactionBody');
        refs.transactionCount = $('#paymentGatewayTransactionCount');
    }

    function bindDomState(ctx) {
        applySettingsToForm(ctx.state.settings || {});
    }

    function bindEvents(ctx) {
        refs.saveBtn?.addEventListener('click', async () => {
            await saveSettings(ctx);
        });

        refs.refreshBtn?.addEventListener('click', async () => {
            await loadTransactions(ctx, { silent: false });
            nxToast('success', 'Payment gateway transactions refreshed.');
        });
    }

    async function loadSettings(ctx) {
        try {
            const response = await api.get(API.settings);
            const settings = response.data || response || {};

            ctx.set('settings', settings);
            applySettingsToForm(settings);
        } catch (err) {
            console.error('[PaymentGateway] Failed to load settings', err);
            nxToast('error', err?.message || 'Failed to load gateway settings.');
        }
    }

    async function saveSettings(ctx) {
        const payload = {
            paymongo_enabled: refs.enabled?.checked ? 1 : 0,
            paymongo_mode: refs.mode?.value || 'test',
            paymongo_public_key: refs.publicKey?.value?.trim() || '',
            paymongo_secret_key: refs.secretKey?.value?.trim() || ''
        };

        setButtonBusy(refs.saveBtn, true, 'Saving...');

        try {
            const response = await api.post(API.settingsSave, payload);
            const settings = response.data || response || payload;

            ctx.set('settings', settings);
            applySettingsToForm(settings);

            nxToast('success', response.message || 'Payment gateway settings saved.');
        } catch (err) {
            console.error('[PaymentGateway] Save failed', err);
            nxToast('error', err?.message || 'Unable to save gateway settings.');
        } finally {
            setButtonBusy(refs.saveBtn, false);
        }
    }

    async function loadTransactions(ctx, { silent = true } = {}) {
        if (!silent && refs.transactionHost) {
            html(refs.transactionHost, render.skeletonTable(5, 7));
        } else if (!silent && refs.transactionBody) {
            html(refs.transactionBody, `
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">
                        Loading transactions...
                    </td>
                </tr>
            `);
        }

        try {
            const response = await api.get(API.transactions);

            const rows = safeArray(
                response.data?.items ||
                response.items ||
                response.data ||
                response ||
                []
            );

            ctx.patch({
                transactions: normalizeTransactions(rows),
                loaded: true
            });
        } catch (err) {
            console.error('[PaymentGateway] Failed to load transactions', err);

            ctx.patch({
                transactions: [],
                loaded: true
            });

            nxToast('error', err?.message || 'Failed to load payment gateway transactions.');
        }
    }

    function applySettingsToForm(settings = {}) {
        if (refs.enabled) {
            refs.enabled.checked = Number(settings.paymongo_enabled || 0) === 1;
        }

        if (refs.mode) {
            refs.mode.value = String(settings.paymongo_mode || 'test').toLowerCase();
        }

        if (refs.publicKey) {
            refs.publicKey.value = settings.paymongo_public_key || '';
        }

        if (refs.secretKey) {
            refs.secretKey.value = settings.paymongo_secret_key || '';
        }
    }

    function normalizeTransactions(rows) {
        return safeArray(rows).map((row) => ({
            id: row.id ?? 0,
            payment_id: row.payment_id ?? null,
            invoice_id: row.invoice_id ?? null,
            subscriber_id: row.subscriber_id ?? null,
            gateway: row.gateway ?? 'PAYMONGO',
            gateway_reference: row.gateway_reference ?? '',
            gateway_payment_url: row.gateway_payment_url ?? '',
            gateway_status: upper(row.gateway_status || 'PENDING'),
            amount: Number(row.amount || 0),
            currency: row.currency || 'PHP',
            invoice_no: row.invoice_no || '',
            invoice_status: row.invoice_status || '',
            balance_amount: Number(row.balance_amount || 0),
            subscriber_name: row.subscriber_name || '',
            account_number: row.account_number || '',
            created_at: row.created_at || '',
            updated_at: row.updated_at || ''
        }));
    }

    function renderTransactions(ctx) {
        const rows = safeArray(ctx.state.transactions);

        if (refs.transactionCount) {
            text(
                refs.transactionCount,
                `${rows.length} ${rows.length === 1 ? 'record' : 'records'}`
            );
        }

        if (refs.transactionHost) {
            renderTransactionsTableHost(rows);
            return;
        }

        renderTransactionsLegacyBody(rows);
    }

    function renderTransactionsTableHost(rows) {
        if (!refs.transactionHost) return;

        if (!rows.length) {
            if (tableInstance) {
                tableInstance.destroy();
                tableInstance = null;
            }

            html(refs.transactionHost, `
                <div class="text-center text-muted py-5">
                    <div class="mb-2">
                        <i class="bi bi-credit-card-2-front fs-2"></i>
                    </div>
                    <div class="fw-semibold">No payment gateway transactions found</div>
                    <div class="small">PayMongo checkout sessions will appear here.</div>
                </div>
            `);
            return;
        }

        html(refs.transactionHost, '<div id="paymentGatewayDataTable"></div>');

        if (tableInstance) {
            tableInstance.destroy();
            tableInstance = null;
        }

        tableInstance = window.NX.datatable.create({
            el: '#paymentGatewayDataTable',
            rows,
            search: false,
            paginate: true,
            pager: { currentPage: 1, rowsPerPage: 20 },
            sort: { key: 'id', dir: 'desc' },
            columns: [
                {
                    key: 'gateway_reference',
                    label: 'Reference',
                    render: (value, row) => `
                        <div class="nx-cell-stack">
                            <div class="nx-cell-title nx-text-mono">${escape(value || '-')}</div>
                            <div class="nx-cell-sub">${escape(row.gateway || 'PAYMONGO')}</div>
                        </div>
                    `
                },
                {
                    key: 'invoice_no',
                    label: 'Invoice',
                    render: (value, row) => `
                        <div class="nx-cell-stack">
                            <div class="nx-cell-title">${escape(value || '-')}</div>
                            <div class="nx-cell-sub">${escape(row.invoice_status || '-')}</div>
                        </div>
                    `
                },
                {
                    key: 'subscriber_name',
                    label: 'Subscriber',
                    render: (value, row) => `
                        <div class="nx-cell-stack">
                            <div class="nx-cell-title">${escape(value || '-')}</div>
                            <div class="nx-cell-sub">${escape(row.account_number || '-')}</div>
                        </div>
                    `
                },
                {
                    key: 'gateway_status',
                    label: 'Status',
                    render: value => renderStatusBadge(value)
                },
                {
                    key: 'amount',
                    label: 'Amount',
                    render: value => `<span class="fw-semibold">${money(value)}</span>`
                },
                {
                    key: 'created_at',
                    label: 'Created',
                    render: value => escape(formatSafeDate(value))
                },
                {
                    key: '__actions',
                    label: 'Actions',
                    render: (_, row) => renderActionButton(row)
                }
            ]
        });
    }

    function renderTransactionsLegacyBody(rows) {
        if (!refs.transactionBody) return;

        if (!rows.length) {
            html(refs.transactionBody, `
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">
                        No transactions found
                    </td>
                </tr>
            `);
            return;
        }

        html(refs.transactionBody, rows.map(row => `
            <tr>
                <td>
                    <div class="fw-semibold nx-text-mono">${escape(row.gateway_reference || '-')}</div>
                    <div class="small text-muted">${escape(row.gateway || 'PAYMONGO')}</div>
                </td>

                <td>${escape(row.invoice_no || '-')}</td>

                <td>
                    <div class="fw-semibold">${escape(row.subscriber_name || '-')}</div>
                    <div class="small text-muted">${escape(row.account_number || '-')}</div>
                </td>

                <td>${renderStatusBadge(row.gateway_status)}</td>

                <td class="text-end">${money(row.amount)}</td>

                <td>${escape(formatSafeDate(row.created_at))}</td>

                <td class="text-end">${renderActionButton(row)}</td>
            </tr>
        `).join(''));
    }

    function renderActionButton(row) {
        if (!row.gateway_payment_url) {
            return '<span class="text-muted">-</span>';
        }

        return `
            <a href="${escape(row.gateway_payment_url)}"
               target="_blank"
               rel="noopener noreferrer"
               class="btn btn-sm btn-primary">
                <i class="bi bi-box-arrow-up-right"></i>
                <span>Open</span>
            </a>
        `;
    }

    function renderStatusBadge(status) {
        const value = upper(status || 'PENDING');

        if (value === 'PAID') {
            return '<span class="badge rounded-pill bg-success-subtle text-success">PAID</span>';
        }

        if (value === 'FAILED') {
            return '<span class="badge rounded-pill bg-danger-subtle text-danger">FAILED</span>';
        }

        if (value === 'PENDING') {
            return '<span class="badge rounded-pill bg-warning-subtle text-warning">PENDING</span>';
        }

        if (value === 'EXPIRED') {
            return '<span class="badge rounded-pill bg-secondary-subtle text-secondary">EXPIRED</span>';
        }

        return `<span class="badge rounded-pill bg-secondary-subtle text-secondary">${escape(value)}</span>`;
    }

    function money(value) {
        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(Number(value || 0));
    }

    function formatSafeDate(value) {
        if (!value) return '-';

        if (typeof formatDateTime === 'function') {
            return formatDateTime(value);
        }

        const date = new Date(String(value).replace(' ', 'T'));

        if (Number.isNaN(date.getTime())) {
            return value;
        }

        return date.toLocaleString('en-PH', {
            timeZone: 'Asia/Manila',
            year: 'numeric',
            month: 'short',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function setButtonBusy(button, busy, busyText = 'Saving...') {
        if (!button) return;

        if (busy) {
            button.dataset.originalHtml = button.innerHTML;
            button.disabled = true;
            button.innerHTML = `
                <span class="spinner-border spinner-border-sm me-1"></span>
                ${escape(busyText)}
            `;
            return;
        }

        button.disabled = false;

        if (button.dataset.originalHtml) {
            button.innerHTML = button.dataset.originalHtml;
            delete button.dataset.originalHtml;
        }
    }

    function nxToast(icon = 'success', title = '') {
        if (ui?.toast) {
            ui.toast(icon, title);
            return;
        }

        if (typeof Swal === 'undefined') {
            console.log(`[${icon}] ${title}`);
            return;
        }

        return Swal.fire({
            toast: true,
            position: 'top-end',
            icon,
            title,
            showConfirmButton: false,
            timer: icon === 'error' ? 5000 : 3000,
            timerProgressBar: true,
            customClass: {
                popup: 'nx-toast-popup'
            },
            didOpen: (toastEl) => {
                toastEl.addEventListener('mouseenter', Swal.stopTimer);
                toastEl.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });
    }

    gatewayPage.start();
});