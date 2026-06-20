<?php

use Framework\SessionManager;

$user = SessionManager::user();
$role = strtoupper((string)($user['role'] ?? ''));

$canViewTeamAttendance = in_array($role, ['SUPERADMIN', 'ADMIN', 'ADMINISTRATOR'], true);

?>

<div class="container-fluid nx-page staff-attendance-page" data-staff-attendance-page="index">

    <link rel="stylesheet" href="/module-assets/StaffAttendance/css/StaffAttendance.css">

    <div id="staffAttendanceAlert"></div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="text-primary small fw-bold text-uppercase">
                    <i class="bi bi-clock-history"></i>
                    Staff Attendance
                </div>
                <h5 class="mb-0 fw-semibold">Time-In / Time-Out</h5>
                <small class="text-muted">Track staff duty status for smart ticket and work order assignment.</small>
            </div>

            <button id="staffAttendanceRefreshBtn" class="btn btn-light border">
                <i class="bi bi-arrow-clockwise"></i>
                Refresh
            </button>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 <?= $canViewTeamAttendance ? 'col-xl-4' : 'col-xl-5' ?>">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-semibold">My Attendance</h6>
                    <small class="text-muted">Your current duty status today</small>
                </div>

                <div class="card-body">
                    <div class="staff-attendance-status mb-3">
                        <div class="text-muted small">Current Status</div>
                        <div id="staffMyStatus" class="fs-5 fw-bold">Loading...</div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="staff-attendance-mini">
                                <div class="text-muted small">Time In</div>
                                <div class="fw-semibold" id="staffMyTimeIn">-</div>
                            </div>
                        </div>

                        <div class="col-6">
                            <div class="staff-attendance-mini">
                                <div class="text-muted small">Time Out</div>
                                <div class="fw-semibold" id="staffMyTimeOut">-</div>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button id="staffTimeInBtn" class="btn btn-success">
                            <i class="bi bi-box-arrow-in-right"></i>
                            Time In
                        </button>

                        <button id="staffTimeOutBtn" class="btn btn-danger">
                            <i class="bi bi-box-arrow-right"></i>
                            Time Out
                        </button>
                    </div>

                    <hr>

                    <label class="form-label">Duty Status</label>
                    <select id="staffDutyStatusSelect" class="form-select mb-2">
                        <option value="AVAILABLE">Available</option>
                        <option value="BUSY">Busy</option>
                        <option value="ON_BREAK">On Break</option>
                        <option value="TRAVELING">Traveling</option>
                        <option value="ON_SITE">On Site</option>
                    </select>

                    <button id="staffDutyStatusBtn" class="btn btn-outline-primary w-100">
                        Update Duty Status
                    </button>
                </div>
            </div>
        </div>

        <?php if ($canViewTeamAttendance): ?>
            <div class="col-12 col-xl-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0 fw-semibold">Today’s Staff</h6>
                        <small class="text-muted">Staff currently timed in or timed out today</small>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                <tr>
                                    <th class="ps-4">Staff</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Time In</th>
                                    <th class="pe-4">Time Out</th>
                                </tr>
                                </thead>
                                <tbody id="staffAttendanceBody">
                                <tr>
                                    <td colspan="5" class="text-muted text-center py-4">Loading staff attendance...</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$canViewTeamAttendance): ?>
            <div class="col-12 col-xl-7">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0 fw-semibold">My Activity Today</h6>
                        <small class="text-muted">Your own time-in, time-out, and duty status changes</small>
                    </div>

                    <div class="card-body p-0">
                        <div id="staffMyLogs" class="staff-attendance-log-list">
                            <div class="text-muted text-center py-4">Loading your activity...</div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($canViewTeamAttendance): ?>
        <div class="row g-3">
            <div class="col-12 col-xl-5">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0 fw-semibold">My Activity Today</h6>
                        <small class="text-muted">Your own time-in, time-out, and duty status changes</small>
                    </div>

                    <div class="card-body p-0">
                        <div id="staffMyLogs" class="staff-attendance-log-list">
                            <div class="text-muted text-center py-4">Loading your activity...</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-7">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0 fw-semibold">Today’s Activity Log</h6>
                        <small class="text-muted">Audit trail of staff attendance and duty status movement</small>
                    </div>

                    <div class="card-body p-0">
                        <div id="staffAttendanceLogs" class="staff-attendance-log-list">
                            <div class="text-muted text-center py-4">Loading activity logs...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="/module-assets/StaffAttendance/js/StaffAttendance.js"></script>
</div>