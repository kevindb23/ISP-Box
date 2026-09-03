<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);$service=file_get_contents($root.'/app/Modules/BngManagement/Services/AccelPppConfigService.php');$controller=file_get_contents($root.'/app/Modules/BngManagement/Controllers/BngManagementApiController.php');$routes=file_get_contents($root.'/app/Modules/BngManagement/Routes/api.php');$connection=file_get_contents($root.'/app/Modules/BngManagement/Services/BngConnectionService.php');$api=file_get_contents($root.'/frontend-next/src/modules/bng/api.ts');$page=file_get_contents($root.'/frontend-next/src/modules/bng/BngPage.vue');$fail=[];
foreach(['previewDraft','[REDACTED]',"'saved'=>false"]as$c)if(!str_contains($service,$c))$fail[]="draft preview omits {$c}";
if(!str_contains($controller,'previewAccelDraft')||!str_contains($routes,'preview-draft'))$fail[]='draft preview endpoint is incomplete';
if(!str_contains($api,'preview-draft')||!str_contains($page,'previewAccelConfig(accelForm)'))$fail[]='BNG UI does not preview current form values';
foreach(['nexusbox-bng-ssh-','posix_geteuid','fileowner']as$c)if(!str_contains($connection,$c))$fail[]="per-user SSH storage omits {$c}";
if($fail){fwrite(STDERR,implode(PHP_EOL,$fail).PHP_EOL);exit(1);}echo "bng_preview_monitoring_contract=PASS\n";
