<?php $p=BASE_PATH.'/public/build-next/.vite/manifest.json';$m=is_file($p)?(json_decode((string)file_get_contents($p),true)?:[]):[];$e=$m['src/main.ts']??[];$v=is_file($p)?(string)filemtime($p):(string)time();foreach(($e['css']??[])as$c):?><link rel="stylesheet" href="/build-next/<?=htmlspecialchars(ltrim((string)$c,'/'),ENT_QUOTES,'UTF-8')?>?v=<?=htmlspecialchars($v,ENT_QUOTES,'UTF-8')?>"><?php endforeach;?><div class="container-fluid nx-page" data-nx-next-root="work-orders"></div><?php if(!empty($e['file'])):?><script type="module" src="/build-next/<?=htmlspecialchars(ltrim((string)$e['file'],'/'),ENT_QUOTES,'UTF-8')?>?v=<?=htmlspecialchars($v,ENT_QUOTES,'UTF-8')?>"></script><?php else:?><div class="alert alert-warning">The work orders interface is not built.</div><?php endif;return;?>
<div class="container-fluid nx-page work-orders-page" id="workOrdersPage" data-work-orders-page="index">
    <div id="workOrdersAlert"></div>

    <div class="card border-0 shadow-sm mb-3 nx-page-header-card work-orders-hero-card">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="text-primary small fw-bold text-uppercase">
                    <i class="bi bi-clipboard-check"></i>
                    Field Operations
                </div>
                <h5 class="mb-0 fw-semibold">Work Orders</h5>
                <small class="text-muted">Manage installations, repairs, technical visits, and field tasks.</small>
            </div>

            <button id="workOrdersRefreshBtn" class="btn btn-light border">
                <i class="bi bi-arrow-clockwise"></i>
                Refresh
            </button>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-6 col-xl">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Open</div>
                    <div class="fs-4 fw-bold" id="workOrdersOpenCount">0</div>
                    <div class="small text-muted">Unassigned jobs</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Assigned</div>
                    <div class="fs-4 fw-bold" id="workOrdersAssignedCount">0</div>
                    <div class="small text-muted">Assigned to staff</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Active</div>
                    <div class="fs-4 fw-bold" id="workOrdersActiveCount">0</div>
                    <div class="small text-muted">In progress / on site</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Completed</div>
                    <div class="fs-4 fw-bold" id="workOrdersCompletedCount">0</div>
                    <div class="small text-muted">Finished jobs</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Issues</div><div class="fs-4 fw-bold" id="workOrdersIssueCount">0</div><div class="small text-muted">Failed / cancelled</div></div></div></div>
    </div>

    <div class="card border-0 shadow-sm nx-content-card work-orders-content-card">
        <div class="work-orders-table-topline"></div>
        <div class="card-header bg-white border-0 d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <div>
                <h6 class="mb-0 fw-semibold">Work Order List</h6>
                <small class="text-muted">Click view to inspect tasks, subscriber info, and status history.</small>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <select id="workOrdersStatusFilter" class="form-select form-select-sm" style="width: 180px;">
                    <option value="">All Status</option>
                    <option value="OPEN">Open</option>
                    <option value="ASSIGNED">Assigned</option>
                    <option value="IN_PROGRESS">In Progress</option>
                    <option value="ON_SITE">On Site</option>
                    <option value="COMPLETED">Completed</option>
                    <option value="CANCELLED">Cancelled</option>
                    <option value="FAILED">Failed</option>
                </select>

                <select id="workOrdersTypeFilter" class="form-select form-select-sm" style="width: 220px;">
                    <option value="">All Types</option>
                    <option value="ONT_INSTALLATION">ONT Installation</option>
                    <option value="TECHNICAL_VISIT">Technical Visit</option>
                    <option value="NO_INTERNET">No Internet</option>
                    <option value="LOS">LOS</option>
                    <option value="RELOCATION">Relocation</option>
                    <option value="REPAIR">Repair</option>
                    <option value="RECONNECTION">Reconnection</option>
                    <option value="DISCONNECTION">Disconnection</option>
                    <option value="NAP_CHECK">NAP Check</option>
                    <option value="DROP_CABLE_REPLACEMENT">Drop Cable Replacement</option>
                    <option value="OTHER">Other</option>
                </select>

                <input id="workOrdersSearchInput"
                       type="text"
                       class="form-control form-control-sm"
                       placeholder="Search work order, subscriber..."
                       style="width: 260px;">
            </div>
        </div>

        <div class="card-body">
            <div id="workOrdersTableHost"></div>
        </div>
    </div>

    <!-- Work Order Details Modal -->
    <div class="modal fade nx-modal" id="workOrderDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
            <div class="modal-content nx-modal-content">
                <div class="modal-header nx-modal-header">
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <h5 class="modal-title mb-0" id="workOrderModalTitle">Work Order Details</h5>
                            <span id="workOrderModalStatus"></span>
                        </div>
                        <small class="text-muted" id="workOrderModalSubtitle">Loading work order...</small>
                    </div>

                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body nx-modal-body">
                    <div id="workOrderModalLoading" class="text-center text-muted py-5">
                        <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                        Loading work order details...
                    </div>

                    <div id="workOrderModalContent" class="d-none">
                        <input type="hidden" id="workOrderId">

                        <div class="row g-3 mb-3">
                            <div class="col-12 col-xl-8">
                                <div class="work-order-detail-card h-100">
                                    <div class="work-order-detail-label">Work Order Information</div>

                                    <div class="row g-2 mt-2">
                                        <div class="col-12">
                                            <div class="text-muted small">Title</div>
                                            <div class="fw-semibold" id="workOrderTitle">-</div>
                                        </div>

                                        <div class="col-12 col-md-4">
                                            <div class="text-muted small">Type</div>
                                            <div id="workOrderType">-</div>
                                        </div>

                                        <div class="col-12 col-md-4">
                                            <div class="text-muted small">Priority</div>
                                            <div id="workOrderPriority">-</div>
                                        </div>

                                        <div class="col-12 col-md-4">
                                            <div class="text-muted small">Assigned</div>
                                            <div id="workOrderAssigned">-</div>
                                        </div>

                                        <div class="col-12">
                                            <div class="text-muted small">Description</div>
                                            <div id="workOrderDescription">-</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-xl-4">
                                <div class="work-order-detail-card h-100">
                                    <div class="work-order-detail-label">Subscriber</div>

                                    <div class="mt-2">
                                        <div class="fw-semibold" id="workOrderSubscriberName">-</div>
                                        <div class="small text-muted" id="workOrderSubscriberAccount">-</div>
                                    </div>

                                    <hr>

                                    <div class="mb-2">
                                        <div class="text-muted small">Contact</div>
                                        <div id="workOrderSubscriberContact">-</div>
                                    </div>

                                    <div class="mb-2">
                                        <div class="text-muted small">Service</div>
                                        <div id="workOrderService">-</div>
                                    </div>

                                    <div class="mb-2">
                                        <div class="text-muted small">Source Ticket</div>
                                        <div id="workOrderTicket">Not linked to a ticket</div>
                                    </div>

                                    <div>
                                        <div class="text-muted small">Location</div>
                                        <div id="workOrderLocation">-</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-12 col-xl-4">
                                <div class="work-order-detail-card h-100">
                                    <h6 class="fw-semibold mb-3">Manage Work Order</h6>

                                    <form id="workOrderAssignForm" class="mb-3">
                                        <input type="hidden" name="work_order_id" id="workOrderAssignId">

                                        <label class="form-label">Assign Technician</label>
                                        <select name="assigned_user_id" id="workOrderAssignUserSelect" class="form-select mb-2">
                                            <option value="0">Unassigned</option>
                                        </select>

                                        <button type="submit" class="btn btn-sm btn-outline-primary w-100" id="workOrderAssignSubmitBtn">
                                            Assign Work Order
                                        </button>
                                    </form>

                                    <form id="workOrderStatusForm">
                                        <input type="hidden" name="work_order_id" id="workOrderStatusId">

                                        <label class="form-label">Status</label>
                                        <select name="status" id="workOrderStatusSelect" class="form-select mb-2">
                                            <option value="OPEN">Open</option>
                                            <option value="ASSIGNED">Assigned</option>
                                            <option value="IN_PROGRESS">In Progress</option>
                                            <option value="ON_SITE">On Site</option>
                                            <option value="COMPLETED">Completed</option>
                                            <option value="CANCELLED">Cancelled</option>
                                            <option value="FAILED">Failed</option>
                                        </select>

                                        <textarea name="note"
                                                  id="workOrderStatusNote"
                                                  class="form-control mb-2"
                                                  rows="2"
                                                  placeholder="Add a status note; required for completion, failure, or cancellation"></textarea>

                                        <button type="submit" class="btn btn-sm btn-primary w-100" id="workOrderStatusSubmitBtn">
                                            Update Status
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <div class="col-12 col-xl-8">
                                <div class="work-order-detail-card h-100">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0 fw-semibold">Quick Actions</h6>
                                        <small class="text-muted">Field workflow shortcuts</small>
                                    </div>

                                    <div class="row g-2">
                                        <div class="col-12 col-md-6 col-xl-3">
                                            <button type="button"
                                                    class="btn btn-outline-primary w-100 work-order-status-shortcut"
                                                    data-status="IN_PROGRESS">
                                                <i class="bi bi-play-circle"></i>
                                                Start Job
                                            </button>
                                        </div>

                                        <div class="col-12 col-md-6 col-xl-3">
                                            <button type="button"
                                                    class="btn btn-outline-warning w-100 work-order-status-shortcut"
                                                    data-status="ON_SITE">
                                                <i class="bi bi-geo-alt"></i>
                                                On Site
                                            </button>
                                        </div>

                                        <div class="col-12 col-md-6 col-xl-3">
                                            <button type="button"
                                                    class="btn btn-outline-success w-100 work-order-status-shortcut"
                                                    data-status="COMPLETED">
                                                <i class="bi bi-check-circle"></i>
                                                Complete
                                            </button>
                                        </div>

                                        <div class="col-12 col-md-6 col-xl-3">
                                            <button type="button"
                                                    class="btn btn-outline-danger w-100 work-order-status-shortcut"
                                                    data-status="FAILED">
                                                <i class="bi bi-x-circle"></i>
                                                Failed
                                            </button>
                                        </div>
                                    </div>

                                    <div class="small text-muted mt-3">
                                        Completing a work order requires all required tasks to be marked done.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-xl-6">
                                <div class="work-order-detail-card h-100">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0 fw-semibold">Tasks</h6>
                                        <small class="text-muted" id="workOrderTaskCount">0 tasks</small>
                                    </div>

                                    <div id="workOrderTasks">
                                        <div class="text-muted text-center py-3">No tasks yet.</div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-xl-6">
                                <div class="work-order-detail-card h-100">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0 fw-semibold">Status History</h6>
                                        <small class="text-muted" id="workOrderLogCount">0 logs</small>
                                    </div>

                                    <div id="workOrderStatusLogs">
                                        <div class="text-muted text-center py-3">No status logs yet.</div>
                                    </div>
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

    <script src="/module-assets/WorkOrders/js/WorkOrders.js?v=<?= time() ?>"></script>
</div>
