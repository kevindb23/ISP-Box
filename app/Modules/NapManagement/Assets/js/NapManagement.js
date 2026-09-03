(function () {
    document.addEventListener('DOMContentLoaded', () => {
        const app = document.getElementById('napManagementApp');
        if (!app || !window.NX) return;
        const {
            api,
            ui,
            dom,
            util,
            modal,
            select,
            state,
            component,
            renderComponent,
            module,
            maps,
            forms,
            table,
            actions,
            dialog,
            formBuilder
        } = window.NX;
        const { $, $$, html, text, val, show, hide, on } = dom;
        const { escape, num, upper, safeArray } = util;
        const { fill: fillSelect, clear: clearSelect } = select;
        const STORE_KEY = 'nap-management-v10';
        const UI_STATE_KEY = 'nap-management-ui-v10';

        function readUiState() {
            try {
                return JSON.parse(localStorage.getItem(UI_STATE_KEY) || '{}');
            } catch {
                return {};
            }
        }

        function writeUiState(next = {}) {
            try {
                const current = readUiState();
                localStorage.setItem(UI_STATE_KEY, JSON.stringify({
                    ...current,
                    ...next
                }));
            } catch {}
        }

        function getSyntheticPlannerPositions() {
            const positions = readUiState().plannerSyntheticPositions;
            return positions && typeof positions === 'object' ? positions : {};
        }

        function saveSyntheticPlannerPosition(nodeId, position) {
            writeUiState({
                plannerSyntheticPositions: {
                    ...getSyntheticPlannerPositions(),
                    [nodeId]: {
                        x: Number(position.x),
                        y: Number(position.y)
                    }
                }
            });
        }

        const savedUi = window.__NAP_BOOT_STATE__ || readUiState();

        /*
        |--------------------------------------------------------------------------
        | Force boot-restored tab into dataset before first render
        |--------------------------------------------------------------------------
        */
        app.dataset.initialTab = window.__NAP_BOOT_TAB__ || savedUi.tab || app.dataset.initialTab || 'planner';

        const initialState = {
            tab: window.__NAP_BOOT_TAB__ || savedUi.tab || app.dataset.initialTab || 'planner',
            odfs: [],
            lcps: [],
            naps: [],
            odfPortGrid: [],
            lcpPortGrid: [],
            napPortGrid: [],
            odfCandidates: [],
            lcpCandidates: [],
            napCandidates: [],
            oltDevices: [],
            plannerOltPorts: [],
            plannerObjects: {
                nodes: [],
                links: []
            },
            planner: {
                view: 'logical',
                cy: null,
                map: null,
                mapLayers: {
                    markers: null,
                    links: null
                },
                selectedNode: null,
                selectedEdge: null,
                connectSourceNode: null,
                scopeMode: 'ALL',
                oltId: '',
                oltPortId: '',
                plannerCreateReturnTab: null,
                trace: {
                    upstreamNodeIds: [],
                    upstreamEdgeIds: [],
                    downstreamNodeIds: [],
                    downstreamEdgeIds: []
                },
                drag: {
                    active: false,
                    startX: 0,
                    startY: 0,
                    initialLeft: 0,
                    initialTop: 0
                }
            },
            edit: {
                odfId: null,
                lcpId: null,
                napId: null
            },
            mapPickers: {
                odf: null,
                lcp: null,
                nap: null
            },
            linksTable: {
                q: '',
                page: 1,
                perPage: 10,
                sortKey: 'identification',
                sortDir: 'asc'
            },
            nodesTable: {
                q: '',
                page: 1,
                perPage: 10
            }
        };
        const store = state.create(STORE_KEY, initialState);
        const refs = {
            page: {
                subtitle: $('#napPageSubtitle'),
                toolbarArea: $('#napToolbarArea'),
                contentArea: $('#napContentArea')
            },
            summary: {
                section: $('#napSummarySection'),
                odfs: $('#summaryOdfs'),
                odfPorts: $('#summaryOdfPorts'),
                lcps: $('#summaryLcps'),
                lcpPorts: $('#summaryLcpPorts'),
                naps: $('#summaryNaps'),
                napPorts: $('#summaryNapPorts')
            },
            odf: {
                form: $('#odfForm'),
                id: $('#odfIdInput'),
                name: $('#odfNameInput'),
                code: $('#odfCodeInput'),
                ports: $('#odfPortsInput'),
                status: $('#odfStatusSelect'),
                olt: $('#odfOltSelect'),
                oltPort: $('#odfOltPortSelect'),
                inputPort: $('#odfInputPortSelect'),
                location: $('#odfLocationInput'),
                latitude: $('#odfLatitudeInput'),
                longitude: $('#odfLongitudeInput'),
                remarks: $('#odfRemarksInput'),
                mapEl: $('#odfMapPicker'),
                title: $('#odfModalTitle'),
                subtitle: $('#odfModalSubtitle'),
                notice: $('#odfEditNotice'),
                modalEl: $('#odfModal')
            },
            lcp: {
                form: $('#lcpForm'),
                id: $('#lcpIdInput'),
                code: $('#lcpCodeInput'),
                name: $('#lcpNameInput'),
                ports: $('#lcpPortsInput'),
                parentOdf: $('#lcpParentOdfSelect'),
                parentOdfPort: $('#lcpParentOdfPortSelect'),
                inputPort: $('#lcpInputPortSelect'),
                location: $('#lcpLocationInput'),
                latitude: $('#lcpLatitudeInput'),
                longitude: $('#lcpLongitudeInput'),
                mapEl: $('#lcpMapPicker'),
                title: $('#lcpModalTitle'),
                subtitle: $('#lcpModalSubtitle'),
                notice: $('#lcpEditNotice'),
                modalEl: $('#lcpModal')
            },
            nap: {
                form: $('#napForm'),
                id: $('#napIdInput'),
                code: $('#napCodeInput'),
                name: $('#napNameInput'),
                splitterPorts: $('#napSplitterPortsInput'),
                parentType: $('#napParentTypeSelect'),
                parentBox: $('#napParentBoxSelect'),
                parentPort: $('#napParentPortSelect'),
                feedMode: $('#napFeedModeSelect'),
                location: $('#napLocationInput'),
                latitude: $('#napLatitudeInput'),
                longitude: $('#napLongitudeInput'),
                mapEl: $('#napMapPicker'),
                title: $('#napModalTitle'),
                subtitle: $('#napModalSubtitle'),
                notice: $('#napEditNotice'),
                modalEl: $('#napModal')
            },
            viewer: {
                modalEl: $('#boxViewerModal'),
                title: $('#boxViewerTitle'),
                subtitle: $('#boxViewerSubtitle'),
                type: $('#boxViewerType'),
                name: $('#boxViewerName'),
                location: $('#boxViewerLocation'),
                status: $('#boxViewerStatus'),
                cabinet: $('#boxViewerCabinet'),
                badgeName: $('#boxViewerBadgeName'),
                badgeType: $('#boxViewerBadgeType'),
                ratio: $('#boxViewerRatio'),
                portsArea: $('#boxViewerPortsArea'),
                extraInfo: $('#boxViewerExtraInfo'),
            },
            plannerObjectModalEl: $('#plannerObjectModal')
        };
        const getState = () => store.get();
        const patch = (obj) => store.patch(obj);
        const patchPlanner = (obj = {}) => {
            patch({
                planner: {
                    ...getState().planner,
                    ...obj
                }
            });
        };
        const patchEdit = (obj = {}) => {
            patch({
                edit: {
                    ...getState().edit,
                    ...obj
                }
            });
        };

        function markAppReady() {
            app.classList.add('nap-ready');
            app.style.visibility = 'visible';
        }
        function resetGeoFields(refsGroup) {
            if (!refsGroup?.location) return;

            delete refsGroup.location.dataset.lastGeocodedLat;
            delete refsGroup.location.dataset.lastGeocodedLng;
            delete refsGroup.location.dataset.lastLat;
            delete refsGroup.location.dataset.lastLng;
            delete refsGroup.location.dataset.geoStatus;
        }
        const formInstances = {
            odf: null,
            lcp: null,
            nap: null
        };
        function getLinksUiRefs() {
            return {
                searchInput: $('#linksSearchInput'),
                perPageSelect: $('#linksPerPageSelect'),
                tbody: $('#linksTableBody'),
                pageMeta: $('#linksPageMeta'),
                prevBtn: $('#linksPrevBtn'),
                nextBtn: $('#linksNextBtn'),
                sortButtons: $$('[data-links-sort]')
            };
        }
        function getNodesUiRefs() {
            return {
                searchInput: $('#nodesSearchInput'),
                perPageSelect: $('#nodesPerPageSelect'),
                grid: $('#nodesCardGrid'),
                pageMeta: $('#nodesPageMeta'),
                prevBtn: $('#nodesPrevBtn'),
                nextBtn: $('#nodesNextBtn')
            };
        }
        const buildFormData = (obj = {}) => forms.data(obj);
        const leafletPickers = {
            odf: null,
            lcp: null,
            nap: null
        };
        function toFloatOrNull(value) {
            const n = parseFloat(String(value ?? '').trim());
            return Number.isFinite(n) ? n : null;
        }
        function buildPinnedLocation(lat, lng) {
            return `Pinned: ${Number(lat).toFixed(6)}, ${Number(lng).toFixed(6)}`;
        }
        function buildReadableLocation(address = {}, fallbackLat = null, fallbackLng = null) {
            const parts = [
                address.road,
                address.neighbourhood,
                address.suburb,
                address.village,
                address.hamlet,
                address.barangay,
                address.city,
                address.town,
                address.municipality,
                address.province,
                address.state,
                address.country
            ].filter(Boolean);
            const uniqueParts = [...new Set(parts.map((x) => String(x).trim()).filter(Boolean))];
            if (uniqueParts.length) {
                return uniqueParts.join(', ');
            }
            if (fallbackLat != null && fallbackLng != null) {
                return buildPinnedLocation(fallbackLat, fallbackLng);
            }
            return '';
        }
        async function reverseGeocodeLatLng(lat, lng) {
            const url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${encodeURIComponent(lat)}&lon=${encodeURIComponent(lng)}`;

            const res = await fetch(url, {
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!res.ok) {
                throw new Error(`Reverse geocoding failed with status ${res.status}.`);
            }

            return await res.json();
        }

        async function fillLocationFromLatLng(refsGroup, lat, lng) {
            if (!refsGroup?.location) return;

            const currentLat = Number(lat).toFixed(6);
            const currentLng = Number(lng).toFixed(6);

            refsGroup.location.dataset.geoStatus = 'loading';
            refsGroup.location.dataset.lastLat = currentLat;
            refsGroup.location.dataset.lastLng = currentLng;

            try {
                const json = await reverseGeocodeLatLng(lat, lng);

                if (
                    refsGroup.location.dataset.lastLat &&
                    refsGroup.location.dataset.lastLng &&
                    (
                        refsGroup.location.dataset.lastLat !== currentLat ||
                        refsGroup.location.dataset.lastLng !== currentLng
                    )
                ) {
                    return;
                }

                const readable =
                    buildReadableLocation(json?.address || {}, lat, lng) ||
                    json?.display_name ||
                    buildPinnedLocation(lat, lng);

                refsGroup.location.value = readable;
                refsGroup.location.dataset.geoStatus = 'done';
                refsGroup.location.dataset.lastGeocodedLat = currentLat;
                refsGroup.location.dataset.lastGeocodedLng = currentLng;

            } catch (err) {
                console.warn('Reverse geocode error:', err);

                if (
                    refsGroup.location.dataset.lastLat === currentLat &&
                    refsGroup.location.dataset.lastLng === currentLng
                ) {
                    refsGroup.location.dataset.geoStatus = 'error';

                    if (!refsGroup.location.value?.trim()) {
                        refsGroup.location.value = buildPinnedLocation(lat, lng);
                    }
                }
            }
        }

        function setPickerPoint(pickerKey, lat, lng, refsGroup, options = {}) {
            const picker = leafletPickers[pickerKey];
            if (!picker || lat == null || lng == null) return;

            const {
                pan = true,
                updateLocation = true,
                locationText = null
            } = options;

            const latFixed = Number(lat).toFixed(6);
            const lngFixed = Number(lng).toFixed(6);

            if (!picker.marker) {
                picker.marker = L.marker([lat, lng], {
                    draggable: true
                }).addTo(picker.map);

                picker.marker.on('dragend', (e) => {
                    const p = e.target.getLatLng();
                    setPickerPoint(pickerKey, p.lat, p.lng, refsGroup, {
                        pan: false,
                        updateLocation: true
                    });
                });
            } else {
                picker.marker.setLatLng([lat, lng]);
            }

            if (pan) {
                picker.map.setView(
                    [lat, lng],
                    picker.map.getZoom() < 15 ? 15 : picker.map.getZoom()
                );
            }

            if (refsGroup.latitude) refsGroup.latitude.value = latFixed;
            if (refsGroup.longitude) refsGroup.longitude.value = lngFixed;

            if (refsGroup.location) {
                refsGroup.location.dataset.lastLat = latFixed;
                refsGroup.location.dataset.lastLng = lngFixed;
                refsGroup.location.dataset.geoStatus = 'pending';

                if (updateLocation) {
                    refsGroup.location.value = locationText || `📍 ${latFixed}, ${lngFixed}`;
                }
            }

            debouncedReverseGeocodeByPicker[pickerKey]?.(refsGroup, lat, lng);
        }

        const debouncedReverseGeocodeByPicker = {
            odf: util.debounce((refsGroup, lat, lng) => fillLocationFromLatLng(refsGroup, lat, lng), 500),
            lcp: util.debounce((refsGroup, lat, lng) => fillLocationFromLatLng(refsGroup, lat, lng), 500),
            nap: util.debounce((refsGroup, lat, lng) => fillLocationFromLatLng(refsGroup, lat, lng), 500)
        };

        function syncPickerFromInputs(pickerKey, refsGroup, options = {}) {
            const lat = toFloatOrNull(refsGroup.latitude?.value);
            const lng = toFloatOrNull(refsGroup.longitude?.value);

            if (lat == null || lng == null) return;

            setPickerPoint(pickerKey, lat, lng, refsGroup, options);
        }

        function createLeafletPicker(pickerKey, refsGroup) {
            if (!refsGroup?.mapEl || typeof window.L === 'undefined') return null;
            if (leafletPickers[pickerKey]) return leafletPickers[pickerKey];

            maps?.fixLeafletDefaultIcons?.();

            const map = L.map(refsGroup.mapEl, {
                center: [14.5995, 120.9842],
                zoom: 13,
                zoomControl: true
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            const picker = {
                map,
                marker: null,
                resizeObserver: null
            };

            // The picker is created inside an animated modal. Keep Leaflet's
            // viewport in sync with the modal column while it opens or when
            // the responsive layout changes, otherwise its tile grid can be
            // calculated using a stale width.
            if (typeof ResizeObserver !== 'undefined') {
                picker.resizeObserver = new ResizeObserver(() => {
                    window.requestAnimationFrame(() => map.invalidateSize({ pan: false }));
                });
                picker.resizeObserver.observe(refsGroup.mapEl);
            }

            map.on('click', (e) => {
                setPickerPoint(pickerKey, e.latlng.lat, e.latlng.lng, refsGroup, {
                    pan: true,
                    updateLocation: true
                });

                debouncedReverseGeocodeByPicker[pickerKey]?.(refsGroup, e.latlng.lat, e.latlng.lng);
            });

            const syncFromInputs = util.debounce(() => {
                const lat = toFloatOrNull(refsGroup.latitude?.value);
                const lng = toFloatOrNull(refsGroup.longitude?.value);

                if (lat == null || lng == null) return;

                syncPickerFromInputs(pickerKey, refsGroup, {
                    pan: true,
                    updateLocation: false
                });
            }, 250);

            refsGroup.latitude?.addEventListener('input', syncFromInputs);
            refsGroup.longitude?.addEventListener('input', syncFromInputs);

            leafletPickers[pickerKey] = picker;
            return picker;
        }

        function openLeafletPicker(pickerKey, refsGroup) {
            const picker = createLeafletPicker(pickerKey, refsGroup);
            if (!picker) return;

            const refreshViewport = () => picker.map.invalidateSize({ pan: false });
            window.requestAnimationFrame(refreshViewport);
            setTimeout(refreshViewport, 80);

            setTimeout(() => {
                refreshViewport();

                const lat = toFloatOrNull(refsGroup.latitude?.value);
                const lng = toFloatOrNull(refsGroup.longitude?.value);

                if (lat != null && lng != null) {
                    setPickerPoint(pickerKey, lat, lng, refsGroup, {
                        pan: true,
                        updateLocation: false
                    });
                } else {
                    picker.map.setView([14.5995, 120.9842], 13);
                }
                // Bootstrap's modal transition may still be completing at the
                // first measurement on slower browsers.
                setTimeout(refreshViewport, 220);
            }, 180);
        }
        function populateInputPortSelect(selectEl, totalPorts, selectedValue = null, placeholder = 'Select input port') {
            if (!selectEl) return;

            const count = Math.max(0, num(totalPorts, 0));

            if (!count) {
                clearSelect(selectEl, placeholder);
                return;
            }

            const rows = Array.from({ length: count }, (_, i) => {
                const portNo = i + 1;
                return {
                    id: String(portNo),
                    port_number: portNo,
                    label: `Port ${portNo}`
                };
            });

            fillSelect(selectEl, rows, {
                placeholder,
                selected: selectedValue,
                label: (row) => row.label || `Port ${row.port_number || row.id}`
            });
        }

        function getInputPortValue(selectEl) {
            return selectEl?.value || '';
        }
        function getInputPortNumber(row, fallback = 1) {
            return num(
                row?.input_port_number ||
                row?.target_port_number ||
                row?.port_number ||
                fallback,
                fallback
            );
        }

        const sumBy = (rows, key) =>
            safeArray(rows).reduce((sum, row) => sum + num(row?.[key], 0), 0);

        const formatLatLon = (lat, lon) =>
            (lat ?? '') === '' && (lon ?? '') === '' ? '-' : `${lat || '-'} / ${lon || '-'}`;

        const findById = (rows, id) =>
            safeArray(rows).find((x) => num(x.id) === num(id)) || null;

        const getNodeMeta = (type) => {
            const t = upper(type);
            if (t === 'ODF') return { type: 'ODF', icon: 'bi-hdd-network', wrap: 'odf', chip: 'odf' };
            if (t === 'LCP') return { type: 'LCP', icon: 'bi-diagram-3', wrap: 'lcp', chip: 'lcp' };
            return { type: 'NAP', icon: 'bi-box-seam', wrap: 'nap', chip: 'nap' };
        };

        const getPlannerNodeLabel = (row) =>
            row.node_name || row.box_name || row.odf_name || row.name || `${row.node_type || 'NODE'} ${row.id}`;

        const getAllNodeRows = () => {
            const s = getState();
            return [
                ...safeArray(s.odfs).map((row) => ({ ...row, box_type: 'ODF' })),
                ...safeArray(s.lcps).map((row) => ({ ...row, box_type: 'LCP' })),
                ...safeArray(s.naps).map((row) => ({ ...row, box_type: 'NAP' }))
            ];
        };

        const getDetailRow = (type, id) => {
            const s = getState();
            if (type === 'odf') return { ...(findById(s.odfs, id) || {}), box_type: 'ODF' };
            if (type === 'lcp') return { ...(findById(s.lcps, id) || {}), box_type: 'LCP' };
            return { ...(findById(s.naps, id) || {}), box_type: 'NAP' };
        };

        const getPortGridRow = (type, id) => {
            const s = getState();
            if (type === 'odf') return findById(s.odfPortGrid, id);
            if (type === 'lcp') return findById(s.lcpPortGrid, id);
            return findById(s.napPortGrid, id);
        };

        const getOltLabelById = (id) => {
            const row = findById(getState().oltDevices, id);
            return row
                ? (row.name || row.device_name || row.hostname || row.ip_address || `OLT ${row.id}`)
                : '';
        };

        const getPlannerPortLabelById = (id) => {
            const row = findById(getState().plannerOltPorts, id);
            return row
                ? (row.port_path || row.port_name || row.label || `${row.frame ?? 0}/${row.slot ?? 0}/${row.port ?? 0}`)
                : '';
        };

        function isRowInMaintenance(row) {
            return upper(row?.status || '') === 'MAINTENANCE';
        }

        function isSelectableParentRow(row, selectedId = null) {
            if (!row) return false;
            if (selectedId && String(num(row.id, 0)) === String(num(selectedId, 0))) return true;
            return !isRowInMaintenance(row);
        }

        function isSelectableParentPortRow(row, selectedPortId = null) {
            if (!row) return false;

            const rowId = String(num(row.id, 0));
            if (selectedPortId && rowId === String(num(selectedPortId, 0))) return true;

            const status = upper(row.status || row.derived_status || 'AVAILABLE');
            const connectedEntityType = upper(row.connected_entity_type || 'NONE');
            const ownerStatus = upper(
                row.owner_status ||
                row.box_status ||
                row.parent_status ||
                row.source_box_status ||
                'ACTIVE'
            );

            if (ownerStatus === 'MAINTENANCE') return false;
            if (status !== 'AVAILABLE') return false;
            if (connectedEntityType !== 'NONE') return false;

            return true;
        }

        function getMaintenanceBlockedLabel(name, type) {
            return `${name} (${type} in maintenance)`;
        }

        function getLcpParentOdfId(lcp) {
            return num(lcp.parent_odf_id || lcp.source_odf_id || lcp.odf_id || 0);
        }

        function getPlannerNodeSourceRow(nodeRow) {
            const s = getState();
            const refTable = String(nodeRow.reference_table || '');
            const refId = num(nodeRow.reference_id || nodeRow.id);
            const nodeType = upper(nodeRow.node_type || '');

            if (!refId) return null;

            if (refTable === 'odf_nodes') {
                return s.odfs.find((x) => num(x.id) === refId) || null;
            }

            if (refTable === 'network_boxes') {
                if (nodeType === 'LCP') return s.lcps.find((x) => num(x.id) === refId) || null;
                if (nodeType === 'NAP') return s.naps.find((x) => num(x.id) === refId) || null;
                return s.lcps.find((x) => num(x.id) === refId) || s.naps.find((x) => num(x.id) === refId) || null;
            }

            return null;
        }
        function findPlannerNodeRowBySource(refTable, refId, fallbackName = '', expectedType = '') {
            const s = getState();
            const wantedType = upper(expectedType || '');

            const exact = s.plannerObjects.nodes.find((row) => {
                const sameTable = String(row.reference_table || '') === String(refTable || '');
                const sameId = String(row.reference_id || '') === String(refId || '');
                const sameType = !wantedType || upper(row.node_type || '') === wantedType;
                return sameTable && sameId && sameType;
            });
            if (exact) return exact;

            if (!fallbackName) return null;

            return s.plannerObjects.nodes.find((row) => {
                const rowName = row.node_name || row.box_name || row.odf_name || row.name || '';
                const sameName = upper(rowName) === upper(fallbackName);
                const sameType = !wantedType || upper(row.node_type || '') === wantedType;
                return sameName && sameType;
            }) || null;
        }

        function getIncomingPlannerLinkMeta(type, id) {
            const s = getState();
            const typeUpper = upper(type);
            const refTable = typeUpper === 'ODF' ? 'odf_nodes' : 'network_boxes';

            const plannerNode = findPlannerNodeRowBySource(refTable, id, '', typeUpper);
            if (!plannerNode) return null;

            const incomingLink = s.plannerObjects.links.find(
                (link) => num(link.target_node_id) === num(plannerNode.id)
            );
            if (!incomingLink) return null;

            const sourcePlannerNode = s.plannerObjects.nodes.find(
                (node) => num(node.id) === num(incomingLink.source_node_id)
            );

            return {
                link: incomingLink,
                sourcePlannerNode,
                sourceName:
                    incomingLink.source_name ||
                    sourcePlannerNode?.node_name ||
                    sourcePlannerNode?.box_name ||
                    sourcePlannerNode?.odf_name ||
                    sourcePlannerNode?.name ||
                    '-',
                label: incomingLink.label || incomingLink.link_type || ''
            };
        }

        function getPlannerGraphData() {
            const s = getState();
            const nodes = safeArray(s.plannerObjects?.nodes);
            const links = safeArray(s.plannerObjects?.links);

            const nodeMap = new Map(nodes.map((n) => [Number(n.id), n]));
            const incomingByTarget = new Map();
            const outgoingBySource = new Map();

            links.forEach((link) => {
                const sourceId = Number(link.source_node_id || 0);
                const targetId = Number(link.target_node_id || 0);

                if (!incomingByTarget.has(targetId)) incomingByTarget.set(targetId, []);
                if (!outgoingBySource.has(sourceId)) outgoingBySource.set(sourceId, []);

                incomingByTarget.get(targetId).push(link);
                outgoingBySource.get(sourceId).push(link);
            });

            return {
                nodes,
                links,
                nodeMap,
                incomingByTarget,
                outgoingBySource
            };
        }

        function tracePlannerUpstream(startNodeId) {
            const { incomingByTarget } = getPlannerGraphData();

            const nodeIds = [];
            const edgeIds = [];
            let currentId = Number(startNodeId || 0);
            const visited = new Set();

            while (currentId && !visited.has(currentId)) {
                visited.add(currentId);

                const incoming = safeArray(incomingByTarget.get(currentId));
                if (!incoming.length) break;

                const link = incoming[0];
                edgeIds.push(Number(link.id));
                currentId = Number(link.source_node_id || 0);

                if (currentId) nodeIds.push(currentId);
            }

            return { nodeIds, edgeIds };
        }

        function tracePlannerDownstream(startNodeId) {
            const { outgoingBySource } = getPlannerGraphData();

            const nodeIds = [];
            const edgeIds = [];
            const queue = [Number(startNodeId || 0)];
            const visited = new Set([Number(startNodeId || 0)]);

            while (queue.length) {
                const currentId = queue.shift();
                const outgoing = safeArray(outgoingBySource.get(currentId));

                outgoing.forEach((link) => {
                    const edgeId = Number(link.id || 0);
                    const nextNodeId = Number(link.target_node_id || 0);

                    if (edgeId) edgeIds.push(edgeId);

                    if (nextNodeId && !visited.has(nextNodeId)) {
                        visited.add(nextNodeId);
                        nodeIds.push(nextNodeId);
                        queue.push(nextNodeId);
                    }
                });
            }

            return { nodeIds, edgeIds };
        }

        function applyPlannerTraceHighlight() {
            const cy = getState().planner.cy;
            if (!cy) return;

            cy.elements().removeClass(
                'highlighted trace-upstream trace-downstream trace-selected dimmed'
            );

            const trace = getState().planner.trace || {};
            const selectedNode = getState().planner.selectedNode;
            const selectedEdge = getState().planner.selectedEdge;

            const upstreamNodeIds = safeArray(trace.upstreamNodeIds).map((id) => Number(id));
            const upstreamEdgeIds = safeArray(trace.upstreamEdgeIds).map((id) => Number(id));
            const downstreamNodeIds = safeArray(trace.downstreamNodeIds).map((id) => Number(id));
            const downstreamEdgeIds = safeArray(trace.downstreamEdgeIds).map((id) => Number(id));

            /*
            |--------------------------------------------------------------------------
            | Nothing selected / nothing traced
            | Do NOT dim anything
            |--------------------------------------------------------------------------
            */
            const hasTrace =
                upstreamNodeIds.length ||
                upstreamEdgeIds.length ||
                downstreamNodeIds.length ||
                downstreamEdgeIds.length;

            if (!selectedNode && !selectedEdge && !hasTrace) {
                return;
            }

            const activeNodeIds = new Set([
                ...upstreamNodeIds,
                ...downstreamNodeIds
            ]);

            const activeEdgeIds = new Set([
                ...upstreamEdgeIds,
                ...downstreamEdgeIds
            ]);

            const activeSyntheticNodeIds = new Set();
            const activeSyntheticEdgeIds = new Set();

            /*
            |--------------------------------------------------------------------------
            | Selected node
            |--------------------------------------------------------------------------
            */
            if (selectedNode) {
                selectedNode.addClass('trace-selected');

                const rawId = Number(selectedNode.data('raw_id') || 0);
                if (rawId) activeNodeIds.add(rawId);
            }

            /*
            |--------------------------------------------------------------------------
            | Selected edge
            |--------------------------------------------------------------------------
            */
            if (selectedEdge) {
                selectedEdge.addClass('trace-selected');

                const rawId = Number(selectedEdge.data('raw_id') || 0);
                if (rawId) activeEdgeIds.add(rawId);

                const sourceId = Number(selectedEdge.data('source_node_id') || 0);
                const targetId = Number(selectedEdge.data('target_node_id') || 0);

                if (sourceId) activeNodeIds.add(sourceId);
                if (targetId) activeNodeIds.add(targetId);
            }

            /*
            |--------------------------------------------------------------------------
            | Highlight real upstream
            |--------------------------------------------------------------------------
            */
            upstreamNodeIds.forEach((id) => {
                cy.$(`#NODE_${id}`).addClass('trace-upstream');
            });

            upstreamEdgeIds.forEach((id) => {
                cy.$(`#LINK_${id}`).addClass('trace-upstream');
            });

            /*
            |--------------------------------------------------------------------------
            | Highlight real downstream
            |--------------------------------------------------------------------------
            */
            downstreamNodeIds.forEach((id) => {
                cy.$(`#NODE_${id}`).addClass('trace-downstream');
            });

            downstreamEdgeIds.forEach((id) => {
                cy.$(`#LINK_${id}`).addClass('trace-downstream');
            });

            /*
            |--------------------------------------------------------------------------
            | Include source/target nodes of selected edge
            |--------------------------------------------------------------------------
            */
            if (selectedEdge) {
                const sourceNode = selectedEdge.source();
                const targetNode = selectedEdge.target();

                if (sourceNode?.length) {
                    const sourceType = String(sourceNode.data('type') || '').toUpperCase();
                    const sourceRawId = Number(sourceNode.data('raw_id') || 0);

                    if (sourceType === 'OLT') {
                        activeSyntheticNodeIds.add(sourceNode.id());
                    } else if (sourceRawId) {
                        activeNodeIds.add(sourceRawId);
                    }
                }

                if (targetNode?.length) {
                    const targetType = String(targetNode.data('type') || '').toUpperCase();
                    const targetRawId = Number(targetNode.data('raw_id') || 0);

                    if (targetType === 'OLT') {
                        activeSyntheticNodeIds.add(targetNode.id());
                    } else if (targetRawId) {
                        activeNodeIds.add(targetRawId);
                    }
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Include synthetic OLT when active ODF is part of the trace
            |--------------------------------------------------------------------------
            */
            cy.nodes().forEach((node) => {
                const d = node.data();
                const nodeType = String(d.type || '').toUpperCase();

                if (nodeType !== 'ODF') return;

                const rawId = Number(d.raw_id || 0);
                if (!activeNodeIds.has(rawId)) return;

                const incomingEdges = node.incomers('edge');
                incomingEdges.forEach((edge) => {
                    const sourceNode = edge.source();
                    const sourceType = String(sourceNode.data('type') || '').toUpperCase();

                    if (sourceType === 'OLT') {
                        activeSyntheticNodeIds.add(sourceNode.id());
                        activeSyntheticEdgeIds.add(edge.id());

                        sourceNode.addClass('trace-upstream');
                        edge.addClass('trace-upstream');
                    }
                });
            });

            /*
            |--------------------------------------------------------------------------
            | If selected edge itself is synthetic, keep it active too
            |--------------------------------------------------------------------------
            */
            if (selectedEdge) {
                const edgeId = selectedEdge.id();
                if (!String(edgeId).startsWith('LINK_')) {
                    activeSyntheticEdgeIds.add(edgeId);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Dim everything else only while focus mode is active
            |--------------------------------------------------------------------------
            */
            cy.nodes().forEach((node) => {
                const d = node.data();
                const nodeId = node.id();
                const rawId = Number(d.raw_id || 0);
                const nodeType = String(d.type || '').toUpperCase();

                const isActiveReal = rawId && activeNodeIds.has(rawId);
                const isActiveSynthetic = activeSyntheticNodeIds.has(nodeId);
                const isSelectedNode = !!(selectedNode && nodeId === selectedNode.id());
                const isSelectedEdgeEndpoint = !!(
                    selectedEdge &&
                    (nodeId === selectedEdge.source().id() || nodeId === selectedEdge.target().id())
                );

                const syntheticOltKeptByEdge =
                    nodeType === 'OLT' &&
                    selectedEdge &&
                    (nodeId === selectedEdge.source().id() || nodeId === selectedEdge.target().id());

                if (
                    !isActiveReal &&
                    !isActiveSynthetic &&
                    !isSelectedNode &&
                    !isSelectedEdgeEndpoint &&
                    !syntheticOltKeptByEdge
                ) {
                    node.addClass('dimmed');
                }
            });

            cy.edges().forEach((edge) => {
                const d = edge.data();
                const edgeId = edge.id();
                const rawId = Number(d.raw_id || 0);

                const isActiveReal = rawId && activeEdgeIds.has(rawId);
                const isActiveSynthetic = activeSyntheticEdgeIds.has(edgeId);
                const isSelected = !!(selectedEdge && edgeId === selectedEdge.id());

                if (!isActiveReal && !isActiveSynthetic && !isSelected) {
                    edge.addClass('dimmed');
                }
            });
        }
        function getNodesTableModel() {
            const tableState = getNodesTableState();
            const rows = getAllNodeRows().map((row) => ({
                ...row,
                __search: [
                    row.odf_name,
                    row.box_name,
                    row.node_name,
                    row.node_code,
                    row.box_code,
                    row.location,
                    row.olt_name,
                    row.olt_port_path,
                    row.source_box_name,
                    row.parent_odf_name,
                    row.feed_mode,
                    getUplinkLabel(row)
                ].filter(Boolean).join(' ')
            }));

            const processed = table.process({
                rows,
                search: tableState.q || '',
                searchFields: ['__search'],
                pager: {
                    currentPage: tableState.page || 1,
                    rowsPerPage: tableState.perPage || 10
                }
            });

            return {
                q: tableState.q || '',
                rows: processed.rows,
                total: processed.totalRows,
                page: processed.currentPage,
                perPage: processed.rowsPerPage,
                totalPages: processed.totalPages
            };
        }

        function getUplinkLabel(row) {
            const type = upper(row.box_type || row.node_type);

            if (type === 'ODF') {
                const oltName = row.olt_name || getOltLabelById(row.olt_id);
                const portName = row.olt_port_path || row.olt_port_label || getPlannerPortLabelById(row.olt_port_id);

                if (oltName && portName) return `${oltName} • ${portName}`;
                if (oltName) return `OLT: ${oltName}`;

                const fallback = getIncomingPlannerLinkMeta('ODF', row.id);
                if (fallback) return fallback.label ? `${fallback.sourceName} • ${fallback.label}` : fallback.sourceName;

                return 'No OLT uplink assigned';
            }

            if (type === 'LCP') {
                const odfName = row.parent_odf_name || row.source_odf_name || '';
                const odfPort = row.parent_odf_port_number ? `Port ${row.parent_odf_port_number}` : '';

                if (odfName && odfPort) return `${odfName} • ${odfPort}`;
                if (odfName) return `ODF: ${odfName}`;

                const fallback = getIncomingPlannerLinkMeta('LCP', row.id);
                if (fallback) return fallback.label ? `${fallback.sourceName} • ${fallback.label}` : fallback.sourceName;

                return 'No parent ODF assigned';
            }

            if (type === 'NAP') {
                const sourceBox = row.source_box_name || row.parent_box_name || '';
                const sourcePort = row.source_port_number || row.parent_port_number || '';
                const feedMode = row.feed_mode || '';

                if (sourceBox && sourcePort) return `${sourceBox} • Port ${sourcePort} (${feedMode || 'FEED'})`;
                if (sourceBox) return `${sourceBox} (${feedMode || 'FEED'})`;

                const fallback = getIncomingPlannerLinkMeta('NAP', row.id);
                if (fallback) return fallback.label ? `${fallback.sourceName} • ${fallback.label}` : fallback.sourceName;

                return 'No parent feed assigned';
            }

            return '-';
        }

        const resolvePortClass = (port) => {
            const status = upper(port?.status);
            if (status === 'USED') return 'used';
            if (status === 'RESERVED') return 'reserved';
            if (status === 'MAINTENANCE') return 'maintenance';
            return 'free';
        };

        function countTopologyNodes() {
            return safeArray(getState().plannerObjects.nodes).length;
        }

        function buildPlannerNodeData(nodeRow) {
            const source = getPlannerNodeSourceRow(nodeRow) || {};
            const type = upper(nodeRow.node_type || 'NODE');

            return {
                id: `NODE_${nodeRow.id}`,
                raw_id: nodeRow.id,
                label: getPlannerNodeLabel(nodeRow),
                short_label: getPlannerNodeLabel(nodeRow),
                type,
                status: nodeRow.status || 'ACTIVE',
                location: nodeRow.location || '',
                reference_table: nodeRow.reference_table || '',
                reference_id: nodeRow.reference_id || null,

                olt_id: source.olt_id || null,
                olt_name: source.olt_name || getOltLabelById(source.olt_id) || '',
                olt_port_id: source.olt_port_id || null,
                olt_port_label: source.olt_port_path || source.port_path || getPlannerPortLabelById(source.olt_port_id) || '',

                parent_odf_id: source.parent_odf_id || null,
                parent_odf_name: source.parent_odf_name || '',
                parent_odf_port_id: source.parent_odf_port_id || null,
                parent_odf_port_number: source.parent_odf_port_number || '',

                source_box_id: source.source_box_id || null,
                source_box_name: source.source_box_name || '',
                source_port_id: source.source_port_id || null,
                source_port_number: source.source_port_number || '',
                input_port_number: source.input_port_number || (type === 'NAP' ? 1 : null),
                feed_mode: source.feed_mode || '',

                planner_x: nodeRow.planner_x != null ? Number(nodeRow.planner_x) : null,
                planner_y: nodeRow.planner_y != null ? Number(nodeRow.planner_y) : null
            };
        }

        function hasSavedPlannerPositions(elements = []) {
            const plannerNodes = safeArray(elements).filter((el) => {
                const d = el?.data || {};
                return !d.source && !d.target;
            });

            if (!plannerNodes.length) return false;

            return plannerNodes.every((el) => {
                const d = el?.data || {};
                return d.planner_x != null
                    && d.planner_y != null
                    && Number.isFinite(Number(d.planner_x))
                    && Number.isFinite(Number(d.planner_y));
            });
        }

        function hasAnySavedPlannerPosition(elements = []) {
            return safeArray(elements).some((el) => {
                const d = el?.data || {};

                return !d.source
                    && !d.target
                    && d.planner_x != null
                    && d.planner_y != null
                    && Number.isFinite(Number(d.planner_x))
                    && Number.isFinite(Number(d.planner_y));
            });
        }

        function applyPlannerSavedPositions(cy) {
            cy.nodes().forEach((node) => {
                const d = node.data();
                if (
                    d.planner_x != null &&
                    d.planner_y != null &&
                    Number.isFinite(Number(d.planner_x)) &&
                    Number.isFinite(Number(d.planner_y))
                ) {
                    node.position({
                        x: Number(d.planner_x),
                        y: Number(d.planner_y)
                    });
                }
            });
        }

        async function savePlannerNodePosition({ nodeId, x, y }) {
            return api.form(
                '/api/v1/nap-management/planner/node-position',
                buildFormData({
                    node_id: nodeId,
                    x,
                    y
                })
            );
        }
        function getObjectNameByKind(kind, id) {
            const s = getState();

            if (kind === 'OLT') return getOltLabelById(id) || `OLT ${id}`;

            if (kind === 'ODF') {
                const row = s.odfs.find(x => num(x.id) === num(id));
                return row?.odf_name || row?.box_name || `ODF-${id}`;
            }

            if (kind === 'LCP') {
                const row = s.lcps.find(x => num(x.id) === num(id));
                return row?.box_name || row?.lcp_name || `LCP-${id}`;
            }

            if (kind === 'NAP') {
                const row = s.naps.find(x => num(x.id) === num(id));
                return row?.box_name || row?.nap_name || `NAP-${id}`;
            }

            return `NODE-${id}`;
        }

        function getObjectPortLabel(kind, row, side = 'source') {
            if (!row) return '';

            if (kind === 'OLT') {
                return row.olt_port_path || row.olt_port_label || getPlannerPortLabelById(row.olt_port_id) || '';
            }

            if (kind === 'ODF') {
                if (side === 'target') {
                    if (row.target_port_label) return row.target_port_label;
                    if (row.input_port_number) return `Port ${row.input_port_number}`;
                    if (row.target_port_number) return `Port ${row.target_port_number}`;
                    if (row.odf_port_number) return `Port ${row.odf_port_number}`;
                    if (row.parent_odf_port_number) return `Port ${row.parent_odf_port_number}`;
                    return 'Port 1';
                }

                if (row.source_port_label) return row.source_port_label;
                if (row.source_port_number) return `Port ${row.source_port_number}`;
                if (row.parent_odf_port_number) return `Port ${row.parent_odf_port_number}`;
                if (row.odf_port_number) return `Port ${row.odf_port_number}`;
                return '';
            }

            if (kind === 'LCP' || kind === 'NAP') {
                if (side === 'target') {
                    if (row.target_port_label) return row.target_port_label;
                    if (row.input_port_number) return `Port ${row.input_port_number}`;
                    if (row.target_port_number) return `Port ${row.target_port_number}`;
                    return 'Port 1';
                }

                if (row.source_port_label) return row.source_port_label;
                if (row.source_port_number) return `Port ${row.source_port_number}`;
                if (row.parent_port_number) return `Port ${row.parent_port_number}`;
                return '';
            }

            return '';
        }

        function buildConnectionLabel(sourceKind, sourceRow, sourceId, targetKind, targetRow, targetId) {
            const sourceName = getObjectNameByKind(sourceKind, sourceId);
            const targetName = getObjectNameByKind(targetKind, targetId);

            let sourcePort = '';
            let targetPort = '';

            if (sourceRow && sourceRow.source_port_label) {
                sourcePort = sourceRow.source_port_label;
            } else {
                sourcePort = getObjectPortLabel(sourceKind, sourceRow, 'source');
            }

            if (targetRow && targetRow.target_port_label) {
                targetPort = targetRow.target_port_label;
            } else {
                targetPort = getObjectPortLabel(targetKind, targetRow, 'target');
            }

            const left = sourcePort ? `${sourceName} • ${sourcePort}` : sourceName;
            const right = targetPort ? `${targetName} • ${targetPort}` : targetName;

            return `${left} → ${right}`;
        }

        function buildLinksInventory() {
            const s = getState();
            const rows = [];
            const seen = new Set();

            const pushRow = (row) => {
                if (!row.inventory_key || seen.has(row.inventory_key)) return;
                seen.add(row.inventory_key);
                rows.push(row);
            };

            /*
            |--------------------------------------------------------------------------
            | 1) Derived OLT -> ODF feeder links
            |--------------------------------------------------------------------------
            */
            s.odfs.forEach((odf) => {
                const oltId = num(odf.olt_id || 0);
                const odfId = num(odf.id || 0);

                if (!oltId || !odfId) return;

                const oltPortLabel =
                    getPlannerPortLabelById(odf.olt_port_id) ||
                    odf.olt_port_path ||
                    odf.olt_port_label ||
                    '';

                const targetPortLabel = `Port ${getInputPortNumber(odf, 1)}`;

                pushRow({
                    inventory_key: `FEEDER:OLT:${oltId}:${odf.olt_port_id || ''}->ODF:${odfId}:${getInputPortNumber(odf, 1)}`,
                    link_type: 'FEEDER',
                    identification: buildConnectionLabel(
                        'OLT',
                        {
                            ...odf,
                            source_port_label: oltPortLabel
                        },
                        oltId,
                        'ODF',
                        {
                            ...odf,
                            target_port_label: targetPortLabel
                        },
                        odfId
                    ),
                    source_name: getObjectNameByKind('OLT', oltId),
                    target_name: getObjectNameByKind('ODF', odfId)
                });
            });

            /*
            |--------------------------------------------------------------------------
            | 2) Actual planner links (ODF->LCP, LCP->NAP, NAP->NAP, etc.)
            |--------------------------------------------------------------------------
            */
            s.plannerObjects.links.forEach((link) => {
                const sourceNode = s.plannerObjects.nodes.find(
                    (n) => num(n.id) === num(link.source_node_id)
                );
                const targetNode = s.plannerObjects.nodes.find(
                    (n) => num(n.id) === num(link.target_node_id)
                );

                if (!sourceNode || !targetNode) return;

                const sourceKind = upper(sourceNode.node_type || '');
                const targetKind = upper(targetNode.node_type || '');

                const sourceRefId = num(sourceNode.reference_id || sourceNode.id);
                const targetRefId = num(targetNode.reference_id || targetNode.id);

                const sourceSrc = getPlannerNodeSourceRow(sourceNode) || {};
                const targetSrc = getPlannerNodeSourceRow(targetNode) || {};

                pushRow({
                    inventory_key: `${upper(link.link_type || 'LINK')}:${sourceKind}:${sourceRefId}:${link.source_port_id || ''}->${targetKind}:${targetRefId}:${link.target_port_id || ''}`,
                    link_type: upper(link.link_type || 'DISTRIBUTION'),
                    identification: buildConnectionLabel(
                        sourceKind,
                        {
                            ...sourceSrc,
                            source_port_label: link.source_port_label || sourceSrc.source_port_label || ''
                        },
                        sourceRefId,
                        targetKind,
                        {
                            ...targetSrc,
                            target_port_label: link.target_port_label || targetSrc.target_port_label || ''
                        },
                        targetRefId
                    ),
                    source_name: getObjectNameByKind(sourceKind, sourceRefId),
                    target_name: getObjectNameByKind(targetKind, targetRefId)
                });
            });

            return rows;
        }

        function refreshDynamicRefs() {
            refs.plannerObjectModalEl = $('#plannerObjectModal');
        }
        function getLinksTableState() {
            return getState().linksTable || {
                q: '',
                page: 1,
                perPage: 10,
                sortKey: 'identification',
                sortDir: 'asc'
            };
        }

        function patchLinksTable(obj = {}) {
            patch({
                linksTable: {
                    ...getLinksTableState(),
                    ...obj
                }
            });
        }

        function getNodesTableState() {
            return getState().nodesTable || {
                q: '',
                page: 1,
                perPage: 10
            };
        }

        function patchNodesTable(obj = {}) {
            patch({
                nodesTable: {
                    ...getNodesTableState(),
                    ...obj
                }
            });
        }

        function getProcessedLinksTable() {
            const tableState = getLinksTableState();
            const rows = buildLinksInventory();

            const processed = table.process({
                rows,
                search: tableState.q || '',
                searchFields: ['link_type', 'identification', 'source_name', 'target_name'],
                sort: {
                    key: tableState.sortKey || 'identification',
                    dir: tableState.sortDir || 'asc'
                },
                pager: {
                    currentPage: tableState.page || 1,
                    rowsPerPage: tableState.perPage || 10
                }
            });

            return {
                q: tableState.q || '',
                rows: processed.rows,
                total: processed.totalRows,
                page: processed.currentPage,
                perPage: processed.rowsPerPage,
                totalPages: processed.totalPages,
                sortKey: tableState.sortKey || 'identification',
                sortDir: tableState.sortDir || 'asc'
            };
        }

        function buildLinkIdentification(data) {
            if (!data) return 'No link selected.';

            const source = data.source_name || data.source_node_id || '-';
            const target = data.target_name || data.target_node_id || '-';
            const type = upper(data.link_type || 'LINK');

            return `
                <div class="nx-prop-list">
                    <div><strong>Friendly Label:</strong> ${escape(data.label || '-')}</div>
                    <div><strong>Type:</strong> ${escape(type)}</div>
                    <div><strong>Path:</strong> ${escape(source)} → ${escape(target)}</div>
                </div>
            `;
        }

        function getAllowedRefKeysForOltScope() {
            const s = getState();
            const allowedNodeKeys = new Set();
            const allowedOdfIds = new Set();
            const allowedLcpIds = new Set();
            const allowedNapIds = new Set();

            const selectedOltId = String(s.planner.oltId || '');
            const selectedOltPortId = String(s.planner.oltPortId || '');

            s.odfs.forEach((odf) => {
                const odfOltId = String(odf.olt_id || '');
                const odfOltPortId = String(odf.olt_port_id || '');

                if (!selectedOltId || odfOltId !== selectedOltId) return;
                if (selectedOltPortId && odfOltPortId !== selectedOltPortId) return;

                const odfId = num(odf.id);
                if (!odfId) return;

                allowedOdfIds.add(odfId);
                allowedNodeKeys.add(`odf_nodes:${odfId}`);
            });

            let changed = true;

            while (changed) {
                changed = false;

                s.lcps.forEach((lcp) => {
                    const lcpId = num(lcp.id);
                    const parentOdfId = getLcpParentOdfId(lcp);

                    if (lcpId && parentOdfId && allowedOdfIds.has(parentOdfId) && !allowedLcpIds.has(lcpId)) {
                        allowedLcpIds.add(lcpId);
                        allowedNodeKeys.add(`network_boxes:${lcpId}`);
                        changed = true;
                    }
                });

                s.naps.forEach((nap) => {
                    const napId = num(nap.id);
                    let parentBoxId = null;

                    const napPlannerNode = findPlannerNodeRowBySource(
                        'network_boxes',
                        nap.id,
                        nap.box_name || '',
                        'NAP'
                    );

                    if (napPlannerNode) {
                        const incomingLink = s.plannerObjects.links.find(
                            (l) => num(l.target_node_id) === num(napPlannerNode.id)
                        );
                        if (incomingLink) parentBoxId = num(incomingLink.source_node_id);
                    }

                    const parentIsAllowedLcp = [...allowedLcpIds].some((lcpId) => {
                        const plannerNode = findPlannerNodeRowBySource('network_boxes', lcpId, '', 'LCP');
                        return plannerNode && num(plannerNode.id) === num(parentBoxId);
                    });

                    const parentIsAllowedNap = [...allowedNapIds].some((napIdCheck) => {
                        const plannerNode = findPlannerNodeRowBySource('network_boxes', napIdCheck, '', 'NAP');
                        return plannerNode && num(plannerNode.id) === num(parentBoxId);
                    });

                    if (napId && parentBoxId && (parentIsAllowedLcp || parentIsAllowedNap) && !allowedNapIds.has(napId)) {
                        allowedNapIds.add(napId);
                        allowedNodeKeys.add(`network_boxes:${napId}`);
                        changed = true;
                    }
                });
            }

            return {
                keys: allowedNodeKeys,
                odfIds: allowedOdfIds,
                lcpIds: allowedLcpIds,
                napIds: allowedNapIds
            };
        }

        function getFilteredPlannerElements() {
            const s = getState();
            let nodeRows = [...s.plannerObjects.nodes];
            const linkRows = [...s.plannerObjects.links];

            if (s.planner.scopeMode === 'OLT' && s.planner.oltId) {
                const allowed = getAllowedRefKeysForOltScope();

                nodeRows = nodeRows.filter((node) => {
                    const refTable = String(node.reference_table || '');
                    const refId = num(node.reference_id || node.id);
                    const key = `${refTable}:${refId}`;
                    const nodeType = upper(node.node_type || '');

                    if (allowed.keys && allowed.keys.has(key)) return true;

                    if (nodeType === 'OLT') {
                        const nodeOltId = String(
                            node.olt_id ||
                            node.reference_id ||
                            (refTable === 'olt_devices' ? node.id : '')
                        );
                        return nodeOltId && nodeOltId === String(s.planner.oltId);
                    }

                    if (nodeType === 'ODF') {
                        const source = getPlannerNodeSourceRow(node);
                        return source ? allowed.odfIds.has(num(source.id)) : false;
                    }

                    if (nodeType === 'LCP') {
                        const source = getPlannerNodeSourceRow(node);
                        return source ? allowed.lcpIds.has(num(source.id)) : false;
                    }

                    if (nodeType === 'NAP') {
                        const source = getPlannerNodeSourceRow(node);
                        return source ? allowed.napIds.has(num(source.id)) : false;
                    }

                    return false;
                });
            }

            const elements = [];
            const allowedNodeIds = new Set();

            nodeRows.forEach((row) => {
                const dataRow = buildPlannerNodeData(row);
                allowedNodeIds.add(dataRow.id);
                elements.push({ data: dataRow });
            });

            linkRows.forEach((link) => {
                const source = `NODE_${link.source_node_id}`;
                const target = `NODE_${link.target_node_id}`;
                if (!allowedNodeIds.has(source) || !allowedNodeIds.has(target)) return;

                const sourceNode = s.plannerObjects.nodes.find(n => num(n.id) === num(link.source_node_id));
                const targetNode = s.plannerObjects.nodes.find(n => num(n.id) === num(link.target_node_id));

                const sourceSrc = sourceNode ? (getPlannerNodeSourceRow(sourceNode) || {}) : {};
                const targetSrc = targetNode ? (getPlannerNodeSourceRow(targetNode) || {}) : {};

                const sourceKind = upper(sourceNode?.node_type || '');
                const targetKind = upper(targetNode?.node_type || '');

                const sourceRefId = num(sourceNode?.reference_id || sourceNode?.id);
                const targetRefId = num(targetNode?.reference_id || targetNode?.id);

                const sourceRowForLabel = {
                    ...sourceSrc,
                    source_port_label: link.source_port_label || '',
                    target_port_label: link.target_port_label || ''
                };

                const targetRowForLabel = {
                    ...targetSrc,
                    source_port_label: link.source_port_label || '',
                    target_port_label: link.target_port_label || ''
                };

                elements.push({
                    data: {
                        id: `LINK_${link.id}`,
                        raw_id: link.id,
                        source,
                        target,
                        link_type: upper(link.link_type || 'DISTRIBUTION'),
                        label: buildConnectionLabel(
                            sourceKind,
                            sourceRowForLabel,
                            sourceRefId,
                            targetKind,
                            targetRowForLabel,
                            targetRefId
                        ),
                        source_node_id: link.source_node_id,
                        target_node_id: link.target_node_id,
                        source_name: getObjectNameByKind(sourceKind, sourceRefId),
                        target_name: getObjectNameByKind(targetKind, targetRefId),
                        source_port_label: link.source_port_label || '',
                        target_port_label: link.target_port_label || ''
                    }
                });
            });

            const synthetic = buildSyntheticOltElements(nodeRows);
            synthetic.nodes.forEach((node) => {
                if (!allowedNodeIds.has(node.data.id)) {
                    allowedNodeIds.add(node.data.id);
                    elements.push(node);
                }
            });
            synthetic.edges.forEach((edge) => elements.push(edge));

            return elements;
        }

        component.define('nap.toolbar', () => ``);

        function renderToolbar() {
            html(refs.page.toolbarArea, renderComponent('nap.toolbar', { tab: getState().tab }));

            const toolbarCard = refs.page.toolbarArea?.closest('.nx-toolbar-card');
            if (toolbarCard) {
                toolbarCard.classList.add('d-none');
            }
        }

        component.define('nap.nodeCard', ({ row }) => {
            const meta = getNodeMeta(row.box_type);
            const name = row.odf_name || row.box_name || row.node_name || '-';
            const totalPorts = num(row.port_count || row.total_ports || row.splitter_ratio || row.splitter_ports || 0);
            const usedPorts = num(row.used_ports || 0);
            const freePorts = num(row.free_ports || 0);

            const statusUpper = upper(row.status || '');
            const maintenance = statusUpper === 'MAINTENANCE';

            const isSelfMaintenance = Number(row.is_self_maintenance || 0) === 1;
            const isParentMaintenance = Number(row.is_parent_maintenance || 0) === 1;
            const maintenanceOrigin = upper(row.maintenance_origin || row.maintenance_source || '');
            const parentName = row.maintenance_parent_name || row.source_box_name || row.parent_odf_name || '';

            const inheritedMaintenance =
                maintenance &&
                !isSelfMaintenance &&
                (isParentMaintenance || maintenanceOrigin === 'PARENT');

            /*
            |--------------------------------------------------------------------------
            | TOPOLOGY-BASED CHILD DETECTION
            |--------------------------------------------------------------------------
            */
            const typeUpper = upper(row.box_type || row.node_type || '');
            const isChildBox =
                (typeUpper === 'LCP' && num(row.parent_odf_id || row.source_odf_id || row.odf_id || 0) > 0) ||
                (typeUpper === 'NAP' && num(row.source_box_id || row.parent_box_id || row.parent_lcp_id || row.parent_nap_id || 0) > 0);

            const disableEdit = inheritedMaintenance;
            const disableDelete = maintenance;

            /*
            |--------------------------------------------------------------------------
            | FIX:
            | Child boxes may still enter SELF maintenance.
            | Only inherited maintenance should be locked.
            |--------------------------------------------------------------------------
            */
            const disableMaintenance = inheritedMaintenance;

            const editTitle = disableEdit
                ? 'Editing disabled during inherited maintenance'
                : 'Edit';

            const deleteTitle = disableDelete
                ? 'Delete disabled during maintenance'
                : 'Delete';

            const maintenanceTitle = disableMaintenance
                ? 'Inherited maintenance cannot be changed directly'
                : (maintenance ? 'End maintenance' : 'Maintenance');

            const lockHint = inheritedMaintenance
                ? `Inherited from parent maintenance${parentName ? ` (${parentName})` : ''}`
                : (
                    maintenance
                        ? 'Box is in maintenance mode'
                        : (
                            isChildBox
                                ? `Child box${parentName ? ` under ${parentName}` : ''}`
                                : ''
                        )
                );


            return `
        <div class="infra-box-card ${maintenance ? 'maintenance' : ''}" data-action="open-box-viewer" data-type="${meta.type.toLowerCase()}" data-id="${row.id}">
            ${maintenance ? `<div class="infra-maintenance-ribbon">${inheritedMaintenance ? 'INHERITED MAINTENANCE' : 'MAINTENANCE'}</div>` : ''}

            <div class="infra-box-icon-wrap ${meta.wrap}">
                <i class="bi ${meta.icon}"></i>
            </div>

            <div class="infra-box-body">
                <div class="infra-box-title-row">
                    <div class="infra-box-title">${escape(name)}</div>
                    <div class="infra-box-chip ${meta.chip}">${meta.type}</div>
                </div>

                <div class="infra-box-meta">
                    <div><strong>Code:</strong> ${escape(row.node_code || row.box_code || '-')}</div>
                    <div><strong>Uplink:</strong> ${escape(getUplinkLabel(row))}</div>
                    <div><strong>Location:</strong> ${escape(row.location || '-')}</div>
                    <div><strong>Lat/Lon:</strong> ${escape(formatLatLon(row.latitude, row.longitude))}</div>
                    ${
                inheritedMaintenance
                    ? `<div><strong>Lock:</strong> <span class="text-danger">Inherited from parent maintenance</span></div>`
                    : ''
            }
                </div>

                <div class="infra-box-stats">
                    <span class="infra-stat used">${usedPorts} used</span>
                    <span class="infra-stat free">${freePorts} free</span>
                    <span class="infra-stat neutral">${totalPorts} total</span>
                </div>

                <div class="infra-box-actions mt-3">
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-secondary infra-action-btn"
                        data-action="view-box"
                        data-type="${meta.type.toLowerCase()}"
                        data-id="${row.id}"
                        title="View"
                    >
                        <i class="bi bi-eye"></i>
                    </button>

                    <button
                        type="button"
                        class="btn btn-sm btn-outline-primary infra-action-btn"
                        data-action="edit-box"
                        data-type="${meta.type.toLowerCase()}"
                        data-id="${row.id}"
                        title="${editTitle}"
                        ${disableEdit ? 'disabled aria-disabled="true"' : ''}
                    >
                        <i class="bi bi-pencil"></i>
                    </button>

                    <button
                        type="button"
                        class="btn btn-sm btn-outline-warning infra-action-btn infra-action-btn-wide"
                        data-action="maint-box"
                        data-type="${meta.type.toLowerCase()}"
                        data-id="${row.id}"
                        title="${maintenanceTitle}"
                        ${disableMaintenance ? 'disabled aria-disabled="true"' : ''}
                    >
                        <i class="bi bi-tools"></i>
                    </button>

                    <button
                        type="button"
                        class="btn btn-sm btn-outline-danger infra-action-btn"
                        data-action="delete-box"
                        data-type="${meta.type.toLowerCase()}"
                        data-id="${row.id}"
                        title="${deleteTitle}"
                        ${disableDelete ? 'disabled aria-disabled="true"' : ''}
                    >
                        <i class="bi bi-trash"></i>
                    </button>
                </div>

                ${
                lockHint
                    ? `<div class="small text-danger mt-2"><i class="bi bi-lock-fill me-1"></i>${escape(lockHint)}</div>`
                    : ''
            }
            </div>
        </div>
    `;
        });

        component.define('nap.nodeTableRow', ({ row }) => {
            const meta = getNodeMeta(row.box_type);
            const type = meta.type.toLowerCase();
            const name = row.odf_name || row.box_name || row.node_name || '-';
            const codeValue = row.node_code || row.box_code || '-';
            const total = num(row.port_count || row.total_ports || row.splitter_ratio || row.splitter_ports || 0);
            const used = num(row.used_ports || 0);
            const free = num(row.free_ports || 0);
            const maintenance = upper(row.status || '') === 'MAINTENANCE';
            const inherited = maintenance && Number(row.is_self_maintenance || 0) !== 1 && (Number(row.is_parent_maintenance || 0) === 1 || upper(row.maintenance_origin || row.maintenance_source || '') === 'PARENT');
            const status = maintenance ? (inherited ? 'Inherited maintenance' : 'Maintenance') : (row.status || 'ACTIVE');
            const statusClass = maintenance ? 'warning' : (upper(row.status || 'ACTIVE') === 'ACTIVE' ? 'success' : 'secondary');

            return `<tr data-action="open-box-viewer" data-type="${type}" data-id="${escape(row.id)}">
                <td><div class="d-flex align-items-center gap-2"><span class="nap-node-table-icon ${meta.wrap}"><i class="bi ${meta.icon}"></i></span><div><div class="nx-cell-title">${escape(name)}</div><div class="nx-cell-sub">${escape(meta.type)} · ${escape(codeValue)}</div></div></div></td>
                <td>${escape(getUplinkLabel(row))}</td>
                <td><div class="nx-cell-title">${escape(row.location || '-')}</div><div class="nx-cell-sub">${escape(formatLatLon(row.latitude, row.longitude))}</div></td>
                <td><div class="nx-cell-title">${used} used / ${free} free</div><div class="nx-cell-sub">${total} total ports</div></td>
                <td><span class="nx-soft-badge nx-soft-badge-${statusClass}">${escape(status)}</span></td>
                <td class="text-end text-nowrap"><button type="button" class="btn btn-sm btn-light border" data-action="view-box" data-type="${type}" data-id="${escape(row.id)}" title="View"><i class="bi bi-eye"></i></button> <button type="button" class="btn btn-sm btn-light border" data-action="edit-box" data-type="${type}" data-id="${escape(row.id)}" title="Edit" ${inherited ? 'disabled aria-disabled="true"' : ''}><i class="bi bi-pencil"></i></button> <button type="button" class="btn btn-sm btn-light border text-warning" data-action="maint-box" data-type="${type}" data-id="${escape(row.id)}" title="${maintenance ? 'End maintenance' : 'Maintenance'}" ${inherited ? 'disabled aria-disabled="true"' : ''}><i class="bi bi-tools"></i></button> <button type="button" class="btn btn-sm btn-light border text-danger" data-action="delete-box" data-type="${type}" data-id="${escape(row.id)}" title="Delete" ${maintenance ? 'disabled aria-disabled="true"' : ''}><i class="bi bi-trash"></i></button></td>
            </tr>`;
        });

        component.define('nap.nodesTab', ({ table }) => `
    <div class="d-grid gap-3 tab-shell nap-nodes-tab-shell">
        <div class="card border-0 nx-surface-card nap-standard-filter-panel">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                    <div>
                        <h5 class="mb-1">Infrastructure Nodes</h5>
                        <div class="text-muted small">Manage ODF, LCP, and NAP physical nodes.</div>
                    </div>
                </div>

                <div class="mt-3">
                    <input
                        type="text"
                        class="form-control form-control-sm"
                        id="nodesSearchInput"
                        placeholder="Search..."
                        value="${escape(table.q || '')}"
                    >
                </div>
            </div>
        </div>

        <div class="card border-0 nx-surface-card nap-standard-results-panel">
            <div class="card-body p-0">
                <div class="table-responsive nx-table-wrap"><table class="table align-middle mb-0 nap-nodes-table"><thead class="nx-sticky-head"><tr><th>Node</th><th>Uplink</th><th>Location / Coordinates</th><th>Port Usage</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody id="nodesCardGrid"></tbody></table></div>

                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 p-3 border-top">
                    <div class="text-muted small" id="nodesPageMeta"></div>

                    <div class="d-flex align-items-center gap-2">
                        <label for="nodesPerPageSelect" class="text-muted small mb-0">Rows per page</label>

                        <select class="form-select form-select-sm" id="nodesPerPageSelect" style="width: 88px;">
                            <option value="10" ${num(table.perPage) === 10 ? 'selected' : ''}>10</option>
                            <option value="20" ${num(table.perPage) === 20 ? 'selected' : ''}>20</option>
                            <option value="50" ${num(table.perPage) === 50 ? 'selected' : ''}>50</option>
                            <option value="100" ${num(table.perPage) === 100 ? 'selected' : ''}>100</option>
                        </select>

                        <button type="button" class="btn btn-sm btn-outline-secondary" id="nodesPrevBtn">
                            Prev
                        </button>

                        <button type="button" class="btn btn-sm btn-outline-secondary" id="nodesNextBtn">
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
`);

        component.define('nap.linksTab', ({ table }) => `
    <div class="d-grid gap-3 tab-shell nap-links-tab-shell">
        <div class="card border-0 nx-surface-card nap-standard-filter-panel">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                    <div>
                        <h5 class="mb-1">Network Links</h5>
                        <div class="text-muted small">Friendly, physical-aware link identification using topology links.</div>
                    </div>
                </div>

                <div class="mt-3">
                    <input
                        type="text"
                        class="form-control form-control-sm"
                        id="linksSearchInput"
                        placeholder="Search..."
                        value="${escape(table.q || '')}"
                    >
                </div>
            </div>
        </div>

        <div class="card border-0 nx-surface-card nap-standard-results-panel">
            <div class="card-body">
                <div class="table-responsive nx-table-wrap">
                    <table class="table align-middle mb-0">
                        <thead class="nx-sticky-head">
                            <tr>
                                <th>
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-reset" data-links-sort="link_type">
                                        TYPE <span data-links-sort-indicator="link_type"></span>
                                    </button>
                                </th>
                                <th>
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-reset" data-links-sort="identification">
                                        IDENTIFICATION <span data-links-sort-indicator="identification"></span>
                                    </button>
                                </th>
                                <th>
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-reset" data-links-sort="source_name">
                                        SOURCE <span data-links-sort-indicator="source_name"></span>
                                    </button>
                                </th>
                                <th>
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-reset" data-links-sort="target_name">
                                        TARGET <span data-links-sort-indicator="target_name"></span>
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="linksTableBody"></tbody>
                    </table>
                </div>

                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mt-3">
                    <div class="text-muted small" id="linksPageMeta"></div>

                    <div class="d-flex align-items-center gap-2">
                        <label for="linksPerPageSelect" class="text-muted small mb-0">Rows per page</label>

                        <select class="form-select form-select-sm" id="linksPerPageSelect" style="width: 88px;">
                            <option value="10" ${num(table.perPage) === 10 ? 'selected' : ''}>10</option>
                            <option value="20" ${num(table.perPage) === 20 ? 'selected' : ''}>20</option>
                            <option value="50" ${num(table.perPage) === 50 ? 'selected' : ''}>50</option>
                            <option value="100" ${num(table.perPage) === 100 ? 'selected' : ''}>100</option>
                        </select>

                        <button type="button" class="btn btn-sm btn-outline-secondary" id="linksPrevBtn">
                            Prev
                        </button>

                        <button type="button" class="btn btn-sm btn-outline-secondary" id="linksNextBtn">
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
`);
        function updateSummary() {
            const s = getState();

            const odfUsed = sumBy(s.odfPortGrid, 'used_ports');
            const odfTotal = safeArray(s.odfPortGrid).reduce((sum, row) => {
                return sum + num(row.port_count || row.total_ports || row.splitter_ratio || row.splitter_ports || 0, 0);
            }, 0);

            const lcpUsed = sumBy(s.lcpPortGrid, 'used_ports');
            const lcpTotal = safeArray(s.lcpPortGrid).reduce((sum, row) => {
                return sum + num(row.total_ports || row.splitter_ratio || row.splitter_ports || 0, 0);
            }, 0);

            const napUsed = sumBy(s.napPortGrid, 'used_ports');
            const napTotal = safeArray(s.napPortGrid).reduce((sum, row) => {
                return sum + num(row.total_ports || row.splitter_ratio || row.splitter_ports || 0, 0);
            }, 0);

            text(refs.summary.odfs, s.odfs.length);
            text(refs.summary.odfPorts, `${odfUsed} / ${odfTotal}`);

            text(refs.summary.lcps, s.lcps.length);
            text(refs.summary.lcpPorts, `${lcpUsed} / ${lcpTotal}`);

            text(refs.summary.naps, s.naps.length);
            text(refs.summary.napPorts, `${napUsed} / ${napTotal}`);
        }
        function toggleSummarySection() {
            refs.summary.section?.classList.toggle('d-none', getState().tab === 'planner');
        }
        function syncActiveTabUi() {
            const currentTab = getState().tab || 'planner';
            $$('[data-tab-link]').forEach((link) => {
                link.classList.toggle('active', link.dataset.tabLink === currentTab);
            });
        }
        function setActiveTab(tab) {
            patch({ tab });
            writeUiState({ tab });

            if (tab === 'links') {
                patchLinksTable({ page: 1 });
            }

            if (tab === 'nodes') {
                patchNodesTable({ page: 1 });
            }

            syncActiveTabUi();
            renderPage();
        }

        async function deleteSelectedPlannerObject() {
            const node = getState().planner.selectedNode;
            if (!node) {
                ui.toast('warning', 'Select a node first.');
                return;
            }

            const current = node.data();
            const type = upper(current.type || '');
            const referenceId = current.reference_id || current.raw_id;

            if (!referenceId) {
                ui.toast('warning', 'This planner node is not mapped to a deletable object.');
                return;
            }

            if (type === 'OLT') {
                ui.toast('warning', 'OLT is a derived planner object and cannot be deleted here.');
                return;
            }

            if (type === 'ODF') return confirmDelete('odf', referenceId);
            if (type === 'LCP') return confirmDelete('lcp', referenceId);
            if (type === 'NAP') return confirmDelete('nap', referenceId);

            ui.toast('warning', 'Unsupported planner object.');
        }

        function setPlannerReturnTab(tab = 'planner') {
            patch({ plannerCreateReturnTab: tab || 'planner' });
        }

        function consumePlannerReturnTab() {
            const next = getState().plannerCreateReturnTab || null;
            patch({ plannerCreateReturnTab: null });
            return next;
        }
        function renderNodesTab(forceShell = true) {
            app.classList.remove('nap-view-planner', 'nap-view-links');
            app.classList.add('nap-view-nodes');
            text(refs.page.subtitle, 'Manage all FTTH infrastructure nodes including ODF, LCP, and NAP.');
            if (forceShell || !$('#nodesCardGrid')) {
                html(refs.page.contentArea, renderComponent('nap.nodesTab', { table: getNodesTableModel() }));
            }

            renderNodesTableBodyOnly();
        }
        function renderLinksTab(forceShell = true) {
            app.classList.remove('nap-view-planner', 'nap-view-nodes');
            app.classList.add('nap-view-links');
            text(refs.page.subtitle, 'Review physical-aware topology links with readable identification.');
            if (forceShell || !$('#linksTableBody')) {
                html(refs.page.contentArea, renderComponent('nap.linksTab', {
                    table: getProcessedLinksTable()
                }));
            }
            renderLinksTableBodyOnly();
        }

        function renderLinksTableBodyOnly() {
            const table = getProcessedLinksTable();
            const refsUi = getLinksUiRefs();

            if (refsUi.tbody) {
                refsUi.tbody.innerHTML = table.rows.length
                    ? table.rows.map((link) => `
                <tr>
                    <td>${escape(upper(link.link_type || '-'))}</td>
                    <td>${escape(link.identification || '-')}</td>
                    <td>${escape(link.source_name || '-')}</td>
                    <td>${escape(link.target_name || '-')}</td>
                </tr>
            `).join('')
                    : `
                <tr>
                    <td colspan="4" class="text-center text-muted py-4">No network links found.</td>
                </tr>
            `;
            }

            if (refsUi.pageMeta) {
                refsUi.pageMeta.textContent = `Page ${table.page} of ${table.totalPages} (${table.total} ${table.total === 1 ? 'record' : 'records'})`;
            }

            if (refsUi.prevBtn) {
                refsUi.prevBtn.disabled = table.page <= 1;
                refsUi.prevBtn.dataset.linksPage = String(Math.max(1, table.page - 1));
            }

            if (refsUi.nextBtn) {
                refsUi.nextBtn.disabled = table.page >= table.totalPages;
                refsUi.nextBtn.dataset.linksPage = String(Math.min(table.totalPages, table.page + 1));
            }

            if (refsUi.perPageSelect && String(refsUi.perPageSelect.value) !== String(table.perPage)) {
                refsUi.perPageSelect.value = String(table.perPage);
            }

            $$('[data-links-sort-indicator]').forEach((el) => {
                const key = el.dataset.linksSortIndicator || '';
                const isActive = table.sortKey === key;
                el.classList.toggle('sort-indicator--unsorted', !isActive);
                el.classList.toggle('sort-indicator--ascending', isActive && table.sortDir === 'asc');
                el.classList.toggle('sort-indicator--descending', isActive && table.sortDir === 'desc');
                el.textContent = isActive ? (table.sortDir === 'asc' ? '↑' : '↓') : '↕';
            });
        }
        function updatePlannerInspectorVisibility() {
            const inspector = $('#plannerInspector');
            const s = getState();
            if (!inspector) return;
            inspector.classList.toggle('d-none', !s.planner.selectedNode && !s.planner.selectedEdge);
        }
        function renderNodesTableBodyOnly() {
            const table = getNodesTableModel();
            const refsUi = getNodesUiRefs();

            if (refsUi.grid) {
                refsUi.grid.innerHTML = table.rows.length
                    ? table.rows.map((row) => renderComponent('nap.nodeTableRow', { row })).join('')
                    : '<tr><td colspan="6" class="text-center text-muted py-4">No infrastructure nodes found.</td></tr>';
            }

            if (refsUi.pageMeta) {
                refsUi.pageMeta.textContent = `Page ${table.page} of ${table.totalPages} (${table.total} ${table.total === 1 ? 'record' : 'records'})`;
            }

            if (refsUi.prevBtn) {
                refsUi.prevBtn.disabled = table.page <= 1;
                refsUi.prevBtn.dataset.nodesPage = String(Math.max(1, table.page - 1));
            }

            if (refsUi.nextBtn) {
                refsUi.nextBtn.disabled = table.page >= table.totalPages;
                refsUi.nextBtn.dataset.nodesPage = String(Math.min(table.totalPages, table.page + 1));
            }

            if (refsUi.perPageSelect && String(refsUi.perPageSelect.value) !== String(table.perPage)) {
                refsUi.perPageSelect.value = String(table.perPage);
            }
        }
        function renderPlannerSelectionPanels() {
            const s = getState();

            const selectionInfo = $('#plannerSelectionInfo');
            const nodeProps = $('#plannerNodeProperties');
            const linkId = $('#plannerLinkIdentification');
            const linkProps = $('#plannerLinkProperties');

            const nodeSection = nodeProps?.closest('.nx-inspector-section');
            const linkIdSection = linkId?.closest('.nx-inspector-section');
            const linkPropsSection = linkProps?.closest('.nx-inspector-section');

            const selectedNode = s.planner.selectedNode;
            const selectedEdge = s.planner.selectedEdge;

            if (selectionInfo) {
                if (selectedNode) {
                    selectionInfo.innerHTML = `<strong>${escape(selectedNode.data().label || '-')}</strong> selected.`;
                } else if (selectedEdge) {
                    selectionInfo.innerHTML = `<strong>${escape(selectedEdge.data().label || 'LINK')}</strong> selected.`;
                } else {
                    selectionInfo.innerHTML = 'Select a node or link in the planner to view details.';
                }
            }

            /*
            |--------------------------------------------------------------------------
            | NODE SELECTED
            |--------------------------------------------------------------------------
            */
            if (selectedNode) {
                const d = selectedNode.data();
                const type = upper(d.type || '');

                let infoRows = `
            <div><strong>Name:</strong> ${escape(d.label || '-')}</div>
            <div><strong>Type:</strong> ${escape(type || '-')}</div>
            <div><strong>Status:</strong> ${escape(d.status || '-')}</div>
            <div><strong>Location:</strong> ${escape(d.location || '-')}</div>
        `;

                if (type === 'ODF') {
                    const uplink = [
                        d.olt_name,
                        d.olt_port_label
                    ].filter(Boolean).join(' • ') || '-';

                    infoRows += `<div><strong>Uplink:</strong> ${escape(uplink)}</div>`;
                    const trace = s.planner.trace || {};
                    const { nodeMap } = getPlannerGraphData();

                    const upstreamNames = safeArray(trace.upstreamNodeIds)
                        .map((id) => nodeMap.get(Number(id))?.node_name || nodeMap.get(Number(id))?.box_name || `Node ${id}`);

                    const downstreamNames = safeArray(trace.downstreamNodeIds)
                        .map((id) => nodeMap.get(Number(id))?.node_name || nodeMap.get(Number(id))?.box_name || `Node ${id}`);

                    infoRows += `
    <hr class="my-2">
    <div><strong>Upstream Hops:</strong> ${safeArray(trace.upstreamNodeIds).length}</div>
    <div><strong>Downstream Nodes:</strong> ${safeArray(trace.downstreamNodeIds).length}</div>
    <div><strong>Upstream Path:</strong> ${escape(upstreamNames.length ? upstreamNames.join(' ← ') : 'Root / none')}</div>
    <div><strong>Downstream Path:</strong> ${escape(downstreamNames.length ? downstreamNames.join(', ') : 'No downstream nodes')}</div>
`;
                } else if (type === 'LCP') {
                    const uplink = [
                        d.parent_odf_name,
                        d.parent_odf_port_number ? `Port ${d.parent_odf_port_number}` : ''
                    ].filter(Boolean).join(' • ') || '-';

                    infoRows += `<div><strong>Uplink:</strong> ${escape(uplink)}</div>`;
                    const trace = s.planner.trace || {};
                    const { nodeMap } = getPlannerGraphData();

                    const upstreamNames = safeArray(trace.upstreamNodeIds)
                        .map((id) => nodeMap.get(Number(id))?.node_name || nodeMap.get(Number(id))?.box_name || `Node ${id}`);

                    const downstreamNames = safeArray(trace.downstreamNodeIds)
                        .map((id) => nodeMap.get(Number(id))?.node_name || nodeMap.get(Number(id))?.box_name || `Node ${id}`);

                    infoRows += `
    <hr class="my-2">
    <div><strong>Upstream Hops:</strong> ${safeArray(trace.upstreamNodeIds).length}</div>
    <div><strong>Downstream Nodes:</strong> ${safeArray(trace.downstreamNodeIds).length}</div>
    <div><strong>Upstream Path:</strong> ${escape(upstreamNames.length ? upstreamNames.join(' ← ') : 'Root / none')}</div>
    <div><strong>Downstream Path:</strong> ${escape(downstreamNames.length ? downstreamNames.join(', ') : 'No downstream nodes')}</div>
`;
                } else if (type === 'NAP') {
                    const uplink = [
                        d.source_box_name,
                        d.source_port_number ? `Port ${d.source_port_number}` : '',
                        d.feed_mode ? `(${d.feed_mode})` : ''
                    ].filter(Boolean).join(' ').replace('Port ', '• Port ').replace('• (', ' (') || '-';

                    infoRows += `<div><strong>Uplink:</strong> ${escape(uplink)}</div>`;
                    const trace = s.planner.trace || {};
                    const { nodeMap } = getPlannerGraphData();

                    const upstreamNames = safeArray(trace.upstreamNodeIds)
                        .map((id) => nodeMap.get(Number(id))?.node_name || nodeMap.get(Number(id))?.box_name || `Node ${id}`);

                    const downstreamNames = safeArray(trace.downstreamNodeIds)
                        .map((id) => nodeMap.get(Number(id))?.node_name || nodeMap.get(Number(id))?.box_name || `Node ${id}`);

                    infoRows += `
    <hr class="my-2">
    <div><strong>Upstream Hops:</strong> ${safeArray(trace.upstreamNodeIds).length}</div>
    <div><strong>Downstream Nodes:</strong> ${safeArray(trace.downstreamNodeIds).length}</div>
    <div><strong>Upstream Path:</strong> ${escape(upstreamNames.length ? upstreamNames.join(' ← ') : 'Root / none')}</div>
    <div><strong>Downstream Path:</strong> ${escape(downstreamNames.length ? downstreamNames.join(', ') : 'No downstream nodes')}</div>
`;
                }

                if (nodeProps) {
                    nodeProps.innerHTML = `
                <div class="nx-prop-list">
                    ${infoRows}
                </div>
            `;
                }

                if (linkId) {
                    linkId.innerHTML = 'No link selected.';
                }

                if (linkProps) {
                    linkProps.innerHTML = 'No link selected.';
                }

                nodeSection?.classList.remove('d-none');
                linkIdSection?.classList.add('d-none');
                linkPropsSection?.classList.add('d-none');
            }

            /*
            |--------------------------------------------------------------------------
            | LINK SELECTED
            |--------------------------------------------------------------------------
            */
            else if (selectedEdge) {
                const d = selectedEdge.data();

                if (nodeProps) {
                    nodeProps.innerHTML = 'No node selected.';
                }

                if (linkId) {
                    linkId.innerHTML = buildLinkIdentification(d);
                }

                if (linkProps) {
                    linkProps.innerHTML = `
                <div class="nx-prop-list">
                    <div><strong>Type:</strong> ${escape(d.link_type || '-')}</div>
                    <div><strong>Source:</strong> ${escape(d.source_name || d.source_node_id || '-')}</div>
                    <div><strong>Target:</strong> ${escape(d.target_name || d.target_node_id || '-')}</div>
                    <div><strong>Label:</strong> ${escape(d.label || '-')}</div>
                </div>
            `;
                }

                nodeSection?.classList.add('d-none');
                linkIdSection?.classList.remove('d-none');
                linkPropsSection?.classList.remove('d-none');
            }

            /*
            |--------------------------------------------------------------------------
            | NOTHING SELECTED
            |--------------------------------------------------------------------------
            */
            else {
                if (nodeProps) {
                    nodeProps.innerHTML = 'No node selected.';
                }

                if (linkId) {
                    linkId.innerHTML = `No link selected.<br><span class="text-muted">Select a link in the planner to view identification details.</span>`;
                }

                if (linkProps) {
                    linkProps.innerHTML = 'No link selected.';
                }

                nodeSection?.classList.remove('d-none');
                linkIdSection?.classList.remove('d-none');
                linkPropsSection?.classList.remove('d-none');
            }

            updatePlannerInspectorVisibility();
        }

        function applyPlannerScopeButtons() {
            const { planner } = getState();
            $$('[data-planner-scope]').forEach((btn) => {
                btn.classList.toggle('active', btn.dataset.plannerScope === planner.scopeMode);
            });
        }

        async function openPlannerConnectModal(sourceNode, targetNode) {
            const sourceData = sourceNode?.data() || {};
            const targetData = targetNode?.data() || {};
            const sourceType = upper(sourceData.type || '');
            const targetType = upper(targetData.type || '');

            if (sourceType === 'OLT' || targetType === 'OLT') {
                throw new Error('OLT links are auto-derived from ODF uplink settings. Edit the ODF instead.');
            }

            const result = await ui.swal({
                title: 'Create Planner Link',
                html: `
                    <div class="nx-swal-grid one-col">
                        <div class="nx-swal-banner">
                            <i class="bi bi-diagram-2"></i>
                            <div>Connect <strong>${escape(sourceData.label || '-')}</strong> to <strong>${escape(targetData.label || '-')}</strong>.</div>
                        </div>

                        <div class="nx-swal-field">
                            <label for="swalLinkType">Link Type</label>
                            <select id="swalLinkType" class="swal2-select">
                                <option value="FEEDER">FEEDER</option>
                                <option value="DISTRIBUTION" selected>DISTRIBUTION</option>
                                <option value="DROP">DROP</option>
                            </select>
                        </div>
                    </div>
                `,
                customClass: {
                    popup: 'nx-swal-popup',
                    title: 'nx-swal-title',
                    htmlContainer: 'nx-swal-html',
                    confirmButton: 'nx-swal-confirm',
                    cancelButton: 'nx-swal-cancel'
                },
                showCancelButton: true,
                confirmButtonText: 'Save Link',
                preConfirm: () => ({
                    linkType: $('#swalLinkType')?.value || 'DISTRIBUTION'
                })
            });

            if (!result.isConfirmed || !result.value) return;

            await api.form('/api/v1/nap-management/planner/object-connect', buildFormData({
                source_node_id: sourceNode.data('raw_id'),
                target_node_id: targetNode.data('raw_id'),
                link_type: result.value.linkType
            }));

            ui.toast('success', 'Planner link saved.');
            await loadPlannerObjects();
            renderPlannerLogicalView();
        }

        async function deleteSelectedPlannerLink() {
            const edge = getState().planner.selectedEdge;
            if (!edge) {
                ui.toast('warning', 'Select a link first.');
                return;
            }

            const edgeId = String(edge.id() || '');
            const d = edge.data() || {};

            if (!edgeId.startsWith('LINK_')) {
                ui.toast('warning', 'This is a derived/auto-generated link and cannot be deleted here.');
                return;
            }

            const result = await ui.swal({
                title: 'Delete Planner Link?',
                text: 'This will remove the selected planner connection.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Delete'
            });
            if (!result.isConfirmed) return;

            await api.form('/api/v1/nap-management/planner/object-delete-link', buildFormData({
                target_node_id: d.target_node_id
            }));

            patchPlanner({
                selectedEdge: null,
                selectedNode: null,
                trace: {
                    upstreamNodeIds: [],
                    upstreamEdgeIds: [],
                    downstreamNodeIds: [],
                    downstreamEdgeIds: []
                }
            });

            ui.toast('success', 'Planner link deleted.');
            await loadPlannerObjects();
            renderPlannerLogicalView();
        }

        async function resetPlannerLayout() {
            const result = await ui.swal({
                title: 'Reset Planner Layout?',
                text: 'This will clear all saved node positions and rebuild the layout automatically.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Reset Layout'
            });

            if (!result.isConfirmed) return;

            await api.form(
                '/api/v1/nap-management/planner/reset-layout',
                buildFormData({})
            );

            patchPlanner({
                selectedNode: null,
                selectedEdge: null,
                connectSourceNode: null
            });

            await loadPlannerObjects();
            renderPlannerLogicalView();

            ui.toast('success', 'Planner layout reset.');
        }

        function renderPlannerTab() {
            app.classList.remove('nap-view-nodes', 'nap-view-links');
            app.classList.add('nap-view-planner');
            text(refs.page.subtitle, 'Visualize ODF, LCP, and NAP topology in the planner.');

            const template = $('#napPlannerTemplate');
            if (!template) {
                html(refs.page.contentArea, '<div class="text-muted">Planner template not found.</div>');
                return;
            }

            refs.page.contentArea.innerHTML = '';
            refs.page.contentArea.appendChild(template.content.cloneNode(true));

            refreshDynamicRefs();
            wirePlannerControls();
            populatePlannerOltFilters();

            const view = getState().planner.view || 'logical';

            $$('[data-planner-view]').forEach((btn) => {
                btn.classList.toggle('active', btn.dataset.plannerView === view);
            });

            const logicalCanvas = $('#plannerLogicalCanvas');
            const mapCanvas = $('#plannerMapCanvas');

            if (logicalCanvas) {
                logicalCanvas.classList.toggle('d-none', view !== 'logical');
            }

            if (mapCanvas) {
                mapCanvas.classList.toggle('d-none', view !== 'map');
            }

            if (view === 'map') {
                renderPlannerMapView();
            } else {
                renderPlannerLogicalView();
            }

            updatePlannerInspectorVisibility();
        }

        function renderPlannerLogicalView() {
            const s = getState();
            const container = $('#plannerLogicalCanvas');
            const tooltipEl = $('#plannerHoverTooltip');
            const plannerGridUnit = 48;

            if (!container || typeof cytoscape !== 'function') {
                if (container) {
                    container.innerHTML = '<div class="nx-workspace-map-placeholder">Cytoscape is not available.</div>';
                }
                return;
            }

            s.planner.cy?.destroy();

            const elements = getFilteredPlannerElements();
            const usePreset = hasSavedPlannerPositions(elements);
            const hasAnySavedPosition = hasAnySavedPlannerPosition(elements);

            container.style.position = 'absolute';
            container.style.inset = '0';
            container.style.zIndex = '1';

            const cy = cytoscape({
                container,
                elements,
                style: [
                    {
                        selector: 'node',
                        style: {
                            label: 'data(label)',
                            'text-wrap': 'wrap',
                            'text-max-width': 160,
                            'text-valign': 'center',
                            'text-halign': 'center',
                            'font-size': 10,
                            'line-height': 1.15,
                            padding: '8px',
                            color: '#0f172a',
                            'background-color': '#cbd5e1',
                            'border-width': 2,
                            'border-color': '#94a3b8',
                            width: 72,
                            height: 72
                        }
                    },
                    {
                        selector: 'node[type = "ODF"]',
                        style: {
                            shape: 'round-rectangle',
                            'background-color': '#e0f2fe',
                            'border-color': '#0284c7',
                            width: 92,
                            height: 74
                        }
                    },
                    {
                        selector: 'node[type = "OLT"]',
                        style: {
                            shape: 'diamond',
                            'background-color': '#ede9fe',
                            'border-color': '#7c3aed',
                            width: 88,
                            height: 88,
                            'font-size': 11,
                            'font-weight': 700
                        }
                    },
                    {
                        selector: 'node[type = "LCP"]',
                        style: {
                            shape: 'round-rectangle',
                            'background-color': '#dbeafe',
                            'border-color': '#2563eb',
                            width: 92,
                            height: 74
                        }
                    },
                    {
                        selector: 'node[type = "NAP"]',
                        style: {
                            shape: 'ellipse',
                            'background-color': '#dcfce7',
                            'border-color': '#16a34a'
                        }
                    },
                    {
                        selector: 'edge',
                        style: {
                            'curve-style': 'bezier',
                            'target-arrow-shape': 'triangle',
                            'arrow-scale': 1,
                            width: 4,
                            'line-color': '#94a3b8',
                            'target-arrow-color': '#94a3b8',
                            label: 'data(label)',
                            'font-size': 10,
                            'text-background-color': '#ffffff',
                            'text-background-opacity': 1,
                            'text-background-padding': 3,
                            color: '#334155',
                            'overlay-padding': 12,
                            'overlay-opacity': 0,
                            'z-index': 10
                        }
                    },
                    {
                        selector: 'edge:hover',
                        style: {
                            width: 6,
                            'line-color': '#2563eb',
                            'target-arrow-color': '#2563eb',
                            cursor: 'pointer'
                        }
                    },
                    {
                        selector: 'node.trace-selected',
                        style: {
                            'border-width': 5,
                            'border-color': '#0f172a'
                        }
                    },
                    {
                        selector: 'edge.trace-selected',
                        style: {
                            width: 6,
                            'line-color': '#0f172a',
                            'target-arrow-color': '#0f172a'
                        }
                    },
                    {
                        selector: '.trace-upstream',
                        style: {
                            'line-color': '#2563eb',
                            'target-arrow-color': '#2563eb',
                            'border-color': '#2563eb',
                            'border-width': 4
                        }
                    },
                    {
                        selector: '.trace-downstream',
                        style: {
                            'line-color': '#16a34a',
                            'target-arrow-color': '#16a34a',
                            'border-color': '#16a34a',
                            'border-width': 4
                        }
                    },
                    {
                        selector: '.dimmed',
                        style: {
                            opacity: 0.2,
                            'text-opacity': 0.2
                        }
                    },
                    {
                        selector: '.link-source',
                        style: {
                            'border-width': 6,
                            'border-color': '#0f172a'
                        }
                    },
                    {
                        selector: '.link-valid-target',
                        style: {
                            'border-width': 5,
                            'border-color': '#16a34a',
                            'overlay-padding': 10,
                            'overlay-opacity': 0
                        }
                    },
                    {
                        selector: '.link-invalid-target',
                        style: {
                            opacity: 0.18
                        }
                    },
                    {
                        selector: 'edge[link_type = "FEEDER"]',
                        style: {
                            'line-color': '#2563eb',
                            'target-arrow-color': '#2563eb'
                        }
                    },
                    {
                        selector: 'edge[link_type = "DISTRIBUTION"]',
                        style: {
                            'line-color': '#16a34a',
                            'target-arrow-color': '#16a34a'
                        }
                    },
                    {
                        selector: 'edge[link_type = "DROP"]',
                        style: {
                            'line-color': '#f97316',
                            'target-arrow-color': '#f97316'
                        }
                    },
                    {
                        selector: '.highlighted',
                        style: {
                            'border-width': 4,
                            'border-color': '#f59e0b',
                            'line-color': '#f59e0b',
                            'target-arrow-color': '#f59e0b'
                        }
                    }
                ],
                layout: usePreset
                    ? { name: 'preset', fit: true, padding: 40 }
                    : {
                        name: 'breadthfirst',
                        directed: true,
                        padding: 40,
                        spacingFactor: 1.15
                    }
            });

            if (hasAnySavedPosition) {
                applyPlannerSavedPositions(cy);
                cy.fit(cy.elements(), 40);
            }

            const syncPlannerGridViewport = () => {
                const zoom = cy.zoom();
                const pan = cy.pan();
                const renderedGridSize = plannerGridUnit * zoom;

                container.style.setProperty('--planner-grid-size', `${renderedGridSize}px`);
                container.style.setProperty('--planner-grid-offset-x', `${pan.x}px`);
                container.style.setProperty('--planner-grid-offset-y', `${pan.y}px`);
            };

            cy.on('pan zoom viewport resize', syncPlannerGridViewport);
            syncPlannerGridViewport();

            patchPlanner({
                cy,
                selectedNode: null,
                selectedEdge: null
            });

            cy.nodes().forEach((node) => {
                const nodeId = String(node.id());
                const isPositionableNode = nodeId.startsWith('NODE_') || nodeId.startsWith('OLT_');

                if (isPositionableNode) {
                    node.grabify();
                } else {
                    node.ungrabify();
                }
            });

            cy.on('dragfree', 'node', async (evt) => {
                const node = evt.target;
                const d = node.data();

                const nodeId = String(d.id || '');
                const isDatabaseNode = nodeId.startsWith('NODE_');
                const isSyntheticOlt = nodeId.startsWith('OLT_');

                if (!isDatabaseNode && !isSyntheticOlt) return;

                try {
                    const pos = node.position();
                    const snappedPosition = {
                        x: Math.round(pos.x / plannerGridUnit) * plannerGridUnit,
                        y: Math.round(pos.y / plannerGridUnit) * plannerGridUnit
                    };

                    node.position(snappedPosition);

                    if (isDatabaseNode) {
                        await savePlannerNodePosition({
                            nodeId: d.raw_id,
                            x: Number(snappedPosition.x.toFixed(2)),
                            y: Number(snappedPosition.y.toFixed(2))
                        });
                    } else {
                        saveSyntheticPlannerPosition(nodeId, snappedPosition);
                    }

                    node.data({
                        ...d,
                        planner_x: Number(snappedPosition.x.toFixed(2)),
                        planner_y: Number(snappedPosition.y.toFixed(2))
                    });
                } catch (err) {
                    console.error('Failed to save planner node position:', err);
                    ui.toast('error', 'Failed to save planner position.');
                }
            });

            bindPlannerCyEvents(cy);

            cy.on('mouseover', 'node', (evt) => {
                if (!tooltipEl) return;

                const d = evt.target.data();
                const type = upper(d.type || '');

                let rows = `
            <div><strong>Type:</strong> ${escape(d.type || '-')}</div>
            <div><strong>Status:</strong> ${escape(d.status || '-')}</div>
        `;

                if (type === 'ODF') {
                    const uplink = [
                        d.olt_name,
                        d.olt_port_label
                    ].filter(Boolean).join(' • ') || '-';

                    rows += `<div><strong>Uplink:</strong> ${escape(uplink)}</div>`;
                } else if (type === 'LCP') {
                    const uplink = [
                        d.parent_odf_name,
                        d.parent_odf_port_number ? `Port ${d.parent_odf_port_number}` : ''
                    ].filter(Boolean).join(' • ') || '-';

                    rows += `<div><strong>Uplink:</strong> ${escape(uplink)}</div>`;
                } else if (type === 'NAP') {
                    const uplink = [
                        d.source_box_name,
                        d.source_port_number ? `Port ${d.source_port_number}` : '',
                        d.feed_mode ? `(${d.feed_mode})` : ''
                    ].filter(Boolean).join(' • ').replace('• (', ' (') || '-';

                    rows += `<div><strong>Uplink:</strong> ${escape(uplink)}</div>`;
                }

                tooltipEl.classList.remove('d-none');
                tooltipEl.innerHTML = `
            <div class="tooltip-title">${escape(d.label || '-')}</div>
            <div class="tooltip-grid">
                ${rows}
            </div>
        `;
            });

            cy.on('mouseover', 'edge', (evt) => {
                if (!tooltipEl) return;

                const d = evt.target.data();
                tooltipEl.classList.remove('d-none');
                tooltipEl.innerHTML = `
            <div class="tooltip-title">${escape(d.label || 'LINK')}</div>
            <div class="tooltip-grid">
                <div><strong>Type:</strong> ${escape(d.link_type || '-')}</div>
                <div><strong>Source:</strong> ${escape(d.source_name || d.source_node_id || '-')}</div>
                <div><strong>Target:</strong> ${escape(d.target_name || d.target_node_id || '-')}</div>
            </div>
        `;
            });

            cy.on('mousemove', (evt) => {
                if (!tooltipEl || tooltipEl.classList.contains('d-none')) return;
                tooltipEl.style.left = `${evt.renderedPosition.x + 18}px`;
                tooltipEl.style.top = `${evt.renderedPosition.y + 18}px`;
            });

            cy.on('mouseout', 'node, edge', () => {
                if (!tooltipEl) return;
                tooltipEl.classList.add('d-none');
                tooltipEl.innerHTML = '';
            });

            renderPlannerSelectionPanels();
            applyPlannerTraceHighlight();
            attachPlannerDragBehavior();

            setTimeout(() => {
                ensurePlannerToolbarClickable();
            }, 0);
        }

        function renderPlannerMapView() {
            const mapCanvas = $('#plannerMapCanvas');
            const mapEl = $('#napPlannerMap');

            if (!mapCanvas || !mapEl) return;

            if (typeof window.L === 'undefined') {
                mapEl.innerHTML = `
            <div class="nx-workspace-map-placeholder">
                <div class="nx-workspace-map-placeholder-icon">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
                <div class="nx-workspace-map-placeholder-title">Leaflet Not Loaded</div>
                <div class="nx-workspace-map-placeholder-text">
                    Please check /assets/leaflet/leaflet.js.
                </div>
            </div>
        `;
                return;
            }

            mapCanvas.classList.remove('d-none');

            setTimeout(() => {
                initPlannerLeafletMap();
            }, 120);
        }

        function initPlannerLeafletMap() {
            const mapEl = $('#napPlannerMap');
            if (!mapEl || typeof window.L === 'undefined') return;

            const oldMap = getState().planner.map;
            if (oldMap && typeof oldMap.remove === 'function') {
                oldMap.remove();
            }

            mapEl.innerHTML = '';

            maps?.fixLeafletDefaultIcons?.();

            const map = L.map(mapEl, {
                center: [14.5995, 120.9842],
                zoom: 13,
                zoomControl: true
            });

            const streetLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}', {
                maxZoom: 19,
                attribution: 'Tiles &copy; Esri'
            });

            const satelliteLayer = L.tileLayer(
                'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
                {
                    maxZoom: 22,
                    attribution: 'Tiles &copy; Esri'
                }
            );

            streetLayer.addTo(map);

            L.control.layers(
                {
                    'Map': streetLayer,
                    'Satellite': satelliteLayer
                },
                {},
                {
                    position: 'topleft',
                    collapsed: false
                }
            ).addTo(map);

            const markerLayer = L.layerGroup().addTo(map);
            const linkLayer = L.layerGroup().addTo(map);

            const nodes = getPlannerMapNodes();
            const links = getPlannerMapLinks(nodes);

            links.forEach((link) => {
                L.polyline(
                    [
                        [link.source.latitude, link.source.longitude],
                        [link.target.latitude, link.target.longitude]
                    ],
                    {
                        color: getPlannerMapLinkColor(link.link_type),
                        weight: 5,
                        opacity: 0.8
                    }
                )
                    .bindPopup(buildPlannerMapLinkPopup(link))
                    .addTo(linkLayer);
            });

            nodes.forEach((node) => {
                const baseColor = getPlannerMapNodeColor(node.node_type);
                const color = getPlannerMapUtilizationColor(
                    node.utilization?.percent || 0,
                    baseColor
                );

                const marker = L.circleMarker([node.latitude, node.longitude], {
                    radius: getPlannerMapNodeRadius(node.node_type),
                    color,
                    fillColor: color,
                    fillOpacity: 0.95,
                    weight: 3
                })
                    .bindPopup(buildPlannerMapNodePopup(node))
                    .on('click', () => {
                        patchPlanner({
                            selectedNode: null,
                            selectedEdge: null
                        });
                    })
                    .addTo(markerLayer);

                marker.bindTooltip(buildPlannerMapNodeLabel(node), {
                    permanent: true,
                    direction: 'top',
                    offset: [0, -8],
                    opacity: 1,
                    className: `nap-map-node-label ${String(node.node_type || '').toLowerCase()} ${node.utilizationClass || 'success'}`
                });
            });

            if (nodes.length) {
                const bounds = L.latLngBounds(nodes.map((node) => [node.latitude, node.longitude]));
                map.fitBounds(bounds, {
                    padding: [50, 50],
                    maxZoom: 18
                });
            }

            patchPlanner({
                map,
                mapLayers: {
                    markers: markerLayer,
                    links: linkLayer
                }
            });

            setTimeout(() => {
                map.invalidateSize();
            }, 250);

            attachPlannerDragBehavior();
            ensurePlannerToolbarClickable();
        }

        function buildPlannerMapNodeLabel(node) {
            const name = node.map_name || '-';
            const util = node.utilization || { used: 0, total: 0, percent: 0 };

            if (util.total > 0) {
                return `${name} (${util.used}/${util.total} • ${util.percent}%)`;
            }

            return name;
        }

        function getPlannerMapUtilization(source) {
            const total = num(
                source?.port_count ||
                source?.total_ports ||
                source?.splitter_ratio ||
                source?.splitter_ports ||
                0,
                0
            );

            const used = num(source?.used_ports || 0, 0);
            const free = Math.max(total - used, 0);
            const percent = total > 0 ? Math.round((used / total) * 100) : 0;

            return { used, total, free, percent };
        }

        function getPlannerMapUtilizationClass(percent) {
            if (percent >= 96) return 'danger';
            if (percent >= 81) return 'warning';
            if (percent >= 51) return 'info';
            return 'success';
        }

        function getPlannerMapUtilizationColor(percent, fallbackColor) {
            if (percent >= 96) return '#dc2626';
            if (percent >= 81) return '#f97316';
            if (percent >= 51) return '#facc15';
            return fallbackColor;
        }
        function getPlannerMapNodes() {
            const elements = getFilteredPlannerElements();

            return safeArray(elements)
                .filter((el) => {
                    const d = el?.data || {};
                    return String(d.id || '').startsWith('NODE_');
                })
                .map((el) => {
                    const d = el.data || {};
                    const source = getPlannerMapSourceRow(d);
                    const utilization = getPlannerMapUtilization(source);

                    return {
                        ...d,
                        source,
                        utilization,
                        utilizationClass: getPlannerMapUtilizationClass(utilization.percent),
                        node_type: upper(d.type || source?.box_type || source?.node_type || ''),
                        latitude: toFloatOrNull(source?.latitude),
                        longitude: toFloatOrNull(source?.longitude),
                        map_name: d.label || source?.odf_name || source?.box_name || source?.node_name || '-',
                        map_location: source?.location || d.location || '-',
                        map_status: source?.status || d.status || 'ACTIVE'
                    };
                })
                .filter((node) => node.latitude != null && node.longitude != null);
        }

        function getPlannerMapSourceRow(d) {
            const type = upper(d?.type || '');
            const refId = num(d?.reference_id || d?.raw_id || 0);

            if (type === 'ODF') return findById(getState().odfs, refId);
            if (type === 'LCP') return findById(getState().lcps, refId);
            if (type === 'NAP') return findById(getState().naps, refId);

            return null;
        }

        function getPlannerMapLinks(nodes) {
            const elements = getFilteredPlannerElements();
            const nodeByPlannerId = new Map();

            nodes.forEach((node) => {
                nodeByPlannerId.set(String(node.id), node);
                nodeByPlannerId.set(`NODE_${node.raw_id}`, node);
            });

            return safeArray(elements)
                .filter((el) => {
                    const d = el?.data || {};
                    return String(d.id || '').startsWith('LINK_') || String(d.id || '').includes('EDGE');
                })
                .map((el) => {
                    const d = el.data || {};
                    const source = nodeByPlannerId.get(String(d.source));
                    const target = nodeByPlannerId.get(String(d.target));

                    if (!source || !target) return null;

                    return {
                        ...d,
                        source,
                        target,
                        link_type: upper(d.link_type || 'DISTRIBUTION')
                    };
                })
                .filter(Boolean);
        }

        function getPlannerMapNodeColor(type) {
            const t = upper(type || '');
            if (t === 'ODF') return '#dc2626';
            if (t === 'LCP') return '#2563eb';
            if (t === 'NAP') return '#16a34a';
            return '#64748b';

        }

        function getPlannerMapNodeRadius(type) {
            const t = upper(type || '');

            if (t === 'ODF') return 11;
            if (t === 'LCP') return 10;
            if (t === 'NAP') return 9;

            return 8;
        }

        function getPlannerMapLinkColor(type) {
            const t = upper(type || '');
            if (t === 'FEEDER') return '#7c3aed';
            if (t === 'DISTRIBUTION') return '#f97316';
            if (t === 'DROP') return '#0f172a';
            return '#64748b';

        }

        function buildPlannerMapNodePopup(node) {
            const util = node.utilization || {
                used: 0,
                total: 0,
                free: 0,
                percent: 0
            };

            const badgeClass = getPlannerMapUtilizationClass(util.percent);

            return `
        <div style="min-width:260px">
            <div class="fw-bold mb-1">${escape(node.node_type || 'NODE')}: ${escape(node.map_name || '-')}</div>
            <div><strong>Status:</strong> ${escape(node.map_status || '-')}</div>
            <div><strong>Location:</strong> ${escape(node.map_location || '-')}</div>
            <div><strong>Lat/Lon:</strong> ${escape(formatLatLon(node.latitude, node.longitude))}</div>

            <hr class="my-2">

            <div class="d-flex align-items-center justify-content-between gap-2">
                <strong>Utilization:</strong>
                <span class="nap-map-util-badge ${badgeClass}">
                    ${escape(String(util.used))}/${escape(String(util.total))} • ${escape(String(util.percent))}%
                </span>
            </div>

            <div class="nap-map-util-bar mt-2">
                <div class="nap-map-util-bar-fill ${badgeClass}" style="width:${Math.min(util.percent, 100)}%"></div>
            </div>

            <div class="small text-muted mt-1">
                Free ports: ${escape(String(util.free))}
            </div>

            <hr class="my-2">

            <button
                type="button"
                class="btn btn-sm btn-primary"
                onclick="document.dispatchEvent(new CustomEvent('nx:n ap-map-open-node', { detail: { type: '${escape(String(node.node_type || '').toLowerCase())}', id: '${escape(String(node.reference_id || node.raw_id || ''))}' } }))"
            >
                View Details
            </button>
        </div>
    `.replace('nx:n ap-map-open-node', 'nx:nap-map-open-node');
        }

        function buildPlannerMapLinkPopup(link) {
            const distanceMeters = computeDistanceMeters(
                link.source.latitude,
                link.source.longitude,
                link.target.latitude,
                link.target.longitude
            );

            const estimatedCableMeters = distanceMeters * 1.25;

            return `
        <div style="min-width:260px">
            <div class="fw-bold mb-1">${escape(link.link_type || 'LINK')}</div>

            <div><strong>From:</strong> ${escape(link.source.map_name || '-')}</div>
            <div><strong>To:</strong> ${escape(link.target.map_name || '-')}</div>
            <div><strong>Label:</strong> ${escape(link.label || '-')}</div>

            <hr class="my-2">

            <div><strong>Straight Distance:</strong> ${escape(formatDistanceMeters(distanceMeters))}</div>
            <div><strong>Estimated Cable:</strong> ${escape(formatDistanceMeters(estimatedCableMeters))}</div>
            <div class="text-muted small mt-1">Estimated cable includes 25% allowance.</div>
        </div>
    `;
        }

        function computeDistanceMeters(lat1, lon1, lat2, lon2) {
            const R = 6371000;
            const toRad = (value) => Number(value) * Math.PI / 180;

            const p1 = toRad(lat1);
            const p2 = toRad(lat2);
            const deltaP = toRad(Number(lat2) - Number(lat1));
            const deltaL = toRad(Number(lon2) - Number(lon1));

            const a =
                Math.sin(deltaP / 2) * Math.sin(deltaP / 2) +
                Math.cos(p1) * Math.cos(p2) *
                Math.sin(deltaL / 2) * Math.sin(deltaL / 2);

            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

            return R * c;
        }

        function formatDistanceMeters(meters) {
            const value = Number(meters || 0);

            if (!Number.isFinite(value) || value <= 0) return '-';

            if (value >= 1000) {
                return `${(value / 1000).toFixed(2)} km`;
            }

            return `${Math.round(value)} m`;
        }

        function applyPlannerLinkTargetFilter(sourceNode) {
            const cy = getState().planner.cy;
            if (!cy || !sourceNode) return;

            cy.elements().removeClass('link-source link-valid-target link-invalid-target dimmed');

            const sourceType = String(sourceNode.data('type') || '').toUpperCase();
            const sourceId = sourceNode.id();
            const linkType = getPlannerLinkTypeForSource(sourceType);

            sourceNode.addClass('link-source');

            cy.nodes().forEach((node) => {
                const nodeId = node.id();
                const targetType = String(node.data('type') || '').toUpperCase();

                if (nodeId === sourceId) return;

                if (isValidPlannerLinkPair(sourceType, targetType, linkType)) {
                    node.addClass('link-valid-target');
                } else {
                    node.addClass('link-invalid-target');
                    node.addClass('dimmed');
                }
            });

            cy.edges().forEach((edge) => {
                edge.addClass('dimmed');
            });
        }
        function isValidPlannerLinkPair(sourceType, targetType, linkType = 'DISTRIBUTION') {
            const s = String(sourceType || '').toUpperCase();
            const t = String(targetType || '').toUpperCase();
            const l = String(linkType || '').toUpperCase();

            if (l === 'FEEDER') {
                return s === 'OLT' && t === 'ODF';
            }

            if (l === 'DISTRIBUTION') {
                return (
                    (s === 'ODF' && t === 'LCP') ||
                    (s === 'LCP' && t === 'NAP') ||
                    (s === 'NAP' && t === 'NAP')
                );
            }

            if (l === 'DROP') {
                return false;
            }

            return false;
        }

        function getPlannerLinkTypeForSource(sourceType) {
            const s = String(sourceType || '').toUpperCase();

            if (s === 'OLT') return 'FEEDER';
            if (s === 'ODF') return 'DISTRIBUTION';
            if (s === 'LCP') return 'DISTRIBUTION';
            if (s === 'NAP') return 'DISTRIBUTION';

            return 'DISTRIBUTION';
        }


        function clearPlannerLinkTargetFilter() {
            const cy = getState().planner.cy;
            if (!cy) return;

            cy.elements().removeClass('link-source link-valid-target link-invalid-target');
            cy.elements().removeClass('dimmed');

            applyPlannerTraceHighlight();
        }

        function attachPlannerDragBehavior() {
            const topbar = $('#plannerTopbar');
            const handle = $('#plannerDragHandle');
            const toggleBtn = $('#plannerToolbarToggle');
            const container = document.querySelector('.nx-workspace-canvas-container');

            if (!topbar || !handle || !container) return;

            /*
            |--------------------------------------------------------------------------
            | Prevent duplicate drag binding after rerenders
            |--------------------------------------------------------------------------
            */
            if (handle.dataset.dragBound !== '1') {
                const onMove = (e) => {
                    const planner = getState().planner;
                    if (!planner.drag.active) return;

                    const containerRect = container.getBoundingClientRect();
                    const dx = e.clientX - planner.drag.startX;
                    const dy = e.clientY - planner.drag.startY;

                    let left = planner.drag.initialLeft + dx;
                    let top = planner.drag.initialTop + dy;

                    left = Math.max(8, Math.min(left, containerRect.width - topbar.offsetWidth - 8));
                    top = Math.max(8, Math.min(top, containerRect.height - topbar.offsetHeight - 8));

                    topbar.style.left = `${left}px`;
                    topbar.style.top = `${top}px`;
                    topbar.style.transform = 'none';
                    topbar.style.right = 'auto';
                    topbar.style.bottom = 'auto';
                };

                const onUp = () => {
                    patchPlanner({
                        drag: {
                            ...getState().planner.drag,
                            active: false
                        }
                    });

                    document.removeEventListener('mousemove', onMove);
                    document.removeEventListener('mouseup', onUp);
                };

                handle.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    e.stopPropagation();

                    const rect = topbar.getBoundingClientRect();
                    const containerRect = container.getBoundingClientRect();

                    patchPlanner({
                        drag: {
                            active: true,
                            startX: e.clientX,
                            startY: e.clientY,
                            initialLeft: rect.left - containerRect.left,
                            initialTop: rect.top - containerRect.top
                        }
                    });

                    document.addEventListener('mousemove', onMove);
                    document.addEventListener('mouseup', onUp);
                });

                handle.dataset.dragBound = '1';
            }

            /*
            |--------------------------------------------------------------------------
            | Rebind collapse toggle after rerenders
            |--------------------------------------------------------------------------
            */
            if (toggleBtn && toggleBtn.dataset.collapseBound !== '1') {
                toggleBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();

                    const collapsed = topbar.classList.toggle('is-collapsed');

                    const icon = toggleBtn.querySelector('i');
                    if (icon) {
                        icon.className = collapsed
                            ? 'bi bi-layout-sidebar'
                            : 'bi bi-layout-sidebar-inset';
                    }
                });

                toggleBtn.dataset.collapseBound = '1';
            }
        }

        async function loadPlannerOltPorts(oltId) {
            const plannerPortSelect = $('#plannerOltPortSelect');

            if (!oltId) {
                patch({ plannerOltPorts: [] });
                clearSelect(plannerPortSelect, 'OLT Port');
                return;
            }

            try {
                const rows = safeArray(await api.get(`/api/v1/olt-management/available-ports/${oltId}`));
                patch({ plannerOltPorts: rows });

                fillSelect(plannerPortSelect, rows, {
                    placeholder: 'OLT Port',
                    label: (row) => row.port_path || row.port_name || `${row.frame ?? 0}/${row.slot ?? 0}/${row.port ?? 0}`
                });

                if (getState().planner.oltPortId) plannerPortSelect.value = String(getState().planner.oltPortId);
            } catch {
                patch({ plannerOltPorts: [] });
                clearSelect(plannerPortSelect, 'OLT Port');
            }
        }

        function populatePlannerOltFilters() {
            const s = getState();
            const oltSelect = $('#plannerOltSelect');
            const portSelect = $('#plannerOltPortSelect');
            if (!oltSelect || !portSelect) return;

            fillSelect(oltSelect, s.oltDevices, {
                placeholder: 'OLT Device',
                label: (row) => row.name || row.device_name || row.hostname || row.ip_address || `OLT ${row.id}`
            });

            oltSelect.value = s.planner.oltId || '';

            const scopeIsOlt = s.planner.scopeMode === 'OLT';
            oltSelect.disabled = !scopeIsOlt;
            portSelect.disabled = !scopeIsOlt || !s.planner.oltId;

            loadPlannerOltPorts(s.planner.oltId).then(() => {
                portSelect.disabled = !scopeIsOlt || !getState().planner.oltId;
            });
        }

        function wirePlannerControls() {
            const plannerRoot =
                $('#plannerTopbar')?.closest('.nx-workspace-canvas-container') ||
                document;

            plannerRoot.querySelectorAll('[data-planner-view]').forEach((btn) => {
                btn.onclick = () => {
                    const nextView = btn.dataset.plannerView || 'logical';

                    patchPlanner({ view: nextView });
                    writeUiState({ plannerView: nextView });

                    plannerRoot.querySelectorAll('[data-planner-view]').forEach((item) => {
                        item.classList.toggle('active', item.dataset.plannerView === nextView);
                    });

                    const logicalCanvas = $('#plannerLogicalCanvas');
                    const mapCanvas = $('#plannerMapCanvas');

                    if (logicalCanvas) {
                        logicalCanvas.classList.toggle('d-none', nextView !== 'logical');
                    }

                    if (mapCanvas) {
                        mapCanvas.classList.toggle('d-none', nextView !== 'map');
                    }

                    if (nextView === 'logical') {
                        renderPlannerLogicalView();
                    } else if (nextView === 'map') {
                        if (typeof renderPlannerMapView === 'function') {
                            renderPlannerMapView();
                        }
                    }
                };
            });

            plannerRoot.querySelectorAll('[data-planner-scope]').forEach((btn) => {
                btn.onclick = () => {
                    const nextScope = btn.dataset.plannerScope || 'ALL';
                    const current = getState().planner || {};

                    const nextPlanner = {
                        ...current,
                        scopeMode: nextScope
                    };

                    if (nextScope === 'ALL') {
                        nextPlanner.oltId = '';
                        nextPlanner.oltPortId = '';
                    }

                    patchPlanner(nextPlanner);

                    writeUiState({
                        plannerScopeMode: nextScope,
                        plannerOltId: nextPlanner.oltId || '',
                        plannerOltPortId: nextPlanner.oltPortId || ''
                    });

                    if (nextScope === 'ALL') {
                        patch({ plannerOltPorts: [] });
                    }

                    if (typeof applyPlannerScopeButtons === 'function') {
                        applyPlannerScopeButtons();
                    } else {
                        plannerRoot.querySelectorAll('[data-planner-scope]').forEach((item) => {
                            item.classList.toggle('active', item.dataset.plannerScope === nextScope);
                        });
                    }

                    if (typeof populatePlannerOltFilters === 'function') {
                        populatePlannerOltFilters();
                    }

                    if (typeof renderPlannerLogicalView === 'function') {
                        renderPlannerLogicalView();
                    }
                };
            });

            const plannerOltSelect = $('#plannerOltSelect');
            if (plannerOltSelect) {
                plannerOltSelect.onchange = async () => {
                    const nextOltId = plannerOltSelect.value || '';

                    patchPlanner({
                        oltId: nextOltId,
                        oltPortId: ''
                    });

                    writeUiState({
                        plannerOltId: nextOltId,
                        plannerOltPortId: ''
                    });

                    if (typeof loadPlannerOltPorts === 'function') {
                        await loadPlannerOltPorts(nextOltId);
                    }

                    const plannerOltPortSelect = $('#plannerOltPortSelect');
                    if (plannerOltPortSelect) {
                        plannerOltPortSelect.value = '';
                        plannerOltPortSelect.disabled = !nextOltId;
                    }

                    renderPlannerLogicalView();
                };
            }

            const plannerOltPortSelect = $('#plannerOltPortSelect');
            if (plannerOltPortSelect) {
                plannerOltPortSelect.onchange = () => {
                    const nextPortId = plannerOltPortSelect.value || '';

                    patchPlanner({
                        oltPortId: nextPortId
                    });

                    writeUiState({
                        plannerOltPortId: nextPortId
                    });

                    renderPlannerLogicalView();
                };
            }

            const plannerRefreshBtn = $('#plannerRefreshBtn');
            if (plannerRefreshBtn) {
                plannerRefreshBtn.onclick = async () => {
                    await loadPlannerObjects();
                    renderPlannerLogicalView();
                    ui.toast('success', 'Planner refreshed.');
                };
            }

            const plannerClearHighlightBtn = $('#plannerClearHighlightBtn');
            if (plannerClearHighlightBtn) {
                plannerClearHighlightBtn.onclick = () => {
                    patchPlanner({
                        selectedNode: null,
                        selectedEdge: null,
                        linking: {
                            active: false,
                            sourceNodeId: null,
                            sourceType: null,
                            linkType: null
                        },
                        trace: {
                            upstreamNodeIds: [],
                            upstreamEdgeIds: [],
                            downstreamNodeIds: [],
                            downstreamEdgeIds: []
                        }
                    });

                    clearPlannerLinkTargetFilter();
                    renderPlannerSelection();
                    renderPlannerNodeProperties();
                    renderPlannerLinkIdentification();
                    renderPlannerLinkProperties();
                    hidePlannerInspectorIfEmpty();
                };
            }

            const plannerDeleteObjectBtn = $('#plannerDeleteObjectBtn');
            if (plannerDeleteObjectBtn) {
                plannerDeleteObjectBtn.onclick = async () => {
                    await deleteSelectedPlannerObject();
                };
            }

            const plannerDeleteLinkBtn = $('#plannerDeleteLinkBtn');
            if (plannerDeleteLinkBtn) {
                plannerDeleteLinkBtn.onclick = async () => {
                    await deleteSelectedPlannerLink();
                };
            }

            const plannerAddObjectBtn = $('#plannerAddObjectBtn');
            if (plannerAddObjectBtn) {
                plannerAddObjectBtn.onclick = () => {
                    const modalEl = document.getElementById('plannerObjectModal');

                    if (!modalEl) {
                        ui.toast('error', 'Planner object modal not found.');
                        return;
                    }

                    refs.plannerObjectModalEl = modalEl;

                    const instance =
                        bootstrap.Modal.getInstance(modalEl) ||
                        bootstrap.Modal.getOrCreateInstance(modalEl);

                    instance.show();
                    setTimeout(bindPlannerCreateButtons, 100);
                };
            }

            const plannerAddLinkBtn = $('#plannerAddLinkBtn');
            if (plannerAddLinkBtn) {
                plannerAddLinkBtn.onclick = () => {
                    const selectedNode = getState().planner.selectedNode;

                    if (!selectedNode) {
                        ui.toast('warning', 'Select a source node first.');
                        return;
                    }

                    const sourceType = String(selectedNode.data('type') || '').toUpperCase();

                    if (!['OLT', 'ODF', 'LCP', 'NAP'].includes(sourceType)) {
                        ui.toast('warning', 'This node type cannot be used as a planner source.');
                        return;
                    }

                    patchPlanner({
                        linking: {
                            active: true,
                            sourceNodeId: selectedNode.id(),
                            sourceType,
                            linkType: getPlannerLinkTypeForSource(sourceType)
                        }
                    });

                    applyPlannerLinkTargetFilter(selectedNode);
                    ui.toast('info', `Link mode enabled. Select a valid target for ${sourceType}.`);
                };
            }

            const plannerSaveBtn = $('#plannerSaveBtn');
            if (plannerSaveBtn) {
                plannerSaveBtn.onclick = async () => {
                    ui.toast('info', 'Planner positions are auto-saved.');
                };
            }
        }

        async function loadPlannerObjects() {
            try {
                const plannerData = await api.get('/api/v1/nap-management/planner/objects');
                patch({
                    plannerObjects: {
                        nodes: safeArray(plannerData?.nodes ?? plannerData?.planner_nodes ?? plannerData),
                        links: safeArray(plannerData?.links ?? [])
                    }
                });
            } catch {
                patch({
                    plannerObjects: {
                        nodes: [],
                        links: []
                    }
                });
            }
        }

        async function loadOltDevices() {
            try {
                patch({ oltDevices: safeArray(await api.get('/api/v1/olt-management/devices')) });
            } catch {
                patch({ oltDevices: [] });
            }

            fillSelect(refs.odf.olt, getState().oltDevices, {
                placeholder: 'Select OLT',
                label: (row) => row.name || row.device_name || row.hostname || row.ip_address || `OLT ${row.id}`
            });
        }

        async function loadOdfOltPorts(selectedPortId = null) {
            if (!refs.odf.olt || !refs.odf.oltPort) return;

            const oltId = num(refs.odf.olt.value, 0);
            if (!oltId) {
                clearSelect(refs.odf.oltPort, 'Select OLT port');
                return;
            }

            try {
                const portRows = safeArray(await api.get(`/api/v1/olt-management/available-ports/${oltId}`));
                const editingOdfId = num(getState().edit.odfId || 0);

                const usedPortIds = new Set(
                    safeArray(getState().odfs)
                        .filter((row) => num(row.olt_id) === oltId)
                        .filter((row) => !editingOdfId || num(row.id) !== editingOdfId)
                        .map((row) => String(num(row.olt_port_id || 0)))
                        .filter((v) => v !== '0')
                );

                const availablePorts = portRows.filter((row) => {
                    const portId = String(num(row.id || 0));
                    if (selectedPortId && String(selectedPortId) === portId) return true;
                    return !usedPortIds.has(portId);
                });

                fillSelect(refs.odf.oltPort, availablePorts, {
                    placeholder: 'Select OLT port',
                    selected: selectedPortId,
                    label: (row) => row.port_path || row.port_name || `${row.frame ?? 0}/${row.slot ?? 0}/${row.port ?? 0}`
                });
            } catch {
                clearSelect(refs.odf.oltPort, 'No OLT ports found');
            }
        }

        async function populateNapParentPorts(parentType, parentId, selectedPortId = null) {
            if (!refs.nap.parentPort) return;

            const s = getState();
            const type = upper(parentType) === 'NAP' ? 'NAP' : 'LCP';
            const id = num(parentId, 0);

            if (!id) {
                clearSelect(refs.nap.parentPort, 'Select parent port');
                return;
            }

            const parentRow = type === 'LCP'
                ? findById(s.lcps, id)
                : findById(s.naps, id);

            if (parentRow && isRowInMaintenance(parentRow) && !selectedPortId) {
                clearSelect(refs.nap.parentPort, `${type} is in maintenance`);
                return;
            }

            const normalizeRows = (rows = []) => {
                return safeArray(rows)
                    .map((row) => {
                        const portId = num(row.id || 0);
                        const portNumber = num(row.port_number || row.port_no || row.port || 0);
                        const status = upper(row.status || row.derived_status || 'AVAILABLE');
                        const connectedEntityType = upper(row.connected_entity_type || 'NONE');

                        return {
                            ...row,
                            id: portId,
                            port_number: portNumber,
                            status,
                            connected_entity_type: connectedEntityType,
                            owner_status: row.owner_status || parentRow?.status || 'ACTIVE'
                        };
                    })
                    .filter((row) => {
                        if (!row.id || !row.port_number) return false;
                        return isSelectableParentPortRow(row, selectedPortId);
                    });
            };

            const fillParentPortSelect = (rows) => {
                if (!rows.length) {
                    clearSelect(refs.nap.parentPort, 'No available parent ports');
                    return;
                }

                fillSelect(refs.nap.parentPort, rows, {
                    placeholder: 'Select parent port',
                    selected: selectedPortId,
                    label: (row) => `Port ${row.port_number} • ${row.status}`
                });
            };

            try {
                let response;

                if (type === 'LCP') {
                    const url = selectedPortId
                        ? `/api/v1/nap-management/lcp-ports/${id}?include_current=${encodeURIComponent(selectedPortId)}`
                        : `/api/v1/nap-management/lcp-ports/${id}`;
                    response = await api.get(url);
                } else {
                    const url = selectedPortId
                        ? `/api/v1/nap-management/nap-parent-ports/${id}?include_current=${encodeURIComponent(selectedPortId)}`
                        : `/api/v1/nap-management/nap-parent-ports/${id}`;
                    response = await api.get(url);
                }

                const apiRows = safeArray(
                    response?.data ??
                    response?.rows ??
                    response?.items ??
                    response
                );

                const normalized = normalizeRows(apiRows);

                if (normalized.length) {
                    fillParentPortSelect(normalized);
                    return;
                }
            } catch (err) {
                console.error('populateNapParentPorts API error:', err);
            }

            try {
                const parentGridRow = type === 'LCP'
                    ? findById(s.lcpPortGrid, id)
                    : findById(s.napPortGrid, id);

                const fallbackPorts = safeArray(parentGridRow?.ports).map((row) => ({
                    ...row,
                    owner_status: parentRow?.status || 'ACTIVE'
                }));

                const normalizedFallback = normalizeRows(fallbackPorts);

                if (normalizedFallback.length) {
                    fillParentPortSelect(normalizedFallback);
                    return;
                }

                clearSelect(refs.nap.parentPort, 'No available parent ports');
            } catch (err) {
                console.error('populateNapParentPorts fallback error:', err);
                clearSelect(refs.nap.parentPort, 'Failed to load parent ports');
            }
        }

        function populateLcpParentOdfOptions(selectedId = null) {
            if (!refs.lcp.parentOdf) return;

            const s = getState();
            const allRows = safeArray(s.odfs);

            if (!allRows.length) {
                clearSelect(refs.lcp.parentOdf, 'No ODF available');
                return;
            }

            const rows = allRows.filter((row) => isSelectableParentRow(row, selectedId));

            if (!rows.length) {
                clearSelect(refs.lcp.parentOdf, 'No active ODF available');
                clearSelect(refs.lcp.parentOdfPort, 'Select ODF port');
                return;
            }

            fillSelect(refs.lcp.parentOdf, rows, {
                placeholder: 'Select ODF',
                selected: selectedId,
                label: (odf) => {
                    const name = odf.odf_name || odf.box_name || odf.name || odf.node_code || `ODF-${odf.id}`;
                    return isRowInMaintenance(odf)
                        ? getMaintenanceBlockedLabel(name, 'ODF')
                        : name;
                }
            });
        }

        async function populateLcpParentOdfPorts(selectedPortId = null) {
            if (!refs.lcp.parentOdf || !refs.lcp.parentOdfPort) return;

            const s = getState();
            const odfId = num(refs.lcp.parentOdf.value, 0);
            if (!odfId) {
                clearSelect(refs.lcp.parentOdfPort, 'Select ODF port');
                return;
            }

            const selectedOdf = findById(s.odfs, odfId);
            if (selectedOdf && isRowInMaintenance(selectedOdf) && !selectedPortId) {
                clearSelect(refs.lcp.parentOdfPort, 'ODF is in maintenance');
                return;
            }

            try {
                const ports = safeArray(await api.get(`/api/v1/nap-management/odf-ports/${odfId}`));
                const editingId = num(s.edit.lcpId || 0);

                const usedPortNumbers = new Set(
                    s.lcps
                        .filter((lcp) => num(getLcpParentOdfId(lcp)) === odfId)
                        .filter((lcp) => !editingId || num(lcp.id || 0) !== editingId)
                        .map((lcp) => num(lcp.parent_odf_port_number || 0))
                        .filter((n) => n > 0)
                );

                const usedPortIds = new Set(
                    s.lcps
                        .filter((lcp) => num(getLcpParentOdfId(lcp)) === odfId)
                        .filter((lcp) => !editingId || num(lcp.id || 0) !== editingId)
                        .map((lcp) => String(num(lcp.parent_odf_port_id || 0)))
                        .filter((v) => v !== '0')
                );

                const availablePorts = ports.filter((row) => {
                    const portId = String(num(row.id || 0));
                    const portNumber = num(row.port_number || row.port_no || 0);
                    const status = upper(row.status || row.derived_status || 'AVAILABLE');

                    if (selectedPortId && String(selectedPortId) === portId) return true;
                    if (usedPortIds.has(portId)) return false;
                    if (usedPortNumbers.has(portNumber)) return false;
                    if (status !== 'AVAILABLE') return false;

                    return true;
                });

                if (!availablePorts.length) {
                    clearSelect(refs.lcp.parentOdfPort, 'No available ODF ports');
                    return;
                }

                fillSelect(refs.lcp.parentOdfPort, availablePorts, {
                    placeholder: 'Select ODF port',
                    selected: selectedPortId,
                    label: (row) => `Port ${row.port_number || row.port_no || row.id} • ${upper(row.status || row.derived_status || 'AVAILABLE')}`
                });
            } catch {
                clearSelect(refs.lcp.parentOdfPort, 'Failed to load ports');
            }
        }

        function buildSyntheticOltElements(filteredNodeRows) {
            const s = getState();
            const syntheticNodes = [];
            const syntheticEdges = [];
            const createdOltNodes = new Set();
            const savedSyntheticPositions = getSyntheticPlannerPositions();
            const visibleNodeIds = new Set(filteredNodeRows.map((row) => `NODE_${row.id}`));

            const realEdgeExists = (sourcePlannerId, targetPlannerId) =>
                s.plannerObjects.links.some(
                    (link) =>
                        num(link.source_node_id) === num(sourcePlannerId) &&
                        num(link.target_node_id) === num(targetPlannerId)
                );

            s.odfs.forEach((odf) => {
                const oltId = num(odf.olt_id || 0);
                if (!oltId) return;

                const plannerOdfNode = findPlannerNodeRowBySource(
                    'odf_nodes',
                    odf.id,
                    odf.odf_name || odf.node_code || '',
                    'ODF'
                );

                if (!plannerOdfNode || !visibleNodeIds.has(`NODE_${plannerOdfNode.id}`)) return;

                const oltNodeId = `OLT_${oltId}`;

                if (!createdOltNodes.has(oltNodeId)) {
                    const savedPosition = savedSyntheticPositions[oltNodeId] || {};

                    syntheticNodes.push({
                        data: {
                            id: oltNodeId,
                            raw_id: `OLT_${oltId}`,
                            label: getOltLabelById(oltId) || `OLT ${oltId}`,
                            short_label: getOltLabelById(oltId) || `OLT ${oltId}`,
                            type: 'OLT',
                            status: 'ACTIVE',
                            location: '',
                            reference_table: 'olt_devices',
                            reference_id: oltId,
                            olt_id: oltId,
                            olt_name: getOltLabelById(oltId) || `OLT ${oltId}`,
                            olt_port_id: null,
                            olt_port_label: '',
                            planner_x: Number.isFinite(Number(savedPosition.x)) ? Number(savedPosition.x) : null,
                            planner_y: Number.isFinite(Number(savedPosition.y)) ? Number(savedPosition.y) : null
                        }
                    });
                    createdOltNodes.add(oltNodeId);
                }

                syntheticEdges.push({
                    data: {
                        id: `OLT_EDGE_ODF_${oltId}_${plannerOdfNode.id}`,
                        raw_id: `OLT_EDGE_ODF_${oltId}_${plannerOdfNode.id}`,
                        source: oltNodeId,
                        target: `NODE_${plannerOdfNode.id}`,
                        link_type: 'FEEDER',
                        label: buildConnectionLabel(
                            'OLT',
                            {
                                ...odf,
                                source_port_label:
                                    getPlannerPortLabelById(odf.olt_port_id) ||
                                    odf.olt_port_path ||
                                    odf.olt_port_label ||
                                    ''
                            },
                            oltId,
                            'ODF',
                            {
                                ...odf,
                                target_port_label: `Port ${getInputPortNumber(odf, 1)}`
                            },
                            odf.id
                        ),
                        source_node_id: oltNodeId,
                        target_node_id: plannerOdfNode.id,
                        source_name: getOltLabelById(oltId) || `OLT ${oltId}`,
                        target_name: plannerOdfNode.node_name || plannerOdfNode.odf_name || odf.odf_name || 'ODF'
                    }
                });

                const matchedLcps = s.lcps.filter((lcp) => num(getLcpParentOdfId(lcp)) === num(odf.id));

                matchedLcps.forEach((lcp) => {
                    const plannerLcpNode = findPlannerNodeRowBySource(
                        'network_boxes',
                        lcp.id,
                        lcp.box_name || '',
                        'LCP'
                    );

                    if (!plannerLcpNode || !visibleNodeIds.has(`NODE_${plannerLcpNode.id}`)) return;

                    if (!realEdgeExists(plannerOdfNode.id, plannerLcpNode.id)) {
                        syntheticEdges.push({
                            data: {
                                id: `ODF_EDGE_LCP_${plannerOdfNode.id}_${plannerLcpNode.id}`,
                                raw_id: `ODF_EDGE_LCP_${plannerOdfNode.id}_${plannerLcpNode.id}`,
                                source: `NODE_${plannerOdfNode.id}`,
                                target: `NODE_${plannerLcpNode.id}`,
                                link_type: 'DISTRIBUTION',
                                label: buildConnectionLabel(
                                    'ODF',
                                    {
                                        ...lcp,
                                        source_port_label: `Port ${num(lcp.parent_odf_port_number || 1, 1)}`
                                    },
                                    odf.id,
                                    'LCP',
                                    {
                                        ...lcp,
                                        target_port_label: `Port ${getInputPortNumber(lcp, 1)}`
                                    },
                                    lcp.id
                                ),
                                source_node_id: plannerOdfNode.id,
                                target_node_id: plannerLcpNode.id,
                                source_name: plannerOdfNode.node_name || plannerOdfNode.odf_name || odf.odf_name || 'ODF',
                                target_name: plannerLcpNode.node_name || plannerLcpNode.box_name || lcp.box_name || 'LCP'
                            }
                        });
                    }

                    const matchedNaps = s.naps.filter((nap) => {
                        const plannerNapNode = findPlannerNodeRowBySource(
                            'network_boxes',
                            nap.id,
                            nap.box_name || '',
                            'NAP'
                        );
                        if (!plannerNapNode) return false;

                        const incomingLink = s.plannerObjects.links.find(
                            (link) => num(link.target_node_id) === num(plannerNapNode.id)
                        );

                        return incomingLink && num(incomingLink.source_node_id) === num(plannerLcpNode.id);
                    });

                    matchedNaps.forEach((nap) => {
                        const plannerNapNode = findPlannerNodeRowBySource(
                            'network_boxes',
                            nap.id,
                            nap.box_name || '',
                            'NAP'
                        );

                        if (!plannerNapNode || !visibleNodeIds.has(`NODE_${plannerNapNode.id}`)) return;

                        if (!realEdgeExists(plannerLcpNode.id, plannerNapNode.id)) {
                            syntheticEdges.push({
                                data: {
                                    id: `LCP_EDGE_NAP_${plannerLcpNode.id}_${plannerNapNode.id}`,
                                    raw_id: `LCP_EDGE_NAP_${plannerLcpNode.id}_${plannerNapNode.id}`,
                                    source: `NODE_${plannerLcpNode.id}`,
                                    target: `NODE_${plannerNapNode.id}`,
                                    link_type: 'DISTRIBUTION',
                                    label: buildConnectionLabel(
                                        'LCP',
                                        {
                                            ...nap,
                                            source_port_label: `Port ${num(nap.source_port_number || nap.parent_port_number || 1, 1)}`
                                        },
                                        lcp.id,
                                        'NAP',
                                        {
                                            ...nap,
                                            target_port_label: `Port ${getInputPortNumber(nap, 1)}`
                                        },
                                        nap.id
                                    ),
                                    source_node_id: plannerLcpNode.id,
                                    target_node_id: plannerNapNode.id,
                                    source_name: plannerLcpNode.node_name || plannerLcpNode.box_name || lcp.box_name || 'LCP',
                                    target_name: plannerNapNode.node_name || plannerNapNode.box_name || nap.box_name || 'NAP'
                                }
                            });
                        }
                    });
                });
            });

            return { nodes: syntheticNodes, edges: syntheticEdges };
        }

        async function populateNapParentBoxes(parentType, selectedParentId = null, selectedPortId = null) {
            if (!refs.nap.parentBox || !refs.nap.parentPort) return;

            const s = getState();
            const type = upper(parentType) === 'NAP' ? 'NAP' : 'LCP';
            const selectedIdNum = num(selectedParentId, 0);

            let rows = type === 'LCP'
                ? safeArray(s.lcpCandidates)
                : safeArray(s.napCandidates).filter((row) => num(row.id) !== num(s.edit.napId));

            if (selectedIdNum > 0) {
                const currentParentRow = type === 'LCP'
                    ? findById(s.lcps, selectedIdNum)
                    : findById(s.naps, selectedIdNum);

                if (currentParentRow && !rows.some((row) => num(row.id) === selectedIdNum)) {
                    rows = [currentParentRow, ...rows];
                }
            }

            rows = rows.filter((row) => isSelectableParentRow(row, selectedParentId));

            if (!rows.length) {
                clearSelect(
                    refs.nap.parentBox,
                    type === 'LCP' ? 'No active LCP available' : 'No active NAP available'
                );
                clearSelect(refs.nap.parentPort, 'No available parent ports');
                return;
            }

            fillSelect(refs.nap.parentBox, rows, {
                placeholder: 'Select parent box',
                selected: selectedParentId,
                label: (row) => {
                    const name = row.box_name || row.lcp_name || row.nap_name || `Box ${row.id}`;
                    return isRowInMaintenance(row)
                        ? getMaintenanceBlockedLabel(name, type)
                        : name;
                }
            });

            const effectiveParentId = selectedParentId || refs.nap.parentBox.value || '';
            await populateNapParentPorts(type, effectiveParentId, selectedPortId);
        }

        function setModalMeta(refGroup, mode, { title, subtitle, notice = '' }) {
            text(refGroup.title, title || '');
            text(refGroup.subtitle, subtitle || '');

            if (!refGroup.notice) return;

            if (mode === 'edit') {
                refGroup.notice.textContent = notice || 'Edit mode';
                show(refGroup.notice);
            } else {
                refGroup.notice.textContent = '';
                hide(refGroup.notice);
            }
        }

        function openEntityModal(refGroup, pickerKey) {
            console.log('openEntityModal called:', {
                pickerKey,
                modalId: refGroup?.modalEl?.id || null
            });

            const el = refGroup?.modalEl;
            if (!el) {
                ui.toast('error', 'Target modal element not found.');
                return;
            }

            const instance = window.bootstrap?.Modal?.getOrCreateInstance(el);
            instance.show();

            if (pickerKey) {
                setTimeout(() => openLeafletPicker(pickerKey, refGroup), 120);
            }
        }

        function resetOdfForm() {
            patchEdit({ odfId: null });
            resetGeoFields(refs.odf);

            refs.odf.form?.reset();
            val(refs.odf.id, '');
            val(refs.odf.status, 'ACTIVE');
            val(refs.odf.olt, '');
            clearSelect(refs.odf.oltPort, 'Select OLT port');
            populateInputPortSelect(
                refs.odf.inputPort,
                refs.odf.ports?.value || 0,
                '',
                'Select ODF input port'
            );

            setModalMeta(refs.odf, 'create', {
                title: 'Create ODF',
                subtitle: 'Add an optical distribution frame.'
            });
        }

        function resetLcpForm() {
            patchEdit({ lcpId: null });
            resetGeoFields(refs.lcp);

            refs.lcp.form?.reset();
            val(refs.lcp.id, '');
            val(refs.lcp.code, '');
            clearSelect(refs.lcp.parentOdf, 'Select ODF');
            clearSelect(refs.lcp.parentOdfPort, 'Select ODF port');
            populateInputPortSelect(
                refs.lcp.inputPort,
                refs.lcp.ports?.value || 0,
                '',
                'Select LCP input port'
            );

            setModalMeta(refs.lcp, 'create', {
                title: 'Create LCP',
                subtitle: 'Add a local convergence point.'
            });
        }

        function resetNapForm() {
            patchEdit({ napId: null });
            resetGeoFields(refs.nap);

            refs.nap.form?.reset();
            val(refs.nap.id, '');
            val(refs.nap.code, '');
            val(refs.nap.parentType, 'LCP');
            val(refs.nap.feedMode, 'DIRECT_FROM_LCP');
            clearSelect(refs.nap.parentBox, 'Select parent box');
            clearSelect(refs.nap.parentPort, 'Select parent port');

            setModalMeta(refs.nap, 'create', {
                title: 'Create NAP',
                subtitle: 'Add a network access point box.'
            });
        }

        function getPlannerConnectionInfo(d) {
            const type = upper(d?.type || '');

            if (type === 'ODF') {
                return {
                    deviceLabel: 'OLT Device',
                    deviceValue: d?.olt_name || '-',
                    portLabel: 'OLT Port',
                    portValue: d?.olt_port_label || '-',
                    uplinkLabel: 'Uplink',
                    uplinkValue: [d?.olt_name, d?.olt_port_label].filter(Boolean).join(' • ') || '-'
                };
            }

            if (type === 'LCP') {
                const parentPort = d?.parent_odf_port_number ? `Port ${d.parent_odf_port_number}` : '-';
                return {
                    deviceLabel: 'Connected From',
                    deviceValue: d?.parent_odf_name || '-',
                    portLabel: 'Parent Port',
                    portValue: parentPort,
                    uplinkLabel: 'Uplink',
                    uplinkValue: [d?.parent_odf_name, parentPort !== '-' ? parentPort : ''].filter(Boolean).join(' • ') || '-'
                };
            }

            if (type === 'NAP') {
                const sourcePort = d?.source_port_number ? `Port ${d.source_port_number}` : '-';
                return {
                    deviceLabel: 'Connected From',
                    deviceValue: d?.source_box_name || '-',
                    portLabel: 'Parent Port',
                    portValue: sourcePort,
                    uplinkLabel: 'Uplink',
                    uplinkValue: [
                        d?.source_box_name,
                        sourcePort !== '-' ? sourcePort : '',
                        d?.feed_mode ? `(${d.feed_mode})` : ''
                    ].filter(Boolean).join(' ') || '-'
                };
            }

            return {
                deviceLabel: 'Connected From',
                deviceValue: '-',
                portLabel: 'Port',
                portValue: '-',
                uplinkLabel: 'Uplink',
                uplinkValue: '-'
            };
        }
        function setCreateNotice(refGroup, title, subtitle) {
            text(refGroup.title, title);
            text(refGroup.subtitle, subtitle);

            if (refGroup.notice) {
                refGroup.notice.classList.add('d-none');
                refGroup.notice.textContent = '';
            }
        }

        function setEditNotice(refGroup, title, subtitle, noticeText) {
            text(refGroup.title, title);
            text(refGroup.subtitle, subtitle);

            if (refGroup.notice) {
                refGroup.notice.classList.remove('d-none');
                refGroup.notice.textContent = noticeText || 'Editing existing record.';
            }
        }


        async function openEditOdf(id) {
            const row = findById(getState().odfs, id);
            if (!row) return;

            resetOdfForm();
            patchEdit({ odfId: num(id) });

            val(refs.odf.id, row.id);
            val(refs.odf.name, row.odf_name || '');
            val(refs.odf.code, row.node_code || '');
            val(refs.odf.ports, row.port_count || row.total_ports || 24);
            val(refs.odf.status, row.status || 'ACTIVE');
            val(refs.odf.location, row.location || '');
            val(refs.odf.latitude, row.latitude || '');
            val(refs.odf.longitude, row.longitude || '');
            val(refs.odf.remarks, row.remarks || '');

            populateInputPortSelect(
                refs.odf.inputPort,
                row.port_count || row.total_ports || 24,
                row.input_port_number || row.target_port_number || '',
                'Select ODF input port'
            );

            await loadOltDevices();

            if (row.olt_id && refs.odf.olt) {
                refs.odf.olt.value = String(row.olt_id);
                await loadOdfOltPorts(row.olt_port_id || null);
            }

            setModalMeta(refs.odf, 'edit', {
                title: 'Edit ODF',
                subtitle: 'Update optical distribution frame details.',
                notice: 'This ODF is in edit mode.'
            });

            openEntityModal(refs.odf, 'odf');
        }

        async function openEditLcp(id) {
            try {
                await loadAll();
                const row = findById(getState().lcps, id);
                if (!row) throw new Error('LCP record not found.');

                resetLcpForm();
                patchEdit({ lcpId: num(id) });

                val(refs.lcp.id, row.id);
                val(refs.lcp.code, row.box_code || '');
                val(refs.lcp.name, row.box_name || '');
                val(refs.lcp.ports, row.splitter_ratio || row.total_ports || 0);
                val(refs.lcp.location, row.location || '');
                val(refs.lcp.latitude, row.latitude || '');
                val(refs.lcp.longitude, row.longitude || '');

                populateInputPortSelect(
                    refs.lcp.inputPort,
                    row.splitter_ratio || row.total_ports || 0,
                    row.input_port_number || row.target_port_number || '',
                    'Select LCP input port'
                );

                populateLcpParentOdfOptions(row.parent_odf_id || null);
                await populateLcpParentOdfPorts(row.parent_odf_port_id || null);

                if (row.parent_odf_id && refs.lcp.parentOdf) {
                    refs.lcp.parentOdf.value = String(row.parent_odf_id);
                }

                if (row.parent_odf_port_id && refs.lcp.parentOdfPort) {
                    refs.lcp.parentOdfPort.value = String(row.parent_odf_port_id);
                }

                const selfInMaintenance = upper(row.status || '') === 'MAINTENANCE';
                const parentOdf = findById(getState().odfs, row.parent_odf_id);
                const parentInMaintenance = upper(parentOdf?.status || '') === 'MAINTENANCE';

                if (refs.lcp.parentOdf) {
                    refs.lcp.parentOdf.disabled = selfInMaintenance || parentInMaintenance;
                }

                if (refs.lcp.parentOdfPort) {
                    refs.lcp.parentOdfPort.disabled = selfInMaintenance || parentInMaintenance;
                }

                if (refs.lcp.inputPort) {
                    refs.lcp.inputPort.disabled = selfInMaintenance;
                }

                if (refs.lcp.ports) {
                    refs.lcp.ports.disabled = selfInMaintenance;
                }

                setModalMeta(refs.lcp, 'edit', {
                    title: 'Edit LCP',
                    subtitle: parentInMaintenance
                        ? 'Parent ODF is in maintenance. Uplink changes are temporarily locked.'
                        : 'Update local convergence point details.',
                    notice: selfInMaintenance
                        ? 'This LCP is in maintenance mode. Structural edits are locked.'
                        : (
                            parentInMaintenance
                                ? 'Parent ODF is currently in maintenance mode.'
                                : 'This LCP is in edit mode.'
                        )
                });

                openEntityModal(refs.lcp, 'lcp');
            } catch (err) {
                ui.swal({
                    title: 'Error',
                    text: err.message || 'Failed to load LCP data.',
                    icon: 'error'
                });
                modal.close(refs.lcp.modalEl);
            }
        }

        async function openCreateOdf() {
            resetOdfForm();
            await loadOltDevices();
            await loadOdfOltPorts(null);

            setModalMeta(refs.odf, 'create', {
                title: 'Create ODF',
                subtitle: 'Add an optical distribution frame.'
            });

            openEntityModal(refs.odf, 'odf');
        }

        async function openCreateLcp() {
            await loadAll();

            const odfs = safeArray(getState().odfs).filter((row) => !isRowInMaintenance(row));
            if (!odfs.length) {
                ui.toast('warning', 'No active ODF available. Create ODF first.');
                return;
            }

            resetLcpForm();
            populateLcpParentOdfOptions(null);
            clearSelect(refs.lcp.parentOdfPort, 'Select ODF port');

            setModalMeta(refs.lcp, 'create', {
                title: 'Create LCP',
                subtitle: 'Add a local convergence point.'
            });

            openEntityModal(refs.lcp, 'lcp');
        }

        async function openCreateNap() {
            await loadAll();

            const lcps = safeArray(getState().lcpCandidates).filter((row) => !isRowInMaintenance(row));
            if (!lcps.length) {
                ui.toast('warning', 'No active LCP available. Create LCP first.');
                return;
            }

            resetNapForm();
            await populateNapParentBoxes('LCP', null, null);

            setModalMeta(refs.nap, 'create', {
                title: 'Create NAP',
                subtitle: 'Add a network access point box.'
            });

            openEntityModal(refs.nap, 'nap');
        }

        async function openEditNap(id) {
            const row = findById(getState().naps, id);
            if (!row) return;

            resetNapForm();
            patchEdit({ napId: num(id) });

            val(refs.nap.id, row.id);
            val(refs.nap.code, row.box_code || '');
            val(refs.nap.name, row.box_name || '');
            val(refs.nap.splitterPorts, row.splitter_ratio || row.splitter_ports || 0);
            val(refs.nap.location, row.location || '');
            val(refs.nap.latitude, row.latitude || '');
            val(refs.nap.longitude, row.longitude || '');

            const parentType = upper(row.feed_mode) === 'CASCADE_FROM_NAP' ? 'NAP' : 'LCP';
            val(refs.nap.parentType, parentType);
            val(
                refs.nap.feedMode,
                row.feed_mode || (parentType === 'NAP' ? 'CASCADE_FROM_NAP' : 'DIRECT_FROM_LCP')
            );

            const s = getState();

            let selectedParentId = num(
                row.source_box_id ||
                row.parent_box_id ||
                row.parent_lcp_id ||
                row.parent_nap_id ||
                0
            );

            let selectedPortId = num(
                row.source_port_id ||
                row.parent_port_id ||
                0
            );

            if (!selectedParentId) {
                const parentName = String(
                    row.source_box_name ||
                    row.parent_box_name ||
                    ''
                ).trim();

                if (parentName) {
                    const pool = parentType === 'NAP'
                        ? safeArray(s.naps).filter((x) => num(x.id) !== num(row.id))
                        : safeArray(s.lcps);

                    const foundParent = pool.find((x) =>
                        upper(x.box_name || x.lcp_name || x.nap_name || '') === upper(parentName)
                    );

                    if (foundParent) {
                        selectedParentId = num(foundParent.id);
                    }
                }
            }

            await populateNapParentBoxes(
                parentType,
                selectedParentId || null,
                selectedPortId || null
            );

            if (selectedParentId && refs.nap.parentBox) {
                refs.nap.parentBox.value = String(selectedParentId);
            }

            if (selectedPortId && refs.nap.parentPort) {
                refs.nap.parentPort.value = String(selectedPortId);
            }

            const selfInMaintenance = upper(row.status || '') === 'MAINTENANCE';

            const parentRow = parentType === 'NAP'
                ? findById(s.naps, selectedParentId)
                : findById(s.lcps, selectedParentId);

            const parentInMaintenance = upper(parentRow?.status || '') === 'MAINTENANCE';

            if (refs.nap.parentType) {
                refs.nap.parentType.disabled = selfInMaintenance || parentInMaintenance;
            }

            if (refs.nap.parentBox) {
                refs.nap.parentBox.disabled = selfInMaintenance || parentInMaintenance;
            }

            if (refs.nap.parentPort) {
                refs.nap.parentPort.disabled = selfInMaintenance || parentInMaintenance;
            }

            if (refs.nap.feedMode) {
                refs.nap.feedMode.disabled = selfInMaintenance || parentInMaintenance;
            }

            if (refs.nap.splitterPorts) {
                refs.nap.splitterPorts.disabled = selfInMaintenance;
            }

            setModalMeta(refs.nap, 'edit', {
                title: 'Edit NAP',
                subtitle: parentInMaintenance
                    ? 'Parent box is in maintenance. Uplink changes are temporarily locked.'
                    : 'Update NAP details and parent feed.',
                notice: selfInMaintenance
                    ? 'This NAP is in maintenance mode. Structural edits are locked.'
                    : (
                        parentInMaintenance
                            ? 'Parent box is currently in maintenance mode.'
                            : 'This NAP is in edit mode.'
                    )
            });

            openEntityModal(refs.nap, 'nap');
        }

        function getPlannerRefTableByType(typeUpper) {
            return typeUpper === 'ODF' ? 'odf_nodes' : 'network_boxes';
        }

        function normalizePortLabelValue(value) {
            return String(value || '')
                .trim()
                .toUpperCase()
                .replace(/\s+/g, ' ');
        }

        function normalizePortNumber(value) {
            return num(value, 0);
        }

        function getPlannerNodeDisplayName(node) {
            return (
                node?.node_name ||
                node?.box_name ||
                node?.odf_name ||
                node?.name ||
                '-'
            );
        }

        function extractPortFromSideLabel(sideLabel) {
            const text = String(sideLabel || '').trim();
            if (!text) return '';

            const m = text.match(/PORT\s+(\d+)/i);
            return m ? `Port ${m[1]}` : '';
        }

        function extractRemotePortFromLinkLabel(link, remoteSide = 'target') {
            const full = String(link?.label || '').trim();
            if (!full) return '';

            const parts = full.split('→').map(x => x.trim());
            if (parts.length !== 2) return '';

            const remotePart = remoteSide === 'target' ? parts[1] : parts[0];
            return extractPortFromSideLabel(remotePart);
        }


        function buildRemoteLabelFromLink(link, remoteSide = 'target') {
            const s = getState();
            if (!link) return '';

            const remoteNodeId = remoteSide === 'target'
                ? num(link.target_node_id)
                : num(link.source_node_id);

            const remotePlannerNode = safeArray(s.plannerObjects.nodes).find(
                (node) => num(node.id) === remoteNodeId
            );

            const remoteName = remoteSide === 'target'
                ? (link.target_name || getPlannerNodeDisplayName(remotePlannerNode))
                : (link.source_name || getPlannerNodeDisplayName(remotePlannerNode));

            let remotePort = remoteSide === 'target'
                ? (
                    link.target_port_label ||
                    (num(link.target_port_number || 0) > 0 ? `Port ${num(link.target_port_number)}` : '')
                )
                : (
                    link.source_port_label ||
                    (num(link.source_port_number || 0) > 0 ? `Port ${num(link.source_port_number)}` : '')
                );

            if (!remotePort) {
                remotePort = extractRemotePortFromLinkLabel(link, remoteSide);
            }

            return [remoteName, remotePort].filter(Boolean).join(' • ');
        }

        function resolveViewerPortLinkedLabel(typeUpper, merged, port) {
            const s = getState();
            const portNumber = num(port?.port_number || port?.port_no || 0);

            /*
            |--------------------------------------------------------------------------
            | ODF VIEWER
            | 1) OLT uplink input port
            | 2) Direct LCP data mapping fallback
            | 3) Planner link fallback
            |--------------------------------------------------------------------------
            */
            if (typeUpper === 'ODF') {
                const odfInputPort = num(
                    merged?.input_port_number ||
                    merged?.target_port_number ||
                    0
                );

                if (odfInputPort && portNumber === odfInputPort) {
                    const oltName =
                        merged?.olt_name ||
                        getOltLabelById(merged?.olt_id) ||
                        '';

                    const oltPort =
                        merged?.olt_port_path ||
                        merged?.olt_port_label ||
                        getPlannerPortLabelById(merged?.olt_port_id) ||
                        '';

                    return [oltName, oltPort].filter(Boolean).join(' • ');
                }

                // ✅ FIRST FALLBACK: resolve from actual LCP records
                const matchedLcp = safeArray(s.lcps).find((lcp) => {
                    const parentOdfId = num(
                        lcp.parent_odf_id ||
                        lcp.source_odf_id ||
                        lcp.odf_id ||
                        0
                    );

                    const parentOdfPortNo = num(
                        lcp.parent_odf_port_number ||
                        lcp.source_port_number ||
                        0
                    );

                    return parentOdfId === num(merged?.id) && parentOdfPortNo === portNumber;
                });

                if (matchedLcp) {
                    const lcpName =
                        matchedLcp.box_name ||
                        matchedLcp.lcp_name ||
                        `LCP-${matchedLcp.id}`;

                    const lcpInputPortNo = num(
                        matchedLcp.input_port_number ||
                        matchedLcp.target_port_number ||
                        1,
                        1
                    );

                    return `${lcpName} • Port ${lcpInputPortNo}`;
                }

                // ✅ SECOND FALLBACK: planner link lookup
                const plannerNode = findPlannerNodeRowBySource(
                    'odf_nodes',
                    merged?.id,
                    merged?.odf_name || merged?.box_name || '',
                    'ODF'
                );

                if (plannerNode) {
                    const outgoingLink = safeArray(s.plannerObjects.links).find((link) => {
                        if (num(link.source_node_id) !== num(plannerNode.id)) return false;

                        const directSourcePort = num(
                            link.source_port_number ||
                            link.source_port_no ||
                            link.source_port ||
                            0
                        );

                        if (directSourcePort > 0) {
                            return directSourcePort === portNumber;
                        }

                        const label = String(link.label || '');
                        const leftSide = label.split('→')[0] || '';
                        const leftSidePort = extractPortFromSideLabel(leftSide);

                        if (!leftSidePort) return false;

                        return num(leftSidePort.replace(/[^\d]/g, ''), 0) === portNumber;
                    });

                    if (outgoingLink) {
                        return buildRemoteLabelFromLink(outgoingLink, 'target');
                    }
                }
            }

            /*
            |--------------------------------------------------------------------------
            | LCP VIEWER
            | 1) Parent ODF input side
            | 2) Direct NAP data mapping fallback
            | 3) Planner link fallback
            |--------------------------------------------------------------------------
            */
            if (typeUpper === 'LCP') {
                const lcpInputPort = num(
                    merged?.input_port_number ||
                    merged?.target_port_number ||
                    0
                );

                if (lcpInputPort && portNumber === lcpInputPort) {
                    const parentName =
                        merged?.parent_odf_name ||
                        merged?.source_odf_name ||
                        '';

                    const parentPortNo = num(
                        merged?.parent_odf_port_number ||
                        merged?.source_port_number ||
                        0
                    );

                    const parentPort = parentPortNo > 0 ? `Port ${parentPortNo}` : '';
                    return [parentName, parentPort].filter(Boolean).join(' • ');
                }

                // ✅ FIRST FALLBACK: resolve from actual NAP records
                const matchedNap = safeArray(s.naps).find((nap) => {
                    const sourceBoxId = num(
                        nap.source_box_id ||
                        nap.parent_box_id ||
                        nap.parent_lcp_id ||
                        0
                    );

                    const sourcePortNo = num(
                        nap.source_port_number ||
                        nap.parent_port_number ||
                        0
                    );

                    return sourceBoxId === num(merged?.id) && sourcePortNo === portNumber;
                });

                if (matchedNap) {
                    const napName =
                        matchedNap.box_name ||
                        matchedNap.nap_name ||
                        `NAP-${matchedNap.id}`;

                    const napInputPortNo = num(
                        matchedNap.input_port_number ||
                        matchedNap.target_port_number ||
                        1,
                        1
                    );

                    return `${napName} • Port ${napInputPortNo}`;
                }

                // ✅ SECOND FALLBACK: planner link lookup
                const plannerNode = findPlannerNodeRowBySource(
                    'network_boxes',
                    merged?.id,
                    merged?.box_name || '',
                    'LCP'
                );

                if (plannerNode) {
                    const outgoingLink = safeArray(s.plannerObjects.links).find((link) => {
                        if (num(link.source_node_id) !== num(plannerNode.id)) return false;

                        const directSourcePort = num(
                            link.source_port_number ||
                            link.source_port_no ||
                            link.source_port ||
                            0
                        );

                        if (directSourcePort > 0) {
                            return directSourcePort === portNumber;
                        }

                        const label = String(link.label || '');
                        const leftSide = label.split('→')[0] || '';
                        const leftSidePort = extractPortFromSideLabel(leftSide);

                        if (!leftSidePort) return false;

                        return num(leftSidePort.replace(/[^\d]/g, ''), 0) === portNumber;
                    });

                    if (outgoingLink) {
                        return buildRemoteLabelFromLink(outgoingLink, 'target');
                    }
                }
            }

            /*
            |--------------------------------------------------------------------------
            | NAP VIEWER
            | 1) direct child NAP mapping
            | 2) planner link fallback
            |--------------------------------------------------------------------------
            */
            if (typeUpper === 'NAP') {
                const matchedChildNap = safeArray(s.naps).find((nap) => {
                    const sourceBoxId = num(
                        nap.source_box_id ||
                        nap.parent_box_id ||
                        nap.parent_nap_id ||
                        0
                    );

                    const sourcePortNo = num(
                        nap.source_port_number ||
                        nap.parent_port_number ||
                        0
                    );

                    return sourceBoxId === num(merged?.id) && sourcePortNo === portNumber;
                });

                if (matchedChildNap) {
                    const napName =
                        matchedChildNap.box_name ||
                        matchedChildNap.nap_name ||
                        `NAP-${matchedChildNap.id}`;

                    const napInputPortNo = num(
                        matchedChildNap.input_port_number ||
                        matchedChildNap.target_port_number ||
                        1,
                        1
                    );

                    return `${napName} • Port ${napInputPortNo}`;
                }

                const plannerNode = findPlannerNodeRowBySource(
                    'network_boxes',
                    merged?.id,
                    merged?.box_name || '',
                    'NAP'
                );

                if (plannerNode) {
                    const outgoingLink = safeArray(s.plannerObjects.links).find((link) => {
                        if (num(link.source_node_id) !== num(plannerNode.id)) return false;

                        const directSourcePort = num(
                            link.source_port_number ||
                            link.source_port_no ||
                            link.source_port ||
                            0
                        );

                        if (directSourcePort > 0) {
                            return directSourcePort === portNumber;
                        }

                        const label = String(link.label || '');
                        const leftSide = label.split('→')[0] || '';
                        const leftSidePort = extractPortFromSideLabel(leftSide);

                        if (!leftSidePort) return false;

                        return num(leftSidePort.replace(/[^\d]/g, ''), 0) === portNumber;
                    });

                    if (outgoingLink) {
                        return buildRemoteLabelFromLink(outgoingLink, 'target');
                    }
                }
            }

            return (
                port?.connected_entity_name ||
                port?.child_box_name ||
                port?.source_box_name ||
                port?.reserved_label ||
                ''
            );
        }

        function openBoxViewer(type, id) {
            const merged = { ...(getPortGridRow(type, id) || {}), ...(getDetailRow(type, id) || {}) };
            if (!Object.keys(merged).length) return;

            const typeUpper = upper(type);
            const name = merged.odf_name || merged.box_name || merged.node_name || '-';
            const status = merged.status || 'ACTIVE';
            const location = merged.location || '-';
            const totalPorts = num(merged.port_count || merged.total_ports || merged.splitter_ratio || merged.splitter_ports || 0);
            const ports = safeArray(merged.ports);

            text(refs.viewer.title, `${typeUpper} Viewer`);
            text(refs.viewer.subtitle, getUplinkLabel(merged));
            text(refs.viewer.type, typeUpper);
            text(refs.viewer.name, name);
            text(refs.viewer.location, location);
            text(refs.viewer.status, status);
            text(refs.viewer.badgeName, name);
            text(refs.viewer.badgeType, typeUpper);
            text(refs.viewer.ratio, totalPorts || ports.length || 0);

            if (refs.viewer.cabinet) {
                refs.viewer.cabinet.classList.toggle('maintenance', upper(status) === 'MAINTENANCE');
                refs.viewer.cabinet.classList.toggle('odf', typeUpper === 'ODF');
            }

            const viewerPorts = ports;
            let uplinkCardHtml = '';

            if (typeUpper === 'NAP') {
                uplinkCardHtml = `
        <div class="splitter-row splitter-row-uplink">
            <div class="splitter-port reserved uplink-port">
                <div class="splitter-port-no">UPLINK</div>
                <div class="splitter-port-status">INPUT FEED</div>
                <div class="splitter-port-linked">${escape(getUplinkLabel(merged) || 'Incoming uplink')}</div>
            </div>
        </div>
    `;
            }
            const groups = [];
            for (let i = 0; i < viewerPorts.length; i += 8) {
                groups.push(viewerPorts.slice(i, i + 8));
            }

            html(
                refs.viewer.portsArea,
                `
        ${uplinkCardHtml}
        ${
                    groups.length
                        ? groups.map((group) => `
                    <div class="splitter-row">
                        ${group.map((port) => {
                            const linked = resolveViewerPortLinkedLabel(typeUpper, merged, port);
                            const shownStatus = port.status || 'AVAILABLE';
                            const linkedText = linked || 'No link';
                            const linkedClass =
                                linkedText.length > 26 ? 'is-long' :
                                    linkedText.length > 18 ? 'is-medium' : '';

                            return `
                                <div class="splitter-port ${resolvePortClass(port)}">
                                    <div class="splitter-port-no">${escape(port.port_number || port.port_no || '-')}</div>
                                    <div class="splitter-port-status">${escape(shownStatus)}</div>
                                    <div
                                        class="splitter-port-linked ${linked ? '' : 'is-empty'} ${linkedClass}"
                                        title="${escape(linkedText)}"
                                    >
                                        ${escape(linkedText)}
                                    </div>
                                </div>
                            `;
                        }).join('')}
                    </div>
                `).join('')
                        : '<div class="text-muted small">No port data found for this node.</div>'
                }
    `
            );
            html(
                refs.viewer.extraInfo,
                `
        <div class="row g-3">
            <div class="col-md-4">
                <div class="nx-meta-tile">
                    <div class="nx-meta-label">Code</div>
                    <div class="nx-meta-value">${escape(merged.node_code || merged.box_code || '-')}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="nx-meta-tile">
                    <div class="nx-meta-label">Lat / Lon</div>
                    <div class="nx-meta-value">${escape(formatLatLon(merged.latitude, merged.longitude))}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="nx-meta-tile">
                    <div class="nx-meta-label">Uplink</div>
                    <div class="nx-meta-value">${escape(getUplinkLabel(merged))}</div>
                </div>
            </div>
        </div>
    `
            );
            requestAnimationFrame(() => refs.viewer.cabinet?.classList.add('open'));
            modal.open(refs.viewer.modalEl);
        }
        async function confirmDelete(type, id) {
            const typeUpper = upper(type);
            const confirmed = await dialog.confirm({
                title: `Delete ${typeUpper}?`,
                text: 'This will permanently delete the selected record.'
            });
            if (!confirmed) return;
            const urlMap = {
                odf: '/api/v1/nap-management/odf/delete',
                lcp: '/api/v1/nap-management/lcp/delete',
                nap: '/api/v1/nap-management/nap/delete'
            };
            await actions.run({
                loading: `Deleting ${typeUpper}...`,
                success: `${typeUpper} deleted.`,
                task: async () => api.form(urlMap[type], buildFormData({ id })),
                onSuccess: async () => {
                    await loadAll();
                }
            });
        }
        function getBoxRowByTypeAndId(type, id) {
            const s = getState();
            const typeLower = String(type || '').toLowerCase();
            const numericId = num(id, 0);

            if (typeLower === 'odf') return findById(s.odfs, numericId);
            if (typeLower === 'lcp') return findById(s.lcps, numericId);
            if (typeLower === 'nap') return findById(s.naps, numericId);

            return null;
        }

        function countChildBoxes(type, id) {
            const s = getState();
            const typeUpper = upper(type);
            const numericId = num(id, 0);

            if (!numericId) return 0;

            const visited = new Set();

            function countNapDescendants(parentNapId) {
                let total = 0;

                const childNaps = safeArray(s.naps).filter((nap) => {
                    const sourceBoxId = num(
                        nap.source_box_id ||
                        nap.parent_box_id ||
                        nap.parent_nap_id ||
                        0
                    );
                    return sourceBoxId === parentNapId;
                });

                childNaps.forEach((nap) => {
                    const napId = num(nap.id, 0);
                    if (!napId || visited.has(`NAP:${napId}`)) return;

                    visited.add(`NAP:${napId}`);
                    total += 1;
                    total += countNapDescendants(napId);
                });

                return total;
            }

            if (typeUpper === 'ODF') {
                let total = 0;

                const childLcps = safeArray(s.lcps).filter(
                    (row) => num(row.parent_odf_id || row.source_odf_id || row.odf_id || 0) === numericId
                );

                childLcps.forEach((lcp) => {
                    const lcpId = num(lcp.id, 0);
                    if (!lcpId || visited.has(`LCP:${lcpId}`)) return;

                    visited.add(`LCP:${lcpId}`);
                    total += 1;

                    const directNaps = safeArray(s.naps).filter((nap) => {
                        const sourceBoxId = num(
                            nap.source_box_id ||
                            nap.parent_box_id ||
                            nap.parent_lcp_id ||
                            0
                        );
                        return sourceBoxId === lcpId;
                    });

                    directNaps.forEach((nap) => {
                        const napId = num(nap.id, 0);
                        if (!napId || visited.has(`NAP:${napId}`)) return;

                        visited.add(`NAP:${napId}`);
                        total += 1;
                        total += countNapDescendants(napId);
                    });
                });

                return total;
            }

            if (typeUpper === 'LCP') {
                let total = 0;

                const directNaps = safeArray(s.naps).filter((nap) => {
                    const sourceBoxId = num(
                        nap.source_box_id ||
                        nap.parent_box_id ||
                        nap.parent_lcp_id ||
                        0
                    );
                    return sourceBoxId === numericId;
                });

                directNaps.forEach((nap) => {
                    const napId = num(nap.id, 0);
                    if (!napId || visited.has(`NAP:${napId}`)) return;

                    visited.add(`NAP:${napId}`);
                    total += 1;
                    total += countNapDescendants(napId);
                });

                return total;
            }

            if (typeUpper === 'NAP') {
                return countNapDescendants(numericId);
            }

            return 0;
        }

        async function toggleMaintenance(type, id, currentStatus) {
            try {
                const row = getBoxRowByTypeAndId(type, id);

                if (!row) {
                    ui.toast('error', 'Node not found.');
                    return;
                }

                const isMaintenance = upper(currentStatus || row.status || '') === 'MAINTENANCE';

                const isSelfMaintenance = Number(row.is_self_maintenance || 0) === 1;
                const isParentMaintenance = Number(row.is_parent_maintenance || 0) === 1;
                const maintenanceOrigin = upper(row.maintenance_origin || row.maintenance_source || '');
                const childCount = countChildBoxes(type, id);

                if (isMaintenance && !isSelfMaintenance && (isParentMaintenance || maintenanceOrigin === 'PARENT')) {
                    ui.toast('warning', 'This box is under inherited maintenance from its parent. End maintenance on the parent first.');
                    return;
                }

                const result = await ui.swal({
                    title: isMaintenance ? 'End Maintenance' : 'Enable Maintenance',
                    html: `
                <div class="text-start">
                    ${
                        (!isSelfMaintenance && (isParentMaintenance || maintenanceOrigin === 'PARENT'))
                            ? `
                                <div class="alert alert-warning py-2 px-3 mb-3">
                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                    Inherited from parent — actions are restricted.
                                </div>
                              `
                            : ''
                    }

                    ${
                        !isMaintenance
                            ? `
                                <label class="form-label fw-semibold">Maintenance Reason</label>
                                <textarea
                                    id="nx-maint-reason"
                                    class="form-control"
                                    rows="3"
                                    placeholder="Enter reason..."
                                ></textarea>

                                ${
                                childCount > 0
                                    ? `
                                            <div class="small text-muted mt-2">
                                                <i class="bi bi-info-circle me-1"></i>
                                                This may affect ${childCount} downstream child box${childCount === 1 ? '' : 'es'}.
                                            </div>
                                          `
                                    : ''
                            }
                              `
                            : `
                                <label class="form-label fw-semibold">Resolution</label>
                                <textarea
                                    id="nx-maint-resolution"
                                    class="form-control"
                                    rows="3"
                                    placeholder="Enter resolution..."
                                ></textarea>
                              `
                    }
                </div>
            `,
                    showCancelButton: true,
                    confirmButtonText: isMaintenance ? 'End Maintenance' : 'Save',
                    cancelButtonText: 'Cancel',
                    focusConfirm: false,
                    preConfirm: () => {
                        const reason = document.getElementById('nx-maint-reason')?.value?.trim() || '';
                        const resolution = document.getElementById('nx-maint-resolution')?.value?.trim() || '';

                        if (!isMaintenance && !reason) {
                            Swal.showValidationMessage('Maintenance reason is required');
                            return false;
                        }

                        if (isMaintenance && !resolution) {
                            Swal.showValidationMessage('Resolution is required');
                            return false;
                        }

                        return {
                            reason: reason || null,
                            resolution: resolution || null
                        };
                    }
                });

                if (!result.isConfirmed || !result.value) return;

                const payload = buildFormData({
                    status: isMaintenance ? 'ACTIVE' : 'MAINTENANCE',
                    maintenance_reason: result.value.reason || '',
                    maintenance_resolution: result.value.resolution || ''
                });

                await api.form(
                    `/api/v1/nap-management/${type}/${id}/maintenance`,
                    payload
                );

                ui.toast(
                    'success',
                    isMaintenance
                        ? 'Maintenance ended successfully.'
                        : 'Maintenance enabled successfully.'
                );

                await loadAll();
            } catch (err) {
                console.error('toggleMaintenance error:', err);
                ui.toast('error', err?.message || 'Unexpected error occurred.');
            }
        }

        async function submitCrud({ id, createUrl, updateUrl, payload, successMessage, onSuccess }) {
            const url = id ? updateUrl(id) : createUrl;
            return actions.run({
                loading: 'Saving...',
                success: successMessage || 'Saved.',
                task: async () => api.form(url, payload),
                onSuccess: async () => {
                    if (typeof onSuccess === 'function') {
                        await onSuccess();
                    }
                }
            });
        }
        function renderPage() {
            const s = getState();
            syncActiveTabUi();
            renderToolbar();
            updateSummary();
            toggleSummarySection();
            if (s.tab === 'planner') return renderPlannerTab();
            if (s.tab === 'links') return renderLinksTab();
            return renderNodesTab();
        }

        async function loadAll() {
            const results = await Promise.allSettled([
                api.get('/api/v1/nap-management/odfs'),
                api.get('/api/v1/nap-management/lcps'),
                api.get('/api/v1/nap-management/naps'),
                api.get('/api/v1/nap-management/odf-port-grid'),
                api.get('/api/v1/nap-management/lcp-port-grid'),
                api.get('/api/v1/nap-management/nap-port-grid'),
                api.get('/api/v1/nap-management/odf-candidates'),
                api.get('/api/v1/nap-management/lcp-candidates'),
                api.get('/api/v1/nap-management/nap-candidates')
            ]);

            patch({
                odfs: results[0].status === 'fulfilled' ? safeArray(results[0].value) : [],
                lcps: results[1].status === 'fulfilled' ? safeArray(results[1].value) : [],
                naps: results[2].status === 'fulfilled' ? safeArray(results[2].value) : [],
                odfPortGrid: results[3].status === 'fulfilled' ? safeArray(results[3].value) : [],
                lcpPortGrid: results[4].status === 'fulfilled' ? safeArray(results[4].value) : [],
                napPortGrid: results[5].status === 'fulfilled' ? safeArray(results[5].value) : [],
                odfCandidates: results[6].status === 'fulfilled' ? safeArray(results[6].value) : [],
                lcpCandidates: results[7].status === 'fulfilled' ? safeArray(results[7].value) : [],
                napCandidates: results[8].status === 'fulfilled' ? safeArray(results[8].value) : []
            });

            await loadOltDevices();
            await loadPlannerObjects();

            const uiState = window.__NAP_BOOT_STATE__ || readUiState();
            const restoredTab = window.__NAP_BOOT_TAB__ || uiState.tab || app.dataset.initialTab || getState().tab || 'planner';

            patch({
                tab: restoredTab
            });

            patchPlanner({
                view: uiState.plannerView || getState().planner.view || 'logical',
                scopeMode: uiState.plannerScopeMode || getState().planner.scopeMode || 'ALL',
                oltId: uiState.plannerOltId || '',
                oltPortId: uiState.plannerOltPortId || ''
            });

            syncActiveTabUi();
            renderPage();

            requestAnimationFrame(() => {
                markAppReady();
            });
        }
        const attachLatLonReverseLookup = (pickerKey, refsGroup) => {
            const runner = util.debounce(() => {
                const lat = toFloatOrNull(refsGroup.latitude?.value);
                const lng = toFloatOrNull(refsGroup.longitude?.value);
                if (lat == null || lng == null) return;
                const lastLat = refsGroup.location?.dataset?.lastGeocodedLat || '';
                const lastLng = refsGroup.location?.dataset?.lastGeocodedLng || '';
                if (lastLat === Number(lat).toFixed(6) && lastLng === Number(lng).toFixed(6)) {
                    return;
                }
                fillLocationFromLatLng(refsGroup, lat, lng);
            }, 700);
            refsGroup.latitude?.addEventListener('change', runner);
            refsGroup.longitude?.addEventListener('change', runner);
            refsGroup.latitude?.addEventListener('blur', runner);
            refsGroup.longitude?.addEventListener('blur', runner);
        };
        attachLatLonReverseLookup('odf', refs.odf);
        attachLatLonReverseLookup('lcp', refs.lcp);
        attachLatLonReverseLookup('nap', refs.nap);
        function buildOdfPayload() {
            return forms.data({
                odf_name: refs.odf.name?.value || '',
                node_code: refs.odf.code?.value || '',
                port_count: refs.odf.ports?.value || '24',
                status: refs.odf.status?.value || 'ACTIVE',
                olt_id: refs.odf.olt?.value || '',
                olt_port_id: refs.odf.oltPort?.value || '',
                input_port_number: getInputPortValue(refs.odf.inputPort),
                location: refs.odf.location?.value || '',
                latitude: refs.odf.latitude?.value || '',
                longitude: refs.odf.longitude?.value || '',
                remarks: refs.odf.remarks?.value || ''
            });
        }
        function buildLcpPayload() {
            const boxCode = refs.lcp.code?.value?.trim() || '';
            return forms.data({
                box_code: boxCode,
                lcp_name: refs.lcp.name?.value || '',
                total_ports: refs.lcp.ports?.value || '',
                parent_odf_id: refs.lcp.parentOdf?.value || '',
                parent_odf_port_id: refs.lcp.parentOdfPort?.value || '',
                input_port_number: getInputPortValue(refs.lcp.inputPort),
                location: refs.lcp.location?.value || '',
                latitude: refs.lcp.latitude?.value || '',
                longitude: refs.lcp.longitude?.value || '',
                status: 'ACTIVE'
            });
        }
        function getNapParentSelection() {
            const parentType = refs.nap.parentType?.value || 'LCP';
            let parentBoxId = refs.nap.parentBox?.value || '';
            let parentPortId = refs.nap.parentPort?.value || '';
            const feedMode =
                refs.nap.feedMode?.value ||
                (upper(parentType) === 'NAP' ? 'CASCADE_FROM_NAP' : 'DIRECT_FROM_LCP');
            if ((!parentBoxId || !parentPortId) && getState().edit.napId) {
                const currentNap = findById(getState().naps, getState().edit.napId);
                if (currentNap) {
                    parentBoxId = parentBoxId || currentNap.source_box_id || '';
                    parentPortId = parentPortId || currentNap.source_port_id || '';
                }
            }
            return {
                parentType,
                parentBoxId,
                parentPortId,
                feedMode
            };
        }
        function buildNapPayload() {
            const {
                parentType,
                parentBoxId,
                parentPortId,
                feedMode
            } = getNapParentSelection();
            return forms.data({
                box_code: refs.nap.code?.value || '',
                nap_name: refs.nap.name?.value || '',
                splitter_ports: refs.nap.splitterPorts?.value || '',
                parent_type: parentType,
                parent_port_id: parentPortId,
                feed_mode: feedMode,
                location: refs.nap.location?.value || '',
                latitude: refs.nap.latitude?.value || '',
                longitude: refs.nap.longitude?.value || '',
                status: 'ACTIVE',
                [upper(parentType) === 'LCP' ? 'parent_lcp_id' : 'parent_nap_id']: parentBoxId
            });
        }
        function initFormBuilders() {
            formInstances.odf?.destroy?.();
            formInstances.lcp?.destroy?.();
            formInstances.nap?.destroy?.();
            formInstances.odf = formBuilder.create({
                el: refs.odf.form,
                submit: async () => {
                    await submitCrud({
                        id: getState().edit.odfId,
                        createUrl: '/api/v1/nap-management/odf/create',
                        updateUrl: (id) => `/api/v1/nap-management/odf/update/${id}`,
                        payload: buildOdfPayload(),
                        successMessage: getState().edit.odfId ? 'ODF updated.' : 'ODF created.',
                        onSuccess: async () => {
                            const returnTab = consumePlannerReturnTab();
                            modal.close(refs.odf.modalEl);
                            await loadAll();
                            if (returnTab) {
                                setActiveTab(returnTab);
                            }
                        }
                    });
                },
                onError: async ({ error }) => {
                    await ui.swal({
                        icon: 'error',
                        title: 'Save Error',
                        text: error?.message || 'Failed to save ODF.'
                    });
                }
            });
            formInstances.lcp = formBuilder.create({
                el: refs.lcp.form,
                rules: {
                    lcpCodeVirtual: ['required']
                },
                map: () => ({
                    lcpCodeVirtual: refs.lcp.code?.value?.trim() || ''
                }),
                submit: async () => {
                    await submitCrud({
                        id: getState().edit.lcpId,
                        createUrl: '/api/v1/nap-management/lcp/create',
                        updateUrl: (id) => `/api/v1/nap-management/lcp/update/${id}`,
                        payload: buildLcpPayload(),
                        successMessage: getState().edit.lcpId ? 'LCP updated.' : 'LCP created.',
                        onSuccess: async () => {
                            const returnTab = consumePlannerReturnTab();
                            modal.close(refs.lcp.modalEl);
                            await loadAll();
                            if (returnTab) {
                                setActiveTab(returnTab);
                            }
                        }
                    });
                },
                onError: async (ctx) => {
                    const message =
                        ctx?.type === 'validation'
                            ? 'LCP code is required.'
                            : (ctx?.error?.message || 'Failed to save LCP.');
                    await ui.swal({
                        icon: 'error',
                        title: 'Save Error',
                        text: message
                    });
                }
            });
            formInstances.nap = formBuilder.create({
                el: refs.nap.form,
                submit: async () => {
                    await submitCrud({
                        id: getState().edit.napId,
                        createUrl: '/api/v1/nap-management/nap/create',
                        updateUrl: (id) => `/api/v1/nap-management/nap/update/${id}`,
                        payload: buildNapPayload(),
                        successMessage: getState().edit.napId ? 'NAP updated.' : 'NAP created.',
                        onSuccess: async () => {
                            const returnTab = consumePlannerReturnTab();
                            modal.close(refs.nap.modalEl);
                            await loadAll();
                            if (returnTab) {
                                setActiveTab(returnTab);
                            }
                        }
                    });
                },
                onError: async ({ error }) => {
                    await ui.swal({
                        icon: 'error',
                        title: 'Save Error',
                        text: error?.message || 'Failed to save NAP.'
                    });
                }
            });
        }

        function ensurePlannerToolbarClickable() {
            const canvas = $('#plannerLogicalCanvas');
            const topbar = $('#plannerTopbar');
            const inspector = $('#plannerInspector');
            const tooltip = $('#plannerHoverTooltip');
            const dragHandle = $('#plannerDragHandle');

            if (canvas) {
                canvas.style.position = 'absolute';
                canvas.style.inset = '0';
                canvas.style.zIndex = '1';
                canvas.style.pointerEvents = 'auto';
            }

            if (topbar) {
                topbar.style.position = 'absolute';
                topbar.style.zIndex = '30';
                topbar.style.pointerEvents = 'auto';

                topbar.querySelectorAll('button, a, select, input, textarea, label').forEach((el) => {
                    el.style.pointerEvents = 'auto';

                    /*
                    |--------------------------------------------------------------------------
                    | Do NOT block mousedown on drag handle
                    |--------------------------------------------------------------------------
                    */
                    if (dragHandle && (el === dragHandle || dragHandle.contains(el))) {
                        el.addEventListener('click', (e) => e.stopPropagation());
                        el.addEventListener('dblclick', (e) => e.stopPropagation());
                        return;
                    }

                    el.addEventListener('mousedown', (e) => e.stopPropagation());
                    el.addEventListener('click', (e) => e.stopPropagation());
                    el.addEventListener('dblclick', (e) => e.stopPropagation());
                    el.addEventListener('touchstart', (e) => e.stopPropagation(), { passive: true });
                });
            }

            if (inspector) {
                inspector.style.position = 'absolute';
                inspector.style.zIndex = '25';
                inspector.style.pointerEvents = 'auto';

                inspector.querySelectorAll('button, a, select, input, textarea, label').forEach((el) => {
                    el.style.pointerEvents = 'auto';
                    el.addEventListener('mousedown', (e) => e.stopPropagation());
                    el.addEventListener('click', (e) => e.stopPropagation());
                    el.addEventListener('dblclick', (e) => e.stopPropagation());
                });
            }

            if (tooltip) {
                tooltip.style.zIndex = '40';
                tooltip.style.pointerEvents = 'none';
            }
        }
        function bindPlannerCyEvents(cy) {
            cy.off('tap', 'node');
            cy.off('tap', 'edge');
            cy.off('tap');

            cy.on('tap', 'node', async (evt) => {
                const node = evt.target;
                const linking = getState().planner.linking || {};
                const isLinkMode = !!linking.active;

                if (isLinkMode) {
                    const sourceNodeId = String(linking.sourceNodeId || '');
                    const sourceType = String(linking.sourceType || '').toUpperCase();
                    const targetType = String(node.data('type') || '').toUpperCase();
                    const targetNodeId = String(node.id() || '');
                    const linkType = String(
                        linking.linkType || getPlannerLinkTypeForSource(sourceType)
                    ).toUpperCase();

                    if (!sourceNodeId || sourceNodeId === targetNodeId) {
                        ui.toast('warning', 'Select a different valid target.');
                        return;
                    }

                    if (!isValidPlannerLinkPair(sourceType, targetType, linkType)) {
                        ui.toast('warning', `Invalid topology: ${sourceType} → ${targetType}`);
                        return;
                    }

                    try {
                        await api.form('/api/v1/nap-management/planner/object-connect', buildFormData({
                            source_node_id: Number(cy.$(`#${sourceNodeId}`).data('raw_id') || 0),
                            target_node_id: Number(node.data('raw_id') || 0),
                            link_type: linkType
                        }));

                        patchPlanner({
                            linking: {
                                active: false,
                                sourceNodeId: null,
                                sourceType: null,
                                linkType: null
                            },
                            selectedNode: null,
                            selectedEdge: null,
                            trace: {
                                upstreamNodeIds: [],
                                upstreamEdgeIds: [],
                                downstreamNodeIds: [],
                                downstreamEdgeIds: []
                            }
                        });

                        clearPlannerLinkTargetFilter();
                        await loadPlannerObjects();
                        renderPlannerLogicalView();
                        ui.toast('success', 'Planner link created.');
                        return;
                    } catch (err) {
                        ui.toast('error', err?.message || 'Failed to create planner link.');
                        return;
                    }
                }

                const currentSelected = getState().planner.selectedNode;
                const isSameNodeSelected =
                    currentSelected && currentSelected.id() === node.id();

                /*
                |--------------------------------------------------------------------------
                | Toggle deselect on same node click
                |--------------------------------------------------------------------------
                */
                if (isSameNodeSelected) {
                    patchPlanner({
                        selectedNode: null,
                        selectedEdge: null,
                        linking: {
                            active: false,
                            sourceNodeId: null,
                            sourceType: null,
                            linkType: null
                        },
                        trace: {
                            upstreamNodeIds: [],
                            upstreamEdgeIds: [],
                            downstreamNodeIds: [],
                            downstreamEdgeIds: []
                        }
                    });

                    clearPlannerLinkTargetFilter();
                    renderPlannerSelectionPanels();
                    applyPlannerTraceHighlight();
                    hidePlannerInspectorIfEmpty();
                    return;
                }

                /*
                |--------------------------------------------------------------------------
                | Rebuild trace path for selected node
                |--------------------------------------------------------------------------
                */
                const rawId = Number(node.data('raw_id') || 0);
                const upstream = tracePlannerUpstream(rawId);
                const downstream = tracePlannerDownstream(rawId);

                patchPlanner({
                    selectedNode: node,
                    selectedEdge: null,
                    linking: {
                        active: false,
                        sourceNodeId: null,
                        sourceType: null,
                        linkType: null
                    },
                    trace: {
                        upstreamNodeIds: upstream.nodeIds,
                        upstreamEdgeIds: upstream.edgeIds,
                        downstreamNodeIds: downstream.nodeIds,
                        downstreamEdgeIds: downstream.edgeIds
                    }
                });

                clearPlannerLinkTargetFilter();
                renderPlannerSelectionPanels();
                showPlannerInspector();
                applyPlannerTraceHighlight();
            });

            cy.on('tap', 'edge', (evt) => {
                const edge = evt.target;

                patchPlanner({
                    selectedEdge: edge,
                    selectedNode: null,
                    linking: {
                        active: false,
                        sourceNodeId: null,
                        sourceType: null,
                        linkType: null
                    },
                    trace: {
                        upstreamNodeIds: [],
                        upstreamEdgeIds: [],
                        downstreamNodeIds: [],
                        downstreamEdgeIds: []
                    }
                });

                clearPlannerLinkTargetFilter();
                renderPlannerSelectionPanels();
                showPlannerInspector();
                applyPlannerTraceHighlight();
            });

            cy.on('tap', (evt) => {
                if (evt.target !== cy) return;

                patchPlanner({
                    selectedNode: null,
                    selectedEdge: null,
                    linking: {
                        active: false,
                        sourceNodeId: null,
                        sourceType: null,
                        linkType: null
                    },
                    trace: {
                        upstreamNodeIds: [],
                        upstreamEdgeIds: [],
                        downstreamNodeIds: [],
                        downstreamEdgeIds: []
                    }
                });

                clearPlannerLinkTargetFilter();
                renderPlannerSelectionPanels();
                applyPlannerTraceHighlight();
                hidePlannerInspectorIfEmpty();
            });
        }
        function bindPlannerCreateButtons() {
            const buttons = document.querySelectorAll('[data-planner-create-type]');

            buttons.forEach(btn => {
                btn.onclick = async () => {
                    const type = String(btn.dataset.plannerCreateType || '').toLowerCase();

                    setPlannerReturnTab('planner');

                    const pickerEl = document.getElementById('plannerObjectModal');

                    const launch = async () => {
                        if (type === 'odf') return openCreateOdf();
                        if (type === 'lcp') return openCreateLcp();
                        if (type === 'nap') return openCreateNap();
                    };

                    if (pickerEl) {
                        const instance =
                            bootstrap.Modal.getInstance(pickerEl) ||
                            bootstrap.Modal.getOrCreateInstance(pickerEl);

                        pickerEl.addEventListener('hidden.bs.modal', launch, { once: true });
                        instance.hide();
                    } else {
                        await launch();
                    }
                };
            });
        }
        function bindEvents() {
            on(app, 'click', '[data-action]', async (_e, btn) => {
                const { action, type, id } = btn.dataset;

                const row = getBoxRowByTypeAndId(type, id);
                const typeUpper = upper(type || row?.box_type || '');

                const statusUpper = upper(row?.status || '');
                const maintenance = statusUpper === 'MAINTENANCE';

                const isSelfMaintenance = Number(row?.is_self_maintenance || 0) === 1;
                const isParentMaintenance = Number(row?.is_parent_maintenance || 0) === 1;
                const maintenanceOrigin = upper(row?.maintenance_origin || row?.maintenance_source || '');

                const inheritedMaintenance =
                    maintenance &&
                    !isSelfMaintenance &&
                    (isParentMaintenance || maintenanceOrigin === 'PARENT');

                /*
                |--------------------------------------------------------------------------
                | TOPOLOGY-BASED CHILD DETECTION
                |--------------------------------------------------------------------------
                */
                const isChildBox =
                    (typeUpper === 'LCP' && num(row?.parent_odf_id || row?.source_odf_id || row?.odf_id || 0) > 0) ||
                    (typeUpper === 'NAP' && num(row?.source_box_id || row?.parent_box_id || row?.parent_lcp_id || row?.parent_nap_id || 0) > 0);

                if (action === 'open-box-viewer' || action === 'view-box') {
                    return openBoxViewer(type, id);
                }

                if (action === 'edit-box') {
                    if (inheritedMaintenance) {
                        ui.toast('warning', 'Editing is disabled (inherited maintenance from parent)');
                        return;
                    }

                    if (type === 'odf') return openEditOdf(id);
                    if (type === 'lcp') return openEditLcp(id);
                    if (type === 'nap') return openEditNap(id);
                }

                if (action === 'delete-box') {
                    if (maintenance) {
                        ui.toast('warning', 'Cannot delete while in maintenance');
                        return;
                    }

                    return confirmDelete(type, id);
                }
                if (action === 'maint-box') {
                    if (inheritedMaintenance) {
                        ui.toast('warning', 'End maintenance from parent box first');
                        return;
                    }

                    return toggleMaintenance(type, id, row?.status || '');
                }

            });

            on(app, 'click', '[data-tab-link]', (e, link) => {
                e.preventDefault();
                setActiveTab(link.dataset.tabLink || 'planner');
            });


            const refreshLinksBodyDebounced = util.debounce(() => {
                renderLinksTableBodyOnly();
            }, 120);

            on(app, 'input', '#linksSearchInput', (_e, el) => {
                patchLinksTable({
                    q: el.value || '',
                    page: 1
                });
                refreshLinksBodyDebounced();
            });

            const refreshNodesBodyDebounced = util.debounce(() => {
                renderNodesTableBodyOnly();
            }, 120);

            on(app, 'input', '#nodesSearchInput', (_e, el) => {
                patchNodesTable({
                    q: el.value || '',
                    page: 1
                });
                refreshNodesBodyDebounced();
            });

            on(app, 'change', '#linksPerPageSelect', (_e, el) => {
                patchLinksTable({
                    perPage: num(el.value, 10),
                    page: 1
                });
                renderLinksTableBodyOnly();
            });

            on(app, 'click', '[data-links-page]', (_e, btn) => {
                patchLinksTable({
                    page: num(btn.dataset.linksPage, 1)
                });
                renderLinksTableBodyOnly();
            });

            on(app, 'click', '[data-links-sort]', (_e, btn) => {
                const nextKey = String(btn.dataset.linksSort || 'identification');
                const current = getLinksTableState();

                patchLinksTable({
                    sortKey: nextKey,
                    sortDir: current.sortKey === nextKey && current.sortDir === 'asc' ? 'desc' : 'asc',
                    page: 1
                });

                renderLinksTableBodyOnly();
            });

            on(app, 'change', '#nodesPerPageSelect', (_e, el) => {
                patchNodesTable({
                    perPage: num(el.value, 10),
                    page: 1
                });
                renderNodesTableBodyOnly();
            });

            on(app, 'click', '[data-nodes-page]', (_e, btn) => {
                patchNodesTable({
                    page: num(btn.dataset.nodesPage, 1)
                });
                renderNodesTableBodyOnly();
            });

            refs.odf.ports?.addEventListener('input', () => {
                populateInputPortSelect(
                    refs.odf.inputPort,
                    refs.odf.ports?.value || 0,
                    refs.odf.inputPort?.value || '',
                    'Select ODF input port'
                );
            });

            refs.lcp.ports?.addEventListener('input', () => {
                populateInputPortSelect(
                    refs.lcp.inputPort,
                    refs.lcp.ports?.value || 0,
                    refs.lcp.inputPort?.value || '',
                    'Select LCP input port'
                );
            });

            refs.odf.olt?.addEventListener('change', () => {
                loadOdfOltPorts().catch(() => clearSelect(refs.odf.oltPort, 'No OLT ports found'));
            });

            refs.lcp.parentOdf?.addEventListener('change', () => {
                populateLcpParentOdfPorts().catch(() => clearSelect(refs.lcp.parentOdfPort, 'No ODF ports found'));
            });

            refs.nap.parentType?.addEventListener('change', async () => {
                const parentType = refs.nap.parentType.value || 'LCP';

                if (refs.nap.feedMode) {
                    refs.nap.feedMode.value = upper(parentType) === 'NAP'
                        ? 'CASCADE_FROM_NAP'
                        : 'DIRECT_FROM_LCP';
                }

                await populateNapParentBoxes(parentType, null, null);
            });

            refs.nap.parentBox?.addEventListener('change', async () => {
                const parentType = refs.nap.parentType?.value || 'LCP';
                const parentBoxId = refs.nap.parentBox?.value || '';
                await populateNapParentPorts(parentType, parentBoxId, null);
            });

            refs.odf.modalEl?.addEventListener('shown.bs.modal', () => {
                openLeafletPicker('odf', refs.odf);
            });

            refs.lcp.modalEl?.addEventListener('shown.bs.modal', () => {
                openLeafletPicker('lcp', refs.lcp);
            });

            refs.nap.modalEl?.addEventListener('shown.bs.modal', () => {
                openLeafletPicker('nap', refs.nap);
            });

            document.addEventListener('nx:nap-map-open-node', (e) => {
                const type = String(e.detail?.type || '').toLowerCase();
                const id = e.detail?.id || '';

                if (!type || !id) return;

                openBoxViewer(type, id);
            });
        }
        module.define('nap-management-page', async () => {
            initFormBuilders();
            bindEvents();
            await loadAll();
            return {
                destroy() {
                    const cy = getState().planner.cy;
                    cy?.destroy?.();
                    formInstances.odf?.destroy?.();
                    formInstances.lcp?.destroy?.();
                    formInstances.nap?.destroy?.();
                    store.reset(initialState);
                }
            };
        });
        module.run('nap-management-page').catch((err) => {
            markAppReady();
            ui.swal({
                title: 'Load Error',
                text: err.message || 'Failed to load NAP Management data.',
                icon: 'error'
            });
        });
    });
})();
