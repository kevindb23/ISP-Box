<?php

use App\Modules\StaffAttendance\Controllers\StaffAttendanceApiController;
use App\Modules\StaffAttendance\Controllers\StaffAttendanceController;

$router->get('/staff-attendance', [StaffAttendanceController::class, 'index']);

$router->get('/api/v1/staff-attendance/today', [StaffAttendanceApiController::class, 'today']);
$router->get('/api/v1/staff-attendance/history', [StaffAttendanceApiController::class, 'history']);
$router->get('/api/v1/staff-attendance/available-technicians', [StaffAttendanceApiController::class, 'availableTechnicians']);

$router->post('/api/v1/staff-attendance/time-in', [StaffAttendanceApiController::class, 'timeIn']);
$router->post('/api/v1/staff-attendance/time-out', [StaffAttendanceApiController::class, 'timeOut']);
$router->post('/api/v1/staff-attendance/status', [StaffAttendanceApiController::class, 'status']);
