<?php
namespace App\Modules\RouterManagement\Repositories;
use App\Infrastructure\Security\SecretCipher;
use App\Modules\RouterManagement\Entities\RouterConfiguration;
use PDO;
final class RouterManagementRepository
{
    public function __construct(private PDO $db,private SecretCipher $cipher){}
    public function core(bool $secrets=false):?array{$row=$this->db->query('SELECT * FROM router_core_settings ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);if(!$row)return null;$row['password']=$this->cipher->decrypt($row['password']??null);return(new RouterConfiguration($row))->toArray($secrets);}
    public function frr():?array{$row=$this->db->query('SELECT * FROM router_frr_settings ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);return$row?(new RouterConfiguration($row))->toArray():null;}
    public function saveCore(array $connection,array $config,string $rendered,int $user):array
    {
        $old=$this->core(true);$password=$connection['password']!==''?$connection['password']:(string)($old['password']??'');$params=[':enabled'=>$connection['enabled'],':host'=>$connection['host'],':port'=>$connection['port'],':username'=>$connection['username'],':password'=>$this->cipher->encrypt($password),':json'=>json_encode($config,JSON_UNESCAPED_SLASHES),':rendered'=>$rendered,':user'=>$user];
        if($old){$params[':id']=$old['id'];$stmt=$this->db->prepare('UPDATE router_core_settings SET enabled=:enabled,host=:host,port=:port,username=:username,password=:password,config_json=:json,rendered_config=:rendered,updated_by=:user WHERE id=:id');}
        else $stmt=$this->db->prepare('INSERT INTO router_core_settings(enabled,host,port,username,password,config_json,rendered_config,updated_by) VALUES(:enabled,:host,:port,:username,:password,:json,:rendered,:user)');
        $stmt->execute($params);if($old&&((string)$old['host']!==$connection['host']||(int)$old['port']!==(int)$connection['port']))$this->db->exec('UPDATE router_core_settings SET known_host_key=NULL,host_key_fingerprint=NULL WHERE id='.(int)$old['id']);return$this->core()??[];
    }
    public function saveFrr(array $config,string $rendered,int $user):array{$old=$this->frr();$p=[':enabled'=>$config['enabled'],':json'=>json_encode($config,JSON_UNESCAPED_SLASHES),':rendered'=>$rendered,':user'=>$user];if($old){$p[':id']=$old['id'];$s=$this->db->prepare('UPDATE router_frr_settings SET enabled=:enabled,config_json=:json,rendered_config=:rendered,updated_by=:user WHERE id=:id');}else$s=$this->db->prepare('INSERT INTO router_frr_settings(enabled,config_json,rendered_config,updated_by) VALUES(:enabled,:json,:rendered,:user)');$s->execute($p);return$this->frr()??[];}
    public function trustCore(int $id,string $key,string $fingerprint):void{$s=$this->db->prepare('UPDATE router_core_settings SET known_host_key=:key,host_key_fingerprint=:fingerprint WHERE id=:id');$s->execute([':id'=>$id,':key'=>$key,':fingerprint'=>$fingerprint]);}
    public function deployment(string $type,string $status,string $hash,string $message,int $user):void{$s=$this->db->prepare('INSERT INTO router_config_deployments(router_type,status,config_hash,result_message,performed_by) VALUES(?,?,?,?,?)');$s->execute([$type,$status,$hash,$message,$user]);}
    public function deployments():array{return$this->db->query('SELECT d.*,u.full_name performed_by_name FROM router_config_deployments d LEFT JOIN users u ON u.id=d.performed_by ORDER BY d.id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC)?:[];}
}
