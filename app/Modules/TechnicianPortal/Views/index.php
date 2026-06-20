<div class="container-fluid nx-page technician-portal-page" data-technician-portal-page="index">

    <link rel="stylesheet" href="/module-assets/TechnicianPortal/css/TechnicianPortal.css">

    <div id="technicianPortalAlert"></div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="text-primary small fw-bold text-uppercase">
                    <i class="bi bi-tools"></i>
                    Technician Portal
                </div>
                <h5 class="mb-0 fw-semibold">My Work Orders</h5>
                <small class="text-muted">View assigned jobs, check in, start work, and submit completion notes.</small>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <button id="techTimeInBtn" class="btn btn-success">
                    <i class="bi bi-box-arrow-in-right"></i>
                    Time In
                </button>

                <button id="techTimeOutBtn" class="btn btn-outline-danger">
                    <i class="bi bi-box-arrow-right"></i>
                    Time Out
                </button>

                <button id="techRefreshBtn" class="btn btn-light border">
                    <i class="bi bi-arrow-clockwise"></i>
                    Refresh
                </button>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Assigned</div>
                    <div class="fs-4 fw-bold" id="techAssignedCount">0</div>
                    <div class="small text-muted">Pending jobs</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">In Progress</div>
                    <div class="fs-4 fw-bold" id="techProgressCount">0</div>
                    <div class="small text-muted">Active jobs</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Completed</div>
                    <div class="fs-4 fw-bold" id="techCompletedCount">0</div>
                    <div class="small text-muted">Finished jobs</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Attendance</div>
                    <div class="fs-6 fw-bold" id="techAttendanceStatus">OFF DUTY</div>
                    <div class="small text-muted" id="techAttendanceMeta">Not timed in</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h6 class="mb-0 fw-semibold">Assigned Work Orders</h6>
                <small class="text-muted">Click view to open work order details.</small>
            </div>

            <select id="techStatusFilter" class="form-select form-select-sm" style="width: 190px;">
                <option value="">All Status</option>
                <option value="ASSIGNED">Assigned</option>
                <option value="IN_PROGRESS">In Progress</option>
                <option value="COMPLETED">Completed</option>
                <option value="FAILED">Failed</option>
                <option value="CANCELLED">Cancelled</option>
            </select>
        </div>

        <div class="card-body">
            <div id="techWorkOrdersHost">
                <div class="text-muted text-center py-4">Loading work orders...</div>
            </div>
        </div>
    </div>

    <div class="modal fade nx-modal" id="techWorkOrderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
            <div class="modal-content nx-modal-content">
                <div class="modal-header nx-modal-header">
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <h5 class="modal-title mb-0" id="techModalTitle">Work Order Details</h5>
                            <span id="techModalStatus"></span>
                        </div>
                        <small class="text-muted" id="techModalSubtitle">Loading...</small>
                    </div>

                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body nx-modal-body">
                    <div id="techModalLoading" class="text-center text-muted py-5">
                        <div class="spinner-border spinner-border-sm me-2"></div>
                        Loading work order...
                    </div>

                    <div id="techModalContent" class="d-none">
                        <div class="row g-3 mb-3">
                            <div class="col-12 col-xl-8">
                                <div class="tech-detail-card h-100">
                                    <div class="tech-detail-label">Job Information</div>

                                    <div class="row g-2 mt-2">
                                        <div class="col-12">
                                            <div class="text-muted small">Title</div>
                                            <div class="fw-semibold" id="techWoTitle">-</div>
                                        </div>

                                        <div class="col-12">
                                            <div class="text-muted small">Description</div>
                                            <div id="techWoDescription">-</div>
                                        </div>

                                        <div class="col-6">
                                            <div class="text-muted small">Scheduled Date</div>
                                            <div id="techWoDate">-</div>
                                        </div>

                                        <div class="col-6">
                                            <div class="text-muted small">Scheduled Time</div>
                                            <div id="techWoTime">-</div>
                                        </div>

                                        <div class="col-6">
                                            <div class="text-muted small">Ticket</div>
                                            <div id="techWoTicket">-</div>
                                        </div>

                                        <div class="col-6">
                                            <div class="text-muted small">Service</div>
                                            <div id="techWoService">-</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-xl-4">
                                <div class="tech-detail-card h-100">
                                    <div class="tech-detail-label">Subscriber</div>

                                    <div class="mt-2">
                                        <div class="fw-semibold" id="techSubscriberName">-</div>
                                        <div class="small text-muted" id="techSubscriberAccount">-</div>
                                    </div>

                                    <hr>

                                    <div class="mb-2">
                                        <div class="text-muted small">Contact</div>
                                        <div id="techSubscriberContact">-</div>
                                    </div>

                                    <div class="mb-2">
                                        <div class="text-muted small">Email</div>
                                        <div id="techSubscriberEmail">-</div>
                                    </div>

                                    <div>
                                        <div class="text-muted small">Address</div>
                                        <div id="techSubscriberAddress">-</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-xl-8">
                                <div class="tech-detail-card h-100">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0 fw-semibold">Notes</h6>
                                        <small class="text-muted" id="techNotesCount">0 notes</small>
                                    </div>

                                    <div id="techNotesList">
                                        <div class="text-muted text-center py-3">No notes yet.</div>
                                    </div>

                                    <form id="techAddNoteForm" class="mt-3">
                                        <input type="hidden" name="work_order_id" id="techNoteWorkOrderId">

                                        <label class="form-label">Add Note</label>
                                        <textarea name="note" class="form-control mb-2" rows="3" required></textarea>

                                        <button type="submit" class="btn btn-sm btn-outline-primary" id="techAddNoteSubmitBtn">
                                            Add Note
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <div class="col-12 col-xl-4">
                                <div class="tech-detail-card h-100">
                                    <h6 class="fw-semibold mb-3">Actions</h6>

                                    <input type="hidden" id="techSelectedWorkOrderId">

                                    <button type="button" class="btn btn-outline-primary w-100 mb-2" id="techCheckInBtn">
                                        <i class="bi bi-geo-alt"></i>
                                        GPS Check-in
                                    </button>

                                    <button type="button" class="btn btn-primary w-100 mb-2" id="techStartWorkBtn">
                                        <i class="bi bi-play-circle"></i>
                                        Start Work
                                    </button>

                                    <form id="techCompleteWorkForm">
                                        <input type="hidden" name="work_order_id" id="techCompleteWorkOrderId">

                                        <label class="form-label">Completion Notes</label>
                                        <textarea name="completion_notes"
                                                  class="form-control mb-2"
                                                  rows="4"
                                                  placeholder="What was done?"
                                                  required></textarea>

                                        <button type="submit" class="btn btn-success w-100" id="techCompleteSubmitBtn">
                                            <i class="bi bi-check-circle"></i>
                                            Complete Work
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer nx-modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="/module-assets/TechnicianPortal/js/TechnicianPortal.js"></script>
</div>