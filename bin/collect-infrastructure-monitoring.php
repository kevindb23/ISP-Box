#!/usr/bin/env php
<?php

declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__));
spl_autoload_register(static function(string $class):void{foreach(['App\\'=>BASE_PATH.'/app/','Framework\\'=>BASE_PATH.'/framework/']as$prefix=>$dir){if(!str_starts_with($class,$prefix))continue;$file=$dir.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require_once$file;return;}});

try{
    /** @var Framework\Container $container */
    $container=require BASE_PATH.'/bootstrap/container.php';
    /** @var App\Modules\Api\v1\Services\MonitoringService $service */
    $service=$container->get(App\Modules\Api\v1\Services\MonitoringService::class);
    $snapshot=$service->collectAndStore((int)(getenv('MONITORING_RETENTION_DAYS')?:30));
    fwrite(STDOUT,json_encode(['ok'=>true,'snapshot_id'=>$snapshot['snapshot_id'],'overall_status'=>$snapshot['overall_status'],'collected_at'=>$snapshot['collected_at']],JSON_UNESCAPED_SLASHES).PHP_EOL);
    exit(0);
}catch(Throwable $e){fwrite(STDERR,json_encode(['ok'=>false,'error'=>'MONITORING_COLLECTION_FAILED']).PHP_EOL);exit(1);}
