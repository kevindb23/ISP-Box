<?php $p=BASE_PATH.'/public/build-next/.vite/manifest.json';$m=is_file($p)?(json_decode((string)file_get_contents($p),true)?:[]):[];$e=$m['src/main.ts']??[];$v=is_file($p)?(string)filemtime($p):(string)time();foreach(($e['css']??[])as$c):?><link rel="stylesheet" href="/build-next/<?=htmlspecialchars(ltrim((string)$c,'/'),ENT_QUOTES,'UTF-8')?>?v=<?=htmlspecialchars($v,ENT_QUOTES,'UTF-8')?>"><?php endforeach;?><div class="container-fluid nx-page" data-nx-next-root="tickets"></div><?php if(!empty($e['file'])):?><script type="module" src="/build-next/<?=htmlspecialchars(ltrim((string)$e['file'],'/'),ENT_QUOTES,'UTF-8')?>?v=<?=htmlspecialchars($v,ENT_QUOTES,'UTF-8')?>"></script><?php else:?><div class="alert alert-warning">The tickets interface is not built.</div><?php endif;return;?>
<div class="container-fluid nx-page tickets-page" data-tickets-page="index">
    <div id="ticketsAlert"></div>

    <div class="card border-0 shadow-sm mb-3 nx-page-header-card">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="text-primary small fw-bold text-uppercase">
                    <i class="bi bi-ticket-detailed"></i>
                    Support Operations
                </div>
                <h5 class="mb-0 fw-semibold">Tickets</h5>
                <small class="text-muted">Manage subscriber concerns, replies, status, and assignments.</small>
            </div>

            <button id="ticketsRefreshBtn" class="btn btn-light border">
                <i class="bi bi-arrow-clockwise"></i>
                Refresh
            </button>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Open</div>
                    <div class="fs-4 fw-bold" id="ticketsOpenCount">0</div>
                    <div class="small text-muted">New tickets</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">In Progress</div>
                    <div class="fs-4 fw-bold" id="ticketsProgressCount">0</div>
                    <div class="small text-muted">Currently handled</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Waiting</div>
                    <div class="fs-4 fw-bold" id="ticketsWaitingCount">0</div>
                    <div class="small text-muted">Awaiting action</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Resolved</div>
                    <div class="fs-4 fw-bold" id="ticketsResolvedCount">0</div>
                    <div class="small text-muted">Completed tickets</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <div>
                <h6 class="mb-0 fw-semibold">Ticket List</h6>
                <small class="text-muted">Click a ticket row to manage it.</small>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <select id="ticketsStatusFilter" class="form-select form-select-sm" style="width: 220px;">
                    <option value="">All Status</option>
                    <option value="OPEN">Open</option>
                    <option value="IN_PROGRESS">In Progress</option>
                    <option value="WAITING_CUSTOMER">Waiting Customer</option>
                    <option value="WAITING_TECHNICIAN">Waiting Technician</option>
                    <option value="WAITING_CUSTOMER_SCHEDULE">Waiting Customer Schedule</option>
                    <option value="VISIT_SCHEDULED">Visit Scheduled</option>
                    <option value="RESOLVED">Resolved</option>
                    <option value="CLOSED">Closed</option>
                    <option value="CANCELLED">Cancelled</option>
                </select>

                <input id="ticketsSearchInput"
                       type="text"
                       class="form-control form-control-sm"
                       placeholder="Search ticket, subscriber..."
                       style="width: 260px;">
            </div>
        </div>

        <div class="card-body">
            <div id="ticketsTableHost"></div>
        </div>
    </div>

    <!-- Ticket Details Modal -->
    <div class="modal fade nx-modal" id="ticketDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
            <div class="modal-content nx-modal-content">
                <div class="modal-header nx-modal-header">
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <h5 class="modal-title mb-0" id="ticketModalTitle">Ticket Details</h5>
                            <span id="ticketModalStatus"></span>
                        </div>
                        <small class="text-muted" id="ticketModalSubtitle">Loading ticket...</small>
                    </div>

                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body nx-modal-body">
                    <div id="ticketModalLoading" class="text-center text-muted py-5">
                        <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                        Loading ticket details...
                    </div>

                    <div id="ticketModalContent" class="d-none">
                        <div class="row g-3 mb-3">
                            <div class="col-12 col-xl-8">
                                <div class="ticket-detail-card h-100">
                                    <div class="ticket-detail-label">Ticket Information</div>

                                    <div class="row g-2 mt-2">
                                        <div class="col-12">
                                            <div class="text-muted small">Subject</div>
                                            <div class="fw-semibold" id="ticketSubject">-</div>
                                        </div>

                                        <div class="col-12 col-md-4">
                                            <div class="text-muted small">Category</div>
                                            <div id="ticketCategory">-</div>
                                        </div>

                                        <div class="col-12 col-md-4">
                                            <div class="text-muted small">Priority</div>
                                            <div id="ticketPriority">-</div>
                                        </div>

                                        <div class="col-12 col-md-4">
                                            <div class="text-muted small">Assigned</div>
                                            <div id="ticketAssigned">-</div>
                                        </div>

                                        <div class="col-12 col-md-4">
                                            <div class="text-muted small">Preferred Visit</div>
                                            <div id="ticketPreferredVisit">-</div>
                                        </div>

                                        <div class="col-12">
                                            <div class="text-muted small">Description</div>
                                            <div id="ticketDescription">-</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-xl-4">
                                <div class="ticket-detail-card h-100">
                                    <div class="ticket-detail-label">Subscriber</div>

                                    <div class="mt-2">
                                        <div class="fw-semibold" id="ticketSubscriberName">-</div>
                                        <div class="small text-muted" id="ticketSubscriberAccount">-</div>
                                    </div>

                                    <hr>

                                    <div class="mb-2">
                                        <div class="text-muted small">Email</div>
                                        <div id="ticketSubscriberEmail">-</div>
                                    </div>

                                    <div class="mb-2">
                                        <div class="text-muted small">Contact</div>
                                        <div id="ticketSubscriberContact">-</div>
                                    </div>

                                    <div>
                                        <div class="text-muted small">Service</div>
                                        <div id="ticketService">-</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-12 col-xl-8">
                                <div class="ticket-detail-card h-100">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0 fw-semibold">Conversation</h6>
                                        <small class="text-muted" id="ticketMessageCount">0 messages</small>
                                    </div>

                                    <div id="ticketMessages" class="ticket-thread">
                                        <div class="text-muted text-center py-3">No messages yet.</div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-xl-4">
                                <div class="ticket-detail-card h-100">
                                    <h6 class="fw-semibold mb-3">Manage Ticket</h6>

                                    <form id="ticketAssignForm" class="mb-3">
                                        <input type="hidden" name="ticket_id" id="ticketAssignTicketId">

                                        <label class="form-label">Assigned To</label>
                                        <select name="assigned_user_id" id="ticketAssignUserSelect" class="form-select mb-2">
                                            <option value="0">Unassigned</option>
                                        </select>

                                        <button type="submit" class="btn btn-sm btn-outline-primary w-100" id="ticketAssignSubmitBtn">
                                            Assign Ticket
                                        </button>
                                    </form>

                                    <form id="ticketStatusForm" class="mb-3">
                                        <input type="hidden" name="ticket_id" id="ticketStatusTicketId">

                                        <label class="form-label">Status</label>
                                        <select name="status" id="ticketStatusSelect" class="form-select mb-2">
                                            <option value="OPEN">Open</option>
                                            <option value="IN_PROGRESS">In Progress</option>
                                            <option value="WAITING_CUSTOMER">Waiting Customer</option>
                                            <option value="WAITING_TECHNICIAN">Waiting Technician</option>
                                            <option value="WAITING_CUSTOMER_SCHEDULE">Waiting Customer Schedule</option>
                                            <option value="VISIT_SCHEDULED">Visit Scheduled</option>
                                            <option value="RESOLVED">Resolved</option>
                                            <option value="CLOSED">Closed</option>
                                            <option value="CANCELLED">Cancelled</option>
                                        </select>

                                        <button type="submit" class="btn btn-sm btn-primary w-100" id="ticketStatusSubmitBtn">
                                            Update Status
                                        </button>
                                    </form>

                                    <form id="ticketPriorityForm" class="mb-3">
                                        <input type="hidden" name="ticket_id" id="ticketPriorityTicketId">

                                        <label class="form-label">Priority</label>
                                        <select name="priority" id="ticketPrioritySelect" class="form-select mb-2">
                                            <option value="LOW">Low</option>
                                            <option value="MEDIUM">Medium</option>
                                            <option value="HIGH">High</option>
                                            <option value="URGENT">Urgent</option>
                                        </select>

                                        <button type="submit" class="btn btn-sm btn-outline-primary w-100" id="ticketPrioritySubmitBtn">
                                            Update Priority
                                        </button>
                                    </form>

                                    <form id="ticketReplyForm" class="mb-3">
                                        <input type="hidden" name="ticket_id" id="ticketReplyTicketId">

                                        <label class="form-label">Public Reply</label>
                                        <textarea name="message"
                                                  class="form-control mb-2"
                                                  rows="3"
                                                  placeholder="Reply visible to subscriber"></textarea>

                                        <button type="submit" class="btn btn-sm btn-success w-100" id="ticketReplySubmitBtn">
                                            Send Reply
                                        </button>
                                    </form>

                                    <form id="ticketInternalNoteForm">
                                        <input type="hidden" name="ticket_id" id="ticketInternalNoteTicketId">

                                        <label class="form-label">Internal Note</label>
                                        <textarea name="message"
                                                  class="form-control mb-2"
                                                  rows="3"
                                                  placeholder="Staff-only note"></textarea>

                                        <button type="submit" class="btn btn-sm btn-dark w-100" id="ticketInternalNoteSubmitBtn">
                                            Add Internal Note
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer nx-modal-footer">
                    <button type="button" class="btn btn-warning" id="ticketRequestScheduleBtn">
                        <i class="bi bi-calendar-plus"></i>
                        Request Schedule
                    </button>

                    <button type="button" class="btn btn-primary" id="ticketCreateWorkOrderBtn">
                        <i class="bi bi-clipboard-check"></i>
                        Create Work Order
                    </button>

                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="/module-assets/Tickets/js/Tickets.js?v=2"></script>
</div>
