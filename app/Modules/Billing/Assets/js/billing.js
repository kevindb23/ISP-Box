(function () {
    document.addEventListener('DOMContentLoaded', () => {
        const page = document.getElementById('billingPage');
        if (!page) return;

        const API = {
            overview: '/api/v1/billing/overview',
            generateDueInvoices: '/api/v1/billing/generate-due-invoices',

            invoices: '/api/v1/billing/invoices/list',
            invoiceShow: (id) => `/api/v1/billing/invoices/show/${id}`,
            invoiceCreate: '/api/v1/billing/invoices/create',
            invoiceCancel: (id) => `/api/v1/billing/invoices/cancel/${id}`,
            invoicePrint: (id) => `/billing/invoices/print/${id}`,

            payments: '/api/v1/billing/payments/list',
            paymentShow: (id) => `/api/v1/billing/payments/show/${id}`,
            paymentCreate: '/api/v1/billing/payments/create',
            paymentVoid: (id) => `/api/v1/billing/payments/void/${id}`,
            paymentApprove: (id) => `/api/v1/billing/payments/${id}/approve`,
            paymentReject: (id) => `/api/v1/billing/payments/${id}/reject`,
            paymentProof: (id) => `/api/v1/billing/payments/${id}/proof`,
            paymentReceipt: (id) => `/billing/payments/receipt/${id}`,

            adjustments: '/api/v1/billing/adjustments/list',
            adjustmentShow: (id) => `/api/v1/billing/adjustments/show/${id}`,
            adjustmentCreate: '/api/v1/billing/adjustments/create',
            adjustmentVoid: (id) => `/api/v1/billing/adjustments/void/${id}`,

            collectionsAging: '/api/v1/billing/collections/aging',

            billingRuns: '/api/v1/billing/runs/list',
            billingRunShow: (id) => `/api/v1/billing/runs/show/${id}`,

            settings: '/api/v1/billing/settings',
            settingsSave: '/api/v1/billing/settings/save',
            supportServices: '/api/v1/billing/support/services'
        };

        const state = {
            activeTab: localStorage.getItem('billing.activeTab') || 'overview',
            search: '',
            invoices: [],
            payments: [],
            adjustments: [],
            collections: [],
            billingRuns: [],
            services: [],
            collectionsBucket: '',
            collectionsAsOfDate: '',
            selectedInvoiceForPayment: null,
            selectedPayment: null,
            selectedAdjustment: null,
            selectedBillingRun: null,
            loading: false
        };

        const $ = (id) => document.getElementById(id);

        const els = {
            refresh: $('btnBillingRefresh'),
            generateDueInvoices: $('btnGenerateDueInvoices'),
            generateDueInvoices2: $('btnGenerateDueInvoices2'),
            generateDueInvoices3: $('btnGenerateDueInvoices3'),
            search: $('billingSearch'),

            statTotalBilled: $('statTotalBilled'),
            statCollected: $('statCollected'),
            statBalance: $('statBalance'),
            statOverdue: $('statOverdue'),

            recentInvoicesBody: $('recentInvoicesBody'),
            recentPaymentsBody: $('recentPaymentsBody'),

            invoiceTableBody: $('invoiceTableBody'),
            paymentTableBody: $('paymentTableBody'),
            adjustmentTableBody: $('adjustmentTableBody'),
            collectionsTableBody: $('collectionsTableBody'),
            billingRunTableBody: $('billingRunTableBody'),

            invoiceCountLabel: $('invoiceCountLabel'),
            paymentCountLabel: $('paymentCountLabel'),
            adjustmentCountLabel: $('adjustmentCountLabel'),
            collectionsCountLabel: $('collectionsCountLabel'),
            billingRunCountLabel: $('billingRunCountLabel'),

            invoiceStatusFilter: $('invoiceStatusFilter'),
            paymentStatusFilter: $('paymentStatusFilter'),
            adjustmentStatusFilter: $('adjustmentStatusFilter'),
            adjustmentTypeFilter: $('adjustmentTypeFilter'),

            collectionsBucketFilter: $('collectionsBucketFilter'),
            collectionsAsOfDate: $('collectionsAsOfDate'),
            collectionsAsOfLabel: $('collectionsAsOfLabel'),
            btnRefreshCollections: $('btnRefreshCollections'),

            agingCurrentAmount: $('agingCurrentAmount'),
            agingCurrentCount: $('agingCurrentCount'),
            aging17Amount: $('aging17Amount'),
            aging17Count: $('aging17Count'),
            aging830Amount: $('aging830Amount'),
            aging830Count: $('aging830Count'),
            aging3160Amount: $('aging3160Amount'),
            aging3160Count: $('aging3160Count'),
            aging60Amount: $('aging60Amount'),
            aging60Count: $('aging60Count'),

            billingRunStatusFilter: $('billingRunStatusFilter'),
            billingRunTypeFilter: $('billingRunTypeFilter'),

            createInvoiceForm: $('createInvoiceForm'),
            recordPaymentForm: $('recordPaymentForm'),
            createAdjustmentForm: $('createAdjustmentForm'),
            billingSettingsForm: $('billingSettingsForm'),

            createInvoiceModal: $('createInvoiceModal'),
            recordPaymentModal: $('recordPaymentModal'),
            createAdjustmentModal: $('createAdjustmentModal'),
            invoiceDetailModal: $('invoiceDetailModal'),
            paymentDetailModal: $('paymentDetailModal'),
            adjustmentDetailModal: $('adjustmentDetailModal'),
            billingRunDetailModal: $('billingRunDetailModal'),

            invoiceServiceId: $('invoiceServiceId'),
            invoicePeriodStart: $('invoicePeriodStart'),
            invoicePeriodEnd: $('invoicePeriodEnd'),
            invoiceIssueDate: $('invoiceIssueDate'),
            invoiceDueDate: $('invoiceDueDate'),
            invoiceItemDescription: $('invoiceItemDescription'),
            invoiceItemQty: $('invoiceItemQty'),
            invoiceItemPrice: $('invoiceItemPrice'),
            invoiceDiscount: $('invoiceDiscount'),
            invoiceTax: $('invoiceTax'),
            invoiceNotes: $('invoiceNotes'),

            paymentInvoiceId: $('paymentInvoiceId'),
            paymentAmount: $('paymentAmount'),
            paymentMethod: $('paymentMethod'),
            paymentDate: $('paymentDate'),
            paymentReferenceNo: $('paymentReferenceNo'),
            paymentRemarks: $('paymentRemarks'),
            paymentBalanceHint: $('paymentBalanceHint'),

            adjustmentInvoiceId: $('adjustmentInvoiceId'),
            adjustmentInvoiceHint: $('adjustmentInvoiceHint'),
            adjustmentType: $('adjustmentType'),
            adjustmentAmount: $('adjustmentAmount'),
            adjustmentReason: $('adjustmentReason'),

            invoiceDetailTitle: $('invoiceDetailTitle'),
            invoiceDetailSubtitle: $('invoiceDetailSubtitle'),
            invoiceDetailBody: $('invoiceDetailBody'),
            btnPayFromInvoiceDetail: $('btnPayFromInvoiceDetail'),
            btnPrintInvoiceFromDetail: $('btnPrintInvoiceFromDetail'),
            btnAdjustmentFromInvoiceDetail: $('btnAdjustmentFromInvoiceDetail'),

            paymentDetailTitle: $('paymentDetailTitle'),
            paymentDetailSubtitle: $('paymentDetailSubtitle'),
            paymentDetailBody: $('paymentDetailBody'),
            btnPrintReceiptFromDetail: $('btnPrintReceiptFromDetail'),
            btnVoidPaymentFromDetail: $('btnVoidPaymentFromDetail'),

            adjustmentDetailTitle: $('adjustmentDetailTitle'),
            adjustmentDetailSubtitle: $('adjustmentDetailSubtitle'),
            adjustmentDetailBody: $('adjustmentDetailBody'),
            btnVoidAdjustmentFromDetail: $('btnVoidAdjustmentFromDetail'),

            billingRunDetailTitle: $('billingRunDetailTitle'),
            billingRunDetailSubtitle: $('billingRunDetailSubtitle'),
            billingRunDetailBody: $('billingRunDetailBody'),

            btnSaveBillingSettings: $('btnSaveBillingSettings')
        };

        const getModal = (modalEl) => {
            if (!modalEl || !window.bootstrap?.Modal) return null;

            return bootstrap.Modal.getOrCreateInstance(modalEl);
        };

        const showModal = (modalEl) => {
            const modal = getModal(modalEl);

            if (!modal) {
                console.error('Bootstrap modal is not available or modal element is missing.', modalEl);
                toast('error', 'Modal cannot open. Please check Bootstrap JS is loaded.');
                return;
            }

            modal.show();
        };

        const hideModal = (modalEl) => {
            const modal = getModal(modalEl);

            if (modal) {
                modal.hide();
            }
        };

        const money = (value) => {
            const num = Number(value || 0);

            return new Intl.NumberFormat('en-PH', {
                style: 'currency',
                currency: 'PHP',
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(num);
        };

        const escapeHtml = (value) => {
            return String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        };

        const formatDate = (value) => {
            if (!value) return '—';

            const normalized = String(value).length === 10
                ? `${value}T00:00:00`
                : String(value).replace(' ', 'T');

            const date = new Date(normalized);

            if (Number.isNaN(date.getTime())) return value;

            return date.toLocaleDateString('en-PH', {
                timeZone: 'Asia/Manila',
                year: 'numeric',
                month: 'short',
                day: '2-digit'
            });
        };

        const formatDateTime = (value) => {
            if (!value) return '—';

            const date = new Date(String(value).replace(' ', 'T'));

            if (Number.isNaN(date.getTime())) return value;

            return date.toLocaleString('en-PH', {
                timeZone: 'Asia/Manila',
                year: 'numeric',
                month: 'short',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        };

        const formatDateTimeLocal = (date = new Date()) => {
            const pad = (n) => String(n).padStart(2, '0');

            return [
                date.getFullYear(),
                '-',
                pad(date.getMonth() + 1),
                '-',
                pad(date.getDate()),
                'T',
                pad(date.getHours()),
                ':',
                pad(date.getMinutes())
            ].join('');
        };

        const today = () => {
            const date = new Date();
            const pad = (n) => String(n).padStart(2, '0');

            return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
        };

        const addDays = (dateString, days) => {
            const date = dateString ? new Date(`${dateString}T00:00:00`) : new Date();
            date.setDate(date.getDate() + Number(days || 0));

            const pad = (n) => String(n).padStart(2, '0');

            return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
        };

        const normalizeStatus = (status) => {
            return String(status || 'UNKNOWN').trim().toUpperCase();
        };

        const methodLabel = (method) => {
            const value = String(method || '—').replaceAll('_', ' ').toUpperCase();

            if (value === 'GCASH') return 'GCash';
            if (value === 'MAYA') return 'Maya';
            if (value === 'BANK TRANSFER') return 'Bank Transfer';
            if (value === 'CASH') return 'Cash';
            if (value === 'CHECK') return 'Check';
            if (value === 'XENDIT') return 'Xendit';

            return value;
        };

        const serviceLabel = (row = {}) => {
            const serviceNumber = row.service_number || row.service_id || '';

            if (!serviceNumber) return 'Service # —';

            return `Service # ${serviceNumber}`;
        };

        const accountServiceLabel = (row = {}) => {
            const account = row.account_number || '—';
            const serviceNumber = row.service_number || row.service_id || '—';

            return `Acct: ${account} · Service # ${serviceNumber}`;
        };

        const badge = (status) => {
            const value = normalizeStatus(status);
            const cssValue = value.toLowerCase().replaceAll(' ', '_');

            return `<span class="billing-badge status-${escapeHtml(cssValue)}">${escapeHtml(value)}</span>`;
        };

        const invoiceDisplayStatus = (row = {}) => {
            const status = normalizeStatus(row.status);
            const balance = Number(row.balance_amount || 0);
            const daysOverdue = Number(row.days_overdue || 0);
            const isOverdue = Number(row.is_overdue || 0) === 1 || daysOverdue > 0;

            if (
                balance > 0 &&
                isOverdue &&
                status !== 'PAID' &&
                status !== 'CANCELLED'
            ) {
                return 'OVERDUE';
            }

            return status;
        };

        const overdueText = (row = {}) => {
            const days = Number(row.days_overdue || 0);

            if (days <= 0) return '';

            return `
                <div class="billing-sub-text mt-1 text-danger fw-semibold">
                    ${days} ${days === 1 ? 'day' : 'days'} overdue
                </div>
            `;
        };

        const invoiceStatusCell = (row = {}) => {
            return `
                ${badge(invoiceDisplayStatus(row))}
                ${overdueText(row)}
            `;
        };

        const agingBadge = (bucketKey, label) => {
            const key = String(bucketKey || 'current').toLowerCase();
            const text = label || 'Current';

            return `<span class="billing-badge status-aging-${escapeHtml(key)}">${escapeHtml(text)}</span>`;
        };

        const adjustmentImpactLabel = (type) => {
            const value = String(type || '').toUpperCase();

            if (['CREDIT', 'DISCOUNT', 'REBATE', 'WAIVER'].includes(value)) {
                return 'Reduces invoice balance';
            }

            if (['DEBIT', 'CORRECTION'].includes(value)) {
                return 'Increases invoice balance';
            }

            return 'Invoice adjustment';
        };

        const setButtonBusy = (button, busy, text = null) => {
            if (!button) return;

            if (busy) {
                button.dataset.originalHtml = button.innerHTML;
                button.disabled = true;

                if (text) {
                    button.innerHTML = `
                        <span class="spinner-border spinner-border-sm me-1"></span>
                        ${escapeHtml(text)}
                    `;
                }

                return;
            }

            button.disabled = false;

            if (button.dataset.originalHtml) {
                button.innerHTML = button.dataset.originalHtml;
                delete button.dataset.originalHtml;
            }
        };

        const toast = (icon, title) => {
            const normalizedIcon = ['success', 'error', 'warning', 'info', 'question'].includes(icon)
                ? icon
                : 'info';

            if (window.Swal) {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: normalizedIcon,
                    title: String(title || ''),
                    showConfirmButton: false,
                    timer: normalizedIcon === 'error' ? 4500 : 2800,
                    timerProgressBar: true
                });
                return;
            }

            console.log(`${normalizedIcon}: ${title}`);
        };

        const confirmAction = async (title, text) => {
            if (window.NX?.ui?.confirm) {
                return await NX.ui.confirm(title, text);
            }

            if (window.Swal) {
                const result = await Swal.fire({
                    icon: 'warning',
                    title,
                    text,
                    showCancelButton: true,
                    confirmButtonText: 'Yes, continue',
                    cancelButtonText: 'Cancel'
                });

                return result.isConfirmed;
            }

            return window.confirm(`${title}\n${text || ''}`);
        };

        const promptReason = async (
            title,
            inputLabel,
            inputPlaceholder = '',
            confirmButtonText = 'Continue'
        ) => {
            if (window.Swal) {
                const result = await Swal.fire({
                    icon: 'warning',
                    title,
                    input: 'textarea',
                    inputLabel,
                    inputPlaceholder,
                    inputAttributes: {
                        maxlength: 500,
                        rows: 4,
                        autocomplete: 'off',
                        autocapitalize: 'off',
                        spellcheck: 'true'
                    },
                    showCancelButton: true,
                    confirmButtonText,
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#dc2626',
                    allowOutsideClick: false,
                    allowEscapeKey: true,
                    didOpen: () => {
                        const input = Swal.getInput();

                        if (input) {
                            setTimeout(() => {
                                input.removeAttribute('readonly');
                                input.removeAttribute('disabled');
                                input.focus();
                            }, 150);

                            ['keydown', 'keyup', 'keypress'].forEach((eventName) => {
                                input.addEventListener(eventName, (event) => {
                                    event.stopPropagation();
                                });
                            });
                        }
                    },
                    inputValidator: (value) => {
                        if (!value || !value.trim()) {
                            return 'Reason is required.';
                        }

                        return null;
                    }
                });

                if (!result.isConfirmed) return null;

                return String(result.value || '').trim();
            }

            const reason = window.prompt(`${title}\n${inputLabel}`);

            return reason ? reason.trim() : null;
        };

        const showLoading = (message = 'Loading...') => {
            if (window.NX?.ui?.loading) {
                NX.ui.loading(message);
                return;
            }

            if (window.Swal) {
                Swal.fire({
                    title: message,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => Swal.showLoading()
                });
            }
        };

        const closeLoading = () => {
            if (window.NX?.ui?.closeLoading) {
                NX.ui.closeLoading();
                return;
            }

            if (window.Swal) {
                Swal.close();
            }
        };

        const request = async (url, options = {}) => {
            const method = String(options.method || 'GET').toUpperCase();
            if (method === 'GET') return window.NX.api.get(url);
            let payload = options.body ?? null;
            if (typeof payload === 'string') {
                try { payload = JSON.parse(payload); } catch { /* preserve non-JSON payload */ }
            }
            const result = await window.NX.api.post(url, payload);
            return result.data ?? result;
        };

        const openPrintable = (
            url,
            successMessage,
            errorMessage = 'Unable to open print page. Please allow pop-ups.'
        ) => {
            const opened = window.open(url, '_blank');

            if (!opened) {
                toast('error', errorMessage);
                return false;
            }

            toast('success', successMessage);
            return true;
        };

        const runWithToastError = async (handler, fallbackMessage) => {
            try {
                await handler();
            } catch (error) {
                toast('error', error.message || fallbackMessage);
            }
        };

        const setActiveTab = (tab) => {
            state.activeTab = tab;
            localStorage.setItem('billing.activeTab', tab);

            document.querySelectorAll('[data-billing-tab]').forEach((button) => {
                button.classList.toggle('active', button.dataset.billingTab === tab);
            });

            document.querySelectorAll('[data-billing-panel]').forEach((panel) => {
                panel.classList.toggle('active', panel.dataset.billingPanel === tab);
            });
        };

        const goToTab = async (tab) => {
            setActiveTab(tab);

            if (tab === 'overview') await loadOverview();
            if (tab === 'invoices') await loadInvoices();
            if (tab === 'payments') await loadPayments();
            if (tab === 'adjustments') await loadAdjustments();
            if (tab === 'collections') await loadCollections();
            if (tab === 'runs') await loadBillingRuns();
            if (tab === 'settings') await loadSettings();
        };

        const loadOverview = async () => {
            const data = await request(API.overview);
            const stats = data.stats || {};

            if (els.statTotalBilled) els.statTotalBilled.textContent = money(stats.total_billed);
            if (els.statCollected) els.statCollected.textContent = money(stats.total_collected);
            if (els.statBalance) els.statBalance.textContent = money(stats.total_balance);
            if (els.statOverdue) els.statOverdue.textContent = stats.overdue_invoices ?? 0;

            renderRecentInvoices(data.recent_invoices || []);
            renderRecentPayments(data.recent_payments || []);
        };

        const renderRecentInvoices = (rows) => {
            if (!els.recentInvoicesBody) return;

            if (!rows.length) {
                els.recentInvoicesBody.innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">No invoices yet.</td>
                    </tr>
                `;
                return;
            }

            els.recentInvoicesBody.innerHTML = rows.map((row) => `
                <tr class="billing-clickable" data-action="view-invoice" data-id="${escapeHtml(row.id)}">
                    <td class="ps-4">
                        <div class="billing-main-text">${escapeHtml(row.invoice_no || ('#' + row.id))}</div>
                        <div class="billing-sub-text">Due ${escapeHtml(formatDate(row.due_date))}</div>
                    </td>
                    <td>${escapeHtml(row.subscriber_name || '—')}</td>
                    <td class="billing-money">${money(row.total_amount)}</td>
                    <td class="billing-money">${money(row.balance_amount)}</td>
                    <td>${invoiceStatusCell(row)}</td>
                </tr>
            `).join('');
        };

        const renderRecentPayments = (rows) => {
            if (!els.recentPaymentsBody) return;

            if (!rows.length) {
                els.recentPaymentsBody.innerHTML = `<div class="billing-empty-mini">No payments yet.</div>`;
                return;
            }

            els.recentPaymentsBody.innerHTML = rows.map((row) => `
                <div class="billing-payment-item billing-clickable" data-action="view-payment" data-id="${escapeHtml(row.id)}">
                    <div class="d-flex gap-3">
                        <div class="billing-payment-icon">
                            <i class="bi bi-cash-coin"></i>
                        </div>
                        <div>
                            <div class="billing-main-text">${escapeHtml(row.payment_no || ('#' + row.id))}</div>
                            <div class="billing-sub-text">${escapeHtml(row.subscriber_name || '—')} · ${escapeHtml(methodLabel(row.method))}</div>
                            <div class="billing-sub-text">${escapeHtml(formatDate(row.payment_date))}</div>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="billing-money">${money(row.amount)}</div>
                        <div>${badge(row.payment_status)}</div>
                    </div>
                </div>
            `).join('');
        };

        const loadInvoices = async () => {
            const params = new URLSearchParams();

            if (state.search) params.set('search', state.search);
            if (els.invoiceStatusFilter?.value) params.set('status', els.invoiceStatusFilter.value);

            const data = await request(`${API.invoices}?${params.toString()}`);
            state.invoices = data.items || [];

            if (els.invoiceCountLabel) {
                els.invoiceCountLabel.textContent = `${data.total || 0} ${Number(data.total || 0) === 1 ? 'record' : 'records'}`;
            }

            renderInvoices(state.invoices);
            fillPaymentInvoiceSelect(state.invoices);
            fillAdjustmentInvoiceSelect(state.invoices);
        };

        const renderInvoices = (rows) => {
            if (!els.invoiceTableBody) return;

            if (!rows.length) {
                els.invoiceTableBody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No invoices found.</td>
                    </tr>
                `;
                return;
            }

            els.invoiceTableBody.innerHTML = rows.map((row) => {
                const status = normalizeStatus(row.status);
                const canPay = Number(row.balance_amount || 0) > 0 && status !== 'CANCELLED';
                const canCancel = status !== 'PAID' && status !== 'CANCELLED';

                return `
                    <tr>
                        <td class="ps-4">
                            <div class="billing-main-text">${escapeHtml(row.invoice_no || ('#' + row.id))}</div>
                            <div class="billing-sub-text">Issued ${escapeHtml(formatDate(row.issue_date))}</div>
                        </td>

                        <td>
                            <div class="billing-main-text">${escapeHtml(row.subscriber_name || '—')}</div>
                            <div class="billing-sub-text">Acct: ${escapeHtml(row.account_number || '—')}</div>
                        </td>

                        <td>
                            <div>${escapeHtml(row.plan_name || '—')}</div>
                            <div class="billing-sub-text">${escapeHtml(serviceLabel(row))}</div>
                        </td>

                        <td>
                            ${escapeHtml(formatDate(row.due_date))}
                            ${overdueText(row)}
                        </td>

                        <td class="text-end billing-money">${money(row.total_amount)}</td>
                        <td class="text-end billing-money">${money(row.balance_amount)}</td>

                        <td>${badge(invoiceDisplayStatus(row))}</td>

                        <td class="text-end pe-4">
                            <div class="btn-group btn-group-sm billing-action-group">
                                <button type="button"
                                        class="btn btn-light border"
                                        data-action="view-invoice"
                                        data-id="${row.id}"
                                        title="View Invoice">
                                    <i class="bi bi-eye"></i>
                                </button>

                                <button type="button"
                                        class="btn btn-outline-primary"
                                        data-action="print-invoice"
                                        data-id="${row.id}"
                                        title="Print Invoice">
                                    <i class="bi bi-printer"></i>
                                </button>

                                <button type="button"
                                        class="btn btn-outline-warning"
                                        data-action="adjust-invoice"
                                        data-id="${row.id}"
                                        title="Add Adjustment">
                                    <i class="bi bi-sliders"></i>
                                </button>

                                ${canPay ? `
                                    <button type="button"
                                            class="btn btn-success"
                                            data-action="pay-invoice"
                                            data-id="${row.id}"
                                            title="Record Payment">
                                        <i class="bi bi-cash"></i>
                                    </button>
                                ` : ''}

                                ${canCancel ? `
                                    <button type="button"
                                            class="btn btn-danger"
                                            data-action="cancel-invoice"
                                            data-id="${row.id}"
                                            title="Cancel Invoice">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                ` : ''}
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        };

        const loadPayments = async () => {
            const params = new URLSearchParams();

            if (state.search) params.set('search', state.search);
            if (els.paymentStatusFilter?.value) params.set('payment_status', els.paymentStatusFilter.value);

            const data = await request(`${API.payments}?${params.toString()}`);
            state.payments = data.items || [];

            if (els.paymentCountLabel) {
                els.paymentCountLabel.textContent = `${data.total || 0} ${Number(data.total || 0) === 1 ? 'record' : 'records'}`;
            }

            renderPayments(state.payments);
        };

        const renderPayments = (rows) => {
            if (!els.paymentTableBody) return;

            if (!rows.length) {
                els.paymentTableBody.innerHTML = `
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">No payments found.</td>
                    </tr>
                `;
                return;
            }

            els.paymentTableBody.innerHTML = rows.map((row) => {
                const paymentStatus = normalizeStatus(row.payment_status);
                const isPosted = paymentStatus === 'POSTED';
                const isPending = paymentStatus === 'PENDING';

                return `
                    <tr>
                        <td class="ps-4">
                            <div class="billing-main-text">${escapeHtml(row.payment_no || ('#' + row.id))}</div>
                        </td>
                        <td>${escapeHtml(row.invoice_no || '—')}</td>
                        <td>
                            <div class="billing-main-text">${escapeHtml(row.subscriber_name || '—')}</div>
                            <div class="billing-sub-text">${escapeHtml(accountServiceLabel(row))}</div>
                        </td>
                        <td>${escapeHtml(methodLabel(row.method))}</td>
                        <td>${escapeHtml(row.reference_no || '—')}</td>
                        <td>${escapeHtml(formatDate(row.payment_date))}</td>
                        <td class="text-end billing-money">${money(row.amount)}</td>
                        <td>${badge(row.payment_status)}</td>
                        <td class="text-end pe-4">
                            <div class="btn-group btn-group-sm billing-action-group">
                                <button type="button"
                                        class="btn btn-light border"
                                        data-action="view-payment"
                                        data-id="${row.id}"
                                        title="View Payment">
                                    <i class="bi bi-eye"></i>
                                </button>

                                <button type="button"
                                        class="btn btn-outline-primary"
                                        data-action="print-receipt"
                                        data-id="${row.id}"
                                        title="Print Receipt">
                                    <i class="bi bi-printer"></i>
                                </button>

                                ${isPosted ? `
                                    <button type="button"
                                            class="btn btn-outline-danger"
                                            data-action="void-payment"
                                            data-id="${row.id}"
                                            title="Void Payment">
                                        <i class="bi bi-x-circle"></i>
                                    </button>
                                ` : ''}
                                ${isPending ? `
                                    <a class="btn btn-outline-secondary" href="${API.paymentProof(row.id)}" target="_blank" title="View Payment Proof"><i class="bi bi-image"></i></a>
                                    <button type="button" class="btn btn-outline-success" data-action="approve-payment" data-id="${row.id}" title="Approve Payment"><i class="bi bi-check-lg"></i></button>
                                    <button type="button" class="btn btn-outline-danger" data-action="reject-payment" data-id="${row.id}" title="Reject Payment"><i class="bi bi-x-lg"></i></button>
                                ` : ''}
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        };

        const loadAdjustments = async () => {
            const params = new URLSearchParams();

            if (state.search) params.set('search', state.search);
            if (els.adjustmentStatusFilter?.value) params.set('status', els.adjustmentStatusFilter.value);
            if (els.adjustmentTypeFilter?.value) params.set('adjustment_type', els.adjustmentTypeFilter.value);

            const data = await request(`${API.adjustments}?${params.toString()}`);
            state.adjustments = data.items || [];

            if (els.adjustmentCountLabel) {
                els.adjustmentCountLabel.textContent = `${data.total || 0} ${Number(data.total || 0) === 1 ? 'record' : 'records'}`;
            }

            renderAdjustments(state.adjustments);
        };

        const renderAdjustments = (rows) => {
            if (!els.adjustmentTableBody) return;

            if (!rows.length) {
                els.adjustmentTableBody.innerHTML = `
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">No adjustments found.</td>
                    </tr>
                `;
                return;
            }

            els.adjustmentTableBody.innerHTML = rows.map((row) => {
                const status = normalizeStatus(row.status);
                const type = normalizeStatus(row.adjustment_type);
                const isPosted = status === 'POSTED';

                return `
                    <tr>
                        <td class="ps-4">
                            <div class="billing-main-text">${escapeHtml(row.adjustment_no || ('#' + row.id))}</div>
                            <div class="billing-sub-text">${escapeHtml(adjustmentImpactLabel(type))}</div>
                        </td>
                        <td>${escapeHtml(row.invoice_no || '—')}</td>
                        <td>
                            <div class="billing-main-text">${escapeHtml(row.subscriber_name || '—')}</div>
                            <div class="billing-sub-text">${escapeHtml(accountServiceLabel(row))}</div>
                        </td>
                        <td>${badge(type)}</td>
                        <td>
                            <div class="billing-sub-text">${escapeHtml(row.reason || '—')}</div>
                        </td>
                        <td>${escapeHtml(formatDate(row.created_at))}</td>
                        <td class="text-end billing-money">${money(row.amount)}</td>
                        <td>${badge(status)}</td>
                        <td class="text-end pe-4">
                            <div class="btn-group btn-group-sm billing-action-group">
                                <button type="button"
                                        class="btn btn-light border"
                                        data-action="view-adjustment"
                                        data-id="${row.id}"
                                        title="View Adjustment">
                                    <i class="bi bi-eye"></i>
                                </button>

                                ${isPosted ? `
                                    <button type="button"
                                            class="btn btn-outline-danger"
                                            data-action="void-adjustment"
                                            data-id="${row.id}"
                                            title="Void Adjustment">
                                        <i class="bi bi-x-circle"></i>
                                    </button>
                                ` : ''}
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        };

        const loadCollections = async () => {
            const params = new URLSearchParams();

            if (state.search) params.set('search', state.search);
            if (state.collectionsBucket) params.set('bucket', state.collectionsBucket);
            if (state.collectionsAsOfDate) params.set('as_of_date', state.collectionsAsOfDate);

            const data = await request(`${API.collectionsAging}?${params.toString()}`);

            state.collections = data.items || [];

            if (els.collectionsCountLabel) {
                els.collectionsCountLabel.textContent = `${data.total || 0} ${Number(data.total || 0) === 1 ? 'record' : 'records'}`;
            }

            if (els.collectionsAsOfLabel) {
                els.collectionsAsOfLabel.textContent = formatDate(data.as_of_date || state.collectionsAsOfDate || today());
            }

            if (els.collectionsAsOfDate && !els.collectionsAsOfDate.value && data.as_of_date) {
                els.collectionsAsOfDate.value = data.as_of_date;
                state.collectionsAsOfDate = data.as_of_date;
            }

            renderCollectionsSummary(data.summary || {});
            renderCollections(state.collections);
        };

        const setAgingCardValue = (amountEl, countEl, bucket = {}) => {
            if (amountEl) amountEl.textContent = money(bucket.amount || 0);

            if (countEl) {
                const count = Number(bucket.count || 0);
                countEl.textContent = `${count} ${count === 1 ? 'invoice' : 'invoices'}`;
            }
        };

        const renderCollectionsSummary = (summary = {}) => {
            setAgingCardValue(els.agingCurrentAmount, els.agingCurrentCount, summary.current);
            setAgingCardValue(els.aging17Amount, els.aging17Count, summary.overdue_1_7);
            setAgingCardValue(els.aging830Amount, els.aging830Count, summary.overdue_8_30);
            setAgingCardValue(els.aging3160Amount, els.aging3160Count, summary.overdue_31_60);
            setAgingCardValue(els.aging60Amount, els.aging60Count, summary.overdue_60_plus);

            document.querySelectorAll('[data-collections-bucket]').forEach((card) => {
                const isActive = card.dataset.collectionsBucket === state.collectionsBucket;
                card.classList.toggle('active', isActive);
            });
        };

        const renderCollections = (rows) => {
            if (!els.collectionsTableBody) return;

            if (!rows.length) {
                els.collectionsTableBody.innerHTML = `
                    <tr>
                        <td colspan="10" class="text-center text-muted py-4">No collection aging records found.</td>
                    </tr>
                `;
                return;
            }

            els.collectionsTableBody.innerHTML = rows.map((row) => {
                const daysOverdue = Number(row.days_overdue || 0);
                const agingText = daysOverdue > 0
                    ? `${daysOverdue} ${daysOverdue === 1 ? 'day' : 'days'} overdue`
                    : 'Not overdue';

                return `
                    <tr>
                        <td class="ps-4">
                            <div class="billing-main-text">${escapeHtml(row.invoice_no || ('#' + row.id))}</div>
                            <div class="billing-sub-text">Issued ${escapeHtml(formatDate(row.issue_date))}</div>
                        </td>

                        <td>
                            <div class="billing-main-text">${escapeHtml(row.subscriber_name || '—')}</div>
                            <div class="billing-sub-text">Acct: ${escapeHtml(row.account_number || '—')}</div>
                        </td>

                        <td>
                            <div>${escapeHtml(row.plan_name || '—')}</div>
                            <div class="billing-sub-text">${escapeHtml(serviceLabel(row))}</div>
                        </td>

                        <td>${escapeHtml(formatDate(row.due_date))}</td>

                        <td>
                            ${agingBadge(row.aging_bucket_key, row.aging_bucket_label)}
                            <div class="billing-sub-text mt-1 ${daysOverdue > 0 ? 'text-danger fw-semibold' : ''}">
                                ${escapeHtml(agingText)}
                            </div>
                        </td>

                        <td class="text-end billing-money">${money(row.total_amount)}</td>
                        <td class="text-end billing-money">${money(row.paid_amount)}</td>
                        <td class="text-end billing-money">${money(row.balance_amount)}</td>

                        <td>${badge(invoiceDisplayStatus(row))}</td>

                        <td class="text-end pe-4">
                            <div class="btn-group btn-group-sm billing-action-group">
                                <button type="button"
                                        class="btn btn-light border"
                                        data-action="view-invoice"
                                        data-id="${row.id}"
                                        title="View Invoice">
                                    <i class="bi bi-eye"></i>
                                </button>

                                <button type="button"
                                        class="btn btn-success"
                                        data-action="pay-invoice"
                                        data-id="${row.id}"
                                        title="Record Payment">
                                    <i class="bi bi-cash"></i>
                                </button>

                                <button type="button"
                                        class="btn btn-outline-primary"
                                        data-action="print-invoice"
                                        data-id="${row.id}"
                                        title="Print Invoice">
                                    <i class="bi bi-printer"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        };

        const loadBillingRuns = async () => {
            const params = new URLSearchParams();

            if (els.billingRunStatusFilter?.value) {
                params.set('status', els.billingRunStatusFilter.value);
            }

            if (els.billingRunTypeFilter?.value) {
                params.set('run_type', els.billingRunTypeFilter.value);
            }

            const data = await request(`${API.billingRuns}?${params.toString()}`);
            state.billingRuns = data.items || [];

            if (els.billingRunCountLabel) {
                els.billingRunCountLabel.textContent = `${data.total || 0} ${Number(data.total || 0) === 1 ? 'record' : 'records'}`;
            }

            renderBillingRuns(state.billingRuns);
        };

        const renderBillingRuns = (rows) => {
            if (!els.billingRunTableBody) return;

            if (!rows.length) {
                els.billingRunTableBody.innerHTML = `
                    <tr>
                        <td colspan="10" class="text-center text-muted py-4">No billing runs found.</td>
                    </tr>
                `;
                return;
            }

            els.billingRunTableBody.innerHTML = rows.map((row) => `
                <tr>
                    <td class="ps-4">
                        <div class="billing-main-text">${escapeHtml(row.run_no || ('#' + row.id))}</div>
                        <div class="billing-sub-text">${escapeHtml(row.message || 'Billing run')}</div>
                    </td>
                    <td>${escapeHtml(row.run_type || '—')}</td>
                    <td>${escapeHtml(formatDate(row.as_of_date))}</td>
                    <td class="text-end">${escapeHtml(row.checked_count ?? 0)}</td>
                    <td class="text-end text-success fw-semibold">${escapeHtml(row.created_count ?? 0)}</td>
                    <td class="text-end text-warning fw-semibold">${escapeHtml(row.skipped_count ?? 0)}</td>
                    <td class="text-end text-danger fw-semibold">${escapeHtml(row.failed_count ?? 0)}</td>
                    <td>${badge(row.status)}</td>
                    <td>${escapeHtml(formatDateTime(row.started_at))}</td>
                    <td class="text-end pe-4">
                        <button type="button"
                                class="btn btn-sm btn-light border"
                                data-action="view-billing-run"
                                data-id="${row.id}"
                                title="View Billing Run">
                            <i class="bi bi-eye"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
        };

        const viewBillingRun = async (id) => {
            if (!els.billingRunDetailBody) return;

            els.billingRunDetailBody.innerHTML = `<div class="text-center text-muted py-5">Loading billing run...</div>`;
            showModal(els.billingRunDetailModal);

            try {
                const data = await request(API.billingRunShow(id));
                const run = data.run || {};
                const items = data.items || [];

                state.selectedBillingRun = run;

                if (els.billingRunDetailTitle) {
                    els.billingRunDetailTitle.textContent = run.run_no || `Billing Run #${run.id}`;
                }

                if (els.billingRunDetailSubtitle) {
                    els.billingRunDetailSubtitle.textContent = `${run.run_type || 'MANUAL'} · ${formatDate(run.as_of_date)} · ${run.status || 'UNKNOWN'}`;
                }

                els.billingRunDetailBody.innerHTML = `
                    <div class="billing-detail-grid mb-3">
                        <div class="billing-detail-box">
                            <div class="billing-detail-label mb-2">Run Summary</div>

                            <h5 class="fw-bold mb-2">${escapeHtml(run.run_no || ('#' + run.id))}</h5>

                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Run Type</span>
                                <strong>${escapeHtml(run.run_type || '—')}</strong>
                            </div>

                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">As-of Date</span>
                                <strong>${escapeHtml(formatDate(run.as_of_date))}</strong>
                            </div>

                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Status</span>
                                <span>${badge(run.status)}</span>
                            </div>

                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Triggered By</span>
                                <strong>${escapeHtml(run.triggered_by_name || run.triggered_by_username || 'System / Unknown')}</strong>
                            </div>
                        </div>

                        <div class="billing-detail-box">
                            <div class="billing-detail-label mb-2">Counts</div>

                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="billing-mini-stat">
                                        <div class="billing-sub-text">Checked</div>
                                        <div class="billing-main-text">${escapeHtml(run.checked_count ?? 0)}</div>
                                    </div>
                                </div>

                                <div class="col-6">
                                    <div class="billing-mini-stat">
                                        <div class="billing-sub-text">Created</div>
                                        <div class="billing-main-text text-success">${escapeHtml(run.created_count ?? 0)}</div>
                                    </div>
                                </div>

                                <div class="col-6">
                                    <div class="billing-mini-stat">
                                        <div class="billing-sub-text">Skipped</div>
                                        <div class="billing-main-text text-warning">${escapeHtml(run.skipped_count ?? 0)}</div>
                                    </div>
                                </div>

                                <div class="col-6">
                                    <div class="billing-mini-stat">
                                        <div class="billing-sub-text">Failed</div>
                                        <div class="billing-main-text text-danger">${escapeHtml(run.failed_count ?? 0)}</div>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Started</span>
                                <strong>${escapeHtml(formatDateTime(run.started_at))}</strong>
                            </div>

                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Completed</span>
                                <strong>${escapeHtml(formatDateTime(run.completed_at))}</strong>
                            </div>
                        </div>
                    </div>

                    <div class="billing-detail-box mb-3">
                        <div class="billing-detail-label mb-2">Run Message</div>
                        <div class="text-muted">${escapeHtml(run.message || 'No message.')}</div>
                    </div>

                    <div class="billing-detail-box">
                        <div class="billing-detail-label mb-2">Billing Run Items</div>

                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>Subscriber</th>
                                    <th>Plan</th>
                                    <th>Service</th>
                                    <th>Invoice</th>
                                    <th>Period</th>
                                    <th>Next Due</th>
                                    <th>Status</th>
                                    <th>Reason / Error</th>
                                </tr>
                                </thead>
                                <tbody>
                                ${items.length ? items.map((item) => `
                                    <tr>
                                        <td>
                                            <div class="billing-main-text">${escapeHtml(item.subscriber_name || '—')}</div>
                                            <div class="billing-sub-text">${escapeHtml(item.account_number || '—')}</div>
                                        </td>
                                        <td>${escapeHtml(item.plan_name || '—')}</td>
                                        <td>${escapeHtml(item.service_id || '—')}</td>
                                        <td>${escapeHtml(item.invoice_id || '—')}</td>
                                        <td>
                                            ${escapeHtml(formatDate(item.billing_period_start))}
                                            -
                                            ${escapeHtml(formatDate(item.billing_period_end))}
                                        </td>
                                        <td>${escapeHtml(formatDate(item.next_due_date))}</td>
                                        <td>${badge(item.result_status)}</td>
                                        <td>
                                            <div class="${item.error_message ? 'text-danger' : 'text-muted'}">
                                                ${escapeHtml(item.error_message || item.reason || '—')}
                                            </div>
                                        </td>
                                    </tr>
                                `).join('') : `
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            No item details for this run.
                                        </td>
                                    </tr>
                                `}
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;
            } catch (error) {
                toast('error', error.message || 'Unable to load billing run details.');

                els.billingRunDetailBody.innerHTML = `
                    <div class="alert alert-danger mb-0">${escapeHtml(error.message || 'Unable to load billing run details.')}</div>
                `;
            }
        };

        const loadServices = async () => {
            const rows = await request(API.supportServices);
            state.services = Array.isArray(rows) ? rows : [];
            fillServiceSelect();
        };

        const fillServiceSelect = () => {
            if (!els.invoiceServiceId) return;

            els.invoiceServiceId.innerHTML = `
                <option value="">Select service...</option>
                ${state.services.map((service) => `
                    <option value="${service.id}" data-price="${service.price || 0}">
                        ${escapeHtml(service.subscriber_name || 'Unknown')} — ${escapeHtml(service.plan_name || 'No Plan')} — ${money(service.price || 0)}
                    </option>
                `).join('')}
            `;
        };

        const fillPaymentInvoiceSelect = (rows) => {
            if (!els.paymentInvoiceId) return;

            const payable = rows.filter((row) => {
                return Number(row.balance_amount || 0) > 0 && normalizeStatus(row.status) !== 'CANCELLED';
            });

            els.paymentInvoiceId.innerHTML = `
                <option value="">Select unpaid invoice...</option>
                ${payable.map((invoice) => `
                    <option value="${invoice.id}" data-balance="${invoice.balance_amount || 0}">
                        ${escapeHtml(invoice.invoice_no || ('#' + invoice.id))} — ${escapeHtml(invoice.subscriber_name || '—')} — Balance ${money(invoice.balance_amount)}
                    </option>
                `).join('')}
            `;
        };

        const fillAdjustmentInvoiceSelect = (rows) => {
            if (!els.adjustmentInvoiceId) return;

            const adjustable = rows.filter((row) => {
                return normalizeStatus(row.status) !== 'CANCELLED';
            });

            els.adjustmentInvoiceId.innerHTML = `
                <option value="">Select invoice...</option>
                ${adjustable.map((invoice) => `
                    <option value="${invoice.id}"
                            data-balance="${invoice.balance_amount || 0}"
                            data-total="${invoice.total_amount || 0}"
                            data-subscriber="${escapeHtml(invoice.subscriber_name || '—')}"
                            data-invoice-no="${escapeHtml(invoice.invoice_no || ('#' + invoice.id))}">
                        ${escapeHtml(invoice.invoice_no || ('#' + invoice.id))} — ${escapeHtml(invoice.subscriber_name || '—')} — Balance ${money(invoice.balance_amount)}
                    </option>
                `).join('')}
            `;
        };

        const updateAdjustmentInvoiceHint = () => {
            if (!els.adjustmentInvoiceId || !els.adjustmentInvoiceHint) return;

            const selected = els.adjustmentInvoiceId.selectedOptions?.[0];
            const balance = Number(selected?.dataset?.balance || 0);
            const total = Number(selected?.dataset?.total || 0);

            if (selected?.value) {
                els.adjustmentInvoiceHint.textContent = `Invoice total: ${money(total)} · Current balance: ${money(balance)}`;
                return;
            }

            els.adjustmentInvoiceHint.textContent = 'Select an invoice to apply adjustment.';
        };

        const loadSettings = async () => {
            const data = await request(API.settings);
            const map = data.map || {};

            if ($('settingInvoicePrefix')) $('settingInvoicePrefix').value = map.invoice_prefix || 'INV';
            if ($('settingPaymentPrefix')) $('settingPaymentPrefix').value = map.payment_prefix || 'PAY';
            if ($('settingAdjustmentPrefix')) $('settingAdjustmentPrefix').value = map.adjustment_prefix || 'ADJ';
            if ($('settingCurrency')) $('settingCurrency').value = map.currency || 'PHP';
            if ($('settingDefaultDueDays')) $('settingDefaultDueDays').value = map.default_due_days || 10;
            if ($('settingGracePeriodDays')) $('settingGracePeriodDays').value = map.grace_period_days || 3;
            if ($('settingTaxRate')) $('settingTaxRate').value = map.tax_rate || 0;
            if ($('settingTaxEnabled')) $('settingTaxEnabled').checked = String(map.tax_enabled || '0') === '1';
            if ($('settingAutoSuspend')) $('settingAutoSuspend').checked = String(map.auto_suspend_enabled || '0') === '1';
        };

        const openCreateInvoice = async () => {
            try {
                await loadServices();

                els.createInvoiceForm?.reset();

                const start = today();

                if (els.invoiceIssueDate) els.invoiceIssueDate.value = start;
                if (els.invoicePeriodStart) els.invoicePeriodStart.value = start;
                if (els.invoicePeriodEnd) els.invoicePeriodEnd.value = addDays(start, 29);
                if (els.invoiceDueDate) els.invoiceDueDate.value = addDays(start, 10);
                if (els.invoiceItemQty) els.invoiceItemQty.value = 1;
                if (els.invoiceDiscount) els.invoiceDiscount.value = 0;
                if (els.invoiceTax) els.invoiceTax.value = 0;

                showModal(els.createInvoiceModal);
            } catch (error) {
                toast('error', error.message || 'Unable to open invoice form.');
            }
        };

        const openRecordPayment = async (invoiceId = null) => {
            try {
                if (!state.invoices.length) {
                    await loadInvoices();
                }

                els.recordPaymentForm?.reset();

                if (els.paymentDate) {
                    els.paymentDate.value = formatDateTimeLocal();
                }

                if (els.paymentBalanceHint) {
                    els.paymentBalanceHint.textContent = 'Select invoice to see balance.';
                }

                if (invoiceId && els.paymentInvoiceId) {
                    els.paymentInvoiceId.value = String(invoiceId);
                    updatePaymentBalanceHint();
                }

                showModal(els.recordPaymentModal);
            } catch (error) {
                toast('error', error.message || 'Unable to open payment form.');
            }
        };

        const openCreateAdjustment = async (invoiceId = null) => {
            try {
                if (!state.invoices.length) {
                    await loadInvoices();
                }

                els.createAdjustmentForm?.reset();

                if (els.adjustmentType) {
                    els.adjustmentType.value = 'CREDIT';
                }

                if (invoiceId && els.adjustmentInvoiceId) {
                    els.adjustmentInvoiceId.value = String(invoiceId);
                }

                updateAdjustmentInvoiceHint();

                showModal(els.createAdjustmentModal);
            } catch (error) {
                toast('error', error.message || 'Unable to open adjustment form.');
            }
        };

        const updatePaymentBalanceHint = () => {
            if (!els.paymentInvoiceId || !els.paymentBalanceHint || !els.paymentAmount) return;

            const selected = els.paymentInvoiceId.selectedOptions?.[0];
            const balance = Number(selected?.dataset?.balance || 0);

            if (balance > 0) {
                els.paymentBalanceHint.textContent = `Current invoice balance: ${money(balance)}`;
                els.paymentAmount.max = balance;
                els.paymentAmount.value = balance;
            } else {
                els.paymentBalanceHint.textContent = 'Select invoice to see balance.';
                els.paymentAmount.removeAttribute('max');
                els.paymentAmount.value = '';
            }
        };

        const generateDueInvoices = async () => {
            const confirmed = await confirmAction(
                'Generate due invoices?',
                'This will create invoices for active postpaid services with next due date today or earlier.'
            );

            if (!confirmed) return;

            try {
                showLoading('Generating due invoices...');

                const result = await request(API.generateDueInvoices, {
                    method: 'POST',
                    body: JSON.stringify({})
                });

                closeLoading();

                const checked = Number(result.checked || 0);
                const created = Number(result.created || 0);
                const skipped = Number(result.skipped || 0);
                const failed = Number(result.failed || 0);

                toast(
                    failed > 0 ? 'warning' : 'success',
                    `Billing run completed. Checked: ${checked}, Created: ${created}, Skipped: ${skipped}, Failed: ${failed}.`
                );

                await reloadAll();
                setActiveTab('runs');

                if (result.billing_run_id) {
                    await viewBillingRun(result.billing_run_id);
                }
            } catch (error) {
                closeLoading();
                toast('error', error.message || 'Failed to generate due invoices.');
            }
        };

        const createInvoice = async (event) => {
            event.preventDefault();

            const serviceId = Number(els.invoiceServiceId?.value || 0);
            if (!serviceId) {
                toast('warning', 'Please select a subscriber service.');
                return;
            }

            const submitButton = event.submitter;
            setButtonBusy(submitButton, true, 'Creating...');

            const customDescription = String(els.invoiceItemDescription?.value || '').trim();
            const customPrice = Number(els.invoiceItemPrice?.value || 0);
            const customQty = Number(els.invoiceItemQty?.value || 1);

            const payload = {
                service_id: serviceId,
                billing_period_start: els.invoicePeriodStart?.value || null,
                billing_period_end: els.invoicePeriodEnd?.value || null,
                issue_date: els.invoiceIssueDate?.value || null,
                due_date: els.invoiceDueDate?.value || null,
                discount_amount: Number(els.invoiceDiscount?.value || 0),
                tax_amount: Number(els.invoiceTax?.value || 0),
                notes: els.invoiceNotes?.value || null
            };

            if (customDescription || customPrice > 0) {
                payload.items = [{
                    item_type: 'PLAN',
                    description: customDescription || 'Monthly internet service',
                    quantity: customQty,
                    unit_price: customPrice
                }];
            }

            try {
                await request(API.invoiceCreate, {
                    method: 'POST',
                    body: JSON.stringify(payload)
                });

                toast('success', 'Invoice created.');
                hideModal(els.createInvoiceModal);

                await reloadAll();
                setActiveTab('invoices');
            } catch (error) {
                toast('error', error.message || 'Failed to create invoice.');
            } finally {
                setButtonBusy(submitButton, false);
            }
        };

        const recordPayment = async (event) => {
            event.preventDefault();

            const invoiceId = Number(els.paymentInvoiceId?.value || 0);
            const amount = Number(els.paymentAmount?.value || 0);

            if (!invoiceId) {
                toast('warning', 'Please select an invoice.');
                return;
            }

            if (amount <= 0) {
                toast('warning', 'Please enter a valid payment amount.');
                return;
            }

            const submitButton = event.submitter;
            setButtonBusy(submitButton, true, 'Posting...');

            const paymentDate = els.paymentDate?.value
                ? els.paymentDate.value.replace('T', ' ') + ':00'
                : null;

            const payload = {
                invoice_id: invoiceId,
                amount,
                method: els.paymentMethod?.value || 'CASH',
                payment_date: paymentDate,
                reference_no: els.paymentReferenceNo?.value || null,
                remarks: els.paymentRemarks?.value || null
            };

            try {
                await request(API.paymentCreate, {
                    method: 'POST',
                    body: JSON.stringify(payload)
                });

                toast('success', 'Payment recorded.');
                hideModal(els.recordPaymentModal);

                await reloadAll();
                setActiveTab('payments');
            } catch (error) {
                toast('error', error.message || 'Failed to record payment.');
            } finally {
                setButtonBusy(submitButton, false);
            }
        };

        const createAdjustment = async (event) => {
            event.preventDefault();

            const invoiceId = Number(els.adjustmentInvoiceId?.value || 0);
            const amount = Number(els.adjustmentAmount?.value || 0);
            const type = normalizeStatus(els.adjustmentType?.value || 'CREDIT');
            const reason = String(els.adjustmentReason?.value || '').trim();

            if (!invoiceId) {
                toast('warning', 'Please select an invoice.');
                return;
            }

            if (amount <= 0) {
                toast('warning', 'Please enter a valid adjustment amount.');
                return;
            }

            if (!reason) {
                toast('warning', 'Please enter an adjustment reason.');
                return;
            }

            const submitButton = event.submitter;
            setButtonBusy(submitButton, true, 'Posting...');

            try {
                const result = await request(API.adjustmentCreate, {
                    method: 'POST',
                    body: JSON.stringify({
                        invoice_id: invoiceId,
                        adjustment_type: type,
                        amount,
                        reason
                    })
                });

                toast('success', 'Billing adjustment posted.');
                hideModal(els.createAdjustmentModal);

                await reloadAll();
                setActiveTab('adjustments');

                if (result.adjustment?.id) {
                    await viewAdjustment(result.adjustment.id);
                }
            } catch (error) {
                toast('error', error.message || 'Failed to create billing adjustment.');
            } finally {
                setButtonBusy(submitButton, false);
            }
        };

        const viewInvoice = async (id) => {
            if (!els.invoiceDetailBody) return;

            els.invoiceDetailBody.innerHTML = `<div class="text-center text-muted py-5">Loading invoice...</div>`;
            showModal(els.invoiceDetailModal);

            try {
                const data = await request(API.invoiceShow(id));
                const invoice = data.invoice || {};
                const items = data.items || [];
                const payments = data.payments || [];
                const adjustments = data.adjustments || data.invoice_adjustments || [];

                state.selectedInvoiceForPayment = invoice;

                if (els.invoiceDetailTitle) {
                    els.invoiceDetailTitle.textContent = invoice.invoice_no || `Invoice #${invoice.id}`;
                }

                if (els.invoiceDetailSubtitle) {
                    els.invoiceDetailSubtitle.textContent = `${invoice.subscriber_name || 'Subscriber'} · ${invoice.plan_name || 'Plan'}`;
                }

                els.invoiceDetailBody.innerHTML = `
                    <div class="billing-detail-grid mb-3">
                        <div class="billing-detail-box">
                            <div class="billing-detail-label mb-2">Subscriber</div>
                            <h5 class="fw-bold mb-1">${escapeHtml(invoice.subscriber_name || '—')}</h5>
                            <div class="text-muted">${escapeHtml(invoice.address || 'No address')}</div>
                            <div class="text-muted">${escapeHtml(invoice.contact_number || 'No contact number')}</div>
                            <div class="text-muted">${escapeHtml(invoice.email || '')}</div>
                        </div>

                        <div class="billing-detail-box">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="billing-detail-label">Status</span>
                                <span class="text-end">
                                    ${badge(invoiceDisplayStatus(invoice))}
                                    ${overdueText(invoice)}
                                </span>
                            </div>

                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Account No.</span>
                                <strong>${escapeHtml(invoice.account_number || '—')}</strong>
                            </div>

                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Service No.</span>
                                <strong>${escapeHtml(invoice.service_number || invoice.service_id || '—')}</strong>
                            </div>

                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Issue Date</span>
                                <strong>${escapeHtml(formatDate(invoice.issue_date))}</strong>
                            </div>

                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Due Date</span>
                                <strong>${escapeHtml(formatDate(invoice.due_date))}</strong>
                            </div>

                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Billing Period</span>
                                <strong>${escapeHtml(formatDate(invoice.billing_period_start))} - ${escapeHtml(formatDate(invoice.billing_period_end))}</strong>
                            </div>
                        </div>
                    </div>

                    <div class="billing-detail-box mb-3">
                        <div class="billing-detail-label mb-2">Invoice Items</div>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>Description</th>
                                    <th class="text-end">Qty</th>
                                    <th class="text-end">Unit Price</th>
                                    <th class="text-end">Total</th>
                                </tr>
                                </thead>
                                <tbody>
                                ${items.length ? items.map((item) => `
                                    <tr>
                                        <td>${escapeHtml(item.description)}</td>
                                        <td class="text-end">${escapeHtml(item.quantity)}</td>
                                        <td class="text-end">${money(item.unit_price)}</td>
                                        <td class="text-end billing-money">${money(item.line_total)}</td>
                                    </tr>
                                `).join('') : `
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">No items.</td>
                                    </tr>
                                `}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-lg-4">
                            <div class="billing-detail-box h-100">
                                <div class="billing-detail-label mb-2">Payment History</div>
                                ${payments.length ? payments.map((payment) => `
                                    <div class="d-flex justify-content-between border-bottom py-2">
                                        <div>
                                            <div class="fw-semibold">${escapeHtml(payment.payment_no || ('#' + payment.id))}</div>
                                            <div class="text-muted small">${escapeHtml(methodLabel(payment.method))} · ${escapeHtml(formatDate(payment.payment_date))}</div>
                                        </div>
                                        <div class="text-end">
                                            <div class="billing-money">${money(payment.allocated_amount || payment.amount)}</div>
                                            ${badge(payment.payment_status)}
                                        </div>
                                    </div>
                                `).join('') : `<div class="text-muted">No payments recorded.</div>`}
                            </div>
                        </div>

                        <div class="col-12 col-lg-4">
                            <div class="billing-detail-box h-100">
                                <div class="billing-detail-label mb-2">Adjustments</div>
                                ${adjustments.length ? adjustments.map((adjustment) => `
                                    <div class="d-flex justify-content-between border-bottom py-2">
                                        <div>
                                            <div class="fw-semibold">${escapeHtml(adjustment.adjustment_no || ('#' + adjustment.id))}</div>
                                            <div class="text-muted small">${escapeHtml(adjustment.adjustment_type || '—')} · ${escapeHtml(adjustment.reason || '—')}</div>
                                        </div>
                                        <div class="text-end">
                                            <div class="billing-money">${money(adjustment.amount)}</div>
                                            ${badge(adjustment.status)}
                                        </div>
                                    </div>
                                `).join('') : `<div class="text-muted">No adjustments recorded.</div>`}
                            </div>
                        </div>

                        <div class="col-12 col-lg-4">
                            <div class="billing-detail-box h-100">
                                <div class="billing-detail-label mb-3">Summary</div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Subtotal</span>
                                    <strong>${money(invoice.subtotal)}</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Discount / Credits</span>
                                    <strong>${money(invoice.discount_amount)}</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Tax</span>
                                    <strong>${money(invoice.tax_amount)}</strong>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Total</span>
                                    <strong>${money(invoice.total_amount)}</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Paid</span>
                                    <strong>${money(invoice.paid_amount)}</strong>
                                </div>
                                <div class="d-flex justify-content-between fs-5">
                                    <span class="fw-bold">Balance</span>
                                    <strong>${money(invoice.balance_amount)}</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                if (els.btnPayFromInvoiceDetail) {
                    els.btnPayFromInvoiceDetail.style.display =
                        Number(invoice.balance_amount || 0) > 0 && normalizeStatus(invoice.status) !== 'CANCELLED'
                            ? ''
                            : 'none';
                }

                if (els.btnAdjustmentFromInvoiceDetail) {
                    els.btnAdjustmentFromInvoiceDetail.style.display =
                        normalizeStatus(invoice.status) !== 'CANCELLED'
                            ? ''
                            : 'none';
                }
            } catch (error) {
                toast('error', error.message || 'Unable to load invoice details.');

                els.invoiceDetailBody.innerHTML = `
                    <div class="alert alert-danger mb-0">${escapeHtml(error.message || 'Unable to load invoice details.')}</div>
                `;
            }
        };

        const viewPayment = async (id) => {
            if (!els.paymentDetailBody) return;

            els.paymentDetailBody.innerHTML = `<div class="text-center text-muted py-5">Loading payment...</div>`;
            showModal(els.paymentDetailModal);

            try {
                const data = await request(API.paymentShow(id));
                const payment = data.payment || {};
                const invoice = data.invoice || {};
                const allocations = data.allocations || [];
                const logs = data.activity_logs || [];

                state.selectedPayment = payment;

                if (els.paymentDetailTitle) {
                    els.paymentDetailTitle.textContent = payment.payment_no || `Payment #${payment.id}`;
                }

                if (els.paymentDetailSubtitle) {
                    els.paymentDetailSubtitle.textContent =
                        `${payment.subscriber_name || 'Subscriber'} · ${methodLabel(payment.method)} · ${payment.payment_status || 'UNKNOWN'}`;
                }

                els.paymentDetailBody.innerHTML = `
                    <div class="billing-detail-grid mb-3">
                        <div class="billing-detail-box">
                            <div class="billing-detail-label mb-2">Received From</div>
                            <h5 class="fw-bold mb-1">${escapeHtml(payment.subscriber_name || invoice.subscriber_name || '—')}</h5>
                            <div class="text-muted">${escapeHtml(accountServiceLabel(payment))}</div>
                            <div class="text-muted">${escapeHtml(payment.address || invoice.address || 'No address')}</div>
                            <div class="text-muted">${escapeHtml(payment.contact_number || invoice.contact_number || 'No contact number')}</div>
                            <div class="text-muted">${escapeHtml(payment.email || invoice.email || '')}</div>
                        </div>

                        <div class="billing-detail-box">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="billing-detail-label">Status</span>
                                ${badge(payment.payment_status)}
                            </div>

                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Payment No.</span>
                                <strong>${escapeHtml(payment.payment_no || '—')}</strong>
                            </div>

                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Payment Date</span>
                                <strong>${escapeHtml(formatDateTime(payment.payment_date))}</strong>
                            </div>

                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Method</span>
                                <strong>${escapeHtml(methodLabel(payment.method))}</strong>
                            </div>

                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Reference No.</span>
                                <strong>${escapeHtml(payment.reference_no || '—')}</strong>
                            </div>

                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Amount</span>
                                <strong class="billing-money">${money(payment.amount)}</strong>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-lg-6">
                            <div class="billing-detail-box h-100">
                                <div class="billing-detail-label mb-3">Invoice Summary</div>

                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Invoice No.</span>
                                    <strong>${escapeHtml(payment.invoice_no || invoice.invoice_no || '—')}</strong>
                                </div>

                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Plan</span>
                                    <strong>${escapeHtml(payment.plan_name || invoice.plan_name || '—')}</strong>
                                </div>

                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Billing Period</span>
                                    <strong>${escapeHtml(formatDate(invoice.billing_period_start || payment.billing_period_start))} - ${escapeHtml(formatDate(invoice.billing_period_end || payment.billing_period_end))}</strong>
                                </div>

                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Invoice Total</span>
                                    <strong>${money(invoice.total_amount || payment.total_amount)}</strong>
                                </div>

                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Paid</span>
                                    <strong>${money(invoice.paid_amount || payment.paid_amount)}</strong>
                                </div>

                                <div class="d-flex justify-content-between fs-5">
                                    <span class="fw-bold">Balance</span>
                                    <strong>${money(invoice.balance_amount || payment.balance_amount)}</strong>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-lg-6">
                            <div class="billing-detail-box h-100">
                                <div class="billing-detail-label mb-3">Payment Allocation</div>

                                ${allocations.length ? allocations.map((allocation) => `
                                    <div class="d-flex justify-content-between border-bottom py-2">
                                        <div>
                                            <div class="fw-semibold">${escapeHtml(allocation.invoice_no || ('Invoice #' + allocation.invoice_id))}</div>
                                            <div class="text-muted small">${escapeHtml(allocation.invoice_status || '—')}</div>
                                        </div>
                                        <div class="text-end">
                                            <div class="billing-money">${money(allocation.allocated_amount)}</div>
                                            <div class="text-muted small">Allocated</div>
                                        </div>
                                    </div>
                                `).join('') : `
                                    <div class="text-muted">No allocation records found.</div>
                                `}

                                ${payment.void_reason ? `
                                    <div class="alert alert-warning mt-3 mb-0">
                                        <strong>Void Reason:</strong><br>
                                        ${escapeHtml(payment.void_reason)}
                                    </div>
                                ` : ''}

                                ${payment.remarks ? `
                                    <div class="alert alert-light border mt-3 mb-0">
                                        <strong>Remarks:</strong><br>
                                        ${escapeHtml(payment.remarks)}
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>

                    <div class="billing-detail-box mt-3">
                        <div class="billing-detail-label mb-2">Activity Logs</div>

                        ${logs.length ? logs.map((log) => `
                            <div class="d-flex justify-content-between border-bottom py-2">
                                <div>
                                    <div class="fw-semibold">${escapeHtml(log.title || log.action || log.event || 'Activity')}</div>
                                    <div class="text-muted small">${escapeHtml(log.message || log.description || '—')}</div>
                                </div>
                                <div class="text-end text-muted small">
                                    ${escapeHtml(formatDateTime(log.created_at))}
                                </div>
                            </div>
                        `).join('') : `
                            <div class="text-muted">No activity logs recorded.</div>
                        `}
                    </div>
                `;

                if (els.btnVoidPaymentFromDetail) {
                    els.btnVoidPaymentFromDetail.style.display =
                        normalizeStatus(payment.payment_status) === 'POSTED'
                            ? ''
                            : 'none';
                }
            } catch (error) {
                toast('error', error.message || 'Unable to load payment details.');

                els.paymentDetailBody.innerHTML = `
                    <div class="alert alert-danger mb-0">${escapeHtml(error.message || 'Unable to load payment details.')}</div>
                `;
            }
        };

        const viewAdjustment = async (id) => {
            if (!els.adjustmentDetailBody) return;

            els.adjustmentDetailBody.innerHTML = `<div class="text-center text-muted py-5">Loading adjustment...</div>`;
            showModal(els.adjustmentDetailModal);

            try {
                const data = await request(API.adjustmentShow(id));
                const adjustment = data.adjustment || {};
                const invoice = data.invoice || {};
                const invoiceAdjustments = data.invoice_adjustments || [];
                const totals = data.totals || {};
                const logs = data.activity_logs || [];

                state.selectedAdjustment = adjustment;

                if (els.adjustmentDetailTitle) {
                    els.adjustmentDetailTitle.textContent =
                        adjustment.adjustment_no || `Adjustment #${adjustment.id}`;
                }

                if (els.adjustmentDetailSubtitle) {
                    els.adjustmentDetailSubtitle.textContent =
                        `${adjustment.subscriber_name || 'Subscriber'} · ${adjustment.adjustment_type || 'Adjustment'} · ${adjustment.status || 'UNKNOWN'}`;
                }

                els.adjustmentDetailBody.innerHTML = `
                    <div class="billing-detail-grid mb-3">
                        <div class="billing-detail-box">
                            <div class="billing-detail-label mb-2">Adjustment</div>
                            <h5 class="fw-bold mb-1">${escapeHtml(adjustment.adjustment_no || ('#' + adjustment.id))}</h5>
                            <div class="text-muted">${escapeHtml(adjustmentImpactLabel(adjustment.adjustment_type))}</div>
                            <div class="mt-2">${badge(adjustment.adjustment_type)}</div>
                        </div>

                        <div class="billing-detail-box">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="billing-detail-label">Status</span>
                                ${badge(adjustment.status)}
                            </div>

                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Amount</span>
                                <strong class="billing-money">${money(adjustment.amount)}</strong>
                            </div>

                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Created</span>
                                <strong>${escapeHtml(formatDateTime(adjustment.created_at))}</strong>
                            </div>

                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Approved</span>
                                <strong>${escapeHtml(formatDateTime(adjustment.approved_at))}</strong>
                            </div>
                        </div>
                    </div>

                    <div class="billing-detail-grid mb-3">
                        <div class="billing-detail-box">
                            <div class="billing-detail-label mb-2">Subscriber</div>
                            <h5 class="fw-bold mb-1">${escapeHtml(adjustment.subscriber_name || invoice.subscriber_name || '—')}</h5>
                            <div class="text-muted">${escapeHtml(accountServiceLabel(adjustment))}</div>
                            <div class="text-muted">${escapeHtml(adjustment.address || invoice.address || 'No address')}</div>
                            <div class="text-muted">${escapeHtml(adjustment.contact_number || invoice.contact_number || 'No contact number')}</div>
                            <div class="text-muted">${escapeHtml(adjustment.email || invoice.email || '')}</div>
                        </div>

                        <div class="billing-detail-box">
                            <div class="billing-detail-label mb-3">Invoice Summary</div>

                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Invoice No.</span>
                                <strong>${escapeHtml(adjustment.invoice_no || invoice.invoice_no || '—')}</strong>
                            </div>

                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Plan</span>
                                <strong>${escapeHtml(adjustment.plan_name || invoice.plan_name || '—')}</strong>
                            </div>

                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Invoice Total</span>
                                <strong>${money(invoice.total_amount || adjustment.total_amount)}</strong>
                            </div>

                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Paid</span>
                                <strong>${money(invoice.paid_amount || adjustment.paid_amount)}</strong>
                            </div>

                            <div class="d-flex justify-content-between fs-5">
                                <span class="fw-bold">Balance</span>
                                <strong>${money(invoice.balance_amount || adjustment.balance_amount)}</strong>
                            </div>
                        </div>
                    </div>

                    <div class="billing-detail-box mb-3">
                        <div class="billing-detail-label mb-2">Reason</div>
                        <div class="text-muted">${escapeHtml(adjustment.reason || '—')}</div>

                        ${adjustment.void_reason ? `
                            <div class="alert alert-warning mt-3 mb-0">
                                <strong>Void Reason:</strong><br>
                                ${escapeHtml(adjustment.void_reason)}
                            </div>
                        ` : ''}
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-lg-6">
                            <div class="billing-detail-box h-100">
                                <div class="billing-detail-label mb-3">Adjustment Totals</div>

                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Credit Total</span>
                                    <strong>${money(totals.credit_total || 0)}</strong>
                                </div>

                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Debit Total</span>
                                    <strong>${money(totals.debit_total || 0)}</strong>
                                </div>

                                <div class="d-flex justify-content-between fs-5">
                                    <span class="fw-bold">Net Credit</span>
                                    <strong>${money(totals.net_credit_total || 0)}</strong>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-lg-6">
                            <div class="billing-detail-box h-100">
                                <div class="billing-detail-label mb-3">Invoice Adjustments</div>

                                ${invoiceAdjustments.length ? invoiceAdjustments.map((item) => `
                                    <div class="d-flex justify-content-between border-bottom py-2">
                                        <div>
                                            <div class="fw-semibold">${escapeHtml(item.adjustment_no || ('#' + item.id))}</div>
                                            <div class="text-muted small">${escapeHtml(item.adjustment_type || '—')} · ${escapeHtml(item.reason || '—')}</div>
                                        </div>
                                        <div class="text-end">
                                            <div class="billing-money">${money(item.amount)}</div>
                                            ${badge(item.status)}
                                        </div>
                                    </div>
                                `).join('') : `
                                    <div class="text-muted">No adjustment records found.</div>
                                `}
                            </div>
                        </div>
                    </div>

                    <div class="billing-detail-box mt-3">
                        <div class="billing-detail-label mb-2">Activity Logs</div>

                        ${logs.length ? logs.map((log) => `
                            <div class="d-flex justify-content-between border-bottom py-2">
                                <div>
                                    <div class="fw-semibold">${escapeHtml(log.title || log.action || log.event || 'Activity')}</div>
                                    <div class="text-muted small">${escapeHtml(log.message || log.description || '—')}</div>
                                </div>
                                <div class="text-end text-muted small">
                                    ${escapeHtml(formatDateTime(log.created_at))}
                                </div>
                            </div>
                        `).join('') : `
                            <div class="text-muted">No activity logs recorded.</div>
                        `}
                    </div>
                `;

                if (els.btnVoidAdjustmentFromDetail) {
                    els.btnVoidAdjustmentFromDetail.style.display =
                        normalizeStatus(adjustment.status) === 'POSTED'
                            ? ''
                            : 'none';
                }
            } catch (error) {
                toast('error', error.message || 'Unable to load adjustment details.');

                els.adjustmentDetailBody.innerHTML = `
                    <div class="alert alert-danger mb-0">${escapeHtml(error.message || 'Unable to load adjustment details.')}</div>
                `;
            }
        };

        const cancelInvoice = async (id) => {
            const confirmed = await confirmAction(
                'Cancel invoice?',
                'This will mark the invoice as cancelled. Paid invoices cannot be cancelled.'
            );

            if (!confirmed) return;

            try {
                showLoading('Cancelling invoice...');

                await request(API.invoiceCancel(id), {
                    method: 'POST',
                    body: JSON.stringify({})
                });

                closeLoading();

                toast('success', 'Invoice cancelled.');
                await reloadAll();
            } catch (error) {
                closeLoading();
                toast('error', error.message || 'Failed to cancel invoice.');
            }
        };

        const voidPayment = async (id) => {
            const wasPaymentModalOpen =
                els.paymentDetailModal &&
                els.paymentDetailModal.classList.contains('show');

            if (wasPaymentModalOpen) {
                hideModal(els.paymentDetailModal);
                await new Promise((resolve) => setTimeout(resolve, 350));
            }

            const reason = await promptReason(
                'Void payment?',
                'Please enter the reason for voiding this payment.',
                'Example: Wrong reference number, duplicate payment, incorrect amount...',
                'Void Payment'
            );

            if (!reason) {
                if (wasPaymentModalOpen && state.selectedPayment?.id) {
                    await viewPayment(state.selectedPayment.id);
                }

                return;
            }

            try {
                showLoading('Voiding payment...');

                await request(API.paymentVoid(id), {
                    method: 'POST',
                    body: JSON.stringify({ reason })
                });

                closeLoading();

                toast('success', 'Payment voided.');

                await reloadAll();

                if (state.selectedPayment?.id && Number(state.selectedPayment.id) === Number(id)) {
                    await viewPayment(id);
                }
            } catch (error) {
                closeLoading();
                toast('error', error.message || 'Failed to void payment.');

                if (wasPaymentModalOpen && state.selectedPayment?.id) {
                    await viewPayment(state.selectedPayment.id);
                }
            }
        };

        const voidAdjustment = async (id) => {
            const wasAdjustmentModalOpen =
                els.adjustmentDetailModal &&
                els.adjustmentDetailModal.classList.contains('show');

            if (wasAdjustmentModalOpen) {
                hideModal(els.adjustmentDetailModal);
                await new Promise((resolve) => setTimeout(resolve, 350));
            }

            const reason = await promptReason(
                'Void adjustment?',
                'Please enter the reason for voiding this adjustment.',
                'Example: Incorrect amount, wrong invoice, duplicate adjustment...',
                'Void Adjustment'
            );

            if (!reason) {
                if (wasAdjustmentModalOpen && state.selectedAdjustment?.id) {
                    await viewAdjustment(state.selectedAdjustment.id);
                }

                return;
            }

            try {
                showLoading('Voiding adjustment...');

                await request(API.adjustmentVoid(id), {
                    method: 'POST',
                    body: JSON.stringify({ reason })
                });

                closeLoading();

                toast('success', 'Billing adjustment voided.');

                await reloadAll();

                if (state.selectedAdjustment?.id && Number(state.selectedAdjustment.id) === Number(id)) {
                    await viewAdjustment(id);
                }
            } catch (error) {
                closeLoading();
                toast('error', error.message || 'Failed to void billing adjustment.');

                if (wasAdjustmentModalOpen && state.selectedAdjustment?.id) {
                    await viewAdjustment(state.selectedAdjustment.id);
                }
            }
        };

        const saveSettings = async () => {
            const payload = {
                invoice_prefix: $('settingInvoicePrefix')?.value || 'INV',
                payment_prefix: $('settingPaymentPrefix')?.value || 'PAY',
                adjustment_prefix: $('settingAdjustmentPrefix')?.value || 'ADJ',
                currency: $('settingCurrency')?.value || 'PHP',
                default_due_days: $('settingDefaultDueDays')?.value || 10,
                grace_period_days: $('settingGracePeriodDays')?.value || 3,
                tax_rate: $('settingTaxRate')?.value || 0,
                tax_enabled: $('settingTaxEnabled')?.checked ? 1 : 0,
                auto_suspend_enabled: $('settingAutoSuspend')?.checked ? 1 : 0
            };

            setButtonBusy(els.btnSaveBillingSettings, true, 'Saving...');

            try {
                await request(API.settingsSave, {
                    method: 'POST',
                    body: JSON.stringify(payload)
                });

                toast('success', 'Billing settings saved.');
            } catch (error) {
                toast('error', error.message || 'Failed to save billing settings.');
            } finally {
                setButtonBusy(els.btnSaveBillingSettings, false);
            }
        };

        const reloadAll = async () => {
            if (state.loading) return;

            state.loading = true;

            try {
                await Promise.all([
                    loadOverview(),
                    loadInvoices(),
                    loadPayments(),
                    loadAdjustments(),
                    loadCollections(),
                    loadBillingRuns(),
                    loadSettings()
                ]);
            } finally {
                state.loading = false;
            }
        };

        const debounce = (fn, wait = 350) => {
            let timer = null;

            return (...args) => {
                clearTimeout(timer);
                timer = setTimeout(() => fn(...args), wait);
            };
        };

        const bindQuickActionButtons = () => {
            const mappings = [
                ['btnWorkspaceCreateInvoice', openCreateInvoice],
                ['btnWorkspaceRecordPayment', () => openRecordPayment()],
                ['btnWorkspaceCreateAdjustment', () => openCreateAdjustment()],

                ['btnOpenCreateInvoiceOverview', openCreateInvoice],
                ['btnOpenRecordPaymentOverview', () => openRecordPayment()],
                ['btnOpenCreateAdjustmentOverview', () => openCreateAdjustment()],

                ['btnViewAllInvoices', () => goToTab('invoices')],
                ['btnViewAllPayments', () => goToTab('payments')],
                ['btnViewCollections', () => goToTab('collections')],
                ['btnViewBillingRuns', () => goToTab('runs')]
            ];

            mappings.forEach(([id, handler]) => {
                const el = $(id);
                el?.addEventListener('click', async (event) => {
                    event.preventDefault();
                    await runWithToastError(handler, 'Unable to complete action.');
                });
            });

            document.querySelectorAll('[data-billing-quick-action]').forEach((el) => {
                el.addEventListener('click', async (event) => {
                    event.preventDefault();

                    const action = el.dataset.billingQuickAction;

                    await runWithToastError(async () => {
                        if (action === 'create-invoice') await openCreateInvoice();
                        if (action === 'record-payment') await openRecordPayment();
                        if (action === 'create-adjustment') await openCreateAdjustment();
                        if (action === 'collections') await goToTab('collections');
                        if (action === 'invoices') await goToTab('invoices');
                        if (action === 'payments') await goToTab('payments');
                        if (action === 'runs') await goToTab('runs');
                    }, 'Unable to complete quick action.');
                });
            });

            document.querySelectorAll('[data-billing-tab-jump]').forEach((el) => {
                el.addEventListener('click', async (event) => {
                    event.preventDefault();

                    await runWithToastError(
                        () => goToTab(el.dataset.billingTabJump),
                        'Unable to switch billing tab.'
                    );
                });
            });
        };

        const bindEvents = () => {
            document.querySelectorAll('[data-billing-tab]').forEach((button) => {
                button.addEventListener('click', async () => {
                    await runWithToastError(
                        () => goToTab(button.dataset.billingTab),
                        'Unable to load billing tab.'
                    );
                });
            });

            els.refresh?.addEventListener('click', async () => {
                setButtonBusy(els.refresh, true, 'Refreshing...');

                try {
                    await reloadAll();
                    toast('success', 'Billing data refreshed.');
                } catch (error) {
                    toast('error', error.message || 'Unable to refresh billing data.');
                } finally {
                    setButtonBusy(els.refresh, false);
                }
            });

            els.generateDueInvoices?.addEventListener('click', generateDueInvoices);
            els.generateDueInvoices2?.addEventListener('click', generateDueInvoices);
            els.generateDueInvoices3?.addEventListener('click', generateDueInvoices);

            els.search?.addEventListener('input', debounce(async (event) => {
                state.search = event.target.value.trim();

                try {
                    await Promise.all([
                        loadInvoices(),
                        loadPayments(),
                        loadAdjustments(),
                        loadCollections()
                    ]);
                } catch (error) {
                    toast('error', error.message || 'Unable to search billing records.');
                }
            }));

            els.invoiceStatusFilter?.addEventListener('change', () => {
                runWithToastError(loadInvoices, 'Unable to filter invoices.');
            });

            els.paymentStatusFilter?.addEventListener('change', () => {
                runWithToastError(loadPayments, 'Unable to filter payments.');
            });

            els.adjustmentStatusFilter?.addEventListener('change', () => {
                runWithToastError(loadAdjustments, 'Unable to filter adjustments.');
            });

            els.adjustmentTypeFilter?.addEventListener('change', () => {
                runWithToastError(loadAdjustments, 'Unable to filter adjustments.');
            });

            els.collectionsBucketFilter?.addEventListener('change', async () => {
                state.collectionsBucket = els.collectionsBucketFilter.value || '';
                await runWithToastError(loadCollections, 'Unable to filter collections aging.');
            });

            els.collectionsAsOfDate?.addEventListener('change', async () => {
                state.collectionsAsOfDate = els.collectionsAsOfDate.value || '';
                await runWithToastError(loadCollections, 'Unable to refresh collections aging.');
            });

            els.btnRefreshCollections?.addEventListener('click', async () => {
                setButtonBusy(els.btnRefreshCollections, true, 'Refreshing...');

                try {
                    await loadCollections();
                    toast('success', 'Collections aging refreshed.');
                } catch (error) {
                    toast('error', error.message || 'Unable to refresh collections aging.');
                } finally {
                    setButtonBusy(els.btnRefreshCollections, false);
                }
            });

            document.querySelectorAll('[data-collections-bucket]').forEach((card) => {
                card.addEventListener('click', async () => {
                    const bucket = card.dataset.collectionsBucket || '';

                    state.collectionsBucket = state.collectionsBucket === bucket ? '' : bucket;

                    if (els.collectionsBucketFilter) {
                        els.collectionsBucketFilter.value = state.collectionsBucket;
                    }

                    await runWithToastError(async () => {
                        await loadCollections();
                        setActiveTab('collections');
                    }, 'Unable to load collections bucket.');
                });
            });

            els.billingRunStatusFilter?.addEventListener('change', () => {
                runWithToastError(loadBillingRuns, 'Unable to filter billing runs.');
            });

            els.billingRunTypeFilter?.addEventListener('change', () => {
                runWithToastError(loadBillingRuns, 'Unable to filter billing runs.');
            });

            $('btnOpenCreateInvoice')?.addEventListener('click', openCreateInvoice);
            $('btnOpenCreateInvoice2')?.addEventListener('click', openCreateInvoice);
            $('btnOpenRecordPayment')?.addEventListener('click', () => openRecordPayment());
            $('btnOpenRecordPayment2')?.addEventListener('click', () => openRecordPayment());
            $('btnOpenCreateAdjustment')?.addEventListener('click', () => openCreateAdjustment());
            $('btnOpenCreateAdjustment2')?.addEventListener('click', () => openCreateAdjustment());

            bindQuickActionButtons();

            els.paymentInvoiceId?.addEventListener('change', updatePaymentBalanceHint);
            els.adjustmentInvoiceId?.addEventListener('change', updateAdjustmentInvoiceHint);

            els.createInvoiceForm?.addEventListener('submit', createInvoice);
            els.recordPaymentForm?.addEventListener('submit', recordPayment);
            els.createAdjustmentForm?.addEventListener('submit', createAdjustment);
            els.btnSaveBillingSettings?.addEventListener('click', saveSettings);

            els.recentInvoicesBody?.addEventListener('click', async (event) => {
                const row = event.target.closest('[data-action="view-invoice"]');
                if (!row) return;

                await viewInvoice(Number(row.dataset.id));
            });

            els.recentPaymentsBody?.addEventListener('click', async (event) => {
                const row = event.target.closest('[data-action="view-payment"]');
                if (!row) return;

                await viewPayment(Number(row.dataset.id));
            });

            els.invoiceTableBody?.addEventListener('click', async (event) => {
                const button = event.target.closest('button[data-action]');
                if (!button) return;

                const id = Number(button.dataset.id);
                const action = button.dataset.action;

                if (action === 'view-invoice') await viewInvoice(id);

                if (action === 'print-invoice') {
                    openPrintable(API.invoicePrint(id), 'Invoice print page opened.');
                }

                if (action === 'adjust-invoice') await openCreateAdjustment(id);
                if (action === 'pay-invoice') await openRecordPayment(id);
                if (action === 'cancel-invoice') await cancelInvoice(id);
            });

            els.paymentTableBody?.addEventListener('click', async (event) => {
                const button = event.target.closest('button[data-action]');
                if (!button) return;

                const id = Number(button.dataset.id);
                const action = button.dataset.action;

                if (action === 'view-payment') await viewPayment(id);

                if (action === 'print-receipt') {
                    openPrintable(API.paymentReceipt(id), 'Payment receipt opened.');
                }

                if (action === 'void-payment') await voidPayment(id);
                if (action === 'approve-payment' && window.confirm('Confirm that this payment was received and post it to the invoice?')) {
                    await request(API.paymentApprove(id),{method:'POST'}); toast('success','Payment approved.'); await Promise.all([loadPayments(),loadInvoices(),loadOverview()]);
                }
                if (action === 'reject-payment') {
                    const reason=window.prompt('Reason for rejecting this payment:','');
                    if(reason?.trim()){await request(API.paymentReject(id),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({reason:reason.trim()})});toast('success','Payment rejected.');await loadPayments();}
                }
            });

            els.adjustmentTableBody?.addEventListener('click', async (event) => {
                const button = event.target.closest('button[data-action]');
                if (!button) return;

                const id = Number(button.dataset.id);
                const action = button.dataset.action;

                if (action === 'view-adjustment') await viewAdjustment(id);
                if (action === 'void-adjustment') await voidAdjustment(id);
            });

            els.collectionsTableBody?.addEventListener('click', async (event) => {
                const button = event.target.closest('button[data-action]');
                if (!button) return;

                const id = Number(button.dataset.id);
                const action = button.dataset.action;

                if (action === 'view-invoice') await viewInvoice(id);
                if (action === 'pay-invoice') await openRecordPayment(id);

                if (action === 'print-invoice') {
                    openPrintable(API.invoicePrint(id), 'Invoice print page opened.');
                }
            });

            els.billingRunTableBody?.addEventListener('click', async (event) => {
                const button = event.target.closest('button[data-action]');
                if (!button) return;

                const id = Number(button.dataset.id);
                const action = button.dataset.action;

                if (action === 'view-billing-run') await viewBillingRun(id);
            });

            els.btnPrintInvoiceFromDetail?.addEventListener('click', () => {
                const invoice = state.selectedInvoiceForPayment;
                if (!invoice?.id) return;

                openPrintable(API.invoicePrint(invoice.id), 'Invoice print page opened.');
            });

            els.btnAdjustmentFromInvoiceDetail?.addEventListener('click', async () => {
                const invoice = state.selectedInvoiceForPayment;
                if (!invoice?.id) return;

                hideModal(els.invoiceDetailModal);
                await new Promise((resolve) => setTimeout(resolve, 300));

                await openCreateAdjustment(invoice.id);
            });

            els.btnPayFromInvoiceDetail?.addEventListener('click', async () => {
                const invoice = state.selectedInvoiceForPayment;
                if (!invoice?.id) return;

                hideModal(els.invoiceDetailModal);
                await new Promise((resolve) => setTimeout(resolve, 300));

                await openRecordPayment(invoice.id);
            });

            els.btnPrintReceiptFromDetail?.addEventListener('click', () => {
                const payment = state.selectedPayment;
                if (!payment?.id) return;

                openPrintable(API.paymentReceipt(payment.id), 'Payment receipt opened.');
            });

            els.btnVoidPaymentFromDetail?.addEventListener('click', async () => {
                const payment = state.selectedPayment;
                if (!payment?.id) return;

                await voidPayment(payment.id);
            });

            els.btnVoidAdjustmentFromDetail?.addEventListener('click', async () => {
                const adjustment = state.selectedAdjustment;
                if (!adjustment?.id) return;

                await voidAdjustment(adjustment.id);
            });
        };

        const init = async () => {
            if (!window.bootstrap?.Modal) {
                console.error('Bootstrap Modal JS is not loaded. Billing modals will not open.');
            }

            if (els.collectionsAsOfDate && !els.collectionsAsOfDate.value) {
                els.collectionsAsOfDate.value = today();
                state.collectionsAsOfDate = els.collectionsAsOfDate.value;
            }

            if (els.collectionsBucketFilter) {
                state.collectionsBucket = els.collectionsBucketFilter.value || '';
            }

            setActiveTab(state.activeTab);
            bindEvents();

            try {
                await reloadAll();
            } catch (error) {
                toast('error', error.message || 'Unable to load billing module.');
            }
        };

        init();
    });
})();
