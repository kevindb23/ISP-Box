document.addEventListener('DOMContentLoaded', () => {
    if (!window.NX) {
        console.error('NX is not loaded before OltManagement.js');
        return;
    }

    const {
        api,
        ui,
        modal,
        util,
        forms,
        actions,
        jobs,
        dom,
        render,
        page,
        component,
        mount,
        datatable,
        storage
    } = window.NX;

    const { $, html, text } = dom;
    const { escape, safeArray } = util;

    const appEl = document.getElementById('oltManagementApp');
    if (!appEl) return;

    const pageMode = String(appEl.dataset.pageMode || 'devices').toLowerCase();

    const IMAGE_CANDIDATES = [
        '/assets/img/ma5800-x2.png',
        '/assets/img/MA5800-X2.png',
        '/assets/img/ma500x2.png',
        '/module-assets/OltManagement/img/ma5800-x2.png',
        '/module-assets/OltManagement/img/MA5800-X2.png',
        '/module-assets/OltManagement/img/ma500x2.png',
        '/module-assets/OltManagement/images/ma5800-x2.png',
        '/module-assets/OltManagement/images/MA5800-X2.png',
        '/module-assets/OltManagement/images/ma500x2.png'
    ];

    let RESOLVED_IMAGE_PATH = IMAGE_CANDIDATES[0];

    const SVG_VIEWBOX = { width: 787, height: 157 };
    const UI_STATE_KEY = 'nx.oltManagement.ui';
    const savedUiState = storage.get(UI_STATE_KEY, {}) || {};

    const CONTROL_CONFIG = {
        showLabels: true,
        showControlPortLabels: true
    };

    const PORT_MAP = {
        '0/1/15': { x: 92, y: 77, w: 32, h: 22 },
        '0/1/14': { x: 126, y: 77, w: 32, h: 22 },
        '0/1/13': { x: 160, y: 77, w: 32, h: 22 },
        '0/1/12': { x: 194, y: 77, w: 32, h: 22 },
        '0/1/11': { x: 228, y: 77, w: 31, h: 22 },
        '0/1/10': { x: 261, y: 77, w: 32, h: 22 },
        '0/1/9': { x: 294, y: 77, w: 32, h: 22 },
        '0/1/8': { x: 327, y: 77, w: 32, h: 22 },
        '0/1/7': { x: 361, y: 77, w: 32, h: 22 },
        '0/1/6': { x: 394, y: 77, w: 30, h: 22 },
        '0/1/5': { x: 426, y: 77, w: 30, h: 22 },
        '0/1/4': { x: 459, y: 77, w: 30, h: 22 },
        '0/1/3': { x: 489, y: 77, w: 32, h: 22 },
        '0/1/2': { x: 521, y: 77, w: 32, h: 22 },
        '0/1/1': { x: 553, y: 77, w: 32, h: 22 },
        '0/1/0': { x: 585, y: 77, w: 32, h: 22 },

        '0/2/15': { x: 92, y: 115, w: 32, h: 22 },
        '0/2/14': { x: 126, y: 115, w: 32, h: 22 },
        '0/2/13': { x: 160, y: 115, w: 32, h: 22 },
        '0/2/12': { x: 194, y: 115, w: 32, h: 22 },
        '0/2/11': { x: 228, y: 115, w: 32, h: 22 },
        '0/2/10': { x: 261, y: 115, w: 32, h: 22 },
        '0/2/9': { x: 294, y: 115, w: 32, h: 22 },
        '0/2/8': { x: 327, y: 115, w: 32, h: 22 },
        '0/2/7': { x: 361, y: 115, w: 30, h: 22 },
        '0/2/6': { x: 394, y: 115, w: 30, h: 22 },
        '0/2/5': { x: 426, y: 115, w: 30, h: 22 },
        '0/2/4': { x: 459, y: 115, w: 30, h: 22 },
        '0/2/3': { x: 489, y: 115, w: 32, h: 22 },
        '0/2/2': { x: 521, y: 115, w: 32, h: 22 },
        '0/2/1': { x: 553, y: 115, w: 32, h: 22 },
        '0/2/0': { x: 585, y: 115, w: 32, h: 22 }
    };

    const CONTROL_PORT_MAP = {
        '0/3/3': { x: 76, y: 38, w: 25, h: 16 },
        '0/3/2': { x: 106, y: 38, w: 25, h: 16 },
        '0/3/1': { x: 135, y: 38, w: 25, h: 16 },
        '0/3/0': { x: 164, y: 38, w: 25, h: 16 },

        '0/4/3': { x: 297, y: 38, w: 25, h: 16 },
        '0/4/2': { x: 325, y: 38, w: 25, h: 16 },
        '0/4/1': { x: 354, y: 38, w: 25, h: 16 },
        '0/4/0': { x: 383, y: 38, w: 25, h: 16 }
    };

    const PROFILE_ENDPOINTS = {
        dba: {
            label: 'DBA',
            list: '/api/v1/olt-management/dba-profiles',
            get: (id) => `/api/v1/olt-management/dba-profile/${id}`,
            create: '/api/v1/olt-management/dba-profile/create',
            update: (id) => `/api/v1/olt-management/dba-profile/update/${id}`,
            delete: '/api/v1/olt-management/dba-profile/delete',
            cli: (id) => `/api/v1/olt-management/dba-profile/${id}/cli-preview`
        },
        line: {
            label: 'Line',
            list: '/api/v1/olt-management/line-profiles',
            get: (id) => `/api/v1/olt-management/line-profile/${id}`,
            create: '/api/v1/olt-management/line-profile/create',
            update: (id) => `/api/v1/olt-management/line-profile/update/${id}`,
            delete: '/api/v1/olt-management/line-profile/delete',
            cli: (id) => `/api/v1/olt-management/line-profile/${id}/cli-preview`
        },
        wan: {
            label: 'WAN',
            list: '/api/v1/olt-management/wan-profiles',
            get: (id) => `/api/v1/olt-management/wan-profile/${id}`,
            create: '/api/v1/olt-management/wan-profile/create',
            update: (id) => `/api/v1/olt-management/wan-profile/update/${id}`,
            delete: '/api/v1/olt-management/wan-profile/delete',
            cli: (id) => `/api/v1/olt-management/wan-profile/${id}/cli-preview`
        },
        tr069: {
            label: 'TR069',
            list: '/api/v1/olt-management/tr069-profiles',
            get: (id) => `/api/v1/olt-management/tr069-profile/${id}`,
            create: '/api/v1/olt-management/tr069-profile/create',
            update: (id) => `/api/v1/olt-management/tr069-profile/update/${id}`,
            delete: '/api/v1/olt-management/tr069-profile/delete',
            cli: (id) => `/api/v1/olt-management/tr069-profile/${id}/cli-preview`
        },
        srv: {
            label: 'SRV',
            list: '/api/v1/olt-management/srv-profiles',
            get: (id) => `/api/v1/olt-management/srv-profile/${id}`,
            create: '/api/v1/olt-management/srv-profile/create',
            update: (id) => `/api/v1/olt-management/srv-profile/update/${id}`,
            delete: '/api/v1/olt-management/srv-profile/delete',
            cli: (id) => `/api/v1/olt-management/srv-profile/${id}/cli-preview`
        }
    };

    const VLAN_ENDPOINTS = {
        vlans: '/api/v1/vlan-management/vlans',
        mgmtVlans: '/api/v1/vlan-management/mgmt-vlans'
    };

   const CONTROL_VLAN_ENDPOINTS = {
    workspace: (oltId) => `/api/v1/olt-management/control-board/${oltId}/vlan-workspace`,
    options: (oltId, type, portId = 0) => {
        const params = new URLSearchParams({
            type: String(type || 'SERVICE'),
            port_id: String(portId || 0)
        });

        return `/api/v1/olt-management/control-board/${oltId}/vlan-options?${params.toString()}`;
        },
        create: '/api/v1/olt-management/control-board/vlan-bindings',
        delete: '/api/v1/olt-management/control-board/vlan-bindings/delete'
    };

    const PON_SVLAN_ENDPOINTS = {
        options: (oltId, portId = 0) => `/api/v1/olt-management/pon-port/${oltId}/svlan-options?port_id=${portId}`,
        save: '/api/v1/olt-management/pon-port/assign-svlan',
        remove: '/api/v1/olt-management/pon-port/unassign-svlan'
    };

    const PROFILE_RULES = {
        dbaMax: 1024000,
        srvMaxEthPorts: 4
    };

    const PROFILE_META = {
        dba: {
            title: 'DBA Profiles',
            subtitle: 'Creates: dba-profile add profile-id <id> profile-name "<name>" type4 max <max>',
            emptyTitle: 'No DBA profiles found',
            emptyText: 'Create the default DBA profile for this selected OLT.',
            fields: [
                { name: 'profile_id', label: 'Profile ID', type: 'number', required: true },
                { name: 'profile_name', label: 'Profile Name', type: 'text', required: true },
                { name: 'max_bandwidth', label: 'Max Bandwidth', type: 'number', required: true, placeholder: 'Max 1024000' },
                { name: 'description', label: 'Description', type: 'textarea' }
            ],
            columns: [
                {
                    key: 'profile_id',
                    label: 'Profile',
                    render: (_value, row) => `
                    <div class="nx-cell-stack">
                        <div class="nx-cell-title">${escape(row.profile_name || '-')}</div>
                        <div class="nx-cell-sub">ID ${escape(row.profile_id ?? '-')}</div>
                    </div>
                `
                },
                {
                    key: 'profile_type',
                    label: 'Type',
                    render: () => statusBadge('TYPE4', 'primary')
                },
                {
                    key: 'max_bandwidth',
                    label: 'Max',
                    render: (_value, row) => `<span class="nx-text-mono">${escape(row.max_bandwidth ?? row.max ?? '-')}</span>`
                },
                {
                    key: 'description',
                    label: 'Description',
                    render: (_value, row) => `
                        <div class="nx-cell-stack">
                            <div class="nx-cell-title">${escape(row.description || '-')}</div>
                        </div>
                    `
                }
            ]
        },

        line: {
            title: 'Line Profiles',
            subtitle: 'Creates fixed Huawei GPON line profile syntax with CVLAN and management VLAN mapping.',
            emptyTitle: 'No line profiles found',
            emptyText: 'Create the default line profile for this selected OLT.',
            fields: [
                { name: 'profile_id', label: 'Profile ID', type: 'number', required: true },
                { name: 'profile_name', label: 'Profile Name', type: 'text', required: true },
                { name: 'customer_cvlan', label: 'Customer CVLAN', type: 'select_dynamic', source: 'vlans', required: true, placeholder: 'Select customer VLAN' },
                { name: 'management_vlan', label: 'Management VLAN', type: 'select_dynamic', source: 'mgmtVlans', required: true, placeholder: 'Select management VLAN' },
                { name: 'dba_profile_id', label: 'DBA Profile ID', type: 'select_dynamic', source: 'dbaProfiles', required: true, placeholder: 'Select DBA profile' },
                { name: 'description', label: 'Description', type: 'textarea' }
            ],
            columns: [
                {
                    key: 'profile_id',
                    label: 'Profile',
                    render: (_value, row) => `
                    <div class="nx-cell-stack">
                        <div class="nx-cell-title">${escape(row.profile_name || '-')}</div>
                        <div class="nx-cell-sub">ID ${escape(row.profile_id ?? '-')}</div>
                    </div>
                `
                },
                {
                    key: 'customer_cvlan',
                    label: 'VLAN Mapping',
                    render: (_value, row) => `
                    <div class="nx-cell-stack">
                        <div class="nx-cell-title">Customer VLAN ${escape(row.customer_cvlan ?? '-')}</div>
                        <div class="nx-cell-sub">Management VLAN ${escape(row.management_vlan ?? '-')}</div>
                    </div>
                `
                },
                {
                    key: 'dba_profile_id',
                    label: 'DBA',
                    render: (_value, row) => `
                        <div class="nx-cell-stack">
                            <div class="nx-cell-title">${escape(row.dba_profile_name || `DBA ${row.dba_profile_id ?? '-'}`)}</div>
                            <div class="nx-cell-sub">ID ${escape(row.dba_profile_id ?? '-')}</div>
                        </div>
                    `
                },
                {
                    key: 'fixed',
                    label: 'Fixed Syntax',
                    render: () => `
                    <div class="nx-cell-stack">
                        <div class="nx-cell-title">TCONT 1 / GEM 1,2</div>
                        <div class="nx-cell-sub">OMCC encrypt on / TR069 enabled</div>
                    </div>
                `
                },
                {
                    key: 'description',
                    label: 'Description',
                    render: (_value, row) => `
                        <div class="nx-cell-stack">
                            <div class="nx-cell-title">${escape(row.description || '-')}</div>
                        </div>
                    `
                }
            ]
        },

        wan: {
            title: 'WAN Profiles',
            subtitle: 'Creates fixed WAN profiles: PPPoE with NAT enabled or DHCP without extra options.',
            emptyTitle: 'No WAN profiles found',
            emptyText: 'Create PPPoE-WAN or DHCP-WAN profile for this selected OLT.',
            fields: [
                { name: 'profile_id', label: 'Profile ID', type: 'number', required: true },
                { name: 'profile_name', label: 'Profile Name', type: 'text', required: true },
                {
                    name: 'wan_mode',
                    label: 'WAN Mode',
                    type: 'select',
                    options: [
                        { value: 'pppoe', label: 'PPPoE' },
                        { value: 'dhcp', label: 'DHCP' }
                    ]
                },
                { name: 'description', label: 'Description', type: 'textarea' }
            ],
            columns: [
                {
                    key: 'profile_id',
                    label: 'Profile',
                    render: (_value, row) => `
                    <div class="nx-cell-stack">
                        <div class="nx-cell-title">${escape(row.profile_name || '-')}</div>
                        <div class="nx-cell-sub">ID ${escape(row.profile_id ?? '-')}</div>
                    </div>
                `
                },
                {
                    key: 'wan_mode',
                    label: 'Mode',
                    render: (_value, row) => statusBadge(String(row.wan_mode || '-').toUpperCase(), 'primary')
                },
                {
                    key: 'nat_enable',
                    label: 'Syntax',
                    render: (_value, row) => {
                        const mode = String(row.wan_mode || '').toLowerCase();
                        return mode === 'pppoe'
                            ? statusBadge('NAT ENABLE', 'success')
                            : statusBadge('DEFAULT DHCP', 'slate');
                    }
                },
                {
                    key: 'description',
                    label: 'Description',
                    render: (_value, row) => `
                        <div class="nx-cell-stack">
                            <div class="nx-cell-title">${escape(row.description || '-')}</div>
                        </div>
                    `
                }
            ]
        },

        tr069: {
            title: 'TR069 Profiles',
            subtitle: 'Creates TR069 server profile with editable ACS URL, username, and password.',
            emptyTitle: 'No TR069 profiles found',
            emptyText: 'Create the default TR069 profile for this selected OLT.',
            fields: [
                { name: 'profile_id', label: 'Profile ID', type: 'number', required: true },
                { name: 'profile_name', label: 'Profile Name', type: 'text', required: true },
                { name: 'acs_url', label: 'ACS URL', type: 'text', required: true },
                { name: 'acs_username', label: 'ACS Username', type: 'text' },
                { name: 'acs_password', label: 'ACS Password', type: 'text' },
                { name: 'description', label: 'Description', type: 'textarea' }
            ],
            columns: [
                {
                    key: 'profile_id',
                    label: 'Profile',
                    render: (_value, row) => `
                    <div class="nx-cell-stack">
                        <div class="nx-cell-title">${escape(row.profile_name || '-')}</div>
                        <div class="nx-cell-sub">ID ${escape(row.profile_id ?? '-')}</div>
                    </div>
                `
                },
                {
                    key: 'acs_url',
                    label: 'ACS',
                    render: (_value, row) => `
                    <div class="nx-cell-stack">
                        <div class="nx-cell-title">${escape(row.acs_url || '-')}</div>
                        <div class="nx-cell-sub">${escape(row.acs_username || '-')}</div>
                    </div>
                `
                },
                {
                    key: 'description',
                    label: 'Description',
                    render: (_value, row) => `
                        <div class="nx-cell-stack">
                            <div class="nx-cell-title">${escape(row.description || '-')}</div>
                        </div>
                    `
                }
            ]
        },

        srv: {
            title: 'Service Profiles',
            subtitle: 'Creates fixed SRV profile with transparent ETH port VLAN mode.',
            emptyTitle: 'No service profiles found',
            emptyText: 'Create the default service profile for this selected OLT.',
            fields: [
                { name: 'profile_id', label: 'Profile ID', type: 'number', required: true },
                { name: 'profile_name', label: 'Profile Name', type: 'text', required: true },
                { name: 'eth_ports', label: 'ETH Ports', type: 'number', required: true, placeholder: 'Max Eth Ports is 4' },
                { name: 'description', label: 'Description', type: 'textarea' }
            ],
            columns: [
                {
                    key: 'profile_id',
                    label: 'Profile',
                    render: (_value, row) => `
                    <div class="nx-cell-stack">
                        <div class="nx-cell-title">${escape(row.profile_name || '-')}</div>
                        <div class="nx-cell-sub">ID ${escape(row.profile_id ?? '-')}</div>
                    </div>
                `
                },
                {
                    key: 'eth_ports',
                    label: 'ETH Ports',
                    render: (_value, row) => `<span class="nx-text-mono">${escape(row.eth_ports ?? '-')}</span>`
                },
                {
                    key: 'syntax',
                    label: 'Syntax',
                    render: () => statusBadge('TRANSPARENT', 'success')
                },
                {
                    key: 'description',
                    label: 'Description',
                    render: (_value, row) => `
                            <div class="nx-cell-stack">
                                <div class="nx-cell-title">${escape(row.description || '-')}</div>
                            </div>
                    `
                }
            ]
        }
    };

    const el = {
        subtitle: $('#oltPageSubtitle'),
        headerActions: $('#oltHeaderActions'),
        toolbarArea: $('#oltToolbarArea'),
        kpiStrip: $('#oltKpiStrip'),
        contextChips: $('#oltContextChips'),

        devicesView: $('#oltDevicesView'),
        physicalView: $('#oltPhysicalView'),
        slotDetailsView: $('#oltSlotDetailsView'),
        portsTableView: $('#oltPortsTableView'),

        devicesCard: $('#oltDevicesCard'),
        physicalCard: $('#oltPhysicalCard'),
        slotDetailsCard: $('#oltSlotDetailsCard'),
        portsTableCard: $('#oltPortsTableCard'),
        portsExperienceRow: $('#oltPortsExperienceRow'),

        profilesCard: $('#oltProfilesCard'),
        profilesWorkspace: $('#oltProfilesWorkspace'),
        profilesEmptyState: $('#oltProfilesEmptyState'),

        createDeviceForm: $('#createOltDeviceForm'),
        editDeviceForm: $('#editOltDeviceForm'),
        importFetchedPortsForm: $('#importFetchedPortsForm'),

        fetchModalOltId: $('#fetchModalOltId'),
        fetchPortsJson: $('#fetchPortsJson'),
        fetchSummaryText: $('#fetchSummaryText'),
        fetchPortsBoardsInfo: $('#fetchPortsBoardsInfo'),
        fetchedPortsTbody: document.querySelector('#fetchedPortsTable tbody'),
        importFetchedPortsSubmitBtn: $('#importFetchedPortsSubmitBtn'),

        viewDeviceSubtitle: $('#viewDeviceSubtitle'),
        viewDeviceName: $('#viewDeviceName'),
        viewDeviceIp: $('#viewDeviceIp'),
        viewDeviceVendor: $('#viewDeviceVendor'),
        viewDeviceUsername: $('#viewDeviceUsername'),
        viewDevicePassword: $('#viewDevicePassword'),

        viewDeviceOmci: $('#viewDeviceOmci'),
        viewDeviceOmciAutoDetect: $('#viewDeviceOmciAutoDetect'),

        createDeviceOmci: $('#createDeviceOmci'),
        createDeviceOmciAutoDetect: $('#createDeviceOmciAutoDetect'),

        editDeviceOmci: $('#editDeviceOmci'),
        editDeviceOmciAutoDetect: $('#editDeviceOmciAutoDetect'),

        editDeviceSubtitle: $('#editDeviceSubtitle'),
        editDeviceName: $('#editDeviceName'),
        editDeviceIp: $('#editDeviceIp'),
        editDeviceVendor: $('#editDeviceVendor'),
        editDeviceUsername: $('#editDeviceUsername'),
        editDevicePassword: $('#editDevicePassword'),

        viewPortSubtitle: $('#viewPortSubtitle'),
        viewPortOltName: $('#viewPortOltName'),
        viewPortBoard: $('#viewPortBoard'),
        viewPortPath: $('#viewPortPath'),
        viewPortType: $('#viewPortType'),
        viewPortLink: $('#viewPortLink'),
        viewPortOptic: $('#viewPortOptic'),
        viewPortSpeed: $('#viewPortSpeed'),
        viewPortDuplex: $('#viewPortDuplex'),
        viewPortActiveState: $('#viewPortActiveState'),
        viewPortDescription: $('#viewPortDescription'),
        viewPortSvlanLabel: $('#viewPortSvlanLabel'),
        viewPortSvlan: $('#viewPortSvlan'),
        viewPortOntCount: $('#viewPortOntCount'),
        viewPortOntOnline: $('#viewPortOntOnline'),
        viewPortOntCountWrap: $('#viewPortOntCountWrap'),
        viewPortOntOnlineWrap: $('#viewPortOntOnlineWrap'),
        controlVlanListModal: $('#controlVlanListModal'),
        controlVlanListSubtitle: $('#controlVlanListSubtitle'),
        controlVlanListBody: $('#controlVlanListBody'),

        createDeviceModal: $('#createDeviceModal'),
        viewDeviceModal: $('#viewDeviceModal'),
        editDeviceModal: $('#editDeviceModal'),
        viewPortModal: $('#viewPortModal'),
        fetchPortsModal: $('#fetchPortsModal')
    };

    let physicalPanelMount = null;
    let devicesDatatable = null;
    let portsDatatable = null;
    let profileTables = {
        dba: null,
        line: null,
        wan: null,
        tr069: null,
        srv: null
    };
    let lastPhysicalSignature = '';
    let lastPortsTableSignature = '';
    let lastProfileSignatures = {
        dba: '',
        line: '',
        wan: '',
        tr069: '',
        srv: ''
    };
    let currentOltState = {};

    async function resolveImagePath() {
        for (const candidate of IMAGE_CANDIDATES) {
            try {
                const img = new Image();
                const found = await new Promise((resolve) => {
                    img.onload = () => resolve(true);
                    img.onerror = () => resolve(false);
                    img.src = candidate + `?v=${Date.now()}`;
                });

                if (found) {
                    RESOLVED_IMAGE_PATH = candidate;
                    return candidate;
                }
            } catch (err) {
                console.warn('Image check failed for', candidate, err);
            }
        }

        RESOLVED_IMAGE_PATH = IMAGE_CANDIDATES[0];
        return RESOLVED_IMAGE_PATH;
    }

    function persistUiState(state) {
        storage.set(UI_STATE_KEY, {
            selectedOltId: state.selectedOltId,
            selectedSlot: state.selectedSlot,
            selectedPortPath: state.selectedPortPath,
            selectedDeviceId: state.selectedDeviceId,
            activeProfileTab: state.activeProfileTab || 'dba'
        });
    }

    function cacheDom() {
        el.createDeviceModal = $('#createDeviceModal');
        el.viewDeviceModal = $('#viewDeviceModal');
        el.editDeviceModal = $('#editDeviceModal');
        el.viewPortModal = $('#viewPortModal');
        el.fetchPortsModal = $('#fetchPortsModal');
        el.controlVlanListModal = $('#controlVlanListModal');
        el.controlVlanListSubtitle = $('#controlVlanListSubtitle');
        el.controlVlanListBody = $('#controlVlanListBody');

        el.profilesCard = $('#oltProfilesCard');
        el.profilesWorkspace = $('#oltProfilesWorkspace');
        el.profilesEmptyState = $('#oltProfilesEmptyState');
    }

    function ensureProfileModals() {
        if (!document.getElementById('oltProfileCrudModal')) {
            const shell = document.createElement('div');
            shell.innerHTML = `
                <div class="modal fade" id="oltProfileCrudModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-xl modal-dialog-scrollable">
                        <div class="modal-content nx-modal">
                            <div class="modal-header nx-modal-header">
                                <div>
                                    <h5 class="modal-title" id="oltProfileCrudModalTitle">Profile</h5>
                                    <div class="small text-muted" id="oltProfileCrudModalSubtitle">Manage provisioning profile</div>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body nx-modal-body">
                                <form id="oltProfileCrudForm">
                                    <input type="hidden" name="id" id="oltProfileCrudId">
                                    <input type="hidden" name="profile_type" id="oltProfileCrudType">
                                    <input type="hidden" name="olt_id" id="oltProfileCrudOltId">
                                    <div id="oltProfileCrudFields" class="row g-3"></div>
                                </form>
                            </div>
                            <div class="modal-footer nx-modal-footer">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary" id="oltProfileCrudSaveBtn">
                                    <i class="bi bi-save"></i>
                                    <span>Save</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="oltProfileViewModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <div class="modal-content nx-modal">
                            <div class="modal-header nx-modal-header">
                                <div>
                                    <h5 class="modal-title" id="oltProfileViewModalTitle">Profile Details</h5>
                                    <div class="small text-muted" id="oltProfileViewModalSubtitle">Profile information</div>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body nx-modal-body">
                                <div id="oltProfileViewContent"></div>
                            </div>
                            <div class="modal-footer nx-modal-footer">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="oltProfileCliModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-xl modal-dialog-scrollable">
                        <div class="modal-content nx-modal">
                            <div class="modal-header nx-modal-header">
                                <div>
                                    <h5 class="modal-title" id="oltProfileCliModalTitle">CLI Preview</h5>
                                    <div class="small text-muted" id="oltProfileCliModalSubtitle">Generated CLI configuration</div>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body nx-modal-body">
                                <div class="mb-3 d-flex justify-content-end">
                                    <button type="button" class="btn btn-light" id="oltProfileCliCopyBtn">
                                        <i class="bi bi-clipboard"></i>
                                        <span>Copy</span>
                                    </button>
                                </div>
                                <pre class="p-3 rounded border bg-light mb-0" style="white-space: pre-wrap; word-break: break-word;" id="oltProfileCliContent"></pre>
                            </div>
                            <div class="modal-footer nx-modal-footer">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(shell);
        }

        el.profileCrudModal = $('#oltProfileCrudModal');
        el.profileCrudForm = $('#oltProfileCrudForm');
        el.profileCrudModalTitle = $('#oltProfileCrudModalTitle');
        el.profileCrudModalSubtitle = $('#oltProfileCrudModalSubtitle');
        el.profileCrudFields = $('#oltProfileCrudFields');
        el.profileCrudId = $('#oltProfileCrudId');
        el.profileCrudType = $('#oltProfileCrudType');
        el.profileCrudOltId = $('#oltProfileCrudOltId');
        el.profileCrudSaveBtn = $('#oltProfileCrudSaveBtn');

        el.profileViewModal = $('#oltProfileViewModal');
        el.profileViewModalTitle = $('#oltProfileViewModalTitle');
        el.profileViewModalSubtitle = $('#oltProfileViewModalSubtitle');
        el.profileViewContent = $('#oltProfileViewContent');

        el.profileCliModal = $('#oltProfileCliModal');
        el.profileCliModalTitle = $('#oltProfileCliModalTitle');
        el.profileCliModalSubtitle = $('#oltProfileCliModalSubtitle');
        el.profileCliContent = $('#oltProfileCliContent');
        el.profileCliCopyBtn = $('#oltProfileCliCopyBtn');
    }

    function openModal(elm) {
        if (elm) modal.open(elm);
    }

    function closeModal(elm) {
        if (elm) modal.close(elm);
    }

    function withButtonLoading(button, label = 'Saving...') {
        if (!button) return () => {};

        const original = {
            disabled: button.disabled,
            html: button.innerHTML
        };

        button.disabled = true;
        button.innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>${escape(label)}`;

        return () => {
            button.disabled = original.disabled;
            button.innerHTML = original.html;
        };
    }

    function errorMessage(err, fallback = 'Request failed.') {
        if (!err) return fallback;
        if (typeof err === 'string') return err;
        if (err.message) return err.message;
        return fallback;
    }

    function getToggleValue(el) {
        if (!el) return '0';

        if (typeof el.checked !== 'undefined') {
            return el.checked ? '1' : '0';
        }

        return el.dataset.value === '1' ? '1' : '0';
    }

    function buildDeviceFormData(form) {
        const fd = new FormData(form);

        const omci = form.querySelector('[name="enable_home_gateway_omci"]');
        const auto = form.querySelector('[name="auto_detect_omci_support"]');

        fd.set('enable_home_gateway_omci', getToggleValue(omci));
        fd.set('auto_detect_omci_support', getToggleValue(auto));

        return fd;
    }

    function findDeviceById(rows, id) {
        return safeArray(rows).find((d) => Number(d.id) === Number(id)) || null;
    }

    function findPortById(rows, id) {
        return safeArray(rows).find((p) => Number(p.id) === Number(id)) || null;
    }

    function findPortByPath(rows, path) {
        return safeArray(rows).find((p) => String(p.port_path) === String(path)) || null;
    }

    function findPortBySlotAndPort(rows, slotNum, portNum) {
        return safeArray(rows).find((p) =>
            Number(p.slot) === Number(slotNum) &&
            Number(p.port) === Number(portNum)
        ) || null;
    }

    function findProfileById(rows, id) {
        return safeArray(rows).find((row) => Number(row.id) === Number(id)) || null;
    }

    function getSelectedDevice(ctx) {
        return findDeviceById(ctx.state.devices, ctx.state.selectedOltId);
    }

    function isControlBoardPort(row) {
    return String(row.board_type || '').toUpperCase() === 'CONTROL'
        || [3, 4].includes(Number(row.slot || 0));
}

function getControlPortBindings(portId, type = null) {
    let rows = safeArray(currentOltState.controlBoardVlanBindings)
        .filter((row) => Number(row.olt_port_id) === Number(portId));

    if (type) {
        rows = rows.filter((row) => String(row.vlan_type) === String(type));
    }

    return rows;
}

function renderControlVlanSummary(rows, type) {
    const filtered = safeArray(rows).filter((row) => String(row.vlan_type) === String(type));

    if (!filtered.length) {
        return '<span class="olt-control-vlan-none">None</span>';
    }

    return filtered.map((row) => `
        <span class="olt-control-vlan-tag ${type === 'MGMT' ? 'is-mgmt' : 'is-service'}">
            <span>VLAN ${escape(row.vlan_id)}</span>
            <button
                type="button"
                class="olt-control-vlan-tag-remove js-unbind-control-vlan"
                data-id="${Number(row.id)}"
                data-vlan-id="${escape(row.vlan_id)}"
                data-vlan-type="${escape(type)}"
                title="Unbind VLAN ${escape(row.vlan_id)}"
                aria-label="Unbind VLAN ${escape(row.vlan_id)}"
            >
                <i class="bi bi-x-lg"></i>
            </button>
        </span>
    `).join('');
}

    function renderControlVlanChips(rows, type) {
    const filtered = safeArray(rows).filter((row) => String(row.vlan_type) === String(type));

    if (!filtered.length) {
        return `
            <div class="olt-vlan-empty-chip">
                <i class="bi bi-dash-circle"></i>
                <span>None</span>
            </div>
        `;
    }

    return `
        <div class="olt-vlan-binding-list">
            ${filtered.map((row) => `
                <div class="olt-vlan-binding-card ${type === 'MGMT' ? 'is-mgmt' : 'is-service'}">
                    <div class="olt-vlan-binding-main">
                        <div class="olt-vlan-binding-icon">
                            <i class="bi ${type === 'MGMT' ? 'bi-hdd-network' : 'bi-layers'}"></i>
                        </div>

                        <div class="olt-vlan-binding-text">
                            <div class="olt-vlan-binding-title">
                                VLAN ${escape(row.vlan_id)}
                            </div>
                            <div class="olt-vlan-binding-sub">
                                ${escape(row.vlan_name || (type === 'MGMT' ? 'MGMT VLAN' : 'Service VLAN'))}
                            </div>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="btn btn-sm btn-outline-danger olt-vlan-unbind-btn js-unbind-control-vlan"
                        data-id="${Number(row.id)}"
                        data-vlan-id="${escape(row.vlan_id)}"
                        data-vlan-type="${escape(type)}"
                        title="Unbind VLAN"
                    >
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            `).join('')}
        </div>
    `;
}

    function getProfileRowsByTab(ctx, tab) {
        return safeArray(ctx.state[`${tab}Profiles`] || []);
    }

    function clearSelectedDeviceRowUi() {
        if (!el.devicesView) return;
        el.devicesView.querySelectorAll('.nx-row.is-selected').forEach((row) => {
            row.classList.remove('is-selected');
        });
    }

    function syncSelectedDeviceRowUi(ctx) {
        if (!el.devicesView) return;

        clearSelectedDeviceRowUi();

        const selectedDeviceId = Number(ctx.state.selectedDeviceId || 0);
        if (!selectedDeviceId) return;

        const row = el.devicesView.querySelector(`.nx-row[data-device-id="${selectedDeviceId}"]`);
        if (row) row.classList.add('is-selected');
    }

    function decorateDeviceRows(ctx) {
        if (!el.devicesView) return;

        const rows = el.devicesView.querySelectorAll('tbody tr');
        rows.forEach((row) => {
            const actionButton = row.querySelector(
                '.js-olt-open-ports, .js-olt-open-profiles, .js-olt-view-device, .js-olt-edit-device, .js-olt-delete-device'
            );

            const deviceId = Number(actionButton?.dataset.id || 0);
            if (!deviceId) return;

            row.classList.add('nx-row');
            row.dataset.deviceId = String(deviceId);
        });

        syncSelectedDeviceRowUi(ctx);
    }

    function getPortStatusClass(port) {
        if (!port) return 'empty';

        const link = String(port.link_status || '').toLowerCase();
        const optic = String(port.optic_status || '').toLowerCase();
        const boardStatus = String(port.board_status || '').toLowerCase();
        const ontOnline = Number(port.ont_online || 0);

        if (boardStatus && boardStatus !== 'normal' && boardStatus !== 'active' && boardStatus !== 'active_normal' && boardStatus !== 'standby_normal') {
            return 'warning';
        }
        if (link === 'online' && optic === 'online' && ontOnline > 0) return 'online';
        if (link === 'online' && optic === 'online' && ontOnline === 0) return 'no-ont';
        return 'offline';
    }

    function statusBadge(value, type = 'info') {
        const v = String(value || '-').trim() || '-';
        return `<span class="nx-soft-badge nx-soft-badge-${type}">${escape(v)}</span>`;
    }

    function omciBadge(row) {
        const omci = Number(row.enable_home_gateway_omci || 0) === 1;
        const auto = Number(row.auto_detect_omci_support || 0) === 1;

        if (omci && auto) {
            return `
            <div class="nx-cell-stack">
                ${statusBadge('OMCI ON', 'success')}
                <div class="nx-cell-sub">Auto Detect ON</div>
            </div>
        `;
        }

        if (omci && !auto) {
            return `
            <div class="nx-cell-stack">
                ${statusBadge('OMCI ON', 'primary')}
                <div class="nx-cell-sub">Auto Detect OFF</div>
            </div>
        `;
        }

        return `
        <div class="nx-cell-stack">
            ${statusBadge('OMCI OFF', 'slate')}
            <div class="nx-cell-sub">Not applied</div>
        </div>
    `;
    }

    function mapLinkBadge(value) {
        const v = String(value || '').toLowerCase();
        if (v === 'online') return statusBadge('Online', 'success');
        if (v === 'offline') return statusBadge('Offline', 'danger');
        return statusBadge(value || '-', 'slate');
    }

    function mapOpticBadge(value) {
        const v = String(value || '').toLowerCase();
        if (v === 'online') return statusBadge('Online', 'success');
        if (v === 'offline') return statusBadge('Offline', 'danger');
        return statusBadge(value || '-', 'slate');
    }

    function mapBoardBadge(value) {
        const v = String(value || '').toLowerCase();
        if (v.includes('normal') || v.includes('active')) return statusBadge(value || 'Normal', 'success');
        if (v.includes('standby')) return statusBadge(value || 'Standby', 'warning');
        if (v.includes('failed')) return statusBadge(value || 'Failed', 'danger');
        return statusBadge(value || '-', 'slate');
    }

    function getControlPortRect(slotNum, portNum) {
        return CONTROL_PORT_MAP[`0/${slotNum}/${portNum}`] || null;
    }

    function renderSvgPortGroup(state, slotNum, portNum, rect, path, extraClass = '') {
        if (!rect) return '';

        const port = findPortByPath(state.ports, path) || findPortBySlotAndPort(state.ports, slotNum, portNum);
        const status = getPortStatusClass(port);
        const selectedClass = state.selectedPortPath === path ? 'is-selected-port' : '';
        const slotSelectedClass = state.selectedSlot === slotNum ? 'is-selected-slot' : '';
        const isControlPort = extraClass.includes('is-control-port');
        const showLabel = isControlPort ? CONTROL_CONFIG.showControlPortLabels : CONTROL_CONFIG.showLabels;
        const tooltipText = port?.port_path || path;

        return `
            <g class="ma5800-svg-port-group ${status} ${slotSelectedClass} ${selectedClass} ${extraClass}"
               data-slot="${escape(slotNum)}"
               data-port="${escape(portNum)}"
               data-port-path="${escape(path)}">
                <rect class="ma5800-svg-port-rect" x="${rect.x}" y="${rect.y}" width="${rect.w}" height="${rect.h}" rx="2.5" ry="2.5"></rect>
                ${showLabel ? `
                    <text class="ma5800-svg-port-label" x="${rect.x + (rect.w / 2)}" y="${rect.y + rect.h - 2.5}" text-anchor="middle">${escape(portNum)}</text>
                ` : ''}
                <title>${escape(tooltipText)}</title>
            </g>
        `;
    }

    function renderAllGponPorts(state) {
        let out = '';
        for (let port = 15; port >= 0; port--) {
            out += renderSvgPortGroup(state, 1, port, PORT_MAP[`0/1/${port}`], `0/1/${port}`);
        }
        for (let port = 15; port >= 0; port--) {
            out += renderSvgPortGroup(state, 2, port, PORT_MAP[`0/2/${port}`], `0/2/${port}`);
        }
        return out;
    }

    function renderAllControlPorts(state) {
        let out = '';
        for (let port = 3; port >= 0; port--) {
            out += renderSvgPortGroup(state, 3, port, getControlPortRect(3, port), `0/3/${port}`, 'is-control-port');
        }
        for (let port = 3; port >= 0; port--) {
            out += renderSvgPortGroup(state, 4, port, getControlPortRect(4, port), `0/4/${port}`, 'is-control-port');
        }
        return out;
    }

    function syncPhysicalSelection(ctx) {
        if (!el.physicalView) return;

        const groups = el.physicalView.querySelectorAll('.ma5800-svg-port-group');
        groups.forEach((group) => {
            const slot = Number(group.dataset.slot || 0);
            const portPath = group.dataset.portPath || '';

            group.classList.toggle('is-selected-slot', ctx.state.selectedSlot !== null && slot === Number(ctx.state.selectedSlot));
            group.classList.toggle('is-selected-port', !!ctx.state.selectedPortPath && portPath === String(ctx.state.selectedPortPath));
        });
    }

    function setActiveProfileTab(ctx, tab) {
        const allowed = ['dba', 'line', 'wan', 'tr069', 'srv'];
        const nextTab = allowed.includes(String(tab || '').toLowerCase()) ? String(tab).toLowerCase() : 'dba';
        ctx.patch({ activeProfileTab: nextTab });
    }

    function syncProfileTabUi(ctx) {
        const activeTab = String(ctx.state.activeProfileTab || 'dba');

        document.querySelectorAll('[data-profile-tab]').forEach((btn) => {
            btn.classList.toggle('active', btn.dataset.profileTab === activeTab);
        });

        document.querySelectorAll('[data-profile-pane]').forEach((pane) => {
            pane.classList.toggle('d-none', pane.dataset.profilePane !== activeTab);
        });
    }

    function ensureProfilePane(tab) {
        const existing = document.querySelector(`[data-profile-pane="${tab}"]`);
        if (existing) return existing;
        if (!el.profilesWorkspace) return null;

        const pane = document.createElement('div');
        pane.dataset.profilePane = tab;
        pane.className = tab === 'dba' ? '' : 'd-none';
        el.profilesWorkspace.appendChild(pane);
        return pane;
    }

    function buildProfileTableColumns(tab) {
        const meta = PROFILE_META[tab];
        const cols = safeArray(meta.columns).map((col) => ({
            key: col.key,
            label: col.label,
            render: col.render
        }));

        cols.push({
            key: 'actions',
            label: 'Actions',
            render: (_value, row) => `
                <div class="nx-olt-actions">
                    <button type="button" class="btn btn-outline-secondary nx-icon-btn js-profile-view" data-tab="${escape(tab)}" data-id="${Number(row.id)}" title="View">
                        <i class="bi bi-eye"></i>
                    </button>
                    <button type="button" class="btn btn-outline-primary nx-icon-btn js-profile-edit" data-tab="${escape(tab)}" data-id="${Number(row.id)}" title="Edit">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button type="button" class="btn btn-outline-info nx-icon-btn js-profile-cli" data-tab="${escape(tab)}" data-id="${Number(row.id)}" title="CLI Preview">
                        <i class="bi bi-terminal"></i>
                    </button>
                    <button type="button" class="btn btn-outline-danger nx-icon-btn js-profile-delete" data-tab="${escape(tab)}" data-id="${Number(row.id)}" title="Delete">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            `
        });

        return cols;
    }

    function ensureProfileDatatable(tab, container) {
        if (profileTables[tab] || !container) return;

        profileTables[tab] = datatable.create({
            el: container,
            rows: [],
            search: false,
            paginate: true,
            pager: { currentPage: 1, rowsPerPage: 20 },
            sort: { key: 'profile_id', dir: 'asc' },
            columns: buildProfileTableColumns(tab)
        });
    }

    function buildFieldControl(field, value = '', ctx = null, row = null, tab = '') {
        const id = `profile-field-${field.name}`;
        const safeValue = value ?? '';
        const placeholder = field.placeholder ? `placeholder="${escape(field.placeholder)}"` : '';

        if (field.type === 'textarea') {
            return `
            <div class="col-12">
                <label class="form-label">${escape(field.label)}</label>
                <textarea class="form-control" name="${escape(field.name)}" id="${escape(id)}" rows="3" ${placeholder}>${escape(safeValue)}</textarea>
            </div>
        `;
        }

        if (field.type === 'select_dynamic') {
            let rows = [];

            if (ctx && field.source === 'vlans') rows = safeArray(ctx.state.vlans);
            if (ctx && field.source === 'mgmtVlans') rows = safeArray(ctx.state.mgmtVlans);
            if (ctx && field.source === 'dbaProfiles') rows = safeArray(ctx.state.dbaProfiles);

            if (tab === 'line' && field.name === 'customer_cvlan') {
                const currentCvlan = String(row?.customer_cvlan ?? '');
                const usedCvlanSet = new Set(
                    safeArray(ctx?.state?.lineProfiles)
                        .filter((item) => Number(item.id) !== Number(row?.id || 0))
                        .map((item) => String(item.customer_cvlan ?? '').trim())
                        .filter(Boolean)
                );

                rows = rows.filter((item) => {
                    const vlanValue =
                        item.vlan_id ??
                        item.cvlan ??
                        item.mgmt_vlan ??
                        item.management_vlan ??
                        item.vlan ??
                        item.profile_id ??
                        item.id;

                    const vlanString = String(vlanValue ?? '').trim();

                    return vlanString === currentCvlan || !usedCvlanSet.has(vlanString);
                });
            }

            const options = rows.map((item) => {
                const optionValue =
                    item.vlan_id ??
                    item.cvlan ??
                    item.mgmt_vlan ??
                    item.management_vlan ??
                    item.vlan ??
                    item.profile_id ??
                    item.id;

                const label = item.name || item.profile_name || item.description || `${field.label} ${optionValue}`;

                return `
                <option value="${escape(optionValue)}" ${String(optionValue) === String(safeValue) ? 'selected' : ''}>
                    ${escape(label)} (${escape(optionValue)})
                </option>
            `;
            }).join('');

            return `
            <div class="col-12 col-md-6">
                <label class="form-label">${escape(field.label)}</label>
                <select class="form-select" name="${escape(field.name)}" id="${escape(id)}" ${field.required ? 'required' : ''}>
                    <option value="">${escape(field.placeholder || 'Select option')}</option>
                    ${options}
                </select>
            </div>
        `;
        }

        if (field.type === 'select') {
            const options = safeArray(field.options).map((opt) => `
            <option value="${escape(opt.value)}" ${String(opt.value) === String(safeValue) ? 'selected' : ''}>${escape(opt.label)}</option>
        `).join('');

            return `
            <div class="col-12 col-md-6">
                <label class="form-label">${escape(field.label)}</label>
                <select class="form-select" name="${escape(field.name)}" id="${escape(id)}">
                    ${options}
                </select>
            </div>
        `;
        }

        return `
        <div class="col-12 col-md-6">
            <label class="form-label">${escape(field.label)}</label>
            <input
                type="${escape(field.type || 'text')}"
                class="form-control"
                name="${escape(field.name)}"
                id="${escape(id)}"
                value="${escape(safeValue)}"
                ${placeholder}
                ${field.required ? 'required' : ''}
            >
        </div>
    `;
    }

    function buildProfileForm(tab, row = null, ctx = null) {
        const meta = PROFILE_META[tab];

        return safeArray(meta.fields).map((field) => {
            let value = row?.[field.name] ?? '';

            if (!row) {
                if (tab === 'wan' && field.name === 'wan_mode') {
                    value = 'pppoe';
                }
            }

            return buildFieldControl(field, value, ctx, row, tab);
        }).join('');
    }

    function buildViewContent(row, tab = '') {
        const hiddenByTab = {
            srv: [
                'pots_port_count',
                'catv_enable',
                'eth_ports'
            ]
        };

        const hiddenCommon = [
            'password'
        ];

        const hidden = [
            ...hiddenCommon,
            ...(hiddenByTab[tab] || [])
        ];

        const entries = Object.entries(row || {})
            .filter(([key]) => !hidden.includes(key))
            .map(([key, value]) => `
            <div class="col-12 col-md-6">
                <div class="border rounded p-3 h-100">
                    <div class="small text-muted mb-1">${escape(key)}</div>
                    <div class="fw-semibold">${escape(value === null || value === '' ? '-' : value)}</div>
                </div>
            </div>
        `).join('');

        return `<div class="row g-3">${entries}</div>`;
    }

    function serializeProfileForm(ctx) {
        const fd = new FormData(el.profileCrudForm);
        const obj = {};

        fd.forEach((value, key) => {
            obj[key] = value;
        });

        const tab = String(el.profileCrudType.value || '');

        obj.olt_id = String(Number(ctx.state.selectedOltId || 0));

        if (tab === 'dba') {
            if (Number(obj.max_bandwidth || 0) > PROFILE_RULES.dbaMax) {
                ui.toast('error', `DBA Max Bandwidth must not exceed ${PROFILE_RULES.dbaMax}.`);
                return null;
            }

            obj.profile_type = 'type4';
            obj.bandwidth_unit = 'kbit';
            obj.assure = '0';
            obj.priority = '0';
        }

        if (tab === 'line') {
            obj.omcc_encrypt = '1';
            obj.tr069_management_enable = '1';
            obj.tr069_ip_index = '1';
            obj.tcont_id = '1';
            obj.gem_subscriber_id = '1';
            obj.gem_management_id = '2';
        }

        if (tab === 'wan') {
            const mode = String(obj.wan_mode || 'pppoe').toLowerCase();

            obj.ip_mode = 'ipv4';
            obj.vlan_mode = 'transparent';
            obj.service_type = 'INTERNET';
            obj.mtu = mode === 'pppoe' ? '1492' : '1500';
            obj.priority = '0';
            obj.bind_lan_ports = '';
            obj.nat_enable = mode === 'pppoe' ? '1' : '0';
        }

        if (tab === 'tr069') {
            obj.inform_enable = '1';
            obj.inform_interval = '300';
            obj.connection_request_enable = '1';
        }

        if (tab === 'srv') {
            if (Number(obj.eth_ports || 0) > PROFILE_RULES.srvMaxEthPorts) {
                ui.toast('error', `Service Profile ETH Ports must not exceed ${PROFILE_RULES.srvMaxEthPorts}.`);
                return null;
            }

            obj.eth_port_count = obj.eth_ports || '4';
            obj.service_mode = 'TRANSPARENT';

            delete obj.pots_ports;
            delete obj.catv_enable;
            delete obj.wifi_enable;
            delete obj.port_vlan_mode;
            delete obj.native_vlan;
        }

        delete obj.id;

        return obj;
    }

    async function loadVlanOptions(ctx) {
        try {
            const [vlans, mgmtVlans] = await Promise.all([
                api.get(VLAN_ENDPOINTS.vlans),
                api.get(VLAN_ENDPOINTS.mgmtVlans)
            ]);

            ctx.patch({
                vlans: safeArray(vlans),
                mgmtVlans: safeArray(mgmtVlans)
            });
        } catch (err) {
            console.warn('Failed to load VLAN dropdown options', err);
            ctx.patch({ vlans: [], mgmtVlans: [] });
        }
    }

    async function loadDevices(ctx) {
        ctx.patch({ loading: { ...ctx.state.loading, devices: true } });
        try {
            const data = await api.get('/api/v1/olt-management/devices');
            ctx.patch({ devices: safeArray(data) });
        } finally {
            ctx.patch({ loading: { ...ctx.state.loading, devices: false } });
        }
    }

    async function loadPorts(ctx, oltId = null) {
        const targetOltId = Number((oltId ?? ctx.state.selectedOltId) || 0);

        if (!targetOltId) {
            ctx.patch({ ports: [], loading: { ...ctx.state.loading, ports: false } });
            return;
        }

        ctx.patch({ loading: { ...ctx.state.loading, ports: true } });

        try {
            const data = await api.get(`/api/v1/olt-management/ports/by-olt/${targetOltId}`);
            ctx.patch({ ports: safeArray(data) });
        } catch (err) {
            ui.toast('error', err?.message || 'Failed to load ports');
        } finally {
            ctx.patch({ loading: { ...ctx.state.loading, ports: false } });
        }
    }

    async function loadControlBoardVlanWorkspace(ctx, oltId = null) {
    const targetOltId = Number((oltId ?? ctx.state.selectedOltId) || 0);

    if (!targetOltId) {
        ctx.patch({
            controlBoardPorts: [],
            controlBoardVlanBindings: [],
            loading: { ...ctx.state.loading, controlBoardVlans: false }
        });
        return;
    }

    ctx.patch({
        loading: { ...ctx.state.loading, controlBoardVlans: true }
    });

    try {
        const res = await api.get(CONTROL_VLAN_ENDPOINTS.workspace(targetOltId));
        const payload = res?.data || res || {};

        ctx.patch({
            controlBoardPorts: safeArray(payload.ports),
            controlBoardVlanBindings: safeArray(payload.bindings)
        });
    } catch (err) {
        ui.toast('error', errorMessage(err, 'Failed to load control board VLAN bindings.'));
        ctx.patch({
            controlBoardPorts: [],
            controlBoardVlanBindings: []
        });
    } finally {
        ctx.patch({
            loading: { ...ctx.state.loading, controlBoardVlans: false }
        });
    }
}

    async function loadControlBoardVlanOptions(ctx, vlanType) {
    const targetOltId = Number(ctx.state.selectedOltId || 0);
    const portId = Number($('#controlBoardVlanPortId')?.value || 0);

    if (!targetOltId) {
        ctx.patch({ controlBoardVlanOptions: [] });
        return [];
    }

    try {
        const res = await api.get(CONTROL_VLAN_ENDPOINTS.options(targetOltId, vlanType, portId));
        const rows = safeArray(res?.data || res);

        ctx.patch({ controlBoardVlanOptions: rows });
        return rows;
    } catch (err) {
        ui.toast('error', errorMessage(err, 'Failed to load VLAN options.'));
        ctx.patch({ controlBoardVlanOptions: [] });
        return [];
    }
}

    async function loadProfileRows(ctx, tab, oltId = null) {
        const targetOltId = Number((oltId ?? ctx.state.selectedOltId) || 0);
        const endpoint = PROFILE_ENDPOINTS[tab];

        if (!endpoint) return;

        if (!targetOltId) {
            ctx.patch({
                [`${tab}Profiles`]: [],
                loading: { ...ctx.state.loading, [`${tab}Profiles`]: false }
            });
            return;
        }

        ctx.patch({
            loading: { ...ctx.state.loading, [`${tab}Profiles`]: true }
        });

        try {
            let data = safeArray(await api.get(`${endpoint.list}?olt_id=${targetOltId}`));

            if (tab === 'line') {
                const dbaRows = safeArray(ctx.state.dbaProfiles);

                data = data.map((line) => {
                    const matchedDba = dbaRows.find((dba) =>
                        Number(dba.profile_id) === Number(line.dba_profile_id)
                    );

                    return {
                        ...line,
                        dba_profile_name: matchedDba?.profile_name || ''
                    };
                });
            }

            ctx.patch({ [`${tab}Profiles`]: data });
        } catch (err) {
            console.warn(`Failed to load ${tab} profiles`, err);
            ctx.patch({ [`${tab}Profiles`]: [] });
        } finally {
            ctx.patch({
                loading: { ...ctx.state.loading, [`${tab}Profiles`]: false }
            });
        }
    }

    async function refreshActiveProfileTab(ctx) {
        const tab = String(ctx.state.activeProfileTab || 'dba');
        await loadProfileRows(ctx, tab, ctx.state.selectedOltId);
    }

    function bindHeaderActions(ctx) {
        const openCreateBtn = $('#openCreateDeviceBtn');
        if (openCreateBtn) {
            openCreateBtn.onclick = (e) => {
                e.preventDefault();

                if (el.createDeviceForm) {
                    forms.reset(el.createDeviceForm);
                }

                if (el.createDeviceOmci) {
                    el.createDeviceOmci.checked = true;
                }

                if (el.createDeviceOmciAutoDetect) {
                    el.createDeviceOmciAutoDetect.checked = true;
                }

                openModal(el.createDeviceModal);
            };
        }

        const refreshDevicesBtn = $('#refreshDevicesBtn');
        if (refreshDevicesBtn) {
            refreshDevicesBtn.onclick = async (e) => {
                e.preventDefault();
                try {
                    await loadDevices(ctx);
                    ui.toast('success', 'Devices refreshed');
                } catch (err) {
                    ui.toast('error', errorMessage(err, 'Failed to refresh devices.'));
                }
            };
        }

        const fetchPortsBtn = $('#fetchPortsBtn');
        if (fetchPortsBtn) {
            fetchPortsBtn.onclick = async (e) => {
                e.preventDefault();
                await fetchPortsFromOlt(ctx);
            };
        }

        const refreshPortsBtn = $('#refreshPortsBtn');
        if (refreshPortsBtn) {
            refreshPortsBtn.onclick = async (e) => {
                e.preventDefault();
                try {
                    await loadPorts(ctx, ctx.state.selectedOltId);
                    await loadControlBoardVlanWorkspace(ctx, ctx.state.selectedOltId);
                    ui.toast('success', 'Ports refreshed');
                } catch (err) {
                    ui.toast('error', errorMessage(err, 'Failed to refresh ports.'));
                }
            };
        }

        const refreshProfilesBtn = $('#refreshProfilesBtn');
        if (refreshProfilesBtn) {
            refreshProfilesBtn.onclick = async (e) => {
                e.preventDefault();
                try {
                    await loadDevices(ctx);

                    if (Number(ctx.state.selectedOltId) > 0) {
                        await loadVlanOptions(ctx);
                        await loadProfileRows(ctx, 'dba', ctx.state.selectedOltId);
                        await refreshActiveProfileTab(ctx);
                    }

                    ui.toast('success', 'Profiles workspace refreshed');
                } catch (err) {
                    ui.toast('error', errorMessage(err, 'Failed to refresh workspace.'));
                }
            };
        }
    }

    function renderHeaderActions(ctx) {
        if (!el.headerActions) return;

        if (pageMode === 'devices') {
            html(el.headerActions, `
                <button class="btn btn-primary nx-header-btn" type="button" id="openCreateDeviceBtn">
                    <i class="bi bi-plus-lg"></i>
                    <span>Add Device</span>
                </button>
                <button class="btn btn-light nx-header-btn" type="button" id="refreshDevicesBtn">
                    <i class="bi bi-arrow-clockwise"></i>
                    <span>Refresh</span>
                </button>
            `);

            text(el.subtitle, 'Device registry and connection profiles');
            return;
        }

        if (pageMode === 'ports') {
            html(el.headerActions, `
                <a href="/olt-management" class="btn btn-light nx-header-btn" id="backToDevicesBtn">
                    <i class="bi bi-arrow-left"></i>
                    <span>Back to Devices</span>
                </a>
                <button class="btn btn-outline-primary nx-header-btn" type="button" id="fetchPortsBtn">
                    <i class="bi bi-cloud-download"></i>
                    <span>Fetch from OLT</span>
                </button>
                <button class="btn btn-light nx-header-btn" type="button" id="refreshPortsBtn">
                    <i class="bi bi-arrow-clockwise"></i>
                    <span>Refresh</span>
                </button>
            `);

            text(el.subtitle, 'Full physical view and operational port management');
            return;
        }

        if (pageMode === 'profiles') {
            const selectedOltId = Number(ctx.state.selectedOltId || 0);

            html(el.headerActions, `
                <a href="/olt-management" class="btn btn-light nx-header-btn">
                    <i class="bi bi-arrow-left"></i>
                    <span>Back to Devices</span>
                </a>
                ${selectedOltId > 0 ? `
                    <a href="/olt-management/ports?olt_id=${selectedOltId}" class="btn btn-outline-primary nx-header-btn">
                        <i class="bi bi-grid-3x3-gap"></i>
                        <span>Open Ports</span>
                    </a>
                ` : ''}
                <button class="btn btn-light nx-header-btn" type="button" id="refreshProfilesBtn">
                    <i class="bi bi-arrow-clockwise"></i>
                    <span>Refresh</span>
                </button>
            `);

            text(el.subtitle, 'Manage DBA, Line, WAN, TR069, and Service profiles for the selected OLT');
        }
    }

    function renderToolbar(ctx) {
        if (!el.toolbarArea) return;

        if (pageMode === 'devices') {
            html(el.toolbarArea, `
                <div class="olt-toolbar-grid">
                    <div class="olt-toolbar-block">
                        <label class="olt-toolbar-label">Search Devices</label>
                        <div class="olt-search-wrap">
                            <i class="bi bi-search olt-search-icon"></i>
                            <input type="text" class="form-control olt-toolbar-control olt-search-control" id="oltDevicesSearch" placeholder="Search by name, IP, vendor, username">
                        </div>
                    </div>
                    <div class="olt-toolbar-block">
                        <label class="olt-toolbar-label">Notes</label>
                        <div class="olt-toolbar-note">
                            Register access credentials here, then open the ports console for discovery, monitoring, and profile management.
                        </div>
                    </div>
                    <div class="olt-toolbar-meta">
                        <span id="oltDevicesTableMeta">${safeArray(ctx.state.devices).length} device${safeArray(ctx.state.devices).length === 1 ? '' : 's'}</span>
                    </div>
                </div>
            `);

            const searchInput = $('#oltDevicesSearch');
            if (searchInput && devicesDatatable) {
                searchInput.value = devicesDatatable.state?.search || '';
                searchInput.addEventListener('input', (e) => {
                    devicesDatatable.search(e.target.value || '');
                    bindDevicesTableActions(ctx);
                });
            }
            return;
        }

        if (pageMode === 'ports') {
            const options = [
                `<option value="" ${Number(ctx.state.selectedOltId) <= 0 ? 'selected' : ''}>Select OLT Device</option>`,
                ...safeArray(ctx.state.devices).map((d) => `
                    <option value="${Number(d.id)}" ${Number(d.id) === Number(ctx.state.selectedOltId) ? 'selected' : ''}>
                        ${escape(d.name)} - ${escape(d.ip_address)}
                    </option>
                `)
            ].join('');

            html(el.toolbarArea, `
                <div class="olt-toolbar-grid">
                    <div class="olt-toolbar-block">
                        <label class="olt-toolbar-label">OLT Device</label>
                        <select id="oltFilter" class="form-select olt-toolbar-control">
                            ${options}
                        </select>
                    </div>
                    <div class="olt-toolbar-block">
                        <label class="olt-toolbar-label">Ports Search</label>
                        <div class="olt-search-wrap">
                            <i class="bi bi-search olt-search-icon"></i>
                            <input type="text" class="form-control olt-toolbar-control olt-search-control" id="oltPortsSearch" placeholder="Search by port path, board, status, type">
                        </div>
                    </div>
                    <div class="olt-toolbar-meta">
                        ${(ctx.state.selectedPortPath || ctx.state.selectedSlot !== null) ? `
                            <button class="btn btn-light nx-header-btn js-clear-port-filter" type="button">
                                <i class="bi bi-x-circle"></i>
                                <span>Clear Selection</span>
                            </button>
                        ` : `
                            <span id="oltPortsTableMeta">${safeArray(ctx.state.ports).length} port${safeArray(ctx.state.ports).length === 1 ? '' : 's'}</span>
                        `}
                    </div>
                </div>
            `);

            const searchInput = $('#oltPortsSearch');
            if (searchInput && portsDatatable) {
                searchInput.value = portsDatatable.state?.search || '';
                searchInput.addEventListener('input', (e) => portsDatatable.search(e.target.value || ''));
            }
            return;
        }

        if (pageMode === 'profiles') {
            const options = [
                `<option value="" ${Number(ctx.state.selectedOltId) <= 0 ? 'selected' : ''}>Select OLT Device</option>`,
                ...safeArray(ctx.state.devices).map((d) => `
                    <option value="${Number(d.id)}" ${Number(d.id) === Number(ctx.state.selectedOltId) ? 'selected' : ''}>
                        ${escape(d.name)} - ${escape(d.ip_address)}
                    </option>
                `)
            ].join('');

            html(el.toolbarArea, `
                <div class="olt-toolbar-grid">
                    <div class="olt-toolbar-block">
                        <label class="olt-toolbar-label">OLT Device</label>
                        <select id="profilesOltSelector" class="form-select olt-toolbar-control">
                            ${options}
                        </select>
                    </div>
                    <div class="olt-toolbar-block">
                        <label class="olt-toolbar-label">Workspace Notes</label>
                        <div class="olt-toolbar-note">
                            Profiles are now OLT-specific only. Select one OLT, then manage DBA, Line, WAN, TR069, and SRV objects inside one workspace.
                        </div>
                    </div>
                    <div class="olt-toolbar-meta">
                        <span class="nx-soft-badge ${Number(ctx.state.selectedOltId) > 0 ? 'nx-soft-badge-success' : 'nx-soft-badge-slate'}">
                            ${Number(ctx.state.selectedOltId) > 0 ? `OLT ID ${Number(ctx.state.selectedOltId)}` : 'No OLT Selected'}
                        </span>
                    </div>
                </div>
            `);
        }
    }

    function renderCards(ctx) {
        if (el.devicesCard) el.devicesCard.classList.toggle('d-none', pageMode !== 'devices');
        if (el.physicalCard) el.physicalCard.classList.toggle('d-none', pageMode !== 'ports');
        if (el.portsExperienceRow) el.portsExperienceRow.classList.toggle('d-none', pageMode !== 'ports');
        if (el.portsTableCard) el.portsTableCard.classList.toggle('d-none', pageMode !== 'ports');
        if (el.profilesCard) el.profilesCard.classList.toggle('d-none', pageMode !== 'profiles');
        if (el.slotDetailsCard) el.slotDetailsCard.classList.add('d-none');
    }

    function renderKpis(ctx) {
        if (!el.kpiStrip) return;

        if (pageMode === 'devices') {
            const devices = safeArray(ctx.state.devices);
            const total = devices.length;
            const vendors = new Set(devices.map((d) => String(d.vendor || '').trim()).filter(Boolean));
            const withIp = devices.filter((d) => String(d.ip_address || '').trim() !== '').length;
            const withCreds = devices.filter((d) => String(d.username || '').trim() !== '' && String(d.password || '').trim() !== '').length;

            html(el.kpiStrip, `
                <div class="col-12 col-md-6 col-xl-3"><div class="nx-summary-card nx-summary-primary"><div class="nx-summary-top"><div><div class="nx-summary-label">Total Devices</div><div class="nx-summary-value">${total}</div><div class="nx-summary-text">All registered OLT profiles available for operations.</div></div><div class="nx-summary-icon"><i class="bi bi-hdd-network"></i></div></div></div></div>
                <div class="col-12 col-md-6 col-xl-3"><div class="nx-summary-card nx-summary-success"><div class="nx-summary-top"><div><div class="nx-summary-label">With IP Address</div><div class="nx-summary-value">${withIp}</div><div class="nx-summary-text">Connection targets ready for port discovery or sync.</div></div><div class="nx-summary-icon"><i class="bi bi-globe2"></i></div></div></div></div>
                <div class="col-12 col-md-6 col-xl-3"><div class="nx-summary-card nx-summary-cyan"><div class="nx-summary-top"><div><div class="nx-summary-label">Credential Ready</div><div class="nx-summary-value">${withCreds}</div><div class="nx-summary-text">Profiles containing both username and password.</div></div><div class="nx-summary-icon"><i class="bi bi-key"></i></div></div></div></div>
                <div class="col-12 col-md-6 col-xl-3"><div class="nx-summary-card nx-summary-danger"><div class="nx-summary-top"><div><div class="nx-summary-label">Vendors</div><div class="nx-summary-value">${vendors.size}</div><div class="nx-summary-text">Distinct vendor profiles currently registered.</div></div><div class="nx-summary-icon"><i class="bi bi-box-seam"></i></div></div></div></div>
            `);
            return;
        }

        if (pageMode === 'ports') {
            const ports = safeArray(ctx.state.ports);
            const selectedDevice = getSelectedDevice(ctx);
            const total = ports.length;
            const online = ports.filter((p) => String(p.link_status || '').toLowerCase() === 'online').length;
            const opticOnline = ports.filter((p) => String(p.optic_status || '').toLowerCase() === 'online').length;
            const ontOnline = ports.reduce((sum, p) => sum + Number(p.ont_online || 0), 0);

            html(el.kpiStrip, `
                <div class="col-12 col-md-6 col-xl-3"><div class="nx-summary-card nx-summary-primary"><div class="nx-summary-top"><div><div class="nx-summary-label">Selected OLT</div><div class="nx-summary-value">${escape(selectedDevice?.name || '-')}</div><div class="nx-summary-text">${escape(selectedDevice?.ip_address || 'Choose a device to load its imported ports.')}</div></div><div class="nx-summary-icon"><i class="bi bi-router"></i></div></div></div></div>
                <div class="col-12 col-md-6 col-xl-3"><div class="nx-summary-card nx-summary-success"><div class="nx-summary-top"><div><div class="nx-summary-label">Total Ports</div><div class="nx-summary-value">${total}</div><div class="nx-summary-text">Imported ports available in the local OLT inventory.</div></div><div class="nx-summary-icon"><i class="bi bi-grid-3x3-gap"></i></div></div></div></div>
                <div class="col-12 col-md-6 col-xl-3"><div class="nx-summary-card nx-summary-cyan"><div class="nx-summary-top"><div><div class="nx-summary-label">Link / Optic Online</div><div class="nx-summary-value">${online} / ${opticOnline}</div><div class="nx-summary-text">Quick health snapshot for imported port status.</div></div><div class="nx-summary-icon"><i class="bi bi-activity"></i></div></div></div></div>
                <div class="col-12 col-md-6 col-xl-3"><div class="nx-summary-card nx-summary-danger"><div class="nx-summary-top"><div><div class="nx-summary-label">ONT Online</div><div class="nx-summary-value">${ontOnline}</div><div class="nx-summary-text">Total online ONTs counted across GPON ports.</div></div><div class="nx-summary-icon"><i class="bi bi-wifi"></i></div></div></div></div>
            `);
            return;
        }

        if (pageMode === 'profiles') {
            const selectedDevice = getSelectedDevice(ctx);
            const activeTab = String(ctx.state.activeProfileTab || 'dba').toUpperCase();
            const dbaCount = safeArray(ctx.state.dbaProfiles).length;
            const lineCount = safeArray(ctx.state.lineProfiles).length;
            const wanCount = safeArray(ctx.state.wanProfiles).length;
            const tr069Count = safeArray(ctx.state.tr069Profiles).length;
            const srvCount = safeArray(ctx.state.srvProfiles).length;
            const totalProfiles = dbaCount + lineCount + wanCount + tr069Count + srvCount;

            html(el.kpiStrip, `
                <div class="col-12 col-md-6 col-xl-3"><div class="nx-summary-card nx-summary-primary"><div class="nx-summary-top"><div><div class="nx-summary-label">Selected OLT</div><div class="nx-summary-value">${escape(selectedDevice?.name || 'No OLT Selected')}</div><div class="nx-summary-text">${escape(selectedDevice?.ip_address || 'Select an OLT from the toolbar to manage its profile workspace.')}</div></div><div class="nx-summary-icon"><i class="bi bi-router"></i></div></div></div></div>
                <div class="col-12 col-md-6 col-xl-3"><div class="nx-summary-card nx-summary-success"><div class="nx-summary-top"><div><div class="nx-summary-label">Vendor</div><div class="nx-summary-value">${escape(selectedDevice?.vendor || '-')}</div><div class="nx-summary-text">Workspace is fully tied to the selected OLT device.</div></div><div class="nx-summary-icon"><i class="bi bi-box-seam"></i></div></div></div></div>
                <div class="col-12 col-md-6 col-xl-3"><div class="nx-summary-card nx-summary-cyan"><div class="nx-summary-top"><div><div class="nx-summary-label">Active Tab</div><div class="nx-summary-value">${escape(activeTab)}</div><div class="nx-summary-text">Current provisioning object family in the shared workspace.</div></div><div class="nx-summary-icon"><i class="bi bi-layers"></i></div></div></div></div>
                <div class="col-12 col-md-6 col-xl-3"><div class="nx-summary-card nx-summary-danger"><div class="nx-summary-top"><div><div class="nx-summary-label">Loaded Profiles</div><div class="nx-summary-value">${totalProfiles}</div><div class="nx-summary-text">DBA ${dbaCount} • Line ${lineCount} • WAN ${wanCount} • TR069 ${tr069Count} • SRV ${srvCount}</div></div><div class="nx-summary-icon"><i class="bi bi-sliders2"></i></div></div></div></div>
            `);
        }
    }

    function renderContextChips(ctx) {
        if (!el.contextChips) return;

        if (pageMode !== 'ports') {
            html(el.contextChips, '');
            return;
        }

        const selectedDevice = getSelectedDevice(ctx);
        const parts = [];

        if (selectedDevice) {
            parts.push(`<span class="olt-context-chip"><i class="bi bi-hdd-network"></i>${escape(selectedDevice.name || '-')}</span>`);
            parts.push(`<span class="olt-context-chip"><i class="bi bi-globe"></i>${escape(selectedDevice.ip_address || '-')}</span>`);
        }

        if (ctx.state.selectedSlot !== null) {
            parts.push(`<span class="olt-context-chip"><i class="bi bi-columns-gap"></i>Slot 0/${escape(ctx.state.selectedSlot)}</span>`);
        }

        if (ctx.state.selectedPortPath) {
            parts.push(`<span class="olt-context-chip"><i class="bi bi-ethernet"></i>${escape(ctx.state.selectedPortPath)}</span>`);
        }

        html(el.contextChips, parts.length ? `<div class="olt-context-chips">${parts.join('')}</div>` : '');
    }

    function ensureDevicesDatatable() {
        if (devicesDatatable || !el.devicesView) return;

        devicesDatatable = datatable.create({
            el: el.devicesView,
            rows: [],
            search: false,
            paginate: true,
            pager: { currentPage: 1, rowsPerPage: 20 },
            sort: { key: 'name', dir: 'asc' },
            columns: [
                {
                    key: 'name',
                    label: 'Device',
                    render: (_value, row) => `
                    <div class="nx-cell-stack">
                        <div class="nx-cell-title">${escape(row.name || '-')}</div>
                        <div class="nx-cell-sub">OLT connection profile</div>
                    </div>
                `
                },
                {
                    key: 'ip_address',
                    label: 'IP Address',
                    render: (value) => `<span class="nx-text-mono">${escape(value || '-')}</span>`
                },
                {
                    key: 'vendor',
                    label: 'Vendor',
                    render: (_value, row) => `
                    <div class="nx-cell-stack">
                        <div class="nx-cell-title">${escape(row.vendor || '-')}</div>
                        <div class="nx-cell-sub">Platform vendor</div>
                    </div>
                `
                },
                {
                    key: 'username',
                    label: 'Username',
                    render: (value) => `<span class="nx-text-mono">${escape(value || '-')}</span>`
                },
                {
                    key: 'enable_home_gateway_omci',
                    label: 'OMCI',
                    render: (_value, row) => omciBadge(row)
                },
                {
                    key: 'created_at',
                    label: 'Created',
                    render: (value) => escape(value || '-')
                },
                {
                    key: 'actions',
                    label: 'Actions',
                    render: (_value, row) => `
                    <div class="nx-olt-actions">
                        <button type="button" class="btn btn-outline-secondary nx-icon-btn js-olt-view-device" data-id="${Number(row.id)}" title="View"><i class="bi bi-eye"></i></button>
                        <button type="button" class="btn btn-outline-primary nx-icon-btn js-olt-edit-device" data-id="${Number(row.id)}" title="Edit"><i class="bi bi-pencil"></i></button>
                        <button type="button" class="btn btn-outline-success nx-icon-btn js-olt-open-ports" data-id="${Number(row.id)}" title="Ports"><i class="bi bi-grid-3x3-gap"></i></button>
                        <button type="button" class="btn btn-outline-info nx-icon-btn js-olt-open-profiles" data-id="${Number(row.id)}" title="Profiles"><i class="bi bi-sliders2"></i></button>
                        <button type="button" class="btn btn-outline-danger nx-icon-btn js-olt-delete-device" data-id="${Number(row.id)}" title="Delete"><i class="bi bi-trash"></i></button>
                    </div>
                `
                }
            ]
        });
    }

    function buildPortsTableColumns(rows = []) {
    const hasGponPorts = safeArray(rows).some((row) => !isControlBoardPort(row));

    const columns = [
        {
            key: 'port_path',
            label: 'Port',
            render: (_value, row) => `
                <div class="nx-cell-stack">
                    <div class="nx-cell-title nx-text-mono">${escape(row.port_path || '-')}</div>
                    <div class="nx-cell-sub">${escape(row.port_type || '-')}</div>
                </div>
            `
        },
        {
            key: 'board_name',
            label: 'Board',
            render: (_value, row) => `
                <div class="nx-cell-stack">
                    <div class="nx-cell-title">${escape(row.board_name || '-')}</div>
                    <div class="nx-cell-sub">${mapBoardBadge(row.board_status || '-')}</div>
                </div>
            `
        },
        {
            key: 'link_status',
            label: 'Link',
            render: (value) => mapLinkBadge(value)
        },
        {
            key: 'optic_status',
            label: 'Optic',
            render: (value) => mapOpticBadge(value)
        }
    ];

    if (hasGponPorts) {
        columns.push({
            key: 'ont_online',
            label: 'ONT Online',
            render: (_value, row) => {
                if (isControlBoardPort(row)) return '';

                return `
                    <div class="nx-cell-stack">
                        <div class="nx-cell-title">${escape(row.ont_online ?? '0')}</div>
                        <div class="nx-cell-sub">of ${escape(row.ont_count ?? '0')} total</div>
                    </div>
                `;
            }
        });
    }

    // 🔥 VLAN COLUMN
    columns.push({
        key: 'svlan',
        label: 'VLAN Access',
        render: (_value, row) => {

            // =========================
            // CONTROL BOARD (unchanged)
            // =========================
            if (isControlBoardPort(row)) {
                const bindings = getControlPortBindings(row.id);
                return `
                    <button
                        type="button"
                        class="btn btn-outline-primary olt-show-vlans-btn js-show-control-vlans"
                        data-id="${Number(row.id)}"
                    >
                        <span class="olt-show-vlans-icon"><i class="bi bi-layers"></i></span>
                        <span class="olt-show-vlans-copy">
                            <strong>Show VLANs</strong>
                            <small>${bindings.length ? `${bindings.length} configured` : 'None configured'}</small>
                        </span>
                        <span class="olt-show-vlans-arrow"><i class="bi bi-chevron-right"></i></span>
                    </button>
                `;
            }

            // =========================
            // 🔥 PON PORT (NEW LOGIC)
            // =========================

           const hasSvlan = !!row.svlan;

return `
    <button
        type="button"
        class="btn btn-outline-primary olt-show-vlans-btn js-show-pon-vlans"
        data-id="${Number(row.id)}"
    >
        <span class="olt-show-vlans-icon"><i class="bi bi-layers"></i></span>
        <span class="olt-show-vlans-copy">
            <strong>Show VLANs</strong>
            <small>${hasSvlan ? '1 configured' : 'None configured'}</small>
        </span>
        <span class="olt-show-vlans-arrow"><i class="bi bi-chevron-right"></i></span>
    </button>
`;
        }
    });

    columns.push({
        key: 'actions',
        label: 'Actions',
        render: (_value, row) => {
            const hasSvlan = !!row.svlan;
            const isControlPort = isControlBoardPort(row);

            return `
            <div class="nx-olt-actions">
                <button type="button" class="btn btn-outline-secondary nx-icon-btn js-olt-view-port" data-id="${Number(row.id)}" title="View">
                    <i class="bi bi-eye"></i>
                </button>
                ${isControlPort ? `
                    <button
                        type="button"
                        class="btn btn-primary nx-icon-btn js-bind-control-vlan"
                        data-id="${Number(row.id)}"
                        title="Add allowed VLAN"
                        aria-label="Add allowed VLAN"
                    >
                        <i class="bi bi-plus-lg"></i>
                    </button>
                ` : `
                    <button
                        type="button"
                        class="btn ${hasSvlan ? 'btn-outline-primary' : 'btn-primary'} nx-icon-btn js-assign-pon-svlan"
                        data-id="${Number(row.id)}"
                        title="${hasSvlan ? 'Change S-VLAN' : 'Assign S-VLAN'}"
                        aria-label="${hasSvlan ? 'Change S-VLAN' : 'Assign S-VLAN'}"
                    >
                        <i class="bi ${hasSvlan ? 'bi-pencil' : 'bi-plus-lg'}"></i>
                    </button>
                    ${hasSvlan ? `
                        <button
                            type="button"
                            class="btn btn-outline-danger nx-icon-btn js-unassign-pon-svlan"
                            data-id="${Number(row.id)}"
                            data-svlan="${escape(row.svlan)}"
                            title="Remove S-VLAN"
                            aria-label="Remove S-VLAN"
                        >
                            <i class="bi bi-x-lg"></i>
                        </button>
                    ` : ''}
                `}
            </div>
        `;
        }
    });

    return columns;
}

    function ensurePortsDatatable(rows = []) {
    if (!el.portsTableView) return;

    const nextHasGponPorts = safeArray(rows).some((row) => !isControlBoardPort(row));
    const currentHasGponPorts = portsDatatable?.__hasGponPorts;

    if (portsDatatable && currentHasGponPorts === nextHasGponPorts) {
        return;
    }

    if (portsDatatable) {
        portsDatatable.destroy();
        portsDatatable = null;
    }

    portsDatatable = datatable.create({
        el: el.portsTableView,
        rows: [],
        search: false,
        paginate: true,
        pager: { currentPage: 1, rowsPerPage: 20 },
        sort: { key: 'port_path', dir: 'asc' },
        columns: buildPortsTableColumns(rows)
    });

    portsDatatable.__hasGponPorts = nextHasGponPorts;
}

    function bindDevicesTableActions(ctx) {
        if (!el.devicesView) return;

        el.devicesView.querySelectorAll('.js-olt-view-device').forEach((btn) => {
            btn.onclick = (e) => {
                e.preventDefault();
                e.stopPropagation();
                openViewDeviceModal(ctx, Number(btn.dataset.id || 0));
            };
        });

        el.devicesView.querySelectorAll('.js-olt-edit-device').forEach((btn) => {
            btn.onclick = (e) => {
                e.preventDefault();
                e.stopPropagation();
                openEditDeviceModal(ctx, Number(btn.dataset.id || 0));
            };
        });

        el.devicesView.querySelectorAll('.js-olt-delete-device').forEach((btn) => {
            btn.onclick = async (e) => {
                e.preventDefault();
                e.stopPropagation();
                await deleteOltDevice(ctx, Number(btn.dataset.id || 0));
            };
        });

        el.devicesView.querySelectorAll('.js-olt-open-ports').forEach((btn) => {
            btn.onclick = (e) => {
                e.preventDefault();
                e.stopPropagation();
                const id = Number(btn.dataset.id || 0);
                if (!id) return;
                window.location.href = `/olt-management/ports?olt_id=${id}`;
            };
        });

        el.devicesView.querySelectorAll('.js-olt-open-profiles').forEach((btn) => {
            btn.onclick = (e) => {
                e.preventDefault();
                e.stopPropagation();
                const id = Number(btn.dataset.id || 0);
                if (!id) return;
                window.location.href = `/olt-management/profiles?olt_id=${id}&tab=dba`;
            };
        });

        decorateDeviceRows(ctx);
    }

    function unassignSvlan(portId) {
    NX.ui.confirm({
        title: 'Remove SVLAN?',
        text: 'This will unbind SVLAN from this PON port.',
        confirmButtonText: 'Yes, remove',
    }).then((confirmed) => {
        if (!confirmed) return;

        NX.api.post('/api/v1/olt-management/pon-port/unassign-svlan', {
            olt_port_id: portId
        }).then((res) => {
            if (!res.ok) {
                NX.ui.toast(res.message || 'Failed', 'error');
                return;
            }

            NX.ui.toast('SVLAN removed successfully');
            loadPorts(); // refresh
        });
    });
}

    async function unassignPonSvlan(ctx, portId, svlan = '') {
        await actions.run({
            confirm: {
                title: 'Remove SVLAN?',
                text: svlan
                    ? `This will remove VLAN ${svlan} from this PON port.`
                    : 'This will remove the assigned SVLAN from this PON port.',
                confirmButtonText: 'Yes, remove'
            },
            loading: 'Removing SVLAN...',
            task: async () => {
                const result = await api.form(
                    PON_SVLAN_ENDPOINTS.remove,
                    forms.data({ olt_port_id: portId })
                );

                lastPortsTableSignature = '';
                await loadPorts(ctx, ctx.state.selectedOltId);

                return result;
            },
            onSuccess: (result) => ui.toast('success', result.message || 'SVLAN removed.'),
            onError: (err) => ui.toast('error', errorMessage(err, 'Failed to remove SVLAN.'))
        });
    }

    function bindPortsTableActions(ctx) {
    if (!el.portsTableView) return;

    // View Port
    el.portsTableView.querySelectorAll('.js-olt-view-port').forEach((btn) => {
        btn.onclick = (e) => {
            e.preventDefault();
            e.stopPropagation();
            openViewPortModal(ctx, Number(btn.dataset.id || 0));
        };
    });

    // Control Board VLAN
    el.portsTableView.querySelectorAll('.js-show-control-vlans').forEach((btn) => {
        btn.onclick = (e) => {
            e.preventDefault();
            e.stopPropagation();
            openControlVlanListModal(ctx, Number(btn.dataset.id || 0));
        };
    });

    el.portsTableView.querySelectorAll('.js-show-pon-vlans').forEach((btn) => {
        btn.onclick = (e) => {
            e.preventDefault();
            e.stopPropagation();
            openPonVlanListModal(ctx, Number(btn.dataset.id || 0));
        };
    });

    el.portsTableView.querySelectorAll('.js-bind-control-vlan').forEach((btn) => {
        btn.onclick = async (e) => {
            e.preventDefault();
            e.stopPropagation();
            await openControlBoardVlanModal(ctx, Number(btn.dataset.id || 0));
        };
    });

    function openControlVlanListModal(ctx, portId) {
        const port = findPortById(ctx.state.ports, portId);
        if (!port || !el.controlVlanListModal || !el.controlVlanListBody) {
            ui.toast('error', 'Control board port not found.');
            return;
        }

        const bindings = getControlPortBindings(portId);
        text(el.controlVlanListSubtitle, `${port.port_path || '-'} • ${bindings.length} allowed VLAN${bindings.length === 1 ? '' : 's'}`);

        if (!bindings.length) {
            html(el.controlVlanListBody, `
                <tr>
                    <td colspan="4">
                        <div class="olt-vlan-list-empty">No VLANs are currently assigned to this port.</div>
                    </td>
                </tr>
            `);
        } else {
            html(el.controlVlanListBody, bindings.map((binding) => `
                <tr>
                    <td><span class="nx-text-mono fw-bold">VLAN ${escape(binding.vlan_id)}</span></td>
                    <td>${escape(binding.vlan_name || '-')}</td>
                    <td><span class="olt-vlan-type-badge ${String(binding.vlan_type) === 'MGMT' ? 'is-mgmt' : 'is-service'}">${escape(binding.vlan_type || 'SERVICE')}</span></td>
                    <td class="text-end">
                        <button
                            type="button"
                            class="btn btn-outline-danger nx-icon-btn js-modal-unbind-control-vlan"
                            data-id="${Number(binding.id)}"
                            data-vlan-id="${escape(binding.vlan_id)}"
                            data-vlan-type="${escape(binding.vlan_type)}"
                            title="Unbind VLAN ${escape(binding.vlan_id)}"
                            aria-label="Unbind VLAN ${escape(binding.vlan_id)}"
                        ><i class="bi bi-trash"></i></button>
                    </td>
                </tr>
            `).join(''));

            el.controlVlanListBody.querySelectorAll('.js-modal-unbind-control-vlan').forEach((btn) => {
                btn.onclick = async () => {
                    closeModal(el.controlVlanListModal);
                    await deleteControlBoardVlanBinding(ctx, Number(btn.dataset.id || 0), {
                        vlanId: btn.dataset.vlanId || '',
                        vlanType: btn.dataset.vlanType || ''
                    });
                };
            });
        }

        openModal(el.controlVlanListModal);
    }

    function openPonVlanListModal(ctx, portId) {
        const port = findPortById(ctx.state.ports, portId);
        if (!port || !el.controlVlanListModal || !el.controlVlanListBody) {
            ui.toast('error', 'PON port not found.');
            return;
        }

        const hasSvlan = !!port.svlan;
        text(el.controlVlanListSubtitle, `${port.port_path || '-'} • ${hasSvlan ? '1 assigned VLAN' : 'No assigned VLAN'}`);

        if (!hasSvlan) {
            html(el.controlVlanListBody, `
                <tr>
                    <td colspan="4">
                        <div class="olt-vlan-list-empty">No VLAN is currently assigned to this PON port.</div>
                    </td>
                </tr>
            `);
        } else {
            html(el.controlVlanListBody, `
                <tr>
                    <td><span class="nx-text-mono fw-bold">VLAN ${escape(port.svlan)}</span></td>
                    <td>${escape(port.svlan_name || '-')}</td>
                    <td><span class="olt-vlan-type-badge is-service">S-VLAN</span></td>
                    <td class="text-end">
                        <button
                            type="button"
                            class="btn btn-outline-danger nx-icon-btn js-modal-unassign-pon-svlan"
                            data-id="${Number(port.id)}"
                            data-svlan="${escape(port.svlan)}"
                            title="Remove VLAN ${escape(port.svlan)}"
                            aria-label="Remove VLAN ${escape(port.svlan)}"
                        ><i class="bi bi-trash"></i></button>
                    </td>
                </tr>
            `);

            const removeButton = el.controlVlanListBody.querySelector('.js-modal-unassign-pon-svlan');
            if (removeButton) {
                removeButton.onclick = async () => {
                    closeModal(el.controlVlanListModal);
                    await unassignPonSvlan(ctx, Number(removeButton.dataset.id || 0), removeButton.dataset.svlan || '');
                };
            }
        }

        openModal(el.controlVlanListModal);
    }

    // Unbind Control VLAN
    el.portsTableView.querySelectorAll('.js-unbind-control-vlan').forEach((btn) => {
        btn.onclick = async (e) => {
            e.preventDefault();
            e.stopPropagation();

            const id = Number(btn.dataset.id || 0);
            if (!id) return;

            await deleteControlBoardVlanBinding(ctx, id, {
                vlanId: btn.dataset.vlanId || '',
                vlanType: btn.dataset.vlanType || ''
            });
        };
    });

    // 🔥 NEW: PON SVLAN ASSIGN
    el.portsTableView.querySelectorAll('.js-assign-pon-svlan').forEach((btn) => {
        btn.onclick = (e) => {
            e.preventDefault();
            e.stopPropagation();

            const portId = Number(btn.dataset.id || 0);
            if (!portId) return;

            openPonSvlanModal(ctx, portId);
        };
    });

        el.portsTableView.querySelectorAll('.js-unassign-pon-svlan').forEach((btn) => {
            btn.onclick = async (e) => {
                e.preventDefault();
                e.stopPropagation();

                const portId = Number(btn.dataset.id || 0);
                const svlan = btn.dataset.svlan || '';

                if (!portId) {
                    ui.toast('error', 'Missing PON port.');
                    return;
                }

                await unassignPonSvlan(ctx, portId, svlan);
            };
        });
}

    function bindProfileTableActions(ctx, tab, pane) {
        if (!pane) return;

        pane.querySelectorAll('.js-profile-view').forEach((btn) => {
            btn.onclick = (e) => {
                e.preventDefault();
                openViewProfileModal(ctx, tab, Number(btn.dataset.id || 0));
            };
        });

        pane.querySelectorAll('.js-profile-edit').forEach((btn) => {
            btn.onclick = (e) => {
                e.preventDefault();
                openEditProfileModal(ctx, tab, Number(btn.dataset.id || 0));
            };
        });

        pane.querySelectorAll('.js-profile-cli').forEach((btn) => {
            btn.onclick = async (e) => {
                e.preventDefault();
                await openCliPreviewModal(tab, Number(btn.dataset.id || 0));
            };
        });

        pane.querySelectorAll('.js-profile-delete').forEach((btn) => {
            btn.onclick = async (e) => {
                e.preventDefault();
                await deleteProfile(ctx, tab, Number(btn.dataset.id || 0));
            };
        });
    }

    function renderDevicesTable(ctx) {
        if (pageMode !== 'devices' || !el.devicesView) return;

        if (ctx.state.loading.devices) {
            if (devicesDatatable) {
                devicesDatatable.destroy();
                devicesDatatable = null;
            }
            html(el.devicesView, render.skeletonTable(4, 6));
            return;
        }

        if (!ctx.state.devices.length) {
            if (devicesDatatable) {
                devicesDatatable.destroy();
                devicesDatatable = null;
            }
            html(el.devicesView, render.emptyState({
                title: 'No OLT devices found',
                text: 'Add or import an OLT device profile first.'
            }));
            return;
        }

        ensureDevicesDatatable();
        devicesDatatable.setRows(safeArray(ctx.state.devices));
        bindDevicesTableActions(ctx);

        const meta = $('#oltDevicesTableMeta');
        if (meta) meta.textContent = `${safeArray(ctx.state.devices).length} device${safeArray(ctx.state.devices).length === 1 ? '' : 's'}`;

        const searchInput = $('#oltDevicesSearch');
        if (searchInput) searchInput.value = devicesDatatable.state?.search || '';
    }

    function renderPhysicalView(ctx) {
        if (pageMode !== 'ports' || !el.physicalView) return;

        const signature = JSON.stringify({
            selectedOltId: Number(ctx.state.selectedOltId || 0),
            imagePath: RESOLVED_IMAGE_PATH,
            ports: safeArray(ctx.state.ports).map((p) => ({
                id: p.id,
                port_path: p.port_path,
                link_status: p.link_status,
                optic_status: p.optic_status,
                board_status: p.board_status,
                ont_online: p.ont_online,
                slot: p.slot,
                port: p.port
            }))
        });

        if (!physicalPanelMount) {
            physicalPanelMount = mount(el.physicalView, 'olt-physical-panel', { state: ctx.state });
            lastPhysicalSignature = signature;
            return;
        }

        if (lastPhysicalSignature !== signature) {
            physicalPanelMount.update({ state: ctx.state });
            lastPhysicalSignature = signature;
            return;
        }

        syncPhysicalSelection(ctx);
    }

    function renderPortsTable(ctx) {
        if (pageMode !== 'ports' || !el.portsTableView) return;

        if (!Number(ctx.state.selectedOltId)) {
            if (portsDatatable) {
                portsDatatable.destroy();
                portsDatatable = null;
            }
            html(el.portsTableView, render.emptyState({
                title: 'No OLT selected',
                text: 'Select an OLT device first.'
            }));
            return;
        }

        let rows = safeArray(ctx.state.ports);

        if (ctx.state.selectedPortPath) {
            rows = rows.filter((p) => String(p.port_path) === String(ctx.state.selectedPortPath));
        } else if (ctx.state.selectedSlot !== null) {
            rows = rows.filter((p) => Number(p.slot) === Number(ctx.state.selectedSlot));
        }

        const signature = JSON.stringify({
            selectedOltId: Number(ctx.state.selectedOltId || 0),
            selectedSlot: ctx.state.selectedSlot,
            selectedPortPath: ctx.state.selectedPortPath,
            loading: !!ctx.state.loading.ports,
            rows: rows.map((p) => ({
                id: p.id,
                port_path: p.port_path,
                board_name: p.board_name,
                board_type: p.board_type,
                board_status: p.board_status,
                link_status: p.link_status,
                optic_status: p.optic_status,
                ont_online: p.ont_online,
                ont_count: p.ont_count,
                svlan: p.svlan
            })),
            bindings: safeArray(ctx.state.controlBoardVlanBindings).map((b) => ({
                id: b.id,
                olt_port_id: b.olt_port_id,
                vlan_type: b.vlan_type,
                vlan_id: b.vlan_id,
                vlan_name: b.vlan_name
            }))
        });

        if (ctx.state.loading.ports) {
            if (portsDatatable) {
                portsDatatable.destroy();
                portsDatatable = null;
            }
            html(el.portsTableView, render.skeletonTable(5, 7));
            lastPortsTableSignature = signature;
            return;
        }

        if (!rows.length) {
            if (portsDatatable) {
                portsDatatable.destroy();
                portsDatatable = null;
            }
            html(el.portsTableView, render.emptyState({
                title: 'No ports found',
                text: ctx.state.selectedPortPath
                    ? `No data found for ${ctx.state.selectedPortPath}.`
                    : ctx.state.selectedSlot !== null
                        ? `No ports found for selected slot 0/${ctx.state.selectedSlot}.`
                        : 'No imported ports found for this OLT.'
            }));
            lastPortsTableSignature = signature;
            return;
        }

        if (lastPortsTableSignature === signature && portsDatatable) {
            bindPortsTableActions(ctx);
            return;
        }

        ensurePortsDatatable(rows);
        portsDatatable.setRows(rows);
        bindPortsTableActions(ctx);
        lastPortsTableSignature = signature;

        const meta = $('#oltPortsTableMeta');
        if (meta) meta.textContent = `${rows.length} port${rows.length === 1 ? '' : 's'}`;

        const searchInput = $('#oltPortsSearch');
        if (searchInput) searchInput.value = portsDatatable.state?.search || '';
    }

    function renderSingleProfilePane(ctx, tab) {
        const pane = ensureProfilePane(tab);
        if (!pane) return;

        const meta = PROFILE_META[tab];
        const rows = getProfileRowsByTab(ctx, tab);
        const isLoading = !!ctx.state.loading[`${tab}Profiles`];

        if (!Number(ctx.state.selectedOltId)) {
            if (profileTables[tab]) {
                profileTables[tab].destroy();
                profileTables[tab] = null;
            }

            html(pane, render.emptyState({
                title: 'No OLT selected',
                text: 'Select an OLT device first to manage profiles.'
            }));

            lastProfileSignatures[tab] = '';
            return;
        }

        const signature = JSON.stringify({
            selectedOltId: Number(ctx.state.selectedOltId || 0),
            loading: isLoading,
            rows: rows.map((row) => ({ ...row }))
        });

        if (isLoading) {
            if (profileTables[tab]) {
                profileTables[tab].destroy();
                profileTables[tab] = null;
            }
            html(pane, render.skeletonTable(5, 6));
            lastProfileSignatures[tab] = signature;
            return;
        }

        if (!rows.length) {
            if (profileTables[tab]) {
                profileTables[tab].destroy();
                profileTables[tab] = null;
            }

            html(pane, `
                <div class="d-flex flex-column gap-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <h3 class="section-title mb-1">${escape(meta.title)}</h3>
                            <p class="section-subtitle mb-0">${escape(meta.subtitle)}</p>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-primary js-profile-create" data-tab="${escape(tab)}">
                                <i class="bi bi-plus-lg"></i>
                                <span>Add ${escape(PROFILE_ENDPOINTS[tab].label)} Profile</span>
                            </button>
                        </div>
                    </div>
                    ${render.emptyState({
                title: meta.emptyTitle,
                text: meta.emptyText
            })}
                </div>
            `);

            lastProfileSignatures[tab] = signature;
            return;
        }

        if (lastProfileSignatures[tab] !== signature || !profileTables[tab]) {
            html(pane, `
                <div class="d-flex flex-column gap-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <h3 class="section-title mb-1">${escape(meta.title)}</h3>
                            <p class="section-subtitle mb-0">${escape(meta.subtitle)}</p>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-primary js-profile-create" data-tab="${escape(tab)}">
                                <i class="bi bi-plus-lg"></i>
                                <span>Add ${escape(PROFILE_ENDPOINTS[tab].label)} Profile</span>
                            </button>
                        </div>
                    </div>
                    <div id="olt-${escape(tab)}-profiles-table-mount"></div>
                </div>
            `);

            const mountPoint = pane.querySelector(`#olt-${tab}-profiles-table-mount`);
            ensureProfileDatatable(tab, mountPoint);
            profileTables[tab].setRows(rows);
            lastProfileSignatures[tab] = signature;
        }

        bindProfileTableActions(ctx, tab, pane);
    }

    function renderProfilesWorkspace(ctx) {
        if (pageMode !== 'profiles') return;

        syncProfileTabUi(ctx);

        renderSingleProfilePane(ctx, 'dba');
        renderSingleProfilePane(ctx, 'line');
        renderSingleProfilePane(ctx, 'wan');
        renderSingleProfilePane(ctx, 'tr069');
        renderSingleProfilePane(ctx, 'srv');

        if (el.profilesEmptyState) {
            el.profilesEmptyState.classList.toggle('d-none', Number(ctx.state.selectedOltId) > 0);
        }

        const tabs = $('#oltProfilesTabs');
        const workspace = $('#oltProfilesWorkspace');

        if (tabs) tabs.classList.toggle('d-none', Number(ctx.state.selectedOltId) <= 0);
        if (workspace) workspace.classList.toggle('d-none', Number(ctx.state.selectedOltId) <= 0);
    }

    function openViewDeviceModal(ctx, id) {
        const d = findDeviceById(ctx.state.devices, id);
        if (!d) {
            ui.toast('error', 'OLT device not found.');
            return;
        }

        text(el.viewDeviceSubtitle, d.name || '-');
        text(el.viewDeviceName, d.name || '-');
        text(el.viewDeviceIp, d.ip_address || '-');
        text(el.viewDeviceVendor, d.vendor || '-');
        text(el.viewDeviceUsername, d.username || '-');
        text(el.viewDevicePassword, d.password || '-');

        if (el.viewDeviceOmci) {
            text(el.viewDeviceOmci, Number(d.enable_home_gateway_omci || 0) === 1 ? 'Enabled' : 'Disabled');
        }

        if (el.viewDeviceOmciAutoDetect) {
            text(el.viewDeviceOmciAutoDetect, Number(d.auto_detect_omci_support || 0) === 1 ? 'Enabled' : 'Disabled');
        }

        openModal(el.viewDeviceModal);
    }

    function openEditDeviceModal(ctx, id) {
        const d = findDeviceById(ctx.state.devices, id);
        if (!d || !el.editDeviceForm) {
            ui.toast('error', 'OLT device not found.');
            return;
        }

        el.editDeviceForm.dataset.id = String(id);
        text(el.editDeviceSubtitle, d.name || '-');

        if (el.editDeviceName) el.editDeviceName.value = d.name || '';
        if (el.editDeviceIp) el.editDeviceIp.value = d.ip_address || '';
        if (el.editDeviceVendor) el.editDeviceVendor.value = d.vendor || '';
        if (el.editDeviceUsername) el.editDeviceUsername.value = d.username || '';
        if (el.editDevicePassword) el.editDevicePassword.value = d.password || '';

        if (el.editDeviceOmci) {
            el.editDeviceOmci.checked = Number(d.enable_home_gateway_omci || 0) === 1;
        }

        if (el.editDeviceOmciAutoDetect) {
            el.editDeviceOmciAutoDetect.checked = Number(d.auto_detect_omci_support || 0) === 1;
        }

        openModal(el.editDeviceModal);
    }

    function openViewPortModal(ctx, id) {
    const p = findPortById(ctx.state.ports, id);
    if (!p) {
        ui.toast('error', 'OLT port not found.');
        return;
    }

    const isControl = isControlBoardPort(p);

    let vlanDisplay = '-';

    if (isControl) {
        const bindings = getControlPortBindings(p.id);

        if (bindings.length) {
            vlanDisplay = bindings
                .map((b) => `${b.vlan_type === 'MGMT' ? 'MGMT' : 'SERVICE'} VLAN ${b.vlan_id}`)
                .join(', ');
        }
    } else {
        vlanDisplay = p.svlan ?? '-';
    }

    text(el.viewPortSubtitle, p.port_path || '-');
    text(el.viewPortOltName, p.olt_name || '-');
    text(el.viewPortBoard, p.board_name || '-');
    text(el.viewPortPath, p.port_path || '-');
    text(el.viewPortType, p.port_type || '-');
    text(el.viewPortLink, p.link_status || '-');
    text(el.viewPortOptic, p.optic_status || '-');
    text(el.viewPortSpeed, p.speed || '-');
    text(el.viewPortDuplex, p.duplex || '-');
    text(el.viewPortActiveState, p.active_state || '-');
    text(el.viewPortDescription, p.description || '-');

    text(el.viewPortSvlanLabel, isControl ? 'Allowed VLANs' : 'Assigned SVLAN');
    text(el.viewPortSvlan, vlanDisplay);

    text(el.viewPortOntCount, p.ont_count ?? '0');
    text(el.viewPortOntOnline, p.ont_online ?? '0');

    if (el.viewPortOntCountWrap) el.viewPortOntCountWrap.style.display = isControl ? 'none' : '';
    if (el.viewPortOntOnlineWrap) el.viewPortOntOnlineWrap.style.display = isControl ? 'none' : '';

    openModal(el.viewPortModal);
}

    function openCreateProfileModal(ctx, tab) {
        ensureProfileModals();

        if (!Number(ctx.state.selectedOltId)) {
            ui.toast('error', 'Select an OLT device first.');
            return;
        }

        const meta = PROFILE_META[tab];
        if (!meta) return;

        text(el.profileCrudModalTitle, `Create ${meta.title.slice(0, -1)}`);
        text(el.profileCrudModalSubtitle, `Add a new ${PROFILE_ENDPOINTS[tab].label} profile`);
        el.profileCrudId.value = '';
        el.profileCrudType.value = tab;
        el.profileCrudOltId.value = String(Number(ctx.state.selectedOltId || 0));
        html(el.profileCrudFields, buildProfileForm(tab, null, ctx));
        openModal(el.profileCrudModal);
    }

    function openEditProfileModal(ctx, tab, id) {
        ensureProfileModals();

        const meta = PROFILE_META[tab];
        const row = findProfileById(getProfileRowsByTab(ctx, tab), id);
        if (!row) {
            ui.toast('error', `${PROFILE_ENDPOINTS[tab].label} profile not found.`);
            return;
        }

        text(el.profileCrudModalTitle, `Edit ${meta.title.slice(0, -1)}`);
        text(el.profileCrudModalSubtitle, `Update ${row.profile_name || 'profile'} configuration`);
        el.profileCrudId.value = String(id);
        el.profileCrudType.value = tab;
        el.profileCrudOltId.value = String(Number(ctx.state.selectedOltId || 0));
        html(el.profileCrudFields, buildProfileForm(tab, row, ctx));
        openModal(el.profileCrudModal);
    }

    function openViewProfileModal(ctx, tab, id) {
        ensureProfileModals();

        const row = findProfileById(getProfileRowsByTab(ctx, tab), id);
        if (!row) {
            ui.toast('error', `${PROFILE_ENDPOINTS[tab].label} profile not found.`);
            return;
        }

        text(el.profileViewModalTitle, `${PROFILE_ENDPOINTS[tab].label} Profile Details`);
        text(el.profileViewModalSubtitle, row.profile_name || `Profile ID ${row.profile_id || id}`);
        html(el.profileViewContent, buildViewContent(row, tab));
        openModal(el.profileViewModal);
    }

    async function fillControlBoardVlanDropdown(ctx, vlanType = 'SERVICE') {
    const select = $('#controlBoardNetworkVlanId');
    if (!select) return;

    select.innerHTML = `<option value="">Loading VLANs...</option>`;

    const rows = await loadControlBoardVlanOptions(ctx, vlanType);

    if (!rows.length) {
        select.innerHTML = `
            <option value="">
                No available ${vlanType === 'MGMT' ? 'MGMT VLAN' : 'Service VLAN'}
            </option>
        `;
        updateControlBoardVlanCliPreview(ctx);
        return;
    }

    select.innerHTML = `
        <option value="">Select VLAN</option>
        ${rows.map((row) => `
            <option value="${Number(row.id)}" data-vlan-id="${Number(row.vlan_id)}">
                VLAN ${escape(row.vlan_id)}${row.name ? ` - ${escape(row.name)}` : ''}
            </option>
        `).join('')}
    `;

    updateControlBoardVlanCliPreview(ctx);
}

    async function openCliPreviewModal(tab, id) {
        ensureProfileModals();

        const endpoint = PROFILE_ENDPOINTS[tab];
        if (!endpoint) return;

        const release = withButtonLoading(el.profileCliCopyBtn, 'Loading...');

        try {
            const res = await api.get(endpoint.cli(id));
            const payload = res?.data || res || {};
            const cli = payload.cli || payload.data?.cli || '';

            text(el.profileCliModalTitle, `${endpoint.label} CLI Preview`);
            text(el.profileCliModalSubtitle, payload.profile_name || `Generated configuration for profile ${id}`);
            text(el.profileCliContent, cli || 'No CLI preview returned.');
            openModal(el.profileCliModal);

            if (el.profileCliCopyBtn) {
                el.profileCliCopyBtn.onclick = async () => {
                    try {
                        await navigator.clipboard.writeText(cli || '');
                        ui.toast('success', 'CLI copied');
                    } catch (err) {
                        ui.toast('error', 'Unable to copy CLI');
                    }
                };
            }
        } catch (err) {
            ui.toast('error', errorMessage(err, 'Unable to load CLI preview.'));
        } finally {
            release();
        }
    }

    async function saveProfileFromModal(ctx) {
        if (!el.profileCrudForm) return;

        const tab = String(el.profileCrudType.value || '');
        const id = Number(el.profileCrudId.value || 0);
        const endpoint = PROFILE_ENDPOINTS[tab];

        if (!endpoint) {
            ui.toast('error', 'Unknown profile type.');
            return;
        }

        if (!Number(ctx.state.selectedOltId)) {
            ui.toast('error', 'Select an OLT device first.');
            return;
        }

        const submitBtn = el.profileCrudSaveBtn;
        const release = withButtonLoading(submitBtn, 'Saving...');

        try {
            await jobs.run({
                title: id > 0 ? 'Updating profile...' : 'Creating profile...',
                success: '',
                task: async () => {
                    const payload = serializeProfileForm(ctx);
                    if (!payload) return null;

                    const fd = forms.data(payload);

                    const result = id > 0
                        ? await api.form(endpoint.update(id), fd)
                        : await api.form(endpoint.create, fd);

                    closeModal(el.profileCrudModal);
                    await loadProfileRows(ctx, tab, ctx.state.selectedOltId);
                    ui.toast('success', result.message || `${endpoint.label} profile saved.`);
                    return result;
                }
            });
        } catch (err) {
            ui.toast('error', errorMessage(err, 'Save failed.'));
        } finally {
            release();
        }
    }

    async function deleteProfile(ctx, tab, id) {
        const endpoint = PROFILE_ENDPOINTS[tab];
        if (!endpoint) return;

        await actions.run({
            confirm: {
                title: `Delete ${endpoint.label} Profile?`,
                text: 'This action cannot be undone.',
                confirmButtonText: 'Yes, delete'
            },
            loading: 'Deleting profile...',
            task: async () => {
                const result = await api.form(endpoint.delete, forms.data({ id }));
                await loadProfileRows(ctx, tab, ctx.state.selectedOltId);
                return result;
            },
            onSuccess: (result) => ui.toast('success', result.message || `${endpoint.label} profile deleted.`),
            onError: (err) => ui.toast('error', errorMessage(err, 'Delete failed.'))
        });
    }

    async function deleteOltDevice(ctx, id) {
        await actions.run({
            confirm: {
                title: 'Delete OLT Device?',
                text: 'This will fail if the OLT still has ports.',
                confirmButtonText: 'Yes, delete'
            },
            loading: 'Deleting device...',
            task: async () => {
                const result = await api.form('/api/v1/olt-management/device/delete', forms.data({ id }));

                if (Number(ctx.state.selectedOltId) === Number(id)) {
                    ctx.patch({
                        selectedOltId: 0,
                        selectedSlot: null,
                        selectedPortPath: null,
                        selectedDeviceId: null,
                        ports: [],
                        dbaProfiles: [],
                        lineProfiles: [],
                        wanProfiles: [],
                        tr069Profiles: [],
                        srvProfiles: []
                    });
                }

                await loadDevices(ctx);
                clearSelectedDeviceRowUi();
                return result;
            },
            onSuccess: (result) => ui.toast('success', result.message || 'OLT device deleted.'),
            onError: (err) => ui.toast('error', errorMessage(err, 'Delete failed.'))
        });
    }

    async function fetchPortsFromOlt(ctx) {
        if (!ctx.state.selectedOltId) {
            ui.toast('error', 'Select an OLT device first.');
            return;
        }

        const fetchBtn = $('#fetchPortsBtn');
        const release = withButtonLoading(fetchBtn, 'Fetching...');

        try {
            await jobs.run({
                title: 'Fetching ports from OLT...',
                success: '',
                task: async () => {
                    const result = await api.form('/api/v1/olt-management/port/fetch', forms.data({ olt_id: ctx.state.selectedOltId }));

                    const preview = safeArray(result.data?.preview);
                    const boards = safeArray(result.data?.boards);

                    ctx.patch({ fetchedPreview: preview });

                    if (el.fetchModalOltId) el.fetchModalOltId.value = ctx.state.selectedOltId;
                    if (el.fetchPortsJson) el.fetchPortsJson.value = JSON.stringify(preview);

                    if (el.fetchedPortsTbody) {
                        el.fetchedPortsTbody.innerHTML = !preview.length
                            ? `<tr><td colspan="7" class="text-center text-muted py-4">No usable ports fetched from OLT.</td></tr>`
                            : preview.map((p) => {
                                const valueText = p.board_type === 'CONTROL'
                                    ? ((p.allowed_svlans && p.allowed_svlans.length) ? p.allowed_svlans.join(', ') : '-')
                                    : (p.svlan ?? '-');

                                const statusText = `${p.board_status || '-'} / ${p.link_status || '-'}`;
                                const importableText = p.importable
                                    ? '<span class="badge bg-success">YES</span>'
                                    : '<span class="badge bg-danger">NO</span>';

                                return `
                                    <tr>
                                        <td class="nx-text-mono">${escape(p.port_path)}</td>
                                        <td>${escape(p.board_name || '-')}</td>
                                        <td>${escape(p.port_type || '-')}</td>
                                        <td>${escape(statusText)}</td>
                                        <td>${importableText}</td>
                                        <td>${p.exists_in_db ? '<span class="badge bg-success">YES</span>' : '<span class="badge bg-warning text-dark">NO</span>'}</td>
                                        <td>${escape(valueText)}</td>
                                    </tr>
                                `;
                            }).join('');
                    }

                    const total = preview.length;
                    const importable = preview.filter((p) => p.importable === true);
                    const newImportable = importable.filter((p) => !p.exists_in_db);
                    const existing = preview.filter((p) => p.exists_in_db).length;

                    if (el.fetchSummaryText) {
                        el.fetchSummaryText.innerHTML = `${total} ports fetched • ${existing} already in DB • ${newImportable.length} new importable ports`;
                    }

                    if (el.importFetchedPortsSubmitBtn) {
                        if (newImportable.length === 0) {
                            el.importFetchedPortsSubmitBtn.disabled = true;
                            el.importFetchedPortsSubmitBtn.innerHTML = 'Nothing to Import';
                        } else {
                            el.importFetchedPortsSubmitBtn.disabled = false;
                            el.importFetchedPortsSubmitBtn.innerHTML = '<i class="bi bi-download"></i> Import Fetched Ports';
                        }
                    }

                    if (el.fetchPortsBoardsInfo) {
                        el.fetchPortsBoardsInfo.innerHTML = boards.length
                            ? `Boards detected: ${boards.map((b) => `${escape(b.board_name)} (${escape(b.board_status)}) @ 0/${Number(b.slot)}`).join(' | ')}`
                            : '';
                    }

                    openModal(el.fetchPortsModal);
                    return result;
                }
            });
        } catch (err) {
            ui.toast('error', errorMessage(err, 'Fetch failed.'));
        } finally {
            release();
        }
    }

    function ensureControlBoardVlanModal() {
    if (document.getElementById('controlBoardVlanModal')) return;

    const shell = document.createElement('div');
    shell.innerHTML = `
        <div class="modal fade" id="controlBoardVlanModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content nx-modal">
                    <div class="modal-header nx-modal-header">
                        <div>
                            <h5 class="modal-title">Bind VLAN to Control Board Port</h5>
                            <div class="small text-muted" id="controlBoardVlanSubtitle">Select VLAN type and VLAN from VLAN Management.</div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body nx-modal-body">
                        <form id="controlBoardVlanForm">
                            <div class="col-12 col-md-6">
                                <label class="form-label">Control Board Port</label>
                                <select class="form-select" id="controlBoardVlanPortIdDisplay" disabled>
                                    <option value="">Selected control board port</option>
                                </select>

                                <input type="hidden" name="olt_port_id" id="controlBoardVlanPortId">
                            </div>

                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="alert alert-info mb-0">
                                        <strong>Huawei CLI:</strong>
                                        <span class="nx-text-mono" id="controlBoardVlanCliPreview">port vlan &lt;vlan&gt; 0/3 0</span>
                                    </div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label">VLAN Type</label>
                                    <select class="form-select" name="vlan_type" id="controlBoardVlanType">
                                        <option value="SERVICE">SERVICE VLAN</option>
                                        <option value="MGMT">MGMT VLAN</option>
                                    </select>
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label">VLAN</label>
                                    <select class="form-select" name="network_vlan_id" id="controlBoardNetworkVlanId">
                                        <option value="">Select VLAN</option>
                                    </select>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="modal-footer nx-modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="saveControlBoardVlanBtn">
                            <i class="bi bi-save"></i>
                            <span>Save Binding</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(shell);
}

function ensurePonSvlanModal() {
    if (document.getElementById('ponSvlanModal')) return;

    const shell = document.createElement('div');
    shell.innerHTML = `
        <div class="modal fade" id="ponSvlanModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content nx-modal">
                    <div class="modal-header nx-modal-header">
                        <div>
                            <h5 class="modal-title mb-0">Assign SVLAN to PON Port</h5>
                            <div class="small text-muted">One Service VLAN per PON port</div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body nx-modal-body">
                        <form id="ponSvlanForm">
                            <input type="hidden" id="ponSvlanPortId" name="olt_port_id">

                            <div class="mb-3">
                                <label class="form-label">PON Port</label>
                                <input type="text" id="ponSvlanPortDisplay" class="form-control" disabled>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Service VLAN</label>
                                <select class="form-select" name="svlan" id="ponSvlanNetworkVlanId">
                                    <option value="">Select SVLAN</option>
                                </select>
                            </div>
                        </form>
                    </div>

                    <div class="modal-footer nx-modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="savePonSvlanBtn">
                            <i class="bi bi-save"></i>
                            <span>Save</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(shell);
}

async function openPonSvlanModal(ctx, portId) {
    ensurePonSvlanModal();

    const port = findPortById(ctx.state.ports, portId);

    if (!port) {
        ui.toast('error', 'PON port not found.');
        return;
    }

    if (isControlBoardPort(port)) {
        ui.toast('error', 'SVLAN assignment is only for PON ports.');
        return;
    }

    const portInput = $('#ponSvlanPortId');
    const portDisplay = $('#ponSvlanPortDisplay');
    const vlanSelect = $('#ponSvlanNetworkVlanId');
    const saveBtn = $('#savePonSvlanBtn');

    if (!portInput || !portDisplay || !vlanSelect || !saveBtn) {
        ui.toast('error', 'PON SVLAN modal elements are missing.');
        return;
    }

    portInput.value = String(port.id);
    portDisplay.value = port.port_path || `${port.frame}/${port.slot}/${port.port}`;

    vlanSelect.innerHTML = `<option value="">Loading Service VLANs...</option>`;

    try {
        const res = await api.get(PON_SVLAN_ENDPOINTS.options(ctx.state.selectedOltId, port.id));
        const rows = safeArray(res?.data || res);

        vlanSelect.innerHTML = `
            <option value="">Select Service VLAN</option>
            ${rows.map((row) => `
               <option
                    value="${Number(row.vlan_id)}"
                    ${Number(row.vlan_id) === Number(port.svlan || 0) ? 'selected' : ''}
                >
    VLAN ${escape(row.vlan_id)}${row.name ? ` - ${escape(row.name)}` : ''}
</option>
            `).join('')}
        `;
    } catch (err) {
        vlanSelect.innerHTML = `<option value="">Failed to load VLANs</option>`;
        ui.toast('error', errorMessage(err, 'Failed to load Service VLANs.'));
    }

    saveBtn.onclick = async (e) => {
        e.preventDefault();
        await savePonSvlan(ctx);
    };

    openModal($('#ponSvlanModal'));
}

async function savePonSvlan(ctx) {
    const portId = Number($('#ponSvlanPortId')?.value || 0);
    const svlan = Number($('#ponSvlanNetworkVlanId')?.value || 0);
    const saveBtn = $('#savePonSvlanBtn');

    if (!portId) {
        ui.toast('error', 'Missing PON port.');
        return;
    }

    if (!svlan) {
        ui.toast('error', 'Please select a Service VLAN.');
        return;
    }

    const release = withButtonLoading(saveBtn, 'Saving...');

    try {
        const result = await api.form(
            PON_SVLAN_ENDPOINTS.save,
            forms.data({
                olt_port_id: portId,
                svlan: svlan
            })
        );

        closeModal($('#ponSvlanModal'));

        lastPortsTableSignature = '';

        await loadPorts(ctx, ctx.state.selectedOltId);

        ui.toast('success', result.message || 'PON port SVLAN assigned.');
    } catch (err) {
        ui.toast('error', errorMessage(err, 'Failed to assign SVLAN.'));
    } finally {
        release();
    }
}

function updateControlBoardVlanCliPreview(ctx) {
    const vlanEl = $('#controlBoardNetworkVlanId');
    const portIdEl = $('#controlBoardVlanPortId');
    const previewEl = $('#controlBoardVlanCliPreview');

    if (!vlanEl || !portIdEl || !previewEl) return;

    const portId = Number(portIdEl.value || 0);

    const port = findPortById(ctx.state.ports, portId)
        || safeArray(ctx.state.controlBoardPorts).find((row) => Number(row.id) === portId);

    const selectedOption = vlanEl.options[vlanEl.selectedIndex];
    const vlanId = selectedOption?.dataset?.vlanId || '<vlan>';

    const frame = port ? Number(port.frame || 0) : '<frame>';
    const slot = port ? Number(port.slot || 0) : '<slot>';
    const portNo = port ? Number(port.port || 0) : '<port>';

    previewEl.textContent = `port vlan ${vlanId} ${frame}/${slot} ${portNo}`;
}

    function fixOltCliPreviewTheme() {
        const isDark = document.documentElement.dataset.theme === 'dark';

        document.querySelectorAll('.modal pre, .modal code, .modal textarea, .modal .nx-code-block, .modal [id*="CliPreview"]').forEach((el) => {
            if (isDark) {
                el.style.setProperty('background', '#020617', 'important');
                el.style.setProperty('background-color', '#020617', 'important');
                el.style.setProperty('color', '#dbeafe', 'important');
                el.style.setProperty('border', '1px solid #334155', 'important');
                el.style.setProperty('border-radius', '3px', 'important');
                el.style.setProperty('box-shadow', 'none', 'important');
            } else {
                el.style.removeProperty('background');
                el.style.removeProperty('background-color');
                el.style.removeProperty('color');
                el.style.removeProperty('border');
                el.style.removeProperty('border-radius');
                el.style.removeProperty('box-shadow');
            }
        });

        document.querySelectorAll('.modal .nx-code-block-wrap').forEach((el) => {
            if (isDark) {
                el.style.setProperty('background', '#020617', 'important');
                el.style.setProperty('background-color', '#020617', 'important');
                el.style.setProperty('border', '1px solid #334155', 'important');
                el.style.setProperty('border-radius', '3px', 'important');
                el.style.setProperty('padding', '14px', 'important');
            } else {
                el.style.removeProperty('background');
                el.style.removeProperty('background-color');
                el.style.removeProperty('border');
                el.style.removeProperty('border-radius');
                el.style.removeProperty('padding');
            }
        });
    }

    document.addEventListener('shown.bs.modal', fixOltCliPreviewTheme);
    document.addEventListener('nx:theme-change', fixOltCliPreviewTheme);
    document.addEventListener('nx:page-load', fixOltCliPreviewTheme);
    setTimeout(fixOltCliPreviewTheme, 100);


async function openControlBoardVlanModal(ctx, portId) {
    ensureControlBoardVlanModal();

    const port = findPortById(ctx.state.ports, portId)
        || safeArray(ctx.state.controlBoardPorts).find((row) => Number(row.id) === Number(portId));

    if (!port) {
        ui.toast('error', 'Control board port not found.');
        return;
    }

    if (!isControlBoardPort(port)) {
        ui.toast('error', 'VLAN binding is allowed only on control board ports 0/3 and 0/4.');
        return;
    }

    const subtitle = $('#controlBoardVlanSubtitle');
    const portInput = $('#controlBoardVlanPortId');
    const portDisplay = $('#controlBoardVlanPortIdDisplay');
    const typeSelect = $('#controlBoardVlanType');
    const vlanSelect = $('#controlBoardNetworkVlanId');
    const saveBtn = $('#saveControlBoardVlanBtn');

    const portLabel = `
        ${escape(port.port_path || `${port.frame}/${port.slot}/${port.port}`)}
        ${port.board_name ? ` - ${escape(port.board_name)}` : ''}
        ${port.port_type ? ` (${escape(port.port_type)})` : ''}
    `.trim();

    if (subtitle) {
        subtitle.textContent = `Binding VLANs to ${port.port_path || `${port.frame}/${port.slot}/${port.port}`}`;
    }

    if (portInput) {
        portInput.value = String(port.id);
    }

    if (portDisplay) {
        portDisplay.innerHTML = `<option selected>${portLabel}</option>`;
    }

    if (typeSelect) {
        typeSelect.value = 'SERVICE';
    }

    await fillControlBoardVlanDropdown(ctx, 'SERVICE');

    if (typeSelect) {
        typeSelect.onchange = async () => {
            await fillControlBoardVlanDropdown(ctx, typeSelect.value);
        };
    }

    if (vlanSelect) {
        vlanSelect.onchange = () => updateControlBoardVlanCliPreview(ctx);
    }

    if (saveBtn) {
        saveBtn.onclick = async (e) => {
            e.preventDefault();
            await saveControlBoardVlanBinding(ctx);
        };
    }

    updateControlBoardVlanCliPreview(ctx);
    openModal($('#controlBoardVlanModal'));
}

    async function saveControlBoardVlanBinding(ctx) {
    const form = $('#controlBoardVlanForm');
    const saveBtn = $('#saveControlBoardVlanBtn');

    if (!form) return;

    const portId = Number($('#controlBoardVlanPortId')?.value || 0);
    const networkVlanId = Number($('#controlBoardNetworkVlanId')?.value || 0);

    if (!portId) {
        ui.toast('error', 'Control board port is required.');
        return;
    }

    if (!networkVlanId) {
        ui.toast('error', 'Please select a VLAN.');
        return;
    }

    const release = withButtonLoading(saveBtn, 'Deploying...');

    try {
        const result = await api.form(CONTROL_VLAN_ENDPOINTS.create, new FormData(form));

        closeModal($('#controlBoardVlanModal'));

        lastPortsTableSignature = '';

        await loadControlBoardVlanWorkspace(ctx, ctx.state.selectedOltId);
        await loadPorts(ctx, ctx.state.selectedOltId);

        ui.toast('success', result.message || 'VLAN binding deployed and saved.');
    } catch (err) {
        ui.toast('error', errorMessage(err, 'Failed to save VLAN binding.'));
    } finally {
        release();
    }
}

    async function deleteControlBoardVlanBinding(ctx, id, meta = {}) {
    const vlanId = meta.vlanId || '';
    const vlanType = String(meta.vlanType || '').toUpperCase();
    const vlanLabel = vlanId
        ? `${vlanType === 'MGMT' ? 'MGMT VLAN' : 'Service VLAN'} ${vlanId}`
        : 'this VLAN binding';

    await actions.run({
        confirm: {
            title: 'Unbind VLAN from Control Board?',
            text: `This will remove ${vlanLabel} from the OLT control board port.`,
            confirmButtonText: 'Yes, unbind'
        },
        loading: 'Unbinding VLAN from OLT...',
        task: async () => {
            const result = await api.form(CONTROL_VLAN_ENDPOINTS.delete, forms.data({ id }));

            lastPortsTableSignature = '';

            await loadControlBoardVlanWorkspace(ctx, ctx.state.selectedOltId);
            await loadPorts(ctx, ctx.state.selectedOltId);

            return result;
        },
        onSuccess: (result) => ui.toast('success', result.message || `${vlanLabel} unbound successfully.`),
        onError: (err) => ui.toast('error', errorMessage(err, 'Failed to unbind VLAN.'))
    });
}

    component.define('olt-physical-panel', {
        render(props = {}) {
            const state = props.state || {};
            const selectedOltId = Number(state.selectedOltId || 0);

            if (!selectedOltId) {
                return render.emptyState({
                    title: 'No OLT selected',
                    text: 'Select an OLT device first to display the front panel.'
                });
            }

            return `
                <div class="ma5800-svg-twin">
                    <img
                        src="${escape(RESOLVED_IMAGE_PATH)}"
                        alt="Huawei MA5800-X2 Front Panel"
                        class="ma5800-base-image"
                        onerror="this.style.display='none'; this.closest('.ma5800-svg-twin')?.classList.add('image-load-failed')"
                    >
                    <svg class="ma5800-svg-overlay" viewBox="0 0 ${SVG_VIEWBOX.width} ${SVG_VIEWBOX.height}" preserveAspectRatio="none">
                        ${renderAllControlPorts(state)}
                        ${renderAllGponPorts(state)}
                    </svg>
                </div>
                <div class="ma5800-legend-real">
                    <span class="ma5800-legend-item"><span class="ma5800-legend-dot online"></span>Online</span>
                    <span class="ma5800-legend-item"><span class="ma5800-legend-dot no-ont"></span>No ONT</span>
                    <span class="ma5800-legend-item"><span class="ma5800-legend-dot offline"></span>Offline</span>
                    <span class="ma5800-legend-item"><span class="ma5800-legend-dot warning"></span>Warning</span>
                </div>
            `;
        }
    });

    const oltPage = page.create({
        state: {
            selectedOltId: Number(savedUiState.selectedOltId || appEl.dataset.initialOltId || 0) || 0,
            selectedSlot: savedUiState.selectedSlot ?? (
                appEl.dataset.initialSlot === '' || appEl.dataset.initialSlot == null
                    ? null
                    : parseInt(appEl.dataset.initialSlot || '0', 10)
            ),
            selectedPortPath: savedUiState.selectedPortPath || null,
            selectedDeviceId: Number(savedUiState.selectedDeviceId || 0) || null,
            activeProfileTab: String(appEl.dataset.activeTab || savedUiState.activeProfileTab || 'dba').toLowerCase(),

            devices: [],
            ports: [],
            controlBoardPorts: [],
            controlBoardVlanBindings: [],
            controlBoardVlanOptions: [],
            vlans: [],
            mgmtVlans: [],

            dbaProfiles: [],
            lineProfiles: [],
            wanProfiles: [],
            tr069Profiles: [],
            srvProfiles: [],

            fetchedPreview: [],

            loading: {
                devices: false,
                ports: false,
                controlBoardVlans: false,
                dbaProfiles: false,
                lineProfiles: false,
                wanProfiles: false,
                tr069Profiles: false,
                srvProfiles: false,
            }
        },

        async init() {
            cacheDom();
            ensureProfileModals();
            await resolveImagePath();
        },

        async events(ctx) {
            dom.on(document, 'change', '#oltFilter', async (e, target) => {
                const nextOltId = Number(target.value || 0) || 0;
                ctx.patch({
                    selectedOltId: nextOltId,
                    selectedSlot: null,
                    selectedPortPath: null,
                    selectedDeviceId: null
                });

                const params = new URLSearchParams(window.location.search);
                if (nextOltId > 0) params.set('olt_id', String(nextOltId));
                else params.delete('olt_id');
                params.delete('selected_slot');
                history.replaceState({}, '', `/olt-management/ports${params.toString() ? `?${params.toString()}` : ''}`);

                await loadPorts(ctx, nextOltId);
                await loadControlBoardVlanWorkspace(ctx, nextOltId);
            });

            dom.on(document, 'change', '#profilesOltSelector', async (e, target) => {
                const nextOltId = Number(target.value || 0) || 0;
                const nextTab = String(ctx.state.activeProfileTab || 'dba');

                ctx.patch({
                    selectedOltId: nextOltId,
                    selectedDeviceId: null
                });

                if (nextOltId > 0) {
                    await loadVlanOptions(ctx);
                    await loadProfileRows(ctx, 'dba', nextOltId);

                    if (nextTab !== 'dba') {
                        await loadProfileRows(ctx, nextTab, nextOltId);
                    }
                } else {
                    ctx.patch({
                        vlans: [],
                        mgmtVlans: [],
                        dbaProfiles: [],
                        lineProfiles: [],
                        wanProfiles: [],
                        tr069Profiles: [],
                        srvProfiles: []
                    });
                }

                const params = new URLSearchParams(window.location.search);
                if (nextOltId > 0) {
                    params.set('olt_id', String(nextOltId));
                    params.set('tab', nextTab);
                    history.replaceState({}, '', `/olt-management/profiles?${params.toString()}`);
                } else {
                    history.replaceState({}, '', '/olt-management/profiles');
                }
            });

            dom.on(document, 'click', '[data-profile-tab]', async (e, target) => {
                e.preventDefault();
                const tab = String(target.dataset.profileTab || 'dba').toLowerCase();
                setActiveProfileTab(ctx, tab);

                if (Number(ctx.state.selectedOltId) > 0) {
                    await loadProfileRows(ctx, tab, ctx.state.selectedOltId);
                }
            });

            dom.on(document, 'click', '.js-profile-create', async (e, target) => {
                e.preventDefault();

                const tab = String(target.dataset.tab || ctx.state.activeProfileTab || 'dba').toLowerCase();

                if (tab === 'line' && Number(ctx.state.selectedOltId) > 0) {
                    await loadProfileRows(ctx, 'line', ctx.state.selectedOltId);
                }

                openCreateProfileModal(ctx, tab);
            });

            dom.on(document, 'click', '#oltPhysicalView .ma5800-svg-port-group, #oltPhysicalView .ma5800-svg-port-rect, #oltPhysicalView .ma5800-svg-port-label', (e, target) => {
                e.preventDefault();
                e.stopPropagation();

                const group = target.closest('.ma5800-svg-port-group');
                if (!group) return;

                const slot = Number(group.dataset.slot || 0);
                const portPath = group.dataset.portPath || null;
                if (!slot) return;

                ctx.patch({
                    selectedSlot: slot,
                    selectedPortPath: portPath
                });

                syncPhysicalSelection(ctx);
            });

            dom.on(document, 'click', '.js-clear-port-filter', (e) => {
                e.preventDefault();
                ctx.patch({
                    selectedSlot: null,
                    selectedPortPath: null
                });

                if (pageMode === 'ports') {
                    const params = new URLSearchParams(window.location.search);
                    params.delete('selected_slot');
                    history.replaceState({}, '', `/olt-management/ports${params.toString() ? `?${params.toString()}` : ''}`);
                }

                syncPhysicalSelection(ctx);
            });

            if (el.profileCrudSaveBtn) {
                el.profileCrudSaveBtn.onclick = async (e) => {
                    e.preventDefault();
                    await saveProfileFromModal(ctx);
                };
            }

            el.createDeviceForm?.addEventListener('submit', async (e) => {
                e.preventDefault();

                const submitBtn = el.createDeviceForm.querySelector('button[type="submit"]');
                const release = withButtonLoading(submitBtn, 'Saving...');

                try {
                    await jobs.run({
                        title: 'Saving device...',
                        success: '',
                        task: async () => {
                            const result = await api.form(
                                '/api/v1/olt-management/device/create',
                                buildDeviceFormData(el.createDeviceForm)
                            );
                            closeModal(el.createDeviceModal);
                            forms.reset(el.createDeviceForm);
                            await loadDevices(ctx);
                            ui.toast('success', result.message || 'OLT device created.');
                            return result;
                        }
                    });
                } catch (err) {
                    ui.toast('error', errorMessage(err, 'Create failed.'));
                } finally {
                    release();
                }
            });

            el.editDeviceForm?.addEventListener('submit', async (e) => {
                e.preventDefault();

                const id = el.editDeviceForm.dataset.id;
                if (!id) {
                    ui.toast('error', 'Missing device ID.');
                    return;
                }

                const submitBtn = el.editDeviceForm.querySelector('button[type="submit"]');
                const release = withButtonLoading(submitBtn, 'Saving...');

                try {
                    await jobs.run({
                        title: 'Updating device...',
                        success: '',
                        task: async () => {
                            const result = await api.form(
                                `/api/v1/olt-management/device/update/${id}`,
                                buildDeviceFormData(el.editDeviceForm)
                            );
                            closeModal(el.editDeviceModal);
                            await loadDevices(ctx);

                            if (pageMode === 'ports' && Number(id) === Number(ctx.state.selectedOltId)) {
                                await loadPorts(ctx, ctx.state.selectedOltId);
                            }

                            if (pageMode === 'profiles' && Number(id) === Number(ctx.state.selectedOltId)) {
                                await loadVlanOptions(ctx);
                                await loadProfileRows(ctx, 'dba', ctx.state.selectedOltId);
                                await refreshActiveProfileTab(ctx);
                            }

                            ui.toast('success', result.message || 'OLT device updated.');
                            return result;
                        }
                    });
                } catch (err) {
                    ui.toast('error', errorMessage(err, 'Update failed.'));
                } finally {
                    release();
                }
            });

            el.importFetchedPortsForm?.addEventListener('submit', async (e) => {
                e.preventDefault();

                const submitBtn = el.importFetchedPortsForm.querySelector('button[type="submit"]');
                const release = withButtonLoading(submitBtn, 'Importing...');

                try {
                    await jobs.run({
                        title: 'Importing fetched ports...',
                        success: '',
                        task: async () => {
                            const result = await api.form('/api/v1/olt-management/port/import-fetched', new FormData(el.importFetchedPortsForm));
                            closeModal(el.fetchPortsModal);
                            await loadPorts(ctx, ctx.state.selectedOltId);
                            ui.toast('success', result.message || 'Import completed.');
                            return result;
                        }
                    });
                } catch (err) {
                    ui.toast('error', errorMessage(err, 'Import failed.'));
                } finally {
                    release();
                }
            });
        },

        async load(ctx) {
            await loadDevices(ctx);

            if (pageMode === 'ports' && Number(ctx.state.selectedOltId) > 0) {
                await loadPorts(ctx, ctx.state.selectedOltId);
                await loadControlBoardVlanWorkspace(ctx, ctx.state.selectedOltId);
            }

            if (pageMode === 'profiles' && Number(ctx.state.selectedOltId) > 0) {

                // 1. Load VLAN dropdown data (CVLAN + MGMT VLAN)
                await loadVlanOptions(ctx);

                // 2. Always load DBA first (needed for dropdown dependency)
                await loadProfileRows(ctx, 'dba', ctx.state.selectedOltId);

                // 3. Then load the active tab
                const activeTab = String(ctx.state.activeProfileTab || 'dba');

                if (activeTab !== 'dba') {
                    await loadProfileRows(ctx, activeTab, ctx.state.selectedOltId);
                }
            }
        },

        render(ctx) {
            currentOltState = ctx.state || {};
            persistUiState(ctx.state);

            renderHeaderActions(ctx);
            bindHeaderActions(ctx);
            renderKpis(ctx);
            renderToolbar(ctx);
            renderContextChips(ctx);
            renderCards(ctx);
            renderDevicesTable(ctx);
            renderPhysicalView(ctx);
            renderPortsTable(ctx);
            renderProfilesWorkspace(ctx);
            syncSelectedDeviceRowUi(ctx);

            if (pageMode === 'ports') {
                const params = new URLSearchParams(window.location.search);
                if (Number(ctx.state.selectedOltId) > 0) params.set('olt_id', String(ctx.state.selectedOltId));
                else params.delete('olt_id');
                params.delete('selected_slot');
                history.replaceState({}, '', `/olt-management/ports${params.toString() ? `?${params.toString()}` : ''}`);
            }

            if (pageMode === 'profiles') {
                const params = new URLSearchParams(window.location.search);
                if (Number(ctx.state.selectedOltId) > 0) {
                    params.set('olt_id', String(ctx.state.selectedOltId));
                    params.set('tab', String(ctx.state.activeProfileTab || 'dba'));
                    history.replaceState({}, '', `/olt-management/profiles?${params.toString()}`);
                } else {
                    history.replaceState({}, '', '/olt-management/profiles');
                }
            }
        }
    });

    oltPage.start();
});
