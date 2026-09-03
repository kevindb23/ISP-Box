(function () {
    document.addEventListener('DOMContentLoaded', () => {
        const app = document.getElementById('serviceProvisioningApp');
        if (!app || !window.NX) return;
        const nxApi = window.NX.api;

        const $ = (id) => document.getElementById(id);
        const $$ = (selector) => Array.from(app.querySelectorAll(selector));

        const els = {
            subscriber: $('spSubscriber'),
            service: $('spService'),
            ont: $('spOnt'),
            olt: $('spOlt'),
            oltPort: $('spOltPort'),
            nap: $('spNap'),
            splitter: $('spSplitter'),
            splitterPort: $('spSplitterPort'),

            btnValidate: $('btnValidate'),
            btnValidateMirror: $('btnValidateMirror'),
            btnRefresh: $('btnRefreshProvisioning'),
            btnProvision: $('btnProvision'),
            btnProvisionMirror: $('btnProvisionMirror'),

            logs: $('spLogs'),

            summarySubscriber: $('spSummarySubscriber'),
            summaryService: $('spSummaryService'),
            summaryOnt: $('spSummaryOnt'),
            summaryOlt: $('spSummaryOlt'),
            summaryOltPort: $('spSummaryOltPort'),
            summaryNap: $('spSummaryNap'),
            summarySplitter: $('spSummarySplitter'),
            summarySplitterPort: $('spSummarySplitterPort'),

            activationState: $('spActivationState'),

            pollIndicator: $('spPollIndicator'),
            pollIndicatorText: $('spPollIndicatorText'),

            jobsTableBody: $('spJobsTableBody'),
            jobsMeta: $('spJobsMeta')
        };

        const state = {
            subscribers: [],
            services: [],
            onts: [],
            olts: [],
            oltPorts: [],
            networkBoxes: [],
            splitters: [],
            splitterPorts: [],
            jobs: [],
            jobsPagination: {
                page: 1,
                limit: 10,
                total: 0,
                pages: 1
            },
            isBusy: false
        };

        createRuntimeModals();

        function createRuntimeModals() {
            if (!document.getElementById('spValidationModal')) {
                document.body.insertAdjacentHTML('beforeend', `
                    <div class="modal fade" id="spValidationModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                            <div class="modal-content sp-job-modal">
                                <div class="modal-header">
                                    <div>
                                        <div class="small fw-bold text-primary text-uppercase">Readiness Check</div>
                                        <h5 class="modal-title mb-0">Validation Result</h5>
                                    </div>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div id="spValidationModalBody">
                                        Preparing validation...
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `);
            }

            if (!document.getElementById('spActivationModal')) {
                document.body.insertAdjacentHTML('beforeend', `
                    <div class="modal fade" id="spActivationModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
                        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                            <div class="modal-content sp-job-modal">
                                <div class="modal-header">
                                    <div>
                                        <div class="small fw-bold text-primary text-uppercase">Service Activation</div>
                                        <h5 class="modal-title mb-0">Activation Progress</h5>
                                    </div>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div id="spActivationProgressBody"></div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `);
            }
        }

        function modalInstance(id) {
            const el = document.getElementById(id);
            if (!el || !window.bootstrap?.Modal) return null;
            return window.bootstrap.Modal.getOrCreateInstance(el);
        }

        function showModal(id) {
            const modal = modalInstance(id);
            if (modal) modal.show();
        }

        function setValidationModal(status, title, message, data = null) {
            const body = document.getElementById('spValidationModalBody');
            if (!body) return;

            const isSuccess = status === 'success';

            body.innerHTML = `
                <div class="p-3 rounded-4 border ${isSuccess ? 'bg-success-subtle border-success-subtle' : 'bg-danger-subtle border-danger-subtle'}">
                    <div class="d-flex align-items-start gap-3">
                        <div class="fs-3 ${isSuccess ? 'text-success' : 'text-danger'}">
                            <i class="bi ${isSuccess ? 'bi-check-circle-fill' : 'bi-x-circle-fill'}"></i>
                        </div>
                        <div>
                            <h5 class="mb-1">${escapeHtml(title)}</h5>
                            <p class="mb-0 text-muted">${escapeHtml(message)}</p>
                        </div>
                    </div>
                </div>

                ${data ? `
                    <div class="mt-3">
                        <div class="small fw-bold text-uppercase text-muted mb-2">Validation Details</div>
                        <pre class="sp-job-logs-content mb-0">${escapeHtml(prettyJson(data))}</pre>
                    </div>
                ` : ''}
            `;
        }

        function setActivationModal(title, stepsHtml, payload = null) {
            const body = document.getElementById('spActivationProgressBody');
            if (!body) return;

            body.innerHTML = `
                <div class="mb-3">
                    <h5 class="mb-1">${escapeHtml(title)}</h5>
                    <p class="text-muted mb-0">Follow the provisioning stages below.</p>
                </div>

                <div class="sp-activation-modal-steps">
                    ${stepsHtml}
                </div>

                ${payload ? `
                    <div class="mt-3">
                        <div class="small fw-bold text-uppercase text-muted mb-2">Latest Response</div>
                        <pre class="sp-job-logs-content mb-0">${escapeHtml(prettyJson(payload))}</pre>
                    </div>
                ` : ''}
            `;
        }

        function progressStep(label, status, message = '') {
            let icon = 'bi-circle';
            let color = 'text-muted';

            if (status === 'active') {
                icon = 'bi-arrow-repeat';
                color = 'text-primary';
            }

            if (status === 'success') {
                icon = 'bi-check-circle-fill';
                color = 'text-success';
            }

            if (status === 'failed') {
                icon = 'bi-x-circle-fill';
                color = 'text-danger';
            }

            if (status === 'warning') {
                icon = 'bi-exclamation-circle-fill';
                color = 'text-warning';
            }

            return `
                <div class="d-flex align-items-start gap-3 p-3 mb-2 rounded-4 border bg-white">
                    <div class="fs-4 ${color}">
                        <i class="bi ${icon}"></i>
                    </div>
                    <div>
                        <div class="fw-bold">${escapeHtml(label)}</div>
                        <div class="small text-muted">${escapeHtml(message || status)}</div>
                    </div>
                </div>
            `;
        }

        function renderActivationProgress(activeStage, payload = null, error = '') {
            const failed = !!error;

            setActivationModal(
                failed ? 'Activation Failed' : 'Activation in Progress',
                [
                    progressStep('Local Readiness', failed && activeStage === 'validation' ? 'failed' : 'success', failed && activeStage === 'validation' ? error : 'Selection payload passed local checks.'),
                    progressStep('Create Provisioning Job', stageStatus(activeStage, 'job', failed), activeStage === 'job' && failed ? error : 'Creating job record.'),
                    progressStep('OLT Provisioning', stageStatus(activeStage, 'olt', failed), activeStage === 'olt' && failed ? error : 'Running Huawei OLT provisioning script.'),
                    progressStep('ACS Verification', stageStatus(activeStage, 'acs', failed), activeStage === 'acs' && failed ? error : 'Checking ONT appearance in ACS.'),
                    progressStep('Completion', stageStatus(activeStage, 'complete', failed), activeStage === 'complete' && failed ? error : 'Final activation state.')
                ].join(''),
                payload
            );
        }

        function stageStatus(activeStage, stage, failed) {
            const order = ['validation', 'job', 'olt', 'acs', 'complete'];
            const activeIndex = order.indexOf(activeStage);
            const stageIndex = order.indexOf(stage);

            if (failed && activeStage === stage) return 'failed';
            if (stageIndex < activeIndex) return 'success';
            if (stageIndex === activeIndex) return 'active';

            return 'pending';
        }

        function toast(icon, title) {
            if (window.NX?.ui?.toast) {
                window.NX.ui.toast(icon, title);
                return;
            }

            if (window.Swal) {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon,
                    title,
                    showConfirmButton: false,
                    timer: 2500,
                    timerProgressBar: true
                });
                return;
            }

            console.log(icon, title);
        }

        function log(message, reset = false) {
            if (!els.logs) return;

            const value = String(message ?? '');

            if (reset) {
                els.logs.textContent = value;
            } else {
                els.logs.textContent += `${els.logs.textContent ? '\n' : ''}${value}`;
            }

            els.logs.scrollTop = els.logs.scrollHeight;
        }

        function clearLogs(message = 'Waiting for validation...') {
            log(message, true);
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function prettyJson(value) {
            if (value == null) return '-';

            if (typeof value === 'string') {
                try {
                    return JSON.stringify(JSON.parse(value), null, 2);
                } catch (_) {
                    return value;
                }
            }

            try {
                return JSON.stringify(value, null, 2);
            } catch (_) {
                return String(value ?? '');
            }
        }

        function currentText(el) {
            return el?.selectedOptions?.[0]?.textContent?.trim() || '';
        }

        function setSummary(el, value) {
            if (!el) return;
            const next = String(value || '').trim();
            el.textContent = next || '-';
        }

        function setActivationState(value) {
            if (!els.activationState) return;
            els.activationState.textContent = value || 'Ready';
        }

        function setBusy(isBusy) {
            state.isBusy = !!isBusy;

            [
                els.subscriber,
                els.service,
                els.ont,
                els.olt,
                els.oltPort,
                els.nap,
                els.splitter,
                els.splitterPort,
                els.btnValidate,
                els.btnValidateMirror,
                els.btnProvision,
                els.btnProvisionMirror,
                els.btnRefresh
            ].forEach((el) => {
                if (el) el.disabled = state.isBusy;
            });
        }

        function asArray(data) {
            if (Array.isArray(data)) return data;
            if (Array.isArray(data?.items)) return data.items;
            return [];
        }

        async function apiGet(url) {
            return (await nxApi.get(url)) ?? [];
        }

        async function apiPost(url, payload = {}) {
            const result = await nxApi.post(url, payload);
            return result.data ?? [];
        }

        function fillSelect(el, items, labelFn, placeholder = 'Select...', valueFn = null) {
            if (!el) return;

            let html = `<option value="">${escapeHtml(placeholder)}</option>`;

            html += (items || []).map((item) => {
                const value = valueFn ? valueFn(item) : (item.id ?? '');
                const label = labelFn(item);

                return `<option value="${escapeHtml(value)}">${escapeHtml(label)}</option>`;
            }).join('');

            el.innerHTML = html;
        }

        function resetSelect(el, placeholder = 'Select...') {
            if (!el) return;
            el.innerHTML = `<option value="">${escapeHtml(placeholder)}</option>`;
        }

        function getSelectedServiceRow() {
            const selectedPlanId = Number(els.service?.value || 0);

            return state.services.find((row) => {
                return Number(row.plan_id || row.id || 0) === selectedPlanId;
            }) || null;
        }

        function getSelectedOltPortRow() {
            const selectedId = Number(els.oltPort?.value || 0);
            return state.oltPorts.find((row) => Number(row.id || 0) === selectedId) || null;
        }

        function formatServiceLabel(row) {
            if (!row) return '-';

            const speed = row.speed_mbps
                ? `${row.speed_mbps} Mbps`
                : `${row.speed_down || 0}/${row.speed_up || 0}`;

            const bits = [
                row.plan_name || 'Unnamed Plan',
                speed
            ];

            if (row.price) bits.push(`₱${row.price}`);
            if (row.plan_type) bits.push(row.plan_type);

            bits.push(row.service_id ? 'Existing service' : 'New service');

            return bits.join(' · ');
        }

        function formatOltPortLabel(row) {
            if (!row) return currentText(els.oltPort);

            const label = row.label || `${row.frame ?? 0}/${row.slot ?? 0}/${row.port ?? 0}`;
            const count = Number(row.ont_count || 0);

            return `${label} · ${count}/16 ONTs`;
        }

        function updateSelectionSummary() {
            const serviceRow = getSelectedServiceRow();
            const oltPortRow = getSelectedOltPortRow();

            setSummary(els.summarySubscriber, currentText(els.subscriber));
            setSummary(els.summaryService, serviceRow ? formatServiceLabel(serviceRow) : currentText(els.service));
            setSummary(els.summaryOnt, currentText(els.ont));
            setSummary(els.summaryOlt, currentText(els.olt));
            setSummary(els.summaryOltPort, oltPortRow ? formatOltPortLabel(oltPortRow) : currentText(els.oltPort));
            setSummary(els.summaryNap, currentText(els.nap));
            setSummary(els.summarySplitter, currentText(els.splitter));
            setSummary(els.summarySplitterPort, currentText(els.splitterPort));

            updateChecklist();
        }

        function setCheck(key, stateName) {
            const row = app.querySelector(`[data-sp-check="${key}"]`);
            if (!row) return;

            row.classList.remove('is-complete', 'is-pending', 'is-error');
            row.classList.add(`is-${stateName}`);

            const icon = row.querySelector('i');
            if (!icon) return;

            if (stateName === 'complete') {
                icon.className = 'bi bi-check-circle-fill';
                return;
            }

            if (stateName === 'error') {
                icon.className = 'bi bi-x-circle-fill';
                return;
            }

            icon.className = 'bi bi-circle';
        }

        function updateChecklist() {
            const serviceRow = getSelectedServiceRow();
            const portRow = getSelectedOltPortRow();

            setCheck('customer', els.subscriber?.value && els.service?.value ? 'complete' : 'pending');
            setCheck('ppp', els.subscriber?.value && serviceRow ? 'complete' : 'pending');
            setCheck('ont', els.ont?.value ? 'complete' : 'pending');

            setCheck(
                'pon',
                els.oltPort?.value && (!portRow || Number(portRow.ont_count || 0) < 16)
                    ? 'complete'
                    : 'pending'
            );

            setCheck('splitter', els.splitterPort?.value ? 'complete' : 'pending');
            setCheck('automation', els.service?.value && els.oltPort?.value ? 'complete' : 'pending');
        }

        function resetTimeline() {
            ['validation', 'job', 'olt', 'acs', 'complete'].forEach((stage) => {
                const item = app.querySelector(`[data-sp-stage="${stage}"]`);
                if (!item) return;

                item.classList.remove('is-active', 'is-success', 'is-failed');
                item.classList.add('is-pending');
            });
        }

        function setTimeline(stage, status, message = '') {
            const item = app.querySelector(`[data-sp-stage="${stage}"]`);
            if (!item) return;

            item.classList.remove('is-pending', 'is-active', 'is-success', 'is-failed');
            item.classList.add(`is-${status}`);

            const text = item.querySelector('span');
            if (text && message) text.textContent = message;
        }

        function getPayload() {
            const serviceRow = getSelectedServiceRow();
            const ontText = currentText(els.ont);
            const ontSerial = ontText ? ontText.split(' · ')[0].trim() : '';

            return {
                subscriber_id: Number(els.subscriber?.value || 0),
                plan_id: Number(serviceRow?.plan_id || serviceRow?.id || els.service?.value || 0),
                service_id: Number(serviceRow?.service_id || 0),

                ont_id: Number(els.ont?.value || 0),
                ont_serial: ontSerial,

                olt_id: Number(els.olt?.value || 0),
                olt_port_id: Number(els.oltPort?.value || 0),

                network_box_id: Number(els.nap?.value || 0),
                splitter_id: Number(els.splitter?.value || 0),
                splitter_output_port_id: Number(els.splitterPort?.value || 0),

                provision_mode: 'FULL_AUTO'
            };
        }

        function validateLocalPayload(payload) {
            if (!payload.subscriber_id) return 'Subscriber is required.';
            if (!payload.plan_id) return 'Plan is required.';
            if (!payload.ont_id) return 'ONT is required.';
            if (!payload.ont_serial) return 'ONT serial is required.';
            if (!payload.olt_id) return 'OLT is required.';
            if (!payload.olt_port_id) return 'PON port is required.';
            if (!payload.network_box_id) return 'NAP box is required.';
            if (!payload.splitter_id) return 'Splitter is required.';
            if (!payload.splitter_output_port_id) return 'Splitter output port is required.';

            const port = getSelectedOltPortRow();
            if (port && Number(port.ont_count || 0) >= 16) {
                return 'Selected PON port is already full.';
            }

            return '';
        }

        async function loadSubscribers() {
            const data = await apiGet('/api/v1/service-provisioning/support/subscribers');
            state.subscribers = asArray(data);

            fillSelect(
                els.subscriber,
                state.subscribers,
                (d) => `${d.full_name || 'Unnamed'} · ${d.account_number || d.id}`,
                'Select subscriber',
                (d) => d.id
            );
        }

        async function loadServices(subscriberId = 0) {
            const qs = new URLSearchParams();

            if (subscriberId > 0) {
                qs.set('subscriber_id', String(subscriberId));
            }

            const url = `/api/v1/service-provisioning/support/plans${qs.toString() ? `?${qs.toString()}` : ''}`;
            const data = await apiGet(url);

            state.services = asArray(data);

            fillSelect(
                els.service,
                state.services,
                (d) => formatServiceLabel(d),
                'Select plan',
                (d) => d.plan_id || d.id
            );
        }

        async function loadOnts() {
            const data = await apiGet('/api/v1/service-provisioning/support/onts');
            state.onts = asArray(data);

            fillSelect(
                els.ont,
                state.onts,
                (d) => `${d.serial_number || 'Unknown Serial'} · ${d.model || d.vendor || 'ONT'}`,
                'Select ONT',
                (d) => d.id
            );
        }

        async function loadOlts() {
            const data = await apiGet('/api/v1/service-provisioning/support/olts');
            state.olts = asArray(data);

            fillSelect(
                els.olt,
                state.olts,
                (d) => `${d.name || 'Unnamed OLT'}${d.ip_address ? ` · ${d.ip_address}` : ''}`,
                'Select OLT',
                (d) => d.id
            );
        }

        async function loadOltPorts(oltId) {
            if (!oltId) {
                state.oltPorts = [];
                resetSelect(els.oltPort, 'Select PON port');
                return;
            }

            const data = await apiGet(`/api/v1/service-provisioning/support/olt-ports?olt_id=${encodeURIComponent(oltId)}`);
            state.oltPorts = asArray(data);

            fillSelect(
                els.oltPort,
                state.oltPorts,
                (d) => formatOltPortLabel(d),
                'Select PON port',
                (d) => d.id
            );
        }

        async function loadNetworkBoxes(oltId, oltPortId) {
            if (!oltId || !oltPortId) {
                state.networkBoxes = [];
                resetSelect(els.nap, 'Select NAP');
                return;
            }

            const qs = new URLSearchParams({
                olt_id: String(oltId),
                olt_port_id: String(oltPortId),
                box_type: 'NAP'
            });

            const data = await apiGet(`/api/v1/service-provisioning/support/network-boxes?${qs.toString()}`);
            state.networkBoxes = asArray(data);

            fillSelect(
                els.nap,
                state.networkBoxes,
                (d) => d.box_name || d.box_code || `NAP ${d.id}`,
                'Select NAP',
                (d) => d.id
            );
        }

        async function loadSplitters(networkBoxId) {
            if (!networkBoxId) {
                state.splitters = [];
                resetSelect(els.splitter, 'Select splitter');
                return;
            }

            const data = await apiGet(`/api/v1/service-provisioning/support/splitters?network_box_id=${encodeURIComponent(networkBoxId)}`);
            state.splitters = asArray(data);

            fillSelect(
                els.splitter,
                state.splitters,
                (d) => `${d.splitter_model || 'Splitter'} · 1:${d.splitter_ratio || '-'}`,
                'Select splitter',
                (d) => d.id
            );
        }

        async function loadSplitterPorts(splitterId) {
            if (!splitterId) {
                state.splitterPorts = [];
                resetSelect(els.splitterPort, 'Select splitter output port');
                return;
            }

            const data = await apiGet(`/api/v1/service-provisioning/support/splitter-output-ports?splitter_id=${encodeURIComponent(splitterId)}`);

            state.splitterPorts = asArray(data).filter(
                (d) => String(d.status || '').toUpperCase() === 'AVAILABLE'
            );

            fillSelect(
                els.splitterPort,
                state.splitterPorts,
                (d) => `Port ${d.port_number}${d.reserved_label ? ` · ${d.reserved_label}` : ''}`,
                'Select splitter output port',
                (d) => d.id
            );
        }

        async function handleValidate() {
            if (state.isBusy) return;

            showModal('spValidationModal');
            setValidationModal('info', 'Validating...', 'Checking subscriber, plan, ONT, OLT path, VLANs, and profiles.');

            try {
                setBusy(true);
                resetTimeline();
                setActivationState('Running validation');
                setTimeline('validation', 'active', 'Running validation');
                clearLogs('Validating activation records...');

                const payload = getPayload();
                const localError = validateLocalPayload(payload);

                if (localError) throw new Error(localError);

                const result = await apiPost('/api/v1/service-provisioning/validate', payload);

                setTimeline('validation', 'success', 'Validation passed');
                setActivationState('Validation successful');

                log('Validation successful.');
                log(prettyJson(result));

                setValidationModal(
                    'success',
                    'Validation Successful',
                    'This subscriber service is ready for activation.',
                    result
                );

                toast('success', 'Validation successful.');
            } catch (e) {
                setTimeline('validation', 'failed', e.message);
                setActivationState('Validation failed');

                log(`ERROR: ${e.message}`);
                if (e.response) log(prettyJson(e.response));

                setValidationModal(
                    'error',
                    'Validation Failed',
                    e.message || 'Validation failed.',
                    e.response || null
                );

                toast('error', e.message || 'Validation failed.');
            } finally {
                setBusy(false);
            }
        }

        async function handleProvision() {
            if (state.isBusy) return;

            showModal('spActivationModal');
            renderActivationProgress('validation');

            try {
                setBusy(true);
                resetTimeline();
                setActivationState('Creating provisioning job');
                clearLogs('Creating provisioning job...');

                const payload = getPayload();
                const localError = validateLocalPayload(payload);

                if (localError) throw new Error(localError);

                setTimeline('validation', 'success', 'Local readiness passed');
                renderActivationProgress('job', payload);

                const created = await apiPost('/api/v1/service-provisioning/create', payload);
                const jobId = Number(created.job_id || created.id || 0);

                if (!jobId) {
                    throw new Error('Provisioning job was created but job_id was not returned.');
                }

                setTimeline('job', 'success', `Job #${jobId} created`);
                log(`Provisioning job created: #${jobId}`);

                renderActivationProgress('olt', created);

                setTimeline('olt', 'active', 'Running OLT provisioning');
                setActivationState('Running OLT provisioning');

                const runResult = await apiPost('/api/v1/service-provisioning/run', { job_id: jobId });

                log('OLT provisioning result:');
                log(prettyJson(runResult));

                setTimeline('olt', 'success', 'OLT provisioning completed');
                setTimeline('acs', 'active', 'Checking ACS');
                setActivationState('Checking ACS');

                renderActivationProgress('acs', runResult);

                let acs = await apiPost(`/api/v1/service-provisioning/check-acs/${jobId}`, {});

                for (let attempt = 1; !acs?.acs_found && attempt <= 18; attempt += 1) {
                    setTimeline('acs', 'active', `Waiting for ACS (${attempt}/18)`);
                    setActivationState(`Waiting for ACS · check ${attempt}/18`);
                    log(`ONT not visible in ACS. Next check ${attempt}/18 in 10 seconds.`);
                    await new Promise((resolve) => window.setTimeout(resolve, 10000));
                    acs = await apiPost(`/api/v1/service-provisioning/check-acs/${jobId}`, {});
                }

                log('ACS check result:');
                log(prettyJson(acs));

                if (acs?.acs_found) {
                    setTimeline('acs', 'success', 'ACS device detected');
                    setTimeline('complete', 'success', 'Provisioning completed');
                    setActivationState('Provisioning completed');

                    setActivationModal(
                        'Activation Completed',
                        [
                            progressStep('Local Readiness', 'success', 'Selection payload passed local checks.'),
                            progressStep('Create Provisioning Job', 'success', `Job #${jobId} created.`),
                            progressStep('OLT Provisioning', 'success', 'OLT provisioning completed.'),
                            progressStep('ACS Verification', 'success', 'ACS device detected.'),
                            progressStep('Completion', 'success', 'Service provisioning completed.')
                        ].join(''),
                        acs
                    );

                    toast('success', 'Provisioning completed.');
                } else {
                    setTimeline('acs', 'active', 'Waiting for ACS appearance');
                    setActivationState('Waiting for ACS appearance');

                    setActivationModal(
                        'Activation Waiting for ACS',
                        [
                            progressStep('Local Readiness', 'success', 'Selection payload passed local checks.'),
                            progressStep('Create Provisioning Job', 'success', `Job #${jobId} created.`),
                            progressStep('OLT Provisioning', 'success', 'OLT provisioning completed.'),
                            progressStep('ACS Verification', 'warning', 'ONT is not yet visible in ACS.'),
                            progressStep('Completion', 'active', 'Waiting for ONT ACS appearance.')
                        ].join(''),
                        acs
                    );

                    toast('success', 'Provisioning completed. Waiting for ACS.');
                }

                if (els.jobsTableBody) {
                    await loadJobs(1);
                }
            } catch (e) {
                setTimeline('complete', 'failed', e.message);
                setActivationState('Provisioning failed');

                log(`ERROR: ${e.message}`);
                if (e.response) log(prettyJson(e.response));

                renderActivationProgress('complete', e.response || null, e.message || 'Provisioning failed.');

                toast('error', e.message || 'Provisioning failed.');
            } finally {
                setBusy(false);
            }
        }

        async function loadJobs(page = 1) {
            if (!els.jobsTableBody) return;

            try {
                const qs = new URLSearchParams({
                    page: String(page),
                    limit: String(state.jobsPagination.limit)
                });

                const data = await apiGet(`/api/v1/service-provisioning/list?${qs.toString()}`);

                state.jobs = asArray(data);
                state.jobsPagination = { ...state.jobsPagination, ...(data?.pagination || {}), page };
                renderJobs();
            } catch (e) {
                els.jobsTableBody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">Unable to load provisioning jobs.</td></tr>';
                if (els.jobsMeta) els.jobsMeta.textContent = e.message || 'Request failed.';
            }
        }

        function renderJobs() {
            if (!els.jobsTableBody) return;
            if (!state.jobs.length) {
                els.jobsTableBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No provisioning jobs found.</td></tr>';
            } else {
                els.jobsTableBody.innerHTML = state.jobs.map((job) => {
                    const status = String(job.job_status || '').toUpperCase();
                    const stage = String(job.current_stage || '').toUpperCase();
                    let action = '<span class="text-muted small">—</span>';
                    if (status === 'READY' && stage === 'CREATED') {
                        action = `<button class="btn btn-sm btn-outline-danger" data-job-action="cancel" data-job-id="${Number(job.id)}">Cancel</button>`;
                    } else if (status === 'VERIFYING' && ['WAITING_FOR_ACS', 'ACS_PUSH_FAILED'].includes(stage)) {
                        action = `<button class="btn btn-sm btn-outline-primary" data-job-action="check-acs" data-job-id="${Number(job.id)}">Check ACS</button>`;
                    } else if (status === 'FAILED' && stage === 'OLT_PROVISIONING') {
                        action = `<button class="btn btn-sm btn-outline-warning" data-job-action="retry" data-job-id="${Number(job.id)}">Retry OLT</button>`;
                    }
                    return `
                    <tr>
                        <td><strong>${escapeHtml(job.job_no || `#${job.id}`)}</strong><div class="small text-muted">#${escapeHtml(job.id || '')}</div></td>
                        <td>${escapeHtml(job.subscriber_name || 'Unknown subscriber')}<div class="small text-muted">${escapeHtml(job.service_number || job.ppp_username || 'No service')}</div></td>
                        <td>${escapeHtml(job.olt_name || 'Not assigned')}<div class="small text-muted">${escapeHtml(job.olt_port_label || 'No PON')}</div></td>
                        <td>${escapeHtml(job.ont_serial || 'Not assigned')}</td>
                        <td>C ${escapeHtml(job.cvlan ?? '—')} / S ${escapeHtml(job.svlan ?? '—')}</td>
                        <td><span class="badge text-bg-${status === 'SUCCESS' ? 'success' : (status === 'FAILED' ? 'danger' : 'warning')}">${escapeHtml(job.job_status || 'UNKNOWN')}</span><div class="small text-muted mt-1">${escapeHtml(job.current_stage || '—')}</div></td>
                        <td>${escapeHtml(job.created_at || '—')}</td>
                        <td class="text-end text-nowrap">${action}</td>
                    </tr>`;
                }).join('');
            }
            if (els.jobsMeta) {
                const p = state.jobsPagination;
                els.jobsMeta.textContent = `${Number(p.total || state.jobs.length)} jobs · page ${Number(p.page || 1)} of ${Number(p.pages || 1)}`;
            }
        }

        async function handleJobAction(button) {
            const jobId = Number(button.dataset.jobId || 0);
            const action = String(button.dataset.jobAction || '');
            if (!jobId || !action || state.isBusy) return;
            if (action === 'cancel' && !window.confirm(`Cancel provisioning job #${jobId} and release its reserved resources?`)) return;

            try {
                setBusy(true);
                button.disabled = true;
                const endpoint = action === 'check-acs'
                    ? `/api/v1/service-provisioning/check-acs/${jobId}`
                    : `/api/v1/service-provisioning/${action}/${jobId}`;
                const result = await apiPost(endpoint, {});
                log(`${action.toUpperCase()} result for job #${jobId}:`);
                log(prettyJson(result));
                toast(result?.acs_found === false ? 'warning' : 'success', result?.message || 'Job updated.');
                await loadJobs(state.jobsPagination.page || 1);
            } catch (e) {
                toast('error', e.message || 'Job action failed.');
            } finally {
                setBusy(false);
            }
        }

        async function loadAll() {
            clearLogs('Loading provisioning workspace...');
            resetTimeline();

            await Promise.all([
                loadSubscribers(),
                loadServices(0),
                loadOnts(),
                loadOlts(),
                loadJobs(1)
            ]);

            resetSelect(els.oltPort, 'Select PON port');
            resetSelect(els.nap, 'Select NAP');
            resetSelect(els.splitter, 'Select splitter');
            resetSelect(els.splitterPort, 'Select splitter output port');

            updateSelectionSummary();
            setActivationState('Ready for selection');
            log('Provisioning workspace ready.');
        }

        function bindEvents() {
            els.jobsTableBody?.addEventListener('click', (event) => {
                const button = event.target.closest('[data-job-action]');
                if (button) handleJobAction(button);
            });
            els.subscriber?.addEventListener('change', async () => {
                await loadServices(Number(els.subscriber.value || 0));
                updateSelectionSummary();
            });

            els.service?.addEventListener('change', updateSelectionSummary);
            els.ont?.addEventListener('change', updateSelectionSummary);

            els.olt?.addEventListener('change', async () => {
                await loadOltPorts(Number(els.olt.value || 0));

                resetSelect(els.nap, 'Select NAP');
                resetSelect(els.splitter, 'Select splitter');
                resetSelect(els.splitterPort, 'Select splitter output port');

                updateSelectionSummary();
            });

            els.oltPort?.addEventListener('change', async () => {
                await loadNetworkBoxes(
                    Number(els.olt.value || 0),
                    Number(els.oltPort.value || 0)
                );

                resetSelect(els.splitter, 'Select splitter');
                resetSelect(els.splitterPort, 'Select splitter output port');

                updateSelectionSummary();
            });

            els.nap?.addEventListener('change', async () => {
                await loadSplitters(Number(els.nap.value || 0));
                resetSelect(els.splitterPort, 'Select splitter output port');
                updateSelectionSummary();
            });

            els.splitter?.addEventListener('change', async () => {
                await loadSplitterPorts(Number(els.splitter.value || 0));
                updateSelectionSummary();
            });

            els.splitterPort?.addEventListener('change', updateSelectionSummary);

            els.btnValidate?.addEventListener('click', handleValidate);
            els.btnValidateMirror?.addEventListener('click', handleValidate);

            els.btnProvision?.addEventListener('click', handleProvision);
            els.btnProvisionMirror?.addEventListener('click', handleProvision);

            els.btnRefresh?.addEventListener('click', async () => {
                try {
                    setBusy(true);
                    await loadAll();
                    toast('success', 'Provisioning workspace refreshed.');
                } catch (e) {
                    toast('error', e.message || 'Refresh failed.');
                } finally {
                    setBusy(false);
                }
            });
        }

        bindEvents();

        loadAll().catch((e) => {
            console.error(e);
            clearLogs(`ERROR: ${e.message}`);
            toast('error', e.message || 'Failed to load Service Provisioning.');
        });
    });
})();
