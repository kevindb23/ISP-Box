document.addEventListener('DOMContentLoaded', () => {
    const pageEl = document.getElementById('ontAcsDevicePage');
    if (!pageEl || !window.NX) return;

    const {
        api,
        ui,
        dom,
        util,
        render,
        forms,
        page,
        poller
    } = window.NX;

    const { html, text } = dom;
    const { escape, upper } = util;

    const el = {
        header: document.getElementById('acsDeviceHeader'),
        content: document.getElementById('acsDeviceContent'),
        tabs: document.getElementById('acsDeviceTabs'),
        refreshBtn: document.getElementById('acsDetailRefreshBtn'),
        pingBtn: document.getElementById('acsPingBtn')
    };

    let autoPoller = null;
    let pingPoller = null;

    const appPage = page.create({
        state: {
            deviceId: pageEl.dataset.deviceId || '',
            activeTab: 'summary',
            loading: true,
            device: null,
            parameters: {},
            optical: null,
            ping: {
                ms: null,
                host: null
            }
        },

        init(ctx) {
            bindTabs(ctx);
            bindTopActions(ctx);
        },

        async load(ctx) {
            await loadDevice(ctx);
        },

        render(ctx) {
            renderHeader(ctx);
            renderContent(ctx);
        }
    });

    function statusBadge(status) {
        const normalized = upper(status || '');
        let cls = 'bg-light-subtle text-dark';

        if (['ONLINE', 'MATCHED', 'ACTIVE', 'CONNECTED', 'UP'].includes(normalized)) {
            cls = 'bg-success-subtle text-success';
        } else if (['OFFLINE', 'UNMATCHED', 'DISABLED', 'DOWN', 'ERROR', 'ROGUE'].includes(normalized)) {
            cls = 'bg-danger-subtle text-danger';
        } else if (['STALE', 'WARNING', 'PENDING'].includes(normalized)) {
            cls = 'bg-warning-subtle text-warning';
        } else if (['CONFIGURED'].includes(normalized)) {
            cls = 'bg-primary-subtle text-primary';
        }

        return `<span class="badge rounded-pill ${cls}">${escape(status || '-')}</span>`;
    }

    function formatDateTime(value) {
        if (!value || value === '-') return '-';
        const d = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(d.getTime())) return String(value);
        return d.toLocaleString();
    }

    function formatSeconds(seconds) {
        const s = Number(seconds);
        if (!Number.isFinite(s) || s <= 0) return '-';

        const days = Math.floor(s / 86400);
        const hours = Math.floor((s % 86400) / 3600);
        const mins = Math.floor((s % 3600) / 60);
        const secs = Math.floor(s % 60);

        const parts = [];
        if (days > 0) parts.push(`${days}d`);
        if (hours > 0) parts.push(`${hours}h`);
        if (mins > 0) parts.push(`${mins}m`);
        if (secs > 0 && days === 0) parts.push(`${secs}s`);

        return parts.length ? parts.join(' ') : '0s';
    }

    function timeAgo(value) {
        if (!value || value === '-') return '-';

        const d = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(d.getTime())) return String(value);

        const diffSeconds = Math.floor((Date.now() - d.getTime()) / 1000);

        if (diffSeconds < 60) return 'just now';
        if (diffSeconds < 3600) return `${Math.floor(diffSeconds / 60)} min ago`;
        if (diffSeconds < 86400) return `${Math.floor(diffSeconds / 3600)} hr ago`;

        const days = Math.floor(diffSeconds / 86400);
        return `${days} day${days > 1 ? 's' : ''} ago`;
    }

    function toBool(value) {
        const v = String(value ?? '').toLowerCase();
        return ['1', 'true', 'yes', 'enabled', 'up'].includes(v);
    }

    function valueOf(node) {
        if (node && typeof node === 'object' && '_value' in node) return node._value;
        if (node === null || node === undefined || node === '') return null;
        return node;
    }

    function getNode(state, path) {
        const src = state.parameters || {};
        let cur = src;

        for (const seg of path.split('.')) {
            if (!cur || typeof cur !== 'object' || !(seg in cur)) {
                return null;
            }
            cur = cur[seg];
        }

        return cur;
    }

    function getParam(state, paths, fallback = '-') {
        for (const path of paths) {
            const node = getNode(state, path);
            const value = valueOf(node);
            if (value !== null && value !== undefined && value !== '') {
                return value;
            }
        }
        return fallback;
    }

    function getIndexedNode(state, path) {
        const node = getNode(state, path);
        if (!node || typeof node !== 'object') return [];

        return Object.keys(node)
            .filter(k => /^\d+$/.test(k))
            .sort((a, b) => Number(a) - Number(b))
            .map(k => ({
                index: k,
                data: node[k] || {}
            }));
    }

    function getSafeDevice(state) {
        return state.device || {};
    }

    function getOpticalData(state) {
        const o = state.optical || {};

        return {
            rx: o.rx_power_dbm != null ? o.rx_power_dbm : '-',
            tx: o.tx_power_dbm != null ? o.tx_power_dbm : '-',
            oltRx: o.olt_rx_ont_power_dbm != null ? o.olt_rx_ont_power_dbm : '-',
            temperature: o.temperature_c != null ? o.temperature_c : '-',
            voltage: o.voltage_v != null ? o.voltage_v : '-',
            current: o.laser_bias_current_ma != null ? o.laser_bias_current_ma : '-',
            distance: o.distance_m != null ? o.distance_m : '-',
            polledAt: o.polled_at ? new Date(String(o.polled_at).replace(' ', 'T')).toLocaleString() : '-'
        };
    }

    function formatOpticalValue(value, unit = '') {
        if (value === '-' || value === null || value === undefined || Number.isNaN(Number(value))) {
            return 'Not available';
        }
        return `${value}${unit ? ` ${unit}` : ''}`;
    }

    function collectWanInterfaces(state) {
        const results = [];
        const wanDevices = getIndexedNode(state, 'InternetGatewayDevice.WANDevice');

        wanDevices.forEach(wd => {
            const base = wd.data?.WANConnectionDevice || {};

            Object.keys(base)
                .filter(k => /^\d+$/.test(k))
                .sort((a, b) => Number(a) - Number(b))
                .forEach(connIdx => {
                    const conn = base[connIdx] || {};

                    const ppps = conn.WANPPPConnection || {};
                    Object.keys(ppps)
                        .filter(k => /^\d+$/.test(k))
                        .sort((a, b) => Number(a) - Number(b))
                        .forEach(pppIdx => {
                            const p = ppps[pppIdx] || {};
                            results.push({
                                type: 'PPP',
                                title: `PPPoE Connection ${pppIdx}`,
                                subtitle: valueOf(p.Name) || 'Internet',
                                name: valueOf(p.Name) || '-',
                                connectionType: valueOf(p.ConnectionType) || 'IP_Routed',
                                username: valueOf(p.Username) || '-',
                                serviceList: valueOf(p.X_HW_ServiceList) || valueOf(p.ServiceList) || '-',
                                vlanId: valueOf(p.X_HW_VLAN) || valueOf(p.VLANIDMark) || '-',
                                status: valueOf(p.ConnectionStatus) || '-',
                                ipAddress: valueOf(p.ExternalIPAddress) || valueOf(p.IPAddress) || '0.0.0.0',
                                macAddress: valueOf(p.MACAddress) || valueOf(p.X_HW_MACAddress) || '-'
                            });
                        });

                    const ips = conn.WANIPConnection || {};
                    Object.keys(ips)
                        .filter(k => /^\d+$/.test(k))
                        .sort((a, b) => Number(a) - Number(b))
                        .forEach(ipIdx => {
                            const p = ips[ipIdx] || {};
                            results.push({
                                type: 'IP',
                                title: `IP Connection ${ipIdx}`,
                                subtitle: valueOf(p.Name) || 'Not configured',
                                name: valueOf(p.Name) || '-',
                                connectionType: valueOf(p.ConnectionType) || 'IP_Routed',
                                username: valueOf(p.Username) || '-',
                                serviceList: valueOf(p.X_HW_ServiceList) || valueOf(p.ServiceList) || '-',
                                vlanId: valueOf(p.X_HW_VLAN) || valueOf(p.VLANIDMark) || '-',
                                status: valueOf(p.ConnectionStatus) || '-',
                                ipAddress: valueOf(p.ExternalIPAddress) || valueOf(p.IPAddress) || '0.0.0.0',
                                macAddress: valueOf(p.MACAddress) || valueOf(p.X_HW_MACAddress) || '-'
                            });
                        });
                });
        });

        if (!results.length) {
            const d = getSafeDevice(state);
            results.push({
                type: 'IP',
                title: 'IP Connection 1',
                subtitle: d.wan_ip || 'Not configured',
                name: d.wan_ip || '-',
                connectionType: 'IP_Routed',
                username: '-',
                serviceList: '-',
                vlanId: '-',
                status: d.acs_status === 'ONLINE' ? 'Connected' : 'Offline',
                ipAddress: d.wan_ip || '0.0.0.0',
                macAddress: '-'
            });
        }

        return results;
    }

    function collectWifiInterfaces(state) {
        const results = [];
        const wlans = getIndexedNode(state, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration');

        wlans.forEach(item => {
            const w = item.data || {};
            results.push({
                index: item.index,
                title: `WLAN ${item.index}`,
                ssid: valueOf(w.SSID) || '-',
                enabled: toBool(valueOf(w.Enable)),
                password: valueOf(w.PreSharedKey?.['1']?.KeyPassphrase) || valueOf(w.KeyPassphrase) || '',
                channel: valueOf(w.Channel) || 'Auto',
                standard: valueOf(w.Standard) || 'Not configured',
                power: valueOf(w.TransmitPower) || 'Not configured',
                clients: valueOf(w.TotalAssociations) || '0',
                security: valueOf(w.BeaconType) || 'Not configured',
                encryption: valueOf(w.WPAEncryptionModes) || valueOf(w.BasicEncryptionModes) || 'Not configured',
                bssid: valueOf(w.BSSID) || '-',
                macAddress: valueOf(w.MACAddress) || '-'
            });
        });

        if (!results.length) {
            const d = getSafeDevice(state);
            results.push({
                index: '1',
                title: 'WLAN 1',
                ssid: d.ssid || '-',
                enabled: true,
                password: '',
                channel: 'Auto',
                standard: 'Not configured',
                power: 'Not configured',
                clients: String(d.client_count || 0),
                security: 'Not configured',
                encryption: 'Not configured',
                bssid: '-',
                macAddress: '-'
            });
        }

        return results;
    }

    function collectClients(state) {
        const hostBase = getNode(state, 'InternetGatewayDevice.LANDevice.1.Hosts.Host') || {};
        const rows = [];

        Object.keys(hostBase)
            .filter(k => /^\d+$/.test(k))
            .sort((a, b) => Number(a) - Number(b))
            .forEach(k => {
                const h = hostBase[k] || {};
                const active = toBool(valueOf(h.Active));
                if (!active) return;
                rows.push({
                    hostName: valueOf(h.HostName) || '-',
                    ipAddress: valueOf(h.IPAddress) || '-',
                    macAddress: valueOf(h.MACAddress) || '-',
                    interfaceType: valueOf(h.InterfaceType) || '-',
                    active
                });
            });

        return rows;
    }

    function getSummaryData(state) {
        const d = getSafeDevice(state);

        return {
            serial: d.serial_number || '-',
            manufacturer: d.inventory_info?.vendor || getParam(state, [
                'InternetGatewayDevice.DeviceInfo.Manufacturer',
                'Device.DeviceInfo.Manufacturer'
            ], d.vendor || '-'),
            productClass: getParam(state, [
                'InternetGatewayDevice.DeviceInfo.ProductClass',
                'Device.DeviceInfo.ProductClass'
            ], d.model || '-'),
            model: getParam(state, [
                'InternetGatewayDevice.DeviceInfo.ModelName',
                'Device.DeviceInfo.ModelName',
                'InternetGatewayDevice.DeviceInfo.ProductClass',
                'Device.DeviceInfo.ProductClass'
            ], d.model || '-'),
            hardware: getParam(state, [
                'InternetGatewayDevice.DeviceInfo.HardwareVersion',
                'Device.DeviceInfo.HardwareVersion'
            ], '-'),
            firmware: getParam(state, [
                'InternetGatewayDevice.DeviceInfo.SoftwareVersion',
                'Device.DeviceInfo.SoftwareVersion'
            ], d.firmware_version || '-'),
            uptimeSeconds: getParam(state, [
                'InternetGatewayDevice.DeviceInfo.UpTime',
                'Device.DeviceInfo.UpTime'
            ], d.uptime_seconds || null),
            lastSeen: d.last_seen || '-',
            wanIp: d.wan_ip || '-',
            wanStatus: d.wan_status || '-',
            managementUrl: getParam(state, [
                'InternetGatewayDevice.ManagementServer.ConnectionRequestURL',
                'Device.ManagementServer.ConnectionRequestURL'
            ], d.management_ip || '-'),
            acsStatus: d.acs_status || 'OFFLINE',
            inventoryState: d.inventory_state || 'UNMATCHED'
        };
    }

    function getCurrentOpticalSnapshot(state) {
        const o = getOpticalData(state);
        const raw = state.optical || {};

        return {
            rx: String(o.rx),
            tx: String(o.tx),
            oltRx: String(o.oltRx),
            temperature: String(o.temperature),
            voltage: String(o.voltage),
            current: String(o.current),
            distance: String(o.distance),
            polledAt: String(o.polledAt),
            rawPolledAt: raw.polled_at || null
        };
    }

    function compareOpticalSnapshots(oldData, newData) {
        const fields = [
            ['rx', 'ONT Rx Power', 'dBm'],
            ['tx', 'ONT Tx Power', 'dBm'],
            ['oltRx', 'OLT Rx ONT Power', 'dBm'],
            ['temperature', 'Temperature', '°C'],
            ['voltage', 'Voltage', 'V'],
            ['current', 'Laser Bias Current', 'mA'],
            ['distance', 'Distance', 'm']
        ];

        const changes = fields.map(([key, label, unit]) => {
            const oldVal = oldData[key] ?? '-';
            const newVal = newData[key] ?? '-';
            const changed = String(oldVal) !== String(newVal);

            return {
                key,
                label,
                unit,
                oldVal,
                newVal,
                changed
            };
        });

        return {
            changed: changes.some(item => item.changed),
            changes
        };
    }

    function buildOpticalComparisonHtml(result, previousPolledAt, newPolledAt) {
        const rows = result.changes.map(item => {
            const rowClass = item.changed ? 'table-success' : '';
            const oldText = `${item.oldVal}${item.oldVal !== '-' ? ' ' + item.unit : ''}`.trim();
            const newText = `${item.newVal}${item.newVal !== '-' ? ' ' + item.unit : ''}`.trim();

            return `
                <tr class="${rowClass}">
                    <td class="fw-semibold">${item.label}</td>
                    <td>${oldText}</td>
                    <td>${newText}</td>
                    <td>${item.changed ? '<span class="badge bg-success">Changed</span>' : '<span class="badge bg-secondary">Same</span>'}</td>
                </tr>
            `;
        }).join('');

        return `
            <div class="text-start">
                <div class="mb-3">
                    <div><strong>Previous Poll:</strong> ${previousPolledAt || '-'}</div>
                    <div><strong>New Poll:</strong> ${newPolledAt || '-'}</div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Metric</th>
                                <th>Previous</th>
                                <th>New</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
            </div>
        `;
    }

    function renderHeader(ctx) {
        if (!el.header) return;

        if (ctx.state.loading) {
            html(el.header, `
                <div class="card border-0 shadow-sm ont-surface-card mb-3">
                    <div class="card-body">
                        <div class="placeholder w-25 mb-3"></div>
                        <div class="placeholder w-75 mb-3" style="height:42px;"></div>
                        <div class="row g-3">
                            <div class="col-md-12"><div class="placeholder w-100" style="height:100px;"></div></div>
                        </div>
                    </div>
                </div>
            `);
            return;
        }

        const s = getSummaryData(ctx.state);
        const pingMs = ctx.state.ping?.ms ?? null;
        const pingText = pingMs !== null ? `Online (${pingMs} ms)` : 'Checking...';

        let pingClass = 'badge rounded-pill bg-secondary-subtle text-muted';
        if (pingMs !== null) {
            pingClass = 'badge rounded-pill bg-success-subtle text-success';
            if (pingMs > 50) pingClass = 'badge rounded-pill bg-warning-subtle text-warning';
            if (pingMs > 150) pingClass = 'badge rounded-pill bg-danger-subtle text-danger';
        }

        html(el.header, `
            <div class="card border-0 shadow-sm ont-surface-card mb-3 ont-acs-hero">
                <div class="card-body">
                    <div class="row g-3 align-items-start">
                        <div class="col-12">
                            <div class="ont-acs-hero-title">Serial No.#</div>

                            <div class="d-flex align-items-center gap-2 flex-wrap mb-3">
                                <div class="ont-acs-hero-serial">${escape(s.serial)}</div>
                                ${statusBadge(s.inventoryState)}
                                <span class="${pingClass}" id="headerPingBadge">
                                    <i class="bi bi-wifi"></i> <span id="headerPingText">${escape(pingText)}</span>
                                </span>
                            </div>

                            <div class="ont-acs-pill-row">
                                <div class="ont-acs-pill">
                                    <div class="ont-acs-pill-icon"><i class="bi bi-cpu"></i></div>
                                    <div>
                                        <div class="ont-acs-pill-label">Manufacturer</div>
                                        <div class="ont-acs-pill-value">${escape(s.manufacturer)}</div>
                                    </div>
                                </div>

                                <div class="ont-acs-pill">
                                    <div class="ont-acs-pill-icon"><i class="bi bi-box"></i></div>
                                    <div>
                                        <div class="ont-acs-pill-label">Product Class</div>
                                        <div class="ont-acs-pill-value">${escape(s.productClass)}</div>
                                    </div>
                                </div>

                                <div class="ont-acs-pill">
                                    <div class="ont-acs-pill-icon"><i class="bi bi-broadcast"></i></div>
                                    <div>
                                        <div class="ont-acs-pill-label">Remote Connection Status</div>
                                        <div class="ont-acs-pill-value" id="headerAcsStatus">${escape(s.acsStatus)}</div>
                                    </div>
                                </div>

                                <div class="ont-acs-pill">
                                    <div class="ont-acs-pill-icon"><i class="bi bi-globe2"></i></div>
                                    <div>
                                        <div class="ont-acs-pill-label">Internet Connection Status</div>
                                        <div class="ont-acs-pill-value" id="headerWanStatus">${escape(s.wanStatus)}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `);
    }

    function renderSummaryTab(ctx) {
        const s = getSummaryData(ctx.state);
        const managementIp = getSafeDevice(ctx.state).management_ip || '-';
        const acsUrl = getParam(ctx.state, [
            'InternetGatewayDevice.ManagementServer.ConnectionRequestURL',
            'Device.ManagementServer.ConnectionRequestURL'
        ], '-');
        const optical = getOpticalData(ctx.state);

        const lastSeenText = s.lastSeen && s.lastSeen !== '-'
            ? `${String(s.acsStatus).toUpperCase() === 'ONLINE' ? 'Online' : 'Last seen'} • ${timeAgo(s.lastSeen)}`
            : '-';

        return `
            <div class="row g-3">
                <div class="col-12">
                    <div class="card border-0 shadow-sm ont-surface-card">
                        <div class="card-body">
                            <div class="ont-acs-section-title">
                                <div><h4>Device Details</h4></div>
                            </div>

                            <div class="acs-stats-row">
                                <div class="acs-stat-card">
                                    <div class="acs-stat-icon"><i class="bi bi-router"></i></div>
                                    <div class="acs-stat-text">
                                        <div class="acs-stat-label">WAN IP</div>
                                        <div class="acs-stat-value">${escape(s.wanIp || '-')}</div>
                                    </div>
                                </div>

                                <div class="acs-stat-card">
                                    <div class="acs-stat-icon"><i class="bi bi-hdd-network"></i></div>
                                    <div class="acs-stat-text">
                                        <div class="acs-stat-label">Management IP</div>
                                        <div class="acs-stat-value">${escape(managementIp)}</div>
                                    </div>
                                </div>
                            </div>

                            <div class="acs-overview-grid">
                                <div class="acs-overview-item">
                                    <div class="acs-overview-label">REMOTE MANAGEMENT URL</div>
                                    <div class="acs-overview-value text-break">${escape(acsUrl)}</div>
                                </div>

                                <div class="acs-overview-item">
                                    <div class="acs-overview-label">Uptime</div>
                                    <div class="acs-overview-value">${escape(s.uptimeSeconds ? formatSeconds(s.uptimeSeconds) : '-')}</div>
                                </div>

                                <div class="acs-overview-item">
                                    <div class="acs-overview-label">Last Seen</div>
                                    <div class="acs-overview-value text-success">${escape(lastSeenText)}</div>
                                </div>

                                <div class="acs-overview-item">
                                    <div class="acs-overview-label">Inventory Status</div>
                                    <div class="acs-overview-value">${escape(s.inventoryState || '-')}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="card border-0 shadow-sm ont-surface-card ont-optical-card">
                        <div class="card-body">
                            <div class="ont-acs-section-title ont-optical-header">
                                <div class="ont-optical-title-block">
                                    <h4>Optical Information</h4>
                                </div>
                                <div>
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="refreshOpticalBtn">
                                        <i class="bi bi-arrow-repeat me-1"></i>Refresh Optical Reading
                                    </button>
                                </div>
                            </div>

                            <div class="acs-stats-row">
                                <div class="acs-stat-card">
                                    <div class="acs-stat-icon"><i class="bi bi-arrow-down-circle"></i></div>
                                    <div class="acs-stat-text">
                                        <div class="acs-stat-label">ONT Rx Power</div>
                                        <div class="acs-stat-value" id="opticalRxWrap">
                                            <span id="opticalRx">${escape(formatOpticalValue(optical.rx, 'dBm'))}</span>
                                            <span id="opticalRxBadge"></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="acs-stat-card">
                                    <div class="acs-stat-icon"><i class="bi bi-arrow-up-circle"></i></div>
                                    <div class="acs-stat-text">
                                        <div class="acs-stat-label">ONT Tx Power</div>
                                        <div class="acs-stat-value" id="opticalTx">${escape(formatOpticalValue(optical.tx, 'dBm'))}</div>
                                    </div>
                                </div>
                            </div>

                            <div class="acs-overview-grid">
                                <div class="acs-overview-item">
                                    <div class="acs-overview-label">OLT Rx ONT Power</div>
                                    <div class="acs-overview-value" id="opticalOltRx">${escape(formatOpticalValue(optical.oltRx, 'dBm'))}</div>
                                </div>

                                <div class="acs-overview-item">
                                    <div class="acs-overview-label">Temperature</div>
                                    <div class="acs-overview-value" id="opticalTemp">${escape(formatOpticalValue(optical.temperature, '°C'))}</div>
                                </div>

                                <div class="acs-overview-item">
                                    <div class="acs-overview-label">Voltage</div>
                                    <div class="acs-overview-value" id="opticalVolt">${escape(formatOpticalValue(optical.voltage, 'V'))}</div>
                                </div>

                                <div class="acs-overview-item">
                                    <div class="acs-overview-label">Laser Bias Current</div>
                                    <div class="acs-overview-value" id="opticalCurrent">${escape(formatOpticalValue(optical.current, 'mA'))}</div>
                                </div>

                                <div class="acs-overview-item">
                                    <div class="acs-overview-label">Distance</div>
                                    <div class="acs-overview-value" id="opticalDistance">${escape(formatOpticalValue(optical.distance, 'm'))}</div>
                                </div>

                                <div class="acs-overview-item">
                                    <div class="acs-overview-label">Last Optical Poll</div>
                                    <div class="acs-overview-value" id="opticalPolledAt">${escape(String(optical.polledAt))}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    function renderWanTab(ctx) {
        const interfaces = collectWanInterfaces(ctx.state);

        const subscriberWans = interfaces.filter(isSubscriberBoundWan);
        const localWans = interfaces.filter(iface => !isSubscriberBoundWan(iface));

        const summary = getWanSummary(ctx.state);
        const provisioningLink = getProvisioningLink(ctx.state);

        const renderWanCard = (iface, idx, locked = false) => {
            const role = normalizeWanRole(iface);
            const source = normalizeWanSource(iface);

            return `
            <div class="ont-acs-interface-card ${locked ? 'ont-acs-interface-locked' : ''}">
                <div class="ont-acs-interface-head">
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                            <div class="ont-acs-interface-name">${escape(iface.title)}</div>
                            ${renderWanRoleBadge(role)}
                            ${renderWanSourceBadge(source)}
                            ${locked ? '<span class="badge bg-warning-subtle text-warning">Subscriber Bound</span>' : ''}
                        </div>
                        <div class="ont-acs-interface-sub">${escape(iface.subtitle)}</div>
                    </div>

                    <div class="ont-acs-actions-inline">
                        ${
                locked
                    ? `
                                    <a href="${provisioningLink}" class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-box-arrow-up-right"></i> Open Provisioning
                                    </a>
                                    <button type="button"
                                            class="btn btn-light border btn-sm"
                                            disabled
                                            title="This WAN is managed by subscriber provisioning">
                                        <i class="bi bi-lock"></i> Locked
                                    </button>
                                `
                    : `
                                    <button type="button"
                                            class="btn btn-light border btn-sm js-edit-wan"
                                            data-wan-index="${idx}"
                                            data-wan-name="${escape(iface.name || '')}">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                    <button type="button"
                                            class="btn btn-danger btn-sm js-delete-wan"
                                            data-wan-index="${idx}"
                                            data-wan-name="${escape(iface.name || '')}">
                                        Delete
                                    </button>
                                `
            }
                    </div>
                </div>

                <div class="ont-acs-info-grid">
                    <div class="ont-acs-info-item">
                        <div class="ont-acs-info-label">Name</div>
                        <div class="ont-acs-info-value">${escape(iface.name)}</div>
                    </div>

                    <div class="ont-acs-info-item">
                        <div class="ont-acs-info-label">Connection Type</div>
                        <div class="ont-acs-info-value">${escape(iface.connectionType)}</div>
                    </div>

                    ${role !== 'TR069' ? `
                        <div class="ont-acs-info-item">
                            <div class="ont-acs-info-label">Username</div>
                            <div class="ont-acs-info-value">${escape(iface.username)}</div>
                        </div>
                    ` : ''}

                    ${upper(iface.type) !== 'PPP' && role !== 'TR069' ? `
                        <div class="ont-acs-info-item">
                            <div class="ont-acs-info-label">Service List</div>
                            <div class="ont-acs-info-value">${escape(iface.serviceList)}</div>
                        </div>
                    ` : ''}

                    <div class="ont-acs-info-item">
                        <div class="ont-acs-info-label">VLAN ID</div>
                        <div class="ont-acs-info-value">${escape(iface.vlanId)}</div>
                    </div>

                    <div class="ont-acs-info-item">
                        <div class="ont-acs-info-label">Status</div>
                        <div class="ont-acs-info-value">${renderWanStatusBadge(iface.status)}</div>
                    </div>
                </div>

                <div class="ont-acs-subpanel">
                    <div class="ont-acs-subpanel-title">
                        <i class="bi bi-diagram-3"></i> Network Information
                    </div>

                    <div class="ont-acs-info-grid mb-0">
                        <div class="ont-acs-info-item">
                            <div class="ont-acs-info-label">IP Address</div>
                            <div class="ont-acs-info-value">${escape(iface.ipAddress)}</div>
                        </div>

                        <div class="ont-acs-info-item">
                            <div class="ont-acs-info-label">MAC Address</div>
                            <div class="ont-acs-info-value">${escape(iface.macAddress)}</div>
                        </div>

                        <div class="ont-acs-info-item">
                            <div class="ont-acs-info-label">Role</div>
                            <div class="ont-acs-info-value">${escape(role)}</div>
                        </div>

                        <div class="ont-acs-info-item">
                            <div class="ont-acs-info-label">Source</div>
                            <div class="ont-acs-info-value">${escape(source)}</div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        };

        return `
        <div class="card border-0 shadow-sm ont-surface-card">
            <div class="card-body">
                <div class="ont-acs-section-title">
                    <div>
                        <h4>WAN Configuration</h4>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="button" class="btn btn-light border" id="acsWanRefreshBtn">
                            <i class="bi bi-arrow-clockwise"></i> Refresh
                        </button>
                        <button type="button" class="btn btn-light border" id="acsAddWanBtn">
                            <i class="bi bi-plus"></i> Add WAN Interface
                        </button>
                    </div>
                </div>

                <div class="ont-acs-metric-grid mb-4">
                    <div class="ont-acs-metric-card">
                        <div class="ont-acs-metric-label">Total WANs</div>
                        <div class="ont-acs-metric-value">${escape(String(summary.total))}</div>
                    </div>
                    <div class="ont-acs-metric-card">
                        <div class="ont-acs-metric-label">Subscriber WANs</div>
                        <div class="ont-acs-metric-value">${escape(String(summary.subscriber))}</div>
                    </div>
                    <div class="ont-acs-metric-card">
                        <div class="ont-acs-metric-label">Management / Local</div>
                        <div class="ont-acs-metric-value">${escape(String(summary.local))}</div>
                    </div>
                    <div class="ont-acs-metric-card">
                        <div class="ont-acs-metric-label">Connected</div>
                        <div class="ont-acs-metric-value">${escape(String(summary.connected))}</div>
                    </div>
                </div>

                <div class="ont-acs-section-title">
                    <div>
                        <h5>Subscriber WAN Interfaces</h5>
                        <div class="text-muted small">PPPoE internet WANs managed by provisioning</div>
                    </div>
                </div>

                <div class="d-grid gap-3 mb-4">
                    ${
            subscriberWans.length
                ? subscriberWans.map((iface, idx) => renderWanCard(iface, idx, true)).join('')
                : `
                                <div class="text-muted small border rounded-3 p-3 bg-light">
                                    No subscriber-managed WAN interfaces found.
                                </div>
                            `
        }
                </div>

                <div class="ont-acs-section-title">
                    <div>
                        <h5>Management / Local WAN Interfaces</h5>
                        <div class="text-muted small">TR069 and device-level WAN interfaces editable from this page</div>
                    </div>
                </div>

                <div class="d-grid gap-3">
                    ${
            localWans.length
                ? localWans.map((iface, idx) => renderWanCard(iface, idx, false)).join('')
                : `
                                <div class="text-muted small border rounded-3 p-3 bg-light">
                                    No editable management/local WAN interfaces found.
                                </div>
                            `
        }
                </div>
            </div>
        </div>
    `;
    }

    function bindWanActions(ctx) {
        const addBtn = document.getElementById('acsAddWanBtn');
        const refreshBtn = document.getElementById('acsWanRefreshBtn');

        if (addBtn) {
            addBtn.onclick = () => openAddWanModal(ctx);
        }

        if (refreshBtn) {
            refreshBtn.onclick = () => manualRefresh(ctx);
        }

        document.querySelectorAll('.js-edit-wan').forEach((btn) => {
            btn.onclick = () => openEditWanModal(ctx, btn.dataset.wanName || '');
        });

        document.querySelectorAll('.js-delete-wan').forEach((btn) => {
            btn.onclick = () => confirmDeleteWan(ctx, btn.dataset.wanName || '');
        });
    }

    function normalizeWanRole(iface) {
        const name = upper(iface.name || '');
        const service = upper(iface.serviceList || '');
        const type = upper(iface.type || '');
        const subtitle = upper(iface.subtitle || '');

        if (
            service.includes('TR069') ||
            name.includes('TR069') ||
            subtitle.includes('TR069') ||
            String(iface.vlanId || '') === '25'
        ) {
            return 'TR069';
        }

        if (
            type === 'PPP' ||
            service.includes('INTERNET') ||
            name.includes('INTERNET') ||
            subtitle.includes('INTERNET')
        ) {
            return 'INTERNET';
        }

        if (service.includes('IPTV') || name.includes('IPTV')) {
            return 'IPTV';
        }

        return 'OTHER';
    }

    function normalizeWanSource(iface) {
        const role = normalizeWanRole(iface);
        if (role === 'INTERNET' && upper(iface.type || '') === 'PPP') {
            return 'PROVISIONING';
        }
        return 'LOCAL';
    }

    function isSubscriberBoundWan(iface) {
        return normalizeWanSource(iface) === 'PROVISIONING';
    }

    function isEditableWan(iface) {
        return !isSubscriberBoundWan(iface);
    }

    function renderWanRoleBadge(role) {
        const safe = upper(role || 'OTHER');

        if (safe === 'INTERNET') {
            return '<span class="badge bg-success-subtle text-success">Internet</span>';
        }
        if (safe === 'TR069') {
            return '<span class="badge bg-primary-subtle text-primary">Management</span>';
        }
        if (safe === 'IPTV') {
            return '<span class="badge bg-info-subtle text-info">IPTV</span>';
        }

        return '<span class="badge bg-secondary-subtle text-secondary">Other</span>';
    }

    function renderWanSourceBadge(source) {
        const safe = upper(source || 'LOCAL');

        if (safe === 'PROVISIONING') {
            return '<span class="badge bg-dark-subtle text-dark"><i class="bi bi-lock me-1"></i>Managed by Provisioning</span>';
        }

        return '<span class="badge bg-light text-dark border">Local / Editable</span>';
    }

    function renderWanStatusBadge(status) {
        const safe = upper(status || '-');

        if (['CONNECTED', 'ONLINE', 'UP'].includes(safe)) {
            return '<span class="badge bg-success-subtle text-success">Connected</span>';
        }

        if (['CONNECTING', 'PENDING', 'STALE'].includes(safe)) {
            return '<span class="badge bg-warning-subtle text-warning">Connecting</span>';
        }

        if (['DISCONNECTED', 'DOWN', 'OFFLINE', 'ERROR'].includes(safe)) {
            return '<span class="badge bg-danger-subtle text-danger">Disconnected</span>';
        }

        return `<span class="badge bg-light-subtle text-dark">${escape(status || '-')}</span>`;
    }

    function getProvisioningLink(state) {
        const d = getSafeDevice(state);
        const subscriberId = d.subscriber_id || d.linked_subscriber_id || '';
        if (!subscriberId) return '/service-provisioning';
        return `/service-provisioning?subscriber_id=${encodeURIComponent(subscriberId)}`;
    }

    function getWanSummary(state) {
        const interfaces = collectWanInterfaces(state);

        const subscriber = interfaces.filter(isSubscriberBoundWan);
        const local = interfaces.filter(iface => !isSubscriberBoundWan(iface));
        const connected = interfaces.filter(iface => ['CONNECTED', 'ONLINE', 'UP'].includes(upper(iface.status || '')));

        return {
            total: interfaces.length,
            subscriber: subscriber.length,
            local: local.length,
            connected: connected.length
        };
    }

    function renderWifiTab(ctx) {
        const interfaces = collectWifiInterfaces(ctx.state);

        const inferBand = (wifi) => {
            const explicitBand = getParam(ctx.state, [
                `InternetGatewayDevice.LANDevice.1.WLANConfiguration.${wifi.index}.X_HW_RFBand`
            ], '');

            const safeExplicit = upper(explicitBand);
            if (safeExplicit.includes('2.4')) return '2.4 GHz';
            if (safeExplicit.includes('5')) return '5 GHz';

            const standard = upper(wifi.standard || '');
            if (standard.includes('AC') || standard.includes('AX') || standard.includes('A')) return '5 GHz';

            return '2.4 GHz';
        };

        const getWifiAdvanced = (wifi) => {
            const idx = wifi.index;

            return {
                hideSsid: toBool(getParam(ctx.state, [
                    `InternetGatewayDevice.LANDevice.1.WLANConfiguration.${idx}.SSIDAdvertisementEnabled`
                ], 'true')) === false,

                radioEnabled: toBool(getParam(ctx.state, [
                    `InternetGatewayDevice.LANDevice.1.WLANConfiguration.${idx}.RadioEnabled`
                ], wifi.enabled ? 'true' : 'false')),

                autoChannel: toBool(getParam(ctx.state, [
                    `InternetGatewayDevice.LANDevice.1.WLANConfiguration.${idx}.AutoChannelEnable`
                ], 'true')),

                channel: getParam(ctx.state, [
                    `InternetGatewayDevice.LANDevice.1.WLANConfiguration.${idx}.Channel`
                ], wifi.channel || 'Auto'),

                txPower: getParam(ctx.state, [
                    `InternetGatewayDevice.LANDevice.1.WLANConfiguration.${idx}.TransmitPower`
                ], wifi.power || '100'),

                beaconType: getParam(ctx.state, [
                    `InternetGatewayDevice.LANDevice.1.WLANConfiguration.${idx}.BeaconType`
                ], wifi.security || 'WPAand11i'),

                encryption: getParam(ctx.state, [
                    `InternetGatewayDevice.LANDevice.1.WLANConfiguration.${idx}.IEEE11iEncryptionModes`,
                    `InternetGatewayDevice.LANDevice.1.WLANConfiguration.${idx}.WPAEncryptionModes`
                ], wifi.encryption || 'TKIPEncryption'),

                wpsEnabled: toBool(getParam(ctx.state, [
                    `InternetGatewayDevice.LANDevice.1.WLANConfiguration.${idx}.WPS.Enable`
                ], 'false')),

                wmmEnabled: toBool(getParam(ctx.state, [
                    `InternetGatewayDevice.LANDevice.1.WLANConfiguration.${idx}.WMMEnable`
                ], 'true')),

                macFilterEnabled: toBool(getParam(ctx.state, [
                    `InternetGatewayDevice.LANDevice.1.WLANConfiguration.${idx}.MACAddressControlEnabled`
                ], 'false')),

                currentStandard: getParam(ctx.state, [
                    `InternetGatewayDevice.LANDevice.1.WLANConfiguration.${idx}.Standard`,
                    `InternetGatewayDevice.LANDevice.1.WLANConfiguration.${idx}.X_HW_Standard`
                ], wifi.standard || 'Not configured'),

                band: inferBand(wifi)
            };
        };

        return `
        <div class="card border-0 shadow-sm ont-surface-card">
            <div class="card-body">
                <div class="ont-acs-section-title">
                    <div>
                        <h4>WiFi Configuration</h4>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="button" class="btn btn-light border" id="acsWifiRefreshBtn">
                            <i class="bi bi-arrow-clockwise"></i> Refresh WiFi
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="acsWifiViewClientsBtn">
                            <i class="bi bi-people"></i> View Clients
                        </button>
                    </div>
                </div>

                <div class="ont-acs-metric-grid mb-4">
                    <div class="ont-acs-metric-card">
                        <div class="ont-acs-metric-label">Total WLANs</div>
                        <div class="ont-acs-metric-value">${escape(String(interfaces.length))}</div>
                    </div>
                    <div class="ont-acs-metric-card">
                        <div class="ont-acs-metric-label">Active WLANs</div>
                        <div class="ont-acs-metric-value">${escape(String(interfaces.filter(w => w.enabled).length))}</div>
                    </div>
                    <div class="ont-acs-metric-card">
                        <div class="ont-acs-metric-label">Connected Clients</div>
                        <div class="ont-acs-metric-value">${escape(String(interfaces.reduce((sum, w) => sum + (parseInt(w.clients || '0', 10) || 0), 0)))}</div>
                    </div>
                    <div class="ont-acs-metric-card">
                        <div class="ont-acs-metric-label">Security</div>
                        <div class="ont-acs-metric-value">${escape(interfaces[0]?.security || '-')}</div>
                    </div>
                </div>

                <div class="d-grid gap-3">
                    ${interfaces.map((wifi, idx) => {
            const advanced = getWifiAdvanced(wifi);
            const isPrimaryEditable = idx === 0;

            return `
                            <div class="ont-acs-bigpanel">
                                <div class="ont-acs-wifi-head">
                                    <div class="ont-acs-wifi-left">
                                        <div class="ont-acs-wifi-icon">
                                            <i class="bi bi-wifi"></i>
                                        </div>
                                        <div>
                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                <div class="ont-acs-wifi-title">${escape(wifi.title)}</div>
                                                ${statusBadge(wifi.enabled ? 'ACTIVE' : 'DISABLED')}
                                                <span class="badge bg-primary-subtle text-primary">${escape(advanced.band)}</span>
                                                <span class="badge bg-light text-dark border">${idx === 0 ? 'MAIN' : 'ADDITIONAL'}</span>
                                            </div>
                                            <div class="ont-acs-wifi-sub">${escape(wifi.ssid)}</div>
                                        </div>
                                    </div>

                                    <div class="ont-acs-btn-row">
                                        ${
                isPrimaryEditable
                    ? `
                                                    <button type="button" class="btn btn-primary" id="acsWifiSaveBtn">
                                                        <i class="bi bi-save"></i> Save Changes
                                                    </button>
                                                    <button type="button" class="btn btn-light border" id="acsWifiCancelBtn">
                                                        <i class="bi bi-x"></i> Cancel
                                                    </button>
                                                `
                    : `
                                                    <button type="button" class="btn btn-light border" disabled>
                                                        <i class="bi bi-lock"></i> Read Only
                                                    </button>
                                                `
            }
                                    </div>
                                </div>

                                <div class="ont-acs-section-title mt-3 mb-2">
                                    <div><h5>Basic Settings</h5></div>
                                </div>

                                <div class="ont-acs-info-grid">
                                    <div class="ont-acs-info-item">
                                        <div class="ont-acs-info-label">Network Name (SSID)</div>
                                        ${
                isPrimaryEditable
                    ? `<input type="text" class="form-control" id="acsWifiSsidInput" value="${escape(wifi.ssid)}">`
                    : `<div class="ont-acs-info-value">${escape(wifi.ssid)}</div>`
            }
                                    </div>

                                    <div class="ont-acs-info-item">
                                        <div class="ont-acs-info-label">Security Key</div>
                                        ${
                isPrimaryEditable
                    ? `<input type="password" class="form-control" id="acsWifiPasswordInput" value="" placeholder="Leave blank to keep current password" autocomplete="new-password">`
                    : `<div class="ont-acs-info-value">${escape(wifi.password ? '********' : 'Not configured')}</div>`
            }
                                    </div>
                                </div>

                                ${
                isPrimaryEditable
                    ? `
                                            <div class="ont-acs-enable-box mb-3">
                                                <div class="row g-3">
                                                    <div class="col-md-4">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="acsWifiEnableInput" ${wifi.enabled ? 'checked' : ''}>
                                                            <label class="form-check-label fw-semibold" for="acsWifiEnableInput">
                                                                Enable WiFi Network
                                                            </label>
                                                            <div class="small text-muted">Allow devices to connect to this SSID</div>
                                                        </div>
                                                    </div>

                                                    <div class="col-md-4">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="acsWifiRadioEnableInput" ${advanced.radioEnabled ? 'checked' : ''}>
                                                            <label class="form-check-label fw-semibold" for="acsWifiRadioEnableInput">
                                                                Radio Enabled
                                                            </label>
                                                            <div class="small text-muted">Enable the wireless radio itself</div>
                                                        </div>
                                                    </div>

                                                    <div class="col-md-4">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="acsWifiHideSsidInput" ${advanced.hideSsid ? 'checked' : ''}>
                                                            <label class="form-check-label fw-semibold" for="acsWifiHideSsidInput">
                                                                Hide SSID
                                                            </label>
                                                            <div class="small text-muted">Disable SSID advertisement</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        `
                    : ''
            }

                                <div class="ont-acs-section-title mt-3 mb-2">
                                    <div><h5>Radio Settings</h5></div>
                                </div>

                                ${
                isPrimaryEditable
                    ? `
                                            <div class="row g-3 mb-3">
                                                <div class="col-md-3">
                                                    <div class="form-check mt-4">
                                                        <input class="form-check-input" type="checkbox" id="acsWifiAutoChannelInput" ${advanced.autoChannel ? 'checked' : ''}>
                                                        <label class="form-check-label fw-semibold" for="acsWifiAutoChannelInput">
                                                            Auto Channel
                                                        </label>
                                                    </div>
                                                </div>

                                                <div class="col-md-3">
                                                    <label class="form-label">Channel</label>
                                                    <input type="number" class="form-control" id="acsWifiChannelInput" value="${escape(String(advanced.channel))}" ${advanced.autoChannel ? 'disabled' : ''}>
                                                </div>

                                                <div class="col-md-3">
                                                    <label class="form-label">Transmit Power</label>
                                                    <input type="number" class="form-control" id="acsWifiPowerInput" value="${escape(String(advanced.txPower))}">
                                                </div>

                                                <div class="col-md-3">
                                                    <label class="form-label">Standard</label>
                                                    <input type="text" class="form-control" value="${escape(String(advanced.currentStandard))}" disabled>
                                                </div>
                                            </div>
                                        `
                    : `
                                            <div class="ont-acs-metric-grid mb-3">
                                                <div class="ont-acs-metric-card">
                                                    <div class="ont-acs-metric-label">Channel</div>
                                                    <div class="ont-acs-metric-value">${escape(String(advanced.channel))}</div>
                                                </div>
                                                <div class="ont-acs-metric-card">
                                                    <div class="ont-acs-metric-label">Standard</div>
                                                    <div class="ont-acs-metric-value">${escape(String(advanced.currentStandard))}</div>
                                                </div>
                                                <div class="ont-acs-metric-card">
                                                    <div class="ont-acs-metric-label">Power</div>
                                                    <div class="ont-acs-metric-value">${escape(String(advanced.txPower))}</div>
                                                </div>
                                                <div class="ont-acs-metric-card">
                                                    <div class="ont-acs-metric-label">Band</div>
                                                    <div class="ont-acs-metric-value">${escape(String(advanced.band))}</div>
                                                </div>
                                            </div>
                                        `
            }

                                <div class="ont-acs-section-title mt-3 mb-2">
                                    <div><h5>Security & Features</h5></div>
                                </div>

                                ${
                isPrimaryEditable
                    ? `
                                            <div class="row g-3 mb-3">
                                                <div class="col-md-4">
                                                    <label class="form-label">Security Mode</label>
                                                    <select class="form-select" id="acsWifiBeaconTypeInput">
                                                        <option value="WPAand11i" ${String(advanced.beaconType) === 'WPAand11i' ? 'selected' : ''}>WPA/WPA2 Mixed</option>
                                                        <option value="11i" ${String(advanced.beaconType) === '11i' ? 'selected' : ''}>WPA2</option>
                                                        <option value="Basic" ${String(advanced.beaconType) === 'Basic' ? 'selected' : ''}>Open</option>
                                                    </select>
                                                </div>

                                                <div class="col-md-4">
                                                    <label class="form-label">Encryption</label>
                                                    <select class="form-select" id="acsWifiEncryptionInput">
                                                        <option value="TKIPEncryption" ${String(advanced.encryption) === 'TKIPEncryption' ? 'selected' : ''}>TKIP</option>
                                                        <option value="AESEncryption" ${String(advanced.encryption) === 'AESEncryption' ? 'selected' : ''}>AES</option>
                                                        <option value="TKIPandAESEncryption" ${String(advanced.encryption) === 'TKIPandAESEncryption' ? 'selected' : ''}>TKIP/AES Mixed</option>
                                                    </select>
                                                </div>

                                                <div class="col-md-4">
                                                    <label class="form-label">BSSID</label>
                                                    <input type="text" class="form-control" value="${escape(wifi.bssid)}" disabled>
                                                </div>
                                            </div>

                                            <div class="ont-acs-enable-box mb-3">
                                                <div class="row g-3">
                                                    <div class="col-md-4">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="acsWifiWpsInput" ${advanced.wpsEnabled ? 'checked' : ''}>
                                                            <label class="form-check-label fw-semibold" for="acsWifiWpsInput">
                                                                Enable WPS
                                                            </label>
                                                        </div>
                                                    </div>

                                                    <div class="col-md-4">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="acsWifiWmmInput" ${advanced.wmmEnabled ? 'checked' : ''}>
                                                            <label class="form-check-label fw-semibold" for="acsWifiWmmInput">
                                                                Enable WMM
                                                            </label>
                                                        </div>
                                                    </div>

                                                    <div class="col-md-4">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="acsWifiMacFilterInput" ${advanced.macFilterEnabled ? 'checked' : ''}>
                                                            <label class="form-check-label fw-semibold" for="acsWifiMacFilterInput">
                                                                Enable MAC Filter
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        `
                    : `
                                            <div class="ont-acs-info-grid mb-3">
                                                <div class="ont-acs-info-item">
                                                    <div class="ont-acs-info-label">Security</div>
                                                    <div class="ont-acs-info-value">${escape(wifi.security)}</div>
                                                </div>
                                                <div class="ont-acs-info-item">
                                                    <div class="ont-acs-info-label">Encryption</div>
                                                    <div class="ont-acs-info-value">${escape(wifi.encryption)}</div>
                                                </div>
                                                <div class="ont-acs-info-item">
                                                    <div class="ont-acs-info-label">BSSID</div>
                                                    <div class="ont-acs-info-value">${escape(wifi.bssid)}</div>
                                                </div>
                                                <div class="ont-acs-info-item">
                                                    <div class="ont-acs-info-label">MAC Address</div>
                                                    <div class="ont-acs-info-value">${escape(wifi.macAddress)}</div>
                                                </div>
                                            </div>
                                        `
            }

                                <div class="ont-acs-section-title mt-3 mb-2">
                                    <div><h5>Live Status</h5></div>
                                </div>

                                <div class="ont-acs-metric-grid">
                                    <div class="ont-acs-metric-card">
                                        <div class="ont-acs-metric-label">Connected Clients</div>
                                        <div class="ont-acs-metric-value">${escape(String(wifi.clients))}</div>
                                    </div>
                                    <div class="ont-acs-metric-card">
                                        <div class="ont-acs-metric-label">Band</div>
                                        <div class="ont-acs-metric-value">${escape(String(advanced.band))}</div>
                                    </div>
                                    <div class="ont-acs-metric-card">
                                        <div class="ont-acs-metric-label">Security</div>
                                        <div class="ont-acs-metric-value">${escape(String(advanced.beaconType))}</div>
                                    </div>
                                    <div class="ont-acs-metric-card">
                                        <div class="ont-acs-metric-label">Encryption</div>
                                        <div class="ont-acs-metric-value">${escape(String(advanced.encryption))}</div>
                                    </div>
                                </div>
                            </div>
                        `;
        }).join('')}
                </div>
            </div>
        </div>
    `;
    }

    function renderSystemTab(ctx) {
        const firmware = getParam(ctx.state, [
            'InternetGatewayDevice.DeviceInfo.SoftwareVersion',
            'Device.DeviceInfo.SoftwareVersion'
        ], '-');

        const hardware = getParam(ctx.state, [
            'InternetGatewayDevice.DeviceInfo.HardwareVersion',
            'Device.DeviceInfo.HardwareVersion'
        ], '-');

        const uptime = getParam(ctx.state, [
            'InternetGatewayDevice.DeviceInfo.UpTime',
            'Device.DeviceInfo.UpTime'
        ], null);

        const lastBoot = getParam(ctx.state, [
            'InternetGatewayDevice.DeviceInfo.X_HW_LastBootReason',
            'Device.DeviceInfo.X_HW_LastBootReason'
        ], 'Recently informed');

        return `
            <div class="card border-0 shadow-sm ont-surface-card">
                <div class="card-body">
                    <div class="ont-acs-section-title">
                        <div><h4>System Information</h4></div>
                        <button type="button" class="btn btn-light border" id="acsSystemRefreshBtn">
                            <i class="bi bi-arrow-clockwise"></i> Refresh
                        </button>
                    </div>

                    <div class="ont-acs-metric-grid mb-4">
                        <div class="ont-acs-metric-card"><div class="ont-acs-metric-label">Hardware Version</div><div class="ont-acs-metric-value">${escape(hardware)}</div></div>
                        <div class="ont-acs-metric-card"><div class="ont-acs-metric-label">Software Version</div><div class="ont-acs-metric-value">${escape(firmware)}</div></div>
                        <div class="ont-acs-metric-card"><div class="ont-acs-metric-label">Uptime</div><div class="ont-acs-metric-value">${escape(uptime ? formatSeconds(uptime) : '-')}</div></div>
                        <div class="ont-acs-metric-card"><div class="ont-acs-metric-label">Last Boot</div><div class="ont-acs-metric-value">${escape(lastBoot)}</div></div>
                    </div>

                    <div class="ont-acs-section-title">
                        <div><h5>System Actions</h5></div>
                    </div>

                    <div class="ont-acs-actions-grid">
                        <div class="ont-acs-primary-card">
                            <div class="d-flex justify-content-between gap-3 flex-wrap mb-3">
                                <div>
                                    <div class="ont-acs-action-card-title">Reboot Device</div>
                                    <div class="ont-acs-action-card-sub">Restart the device and all services</div>
                                </div>
                                <div>
                                    <button type="button" class="btn btn-primary" id="acsSystemRebootBtn">Reboot</button>
                                </div>
                            </div>

                            <ul class="ont-acs-action-card-list">
                                <li>Device will be unavailable for 2–3 minutes</li>
                                <li>All connected clients will be disconnected</li>
                                <li>Configuration will be preserved</li>
                            </ul>
                        </div>

                        <div class="ont-acs-danger-card">
                            <div class="d-flex justify-content-between gap-3 flex-wrap mb-3">
                                <div>
                                    <div class="ont-acs-action-card-title">Factory Reset</div>
                                    <div class="ont-acs-action-card-sub">Reset device to factory defaults</div>
                                </div>
                                <div>
                                    <button type="button" class="btn btn-danger" id="acsSystemFactoryResetBtn">Reset</button>
                                </div>
                            </div>

                            <ul class="ont-acs-action-card-list">
                                <li>All settings will be erased</li>
                                <li>Device will return to factory configuration</li>
                                <li>This action cannot be undone</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    function renderClientsTab(ctx) {
        const clients = collectClients(ctx.state);

        return `
            <div class="card border-0 shadow-sm ont-surface-card">
                <div class="card-body">
                    <div class="ont-acs-section-title">
                        <div>
                            <h4>Connected Clients</h4>
                            <div class="text-muted small">${clients.length} client(s) detected</div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table align-middle mb-0 ont-acs-clients-table">
                            <thead>
                                <tr>
                                    <th>Host Name</th>
                                    <th>IP Address</th>
                                    <th>MAC Address</th>
                                    <th>Interface</th>
                                    <th>Active</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${clients.length ? clients.map(client => `
                                    <tr>
                                        <td>${escape(client.hostName)}</td>
                                        <td>${escape(client.ipAddress)}</td>
                                        <td>${escape(client.macAddress)}</td>
                                        <td>${escape(client.interfaceType)}</td>
                                        <td>${statusBadge(client.active ? 'ACTIVE' : 'INACTIVE')}</td>
                                    </tr>
                                `).join('') : `
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-5">No client data found.</td>
                                    </tr>
                                `}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
    }

    function renderLoading() {
        return `
            <div class="card border-0 shadow-sm ont-surface-card">
                <div class="card-body">
                    <div class="placeholder-glow">
                        <span class="placeholder col-12 mb-2"></span>
                        <span class="placeholder col-10 mb-2"></span>
                        <span class="placeholder col-8"></span>
                    </div>
                </div>
            </div>
        `;
    }

    function renderActiveTab(ctx) {
        switch (ctx.state.activeTab) {
            case 'wan':
                return renderWanTab(ctx);
            case 'wifi':
                return renderWifiTab(ctx);
            case 'system':
                return renderSystemTab(ctx);
            case 'clients':
                return renderClientsTab(ctx);
            case 'summary':
            default:
                return renderSummaryTab(ctx);
        }
    }

    function renderContent(ctx) {
        if (!el.content) return;

        if (ctx.state.loading) {
            html(el.content, renderLoading());
            return;
        }

        html(el.content, renderActiveTab(ctx));
        bindDynamicEvents(ctx);
        bindWifiActions(ctx);
        bindSystemActions(ctx);
    }

    function bindTabs(ctx) {
        el.tabs?.querySelectorAll('[data-acs-tab]').forEach(tab => {
            tab.classList.toggle('active', tab.dataset.acsTab === ctx.state.activeTab);
            tab.onclick = (e) => {
                e.preventDefault();
                ctx.patch({ activeTab: tab.dataset.acsTab });
                bindTabs(ctx);
            };
        });
    }

    function bindTopActions(ctx) {
        el.pingBtn = document.getElementById('acsPingBtn');
        el.refreshBtn = document.getElementById('acsDetailRefreshBtn');

        el.pingBtn?.removeEventListener('click', el.pingHandler);
        el.refreshBtn?.removeEventListener('click', el.refreshHandler);

        el.pingHandler = () => pingDevice(ctx, true);
        el.refreshHandler = () => manualRefresh(ctx);

        el.pingBtn?.addEventListener('click', el.pingHandler);
        el.refreshBtn?.addEventListener('click', el.refreshHandler);
    }

    function bindWifiActions(ctx) {
        const saveBtn = document.getElementById('acsWifiSaveBtn');
        const cancelBtn = document.getElementById('acsWifiCancelBtn');
        const refreshBtn = document.getElementById('acsWifiRefreshBtn');
        const viewClientsBtn = document.getElementById('acsWifiViewClientsBtn');
        const autoChannelInput = document.getElementById('acsWifiAutoChannelInput');
        const channelInput = document.getElementById('acsWifiChannelInput');

        if (saveBtn) {
            saveBtn.onclick = () => saveWifiConfig(ctx);
        }

        if (cancelBtn) {
            cancelBtn.onclick = () => renderContent(ctx);
        }

        if (refreshBtn) {
            refreshBtn.onclick = async () => {
                try {
                    const btnHtml = refreshBtn.innerHTML;
                    refreshBtn.disabled = true;
                    refreshBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Refreshing...';

                    try {
                        await api.urlEncoded('/api/v1/ont-devices/acs/refresh', {
                            deviceId: ctx.state.deviceId
                        });
                    } catch (e) {
                        // ignore, still reload UI
                    }

                    setTimeout(async () => {
                        try {
                            await reloadView(ctx);
                        } catch (err) {
                            ui.toast('error', err.message || 'Failed to refresh WiFi.');
                        } finally {
                            refreshBtn.disabled = false;
                            refreshBtn.innerHTML = btnHtml;
                        }
                    }, 1500);
                } catch (err) {
                    refreshBtn.disabled = false;
                    refreshBtn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Refresh WiFi';
                    ui.toast('error', err.message || 'Failed to refresh WiFi.');
                }
            };
        }

        if (viewClientsBtn) {
            viewClientsBtn.onclick = () => {
                ctx.patch({ activeTab: 'clients' });
                bindTabs(ctx);
            };
        }

        if (autoChannelInput && channelInput) {
            autoChannelInput.onchange = () => {
                channelInput.disabled = autoChannelInput.checked;
            };
        }
    }

    function bindSystemActions(ctx) {
        const refreshBtn = document.getElementById('acsSystemRefreshBtn');
        const rebootBtn = document.getElementById('acsSystemRebootBtn');
        const resetBtn = document.getElementById('acsSystemFactoryResetBtn');

        if (refreshBtn) refreshBtn.onclick = () => refreshDeviceTask(ctx);
        if (rebootBtn) rebootBtn.onclick = () => rebootDevice(ctx);
        if (resetBtn) resetBtn.onclick = () => factoryResetDevice(ctx);
    }

    function bindDynamicEvents(ctx) {
        const refreshOpticalBtn = document.getElementById('refreshOpticalBtn');
        if (refreshOpticalBtn) {
            refreshOpticalBtn.onclick = () => refreshOptical(ctx);
        }

        if (ctx.state.activeTab === 'wan') {
            bindWanActions(ctx);
        }
    }

    function getEditableWanByName(state, wanName) {
        const interfaces = collectWanInterfaces(state);
        return interfaces.find((iface) => {
            return String(iface.name || '') === String(wanName || '') && isEditableWan(iface);
        }) || null;
    }

    function getWanTypeOptionsHtml(selected = '') {
        const value = String(selected || '').toUpperCase();
        return `
        <option value="DHCP" ${value === 'DHCP' ? 'selected' : ''}>DHCP</option>
        <option value="STATIC" ${value === 'STATIC' ? 'selected' : ''}>Static IP</option>
    `;
    }

    function getWanRoleOptionsHtml(selected = '') {
        const value = String(selected || '').toUpperCase();
        return `
        <option value="TR069" ${value === 'TR069' ? 'selected' : ''}>TR069 / Management</option>
        <option value="OTHER" ${value === 'OTHER' ? 'selected' : ''}>Other</option>
        <option value="IPTV" ${value === 'IPTV' ? 'selected' : ''}>IPTV</option>
    `;
    }

    function getDefaultServiceListByRole(role) {
        const safe = upper(role || '');
        if (safe === 'TR069') return 'TR069';
        if (safe === 'IPTV') return 'IPTV';
        return 'OTHER';
    }

    function inferEditWanType(iface) {
        const connectionType = upper(iface.connectionType || '');
        const ifaceType = upper(iface.type || '');

        if (connectionType.includes('STATIC')) return 'STATIC';
        if (ifaceType === 'IP' && String(iface.ipAddress || '').trim() && String(iface.ipAddress) !== '0.0.0.0') {
            return 'DHCP';
        }
        return 'DHCP';
    }

    function buildWanFormHtml({
                                  mode = 'add',
                                  values = {}
                              } = {}) {
        const type = upper(values.type || 'DHCP');
        const role = upper(values.role || 'TR069');
        const staticVisible = type === 'STATIC';

        return `
        <form id="acsWanForm" class="text-start">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">WAN Name</label>
                    <input type="text" class="form-control" name="name" value="${escape(values.name || '')}" placeholder="e.g. OLT_C_TR069_DHCP_WAN">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Type</label>
                    <select class="form-select" name="type" id="acsWanTypeSelect">
                        ${getWanTypeOptionsHtml(type)}
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Role</label>
                    <select class="form-select" name="role" id="acsWanRoleSelect">
                        ${getWanRoleOptionsHtml(role)}
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">VLAN ID</label>
                    <input type="number" class="form-control" name="vlan_id" value="${escape(values.vlan_id || '')}" placeholder="e.g. 25">
                </div>

                <div class="col-md-12">
                    <label class="form-label">Service List</label>
                    <input type="text" class="form-control" name="service_list" id="acsWanServiceListInput" value="${escape(values.service_list || '')}" placeholder="e.g. TR069">
                </div>

                <div class="col-md-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="enabled" id="acsWanEnabledInput" ${values.enabled === false ? '' : 'checked'}>
                        <label class="form-check-label" for="acsWanEnabledInput">
                            Enable WAN Interface
                        </label>
                    </div>
                </div>
            </div>

            <div id="acsWanStaticFields" class="mt-3 ${staticVisible ? '' : 'd-none'}">
                <div class="border rounded-3 p-3 bg-light">
                    <div class="fw-semibold mb-2">Static IP Settings</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">IP Address</label>
                            <input type="text" class="form-control" name="ip_address" value="${escape(values.ip_address || '')}" placeholder="e.g. 10.0.25.10">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Subnet Mask</label>
                            <input type="text" class="form-control" name="subnet_mask" value="${escape(values.subnet_mask || '')}" placeholder="e.g. 255.255.255.0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Gateway</label>
                            <input type="text" class="form-control" name="gateway" value="${escape(values.gateway || '')}" placeholder="e.g. 10.0.25.1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">DNS</label>
                            <input type="text" class="form-control" name="dns" value="${escape(values.dns || '')}" placeholder="e.g. 8.8.8.8,1.1.1.1">
                        </div>
                    </div>
                </div>
            </div>

            ${
            mode === 'edit'
                ? `<input type="hidden" name="original_name" value="${escape(values.original_name || values.name || '')}">`
                : ''
        }
        </form>
    `;
    }

    function wireWanFormBehavior(root = document) {
        const typeSelect = root.querySelector('#acsWanTypeSelect');
        const roleSelect = root.querySelector('#acsWanRoleSelect');
        const serviceListInput = root.querySelector('#acsWanServiceListInput');
        const staticFields = root.querySelector('#acsWanStaticFields');

        const toggleStatic = () => {
            const isStatic = upper(typeSelect?.value || '') === 'STATIC';
            staticFields?.classList.toggle('d-none', !isStatic);
        };

        const syncServiceList = () => {
            if (!serviceListInput || !roleSelect) return;

            const current = String(serviceListInput.value || '').trim();
            const defaults = ['TR069', 'IPTV', 'OTHER'];

            if (!current || defaults.includes(upper(current))) {
                serviceListInput.value = getDefaultServiceListByRole(roleSelect.value);
            }
        };

        typeSelect?.addEventListener('change', toggleStatic);
        roleSelect?.addEventListener('change', syncServiceList);

        toggleStatic();
    }

    async function createWanInterface(ctx, payload) {
        return api.urlEncoded('/api/v1/ont-devices/acs/wan/create', {
            deviceId: ctx.state.deviceId,
            ...payload
        });
    }

    async function updateWanInterface(ctx, payload) {
        return api.urlEncoded('/api/v1/ont-devices/acs/wan/update', {
            deviceId: ctx.state.deviceId,
            ...payload
        });
    }

    async function deleteWanInterface(ctx, payload) {
        return api.urlEncoded('/api/v1/ont-devices/acs/wan/delete', {
            deviceId: ctx.state.deviceId,
            ...payload
        });
    }

    async function openAddWanModal(ctx) {
        const result = await ui.swal({
            title: 'Add WAN Interface',
            html: buildWanFormHtml({
                mode: 'add',
                values: {
                    type: 'DHCP',
                    role: 'TR069',
                    service_list: 'TR069',
                    enabled: true
                }
            }),
            width: 760,
            showCancelButton: true,
            confirmButtonText: 'Create WAN',
            didOpen: () => {
                wireWanFormBehavior(document);
            },
            preConfirm: async () => {
                const formEl = document.getElementById('acsWanForm');
                const data = forms.toObject(formEl);

                const payload = {
                    name: String(data.name || '').trim(),
                    type: upper(data.type || 'DHCP'),
                    role: upper(data.role || 'TR069'),
                    vlan_id: String(data.vlan_id || '').trim(),
                    service_list: String(data.service_list || '').trim(),
                    enabled: data.enabled ? '1' : '0',
                    ip_address: String(data.ip_address || '').trim(),
                    subnet_mask: String(data.subnet_mask || '').trim(),
                    gateway: String(data.gateway || '').trim(),
                    dns: String(data.dns || '').trim()
                };

                if (!payload.name) {
                    Swal.showValidationMessage('WAN Name is required.');
                    return false;
                }

                if (!payload.vlan_id) {
                    Swal.showValidationMessage('VLAN ID is required.');
                    return false;
                }

                if (payload.type === 'STATIC') {
                    if (!payload.ip_address || !payload.subnet_mask || !payload.gateway) {
                        Swal.showValidationMessage('Static WAN requires IP Address, Subnet Mask, and Gateway.');
                        return false;
                    }
                }

                try {
                    return await createWanInterface(ctx, payload);
                } catch (err) {
                    Swal.showValidationMessage(err.message || 'Failed to create WAN interface.');
                    return false;
                }
            }
        });

        if (!result.isConfirmed) return;

        ui.toast('success', result.value?.message || 'WAN interface created.');
        await manualRefresh(ctx);
    }

    async function openEditWanModal(ctx, wanName) {
        const iface = getEditableWanByName(ctx.state, wanName);
        if (!iface) {
            ui.toast('error', 'Editable WAN interface not found.');
            return;
        }

        const inferredRole = normalizeWanRole(iface);
        const inferredType = inferEditWanType(iface);

        const result = await ui.swal({
            title: `Edit WAN: ${escape(iface.name || '-')}`,
            html: buildWanFormHtml({
                mode: 'edit',
                values: {
                    original_name: iface.name || '',
                    name: iface.name || '',
                    type: inferredType,
                    role: inferredRole,
                    vlan_id: iface.vlanId || '',
                    service_list: iface.serviceList || getDefaultServiceListByRole(inferredRole),
                    enabled: !['DOWN', 'DISCONNECTED', 'OFFLINE'].includes(upper(iface.status || '')),
                    ip_address: inferredType === 'STATIC' ? iface.ipAddress || '' : '',
                    subnet_mask: '',
                    gateway: '',
                    dns: ''
                }
            }),
            width: 760,
            showCancelButton: true,
            confirmButtonText: 'Save Changes',
            didOpen: () => {
                wireWanFormBehavior(document);
            },
            preConfirm: async () => {
                const formEl = document.getElementById('acsWanForm');
                const data = forms.toObject(formEl);

                const payload = {
                    original_name: String(data.original_name || '').trim(),
                    name: String(data.name || '').trim(),
                    type: upper(data.type || 'DHCP'),
                    role: upper(data.role || 'TR069'),
                    vlan_id: String(data.vlan_id || '').trim(),
                    service_list: String(data.service_list || '').trim(),
                    enabled: data.enabled ? '1' : '0',
                    ip_address: String(data.ip_address || '').trim(),
                    subnet_mask: String(data.subnet_mask || '').trim(),
                    gateway: String(data.gateway || '').trim(),
                    dns: String(data.dns || '').trim()
                };

                if (!payload.original_name) {
                    Swal.showValidationMessage('Original WAN identifier is missing.');
                    return false;
                }

                if (!payload.name) {
                    Swal.showValidationMessage('WAN Name is required.');
                    return false;
                }

                if (!payload.vlan_id) {
                    Swal.showValidationMessage('VLAN ID is required.');
                    return false;
                }

                if (payload.type === 'STATIC') {
                    if (!payload.ip_address || !payload.subnet_mask || !payload.gateway) {
                        Swal.showValidationMessage('Static WAN requires IP Address, Subnet Mask, and Gateway.');
                        return false;
                    }
                }

                try {
                    return await updateWanInterface(ctx, payload);
                } catch (err) {
                    Swal.showValidationMessage(err.message || 'Failed to update WAN interface.');
                    return false;
                }
            }
        });

        if (!result.isConfirmed) return;

        ui.toast('success', result.value?.message || 'WAN interface updated.');
        await manualRefresh(ctx);
    }

    async function confirmDeleteWan(ctx, wanName) {
        const iface = getEditableWanByName(ctx.state, wanName);
        if (!iface) {
            ui.toast('error', 'Editable WAN interface not found.');
            return;
        }

        const confirm = await ui.confirm({
            icon: 'warning',
            title: 'Delete WAN Interface?',
            text: `Delete "${iface.name || wanName}"? This action cannot be undone.`,
            confirmButtonText: 'Delete'
        });

        if (!confirm.isConfirmed) return;

        try {
            ui.loading('Deleting WAN interface...');

            const res = await deleteWanInterface(ctx, {
                name: iface.name || wanName,
                role: normalizeWanRole(iface),
                type: inferEditWanType(iface)
            });

            ui.closeLoading();
            ui.toast('success', res.message || 'WAN interface deleted.');
            await manualRefresh(ctx);
        } catch (err) {
            ui.closeLoading();
            ui.toast('error', err.message || 'Failed to delete WAN interface.');
        }
    }
    function updateHeaderLiveUI(ctx) {
        const s = getSummaryData(ctx.state);

        const acsNode = document.getElementById('headerAcsStatus');
        if (acsNode) acsNode.textContent = s.acsStatus || '-';

        const wanNode = document.getElementById('headerWanStatus');
        if (wanNode) wanNode.textContent = s.wanStatus || '-';

        const pingBadge = document.getElementById('headerPingBadge');
        const pingTextNode = document.getElementById('headerPingText');

        const pingMs = ctx.state.ping?.ms ?? null;
        const pingText = pingMs !== null ? `Online (${pingMs} ms)` : 'Checking...';

        let pingClass = 'badge rounded-pill bg-secondary-subtle text-muted';
        if (pingMs !== null) {
            pingClass = 'badge rounded-pill bg-success-subtle text-success';
            if (pingMs > 50) pingClass = 'badge rounded-pill bg-warning-subtle text-warning';
            if (pingMs > 150) pingClass = 'badge rounded-pill bg-danger-subtle text-danger';
        }

        if (pingBadge) pingBadge.className = pingClass;
        if (pingTextNode) pingTextNode.textContent = pingText;
    }

    function updateRxBadge(value) {
        const badge = document.getElementById('opticalRxBadge');
        if (!badge) return;

        const num = Number(value);

        if (value === '-' || value === null || value === undefined || Number.isNaN(num)) {
            badge.innerHTML = '';
            return;
        }

        if (num >= -20) {
            badge.innerHTML = '<span class="badge bg-success ms-2">GOOD</span>';
        } else if (num >= -25) {
            badge.innerHTML = '<span class="badge bg-warning text-dark ms-2">WEAK</span>';
        } else {
            badge.innerHTML = '<span class="badge bg-danger ms-2">CRITICAL</span>';
        }
    }


    function updateOpticalUI(ctx) {
        const o = getOpticalData(ctx.state);

        const setNodeText = (id, value, unit = '', type = null, isText = false) => {
            const node = document.getElementById(id);
            if (!node) return;

            if (isText) {
                node.textContent = value && value !== '-' ? String(value) : '-';
                return;
            }

            const num = Number(value);
            const isEmpty = value === '-' || value === null || value === undefined || Number.isNaN(num);

            if (isEmpty) {
                node.textContent = 'Not available';
                node.classList.remove('optical-good', 'optical-warning', 'optical-critical');
                return;
            }

            node.textContent = `${value} ${unit}`.trim();
            node.classList.remove('optical-good', 'optical-warning', 'optical-critical');

            if (type === 'rx') {
                if (num >= -20) node.classList.add('optical-good');
                else if (num >= -25) node.classList.add('optical-warning');
                else node.classList.add('optical-critical');
            }

            if (type === 'tx') {
                if (num <= 3) node.classList.add('optical-good');
                else if (num <= 5) node.classList.add('optical-warning');
                else node.classList.add('optical-critical');
            }
        };

        setNodeText('opticalRx', o.rx, 'dBm', 'rx');
        setNodeText('opticalTx', o.tx, 'dBm', 'tx');
        setNodeText('opticalOltRx', o.oltRx, 'dBm');
        setNodeText('opticalTemp', o.temperature, '°C');
        setNodeText('opticalVolt', o.voltage, 'V');
        setNodeText('opticalCurrent', o.current, 'mA');
        setNodeText('opticalDistance', o.distance, 'm');
        setNodeText('opticalPolledAt', o.polledAt, '', null, true);

        updateRxBadge(o.rx);
    }

    async function refreshOptical(ctx) {
        const btn = document.getElementById('refreshOpticalBtn');
        const originalHtml = btn ? btn.innerHTML : '';
        const previousSnapshot = getCurrentOpticalSnapshot(ctx.state);
        const previousPolledAt = previousSnapshot.polledAt;
        const device = getSafeDevice(ctx.state);

        try {
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span>Refreshing...`;
            }

            const response = await api.urlEncoded(
                `/api/v1/ont-devices/acs/device/${encodeURIComponent(ctx.state.deviceId)}/optical`,
                {}
            );
            const result = response?.data ?? response;

            if (!(result && result.optical)) {
                throw {
                    isOpticalError: true,
                    message: 'Failed to refresh optical values.',
                    details: result || {}
                };
            }

            const optical = result.optical;
            const newPolledAtRaw = new Date().toISOString();

            ctx.patch({
                optical: {
                    rx_power_dbm: optical.rx_power_dbm,
                    tx_power_dbm: optical.tx_power_dbm,
                    olt_rx_ont_power_dbm: optical.olt_rx_ont_power_dbm,
                    temperature_c: optical.temperature_c,
                    voltage_v: optical.voltage_v,
                    laser_bias_current_ma: optical.laser_bias_current_ma,
                    distance_m: optical.distance_m,
                    polled_at: newPolledAtRaw
                }
            });

            updateOpticalUI(ctx);

            const newSnapshot = getCurrentOpticalSnapshot(ctx.state);
            const comparison = compareOpticalSnapshots(previousSnapshot, newSnapshot);

            await ui.swal({
                icon: comparison.changed ? 'success' : 'info',
                title: comparison.changed ? 'Optical Values Updated' : 'No Changes Detected',
                html: buildOpticalComparisonHtml(comparison, previousPolledAt, newSnapshot.polledAt),
                width: 760,
                confirmButtonText: 'Close'
            });

        } catch (error) {
            console.error('refreshOptical() failed:', error);

            const errMessage = error?.message || 'Unable to refresh optical values.';
            const details = error?.details || {};
            const serial = device.serial_number || '-';
            const inventoryId = device.id || device.inventory_id || '-';
            const model = device.model || '-';
            const vendor = device.vendor || '-';

            const mappingRows = [
                { label: 'Serial Number', value: serial },
                { label: 'Inventory ID', value: inventoryId },
                { label: 'Vendor', value: vendor },
                { label: 'Model', value: model },
                { label: 'OLT ID', value: details.olt_id ?? '-' },
                { label: 'Frame', value: details.frame ?? '-' },
                { label: 'Slot', value: details.slot ?? '-' },
                { label: 'Port', value: details.port ?? '-' },
                { label: 'ONT ID', value: details.ont_id ?? '-' }
            ];

            const missingFields = [];
            if (details.olt_id == null || details.olt_id === '') missingFields.push('OLT ID');
            if (details.frame == null || details.frame === '') missingFields.push('Frame');
            if (details.slot == null || details.slot === '') missingFields.push('Slot');
            if (details.port == null || details.port === '') missingFields.push('Port');
            if (details.ont_id == null || details.ont_id === '') missingFields.push('ONT ID');

            const mappingHtml = mappingRows.map(row => `
                <tr>
                    <td class="fw-semibold">${escape(row.label)}</td>
                    <td>${escape(String(row.value))}</td>
                </tr>
            `).join('');

            const missingHtml = missingFields.length
                ? `
                    <div class="alert alert-warning text-start py-2 px-3 mb-3">
                        <div class="fw-semibold mb-1">Missing Required Mapping</div>
                        <div>${escape(missingFields.join(', '))}</div>
                    </div>
                `
                : '';

            const hintHtml = `
                <div class="alert alert-info text-start py-2 px-3 mb-0">
                    <div class="fw-semibold mb-1">What to check</div>
                    <ul class="mb-0 ps-3">
                        <li>Make sure this ONT is linked to the correct OLT inventory/mapping.</li>
                        <li>Confirm <strong>frame / slot / port / ont_id</strong> are saved in the ONT inventory record.</li>
                        <li>Verify the serial is matched to the correct subscriber ONT.</li>
                    </ul>
                </div>
            `;

            await ui.swal({
                icon: 'error',
                title: 'Optical Refresh Failed',
                html: `
                    <div class="text-start">
                        <div class="mb-3">${escape(errMessage)}</div>
                        ${missingHtml}
                        <div class="table-responsive mb-3">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Field</th>
                                        <th>Current Value</th>
                                    </tr>
                                </thead>
                                <tbody>${mappingHtml}</tbody>
                            </table>
                        </div>
                        ${hintHtml}
                    </div>
                `,
                width: 760,
                confirmButtonText: 'OK'
            });
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }
    }

    async function refreshDeviceTask(ctx) {
        try {
            ui.loading('Sending refresh task...');
            const res = await api.urlEncoded('/api/v1/ont-devices/acs/refresh', {
                deviceId: ctx.state.deviceId
            });
            ui.closeLoading();
            ui.toast('success', res.message || 'Refresh task sent.');
        } catch (err) {
            ui.closeLoading();
            ui.toast('error', err.message || 'Failed to send refresh task.');
        }
    }

    async function pingDevice(ctx, showPopup = true) {
        try {
            const res = await api.urlEncoded('/api/v1/ont-devices/acs/ping', {
                deviceId: ctx.state.deviceId
            });

            const data = res.data || {};
            ctx.patch({
                ping: {
                    ms: data.ping_ms ?? null,
                    host: data.host ?? null
                }
            });

            updateHeaderLiveUI(ctx);

            if (showPopup) {
                const host = ctx.state.ping.host || '-';
                const ms = ctx.state.ping.ms;

                await ui.swal({
                    icon: ms !== null ? 'success' : 'warning',
                    title: 'Ping Result',
                    html: `
                        <div class="text-center">
                            Pinging ${escape(host)}: <strong>${ms !== null ? `${escape(String(ms))} ms` : 'Timeout'}</strong>
                        </div>
                    `
                });
            }
        } catch (err) {
            ui.toast('error', err.message || 'Ping failed.');
        }
    }

    async function rebootDevice(ctx) {
        const confirmed = await ui.confirm({
            icon: 'warning',
            title: 'Reboot device?',
            text: 'This will send a reboot command to the ONT.',
            confirmButtonText: 'Yes, reboot'
        });

        if (!confirmed.isConfirmed) return;

        try {
            const res = await api.urlEncoded('/api/v1/ont-devices/acs/reboot', {
                deviceId: ctx.state.deviceId
            });
            ui.toast('success', res.message || 'Reboot command sent.');
        } catch (err) {
            ui.toast('error', err.message || 'Reboot failed.');
        }
    }

    async function factoryResetDevice(ctx) {
        const confirmed = await ui.confirm({
            icon: 'warning',
            title: 'Factory reset device?',
            text: 'This will erase the ONT configuration.',
            confirmButtonText: 'Yes, reset'
        });

        if (!confirmed.isConfirmed) return;

        try {
            const res = await api.urlEncoded('/api/v1/ont-devices/acs/factory-reset', {
                deviceId: ctx.state.deviceId
            });
            ui.toast('success', res.message || 'Factory reset scheduled.');
        } catch (err) {
            ui.toast('error', err.message || 'Factory reset failed.');
        }
    }

    function applyWifiOptimisticState() {
        const ssidInput = document.getElementById('acsWifiSsidInput');
        const hideInput = document.getElementById('acsWifiHideSsidInput');
        const enableInput = document.getElementById('acsWifiEnableInput');
        const radioInput = document.getElementById('acsWifiRadioEnableInput');
        const autoChannelInput = document.getElementById('acsWifiAutoChannelInput');
        const channelInput = document.getElementById('acsWifiChannelInput');

        const wlanSub = document.querySelector('.ont-acs-wifi-sub');
        const hideLabel = document.querySelector('label[for="acsWifiHideSsidInput"]');
        const saveBtn = document.getElementById('acsWifiSaveBtn');

        if (ssidInput && wlanSub) {
            wlanSub.textContent = ssidInput.value.trim() || '-';
        }

        if (hideInput && hideLabel) {
            hideLabel.innerHTML = hideInput.checked
                ? 'Hide SSID <span class="text-warning small ms-1">(Applying...)</span>'
                : 'Hide SSID';
        }

        const disableWhileApplying = [
            ssidInput,
            hideInput,
            enableInput,
            radioInput,
            autoChannelInput,
            channelInput,
            document.getElementById('acsWifiPasswordInput'),
            document.getElementById('acsWifiPowerInput'),
            document.getElementById('acsWifiBeaconTypeInput'),
            document.getElementById('acsWifiEncryptionInput'),
            document.getElementById('acsWifiWpsInput'),
            document.getElementById('acsWifiWmmInput'),
            document.getElementById('acsWifiMacFilterInput')
        ].filter(Boolean);

        disableWhileApplying.forEach(el => {
            el.dataset.wasDisabled = el.disabled ? '1' : '0';
            el.disabled = true;
        });

        if (saveBtn) {
            saveBtn.dataset.originalHtml = saveBtn.innerHTML;
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Applying...';
        }

        return () => {
            disableWhileApplying.forEach(el => {
                el.disabled = el.dataset.wasDisabled === '1';
                delete el.dataset.wasDisabled;
            });

            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = saveBtn.dataset.originalHtml || '<i class="bi bi-save"></i> Save Changes';
            }

            if (hideLabel) {
                hideLabel.textContent = 'Hide SSID';
            }
        };
    }

    async function saveWifiConfig(ctx) {
        const ssid = document.getElementById('acsWifiSsidInput')?.value?.trim() || '';
        const password = document.getElementById('acsWifiPasswordInput')?.value?.trim() || '';
        const enable = document.getElementById('acsWifiEnableInput')?.checked ? '1' : '0';
        const radioEnabled = document.getElementById('acsWifiRadioEnableInput')?.checked ? '1' : '0';
        const hideSsid = document.getElementById('acsWifiHideSsidInput')?.checked ? '1' : '0';
        const autoChannel = document.getElementById('acsWifiAutoChannelInput')?.checked ? '1' : '0';
        const channel = document.getElementById('acsWifiChannelInput')?.value?.trim() || '';
        const txPower = document.getElementById('acsWifiPowerInput')?.value?.trim() || '';
        const beaconType = document.getElementById('acsWifiBeaconTypeInput')?.value?.trim() || '';
        const encryption = document.getElementById('acsWifiEncryptionInput')?.value?.trim() || '';
        const wpsEnable = document.getElementById('acsWifiWpsInput')?.checked ? '1' : '0';
        const wmmEnable = document.getElementById('acsWifiWmmInput')?.checked ? '1' : '0';
        const macFilterEnable = document.getElementById('acsWifiMacFilterInput')?.checked ? '1' : '0';

        if (!ssid) {
            ui.toast('error', 'SSID is required.');
            return;
        }

        if (autoChannel === '0' && !channel) {
            ui.toast('error', 'Channel is required when Auto Channel is disabled.');
            return;
        }

        const restoreUi = applyWifiOptimisticState();

        try {
            const res = await api.urlEncoded('/api/v1/ont-devices/acs/wifi-config', {
                deviceId: ctx.state.deviceId,
                ssid,
                password,
                enable,
                radio_enabled: radioEnabled,
                hide_ssid: hideSsid,
                auto_channel: autoChannel,
                channel,
                tx_power: txPower,
                beacon_type: beaconType,
                encryption,
                wps_enable: wpsEnable,
                wmm_enable: wmmEnable,
                mac_filter_enable: macFilterEnable
            });

            ui.toast('success', res.message || 'WiFi settings sent to device.');

            // Give ONT + ACS time to apply and update
            setTimeout(async () => {
                try {
                    await api.urlEncoded('/api/v1/ont-devices/acs/refresh', {
                        deviceId: ctx.state.deviceId
                    });
                } catch (e) {
                    // ignore refresh errors, still reload view
                }

                // Wait a little more so refreshed values are visible
                setTimeout(async () => {
                    try {
                        await reloadView(ctx);
                    } catch (e) {
                        ui.toast('error', e.message || 'Failed to reload WiFi data.');
                    } finally {
                        restoreUi();
                    }
                }, 1800);
            }, 1200);

        } catch (err) {
            restoreUi();
            ui.toast('error', err.message || 'WiFi configuration failed.');
        }
    }

    async function loadDevice(ctx) {
        ctx.patch({
            loading: true,
            device: null,
            parameters: {},
            optical: null
        });

        try {
            const deviceData = await api.get(
                `/api/v1/ont-devices/acs/device/${encodeURIComponent(ctx.state.deviceId)}`
            );

            const paramsData = await api.get(
                `/api/v1/ont-devices/acs/device/${encodeURIComponent(ctx.state.deviceId)}/parameters`
            );

            let cachedOptical = null;
            try {
                cachedOptical = await api.get(
                    `/api/v1/ont-devices/acs/device/${encodeURIComponent(ctx.state.deviceId)}/cached-optical`
                );
            } catch (err) {
                console.warn('Cached optical API failed:', err);
            }

            ctx.patch({
                device: deviceData || null,
                parameters: paramsData || {},
                optical: cachedOptical ? {
                    rx_power_dbm: cachedOptical.last_rx_power_dbm,
                    tx_power_dbm: cachedOptical.last_tx_power_dbm,
                    olt_rx_ont_power_dbm: cachedOptical.last_olt_rx_ont_power_dbm,
                    temperature_c: cachedOptical.last_temperature_c,
                    voltage_v: cachedOptical.last_voltage_v,
                    laser_bias_current_ma: cachedOptical.last_laser_bias_current_ma,
                    distance_m: cachedOptical.last_distance_m,
                    polled_at: cachedOptical.last_optical_polled_at
                } : null,
                loading: false
            });

        } catch (error) {
            console.error('loadDevice() failed:', error);
            ctx.patch({
                loading: false,
                device: null,
                parameters: {},
                optical: null
            });
            throw error;
        }
    }

    async function reloadView(ctx, showToastMessage = '') {
        await loadDevice(ctx);
        bindTabs(ctx);
        bindTopActions(ctx);

        if (showToastMessage) {
            ui.toast('success', showToastMessage);
        }

        if (ctx.state.activeTab === 'summary') {
            updateOpticalUI(ctx);
        }
        updateHeaderLiveUI(ctx);
    }

    async function manualRefresh(ctx) {
        try {
            await reloadView(ctx, 'Device details refreshed.');
        } catch (err) {
            ui.toast('error', err.message || 'Failed to refresh device details.');
        }
    }

    async function autoPollRefresh(ctx) {
        try {
            const deviceData = await api.get(
                `/api/v1/ont-devices/acs/device/${encodeURIComponent(ctx.state.deviceId)}`
            );

            ctx.patch({
                device: deviceData || ctx.state.device
            });

            updateHeaderLiveUI(ctx);
        } catch (err) {
            console.error('ACS background poll failed:', err);
        }
    }

    function startAutoPolling(ctx) {
        stopAutoPolling();

        autoPoller = poller.create({
            interval: 15000,
            task: () => autoPollRefresh(ctx),
            onError: (err) => console.error('Auto poll error:', err)
        });
    }

    function stopAutoPolling() {
        autoPoller?.destroy?.();
        autoPoller = null;
    }

    function startPingPolling(ctx) {
        stopPingPolling();

        pingPoller = poller.create({
            interval: 10000,
            task: () => pingDevice(ctx, false),
            onError: (err) => console.error('Ping poll error:', err)
        });
    }

    function stopPingPolling() {
        pingPoller?.destroy?.();
        pingPoller = null;
    }

    function createLiveCtx() {
        return {
            get state() {
                return appPage.store.get();
            },
            patch: appPage.store.patch,
            set: appPage.store.set,
            reset: appPage.store.reset
        };
    }

    appPage.start().then(async () => {
        const ctx = createLiveCtx();

        try {
            bindTabs(ctx);
            bindTopActions(ctx);

            if (ctx.state.activeTab === 'summary') {
                updateOpticalUI(ctx);
            }

            await pingDevice(ctx, false);
            startAutoPolling(ctx);
            startPingPolling(ctx);
        } catch (err) {
            html(el.header, '');
            html(el.content, `
            <div class="card border-0 shadow-sm ont-surface-card">
                <div class="card-body">
                    <div class="alert alert-danger mb-0">${escape(err.message || 'Failed to load ACS device details.')}</div>
                </div>
            </div>
        `);
        }
    }).catch((err) => {
        html(el.header, '');
        html(el.content, `
        <div class="card border-0 shadow-sm ont-surface-card">
            <div class="card-body">
                <div class="alert alert-danger mb-0">${escape(err.message || 'Failed to load ACS device details.')}</div>
            </div>
        </div>
    `);
    });

    window.addEventListener('beforeunload', () => {
        stopAutoPolling();
        stopPingPolling();
    });
});
