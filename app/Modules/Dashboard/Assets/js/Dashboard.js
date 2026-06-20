document.addEventListener('DOMContentLoaded', () => {
    const app = document.getElementById('dashboardPage');
    if (!app || !window.NX) return;

    const {
        api,
        ui,
        dom
    } = window.NX;

    const { html } = dom;

    const grid = document.getElementById('dashboardKpiGrid');
    const refreshBtn = document.getElementById('dashboardRefreshBtn');

    function peso(v) {
        return '₱' + Number(v || 0).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function card(label, value, icon, tone) {
        return `
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm dashboard-kpi-card dashboard-kpi-${tone}">
                    <div class="card-body">
                        <div class="dashboard-kpi-top">
                            <div class="dashboard-kpi-icon">
                                <i class="bi ${icon}"></i>
                            </div>
                            <div class="dashboard-kpi-label">${label}</div>
                        </div>
                        <div class="dashboard-kpi-value">${value}</div>
                    </div>
                </div>
            </div>
        `;
    }

    function renderDashboard(data) {
        html(grid, `
            ${card('Subscribers', data.subscribers, 'bi-people-fill', 'primary')}
            ${card('Active Services', data.active_services, 'bi-wifi', 'success')}
            ${card('Suspended Services', data.suspended_services, 'bi-pause-circle', 'warning')}
            ${card('Unpaid Billing', data.unpaid_invoices, 'bi-receipt-cutoff', 'danger')}
            ${card('Paid Billing', data.paid_invoices, 'bi-cash-stack', 'success')}
            ${card('Revenue Today', peso(data.today_revenue), 'bi-cash-coin', 'primary')}
            ${card('Active Sessions', data.active_sessions, 'bi-plug-fill', 'primary')}
            ${card('Online ONTs', data.online_onts, 'bi-router-fill', 'success')}
            ${card('Offline ONTs', data.offline_onts, 'bi-router', 'danger')}
            ${card('Available NAP Ports', data.available_nap_ports, 'bi-diagram-3-fill', 'success')}
            ${card('Used NAP Ports', data.used_nap_ports, 'bi-diagram-3', 'warning')}
            ${card('Provisioning Success', data.provisioning_success, 'bi-check-circle-fill', 'success')}
            ${card('Provisioning Failed', data.provisioning_failed, 'bi-x-circle-fill', 'danger')}
            ${card('Provisioning In Progress', data.provisioning_in_progress, 'bi-arrow-repeat', 'primary')}
        `);
    }

    function renderLoading() {
        html(grid, `
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-muted">
                        Loading dashboard data...
                    </div>
                </div>
            </div>
        `);
    }

    function renderError(message) {
        html(grid, `
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="alert alert-danger mb-0">${message}</div>
                    </div>
                </div>
            </div>
        `);

        ui.toast('error', message || 'Failed to load dashboard.');
    }

    async function loadDashboard() {
        renderLoading();

        try {
            const response = await api.get('/api/v1/dashboard/stats');
            renderDashboard(response || {});
        } catch (err) {
            renderError(err.message || 'Failed to load dashboard.');
        }
    }

    if (refreshBtn) {
        refreshBtn.addEventListener('click', loadDashboard);
    }

    loadDashboard();
});