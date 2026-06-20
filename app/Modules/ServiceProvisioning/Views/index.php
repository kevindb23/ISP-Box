<div class="container-fluid nx-page" id="serviceProvisioningApp">
    <link rel="stylesheet" href="/module-assets/ServiceProvisioning/css/ServiceProvisioning.css">

    <div class="sp-modern-shell">

        <!-- HERO -->
        <section class="sp-modern-hero">
            <div class="sp-modern-hero-main">
                <div class="sp-modern-icon">
                    <i class="bi bi-lightning-charge-fill"></i>
                </div>

                <div>
                    <div class="sp-modern-eyebrow">NEXUSBOX FTTH ACTIVATION</div>
                    <h3>Service Provisioning</h3>
                    <p>
                        Select existing records, validate readiness, then activate the subscriber service.
                    </p>
                </div>
            </div>

            <div class="sp-modern-actions">
                <button type="button" class="btn btn-light border" id="btnRefreshProvisioning">
                    <i class="bi bi-arrow-clockwise me-1"></i>
                    Refresh
                </button>

                <button type="button" class="btn btn-outline-primary" id="btnValidate">
                    <i class="bi bi-shield-check me-1"></i>
                    Validate
                </button>

                <button type="button" class="btn btn-primary" id="btnProvision">
                    <i class="bi bi-play-fill me-1"></i>
                    Activate
                </button>
            </div>
        </section>

        <!-- MAIN -->
        <section class="sp-modern-layout">

            <!-- LEFT -->
            <div class="sp-modern-main-card">

                <div class="sp-modern-card-head">
                    <div>
                        <span>ACTIVATION BUILDER</span>
                        <h5>Subscriber Service Setup</h5>
                    </div>
                </div>

                <!-- CUSTOMER -->
                <div class="sp-modern-section">

                    <div class="sp-modern-section-head">
                        <div class="sp-modern-section-no">01</div>

                        <div>
                            <h6>Customer</h6>
                            <p>Choose subscriber and active service plan.</p>
                        </div>
                    </div>

                    <div class="row g-3">

                        <div class="col-12 col-lg-6">
                            <label for="spSubscriber" class="form-label">
                                Subscriber
                            </label>

                            <select id="spSubscriber" class="form-select">
                                <option value="">Select subscriber</option>
                            </select>
                        </div>

                        <div class="col-12 col-lg-6">
                            <label for="spService" class="form-label">
                                Service / Plan
                            </label>

                            <select id="spService" class="form-select">
                                <option value="">Select plan</option>
                            </select>
                        </div>

                    </div>
                </div>

                <!-- ONT -->
                <div class="sp-modern-section">

                    <div class="sp-modern-section-head">
                        <div class="sp-modern-section-no">02</div>

                        <div>
                            <h6>ONT Device</h6>
                            <p>Select known ONT inventory record.</p>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <label for="spOnt" class="form-label">
                                ONT
                            </label>

                            <select id="spOnt" class="form-select">
                                <option value="">Select ONT</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- ACCESS -->
                <div class="sp-modern-section">

                    <div class="sp-modern-section-head">
                        <div class="sp-modern-section-no">03</div>

                        <div>
                            <h6>Access Network</h6>
                            <p>Choose OLT and subscriber PON path.</p>
                        </div>
                    </div>

                    <div class="row g-3">

                        <div class="col-12 col-lg-6">
                            <label for="spOlt" class="form-label">
                                OLT
                            </label>

                            <select id="spOlt" class="form-select">
                                <option value="">Select OLT</option>
                            </select>
                        </div>

                        <div class="col-12 col-lg-6">
                            <label for="spOltPort" class="form-label">
                                PON Port
                            </label>

                            <select id="spOltPort" class="form-select">
                                <option value="">Select PON port</option>
                            </select>
                        </div>

                    </div>
                </div>

                <!-- FTTH -->
                <div class="sp-modern-section">

                    <div class="sp-modern-section-head">
                        <div class="sp-modern-section-no">04</div>

                        <div>
                            <h6>FTTH Distribution</h6>
                            <p>Select NAP, splitter, and output port.</p>
                        </div>
                    </div>

                    <div class="row g-3">

                        <div class="col-12 col-lg-4">
                            <label for="spNap" class="form-label">
                                NAP Box
                            </label>

                            <select id="spNap" class="form-select">
                                <option value="">Select NAP</option>
                            </select>
                        </div>

                        <div class="col-12 col-lg-4">
                            <label for="spSplitter" class="form-label">
                                Splitter
                            </label>

                            <select id="spSplitter" class="form-select">
                                <option value="">Select splitter</option>
                            </select>
                        </div>

                        <div class="col-12 col-lg-4">
                            <label for="spSplitterPort" class="form-label">
                                Splitter Port
                            </label>

                            <select id="spSplitterPort" class="form-select">
                                <option value="">Select splitter output port</option>
                            </select>
                        </div>

                    </div>
                </div>

                <!-- ACTIONS -->
                <div class="sp-modern-form-actions">
                    <button type="button" class="btn btn-outline-primary" id="btnValidateMirror">
                        <i class="bi bi-shield-check me-1"></i>
                        Validate Readiness
                    </button>

                    <button type="button" class="btn btn-primary" id="btnProvisionMirror">
                        <i class="bi bi-play-fill me-1"></i>
                        Activate Service
                    </button>
                </div>

            </div>

            <!-- RIGHT -->
            <aside class="sp-modern-side">

                <!-- SUMMARY -->
                <div class="sp-modern-summary-card sp-modern-summary-dark">

                    <div class="sp-modern-side-head">

                        <div>
                            <span>SELECTED CHAIN</span>
                            <h5>Activation Summary</h5>
                        </div>

                        <span class="sp-poll-indicator is-idle" id="spPollIndicator">
                            <span class="sp-poll-dot"></span>
                            <span id="spPollIndicatorText">ACS IDLE</span>
                        </span>
                    </div>

                    <div class="sp-modern-summary-list">

                        <div>
                            <span>SUBSCRIBER</span>
                            <strong id="spSummarySubscriber">
                                Select subscriber
                            </strong>
                        </div>

                        <div>
                            <span>SERVICE</span>
                            <strong id="spSummaryService">
                                Select plan
                            </strong>
                        </div>

                        <div>
                            <span>ONT</span>
                            <strong id="spSummaryOnt">
                                Select ONT
                            </strong>
                        </div>

                        <div>
                            <span>OLT</span>
                            <strong id="spSummaryOlt">
                                Select OLT
                            </strong>
                        </div>

                        <div>
                            <span>PON PORT</span>
                            <strong id="spSummaryOltPort">
                                Select PON port
                            </strong>
                        </div>

                        <div>
                            <span>NAP</span>
                            <strong id="spSummaryNap">
                                Select NAP
                            </strong>
                        </div>

                        <div>
                            <span>SPLITTER</span>
                            <strong id="spSummarySplitter">
                                Select splitter
                            </strong>
                        </div>

                        <div>
                            <span>PORT</span>
                            <strong id="spSummarySplitterPort">
                                Select splitter output port
                            </strong>
                        </div>

                    </div>
                </div>

                <!-- READINESS -->
                <div class="sp-modern-summary-card">

                    <div class="sp-modern-side-head">
                        <div>
                            <span>PRE-CHECKS</span>
                            <h5>Readiness</h5>
                        </div>
                    </div>

                    <div class="sp-checklist">

                        <div class="sp-check-row is-pending" data-sp-check="customer">
                            <i class="bi bi-circle"></i>
                            <span>Subscriber and service selected</span>
                        </div>

                        <div class="sp-check-row is-pending" data-sp-check="ppp">
                            <i class="bi bi-circle"></i>
                            <span>PPP credentials resolved</span>
                        </div>

                        <div class="sp-check-row is-pending" data-sp-check="ont">
                            <i class="bi bi-circle"></i>
                            <span>ONT selected from inventory</span>
                        </div>

                        <div class="sp-check-row is-pending" data-sp-check="pon">
                            <i class="bi bi-circle"></i>
                            <span>PON port capacity checked</span>
                        </div>

                        <div class="sp-check-row is-pending" data-sp-check="splitter">
                            <i class="bi bi-circle"></i>
                            <span>Splitter output port selected</span>
                        </div>

                        <div class="sp-check-row is-pending" data-sp-check="automation">
                            <i class="bi bi-circle"></i>
                            <span>VLAN and profiles auto-resolved</span>
                        </div>

                    </div>
                </div>

            </aside>
        </section>
    </div>

    <!-- VALIDATION MODAL -->
    <div class="modal fade" id="spValidationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">

            <div class="modal-content sp-job-modal">

                <div class="modal-header">
                    <div>
                        <div class="small fw-bold text-primary text-uppercase">
                            Readiness Check
                        </div>

                        <h5 class="modal-title mb-0">
                            Validation Result
                        </h5>
                    </div>

                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div id="spValidationModalBody">
                        Preparing validation...
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">
                        Close
                    </button>
                </div>

            </div>
        </div>
    </div>

    <!-- ACTIVATION MODAL -->
    <div class="modal fade"
         id="spActivationModal"
         tabindex="-1"
         aria-hidden="true"
         data-bs-backdrop="static">

        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">

            <div class="modal-content sp-job-modal">

                <div class="modal-header">

                    <div>
                        <div class="small fw-bold text-primary text-uppercase">
                            Service Activation
                        </div>

                        <h5 class="modal-title mb-0">
                            Activation Progress
                        </h5>
                    </div>

                    <button type="button"
                            class="btn-close"
                            data-bs-dismiss="modal">
                    </button>
                </div>

                <div class="modal-body">
                    <div id="spActivationProgressBody">
                        Preparing activation...
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button"
                            class="btn btn-light border"
                            data-bs-dismiss="modal">
                        Close
                    </button>
                </div>

            </div>
        </div>
    </div>

    <script src="/module-assets/ServiceProvisioning/js/ServiceProvisioning.js"></script>
</div>