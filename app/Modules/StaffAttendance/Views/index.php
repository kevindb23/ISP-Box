<?php

use Framework\SessionManager;

$user = SessionManager::user();
$role = strtoupper((string)($user['role'] ?? ''));

$canViewTeamAttendance = in_array($role, ['SUPERADMIN', 'ADMIN', 'ADMINISTRATOR', 'NOC', 'SUPPORT'], true);

?>

<?php $p=BASE_PATH.'/public/build-next/.vite/manifest.json';$m=is_file($p)?(json_decode((string)file_get_contents($p),true)?:[]):[];$e=$m['src/main.ts']??[];$v=is_file($p)?(string)filemtime($p):(string)time();foreach(($e['css']??[])as$c):?><link rel="stylesheet" href="/build-next/<?=htmlspecialchars(ltrim((string)$c,'/'),ENT_QUOTES,'UTF-8')?>?v=<?=htmlspecialchars($v,ENT_QUOTES,'UTF-8')?>"><?php endforeach;?><div class="container-fluid nx-page" data-nx-next-root="attendance" data-can-view-team="<?=$canViewTeamAttendance?'1':'0'?>" data-role="<?=htmlspecialchars($role,ENT_QUOTES,'UTF-8')?>"></div><?php if(!empty($e['file'])):?><script type="module" src="/build-next/<?=htmlspecialchars(ltrim((string)$e['file'],'/'),ENT_QUOTES,'UTF-8')?>?v=<?=htmlspecialchars($v,ENT_QUOTES,'UTF-8')?>"></script><?php else:?><div class="alert alert-warning">The attendance interface is not built.</div><?php endif;return;?>
<div class="container-fluid nx-page staff-attendance-page" id="staffAttendancePage" data-staff-attendance-page="index">
    <div id="staffAttendanceAlert"></div>

    <div class="card border-0 shadow-sm mb-3 nx-page-header-card attendance-hero-card">
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
                        <option value="ON_BREAK">On Break</option>
                        <option value="TRAVELING">Traveling</option>
                        <?php if ($role !== 'TECHNICIAN'): ?>
                            <option value="BUSY">Busy</option>
                            <option value="ON_SITE">On Site</option>
                        <?php endif; ?>
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
                        <small class="text-muted">All active staff, including those currently offline</small>
                    </div>

                    <div class="card-body p-0">
                        <div class="attendance-table-topline"></div><div class="nx-table-wrap">
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
        <div class="card border-0 shadow-sm mt-3">
            <div class="attendance-table-topline"></div>
            <div class="card-header bg-white border-0 d-flex flex-wrap justify-content-between align-items-end gap-2">
                <div><h6 class="mb-0 fw-semibold">Attendance History</h6><small class="text-muted">Historical time records and computed duty duration</small></div>
                <div class="d-flex gap-2"><div><label class="form-label small mb-1">From</label><input type="date" id="staffHistoryFrom" class="form-control form-control-sm" value="<?= date('Y-m-d', strtotime('-30 days')) ?>"></div><div><label class="form-label small mb-1">To</label><input type="date" id="staffHistoryTo" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>"></div><button id="staffHistoryLoadBtn" class="btn btn-sm btn-primary align-self-end">Apply</button></div>
            </div>
            <div class="card-body p-0"><div class="nx-table-wrap"><table class="table align-middle mb-0"><thead><tr><th>Staff</th><th>Role</th><th>Date</th><th>Time In</th><th>Time Out</th><th>Duration</th><th>Status</th></tr></thead><tbody id="staffHistoryBody"><tr><td colspan="7" class="text-center text-muted py-4">Loading history...</td></tr></tbody></table></div></div>
        </div>
    <?php endif; ?>

    <script src="/module-assets/StaffAttendance/js/StaffAttendance.js"></script>
</div>
