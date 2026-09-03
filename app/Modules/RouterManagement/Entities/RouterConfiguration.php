<?php
namespace App\Modules\RouterManagement\Entities;
final class RouterConfiguration
{
    public function __construct(private array $row){}
    public function toArray(bool $includeSecrets=false): array
    {
        $row=$this->row;$config=json_decode((string)($row['config_json']??'{}'),true)?:[];unset($row['config_json']);$row['config']=$config;$row['has_password']=!empty($row['password']);$row['host_key_trusted']=!empty($row['known_host_key']);
        if(!$includeSecrets)unset($row['password'],$row['known_host_key']);
        return $row;
    }
}
