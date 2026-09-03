<div class="container-fluid nx-page subscriber-portal-page sp-page" data-sp-page="tickets">
    <div id="subscriberPortalAlert"></div>

    <div class="card border-0 shadow-sm mb-3 sp-page-title-card">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="sp-page-kicker">
                    <i class="bi bi-ticket-detailed"></i>
                    Support Tickets
                </div>
                <h5 class="mb-0 fw-semibold">My Tickets</h5>
                <small class="text-muted">View your submitted concerns and support ticket status.</small>
            </div>

            <button type="button"
                    class="btn btn-primary"
                    data-bs-toggle="modal"
                    data-bs-target="#spRaiseConcernModal">
                <i class="bi bi-headset"></i>
                Raise a Concern
            </button>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <div>
                <h6 class="mb-0 fw-semibold">Ticket List</h6>
                <small class="text-muted">Click a ticket row to view details and replies.</small>
            </div>

            <button id="subscriberPortalRefreshBtn" class="btn btn-light border">
                <i class="bi bi-arrow-clockwise"></i>
                Refresh
            </button>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                    <tr>
                        <th class="ps-4">Ticket No.</th>
                        <th>Subject</th>
                        <th>Category</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Created</th>
                    </tr>
                    </thead>
                    <tbody id="spTicketsBody">
                    <tr>
                        <td colspan="6" class="text-muted text-center py-4">Loading tickets...</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-footer bg-white border-0">
            <div id="spTicketsPager" class="sp-pager"></div>
        </div>
    </div>

    <!-- Ticket Details Modal -->
    <div class="modal fade nx-modal" id="spTicketDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content nx-modal-content">
                <div class="modal-header nx-modal-header">
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <h5 class="modal-title mb-0" id="spTicketModalTitle">Ticket Details</h5>
                            <span id="spTicketModalStatus"></span>
                        </div>
                        <small class="text-muted" id="spTicketModalSubtitle">Loading ticket...</small>
                    </div>

                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body nx-modal-body">
                    <div id="spTicketModalLoading" class="text-center text-muted py-5">
                        <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                        Loading ticket details...
                    </div>

                    <div id="spTicketModalContent" class="d-none">
                        <div class="row g-3 mb-3">
                            <div class="col-12 col-md-6">
                                <div class="sp-detail-card h-100">
                                    <div class="sp-detail-label">Ticket Information</div>

                                    <div class="mt-2">
                                        <div class="text-muted small">Subject</div>
                                        <div class="fw-semibold" id="spTicketSubject">-</div>
                                    </div>

                                    <div class="row g-2 mt-2">
                                        <div class="col-6">
                                            <div class="text-muted small">Category</div>
                                            <div id="spTicketCategory">-</div>
                                        </div>

                                        <div class="col-6">
                                            <div class="text-muted small">Priority</div>
                                            <div id="spTicketPriority">-</div>
                                        </div>

                                        <div class="col-12">
                                            <div class="text-muted small">Preferred Visit</div>
                                            <div id="spTicketPreferredVisit">-</div>
                                        </div>

                                        <div class="col-6">
                                            <div class="text-muted small">Created</div>
                                            <div id="spTicketCreated">-</div>
                                        </div>

                                        <div class="col-6">
                                            <div class="text-muted small">Updated</div>
                                            <div id="spTicketUpdated">-</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <div class="sp-detail-card h-100">
                                    <div class="sp-detail-label">Original Concern</div>
                                    <div class="mt-2 text-muted" id="spTicketDescription">-</div>
                                </div>
                            </div>
                        </div>

                        <div class="sp-detail-card mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0 fw-semibold">Conversation</h6>
                                <small class="text-muted" id="spTicketMessageCount">0 messages</small>
                            </div>

                            <div id="spTicketMessages" class="sp-ticket-thread">
                                <div class="text-muted text-center py-3">No replies yet.</div>
                            </div>
                        </div>

                        <form id="spTicketScheduleVisitForm" class="sp-detail-card mb-3 d-none">
                            <input type="hidden" name="ticket_id" id="spScheduleVisitTicketId">

                            <div class="fw-semibold mb-1">
                                <i class="bi bi-calendar-event"></i>
                                Schedule Technician Visit
                            </div>

                            <div class="small text-muted mb-3">
                                NOC confirmed that this concern needs a technician visit. Please choose your preferred schedule.
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Preferred Date</label>
                                    <input type="date"
                                           name="preferred_visit_date"
                                           id="spScheduleVisitDate"
                                           class="form-control"
                                           required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Preferred Time</label>
                                    <select name="preferred_visit_time"
                                            id="spScheduleVisitTime"
                                            class="form-select"
                                            required>
                                        <option value="">Select date first</option>
                                    </select>
                                    <div class="small text-muted mt-1" id="spScheduleVisitSlotHelp">
                                        Choose a date to load available slots.
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Visit Notes</label>
                                    <textarea name="preferred_visit_notes"
                                              class="form-control"
                                              rows="3"
                                              placeholder="Example: Available after 2 PM, please call before going."></textarea>
                                </div>

                                <div class="col-12 d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary" id="spScheduleVisitSubmitBtn">
                                        <i class="bi bi-calendar-check"></i>
                                        Submit Schedule
                                    </button>
                                </div>
                            </div>
                        </form>

                        <form id="spTicketReplyForm">
                            <input type="hidden" name="ticket_id" id="spTicketReplyTicketId">

                            <div class="mb-3">
                                <label class="form-label">Reply</label>
                                <textarea name="message"
                                          class="form-control"
                                          rows="4"
                                          placeholder="Type your reply..."
                                          required></textarea>
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">
                                    Close
                                </button>

                                <button type="submit" class="btn btn-primary" id="spTicketReplySubmitBtn">
                                    <i class="bi bi-send"></i>
                                    Send Reply
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="modal-footer nx-modal-footer d-none" id="spTicketModalFooter">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Raise Concern Modal -->
    <div class="modal fade nx-modal" id="spRaiseConcernModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content nx-modal-content">
                <form id="spRaiseConcernForm">
                    <div class="modal-header nx-modal-header">
                        <div>
                            <h5 class="modal-title mb-0">Raise a Concern</h5>
                            <small class="text-muted">Tell us what you need help with.</small>
                        </div>

                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body nx-modal-body">
                        <div class="mb-3">
                            <label class="form-label">Affected Service</label>
                            <select name="service_id" id="spTicketServiceId" class="form-select">
                                <option value="">General account concern</option>
                            </select>
                            <div class="form-text">Select the connection affected by this concern.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Concern Type</label>
                            <select name="category" class="form-select" required>
                                <option value="INTERNET">Internet Issue</option>
                                <option value="BILLING">Billing Concern</option>
                                <option value="ACCOUNT">Account Concern</option>
                                <option value="OTHERS">Others</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Subject</label>
                            <input type="text"
                                   name="subject"
                                   class="form-control"
                                   placeholder="Example: No internet connection"
                                   required>
                        </div>

                        <div class="mb-0">
                            <label class="form-label">Description</label>
                            <textarea name="description"
                                      class="form-control"
                                      rows="5"
                                      placeholder="Please describe your concern..."
                                      required></textarea>
                        </div>
                    </div>

                    <div class="modal-footer nx-modal-footer">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">
                            Cancel
                        </button>

                        <button type="submit" class="btn btn-primary" id="spRaiseConcernSubmitBtn">
                            <i class="bi bi-send"></i>
                            Submit Concern
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="/module-assets/SubscriberPortal/js/SubscriberPortal.js?v=4"></script>
</div>
