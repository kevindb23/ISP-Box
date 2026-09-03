<?php
namespace App\Modules\BngManagement\Repositories;
use PDO;
final class NetworkIntegrationRepository
{
    public function __construct(private PDO $db) {}
    public function core(): ?array { return $this->jsonRow('router_core_settings'); }
    public function frr(): ?array { return $this->jsonRow('router_frr_settings'); }
    public function accel(): ?array { return $this->jsonRow('bng_accel_profiles'); }
    public function cgnat(): ?array{$row=$this->db->query('SELECT * FROM cgnat_settings ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);return$row?:null;}
    private function jsonRow(string $table): ?array{$row=$this->db->query('SELECT config_json FROM '.$table.' ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);if(!$row)return null;$config=json_decode((string)$row['config_json'],true);return is_array($config)?$config:null;}
}
