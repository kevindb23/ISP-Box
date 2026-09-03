<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);$service=file_get_contents($root.'/app/Modules/Dashboard/Services/DashboardService.php');$repository=file_get_contents($root.'/app/Modules/Api/v1/Repositories/MonitoringRepository.php');$view=file_get_contents($root.'/app/Modules/Dashboard/Views/index.php');$page=file_get_contents($root.'/frontend-next/src/modules/dashboard/DashboardPage.vue');$fail=[];
foreach(['latestInfrastructureSnapshot','infrastructureDashboardHistory','deliverySummary']as$c)if(!str_contains($service,$c))$fail[]="dashboard service omits {$c}";
foreach(['memory_percent','disk_percent','load_1m','overall_status']as$c)if(!str_contains($repository,$c))$fail[]="dashboard history omits {$c}";
if(!str_contains($view,'data-nx-next-root="dashboard"'))$fail[]='dashboard does not mount the modern UI';
foreach(['Platform services','Host capacity','Infrastructure incidents','Operational metrics','/api/v1/dashboard/stats']as$c)if(!str_contains($page,$c))$fail[]="dashboard UI omits {$c}";
foreach(['Traffic and subscriber growth','Invoice run completed','ONT provisioned for latest subscriber']as$fake)if(str_contains($page,$fake))$fail[]="dashboard UI retains synthetic content: {$fake}";
if($fail){fwrite(STDERR,implode(PHP_EOL,$fail).PHP_EOL);exit(1);}echo "dashboard_infrastructure_contract=PASS\n";
