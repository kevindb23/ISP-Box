document.addEventListener('DOMContentLoaded', () => {
    const app = document.getElementById('ontDevicesApp');
    if (!app || !window.NX) return;

    const {
        api,
        ui,
        dom,
        util,
        render,
        actions,
        clipboard,
        tooltip,
        storage,
        forms,
        modal,
        page,
        datatable
    } = window.NX;

    const { $, $$, html, text } = dom;
    const { escape, upper, safeArray, formatDateTime } = util;

    const STORAGE_KEY = 'nexusbox_ont_devices_ui_state_v10';
    const persisted = storage.get(STORAGE_KEY, {}) || {};

    const TABLES = {
        inventory: null,
        discovery: null,
        remoteManagement: null
    };

    const el = {};

    const stateDefaults = {
        tab: persisted.tab || app.dataset.initialTab || 'inventory',
        search: persisted.search || '',
        inventoryFilter: persisted.inventoryFilter || 'ALL',
        discoveryFilter: persisted.discoveryFilter || 'ALL',
        acsFilter: persisted.acsFilter || 'ALL',

        inventory: [],
        discovery: [],
        acs: [],
        olts: [],
        subscribers: [],
        selectedDiscoveryOltId: null,

        selectedInventoryId: null,
        selectedDiscoverySerial: null,
        selectedAcsDeviceId: null,

        discoverySelections: [],

        loading: {
            inventory: true,
            discovery: true,
            acs: true
        },

        flash: {
            inventorySerial: null,
            discoverySerial: null,
            acsDeviceId: null
        }
    };

    const appPage = page.create({
        storageKey: null,
        state: stateDefaults,

        init(ctx) {
            cacheDom();
            bindStaticEvents(ctx);
        },

        async load(ctx) {
            await loadAll(ctx);
        },

        render(ctx) {
            persistUiState(ctx.state);
            syncUrl(ctx.state);
            renderPage(ctx);
            tooltip.refresh(document);
        }
    });

    function cacheDom() {
        Object.assign(el, {
            subtitle: $('#ontPageSubtitle'),
            headerActions: $('#ontHeaderActions'),
            toolbarArea: $('#ontToolbarArea'),
            contentArea: $('#ontContentArea'),

            ontForm: $('#ontForm'),
            ontModal: $('#ontModal'),
            ontModalTitle: $('#ontModalTitle'),
            ontModalSubtitle: $('#ontModalSubtitle'),

            ontSerialInput: $('#ontSerialInput'),
            ontVendorInput: $('#ontVendorInput'),
            ontModelInput: $('#ontModelInput'),
            ontSubscriberIdInput: $('#ontSubscriberIdInput'),
            ontSubscriberSearchInput: $('#ontSubscriberSearchInput'),
            ontSubscriberMatchText: $('#ontSubscriberMatchText'),
            ontVendorOptions: $('#ontVendorOptions'),
            ontModelOptions: $('#ontModelOptions'),
            ontSubscriberOptions: $('#ontSubscriberOptions'),
            ontStatusInput: $('#ontStatusInput'),

            inventoryViewModal: $('#inventoryViewModal'),
            inventoryViewSubtitle: $('#inventoryViewSubtitle'),
            viewInventorySerial: $('#viewInventorySerial'),
            viewInventoryStatus: $('#viewInventoryStatus'),
            viewInventoryVendor: $('#viewInventoryVendor'),
            viewInventoryModel: $('#viewInventoryModel'),
            viewInventoryEquipmentId: $('#viewInventoryEquipmentId'),
            viewInventorySubscriberId: $('#viewInventorySubscriberId'),
            viewInventorySubscriberName: $('#viewInventorySubscriberName'),
            viewInventoryCreatedAt: $('#viewInventoryCreatedAt'),
            inventoryCopySerialBtn: $('#inventoryCopySerialBtn'),
            inventoryEditFromViewBtn: $('#inventoryEditFromViewBtn'),

            discoveryViewModal: $('#discoveryViewModal'),
            discoveryViewSubtitle: $('#discoveryViewSubtitle'),
            viewDiscoverySerial: $('#viewDiscoverySerial'),
            viewDiscoveryInventoryState: $('#viewDiscoveryInventoryState'),
            viewDiscoveryModel: $('#viewDiscoveryModel'),
            viewDiscoveryVendor: $('#viewDiscoveryVendor'),
            viewDiscoveryVendorCode: $('#viewDiscoveryVendorCode'),
            viewDiscoveryFsp: $('#viewDiscoveryFsp'),
            viewDiscoveryPhysical: $('#viewDiscoveryPhysical'),
            viewDiscoveryLastSeen: $('#viewDiscoveryLastSeen'),
            viewDiscoveryAutofindTime: $('#viewDiscoveryAutofindTime'),
            discoveryModalAddBtn: $('#discoveryModalAddBtn'),
            discoveryCopySerialBtn: $('#discoveryCopySerialBtn'),

            acsViewModal: $('#acsViewModal'),
            acsViewSubtitle: $('#acsViewSubtitle'),
            viewAcsDeviceId: $('#viewAcsDeviceId'),
            viewAcsStatus: $('#viewAcsStatus'),
            viewAcsSerial: $('#viewAcsSerial'),
            viewAcsWanIp: $('#viewAcsWanIp'),
            viewAcsVendor: $('#viewAcsVendor'),
            viewAcsModel: $('#viewAcsModel'),
            viewAcsFirmware: $('#viewAcsFirmware'),
            viewAcsUptime: $('#viewAcsUptime'),
            viewAcsLastSeen: $('#viewAcsLastSeen'),
            viewAcsInventoryMatch: $('#viewAcsInventoryMatch'),
            viewAcsInventoryInfo: $('#viewAcsInventoryInfo'),
            acsCopyDeviceIdBtn: $('#acsCopyDeviceIdBtn'),
            acsCopySerialBtn: $('#acsCopySerialBtn'),
            acsCopyWanIpBtn: $('#acsCopyWanIpBtn'),
            acsOpenInventoryBtn: $('#acsOpenInventoryBtn')
        });
    }

    function persistUiState(state) {
        storage.set(STORAGE_KEY, {
            tab: state.tab,
            search: state.search,
            inventoryFilter: state.inventoryFilter,
            discoveryFilter: state.discoveryFilter,
            acsFilter: state.acsFilter
        });
    }

    function syncUrl(state) {
        const params = new URLSearchParams();
        params.set('tab', state.tab);
        history.replaceState({}, '', `/ont-devices?${params.toString()}`);
    }

    function setFlash(ctx, patch) {
        ctx.patch({
            flash: {
                ...ctx.state.flash,
                ...patch
            }
        });
    }

    function clearFlashLater(ctx, key, ms = 1800) {
        setTimeout(() => {
            ctx.patch({
                flash: {
                    ...ctx.state.flash,
                    [key]: null
                }
            });
        }, ms);
    }

    function bindStaticEvents(ctx) {
        if (el.ontForm) {
            el.ontForm.addEventListener('submit', (e) => submitOntForm(ctx, e));
        }

        el.ontSubscriberSearchInput?.addEventListener('input', util.debounce(() => {
            syncSubscriberSelection(ctx);
        }, 120));

        el.discoveryModalAddBtn?.addEventListener('click', async () => {
            if (!ctx.state.selectedDiscoverySerial) return;
            await addDiscoveredOnt(ctx, ctx.state.selectedDiscoverySerial);
        });

        el.discoveryCopySerialBtn?.addEventListener('click', () => {
            clipboard.copy(findDiscoveryBySerial(ctx.state, ctx.state.selectedDiscoverySerial)?.serial_number, 'Serial');
        });

        el.inventoryCopySerialBtn?.addEventListener('click', () => {
            clipboard.copy(findInventoryById(ctx.state, ctx.state.selectedInventoryId)?.serial_number, 'Serial');
        });

        el.inventoryEditFromViewBtn?.addEventListener('click', () => {
            if (!ctx.state.selectedInventoryId) return;
            modal.close(el.inventoryViewModal);
            openEditOntModal(ctx, ctx.state.selectedInventoryId);
        });

        el.acsCopyDeviceIdBtn?.addEventListener('click', () => {
            clipboard.copy(findAcsById(ctx.state, ctx.state.selectedAcsDeviceId)?.id, 'Device ID');
        });

        el.acsCopySerialBtn?.addEventListener('click', () => {
            clipboard.copy(findAcsById(ctx.state, ctx.state.selectedAcsDeviceId)?.serial_number, 'Serial');
        });

        el.acsCopyWanIpBtn?.addEventListener('click', () => {
            clipboard.copy(findAcsById(ctx.state, ctx.state.selectedAcsDeviceId)?.wan_ip, 'WAN IP');
        });

        el.acsOpenInventoryBtn?.addEventListener('click', () => {
            const row = findAcsById(ctx.state, ctx.state.selectedAcsDeviceId);
            if (!row) return;

            const matchedInventory = row.inventory_id
                ? findInventoryById(ctx.state, row.inventory_id)
                : findInventoryBySerial(ctx.state, row.serial_number);

            if (!matchedInventory) return;

            modal.close(el.acsViewModal);
            ctx.patch({ tab: 'inventory' });
            openInventoryViewModal(ctx, matchedInventory.id);
        });
    }

    function extractApiPayload(response) {
        if (Array.isArray(response)) return response;
        if (!response || typeof response !== 'object') return [];

        if (Array.isArray(response.data)) return response.data;
        if (Array.isArray(response.items)) return response.items;
        if (Array.isArray(response.rows)) return response.rows;

        if (response.data && typeof response.data === 'object') {
            if (Array.isArray(response.data.items)) return response.data.items;
            if (Array.isArray(response.data.rows)) return response.data.rows;
            if (Array.isArray(response.data.data)) return response.data.data;
        }

        return [];
    }

    function enrichDiscoveryRows(discoveryRows, inventoryMap) {
        return safeArray(discoveryRows).map((row) => {
            const serial = String(row.serial_number || '').toUpperCase();
            const inventoryId = inventoryMap.get(serial) || null;
            return {
                ...row,
                inventory_id: inventoryId,
                inventory_state: row.inventory_state || (inventoryId ? 'KNOWN' : 'ROGUE')
            };
        });
    }

    async function loadAll(ctx) {
        ctx.patch({
            loading: {
                inventory: true,
                discovery: true,
                acs: true
            }
        });

        const [inventoryRes, discoveryRes, acsRes, acsDevicesRes, oltsRes, subscribersRes] = await Promise.all([
            api.get('/api/v1/ont-devices/inventory').catch(() => []),
            api.get('/api/v1/ont-devices/discovery').catch(() => []),
            api.get('/api/v1/ont-devices/acs').catch(() => []),
            Promise.resolve([]),
            api.get('/api/v1/olt-management/devices').catch(() => []),
            api.get('/api/v1/ont-devices/subscribers').catch(() => [])
        ]);

        const inventory = safeArray(extractApiPayload(inventoryRes));
        const inventoryMap = new Map(
            inventory.map((item) => [String(item.serial_number || '').toUpperCase(), item.id])
        );

        const discovery = enrichDiscoveryRows(
            extractApiPayload(discoveryRes),
            inventoryMap
        );

        const acsCurrentRows = safeArray(extractApiPayload(acsRes));
        const acsRemoteRows = safeArray(extractApiPayload(acsDevicesRes));
        const olts = safeArray(extractApiPayload(oltsRes));
        const subscribers = safeArray(extractApiPayload(subscribersRes));

        const mergedById = new Map();

        acsCurrentRows.forEach((item) => {
            const normalized = normalizeAcsRow(item, inventoryMap);
            if (!normalized.id) return;
            mergedById.set(String(normalized.id), normalized);
        });

        acsRemoteRows.forEach((item) => {
            const normalized = normalizeAcsRow(item, inventoryMap);
            if (!normalized.id) return;
            const key = String(normalized.id);
            const existing = mergedById.get(key) || {};
            mergedById.set(key, normalizeAcsRow({ ...existing, ...normalized }, inventoryMap));
        });

        ctx.patch({
            inventory,
            discovery,
            acs: Array.from(mergedById.values()),
            olts,
            subscribers,
            selectedDiscoveryOltId: ctx.state.selectedDiscoveryOltId || (olts[0] ? Number(olts[0].id) : null),
            loading: {
                inventory: false,
                discovery: false,
                acs: false
            }
        });
    }

    async function refreshStateAndRender(ctx) {
        try {
            await loadAll(ctx);
        } catch (err) {
            ui.toast('error', err.message || 'Failed to load ONT module.');
        }
    }

    function renderPage(ctx) {
        bindTabLinks(ctx);
        renderHeaderActions(ctx);
        renderToolbar(ctx);
        renderContent(ctx);
    }

    function bindTabLinks(ctx) {
        $$('[data-tab-link]').forEach((link) => {
            link.classList.toggle('active', link.dataset.tabLink === ctx.state.tab);
            link.onclick = (e) => {
                e.preventDefault();
                ctx.patch({
                    tab: link.dataset.tabLink,
                    search: ''
                });
            };
        });
    }

    function renderToolbar(ctx) {
        const { tab, search } = ctx.state;

        const placeholder = tab === 'inventory'
            ? 'Search serial / model / vendor / status'
            : tab === 'discovery'
                ? 'Search serial / model / vendor / F/S/P'
                : 'Search serial / model / vendor / ACS IP / Management IP';

        const exportLabel = tab === 'inventory'
            ? 'Export Inventory'
            : tab === 'discovery'
                ? 'Export Discovery'
                : 'Export ACS';

        html(el.toolbarArea, `
        <div class="ont-toolbar-grid">
            <div class="ont-toolbar-search">
                <div class="ont-search-wrap">
                    <i class="bi bi-search ont-search-icon"></i>
                    <input
                        type="text"
                        id="ontSearchInput"
                        class="form-control ont-toolbar-control ont-search-control"
                        placeholder="${escape(placeholder)}"
                        value="${escape(search)}"
                    >
                </div>
            </div>

            <div class="ont-toolbar-meta">
                <span class="ont-toolbar-note">
                    Last loaded: ${escape(new Date().toLocaleTimeString())}
                </span>

                <div class="ont-toolbar-actions">
                    <button type="button" class="btn btn-outline-secondary ont-toolbar-btn" id="ontExportBtn">
                        <i class="bi bi-download"></i>
                        <span>${escape(exportLabel)}</span>
                    </button>
                </div>
            </div>
        </div>
    `);

        $('#ontSearchInput')?.addEventListener('input', util.debounce((e) => {
            const value = e.target.value || '';
            ctx.patch({ search: value });
        }, 180));

        $('#ontExportBtn')?.addEventListener('click', () => exportCurrentTab(ctx));
    }

    function renderHeaderActions(ctx) {
        if (ctx.state.tab === 'inventory') {
            html(el.headerActions, `
                <button class="btn btn-primary nx-header-btn" type="button" id="openAddOntBtn">
                    <i class="bi bi-plus-lg"></i><span>Add ONT</span>
                </button>
                <button class="btn btn-light border nx-header-btn" type="button" id="refreshOntBtn">
                    <i class="bi bi-arrow-clockwise"></i><span>Refresh</span>
                </button>
            `);
        } else if (ctx.state.tab === 'discovery') {
            const oltOptions = safeArray(ctx.state.olts).map((olt) => `
                <option value="${Number(olt.id)}" ${Number(ctx.state.selectedDiscoveryOltId) === Number(olt.id) ? 'selected' : ''}>
                    ${escape(olt.name || 'OLT')} - ${escape(olt.ip_address || '-')}
                </option>
            `).join('');
            html(el.headerActions, `
                <select class="form-select ont-discovery-olt-select" id="ontDiscoveryOltSelect" aria-label="Discovery OLT">
                    ${oltOptions || '<option value="">No configured OLT</option>'}
                </select>
                <button class="btn btn-primary nx-header-btn" type="button" id="runDiscoveryBtn">
                    <i class="bi bi-search"></i><span>Auto Discover</span>
                </button>
                <button class="btn btn-outline-primary nx-header-btn" type="button" id="bulkAddDiscoveryBtn">
                    <i class="bi bi-plus-square"></i><span>Bulk Add Selected</span>
                </button>
                <button class="btn btn-light border nx-header-btn" type="button" id="refreshOntBtn">
                    <i class="bi bi-arrow-clockwise"></i><span>Refresh</span>
                </button>
            `);
        } else {
            html(el.headerActions, `
                <button class="btn btn-light border nx-header-btn" type="button" id="refreshOntBtn">
                    <i class="bi bi-arrow-clockwise"></i><span>Refresh</span>
                </button>
            `);
        }

        $('#openAddOntBtn')?.addEventListener('click', () => openCreateOntModal(ctx));
        $('#ontDiscoveryOltSelect')?.addEventListener('change', (e) => {
            ctx.state.selectedDiscoveryOltId = Number(e.target.value || 0) || null;
        });
        $('#runDiscoveryBtn')?.addEventListener('click', () => runDiscovery(ctx));
        $('#bulkAddDiscoveryBtn')?.addEventListener('click', () => bulkAddSelectedDiscovery(ctx));
        $('#refreshOntBtn')?.addEventListener('click', () => refreshStateAndRender(ctx));
    }

    function renderContent(ctx) {
        if (ctx.state.tab === 'inventory') {
            text(el.subtitle, 'Manage ONT inventory records');
            renderInventorySection(ctx);
            return;
        }

        if (ctx.state.tab === 'discovery') {
            text(el.subtitle, 'OLT autofind results and inventory onboarding');
            renderDiscoverySection(ctx);
            return;
        }

        text(el.subtitle, 'Direct ACS API visibility');
        renderRemoteManagementSection(ctx);
    }

    function renderInventorySection(ctx) {
        if (ctx.state.loading.inventory) {
            html(el.contentArea, render.skeletonTable(6, 8));
            return;
        }

        const rows = getInventoryRows(ctx.state);
        if (!rows.length) {
            html(el.contentArea, `
                ${renderInventorySummaryCards(ctx.state)}
                ${renderFilterBar(ctx.state.inventoryFilter, [
                ['ALL', 'All'],
                ['UNASSIGNED', 'Unassigned'],
                ['ASSIGNED', 'Assigned'],
                ['OFFLINE', 'Offline']
            ], 'data-inventory-filter')}
                ${renderEmptyState(
                'No inventory records yet',
                'Create your first ONT inventory record to start tracking devices.',
                'Add ONT',
                'inventoryEmptyAddBtn',
                'bi bi-hdd-network'
            )}
            `);

            bindCommonFilterActions(ctx);
            $('#inventoryEmptyAddBtn')?.addEventListener('click', () => openCreateOntModal(ctx));
            return;
        }

        html(el.contentArea, `
            ${renderInventorySummaryCards(ctx.state)}
            ${renderFilterBar(ctx.state.inventoryFilter, [
            ['ALL', 'All'],
            ['UNASSIGNED', 'Unassigned'],
            ['ASSIGNED', 'Assigned'],
            ['OFFLINE', 'Offline']
        ], 'data-inventory-filter')}
            <div class="card border-0 shadow-sm nx-surface-card">
                <div class="card-body">
                    <div id="inventoryTable"></div>
                </div>
            </div>
        `);

        bindCommonFilterActions(ctx);

        TABLES.inventory?.destroy();
        TABLES.inventory = datatable.create({
            el: '#inventoryTable',
            rows,
            search: false,
            paginate: true,
            pager: { currentPage: 1, rowsPerPage: 20 },
            sort: { key: 'serial_number', dir: 'asc' },
            columns: [
                {
                    key: 'serial_number',
                    label: 'Serial',
                    render: (_, row) => serialCell(row.serial_number)
                },
                {
                    key: 'model',
                    label: 'Model',
                    render: (v) => escape(v || '-')
                },
                {
                    key: 'vendor',
                    label: 'Vendor',
                    render: (v) => escape(v || '-')
                },
                {
                    key: 'subscriber_name',
                    label: 'Subscriber',
                    render: (v) => escape(v || '-')
                },
                {
                    key: 'status',
                    label: 'Status',
                    render: (v) => renderStatusBadge(v)
                },
                {
                    key: 'created_at',
                    label: 'Created',
                    render: (v) => escape(formatDateTime(v || '-'))
                },
                {
                    key: '__actions',
                    label: 'Actions',
                    render: (_, row) => `
                        <div class="ont-table-actions">
                            <button type="button" class="btn btn-sm btn-outline-secondary nx-icon-btn js-view-inventory" data-id="${parseInt(row.id, 10)}" title="View Details"><i class="bi bi-eye"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-primary nx-icon-btn js-edit-ont" data-id="${parseInt(row.id, 10)}" title="Edit ONT"><i class="bi bi-pencil"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-danger nx-icon-btn js-delete-ont" data-id="${parseInt(row.id, 10)}" title="Delete ONT"><i class="bi bi-trash"></i></button>
                        </div>
                    `
                }
            ]
        });

        TABLES.inventory.setSearch(ctx.state.search);
        bindInventoryActions(ctx);
    }

    function renderDiscoverySection(ctx) {
        if (ctx.state.loading.discovery) {
            html(el.contentArea, render.skeletonTable(6, 8));
            return;
        }

        const rows = getDiscoveryRows(ctx.state);
        if (!rows.length) {
            html(el.contentArea, `
                ${renderDiscoverySummaryCards(ctx.state)}
                ${renderFilterBar(ctx.state.discoveryFilter, [
                ['ALL', 'All'],
                ['KNOWN', 'Known'],
                ['ROGUE', 'Rogue']
            ], 'data-discovery-filter')}
                ${renderEmptyState(
                'No discovery records found',
                'Run OLT auto discovery to fetch ONTs detected by the OLT.',
                'Run Auto Discover',
                'discoveryEmptyRunBtn',
                'bi bi-router'
            )}
            `);

            bindCommonFilterActions(ctx);
            $('#discoveryEmptyRunBtn')?.addEventListener('click', () => runDiscovery(ctx));
            return;
        }

        html(el.contentArea, `
            ${renderDiscoverySummaryCards(ctx.state)}
            ${renderFilterBar(ctx.state.discoveryFilter, [
            ['ALL', 'All'],
            ['KNOWN', 'Known'],
            ['ROGUE', 'Rogue']
        ], 'data-discovery-filter')}
            <div class="card border-0 shadow-sm nx-surface-card">
                <div class="card-body">
                    <div id="discoveryTable"></div>
                </div>
            </div>
        `);

        bindCommonFilterActions(ctx);

        TABLES.discovery?.destroy();
        TABLES.discovery = datatable.create({
            el: '#discoveryTable',
            rows,
            search: false,
            paginate: true,
            pager: { currentPage: 1, rowsPerPage: 20 },
            sort: { key: 'last_seen', dir: 'desc' },
            columns: [
                {
                    key: '__select',
                    label: '',
                    render: (_, row) => {
                        const isKnown = upper(row.inventory_state) === 'KNOWN';
                        if (isKnown) return '';
                        return `<input type="checkbox" class="js-discovery-select" data-serial="${escape(row.serial_number)}" ${ctx.state.discoverySelections.includes(String(row.serial_number)) ? 'checked' : ''}>`;
                    }
                },
                {
                    key: 'serial_number',
                    label: 'Serial',
                    render: (_, row) => serialCell(row.serial_number)
                },
                {
                    key: 'model',
                    label: 'Model',
                    render: (v) => escape(v || '-')
                },
                {
                    key: 'vendor',
                    label: 'Vendor',
                    render: (v) => escape(v || '-')
                },
                {
                    key: 'fsp',
                    label: 'F/S/P',
                    render: (v) => escape(v || '-')
                },
                {
                    key: 'last_seen',
                    label: 'Last Seen',
                    render: (_, row) => escape(formatDateTime(row.last_seen || row.autofind_time || '-'))
                },
                {
                    key: 'inventory_state',
                    label: 'Inventory State',
                    render: (v) => renderStatusBadge(v)
                },
                {
                    key: '__actions',
                    label: 'Actions',
                    render: (_, row) => {
                        const isKnown = upper(row.inventory_state) === 'KNOWN';
                        return `
                            <div class="ont-table-actions">
                                <button type="button" class="btn btn-sm btn-outline-secondary nx-icon-btn js-view-discovery" data-serial="${escape(row.serial_number)}" title="View Details"><i class="bi bi-eye"></i></button>
                                ${
                            isKnown
                                ? `<button type="button" class="btn btn-sm btn-outline-success nx-icon-btn" disabled title="Already in inventory"><i class="bi bi-check-lg"></i></button>`
                                : `<button type="button" class="btn btn-sm btn-outline-primary nx-icon-btn js-add-discovery" data-serial="${escape(row.serial_number)}" title="Add to Inventory"><i class="bi bi-plus-lg"></i></button>`
                        }
                            </div>
                        `;
                    }
                }
            ]
        });

        TABLES.discovery.setSearch(ctx.state.search);
        bindDiscoveryActions(ctx);
    }

    function renderRemoteManagementSection(ctx) {
        if (ctx.state.loading.acs) {
            html(el.contentArea, render.skeletonTable(8, 6));
            return;
        }

        const rows = getAcsRows(ctx.state);
        if (!rows.length) {
            html(el.contentArea, `
            ${renderAcsSummaryCards(ctx.state)}
            ${renderFilterBar(ctx.state.acsFilter, [
                ['ALL', 'All'],
                ['MATCHED', 'Matched'],
                ['ROGUE', 'Rogue'],
                ['ONLINE', 'Online'],
                ['STALE', 'Stale']
            ], 'data-acs-filter')}
            ${renderEmptyState(
                'No ACS devices found',
                'No ACS device records are currently available for this filter.',
                'Refresh ACS',
                'acsEmptyRefreshBtn',
                'bi bi-hdd-rack'
            )}
        `);

            bindCommonFilterActions(ctx);
            $('#acsEmptyRefreshBtn')?.addEventListener('click', () => refreshStateAndRender(ctx));
            return;
        }

        html(el.contentArea, `
        ${renderAcsSummaryCards(ctx.state)}
        ${renderFilterBar(ctx.state.acsFilter, [
            ['ALL', 'All'],
            ['MATCHED', 'Matched'],
            ['ROGUE', 'Rogue'],
            ['ONLINE', 'Online'],
            ['STALE', 'Stale']
        ], 'data-acs-filter')}
        <div class="card border-0 shadow-sm nx-surface-card">
            <div class="card-body">
                <div id="remoteManagementTable"></div>
            </div>
        </div>
    `);

        bindCommonFilterActions(ctx);

        TABLES.remoteManagement?.destroy();
        TABLES.remoteManagement = datatable.create({
            el: '#remoteManagementTable',
            rows,
            search: false,
            paginate: true,
            pager: { currentPage: 1, rowsPerPage: 20 },
            sort: { key: 'last_seen', dir: 'desc' },
            columns: [
                {
                    key: 'serial_number',
                    label: 'Serial',
                    render: (_, row) => serialCell(row.serial_number || '-')
                },
                {
                    key: 'last_seen',
                    label: 'Last Seen',
                    render: (v) => escape(formatDateTime(v || '-'))
                },
                {
                    key: 'uptime_seconds',
                    label: 'Uptime',
                    render: (_, row) => escape(formatAcsUptime(row.uptime_seconds))
                },
                {
                    key: 'acs_status',
                    label: 'ACS Status',
                    render: (_, row) => renderStatusBadge(row.acs_status || getAcsStatus(row.last_seen))
                },
                {
                    key: 'inventory_state',
                    label: 'Inventory',
                    render: (_, row) => renderStatusBadge(row.inventory_state || (row.inventory_id ? 'MATCHED' : 'UNMATCHED'))
                },
                {
                    key: '__actions',
                    label: 'Actions',
                    render: (_, row) => `
                    <div class="ont-table-actions">
                        <a href="/ont-devices/acs-device/${encodeURIComponent(row.id)}"
                           class="btn btn-sm btn-outline-primary nx-icon-btn"
                           title="Open ACS Page">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                `
                }
            ]
        });

        TABLES.remoteManagement.setSearch(ctx.state.search);
        bindAcsActions(ctx);
    }

    function getActiveTableKey(tab) {
        if (tab === 'inventory') return 'inventory';
        if (tab === 'discovery') return 'discovery';
        return 'remoteManagement';
    }

    function getInventoryRows(state) {
        return safeArray(state.inventory).filter((r) => {
            return state.inventoryFilter === 'ALL' || upper(r.status) === state.inventoryFilter;
        });
    }

    function getDiscoveryRows(state) {
        return safeArray(state.discovery).filter((r) => {
            return state.discoveryFilter === 'ALL' || upper(r.inventory_state) === state.discoveryFilter;
        });
    }

    function getAcsRows(state) {
        return safeArray(state.acs).filter((r) => {
            if (state.acsFilter === 'ALL') return true;
            const matched = !!r.inventory_id;
            const status = r.acs_status || getAcsStatus(r.last_seen);

            if (state.acsFilter === 'MATCHED') return matched;
            if (state.acsFilter === 'ROGUE') return !matched;
            if (state.acsFilter === 'ONLINE') return status === 'ONLINE';
            if (state.acsFilter === 'STALE') return status === 'STALE';
            return true;
        });
    }

    function bindCommonFilterActions(ctx) {
        $$('[data-inventory-filter]', el.contentArea).forEach((btn) => {
            btn.addEventListener('click', () => ctx.patch({ inventoryFilter: btn.dataset.inventoryFilter }));
        });

        $$('[data-discovery-filter]', el.contentArea).forEach((btn) => {
            btn.addEventListener('click', () => ctx.patch({ discoveryFilter: btn.dataset.discoveryFilter }));
        });

        $$('[data-acs-filter]', el.contentArea).forEach((btn) => {
            btn.addEventListener('click', () => ctx.patch({ acsFilter: btn.dataset.acsFilter }));
        });

        $$('.js-copy-serial', el.contentArea).forEach((btn) => {
            btn.addEventListener('click', () => clipboard.copy(btn.dataset.serial, 'Serial'));
        });
    }

    function bindInventoryActions(ctx) {
        $$('.js-view-inventory', el.contentArea).forEach((btn) => {
            btn.addEventListener('click', () => openInventoryViewModal(ctx, parseInt(btn.dataset.id, 10)));
        });

        $$('.js-edit-ont', el.contentArea).forEach((btn) => {
            btn.addEventListener('click', () => openEditOntModal(ctx, parseInt(btn.dataset.id, 10)));
        });

        $$('.js-delete-ont', el.contentArea).forEach((btn) => {
            btn.addEventListener('click', () => deleteOnt(ctx, parseInt(btn.dataset.id, 10)));
        });
    }

    function bindDiscoveryActions(ctx) {
        $$('.js-discovery-select', el.contentArea).forEach((box) => {
            box.addEventListener('change', () => {
                const serial = String(box.dataset.serial);
                const next = new Set(ctx.state.discoverySelections);

                if (next.has(serial)) next.delete(serial);
                else next.add(serial);

                ctx.patch({ discoverySelections: [...next] });
            });
        });

        $$('.js-view-discovery', el.contentArea).forEach((btn) => {
            btn.addEventListener('click', () => openDiscoveryViewModal(ctx, btn.dataset.serial));
        });

        $$('.js-add-discovery', el.contentArea).forEach((btn) => {
            btn.addEventListener('click', () => addDiscoveredOnt(ctx, btn.dataset.serial));
        });
    }

    function bindAcsActions(ctx) {
        void ctx;
    }

    function formatAcsUptime(value) {
        if (value === null || value === undefined || value === '') return '-';
        const s = Number(value);
        if (!Number.isFinite(s) || s <= 0) return '-';

        const days = Math.floor(s / 86400);
        const hours = Math.floor((s % 86400) / 3600);
        const mins = Math.floor((s % 3600) / 60);
        const secs = Math.floor(s % 60);

        if (days > 0) return `${days}D ${hours}H ${mins}M ${secs}S`;
        if (hours > 0) return `${hours}H ${mins}M ${secs}S`;
        if (mins > 0) return `${mins}M ${secs}S`;
        return `${secs}S`;
    }

    function getAcsStatus(lastSeen) {
        if (!lastSeen) return 'UNKNOWN';

        const diff = (Date.now() - new Date(lastSeen).getTime()) / 1000;
        if (Number.isNaN(diff)) return 'UNKNOWN';
        if (diff < 300) return 'ONLINE';
        if (diff < 1800) return 'STALE';
        return 'UNKNOWN';
    }

    function normalizeAcsRow(row, inventoryMap = new Map()) {
        const serial = row.serial_number || row.serial || row._serial || row.SerialNumber || '';
        const firmware = row.firmware_version || row.firmware || row.SoftwareVersion || '';
        const model = row.model || row.product_class || row.ProductClass || '';
        const vendor = row.vendor || row.manufacturer || row.Manufacturer || '';
        const wanIp = row.wan_ip || row.ip_address || row.external_ip || row.wanIp || '';
        const managementIp = row.management_ip || row.tr069_ip || row.mgmt_ip || '';
        const uptime = row.uptime_seconds ?? row.uptime ?? '';
        const lastSeen = row.last_seen || row.last_inform || row._lastInform || row.updated_at || null;
        const ssid = row.ssid || '';
        const id = row.id || row.device_id || row._id || '';
        const inventoryId = row.inventory_id || inventoryMap.get(String(serial).toUpperCase()) || null;

        return {
            ...row,
            id,
            serial_number: serial,
            firmware_version: firmware,
            model,
            vendor,
            wan_ip: wanIp,
            management_ip: managementIp,
            uptime_seconds: Number(uptime) || 0,
            uptime,
            last_seen: lastSeen,
            ssid,
            inventory_id: inventoryId,
            acs_status: row.acs_status || getAcsStatus(lastSeen),
            inventory_state: row.inventory_state || (inventoryId ? 'MATCHED' : 'ROGUE')
        };
    }

    function csvEscape(value) {
        const str = String(value ?? '');
        return `"${str.replace(/"/g, '""')}"`;
    }

    function exportRowsToCsv(filename, rows, columns) {
        const header = columns.map((c) => csvEscape(c.label)).join(',');
        const body = rows.map((row) => columns.map((c) => csvEscape(c.value(row))).join(',')).join('\n');
        const csv = `${header}\n${body}`;
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.click();
        URL.revokeObjectURL(url);
    }

    function exportCurrentTab(ctx) {
        if (ctx.state.tab === 'inventory') {
            exportRowsToCsv('ont_inventory.csv', getInventoryRows(ctx.state), [
                { label: 'Serial Number', value: (r) => r.serial_number },
                { label: 'Model', value: (r) => r.model },
                { label: 'Vendor', value: (r) => r.vendor },
                { label: 'Subscriber Name', value: (r) => r.subscriber_name },
                { label: 'Status', value: (r) => r.status },
                { label: 'Created At', value: (r) => r.created_at }
            ]);
            return;
        }

        if (ctx.state.tab === 'discovery') {
            exportRowsToCsv('ont_discovery.csv', getDiscoveryRows(ctx.state), [
                { label: 'Serial Number', value: (r) => r.serial_number },
                { label: 'Model', value: (r) => r.model },
                { label: 'Vendor', value: (r) => r.vendor },
                { label: 'Vendor Code', value: (r) => r.vendor_code },
                { label: 'FSP', value: (r) => r.fsp },
                { label: 'Frame', value: (r) => r.frame },
                { label: 'Slot', value: (r) => r.slot },
                { label: 'Port', value: (r) => r.port },
                { label: 'Inventory State', value: (r) => r.inventory_state },
                { label: 'Last Seen', value: (r) => r.last_seen },
                { label: 'Autofind Time', value: (r) => r.autofind_time }
            ]);
            return;
        }

        exportRowsToCsv('ont_remote_management.csv', getAcsRows(ctx.state), [
            { label: 'Device ID', value: (r) => r.id },
            { label: 'Serial Number', value: (r) => r.serial_number },
            { label: 'Model', value: (r) => r.model },
            { label: 'Vendor', value: (r) => r.vendor },
            { label: 'WAN IP', value: (r) => r.wan_ip },
            { label: 'Management IP', value: (r) => r.management_ip || '' },
            { label: 'SSID', value: (r) => r.ssid || '' },
            { label: 'Last Seen', value: (r) => r.last_seen },
            { label: 'Firmware', value: (r) => r.firmware_version },
            { label: 'Uptime', value: (r) => r.uptime },
            { label: 'ACS Status', value: (r) => r.acs_status || getAcsStatus(r.last_seen) },
            { label: 'Inventory Match', value: (r) => (r.inventory_id ? 'MATCHED' : 'ROGUE') }
        ]);
    }

    function getInventorySummary(state) {
        const rows = safeArray(state.inventory);
        return {
            total: rows.length,
            unassigned: rows.filter((r) => upper(r.status) === 'UNASSIGNED').length,
            assigned: rows.filter((r) => upper(r.status) === 'ASSIGNED').length,
            offline: rows.filter((r) => upper(r.status) === 'OFFLINE').length
        };
    }

    function getDiscoverySummary(state) {
        const rows = safeArray(state.discovery);
        const total = rows.length;
        const known = rows.filter((r) => upper(r.inventory_state) === 'KNOWN').length;
        const rogue = rows.filter((r) => upper(r.inventory_state) === 'ROGUE').length;

        let latest = null;
        rows.forEach((r) => {
            const candidate = r.last_seen || r.autofind_time;
            if (!candidate) return;
            const ts = new Date(candidate).getTime();
            if (Number.isNaN(ts)) return;
            if (latest === null || ts > latest) latest = ts;
        });

        return {
            total,
            known,
            rogue,
            latestText: latest ? new Date(latest).toLocaleString() : 'No discovery run yet'
        };
    }

    function renderInventorySummaryCards(state) {
        const s = getInventorySummary(state);

        return `
            <div class="row g-3 mb-3">
                <div class="col-12 col-sm-6 col-xl-3"><div class="nx-summary-card nx-summary-primary"><div class="nx-summary-top"><div class="nx-summary-label">Total ONTs</div><div class="nx-summary-icon"><i class="bi bi-hdd-network"></i></div></div><div class="nx-summary-value">${s.total}</div></div></div>
                <div class="col-12 col-sm-6 col-xl-3"><div class="nx-summary-card nx-summary-slate"><div class="nx-summary-top"><div class="nx-summary-label">Unassigned</div><div class="nx-summary-icon"><i class="bi bi-dash-circle"></i></div></div><div class="nx-summary-value">${s.unassigned}</div></div></div>
                <div class="col-12 col-sm-6 col-xl-3"><div class="nx-summary-card nx-summary-success"><div class="nx-summary-top"><div class="nx-summary-label">Assigned</div><div class="nx-summary-icon"><i class="bi bi-check-circle"></i></div></div><div class="nx-summary-value">${s.assigned}</div></div></div>
                <div class="col-12 col-sm-6 col-xl-3"><div class="nx-summary-card nx-summary-danger"><div class="nx-summary-top"><div class="nx-summary-label">Offline</div><div class="nx-summary-icon"><i class="bi bi-wifi-off"></i></div></div><div class="nx-summary-value">${s.offline}</div></div></div>
            </div>
        `;
    }

    function renderDiscoverySummaryCards(state) {
        const s = getDiscoverySummary(state);

        return `
            <div class="row g-3 mb-3">
                <div class="col-12 col-sm-6 col-xl-3"><div class="nx-summary-card nx-summary-primary"><div class="nx-summary-top"><div class="nx-summary-label">Total Discovered</div><div class="nx-summary-icon"><i class="bi bi-router"></i></div></div><div class="nx-summary-value">${s.total}</div></div></div>
                <div class="col-12 col-sm-6 col-xl-3"><div class="nx-summary-card nx-summary-success"><div class="nx-summary-top"><div class="nx-summary-label">Known ONTs</div><div class="nx-summary-icon"><i class="bi bi-check-circle"></i></div></div><div class="nx-summary-value">${s.known}</div></div></div>
                <div class="col-12 col-sm-6 col-xl-3"><div class="nx-summary-card nx-summary-danger"><div class="nx-summary-top"><div class="nx-summary-label">Rogue ONTs</div><div class="nx-summary-icon"><i class="bi bi-exclamation-triangle"></i></div></div><div class="nx-summary-value">${s.rogue}</div></div></div>
                <div class="col-12 col-sm-6 col-xl-3"><div class="nx-summary-card nx-summary-cyan"><div class="nx-summary-top"><div class="nx-summary-label">Last Discovery Refresh</div><div class="nx-summary-icon"><i class="bi bi-clock-history"></i></div></div><div class="nx-summary-subtext">${escape(s.latestText)}</div></div></div>
            </div>
        `;
    }

    function renderAcsSummaryCards(state) {
        const rows = safeArray(state.acs);
        const online = rows.filter((r) => upper(r.acs_status || getAcsStatus(r.last_seen)) === 'ONLINE').length;
        const matched = rows.filter((r) => upper(r.inventory_state) === 'MATCHED').length;
        const rogue = rows.filter((r) => !r.inventory_id || upper(r.inventory_state) === 'ROGUE').length;

        return `
            <div class="row g-3 mb-3">
                <div class="col-12 col-sm-6 col-xl-3"><div class="nx-summary-card nx-summary-primary"><div class="nx-summary-top"><div class="nx-summary-label">Total ACS Devices</div><div class="nx-summary-icon"><i class="bi bi-hdd-rack"></i></div></div><div class="nx-summary-value">${rows.length}</div></div></div>
                <div class="col-12 col-sm-6 col-xl-3"><div class="nx-summary-card nx-summary-online"><div class="nx-summary-top"><div class="nx-summary-label">Online</div><div class="nx-summary-icon"><i class="bi bi-wifi"></i></div></div><div class="nx-summary-value">${online}</div></div></div>
                <div class="col-12 col-sm-6 col-xl-3"><div class="nx-summary-card nx-summary-matched"><div class="nx-summary-top"><div class="nx-summary-label">Matched to Inventory</div><div class="nx-summary-icon"><i class="bi bi-check-circle"></i></div></div><div class="nx-summary-value">${matched}</div></div></div>
                <div class="col-12 col-sm-6 col-xl-3"><div class="nx-summary-card nx-summary-danger"><div class="nx-summary-top"><div class="nx-summary-label">Rogue / Unmatched</div><div class="nx-summary-icon"><i class="bi bi-exclamation-triangle"></i></div></div><div class="nx-summary-value">${rogue}</div></div></div>
            </div>
        `;
    }

    function renderFilterBar(current, items, attr) {
        return `
            <div class="nx-filter-bar">
                ${items.map(([value, label]) => `
                    <button type="button" class="nx-filter-chip ${current === value ? 'active' : ''}" ${attr}="${value}">
                        ${escape(label)}
                    </button>
                `).join('')}
            </div>
        `;
    }

    function renderEmptyState(title, textValue, buttonLabel, buttonId, iconClass = 'bi bi-inbox') {
        return render.emptyState({
            title,
            text: textValue,
            buttonLabel,
            buttonId,
            iconClass
        });
    }

    function serialCell(serial) {
        return `
            <div class="nx-copy-inline">
                <span class="fw-semibold">${escape(serial || '-')}</span>
                <button type="button" class="nx-copy-btn js-copy-serial" data-serial="${escape(serial || '')}" title="Copy Serial">
                    <i class="bi bi-clipboard"></i>
                </button>
            </div>
        `;
    }

    function renderStatusBadge(status) {
        return render.badge(status, {
            success: ['ASSIGNED', 'ONLINE', 'KNOWN', 'MATCHED'],
            danger: ['OFFLINE', 'ROGUE', 'UNMATCHED'],
            warning: ['STALE']
        });
    }

    function resetOntForm() {
        forms.reset(el.ontForm);
        if (!el.ontForm) return;

        el.ontForm.dataset.mode = 'create';
        el.ontForm.dataset.id = '';
        text(el.ontModalTitle, 'Add ONT');
        text(el.ontModalSubtitle, 'Create inventory record');
        if (el.ontStatusInput) el.ontStatusInput.value = 'UNASSIGNED';
        if (el.ontSubscriberMatchText) text(el.ontSubscriberMatchText, 'Leave empty to keep the ONT unassigned.');
    }

    function uniqueInventoryValues(state, field) {
        return [...new Set(safeArray(state.inventory)
            .map((row) => String(row[field] || '').trim())
            .filter(Boolean))]
            .sort((a, b) => a.localeCompare(b));
    }

    function subscriberOptionLabel(row) {
        const name = String(row.full_name || 'Unnamed subscriber').trim();
        const account = String(row.account_number || row.id || '').trim();
        return account ? `${name} — ${account}` : name;
    }

    function syncOntFormOptions(ctx) {
        const fillOptions = (target, values) => {
            if (target) html(target, values.map((value) => `<option value="${escape(value)}"></option>`).join(''));
        };

        fillOptions(el.ontVendorOptions, uniqueInventoryValues(ctx.state, 'vendor'));
        fillOptions(el.ontModelOptions, uniqueInventoryValues(ctx.state, 'model'));

        if (el.ontSubscriberOptions) {
            html(el.ontSubscriberOptions, safeArray(ctx.state.subscribers).map((row) => `
                <option value="${escape(subscriberOptionLabel(row))}"></option>
            `).join(''));
        }
    }

    function syncSubscriberSelection(ctx) {
        if (!el.ontSubscriberSearchInput || !el.ontSubscriberIdInput) return true;
        const query = el.ontSubscriberSearchInput.value.trim();
        if (query === '') {
            el.ontSubscriberIdInput.value = '';
            text(el.ontSubscriberMatchText, 'Leave empty to keep the ONT unassigned.');
            return true;
        }

        const normalized = query.toLowerCase();
        const matches = safeArray(ctx.state.subscribers).filter((row) => {
            return subscriberOptionLabel(row).toLowerCase() === normalized
                || String(row.full_name || '').trim().toLowerCase() === normalized
                || String(row.account_number || '').trim().toLowerCase() === normalized;
        });

        if (matches.length !== 1) {
            el.ontSubscriberIdInput.value = '';
            text(el.ontSubscriberMatchText, 'Select one matching subscriber from the suggestions.');
            return false;
        }

        el.ontSubscriberIdInput.value = String(matches[0].id);
        text(el.ontSubscriberMatchText, `Matched: ${subscriberOptionLabel(matches[0])}`);
        return true;
    }

    function findInventoryById(state, id) {
        return state.inventory.find((x) => parseInt(x.id, 10) === parseInt(id, 10)) || null;
    }

    function findInventoryBySerial(state, serial) {
        return state.inventory.find((x) => String(x.serial_number || '').toUpperCase() === String(serial || '').toUpperCase()) || null;
    }

    function findDiscoveryBySerial(state, serial) {
        return state.discovery.find((x) => String(x.serial_number || '').toUpperCase() === String(serial || '').toUpperCase()) || null;
    }

    function findAcsById(state, id) {
        return state.acs.find((x) => String(x.id) === String(id)) || null;
    }

    function openCreateOntModal(ctx) {
        resetOntForm();
        syncOntFormOptions(ctx);
        modal.open(el.ontModal);
    }

    function openEditOntModal(ctx, id) {
        const row = findInventoryById(ctx.state, id);
        if (!row) return ui.toast('error', 'ONT not found.');

        resetOntForm();
        syncOntFormOptions(ctx);
        el.ontForm.dataset.mode = 'edit';
        el.ontForm.dataset.id = String(id);

        text(el.ontModalTitle, 'Edit ONT');
        text(el.ontModalSubtitle, row.serial_number || '-');

        forms.fill([
            [el.ontSerialInput, row.serial_number || ''],
            [el.ontVendorInput, row.vendor || ''],
            [el.ontModelInput, row.model || ''],
            [el.ontSubscriberIdInput, row.subscriber_id || ''],
            [el.ontSubscriberSearchInput, row.subscriber_id ? subscriberOptionLabel(
                safeArray(ctx.state.subscribers).find((subscriber) => Number(subscriber.id) === Number(row.subscriber_id))
                    || { id: row.subscriber_id, full_name: row.subscriber_name }
            ) : ''],
            [el.ontStatusInput, row.status || 'UNASSIGNED']
        ]);

        modal.open(el.ontModal);
    }

    function openInventoryViewModal(ctx, id) {
        const row = findInventoryById(ctx.state, id);
        if (!row) return ui.toast('error', 'Inventory record not found.');

        const discoveryMatch = findDiscoveryBySerial(ctx.state, row.serial_number);

        ctx.patch({ selectedInventoryId: row.id || null });

        text(el.inventoryViewSubtitle, row.serial_number || 'Inspect ONT inventory record');
        text(el.viewInventorySerial, row.serial_number || '-');
        html(el.viewInventoryStatus, renderStatusBadge(row.status || 'UNKNOWN'));
        text(el.viewInventoryVendor, row.vendor || '-');
        text(el.viewInventoryModel, row.model || '-');
        text(el.viewInventorySubscriberId, row.subscriber_id || '-');
        text(el.viewInventorySubscriberName, row.subscriber_name || '-');

        html(el.viewInventoryCreatedAt, `
            ${escape(formatDateTime(row.created_at || '-'))}
            <div class="small text-muted mt-2">
                Discovery Seen: ${discoveryMatch ? 'YES' : 'NO'}<br>
                Last F/S/P: ${escape(discoveryMatch?.fsp || '-')}<br>
                Last Discovery Seen: ${escape(formatDateTime(discoveryMatch?.last_seen || discoveryMatch?.autofind_time || '-'))}
            </div>
        `);

        modal.open(el.inventoryViewModal);
    }

    function openDiscoveryViewModal(ctx, serial) {
        const row = findDiscoveryBySerial(ctx.state, serial);
        if (!row) return ui.toast('error', 'Discovery record not found.');

        ctx.patch({ selectedDiscoverySerial: row.serial_number || null });

        text(el.discoveryViewSubtitle, row.serial_number || 'Inspect OLT autofind record');
        text(el.viewDiscoverySerial, row.serial_number || '-');
        html(el.viewDiscoveryInventoryState, renderStatusBadge(row.inventory_state || 'UNKNOWN'));
        text(el.viewDiscoveryModel, row.model || '-');
        text(el.viewDiscoveryVendor, row.vendor || '-');
        text(el.viewDiscoveryVendorCode, row.vendor_code || '-');
        text(el.viewDiscoveryFsp, row.fsp || '-');
        text(el.viewDiscoveryPhysical, [row.frame ?? '-', row.slot ?? '-', row.port ?? '-'].join(' / '));
        text(el.viewDiscoveryLastSeen, formatDateTime(row.last_seen || '-'));
        text(el.viewDiscoveryAutofindTime, formatDateTime(row.autofind_time || '-'));

        const isKnown = upper(row.inventory_state) === 'KNOWN';
        if (el.discoveryModalAddBtn) {
            el.discoveryModalAddBtn.disabled = isKnown;
            el.discoveryModalAddBtn.innerHTML = isKnown
                ? '<i class="bi bi-check-lg"></i> Already in Inventory'
                : '<i class="bi bi-plus-lg"></i> Add to Inventory';
        }

        modal.open(el.discoveryViewModal);
    }

    function openAcsViewModal(ctx, id) {
        const row = findAcsById(ctx.state, id);
        if (!row) return ui.toast('error', 'ACS record not found.');

        ctx.patch({ selectedAcsDeviceId: row.id || null });

        const matchedInventory = row.inventory_id
            ? findInventoryById(ctx.state, row.inventory_id)
            : findInventoryBySerial(ctx.state, row.serial_number);

        text(el.acsViewSubtitle, row.serial_number || 'Inspect ACS device record');
        text(el.viewAcsDeviceId, row.id || '-');
        html(el.viewAcsStatus, renderStatusBadge(row.acs_status || getAcsStatus(row.last_seen)));
        text(el.viewAcsSerial, row.serial_number || '-');
        text(el.viewAcsWanIp, row.wan_ip || '-');
        text(el.viewAcsVendor, row.vendor || '-');
        text(el.viewAcsModel, row.model || '-');
        text(el.viewAcsFirmware, row.firmware_version || '-');
        text(el.viewAcsUptime, row.uptime || '-');
        text(el.viewAcsLastSeen, formatDateTime(row.last_seen || '-'));
        html(el.viewAcsInventoryMatch, renderStatusBadge(matchedInventory ? 'MATCHED' : 'ROGUE'));
        html(el.viewAcsInventoryInfo, matchedInventory
            ? `
                Serial: ${escape(matchedInventory.serial_number || '-')}<br>
                Status: ${escape(matchedInventory.status || '-')}<br>
                Vendor: ${escape(matchedInventory.vendor || '-')}<br>
                Model: ${escape(matchedInventory.model || '-')}
            `
            : 'No linked inventory record.'
        );

        if (el.acsOpenInventoryBtn) el.acsOpenInventoryBtn.disabled = !matchedInventory;
        modal.open(el.acsViewModal);
    }

    async function addAcsDeviceToInventory(ctx, id) {
        const row = findAcsById(ctx.state, id);
        if (!row) return ui.toast('error', 'ACS device not found.');

        const fd = forms.data({
            serial_number: row.serial_number || '',
            model: row.model || '',
            vendor: row.vendor || '',
            frame: row.frame ?? '',
            slot: row.slot ?? '',
            port: row.port ?? ''
        });

        const result = await actions.run({
            loading: 'Adding ACS device to inventory...',
            task: () => api.form('/api/v1/ont-devices/add-to-inventory', fd)
        });

        if (!result?.ok) return;

        setFlash(ctx, {
            acsDeviceId: row.id || null,
            inventorySerial: row.serial_number || null
        });

        await loadAll(ctx);
        ui.toast('success', result.result.message || 'ACS device added to inventory.');
        clearFlashLater(ctx, 'acsDeviceId');
        clearFlashLater(ctx, 'inventorySerial');
    }

    async function submitOntForm(ctx, e) {
        e.preventDefault();

        if (!syncSubscriberSelection(ctx)) {
            ui.toast('error', 'Select a matching subscriber or leave the subscriber field empty.');
            el.ontSubscriberSearchInput?.focus();
            return;
        }

        const mode = el.ontForm.dataset.mode || 'create';
        const id = el.ontForm.dataset.id || '';
        const formData = new FormData(el.ontForm);

        const url = mode === 'edit'
            ? `/api/v1/ont-devices/update/${id}`
            : '/api/v1/ont-devices/store';

        const result = await actions.run({
            task: () => api.form(url, formData)
        });

        if (!result?.ok) return;

        modal.close(el.ontModal);
        setFlash(ctx, { inventorySerial: formData.get('serial_number') || null });

        await loadAll(ctx);
        ui.toast('success', result.result.message || 'Saved successfully.');
        clearFlashLater(ctx, 'inventorySerial');
    }

    async function deleteOnt(ctx, id) {
        const fd = forms.data({ id });

        const result = await actions.run({
            confirm: {
                title: 'Delete ONT?',
                text: 'This will permanently delete the ONT inventory record.',
                confirmButtonText: 'Delete'
            },
            task: () => api.form('/api/v1/ont-devices/delete', fd)
        });

        if (!result?.ok) return;

        ui.toast('success', result.result.message || 'Deleted successfully.');
        await loadAll(ctx);
    }

    async function runDiscovery(ctx) {
        const result = await actions.run({
            confirm: {
                title: 'Run OLT auto discovery?',
                text: 'This will execute the OLT autofind script and refresh discovery records.',
                icon: 'question',
                confirmButtonText: 'Run Discovery'
            },
            loading: 'Running discovery...',
            task: () => api.form('/api/v1/ont-devices/discover', forms.data({
                olt_id: ctx.state.selectedDiscoveryOltId || ''
            }))
        });

        if (!result?.ok) return;

        ctx.patch({ discoverySelections: [] });
        await loadAll(ctx);

        await ui.swal({
            icon: 'success',
            title: 'Discovery completed',
            text: result.result.message || 'ONT discovery finished successfully.',
            timer: 2200,
            showConfirmButton: false
        });
    }

    async function addDiscoveredOnt(ctx, serial) {
        const row = findDiscoveryBySerial(ctx.state, serial);
        if (!row) return ui.toast('error', 'Discovery record not found.');

        const fd = forms.data({
            serial_number: row.serial_number || '',
            model: row.model || '',
            vendor: row.vendor || '',
            frame: row.frame ?? '',
            slot: row.slot ?? '',
            port: row.port ?? ''
        });

        const result = await actions.run({
            loading: 'Adding ONT to inventory...',
            task: () => api.form('/api/v1/ont-devices/add-to-inventory', fd)
        });

        if (!result?.ok) return;

        setFlash(ctx, {
            inventorySerial: row.serial_number || null,
            discoverySerial: row.serial_number || null
        });

        ctx.patch({
            discoverySelections: ctx.state.discoverySelections.filter((s) => s !== String(serial))
        });

        await loadAll(ctx);

        const latest = findDiscoveryBySerial(ctx.state, serial);
        if (latest && upper(latest.inventory_state) === 'KNOWN' && ctx.state.selectedDiscoverySerial === serial) {
            openDiscoveryViewModal(ctx, serial);
        }

        ui.toast('success', result.result.message || 'Added to inventory.');
        clearFlashLater(ctx, 'inventorySerial');
        clearFlashLater(ctx, 'discoverySerial');
    }

    async function bulkAddSelectedDiscovery(ctx) {
        const selected = [...ctx.state.discoverySelections];
        if (!selected.length) {
            ui.toast('error', 'No discovery rows selected.');
            return;
        }

        const confirm = await ui.confirm({
            title: 'Bulk add selected ONTs?',
            text: `Add ${selected.length} selected ONT(s) to inventory.`,
            icon: 'question',
            confirmButtonText: 'Add Selected'
        });

        if (!confirm.isConfirmed) return;

        let successCount = 0;
        let failCount = 0;
        ui.loading('Bulk adding ONTs...');

        for (const serial of selected) {
            const row = findDiscoveryBySerial(ctx.state, serial);
            if (!row) {
                failCount++;
                continue;
            }

            const fd = forms.data({
                serial_number: row.serial_number || '',
                model: row.model || '',
                vendor: row.vendor || '',
                frame: row.frame ?? '',
                slot: row.slot ?? '',
                port: row.port ?? ''
            });

            try {
                await api.form('/api/v1/ont-devices/add-to-inventory', fd);
                successCount++;
            } catch {
                failCount++;
            }
        }

        ui.closeLoading();

        ctx.patch({ discoverySelections: [] });
        await loadAll(ctx);

        ui.toast(
            'success',
            failCount === 0
                ? `${successCount} ONT(s) added to inventory.`
                : `${successCount} added, ${failCount} failed.`
        );
    }

    appPage.start();
});
