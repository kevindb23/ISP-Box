<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);
$bngView=file_get_contents($root.'/app/Modules/BngManagement/Views/index.php');
$bngJs=file_get_contents($root.'/app/Modules/BngManagement/Assets/js/BngManagement.js');
$bngService=file_get_contents($root.'/app/Modules/BngManagement/Services/BngConnectionService.php');
$bngRoutes=file_get_contents($root.'/app/Modules/BngManagement/Routes/api.php');
$view=file_get_contents($root.'/app/Modules/CgnatManagement/Views/index.php');
$js=file_get_contents($root.'/app/Modules/CgnatManagement/Assets/js/CgnatManagement.js');
$service=file_get_contents($root.'/app/Modules/CgnatManagement/Services/CgnatService.php');
$routes=file_get_contents($root.'/app/Modules/CgnatManagement/Routes/api.php');
$failures=[];$check=static function(bool $ok,string $message)use(&$failures){if(!$ok)$failures[]=$message;};
preg_match_all('/data-bng-tab-btn="([^"]+)"/',$bngView,$bngTabs);
$check(($bngTabs[1]??[])===['bng','accel','svlan','cvlan','ppp'],'BNG tabs are incomplete.');
$check(str_contains($bngJs,'/api/v1/bng/accel-ppp/stage')&&!str_contains($bngJs,'systemctl restart'),'BNG maintenance staging contract regressed.');
$check(str_contains($bngRoutes,'/api/v1/bng/runtime')&&str_contains($bngService,'runRemoteCommandWithInput'),'BNG runtime or secure transfer is missing.');
preg_match_all('/data-cgnat-tab-btn="([^"]+)"/',$view,$tabs);
$check(($tabs[1]??[])===['config','rules'],'CGNAT must expose exactly Configuration and Live POSTROUTING Rules tabs.');
$check(!str_contains($view,'NAT Pools')&&!str_contains($view,'Deployments'),'Legacy CGNAT tabs remain visible.');
$check(!str_contains($view,'Router next hop')&&!str_contains($view,'Remarks'),'Unused CGNAT syntax fields remain visible.');
foreach(['bng_interface','egress_interface','cgnatRuntimeRulesBody','cgnatRemoveRulesBtn'] as $value)$check(str_contains($view,$value),"CGNAT UI omits {$value}.");
$check(str_contains($js,'runtime.nat_rules')&&str_contains($js,'runtime.interfaces'),'CGNAT live discovery is missing.');
$check(str_contains($js,'/api/v1/cgnat/postrouting/remove')&&str_contains($routes,'/api/v1/cgnat/postrouting/remove'),'POSTROUTING removal wiring is missing.');
$check(str_contains($service,'interfaceExists')&&str_contains($service,'escapeshellarg'),'CGNAT command safety is missing.');
$check(str_contains($service,"str_starts_with(\$rule,'-A POSTROUTING ')")&&str_contains($service,"\$tokens[0]='-D'"),'Rule removal is not restricted to POSTROUTING append entries.');
$check(str_contains($service,'1023'),'CGNAT public range safety limit is missing.');
if($failures){fwrite(STDERR,"BNG/CGNAT contract failed:\n- ".implode("\n- ",$failures)."\n");exit(1);}
echo "cgnat_runtime_ui_contract=PASS\n";
