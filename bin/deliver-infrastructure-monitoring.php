#!/usr/bin/env php
<?php
declare(strict_types=1);define('BASE_PATH',dirname(__DIR__));spl_autoload_register(static function(string$class):void{foreach(['App\\'=>BASE_PATH.'/app/','Framework\\'=>BASE_PATH.'/framework/']as$prefix=>$dir){if(!str_starts_with($class,$prefix))continue;$file=$dir.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require_once$file;return;}});
try{$container=require BASE_PATH.'/bootstrap/container.php';$result=$container->get(App\Modules\Api\v1\Services\MonitoringDeliveryService::class)->deliverDue();fwrite(STDOUT,json_encode(['ok'=>true]+$result,JSON_UNESCAPED_SLASHES).PHP_EOL);exit(($result['failed']??0)>0?2:0);}catch(Throwable){fwrite(STDERR,json_encode(['ok'=>false,'error'=>'MONITORING_DELIVERY_FAILED']).PHP_EOL);exit(1);}
