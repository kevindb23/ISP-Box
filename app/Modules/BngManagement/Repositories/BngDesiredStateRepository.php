<?php
namespace App\Modules\BngManagement\Repositories;
use PDO;
final class BngDesiredStateRepository
{
    public function __construct(private PDO $db){}
    public function interfaces(): array { return $this->db->query("SELECT vlan_id,interface,status FROM bng_vlan_interfaces WHERE status='UP' ORDER BY LENGTH(interface),interface")->fetchAll(PDO::FETCH_ASSOC)?:[]; }
    public function cgnat(): ?array { $row=$this->db->query('SELECT * FROM cgnat_settings WHERE enabled=1 ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);return $row?:null; }
    public function dependentCounts(): array
    {
        return ['deployed_vlans'=>(int)$this->db->query("SELECT COUNT(*) FROM network_vlans WHERE deployment_status='DEPLOYED'")->fetchColumn(),'cgnat_enabled'=>(int)$this->db->query("SELECT COUNT(*) FROM cgnat_settings WHERE enabled=1")->fetchColumn(),'staged_profiles'=>(int)$this->db->query("SELECT COUNT(*) FROM bng_accel_profiles WHERE status IN ('DRAFT','STAGED','ACTIVE')")->fetchColumn(),'frr_profiles'=>(int)$this->db->query('SELECT COUNT(*) FROM router_frr_settings')->fetchColumn()];
    }
}
