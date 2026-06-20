<?php

namespace App\Modules\CgnatManagement\Repositories;

use PDO;

class CgnatRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function get(): ?array
    {
        $stmt = $this->db->query('SELECT * FROM cgnat_settings LIMIT 1');
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function save(array $data): void
    {
        $existing = $this->get();

        if ($existing) {
            $sql = '
                UPDATE cgnat_settings
                SET
                    enabled = :enabled,
                    inside_network = :inside_network,
                    public_start_ip = :public_start_ip,
                    public_end_ip = :public_end_ip,
                    egress_interface = :egress_interface,
                    router_next_hop = :router_next_hop,
                    remarks = :remarks
                WHERE id = :id
            ';
            $data['id'] = $existing['id'];
        } else {
            $sql = '
                INSERT INTO cgnat_settings (
                    enabled, inside_network, public_start_ip, public_end_ip,
                    egress_interface, router_next_hop, remarks
                ) VALUES (
                    :enabled, :inside_network, :public_start_ip, :public_end_ip,
                    :egress_interface, :router_next_hop, :remarks
                )
            ';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);
    }
}