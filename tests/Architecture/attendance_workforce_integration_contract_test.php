<?php
declare(strict_types=1);
$base=dirname(__DIR__,2);
$attendance=file_get_contents($base.'/app/Modules/StaffAttendance/Services/StaffAttendanceService.php');
$work=file_get_contents($base.'/app/Modules/WorkOrders/Services/WorkOrdersService.php');
$portal=file_get_contents($base.'/app/Modules/TechnicianPortal/Services/TechnicianPortalService.php');
$repo=file_get_contents($base.'/app/Modules/WorkOrders/Repositories/WorkOrdersRepository.php');
$checks=[
 'portal canonical status'=>str_contains($portal,'$this->workOrders->updateStatus('),
 'portal canonical tasks'=>str_contains($portal,'$this->workOrders->completeTask('),
 'portal canonical attendance'=>str_contains($portal,'$this->attendance->timeIn(')&&str_contains($portal,'$this->attendance->timeOut('),
 'clock in guard'=>str_contains($portal,'requireClockedIn'),
 'attendance active work guard'=>str_contains($attendance,'Finish or hand off active field work'),
 'derived field statuses'=>str_contains($attendance,'Busy and on-site status are controlled'),
 'transactional creation'=>str_contains($repo,'createWithTasks'),
 'number lock'=>str_contains($repo,'nexusbox:work-order-number:'),
 'dispatch eligibility'=>str_contains($work,'findDispatchableTechnician'),
 'task state guards'=>str_contains($work,'Tasks can only be completed on an assigned or active work order'),
];
$failed=array_keys(array_filter($checks,fn($ok)=>!$ok));
if($failed){fwrite(STDERR,'Attendance/workforce integration contract failed: '.implode(', ',$failed).PHP_EOL);exit(1);}
echo 'attendance_workforce_integration_contract=PASS checks='.count($checks).PHP_EOL;
