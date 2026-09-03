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
                    bng_interface = :bng_interface,
                    public_start_ip = :public_start_ip,
                    public_end_ip = :public_end_ip,
                    egress_interface = :egress_interface
                WHERE id = :id
            ';
            $data['id'] = $existing['id'];
        } else {
            $sql = '
                INSERT INTO cgnat_settings (
                    enabled, inside_network, bng_interface, public_start_ip, public_end_ip,
                    egress_interface
                ) VALUES (
                    :enabled, :inside_network, :bng_interface, :public_start_ip, :public_end_ip,
                    :egress_interface
                )
            ';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);
    }

    public function markApplied(array $state): void
    {
        $existing = $this->get();
        if (!$existing) return;
        $stmt = $this->db->prepare('UPDATE cgnat_settings SET applied_state_json = :state, applied_at = NOW() WHERE id = :id');
        $stmt->execute([':state' => json_encode($state, JSON_UNESCAPED_SLASHES), ':id' => $existing['id']]);
    }

    public function appliedState(): ?array
    {
        $row = $this->get();
        if (!$row || empty($row['applied_state_json'])) return null;
        $decoded = json_decode((string)$row['applied_state_json'], true);
        return is_array($decoded) ? $decoded : null;
    }
}
