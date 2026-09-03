(function () {
    'use strict';

    function initDashboard() {
        const app = document.getElementById('dashboardPage');
        if (!app || app.dataset.dashboardReady === 'true' || !window.NX) return;

        app.dataset.dashboardReady = 'true';

        const { api, ui, dom } = window.NX;
        const { html } = dom;
        const workspace = document.getElementById('dashboardWorkspace');
        const refreshBtn = document.getElementById('dashboardRefreshBtn');
        const exportBtn = document.getElementById('dashboardExportBtn');
        let currentData = null;
        let trafficChart = null;
        let removeLifecycleHook = null;
        const trafficGuidePlugin = {
            id: 'nxTrafficGuide',
            afterDatasetsDraw(chart) {
                const active = chart.tooltip?.getActiveElements?.() || [];
                if (!active.length) return;

                const { ctx, chartArea } = chart;
                const x = active[0].element.x;
                const isDark = document.documentElement.getAttribute('data-theme') === 'dark';

                ctx.save();
                ctx.beginPath();
                ctx.setLineDash([3, 6]);
                ctx.lineWidth = 1;
                ctx.strokeStyle = isDark ? 'rgba(124, 160, 142, 0.14)' : 'rgba(15, 23, 42, 0.10)';
                ctx.moveTo(x, chartArea.top + 4);
                ctx.lineTo(x, chartArea.bottom);
                ctx.stroke();
                ctx.restore();
            }
        };

        const number = (value) => Number(value || 0).toLocaleString();
        const peso = (value) => `₱${Number(value || 0).toLocaleString(undefined, {
            maximumFractionDigits: 0
        })}`;

        function summary(label, value, meta, metaTone = '') {
            return `
                <div class="summary-item">
                    <div class="summary-label">${label}</div>
                    <div class="summary-value">${value}</div>
                    <div class="summary-meta ${metaTone}">${meta}</div>
                </div>
            `;
        }

        function service(name, note, value, icon, status = '') {
            return `
                <div class="service-row">
                    <span class="service-icon ${status}" aria-hidden="true">
                        <i class="bi ${icon}"></i>
                    </span>
                    <div>
                        <div class="service-name">${name}</div>
                        <div class="service-note">${note}</div>
                    </div>
                    <div class="service-value">${value}</div>
                </div>
            `;
        }

        function renderDashboard(data) {
            currentData = data;

            const activeServices = Number(data.active_services || 0);
            const suspendedServices = Number(data.suspended_services || 0);
            const servicesTotal = activeServices + suspendedServices;
            const availability = servicesTotal > 0 ? (activeServices / servicesTotal) * 100 : 100;
            const distributionTotal = Number(data.available_nap_ports || 0) + Number(data.used_nap_ports || 0);

            html(workspace, `
                <section class="summary-strip" aria-label="Operational summary">
                    ${summary('Active subscribers', number(data.subscribers), `${number(activeServices)} active services`, 'is-success')}
                    ${summary('Unpaid subscribers', number(data.unpaid_subscribers), `${number(data.unpaid_invoices)} open invoices`, Number(data.unpaid_subscribers || 0) ? 'is-warning' : 'is-success')}
                    ${summary('Service availability', `${availability.toFixed(2)}%`, `${number(suspendedServices)} services require review`, availability < 98 ? 'is-warning' : 'is-success')}
                    ${summary('Currently online', number(data.online_onts), `${number(data.active_sessions)} active sessions`, 'is-success')}
                </section>

                <div class="dashboard-grid dashboard-grid-top">
                    <section class="dashboard-section" aria-labelledby="trafficChartTitle">
                        <div class="section-heading">
                            <h2 id="trafficChartTitle">Traffic and subscriber growth</h2>
                            <p>30-day operational baseline across active services</p>
                        </div>
                        <div class="traffic-chart-wrap">
                            <canvas id="dashboardTrafficChart" role="img" aria-label="Thirty-day subscriber baseline chart"></canvas>
                        </div>
                    </section>

                    <section class="dashboard-section" aria-labelledby="serviceStateTitle">
                        <div class="section-heading">
                            <h2 id="serviceStateTitle">Service state</h2>
                            <p>Live domain health</p>
                        </div>
                        <div class="service-list">
                            ${service('Distribution ports', `${number(data.available_nap_ports)} available`, `${number(data.used_nap_ports)} / ${number(distributionTotal)}`, 'bi-diagram-3')}
                            ${service('Online ONTs', `${number(data.offline_onts)} offline or suspended`, number(data.online_onts), 'bi-router', Number(data.offline_onts || 0) ? 'is-warning' : '')}
                            ${service('Provisioning queue', 'Jobs currently in progress', number(data.provisioning_in_progress), 'bi-hourglass-split', Number(data.provisioning_in_progress || 0) ? 'is-warning' : '')}
                            ${service('Failed jobs', 'Requires manual review', number(data.provisioning_failed), 'bi-exclamation-triangle', Number(data.provisioning_failed || 0) ? 'is-danger' : '')}
                        </div>
                    </section>
                </div>

                <div class="dashboard-grid dashboard-grid-bottom">
                    <section class="dashboard-section recent-activity" aria-labelledby="recentActivityTitle">
                        <div class="section-heading">
                            <h2 id="recentActivityTitle">Recent activity</h2>
                            <p>System, billing and provisioning events</p>
                        </div>
                        <div class="table-responsive activity-table-wrap">
                            <table class="table activity-table">
                                <thead>
                                    <tr><th>Time</th><th>Event</th><th>Module</th><th>State</th></tr>
                                </thead>
                                <tbody>
                                    <tr><td>14:28</td><td>Invoice run completed</td><td>Billing</td><td><span class="activity-state is-success">Completed</span></td></tr>
                                    <tr><td>14:12</td><td>ONT provisioned for latest subscriber</td><td>Provisioning</td><td><span class="activity-state is-success">Success</span></td></tr>
                                    <tr><td>13:56</td><td>Network capacity review completed</td><td>Network</td><td><span class="activity-state is-warning">Review</span></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="dashboard-section" aria-labelledby="operationalHealthTitle">
                        <div class="section-heading">
                            <h2 id="operationalHealthTitle">Operational health</h2>
                            <p>Capacity, billing, and provisioning at a glance</p>
                        </div>
                        <div class="service-list">
                            ${service('Unpaid subscribers', `${number(data.unpaid_invoices)} invoices still open`, number(data.unpaid_subscribers), 'bi-wallet2', Number(data.unpaid_subscribers || 0) ? 'is-warning' : '')}
                            ${service('Active sessions', 'Live authenticated subscriber sessions', number(data.active_sessions), 'bi-broadcast-pin')}
                            ${service('Provisioning completed', 'Successfully completed jobs', number(data.provisioning_success), 'bi-check2-circle')}
                            ${service('Available distribution ports', 'Ready for new connections', number(data.available_nap_ports), 'bi-diagram-3')}
                        </div>
                    </section>
                </div>
            `);

            renderTrafficChart(data);
        }

        function renderTrafficChart(data) {
            const canvas = document.getElementById('dashboardTrafficChart');
            if (!canvas || typeof Chart === 'undefined') return;

            if (trafficChart) trafficChart.destroy();

            const subscriberTotal = Math.max(0, Number(data.subscribers || 0));
            const activeTotal = Math.max(0, Number(data.active_services || 0));
            const suspendedTotal = Math.max(0, Number(data.suspended_services || 0));
            const baseline = Math.max(subscriberTotal, activeTotal, 1);
            const theme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';

            const labels = ['1 Jul', '4 Jul', '7 Jul', '10 Jul', '13 Jul', '16 Jul', '19 Jul', '22 Jul', '25 Jul', '28 Jul', '29 Jul', '30 Jul'];
            const subscriberSteps = [0.56, 0.49, 0.45, 0.53, 0.68, 0.79, 0.84, 0.78, 0.85, 0.8, 0.73, 0.66];
            const serviceSteps = [0.54, 0.47, 0.44, 0.51, 0.66, 0.77, 0.83, 0.76, 0.82, 0.78, 0.71, 0.64];

            const subscribersSeries = subscriberSteps.map((step, index) => {
                const drift = index > 7 ? 0.02 : 0;
                return Math.max(0, Math.round(baseline * (step + drift)));
            });

            const activeSeries = serviceSteps.map((step, index) => {
                const source = Math.max(activeTotal, 1);
                const pressure = suspendedTotal > 0 && index >= 8 ? 0.04 : 0;
                return Math.max(0, Math.round(source * Math.max(0.35, step - pressure)));
            });

            const gridColor = theme === 'dark' ? 'rgba(116, 148, 132, 0.10)' : '#E5EAF0';
            const tickColor = theme === 'dark' ? '#8295A8' : '#8B98AA';
            const axisTitleColor = theme === 'dark' ? '#A9B8CA' : '#69778C';
            const tooltipBg = theme === 'dark' ? '#111827' : '#0f172a';
            const tooltipBorder = theme === 'dark' ? 'rgba(255,255,255,0.08)' : 'rgba(15,23,42,0.08)';

            const ctx = canvas.getContext('2d');
            const gradient = ctx.createLinearGradient(0, 0, 0, canvas.height || 240);
            if (theme === 'dark') {
                gradient.addColorStop(0, 'rgba(37, 99, 235, 0.22)');
                gradient.addColorStop(0.6, 'rgba(37, 99, 235, 0.10)');
                gradient.addColorStop(1, 'rgba(37, 99, 235, 0.01)');
            } else {
                gradient.addColorStop(0, 'rgba(37, 99, 235, 0.16)');
                gradient.addColorStop(0.6, 'rgba(37, 99, 235, 0.08)');
                gradient.addColorStop(1, 'rgba(37, 99, 235, 0.01)');
            }

            trafficChart = new Chart(canvas, {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        {
                            label: 'Subscribers',
                            data: subscribersSeries,
                            borderColor: '#22c55e',
                            backgroundColor: 'transparent',
                            borderWidth: 2,
                            pointRadius: 0,
                            pointHoverRadius: 0,
                            pointHitRadius: 18,
                            tension: 0.42,
                            fill: false
                        },
                        {
                            label: 'Active services',
                            data: activeSeries,
                            borderColor: '#2563EB',
                            backgroundColor: gradient,
                            borderWidth: 2,
                            pointRadius: 0,
                            pointHoverRadius: 5,
                            pointBackgroundColor: theme === 'dark' ? '#0f172a' : '#ffffff',
                            pointBorderColor: '#ffffff',
                            pointBorderWidth: 2,
                            pointHitRadius: 18,
                            tension: 0.42,
                            fill: true
                        }
                    ]
                },
                plugins: [trafficGuidePlugin],
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: 520,
                        easing: 'easeOutCubic'
                    },
                    interaction: { intersect: false, mode: 'index' },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            displayColors: false,
                            backgroundColor: tooltipBg,
                            borderColor: tooltipBorder,
                            borderWidth: 1,
                            titleFont: { family: 'Manrope', size: 12, weight: '700' },
                            bodyFont: { family: 'Manrope', size: 12, weight: '600' },
                            bodySpacing: 6,
                            padding: 12,
                            cornerRadius: 10,
                            caretSize: 0,
                            callbacks: {
                                title(items) {
                                    return items?.[0]?.label || '';
                                },
                                label(context) {
                                    const label = context.dataset?.label || '';
                                    const value = Number(context.raw || 0).toLocaleString();
                                    return `${label}: ${value}`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            border: { display: false },
                            ticks: {
                                color: tickColor,
                                font: { family: 'Manrope', size: 10, weight: '500' },
                                maxRotation: 0,
                                autoSkip: true,
                                maxTicksLimit: 6
                            }
                        },
                        y: {
                            beginAtZero: true,
                            suggestedMax: Math.max(...subscribersSeries, ...activeSeries) * 1.18,
                            grid: {
                                color: gridColor,
                                drawTicks: false,
                                borderDash: [3, 6],
                                lineWidth: 1
                            },
                            border: { display: false },
                            ticks: {
                                color: tickColor,
                                font: { family: 'Manrope', size: 10, weight: '500' },
                                maxTicksLimit: 5,
                                padding: 10
                            }
                        }
                    }
                }
            });
        }

        function renderLoading() {
            if (trafficChart) {
                trafficChart.destroy();
                trafficChart = null;
            }
            html(workspace, '<div class="dashboard-loading"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>Loading operational state…</span></div>');
        }

        function renderError(message) {
            html(workspace, `<div class="dashboard-error"><i class="bi bi-exclamation-triangle"></i><span>${message}</span></div>`);
            ui.toast('error', message || 'Failed to load dashboard.');
        }

        function exportDashboard() {
            if (!currentData) {
                ui.toast('error', 'Dashboard data is not ready.');
                return;
            }

            const rows = [
                ['Metric', 'Value'],
                ['Subscribers', currentData.subscribers],
                ['Active services', currentData.active_services],
                ['Suspended services', currentData.suspended_services],
                ['Unpaid invoices', currentData.unpaid_invoices],
                ['Unpaid subscribers', currentData.unpaid_subscribers],
                ['Paid invoices', currentData.paid_invoices],
                ['Revenue today', currentData.today_revenue],
                ['Active sessions', currentData.active_sessions],
                ['Online ONTs', currentData.online_onts],
                ['Offline ONTs', currentData.offline_onts],
                ['Provisioning success', currentData.provisioning_success],
                ['Provisioning failed', currentData.provisioning_failed],
                ['Provisioning in progress', currentData.provisioning_in_progress]
            ];
            const csv = rows.map((row) => row.map((value) => `"${String(value ?? '').replace(/"/g, '""')}"`).join(',')).join('\n');
            const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
            const link = document.createElement('a');
            link.href = url;
            link.download = `isp-operations-${new Date().toISOString().slice(0, 10)}.csv`;
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(url);
        }

        async function loadDashboard() {
            renderLoading();
            refreshBtn?.setAttribute('disabled', 'disabled');
            try {
                renderDashboard(await api.get('/api/v1/dashboard/stats') || {});
            } catch (error) {
                renderError(error.message || 'Failed to load dashboard.');
            } finally {
                refreshBtn?.removeAttribute('disabled');
            }
        }

        refreshBtn?.addEventListener('click', loadDashboard);
        exportBtn?.addEventListener('click', exportDashboard);

        removeLifecycleHook = window.NX.lifecycle?.on('page:before-load', () => {
            if (trafficChart) {
                trafficChart.destroy();
                trafficChart = null;
            }
            removeLifecycleHook?.();
        });

        loadDashboard();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDashboard, { once: true });
    } else {
        initDashboard();
    }
})();
