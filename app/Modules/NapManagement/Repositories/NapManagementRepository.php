<?php

namespace App\Modules\NapManagement\Repositories;

use Framework\DatabaseConnection;
use PDO;
use RuntimeException;

class NapManagementRepository
{
    private PDO $db;

    public function __construct(DatabaseConnection $database)
    {
        $this->db = $database->get();
    }

    public function beginTransaction(): void
    {
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
        }
    }

    public function commit(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->commit();
        }
    }

    public function rollBack(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    private function normalizeNullableString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function normalizeNullableDecimal($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return is_numeric($value) ? $value : null;
    }

    private function normalizeStatus($value): string
    {
        $value = strtoupper(trim((string) ($value ?? 'ACTIVE')));

        return match ($value) {
            'ACTIVE' => 'ACTIVE',
            'INACTIVE' => 'INACTIVE',
            'MAINTENANCE' => 'MAINTENANCE',
            'FAULTY' => 'FAULTY',
            default => 'ACTIVE',
        };
    }

    private function normalizeFeedMode($value): string
    {
        $value = strtoupper(trim((string) ($value ?? 'DIRECT_FROM_LCP')));

        return match ($value) {
            'DIRECT_FROM_LCP' => 'DIRECT_FROM_LCP',
            'CASCADE_FROM_NAP' => 'CASCADE_FROM_NAP',
            'FIBER' => 'FIBER',
            default => 'DIRECT_FROM_LCP',
        };
    }

    private function normalizeConnectedEntityType($value): string
    {
        $value = strtoupper(trim((string) ($value ?? 'NONE')));

        return match ($value) {
            'NONE' => 'NONE',
            'SERVICE' => 'SERVICE',
            'NAP' => 'NAP',
            default => 'NONE',
        };
    }

    private function normalizePortStatus($value): string
    {
        $value = strtoupper(trim((string) ($value ?? 'AVAILABLE')));

        return match ($value) {
            'AVAILABLE' => 'AVAILABLE',
            'USED' => 'USED',
            'RESERVED' => 'RESERVED',
            'MAINTENANCE' => 'MAINTENANCE',
            default => 'AVAILABLE',
        };
    }

    private function normalizeBoxType($value): string
    {
        $value = strtoupper(trim((string) ($value ?? 'NAP')));

        return match ($value) {
            'LCP' => 'LCP',
            'NAP' => 'NAP',
            default => 'NAP',
        };
    }

    private function normalizeIntOrNull($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    public function findBoxById(int $id): ?array
    {
        $stmt = $this->db->prepare("
        SELECT *
        FROM network_boxes
        WHERE id = ?
          AND deleted_at IS NULL
        LIMIT 1
    ");
        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return $this->withMaintenanceMeta($row);
    }

    public function findSplitterByBoxId(int $boxId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM box_splitters
            WHERE box_id = ?
              AND deleted_at IS NULL
            ORDER BY id ASC
            LIMIT 1
        ");
        $stmt->execute([$boxId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findPortById(int $portId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM splitter_output_ports
            WHERE id = ?
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$portId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findActiveUplinkByBoxId(int $boxId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM box_uplink_connections
            WHERE box_id = ?
              AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$boxId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function generateNextBoxCode(string $boxType): string
    {
        $boxType = $this->normalizeBoxType($boxType);
        $prefix = $boxType === 'LCP' ? 'LCP' : 'NAP';

        $stmt = $this->db->prepare("
            SELECT box_code
            FROM network_boxes
            WHERE box_type = ?
              AND box_code LIKE ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$boxType, $prefix . '-%']);

        $lastCode = (string) ($stmt->fetchColumn() ?: '');
        if (preg_match('/^' . preg_quote($prefix, '/') . '\-(\d+)$/', $lastCode, $m)) {
            $next = ((int) $m[1]) + 1;
            return sprintf('%s-%02d', $prefix, $next);
        }

        return sprintf('%s-%02d', $prefix, 1);
    }

    public function getAllBoxes(): array
    {
        $stmt = $this->db->query("
        SELECT
            nb.id,
            nb.box_type,
            nb.box_code,
            nb.box_name,
            nb.location,
            nb.address,
            nb.latitude,
            nb.longitude,
            nb.pole_code,
            nb.olt_id,
            nb.olt_port_id,
            nb.parent_odf_id,
            nb.parent_odf_port_id,
            nb.input_port_number,
            nb.status,
            nb.remarks,
            nb.created_at,
            nb.updated_at,

            od.name AS olt_name,
            CONCAT(
                COALESCE(op.frame, 0), '/',
                COALESCE(op.slot, 0), '/',
                COALESCE(op.port, 0)
            ) AS olt_port_path,

            bs.id AS splitter_id,
            COALESCE(bs.splitter_ratio, 0) AS splitter_ratio,

            buc.id AS uplink_id,
            buc.feed_mode,
            buc.source_box_id,
            buc.source_port_id,
            buc.source_cable_id,
            buc.source_fiber_core,
            buc.splice_point,
            buc.splice_note,
            buc.is_active,

            src.box_type AS source_box_type,
            src.box_code AS source_box_code,
            src.box_name AS source_box_name,
            src.location AS source_box_location,
            src.status AS source_box_status,

            src_port.port_number AS source_port_number,

            COUNT(sop.id) AS total_ports,
            SUM(
                CASE
                    WHEN sop.connected_entity_type <> 'NONE'
                      OR sop.status <> 'AVAILABLE'
                    THEN 1 ELSE 0
                END
            ) AS used_ports,
            SUM(
                CASE
                    WHEN sop.connected_entity_type = 'NONE'
                     AND sop.status = 'AVAILABLE'
                    THEN 1 ELSE 0
                END
            ) AS free_ports

        FROM network_boxes nb
        LEFT JOIN olt_devices od
            ON od.id = nb.olt_id
        LEFT JOIN olt_ports op
            ON op.id = nb.olt_port_id
        LEFT JOIN box_splitters bs
            ON bs.box_id = nb.id
           AND bs.deleted_at IS NULL
        LEFT JOIN splitter_output_ports sop
            ON sop.splitter_id = bs.id
           AND sop.deleted_at IS NULL
        LEFT JOIN box_uplink_connections buc
            ON buc.box_id = nb.id
           AND buc.is_active = 1
        LEFT JOIN network_boxes src
            ON src.id = buc.source_box_id
           AND src.deleted_at IS NULL
        LEFT JOIN splitter_output_ports src_port
            ON src_port.id = buc.source_port_id
           AND src_port.deleted_at IS NULL
        WHERE nb.deleted_at IS NULL
        GROUP BY
            nb.id,
            nb.box_type,
            nb.box_code,
            nb.box_name,
            nb.location,
            nb.address,
            nb.latitude,
            nb.longitude,
            nb.pole_code,
            nb.olt_id,
            nb.olt_port_id,
            nb.parent_odf_id,
            nb.parent_odf_port_id,
            nb.input_port_number,
            nb.status,
            nb.remarks,
            nb.created_at,
            nb.updated_at,
            od.name,
            op.frame,
            op.slot,
            op.port,
            bs.id,
            bs.splitter_ratio,
            buc.id,
            buc.feed_mode,
            buc.source_box_id,
            buc.source_port_id,
            buc.source_cable_id,
            buc.source_fiber_core,
            buc.splice_point,
            buc.splice_note,
            buc.is_active,
            src.box_type,
            src.box_code,
            src.box_name,
            src.location,
            src.status,
            src_port.port_number
        ORDER BY
            CASE nb.box_type WHEN 'LCP' THEN 1 ELSE 2 END,
            nb.box_name ASC
    ");

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $rowStatus = strtoupper((string)($row['status'] ?? 'ACTIVE'));
            $sourceStatus = strtoupper((string)($row['source_box_status'] ?? 'ACTIVE'));

            $row['is_self_maintenance'] = $rowStatus === 'MAINTENANCE' ? 1 : 0;
            $row['is_parent_maintenance'] = $sourceStatus === 'MAINTENANCE' ? 1 : 0;
            $row['is_effective_maintenance'] = (
                $row['is_self_maintenance'] ||
                $row['is_parent_maintenance']
            ) ? 1 : 0;

            $row['maintenance_origin'] = $row['is_self_maintenance']
                ? 'SELF'
                : ($row['is_parent_maintenance'] ? 'PARENT' : 'NONE');

            $row['child_box_count'] = count($this->getChildBoxesBySourceBoxId((int)$row['id']));
            $row['has_child_boxes'] = $row['child_box_count'] > 0 ? 1 : 0;

            if ($row['is_parent_maintenance']) {
                $row['maintenance_parent_name'] = $row['source_box_name'] ?? null;
            } else {
                $row['maintenance_parent_name'] = null;
            }

            if (strtoupper((string)$row['box_type']) === 'LCP') {
                $row['uplink_label'] = !empty($row['parent_odf_id'])
                    ? (
                    !empty($row['parent_odf_name'])
                        ? $row['parent_odf_name'] . (!empty($row['parent_odf_port_number']) ? ' • Port ' . $row['parent_odf_port_number'] : '')
                        : 'ODF uplink'
                    )
                    : 'No ODF uplink';
            } else {
                $row['uplink_label'] = strtoupper((string)($row['feed_mode'] ?? '')) === 'FIBER'
                    ? 'Fiber Feed'
                    : (
                    !empty($row['source_box_name'])
                        ? $row['source_box_name'] . (!empty($row['source_port_number']) ? ' • Port ' . $row['source_port_number'] : '')
                        : 'No uplink'
                    );
            }
        }

        return $rows;
    }

    public function getBoxByIdWithStats(int $boxId): ?array
    {
        foreach ($this->getAllBoxes() as $row) {
            if ((int) $row['id'] === $boxId) {
                return $row;
            }
        }

        return null;
    }

    public function getPortsByBoxId(int $boxId): array
    {
        $splitter = $this->findSplitterByBoxId($boxId);
        if (!$splitter) {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT
                sop.*,
                child.box_type AS child_box_type,
                child.box_code AS child_box_code,
                child.box_name AS child_box_name
            FROM splitter_output_ports sop
            LEFT JOIN network_boxes child
                ON child.id = sop.connected_entity_id
               AND sop.connected_entity_type = 'NAP'
               AND child.deleted_at IS NULL
            WHERE sop.splitter_id = ?
              AND sop.deleted_at IS NULL
            ORDER BY sop.port_number ASC
        ");
        $stmt->execute([(int) $splitter['id']]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getLcpPortGrid(): array
    {
        $boxes = $this->getBoxesByType('LCP');

        foreach ($boxes as &$box) {
            $box['ports'] = $this->getPortsByBoxId((int) $box['id']);
            $box['lcp_name'] = $box['box_name'];
            $box['splitter_ports'] = (int) ($box['splitter_ratio'] ?? 0);
            $box['maintenance_mode'] = strtoupper((string) ($box['status'] ?? '')) === 'MAINTENANCE' ? 1 : 0;
            $box['olt_name'] = null;
            $box['olt_port_path'] = null;
            $box['maintenance_source'] = $box['maintenance_source'] ?? $this->extractMaintenanceSourceFromRemarks($box['remarks'] ?? null);
            $box['status_source'] = $box['maintenance_source'];
        }
        unset($box);

        return $boxes;
    }

    public function getNapPortGrid(): array
    {
        $boxes = $this->getBoxesByType('NAP');

        foreach ($boxes as &$box) {
            $box['ports'] = $this->getPortsByBoxId((int) $box['id']);
            $box['nap_name'] = $box['box_name'];
            $box['splitter_ports'] = (int) ($box['splitter_ratio'] ?? 0);
            $box['maintenance_mode'] = strtoupper((string) ($box['status'] ?? '')) === 'MAINTENANCE' ? 1 : 0;
            $box['parent_name'] = $box['source_box_name'] ?? null;
            $box['parent_port'] = $box['source_port_number'] ?? null;
            $box['parent_type'] = $box['feed_mode'] === 'CASCADE_FROM_NAP' ? 'NAP' : 'LCP';
            $box['maintenance_source'] = $box['maintenance_source'] ?? $this->extractMaintenanceSourceFromRemarks($box['remarks'] ?? null);
            $box['status_source'] = $box['maintenance_source'];
        }
        unset($box);

        return $boxes;
    }
    public function getBoxesByType(string $type): array
    {
        $stmt = $this->db->prepare("
        SELECT
            nb.*,

            odf.odf_name AS parent_odf_name,
            opf.port_number AS parent_odf_port_number,

            od.name AS olt_name,
            CONCAT(
                COALESCE(op.frame, 0), '/',
                COALESCE(op.slot, 0), '/',
                COALESCE(op.port, 0)
            ) AS olt_port_path,

            COALESCE(bs.splitter_ratio, 0) AS splitter_ratio,

            buc.id AS uplink_id,
            buc.feed_mode,
            buc.source_box_id,
            buc.source_port_id,
            buc.source_cable_id,
            buc.source_fiber_core,
            buc.splice_point,
            buc.splice_note,
            buc.is_active,

            src.box_type AS source_box_type,
            src.box_code AS source_box_code,
            src.box_name AS source_box_name,
            src.location AS source_box_location,
            src.status AS source_box_status,

            src_port.port_number AS source_port_number,

            COUNT(sp.id) AS total_ports,

            SUM(
                CASE
                    WHEN sp.id IS NULL THEN 0
                    WHEN UPPER(COALESCE(sp.status, 'AVAILABLE')) IN ('USED','RESERVED','FAULTY','MAINTENANCE')
                    THEN 1
                    WHEN UPPER(COALESCE(sp.connected_entity_type, 'NONE')) <> 'NONE'
                    THEN 1
                    ELSE 0
                END
            ) AS used_ports,

            SUM(
                CASE
                    WHEN sp.id IS NULL THEN 0
                    WHEN UPPER(COALESCE(sp.status, 'AVAILABLE')) = 'AVAILABLE'
                     AND UPPER(COALESCE(sp.connected_entity_type, 'NONE')) = 'NONE'
                    THEN 1
                    ELSE 0
                END
            ) AS free_ports

        FROM network_boxes nb

        LEFT JOIN odf_nodes odf
            ON odf.id = nb.parent_odf_id

        LEFT JOIN odf_ports opf
            ON opf.id = nb.parent_odf_port_id

        LEFT JOIN olt_devices od
            ON od.id = nb.olt_id

        LEFT JOIN olt_ports op
            ON op.id = nb.olt_port_id

        LEFT JOIN box_splitters bs
            ON bs.box_id = nb.id
           AND bs.deleted_at IS NULL

        LEFT JOIN splitter_output_ports sp
            ON sp.splitter_id = bs.id
           AND sp.deleted_at IS NULL

        LEFT JOIN box_uplink_connections buc
            ON buc.box_id = nb.id
           AND buc.is_active = 1

        LEFT JOIN network_boxes src
            ON src.id = buc.source_box_id
           AND src.deleted_at IS NULL

        LEFT JOIN splitter_output_ports src_port
            ON src_port.id = buc.source_port_id
           AND src_port.deleted_at IS NULL

        WHERE nb.box_type = ?
          AND nb.deleted_at IS NULL

        GROUP BY
            nb.id,
            nb.legacy_table,
            nb.legacy_id,
            nb.box_type,
            nb.box_code,
            nb.box_name,
            nb.location,
            nb.address,
            nb.latitude,
            nb.longitude,
            nb.pole_code,
            nb.olt_id,
            nb.olt_port_id,
            nb.parent_odf_id,
            nb.parent_odf_port_id,
            nb.input_port_number,
            nb.status,
            nb.remarks,
            nb.created_at,
            nb.updated_at,
            nb.deleted_at,
            odf.odf_name,
            opf.port_number,
            od.name,
            op.frame,
            op.slot,
            op.port,
            bs.splitter_ratio,
            buc.id,
            buc.feed_mode,
            buc.source_box_id,
            buc.source_port_id,
            buc.source_cable_id,
            buc.source_fiber_core,
            buc.splice_point,
            buc.splice_note,
            buc.is_active,
            src.box_type,
            src.box_code,
            src.box_name,
            src.location,
            src.status,
            src_port.port_number

        ORDER BY nb.id DESC
    ");

        $stmt->execute([strtoupper($type)]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $rowStatus = strtoupper((string)($row['status'] ?? 'ACTIVE'));
            $sourceStatus = strtoupper((string)($row['source_box_status'] ?? 'ACTIVE'));

            $row['is_self_maintenance'] = $rowStatus === 'MAINTENANCE' ? 1 : 0;
            $row['is_parent_maintenance'] = $sourceStatus === 'MAINTENANCE' ? 1 : 0;
            $row['is_effective_maintenance'] = (
                $row['is_self_maintenance'] ||
                $row['is_parent_maintenance']
            ) ? 1 : 0;

            $row['maintenance_origin'] = $row['is_self_maintenance']
                ? 'SELF'
                : ($row['is_parent_maintenance'] ? 'PARENT' : 'NONE');

            $row['child_box_count'] = count($this->getChildBoxesBySourceBoxId((int)$row['id']));
            $row['has_child_boxes'] = $row['child_box_count'] > 0 ? 1 : 0;

            if ($row['is_parent_maintenance']) {
                $row['maintenance_parent_name'] = $row['source_box_name'] ?? null;
            } else {
                $row['maintenance_parent_name'] = null;
            }
        }

        return $rows;
    }

    public function getBoxMaintenanceContext(int $boxId): array
    {
        $box = $this->findBoxById($boxId);

        if (!$box) {
            throw new RuntimeException('Box not found.');
        }

        $boxStatus = strtoupper((string)($box['status'] ?? 'ACTIVE'));
        $sourceBoxId = (int)($this->getActiveUplinkSourceBoxId($boxId) ?? 0);
        $parentBox = $sourceBoxId > 0 ? $this->findBoxById($sourceBoxId) : null;
        $parentStatus = strtoupper((string)($parentBox['status'] ?? 'ACTIVE'));

        $childBoxes = $this->getChildBoxesBySourceBoxId($boxId);

        return [
            'box_id' => $boxId,
            'box_type' => strtoupper((string)($box['box_type'] ?? 'NAP')),
            'box_name' => (string)($box['box_name'] ?? ''),
            'self_status' => $boxStatus,
            'parent_box_id' => $parentBox ? (int)$parentBox['id'] : null,
            'parent_box_name' => $parentBox['box_name'] ?? null,
            'parent_status' => $parentStatus,
            'is_self_maintenance' => $boxStatus === 'MAINTENANCE',
            'is_parent_maintenance' => $parentBox ? ($parentStatus === 'MAINTENANCE') : false,
            'maintenance_origin' => $boxStatus === 'MAINTENANCE'
                ? 'SELF'
                : (($parentBox && $parentStatus === 'MAINTENANCE') ? 'PARENT' : 'NONE'),
            'child_box_count' => count($childBoxes),
            'has_child_boxes' => count($childBoxes) > 0,
        ];
    }

    public function canEndBoxMaintenance(int $boxId): array
    {
        $ctx = $this->getBoxMaintenanceContext($boxId);

        if (!$ctx['is_self_maintenance'] && $ctx['is_parent_maintenance']) {
            return [
                'allowed' => false,
                'reason' => 'This box is under inherited maintenance from its parent box and cannot end maintenance directly.',
                'context' => $ctx,
            ];
        }

        if (!$ctx['is_self_maintenance']) {
            return [
                'allowed' => false,
                'reason' => 'This box is not in self-maintenance mode.',
                'context' => $ctx,
            ];
        }

        return [
            'allowed' => true,
            'reason' => null,
            'context' => $ctx,
        ];
    }

    public function canDeleteBox(int $boxId): array
    {
        $ctx = $this->getBoxMaintenanceContext($boxId);

        if ($ctx['is_parent_maintenance']) {
            return [
                'allowed' => false,
                'reason' => 'Cannot delete this child box while its parent maintenance window is active.',
                'context' => $ctx,
            ];
        }

        if ($ctx['has_child_boxes']) {
            return [
                'allowed' => false,
                'reason' => 'Cannot delete box with active downstream child boxes.',
                'context' => $ctx,
            ];
        }

        return [
            'allowed' => true,
            'reason' => null,
            'context' => $ctx,
        ];
    }
    public function getLcpCandidates(): array
    {
        return array_values(array_filter(
            $this->getBoxesByType('LCP'),
            fn(array $row) => (int) ($row['free_ports'] ?? 0) > 0
        ));
    }

    public function getNapCandidates(?int $excludeBoxId = null): array
    {
        return array_values(array_filter(
            $this->getBoxesByType('NAP'),
            function (array $row) use ($excludeBoxId) {
                if ($excludeBoxId !== null && (int) $row['id'] === $excludeBoxId) {
                    return false;
                }

                return (int) ($row['free_ports'] ?? 0) > 0;
            }
        ));
    }

    public function findPlannerNodeByReference(string $table, int $referenceId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM network_nodes
            WHERE reference_table = ?
              AND reference_id = ?
            LIMIT 1
        ");
        $stmt->execute([$table, $referenceId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getPlannerLinks(): array
    {
        $stmt = $this->db->prepare("
            SELECT
                nl.id,
                nl.link_type,
                nl.source_node_id,
                nl.target_node_id,
                nl.cable_id,
                nl.fiber_core_id,
                nl.source_port_id,
                nl.target_port_id,
                nl.connection_mode,
                nl.splice_point,
                nl.splice_note,
                nl.is_active,
                sn.node_name AS source_name,
                tn.node_name AS target_name
            FROM network_links nl
            INNER JOIN network_nodes sn ON sn.id = nl.source_node_id
            INNER JOIN network_nodes tn ON tn.id = nl.target_node_id
            WHERE nl.is_active = 1
            ORDER BY nl.id ASC
        ");
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $row['source_port_label'] = $this->getPortLabelById($row['source_port_id']);
            $row['target_port_label'] = $this->getPortLabelById($row['target_port_id']);
        }

        return $rows;
    }

    public function getPlannerNodes(): array
{
    $stmt = $this->db->prepare("
        SELECT
            id,
            node_type,
            reference_table,
            reference_id,
            node_code,
            node_name,
            location,
            latitude,
            longitude,
            status,
            remarks,
            planner_x,
            planner_y
        FROM network_nodes
        ORDER BY FIELD(node_type, 'ODF', 'LCP', 'NAP'), node_name ASC
    ");
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

    public function getAvailableLcpPorts(int $lcpId, ?int $includeCurrentPortId = null): array
    {
        $box = $this->findBoxById($lcpId);
        if (!$box || strtoupper((string)($box['box_type'] ?? '')) !== 'LCP') {
            return [];
        }

        return $this->getAvailablePortsByBoxId($lcpId, $includeCurrentPortId);
    }

    public function resetAllPlannerNodePositions(): bool
    {
        $sql = "
        UPDATE network_nodes
        SET planner_x = NULL,
            planner_y = NULL,
            updated_at = CURRENT_TIMESTAMP
    ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return true;
    }
    public function getAvailablePortsByBoxId(int $boxId, ?int $includeCurrentPortId = null): array
    {
        $box = $this->findBoxById($boxId);
        if (!$box) {
            return [];
        }

        $boxType = strtoupper((string)($box['box_type'] ?? ''));
        if (!in_array($boxType, ['LCP', 'NAP'], true)) {
            return [];
        }

        $splitter = $this->findSplitterByBoxId($boxId);
        if (!$splitter) {
            return [];
        }

        $stmt = $this->db->prepare("
        SELECT
            sop.id,
            sop.port_number,
            sop.status,
            sop.connected_entity_type,
            sop.connected_entity_id,
            sop.service_id,
            sop.reserved_label
        FROM splitter_output_ports sop
        WHERE sop.splitter_id = ?
          AND sop.deleted_at IS NULL
        ORDER BY sop.port_number ASC
    ");
        $stmt->execute([(int)$splitter['id']]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_values(array_filter($rows, function (array $row) use ($includeCurrentPortId) {
            $rowId = (int)($row['id'] ?? 0);
            $portNumber = (int)($row['port_number'] ?? 0);
            $status = strtoupper((string)($row['status'] ?? 'AVAILABLE'));
            $connectedEntityType = strtoupper((string)($row['connected_entity_type'] ?? 'NONE'));

            if ($rowId <= 0 || $portNumber <= 0) {
                return false;
            }

            if ($includeCurrentPortId !== null && $rowId === (int)$includeCurrentPortId) {
                return true;
            }

            return $status === 'AVAILABLE' && $connectedEntityType === 'NONE';
        }));
    }

    public function isBoxPortAlreadyAssignedAsUplink(int $portId, ?int $excludeChildBoxId = null): bool
    {
        if ($portId <= 0) {
            return false;
        }

        $sql = "
        SELECT COUNT(*)
        FROM box_uplink_connections
        WHERE source_port_id = ?
          AND is_active = 1
    ";

        $params = [$portId];

        if ($excludeChildBoxId !== null && $excludeChildBoxId > 0) {
            $sql .= " AND box_id <> ?";
            $params[] = $excludeChildBoxId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function markLcpPortUsed(int $portId, ?int $napId = null): bool
    {
        $stmt = $this->db->prepare("
        UPDATE splitter_output_ports
        SET
            status = 'USED',
            connected_entity_type = 'NAP',
            connected_entity_id = ?
        WHERE id = ?
          AND deleted_at IS NULL
    ");

        return $stmt->execute([
            $napId,
            $portId,
        ]);
    }

    public function markLcpPortAvailable(int $portId): bool
    {
        $stmt = $this->db->prepare("
        UPDATE splitter_output_ports
        SET
            status = CASE
                WHEN service_id IS NOT NULL THEN 'USED'
                ELSE 'AVAILABLE'
            END,
            connected_entity_type = CASE
                WHEN service_id IS NOT NULL THEN 'SERVICE'
                ELSE 'NONE'
            END,
            connected_entity_id = NULL
        WHERE id = ?
          AND deleted_at IS NULL
    ");

        return $stmt->execute([$portId]);
    }


    public function createBox(array $data): int
    {
        $boxType = $this->normalizeBoxType($data['box_type'] ?? 'NAP');
        $boxCode = trim((string) ($data['box_code'] ?? ''));

        if ($boxCode === '') {
            $boxCode = $this->generateNextBoxCode($boxType);
        }

        $stmt = $this->db->prepare("
        INSERT INTO network_boxes (
            box_type,
            box_code,
            box_name,
            location,
            address,
            latitude,
            longitude,
            pole_code,
            olt_id,
            olt_port_id,
            parent_odf_id,
            parent_odf_port_id,
            input_port_number,
            status,
            remarks
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
        $stmt->execute([
            $boxType,
            $boxCode,
            trim((string) ($data['box_name'] ?? '')),
            $this->normalizeNullableString($data['location'] ?? null),
            $this->normalizeNullableString($data['address'] ?? null),
            $this->normalizeNullableDecimal($data['latitude'] ?? null),
            $this->normalizeNullableDecimal($data['longitude'] ?? null),
            $this->normalizeNullableString($data['pole_code'] ?? null),
            $this->normalizeIntOrNull($data['olt_id'] ?? null),
            $this->normalizeIntOrNull($data['olt_port_id'] ?? null),
            $this->normalizeIntOrNull($data['parent_odf_id'] ?? null),
            $this->normalizeIntOrNull($data['parent_odf_port_id'] ?? null),
            $this->normalizeIntOrNull($data['input_port_number'] ?? null),
            $this->normalizeStatus($data['status'] ?? 'ACTIVE'),
            $this->normalizeNullableString($data['remarks'] ?? null),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function getActiveUplinkSourceBoxId(int $boxId): ?int
    {
        $stmt = $this->db->prepare("
            SELECT source_box_id
            FROM box_uplink_connections
            WHERE box_id = ?
              AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$boxId]);

        $value = $stmt->fetchColumn();

        return $value !== false && $value !== null ? (int) $value : null;
    }

    public function updateBox(int $boxId, array $data): bool
    {
        $current = $this->findBoxById($boxId);
        if (!$current) {
            throw new RuntimeException('Box not found.');
        }

        $boxCode = trim((string) ($data['box_code'] ?? ''));
        if ($boxCode === '') {
            $boxCode = (string) $current['box_code'];
        }

        $stmt = $this->db->prepare("
        UPDATE network_boxes
        SET
            box_type = ?,
            box_code = ?,
            box_name = ?,
            location = ?,
            address = ?,
            latitude = ?,
            longitude = ?,
            pole_code = ?,
            olt_id = ?,
            olt_port_id = ?,
            parent_odf_id = ?,
            parent_odf_port_id = ?,
            input_port_number = ?,
            status = ?,
            remarks = ?,
            updated_at = NOW()
        WHERE id = ?
          AND deleted_at IS NULL
    ");

        return $stmt->execute([
            $this->normalizeBoxType($data['box_type'] ?? $current['box_type']),
            $boxCode,
            trim((string) ($data['box_name'] ?? $current['box_name'])),
            $this->normalizeNullableString($data['location'] ?? $current['location']),
            $this->normalizeNullableString($data['address'] ?? $current['address']),
            $this->normalizeNullableDecimal($data['latitude'] ?? $current['latitude']),
            $this->normalizeNullableDecimal($data['longitude'] ?? $current['longitude']),
            $this->normalizeNullableString($data['pole_code'] ?? $current['pole_code']),
            $this->normalizeIntOrNull($data['olt_id'] ?? ($current['olt_id'] ?? null)),
            $this->normalizeIntOrNull($data['olt_port_id'] ?? ($current['olt_port_id'] ?? null)),
            $this->normalizeIntOrNull($data['parent_odf_id'] ?? ($current['parent_odf_id'] ?? null)),
            $this->normalizeIntOrNull($data['parent_odf_port_id'] ?? ($current['parent_odf_port_id'] ?? null)),
            $this->normalizeIntOrNull($data['input_port_number'] ?? ($current['input_port_number'] ?? null)),
            $this->normalizeStatus($data['status'] ?? $current['status']),
            $this->normalizeNullableString($data['remarks'] ?? $current['remarks']),
            $boxId,
        ]);
    }

    public function createSplitter(int $boxId, int $splitterRatio): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO box_splitters (
                box_id,
                splitter_ratio,
                splitter_role,
                status
            )
            VALUES (?, ?, 'PRIMARY', 'ACTIVE')
        ");
        $stmt->execute([$boxId, $splitterRatio]);

        return (int) $this->db->lastInsertId();
    }

    public function updateSplitter(int $splitterId, int $splitterRatio): bool
    {
        $stmt = $this->db->prepare("
            UPDATE box_splitters
            SET
                splitter_ratio = ?,
                status = 'ACTIVE'
            WHERE id = ?
              AND deleted_at IS NULL
        ");

        return $stmt->execute([$splitterRatio, $splitterId]);
    }

    public function createOutputPort(int $splitterId, int $portNumber): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO splitter_output_ports (
                splitter_id,
                port_number,
                status,
                connected_entity_type
            )
            VALUES (?, ?, 'AVAILABLE', 'NONE')
        ");
        $stmt->execute([$splitterId, $portNumber]);

        return (int) $this->db->lastInsertId();
    }

    public function rebuildSplitterPorts(int $boxId, int $newTotalPorts): void
    {
        if ($newTotalPorts <= 0) {
            throw new RuntimeException('Splitter ports must be greater than zero.');
        }

        $splitter = $this->findSplitterByBoxId($boxId);
        if (!$splitter) {
            throw new RuntimeException('Splitter not found.');
        }

        $stmt = $this->db->prepare("
        SELECT
            sop.*
        FROM splitter_output_ports sop
        WHERE sop.splitter_id = ?
          AND sop.deleted_at IS NULL
        ORDER BY sop.port_number ASC
    ");
        $stmt->execute([(int)$splitter['id']]);
        $ports = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $existingCount = count($ports);

        /*
        |--------------------------------------------------------------------------
        | Nothing to do if same size
        |--------------------------------------------------------------------------
        */
        if ($newTotalPorts === $existingCount) {
            $this->updateSplitter((int)$splitter['id'], $newTotalPorts);
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Expand
        |--------------------------------------------------------------------------
        */
        if ($newTotalPorts > $existingCount) {
            $this->updateSplitter((int)$splitter['id'], $newTotalPorts);

            for ($i = $existingCount + 1; $i <= $newTotalPorts; $i++) {
                $this->createOutputPort((int)$splitter['id'], $i);
            }

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Shrink: validate ONLY ports above new limit
        |--------------------------------------------------------------------------
        */
        $blocked = [];

        foreach ($ports as $port) {
            $portNumber = (int)($port['port_number'] ?? 0);

            if ($portNumber <= $newTotalPorts) {
                continue;
            }

            $status = strtoupper((string)($port['status'] ?? 'AVAILABLE'));
            $connectedEntityType = strtoupper((string)($port['connected_entity_type'] ?? 'NONE'));
            $serviceId = (int)($port['service_id'] ?? 0);
            $reservedLabel = trim((string)($port['reserved_label'] ?? ''));

            $reasons = [];

            if ($serviceId > 0) {
                $reasons[] = 'service';
            }

            if ($connectedEntityType !== 'NONE') {
                $reasons[] = 'connected to ' . $connectedEntityType;
            }

            if (in_array($status, ['USED', 'RESERVED', 'MAINTENANCE', 'FAULTY'], true)) {
                $reasons[] = strtolower($status);
            }

            if ($reservedLabel !== '' && !in_array('reserved', $reasons, true)) {
                $reasons[] = 'reserved';
            }

            if (!empty($reasons)) {
                $blocked[] = 'Port ' . $portNumber . ' (' . implode(', ', array_unique($reasons)) . ')';
            }
        }

        if (!empty($blocked)) {
            throw new RuntimeException(
                'Cannot reduce splitter ports to ' . $newTotalPorts .
                '. Blocked ports above the new limit: ' . implode('; ', $blocked) . '.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Safe shrink
        |--------------------------------------------------------------------------
        */
        $this->updateSplitter((int)$splitter['id'], $newTotalPorts);

        foreach ($ports as $port) {
            $portNumber = (int)($port['port_number'] ?? 0);

            if ($portNumber <= $newTotalPorts) {
                continue;
            }

            $stmtDelete = $this->db->prepare("
            UPDATE splitter_output_ports
            SET deleted_at = NOW()
            WHERE id = ?
        ");
            $stmtDelete->execute([(int)$port['id']]);
        }
    }

    public function validateOdfPortReduction(int $odfId, int $newPortCount): void
    {
        if ($newPortCount <= 0) {
            throw new RuntimeException('ODF port count must be greater than zero.');
        }

        $odf = $this->findOdfById($odfId);
        if (!$odf) {
            throw new RuntimeException('ODF not found.');
        }

        $stmt = $this->db->prepare("
        SELECT
            p.*,
            lcp.id AS linked_lcp_id,
            lcp.box_name AS linked_lcp_name
        FROM odf_ports p
        LEFT JOIN network_boxes lcp
            ON lcp.box_type = 'LCP'
           AND lcp.parent_odf_id = p.odf_id
           AND lcp.parent_odf_port_id = p.id
           AND lcp.deleted_at IS NULL
        WHERE p.odf_id = ?
        ORDER BY p.port_number ASC
    ");
        $stmt->execute([$odfId]);
        $ports = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $blocked = [];
        $currentInputPortNumber = (int)($odf['input_port_number'] ?? 0);

        foreach ($ports as $port) {
            $portNumber = (int)($port['port_number'] ?? 0);

            if ($portNumber <= $newPortCount) {
                continue;
            }

            $status = strtoupper((string)($port['status'] ?? 'AVAILABLE'));
            $linkedLcpId = (int)($port['linked_lcp_id'] ?? 0);
            $linkedLcpName = trim((string)($port['linked_lcp_name'] ?? ''));

            $reasons = [];

            if ($currentInputPortNumber > 0 && $portNumber === $currentInputPortNumber) {
                $reasons[] = 'configured as ODF input port';
            }

            if ($linkedLcpId > 0) {
                $reasons[] = 'linked to LCP' . ($linkedLcpName !== '' ? ' ' . $linkedLcpName : '');
            }

            if (in_array($status, ['USED', 'RESERVED', 'MAINTENANCE', 'FAULTY'], true)) {
                $reasons[] = strtolower($status);
            }

            if (!empty($reasons)) {
                $blocked[] = 'Port ' . $portNumber . ' (' . implode(', ', array_unique($reasons)) . ')';
            }
        }

        if (!empty($blocked)) {
            throw new RuntimeException(
                'Cannot reduce ODF ports to ' . $newPortCount .
                '. Blocked ports above the new limit: ' . implode('; ', $blocked) . '.'
            );
        }
    }

    public function validateSplitterPortReduction(int $boxId, int $newTotalPorts): void
    {
        if ($newTotalPorts <= 0) {
            throw new RuntimeException('Splitter ports must be greater than zero.');
        }

        $splitter = $this->findSplitterByBoxId($boxId);
        if (!$splitter) {
            throw new RuntimeException('Splitter not found.');
        }

        $stmt = $this->db->prepare("
        SELECT
            sop.*
        FROM splitter_output_ports sop
        WHERE sop.splitter_id = ?
          AND sop.deleted_at IS NULL
        ORDER BY sop.port_number ASC
    ");
        $stmt->execute([(int)$splitter['id']]);
        $ports = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $blocked = [];

        foreach ($ports as $port) {
            $portNumber = (int)($port['port_number'] ?? 0);

            if ($portNumber <= $newTotalPorts) {
                continue;
            }

            $status = strtoupper((string)($port['status'] ?? 'AVAILABLE'));
            $connectedEntityType = strtoupper((string)($port['connected_entity_type'] ?? 'NONE'));
            $serviceId = (int)($port['service_id'] ?? 0);
            $reservedLabel = trim((string)($port['reserved_label'] ?? ''));

            $reasons = [];

            if ($serviceId > 0) {
                $reasons[] = 'service';
            }

            if ($connectedEntityType !== 'NONE') {
                $reasons[] = 'connected to ' . $connectedEntityType;
            }

            if (in_array($status, ['USED', 'RESERVED', 'MAINTENANCE', 'FAULTY'], true)) {
                $reasons[] = strtolower($status);
            }

            if ($reservedLabel !== '' && !in_array('reserved', $reasons, true)) {
                $reasons[] = 'reserved';
            }

            if (!empty($reasons)) {
                $blocked[] = 'Port ' . $portNumber . ' (' . implode(', ', array_unique($reasons)) . ')';
            }
        }

        if (!empty($blocked)) {
            throw new RuntimeException(
                'Cannot reduce splitter ports to ' . $newTotalPorts .
                '. Blocked ports above the new limit: ' . implode('; ', $blocked) . '.'
            );
        }
    }
    public function clearActiveUplink(int $boxId): void
    {
        $stmt = $this->db->prepare("
            UPDATE box_uplink_connections
            SET
                is_active = 0,
                disconnected_at = NOW()
            WHERE box_id = ?
              AND is_active = 1
        ");
        $stmt->execute([$boxId]);
    }

    public function clearPortNapConnectionByChildBox(int $childBoxId): void
    {
        $stmt = $this->db->prepare("
            UPDATE splitter_output_ports
            SET
                connected_entity_type = 'NONE',
                connected_entity_id = NULL,
                status = CASE
                    WHEN service_id IS NOT NULL THEN 'USED'
                    ELSE 'AVAILABLE'
                END
            WHERE connected_entity_type = 'NAP'
              AND connected_entity_id = ?
        ");
        $stmt->execute([$childBoxId]);
    }

    public function createUplink(array $data): int
    {
        $sourceBoxId = (int)($data['source_box_id'] ?? 0);
        $sourceBox = $this->findBoxById($sourceBoxId);

        $sourcePortId = null;

        if ($sourceBox && strtoupper((string)($sourceBox['box_type'] ?? '')) === 'LCP') {
            $sourcePortId = $this->normalizeIntOrNull($data['source_port_id'] ?? null);
        } else {
            $sourcePortId = $this->normalizeIntOrNull($data['source_port_id'] ?? null);
        }

        $stmt = $this->db->prepare("
        INSERT INTO box_uplink_connections (
            box_id,
            feed_mode,
            source_box_id,
            source_port_id,
            source_cable_id,
            source_fiber_core,
            splice_point,
            splice_note,
            is_active,
            connected_at
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
    ");

        $stmt->execute([
            (int)$data['box_id'],
            $this->normalizeFeedMode($data['feed_mode'] ?? 'DIRECT_FROM_LCP'),
            $sourceBoxId ?: null,
            $sourcePortId,
            $this->normalizeIntOrNull($data['source_cable_id'] ?? null),
            $this->normalizeIntOrNull($data['source_fiber_core'] ?? null),
            $this->normalizeNullableString($data['splice_point'] ?? null),
            $this->normalizeNullableString($data['splice_note'] ?? null),
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function attachNapToSourcePort(int $sourcePortId, int $childNapBoxId): void
    {
        $stmt = $this->db->prepare("
            UPDATE splitter_output_ports
            SET
                connected_entity_type = 'NAP',
                connected_entity_id = ?,
                status = 'USED'
            WHERE id = ?
              AND deleted_at IS NULL
        ");
        $stmt->execute([$childNapBoxId, $sourcePortId]);
    }

    public function detachNapFromSourcePort(int $childNapBoxId): void
    {
        $stmt = $this->db->prepare("
            UPDATE splitter_output_ports
            SET
                connected_entity_type = 'NONE',
                connected_entity_id = NULL,
                status = CASE
                    WHEN service_id IS NOT NULL THEN 'USED'
                    ELSE 'AVAILABLE'
                END
            WHERE connected_entity_type = 'NAP'
              AND connected_entity_id = ?
              AND deleted_at IS NULL
        ");
        $stmt->execute([$childNapBoxId]);
    }

    public function createBoxWithSplitterAndPorts(array $data, int $portCount): int
    {
        $boxCode = trim((string)($data['box_code'] ?? ''));
        if ($boxCode === '') {
            throw new \RuntimeException('Box code is required.');
        }

        $stmt = $this->db->prepare("
        INSERT INTO network_boxes (
            box_type,
            box_code,
            box_name,
            location,
            address,
            latitude,
            longitude,
            pole_code,
            olt_id,
            olt_port_id,
            parent_odf_id,
            parent_odf_port_id,
            input_port_number,
            status,
            remarks
        ) VALUES (
            :box_type,
            :box_code,
            :box_name,
            :location,
            :address,
            :latitude,
            :longitude,
            :pole_code,
            :olt_id,
            :olt_port_id,
            :parent_odf_id,
            :parent_odf_port_id,
            :input_port_number,
            :status,
            :remarks
        )
    ");

        $stmt->execute([
            ':box_type' => strtoupper((string)$data['box_type']),
            ':box_code' => $boxCode,
            ':box_name' => trim((string)$data['box_name']),
            ':location' => $this->normalizeNullableString($data['location'] ?? null),
            ':address' => $this->normalizeNullableString($data['address'] ?? null),
            ':latitude' => $this->normalizeNullableDecimal($data['latitude'] ?? null),
            ':longitude' => $this->normalizeNullableDecimal($data['longitude'] ?? null),
            ':pole_code' => $this->normalizeNullableString($data['pole_code'] ?? null),
            ':olt_id' => $this->normalizeIntOrNull($data['olt_id'] ?? null),
            ':olt_port_id' => $this->normalizeIntOrNull($data['olt_port_id'] ?? null),
            ':parent_odf_id' => $this->normalizeIntOrNull($data['parent_odf_id'] ?? null),
            ':parent_odf_port_id' => $this->normalizeIntOrNull($data['parent_odf_port_id'] ?? null),
            ':input_port_number' => $this->normalizeIntOrNull($data['input_port_number'] ?? null),
            ':status' => $this->normalizeStatus($data['status'] ?? 'ACTIVE'),
            ':remarks' => $this->normalizeNullableString($data['remarks'] ?? null),
        ]);

        $boxId = (int)$this->db->lastInsertId();

        $splitterStmt = $this->db->prepare("
        INSERT INTO box_splitters (
            box_id,
            splitter_ratio,
            splitter_role,
            status
        ) VALUES (?, ?, 'PRIMARY', 'ACTIVE')
    ");
        $splitterStmt->execute([$boxId, $portCount]);

        $splitterId = (int)$this->db->lastInsertId();

        $portStmt = $this->db->prepare("
        INSERT INTO splitter_output_ports (
            splitter_id,
            port_number,
            status,
            connected_entity_type
        ) VALUES (?, ?, 'AVAILABLE', 'NONE')
    ");

        for ($i = 1; $i <= $portCount; $i++) {
            $portStmt->execute([$splitterId, $i]);
        }

        return $boxId;
    }

    public function updateBoxWithSplitter(int $boxId, array $data, int $portCount): bool
    {
        $boxCode = trim((string)($data['box_code'] ?? ''));
        if ($boxCode === '') {
            throw new \RuntimeException('Box code is required.');
        }

        $stmt = $this->db->prepare("
        UPDATE network_boxes
        SET
            box_type = :box_type,
            box_code = :box_code,
            box_name = :box_name,
            location = :location,
            address = :address,
            latitude = :latitude,
            longitude = :longitude,
            pole_code = :pole_code,
            olt_id = :olt_id,
            olt_port_id = :olt_port_id,
            parent_odf_id = :parent_odf_id,
            parent_odf_port_id = :parent_odf_port_id,
            input_port_number = :input_port_number,
            status = :status,
            remarks = :remarks,
            updated_at = NOW()
        WHERE id = :id
    ");

        $updated = $stmt->execute([
            ':id' => $boxId,
            ':box_type' => strtoupper((string)$data['box_type']),
            ':box_code' => $boxCode,
            ':box_name' => trim((string)$data['box_name']),
            ':location' => $this->normalizeNullableString($data['location'] ?? null),
            ':address' => $this->normalizeNullableString($data['address'] ?? null),
            ':latitude' => $this->normalizeNullableDecimal($data['latitude'] ?? null),
            ':longitude' => $this->normalizeNullableDecimal($data['longitude'] ?? null),
            ':pole_code' => $this->normalizeNullableString($data['pole_code'] ?? null),
            ':olt_id' => $this->normalizeIntOrNull($data['olt_id'] ?? null),
            ':olt_port_id' => $this->normalizeIntOrNull($data['olt_port_id'] ?? null),
            ':parent_odf_id' => $this->normalizeIntOrNull($data['parent_odf_id'] ?? null),
            ':parent_odf_port_id' => $this->normalizeIntOrNull($data['parent_odf_port_id'] ?? null),
            ':input_port_number' => $this->normalizeIntOrNull($data['input_port_number'] ?? null),
            ':status' => $this->normalizeStatus($data['status'] ?? 'ACTIVE'),
            ':remarks' => $this->normalizeNullableString($data['remarks'] ?? null),
        ]);

        $splitter = $this->findSplitterByBoxId($boxId);

        if (!$splitter) {
            $splitterId = $this->createSplitter($boxId, $portCount);

            for ($i = 1; $i <= $portCount; $i++) {
                $this->createOutputPort($splitterId, $i);
            }
        } else {
            $this->rebuildSplitterPorts($boxId, $portCount);
        }

        return $updated;
    }

    public function boxNameExistsByType(string $boxType, string $boxName, ?int $excludeId = null): bool
    {
        $sql = "
        SELECT COUNT(*) 
        FROM network_boxes
        WHERE box_type = ?
          AND UPPER(box_name) = UPPER(?)
          AND deleted_at IS NULL
    ";

        $params = [strtoupper($boxType), trim($boxName)];

        if ($excludeId !== null) {
            $sql .= " AND id <> ?";
            $params[] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn() > 0;
    }

    public function boxCodeExistsByType(string $boxType, string $boxCode, ?int $excludeId = null): bool
    {
        $sql = "
        SELECT COUNT(*)
        FROM network_boxes
        WHERE box_type = ?
          AND UPPER(box_code) = UPPER(?)
          AND deleted_at IS NULL
    ";

        $params = [strtoupper($boxType), trim($boxCode)];

        if ($excludeId !== null) {
            $sql .= " AND id <> ?";
            $params[] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn() > 0;
    }

    public function isOdfPortAlreadyAssignedToLcp(int $odfPortId, ?int $excludeLcpId = null): bool
    {
        $sql = "
        SELECT COUNT(*)
        FROM network_boxes
        WHERE box_type = 'LCP'
          AND parent_odf_port_id = ?
          AND deleted_at IS NULL
    ";

        $params = [$odfPortId];

        if ($excludeLcpId !== null) {
            $sql .= " AND id <> ?";
            $params[] = $excludeLcpId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn() > 0;
    }

    public function getNetworkObjects(): array
    {
        $stmt = $this->db->prepare("
            SELECT
                id,
                object_type,
                object_name,
                object_code,
                reference_table,
                reference_id,
                olt_device_id,
                olt_port_id,
                location,
                latitude,
                longitude,
                status,
                notes
            FROM network_objects
            ORDER BY FIELD(object_type, 'OLT', 'FDT', 'NAP', 'ONT'), object_name ASC
        ");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getNetworkObjectById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM network_objects
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createNetworkObject(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO network_objects (
                object_type,
                object_name,
                object_code,
                reference_table,
                reference_id,
                olt_device_id,
                olt_port_id,
                location,
                latitude,
                longitude,
                status,
                notes
            ) VALUES (
                :object_type,
                :object_name,
                :object_code,
                :reference_table,
                :reference_id,
                :olt_device_id,
                :olt_port_id,
                :location,
                :latitude,
                :longitude,
                :status,
                :notes
            )
        ");

        $stmt->execute([
            ':object_type' => $data['object_type'],
            ':object_name' => $data['object_name'],
            ':object_code' => $data['object_code'] ?? null,
            ':reference_table' => $data['reference_table'] ?? null,
            ':reference_id' => $data['reference_id'] ?? null,
            ':olt_device_id' => $data['olt_device_id'] ?? null,
            ':olt_port_id' => $data['olt_port_id'] ?? null,
            ':location' => $data['location'] ?? null,
            ':latitude' => $data['latitude'] ?? null,
            ':longitude' => $data['longitude'] ?? null,
            ':status' => $data['status'] ?? 'ACTIVE',
            ':notes' => $data['notes'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateNetworkObject(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE network_objects
            SET
                object_type = :object_type,
                object_name = :object_name,
                object_code = :object_code,
                olt_device_id = :olt_device_id,
                olt_port_id = :olt_port_id,
                location = :location,
                latitude = :latitude,
                longitude = :longitude,
                status = :status,
                notes = :notes
            WHERE id = :id
        ");

        return $stmt->execute([
            ':id' => $id,
            ':object_type' => $data['object_type'],
            ':object_name' => $data['object_name'],
            ':object_code' => $data['object_code'] ?? null,
            ':olt_device_id' => $data['olt_device_id'] ?? null,
            ':olt_port_id' => $data['olt_port_id'] ?? null,
            ':location' => $data['location'] ?? null,
            ':latitude' => $data['latitude'] ?? null,
            ':longitude' => $data['longitude'] ?? null,
            ':status' => $data['status'] ?? 'ACTIVE',
            ':notes' => $data['notes'] ?? null,
        ]);
    }

    public function deleteNap(int $id): bool
    {
        $nap = $this->findBoxById($id);

        if (!$nap || strtoupper((string)($nap['box_type'] ?? '')) !== 'NAP') {
            throw new RuntimeException('NAP not found.');
        }

        $deleteCheck = $this->canDeleteBox($id);
        if (!$deleteCheck['allowed']) {
            throw new RuntimeException((string)$deleteCheck['reason']);
        }

        $ports = $this->getPortsByBoxId($id);

        foreach ($ports as $port) {
            $status = strtoupper((string)($port['status'] ?? 'AVAILABLE'));
            $connectedEntityType = strtoupper((string)($port['connected_entity_type'] ?? 'NONE'));
            $serviceId = (int)($port['service_id'] ?? 0);

            if ($serviceId > 0) {
                throw new RuntimeException('Cannot delete NAP with active service-connected ports.');
            }

            if ($connectedEntityType !== 'NONE') {
                throw new RuntimeException('Cannot delete NAP with connected downstream ports.');
            }

            if (in_array($status, ['USED', 'RESERVED', 'MAINTENANCE'], true)) {
                throw new RuntimeException('Cannot delete NAP while one or more splitter ports are not available.');
            }
        }

        $activeChildren = $this->findActiveChildrenBySourceBoxId($id);
        if (!empty($activeChildren)) {
            throw new RuntimeException('Cannot delete NAP with active downstream child boxes.');
        }

        $this->beginTransaction();

        try {
            $this->clearActiveUplink($id);
            $this->detachNapFromSourcePort($id);
            $this->deletePlannerNodeByBoxId($id);
            $this->deleteBox($id);

            $this->commit();
            return true;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function findActiveChildrenBySourceBoxId(int $sourceBoxId): array
    {
        $stmt = $this->db->prepare("
        SELECT *
        FROM box_uplink_connections
        WHERE source_box_id = ?
          AND is_active = 1
    ");
        $stmt->execute([$sourceBoxId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function deleteNetworkObject(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM network_objects WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getNetworkLinks(): array
    {
        $stmt = $this->db->prepare("
            SELECT
                nl.id,
                nl.link_type,
                nl.source_node_id,
                nl.target_node_id,
                nl.cable_id,
                nl.fiber_core_id,
                nl.source_port_id,
                nl.target_port_id,
                nl.connection_mode,
                nl.splice_point,
                nl.splice_note,
                nl.is_active,
                sn.node_name AS source_name,
                sn.node_type AS source_type,
                tn.node_name AS target_name,
                tn.node_type AS target_type
            FROM network_links nl
            INNER JOIN network_nodes sn ON sn.id = nl.source_node_id
            INNER JOIN network_nodes tn ON tn.id = nl.target_node_id
            WHERE nl.is_active = 1
            ORDER BY nl.id ASC
        ");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getNetworkLinkById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM network_links
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function replaceNetworkLinkByTarget(int $targetObjectId, array $data): void
    {
        $delete = $this->db->prepare("
            DELETE FROM network_links
            WHERE target_node_id = ?
        ");
        $delete->execute([$targetObjectId]);

        $insert = $this->db->prepare("
            INSERT INTO network_links (
                link_type,
                source_node_id,
                target_node_id,
                cable_id,
                fiber_core_id,
                source_port_id,
                target_port_id,
                connection_mode,
                splice_point,
                splice_note,
                is_active
            ) VALUES (
                :link_type,
                :source_node_id,
                :target_node_id,
                :cable_id,
                :fiber_core_id,
                :source_port_id,
                :target_port_id,
                :connection_mode,
                :splice_point,
                :splice_note,
                1
            )
        ");

        $insert->execute([
            ':link_type' => $data['link_type'],
            ':source_node_id' => $data['source_node_id'],
            ':target_node_id' => $data['target_node_id'],
            ':cable_id' => $data['cable_id'] ?? null,
            ':fiber_core_id' => $data['fiber_core_id'] ?? null,
            ':source_port_id' => $data['source_port_id'] ?? null,
            ':target_port_id' => $data['target_port_id'] ?? null,
            ':connection_mode' => $data['connection_mode'] ?? null,
            ':splice_point' => $data['splice_point'] ?? null,
            ':splice_note' => $data['splice_note'] ?? ($data['notes'] ?? null),
        ]);
    }

    public function deleteNetworkLinkById(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM network_links WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getActiveParentObjectId(int $objectId): ?int
    {
        $stmt = $this->db->prepare("
            SELECT source_node_id
            FROM network_links
            WHERE target_node_id = ?
              AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$objectId]);

        $value = $stmt->fetchColumn();
        return $value !== false && $value !== null ? (int) $value : null;
    }

    public function replacePlannerLink(int $targetNodeId, array $data): void
    {
        $delete = $this->db->prepare("
        DELETE FROM network_links
        WHERE target_node_id = ?
    ");
        $delete->execute([$targetNodeId]);

        $insert = $this->db->prepare("
        INSERT INTO network_links (
            link_type,
            source_node_id,
            target_node_id,
            source_port_id,
            target_port_id,
            cable_id,
            fiber_core_id,
            connection_mode,
            splice_point,
            splice_note,
            is_active
        ) VALUES (
            :link_type,
            :source_node_id,
            :target_node_id,
            :source_port_id,
            :target_port_id,
            :cable_id,
            :fiber_core_id,
            :connection_mode,
            :splice_point,
            :splice_note,
            1
        )
    ");

        $insert->execute([
            ':link_type'       => $data['link_type'] ?? 'DISTRIBUTION',
            ':source_node_id'  => $this->normalizeIntOrNull($data['source_node_id'] ?? null),
            ':target_node_id'  => $this->normalizeIntOrNull($data['target_node_id'] ?? null),
            ':source_port_id'  => $this->normalizeIntOrNull($data['source_port_id'] ?? null),
            ':target_port_id'  => $this->normalizeIntOrNull($data['target_port_id'] ?? null),
            ':cable_id'        => $this->normalizeIntOrNull($data['cable_id'] ?? null),
            ':fiber_core_id'   => $this->normalizeIntOrNull($data['fiber_core_id'] ?? null),
            ':connection_mode' => $data['connection_mode'] ?? null,
            ':splice_point'    => $this->normalizeNullableString($data['splice_point'] ?? null),
            ':splice_note'     => $this->normalizeNullableString($data['splice_note'] ?? null),
        ]);
    }

    public function getActiveParentNodeId(int $nodeId): ?int
    {
        $stmt = $this->db->prepare("
            SELECT source_node_id
            FROM network_links
            WHERE target_node_id = ?
              AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$nodeId]);

        $value = $stmt->fetchColumn();
        return $value !== false && $value !== null ? (int) $value : null;
    }

    public function deletePlannerLinkByTargetNodeId(int $targetNodeId): void
    {
        $stmt = $this->db->prepare("
            DELETE FROM network_links
            WHERE target_node_id = ?
              AND is_active = 1
        ");
        $stmt->execute([$targetNodeId]);
    }

    public function replaceUplink(int $boxId, array $data): void
    {
        $delete = $this->db->prepare("
        DELETE FROM box_uplink_connections
        WHERE box_id = ?
    ");
        $delete->execute([$boxId]);

        $insert = $this->db->prepare("
        INSERT INTO box_uplink_connections (
            box_id,
            feed_mode,
            source_box_id,
            source_port_id,
            source_cable_id,
            source_fiber_core,
            splice_point,
            splice_note,
            is_active,
            connected_at
        ) VALUES (
            :box_id,
            :feed_mode,
            :source_box_id,
            :source_port_id,
            :source_cable_id,
            :source_fiber_core,
            :splice_point,
            :splice_note,
            1,
            NOW()
        )
    ");

        $insert->execute([
            ':box_id' => $boxId,
            ':feed_mode' => $this->normalizeFeedMode($data['feed_mode'] ?? null),
            ':source_box_id' => $this->normalizeIntOrNull($data['source_box_id'] ?? null),
            ':source_port_id' => $this->normalizeIntOrNull($data['source_port_id'] ?? null),
            ':source_cable_id' => $this->normalizeIntOrNull($data['source_cable_id'] ?? null),
            ':source_fiber_core' => $this->normalizeIntOrNull($data['source_fiber_core'] ?? null),
            ':splice_point' => $this->normalizeNullableString($data['splice_point'] ?? null),
            ':splice_note' => $this->normalizeNullableString($data['splice_note'] ?? null),
        ]);
    }

    public function deleteBox(int $boxId): void
    {
        $box = $this->findBoxById($boxId);
        if (!$box) {
            throw new RuntimeException('Box not found.');
        }

        $deleteCheck = $this->canDeleteBox($boxId);
        if (!$deleteCheck['allowed']) {
            throw new RuntimeException((string)$deleteCheck['reason']);
        }

        $childrenStmt = $this->db->prepare("
        SELECT COUNT(*)
        FROM box_uplink_connections
        WHERE source_box_id = ?
          AND is_active = 1
    ");
        $childrenStmt->execute([$boxId]);
        $childCount = (int) $childrenStmt->fetchColumn();

        if ($childCount > 0) {
            throw new RuntimeException('Cannot delete box with active downstream child boxes.');
        }

        $this->clearActiveUplink($boxId);
        $this->detachNapFromSourcePort($boxId);

        $stmt = $this->db->prepare("DELETE FROM network_boxes WHERE id = ?");
        $stmt->execute([$boxId]);
    }

    public function setBoxMaintenance(
        int $boxId,
        bool $enabled,
        ?string $reason = null,
        ?string $resolution = null,
        string $source = 'SELF'
    ): bool {
        $box = $this->findBoxById($boxId);
        if (!$box) {
            throw new RuntimeException('Box not found.');
        }

        $stmt = $this->db->prepare("
        UPDATE network_boxes
        SET
            status = ?,
            remarks = ?,
            updated_at = NOW()
        WHERE id = ?
          AND deleted_at IS NULL
    ");

        return $stmt->execute([
            $enabled ? 'MAINTENANCE' : 'ACTIVE',
            $this->buildMaintenanceRemarks($enabled, $reason, $resolution, $source),
            $boxId,
        ]);
    }

    public function getDesignProfiles(): array
    {
        $stmt = $this->db->query("
            SELECT *
            FROM network_design_profiles
            WHERE status = 'ACTIVE'
            ORDER BY profile_name ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getDesignProfileById(int $profileId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM network_design_profiles
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$profileId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getDesignProfileNapRules(int $profileId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM network_design_profile_nap_rules
            WHERE profile_id = ?
            ORDER BY nap_level ASC, id ASC
        ");
        $stmt->execute([$profileId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function isOltPortUsed(int $oltPortId, ?int $excludeBoxId = null): bool
    {
        $sql = "
            SELECT COUNT(*)
            FROM box_uplink_connections buc
            INNER JOIN network_boxes nb
                ON nb.id = buc.box_id
               AND nb.deleted_at IS NULL
            WHERE buc.olt_port_id = ?
              AND buc.is_active = 1
        ";

        $params = [$oltPortId];

        if ($excludeBoxId !== null && $excludeBoxId > 0) {
            $sql .= " AND buc.box_id <> ? ";
            $params[] = $excludeBoxId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function isFiberCoreUsed(int $fiberCoreId, ?int $excludeBoxId = null): bool
    {
        $sql = "
            SELECT COUNT(*)
            FROM box_uplink_connections buc
            INNER JOIN network_boxes nb
                ON nb.id = buc.box_id
               AND nb.deleted_at IS NULL
            WHERE buc.fiber_core_id = ?
              AND buc.is_active = 1
        ";

        $params = [$fiberCoreId];

        if ($excludeBoxId !== null && $excludeBoxId > 0) {
            $sql .= " AND buc.box_id <> ? ";
            $params[] = $excludeBoxId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function getFiberCoreById(int $fiberCoreId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                fc.*,
                fcb.cable_name,
                fcb.cable_code
            FROM fiber_cores fc
            LEFT JOIN fiber_cables fcb
                ON fcb.id = fc.cable_id
            WHERE fc.id = ?
            LIMIT 1
        ");
        $stmt->execute([$fiberCoreId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createDesignBuild(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO network_design_builds (
                build_code,
                profile_id,
                olt_id,
                olt_port_id,
                feeder_cable_id,
                feeder_fiber_core_id,
                lcp_box_id,
                build_name,
                location,
                latitude,
                longitude,
                build_status,
                remarks
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            trim((string) $data['build_code']),
            (int) $data['profile_id'],
            (int) $data['olt_id'],
            (int) $data['olt_port_id'],
            $this->normalizeIntOrNull($data['feeder_cable_id'] ?? null),
            $this->normalizeIntOrNull($data['feeder_fiber_core_id'] ?? null),
            $this->normalizeIntOrNull($data['lcp_box_id'] ?? null),
            trim((string) $data['build_name']),
            $this->normalizeNullableString($data['location'] ?? null),
            $this->normalizeNullableDecimal($data['latitude'] ?? null),
            $this->normalizeNullableDecimal($data['longitude'] ?? null),
            trim((string) ($data['build_status'] ?? 'PLANNED')),
            $this->normalizeNullableString($data['remarks'] ?? null),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateDesignBuildLcpBox(int $buildId, int $lcpBoxId, string $status = 'GENERATED'): bool
    {
        $stmt = $this->db->prepare("
            UPDATE network_design_builds
            SET
                lcp_box_id = ?,
                build_status = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        return $stmt->execute([
            $lcpBoxId,
            $status,
            $buildId,
        ]);
    }

    public function insertDesignBuildItem(
        int $buildId,
        string $itemType,
        int $itemId,
        ?string $itemLabel = null,
        int $sequenceNo = 1
    ): int {
        $stmt = $this->db->prepare("
            INSERT INTO network_design_build_items (
                build_id,
                item_type,
                item_id,
                item_label,
                sequence_no
            )
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $buildId,
            strtoupper(trim($itemType)),
            $itemId,
            $this->normalizeNullableString($itemLabel),
            $sequenceNo,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function generateNextDesignBuildCode(): string
    {
        $stmt = $this->db->query("
            SELECT build_code
            FROM network_design_builds
            ORDER BY id DESC
            LIMIT 1
        ");

        $lastCode = (string) ($stmt->fetchColumn() ?: '');

        if (preg_match('/^DESIGN\-(\d+)$/', $lastCode, $m)) {
            $next = ((int) $m[1]) + 1;
            return sprintf('DESIGN-%04d', $next);
        }

        return 'DESIGN-0001';
    }

    public function getDesignBuildById(int $buildId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM network_design_builds
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$buildId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getDesignBuildItems(int $buildId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM network_design_build_items
            WHERE build_id = ?
            ORDER BY sequence_no ASC, id ASC
        ");
        $stmt->execute([$buildId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function deleteUplinkByBoxId(int $boxId): void
    {
        $stmt = $this->db->prepare("
            DELETE FROM box_uplink_connections
            WHERE box_id = ?
        ");
        $stmt->execute([$boxId]);
    }

    public function buildTopologyForest(): array
    {
        $boxes = $this->getAllBoxes();

        $nodes = [];
        foreach ($boxes as $box) {
            $nodes[(int) $box['id']] = [
                'type' => (string) $box['box_type'],
                'id' => (int) $box['id'],
                'name' => (string) $box['box_name'],
                'box_code' => (string) ($box['box_code'] ?? ''),
                'parent_type' => null,
                'parent_name' => null,
                'used_ports' => (int) ($box['used_ports'] ?? 0),
                'free_ports' => (int) ($box['free_ports'] ?? 0),
                'splitter_ports' => (int) ($box['splitter_ratio'] ?? 0),
                'maintenance_mode' => strtoupper((string) ($box['status'] ?? '')) === 'MAINTENANCE' ? 1 : 0,
                'status' => (string) ($box['status'] ?? 'ACTIVE'),
                'location' => $box['location'] ?? null,
                'latitude' => $box['latitude'] ?? null,
                'longitude' => $box['longitude'] ?? null,
                'children' => [],
            ];
        }

        $forest = [];

        foreach ($boxes as $box) {
            $id = (int) $box['id'];
            $sourceBoxId = !empty($box['source_box_id']) ? (int) $box['source_box_id'] : null;

            if ($sourceBoxId && isset($nodes[$sourceBoxId])) {
                $nodes[$id]['parent_type'] = strtoupper((string) ($box['feed_mode'] ?? '')) === 'CASCADE_FROM_NAP' ? 'NAP' : 'LCP';
                $nodes[$id]['parent_name'] = $box['source_box_name'] ?? null;
                $nodes[$sourceBoxId]['children'][] = &$nodes[$id];
            } else {
                $forest[] = &$nodes[$id];
            }
        }

        return $forest;
    }

    /*
    |--------------------------------------------------------------------------
    | PLANNER SYNC
    |--------------------------------------------------------------------------
    */

    public function findPlannerNodeByBoxId(int $boxId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM network_nodes
            WHERE reference_table = 'network_boxes'
              AND reference_id = ?
            LIMIT 1
        ");
        $stmt->execute([$boxId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createPlannerNodeFromBox(array $box): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO network_nodes (
                node_type,
                reference_table,
                reference_id,
                node_code,
                node_name,
                location,
                latitude,
                longitude,
                status,
                remarks
            ) VALUES (
                :node_type,
                'network_boxes',
                :reference_id,
                :node_code,
                :node_name,
                :location,
                :latitude,
                :longitude,
                :status,
                :remarks
            )
        ");

        $stmt->execute([
            ':node_type' => strtoupper((string) $box['box_type']),
            ':reference_id' => (int) $box['id'],
            ':node_code' => (string) $box['box_code'],
            ':node_name' => (string) $box['box_name'],
            ':location' => $box['location'] ?? null,
            ':latitude' => $box['latitude'] ?? null,
            ':longitude' => $box['longitude'] ?? null,
            ':status' => strtoupper((string) ($box['status'] ?? 'ACTIVE')),
            ':remarks' => $box['remarks'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updatePlannerNodeFromBox(int $nodeId, array $box): bool
    {
        $stmt = $this->db->prepare("
            UPDATE network_nodes
            SET
                node_type = :node_type,
                node_code = :node_code,
                node_name = :node_name,
                location = :location,
                latitude = :latitude,
                longitude = :longitude,
                status = :status,
                remarks = :remarks,
                updated_at = NOW()
            WHERE id = :id
        ");

        return $stmt->execute([
            ':id' => $nodeId,
            ':node_type' => strtoupper((string) $box['box_type']),
            ':node_code' => (string) $box['box_code'],
            ':node_name' => (string) $box['box_name'],
            ':location' => $box['location'] ?? null,
            ':latitude' => $box['latitude'] ?? null,
            ':longitude' => $box['longitude'] ?? null,
            ':status' => strtoupper((string) ($box['status'] ?? 'ACTIVE')),
            ':remarks' => $box['remarks'] ?? null,
        ]);
    }

    public function syncPlannerNodeFromBoxId(int $boxId): ?int
    {
        $box = $this->findBoxById($boxId);
        if (!$box) {
            return null;
        }

        $node = $this->findPlannerNodeByBoxId($boxId);

        if ($node) {
            $this->updatePlannerNodeFromBox((int) $node['id'], $box);
            return (int) $node['id'];
        }

        return $this->createPlannerNodeFromBox($box);
    }

    public function deletePlannerLinksByNodeId(int $nodeId): void
    {
        $stmt = $this->db->prepare("
            DELETE FROM network_links
            WHERE source_node_id = ?
               OR target_node_id = ?
        ");
        $stmt->execute([$nodeId, $nodeId]);
    }

    public function deletePlannerNodeByBoxId(int $boxId): void
    {
        $node = $this->findPlannerNodeByBoxId($boxId);
        if (!$node) {
            return;
        }

        $nodeId = (int) $node['id'];

        $this->deletePlannerLinksByNodeId($nodeId);

        $stmt = $this->db->prepare("
            DELETE FROM network_nodes
            WHERE id = ?
        ");
        $stmt->execute([$nodeId]);
    }

    public function syncPlannerLinkFromBoxId(int $boxId): void
    {
        $targetNode = $this->findPlannerNodeByBoxId($boxId);
        if (!$targetNode) {
            return;
        }

        $targetNodeId = (int)$targetNode['id'];

        $this->deletePlannerLinkByTargetNodeId($targetNodeId);

        $box = $this->findBoxById($boxId);
        if (!$box) {
            return;
        }

        $uplink = $this->findActiveUplinkByBoxId($boxId);

        $sourcePortId = null;
        $targetPortId = null;
        $sourceNodeId = null;
        $connectionMode = 'DIRECT';

        if (strtoupper((string)($box['box_type'] ?? '')) === 'LCP') {
            $parentOdfId = (int)($box['parent_odf_id'] ?? 0);
            if ($parentOdfId <= 0) {
                return;
            }

            $sourceNode = $this->findPlannerNodeByOdfId($parentOdfId);
            if (!$sourceNode) {
                return;
            }

            $sourceNodeId = (int)$sourceNode['id'];
            $sourcePortId = $this->normalizeIntOrNull($box['parent_odf_port_id'] ?? null);
            $targetPortId = $this->normalizeIntOrNull($box['input_port_number'] ?? null);
            $connectionMode = 'DIRECT_FROM_ODF';
        } elseif (strtoupper((string)($box['box_type'] ?? '')) === 'NAP') {
            if (!$uplink || empty($uplink['source_box_id'])) {
                return;
            }

            $sourceBoxId = (int)$uplink['source_box_id'];
            $sourceNode = $this->findPlannerNodeByBoxId($sourceBoxId);
            if (!$sourceNode) {
                return;
            }

            $sourceNodeId = (int)$sourceNode['id'];

            $sourceBox = $this->findBoxById($sourceBoxId);
            if ($sourceBox && strtoupper((string)($sourceBox['box_type'] ?? '')) === 'LCP') {
                $lcpPort = $this->findLcpPortById((int)($uplink['source_port_id'] ?? 0));
                $sourcePortId = $lcpPort ? (int)$lcpPort['id'] : null;
            } else {
                $sourcePortId = $this->normalizeIntOrNull($uplink['source_port_id'] ?? null);
            }

            $targetPortId = $this->normalizeIntOrNull($box['input_port_number'] ?? null);
            $connectionMode = $uplink['feed_mode'] ?? 'DIRECT';
        }

        if (!$sourceNodeId) {
            return;
        }

        $this->replacePlannerLink($targetNodeId, [
            'link_type'       => 'DISTRIBUTION',
            'source_node_id'  => $sourceNodeId,
            'target_node_id'  => $targetNodeId,
            'source_port_id'  => $sourcePortId,
            'target_port_id'  => $targetPortId,
            'cable_id'        => $uplink['source_cable_id'] ?? null,
            'fiber_core_id'   => $uplink['source_fiber_core'] ?? null,
            'connection_mode' => $connectionMode,
            'splice_point'    => $uplink['splice_point'] ?? null,
            'splice_note'     => $uplink['splice_note'] ?? null,
        ]);
    }

public function findLcpPortById(int $portId): ?array
{
    $stmt = $this->db->prepare("
        SELECT
            sop.*,
            bs.box_id AS lcp_id
        FROM splitter_output_ports sop
        INNER JOIN box_splitters bs
            ON bs.id = sop.splitter_id
           AND bs.deleted_at IS NULL
        INNER JOIN network_boxes nb
            ON nb.id = bs.box_id
           AND nb.deleted_at IS NULL
           AND nb.box_type = 'LCP'
        WHERE sop.id = ?
          AND sop.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$portId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}


    public function findSplitterPortIdByBoxIdAndPortNumber(int $boxId, ?int $portNumber): ?int
    {
        if (!$boxId || !$portNumber) {
            return null;
        }

        $stmt = $this->db->prepare("
        SELECT sop.id
        FROM box_splitters bs
        INNER JOIN splitter_output_ports sop
            ON sop.splitter_id = bs.id
           AND sop.deleted_at IS NULL
        WHERE bs.box_id = ?
          AND bs.deleted_at IS NULL
          AND sop.port_number = ?
        ORDER BY sop.id ASC
        LIMIT 1
    ");
        $stmt->execute([$boxId, $portNumber]);

        $value = $stmt->fetchColumn();

        return $value !== false && $value !== null ? (int) $value : null;
    }

    public function getPortLabelById(?int $portId): ?string
    {
        if (!$portId) {
            return null;
        }

        $stmt = $this->db->prepare("
        SELECT CONCAT('Port ', port_number) AS port_label
        FROM splitter_output_ports
        WHERE id = ?
          AND deleted_at IS NULL
        LIMIT 1
    ");
        $stmt->execute([$portId]);

        $label = $stmt->fetchColumn();
        if ($label) {
            return (string)$label;
        }

        $stmt = $this->db->prepare("
        SELECT CONCAT('Port ', port_number) AS port_label
        FROM lcp_ports
        WHERE id = ?
          AND deleted_at IS NULL
        LIMIT 1
    ");
        $stmt->execute([$portId]);

        $label = $stmt->fetchColumn();
        if ($label) {
            return (string)$label;
        }

        $stmt = $this->db->prepare("
        SELECT
            CASE
                WHEN port_label IS NOT NULL AND TRIM(port_label) <> '' THEN port_label
                ELSE CONCAT('Port ', port_number)
            END AS port_label
        FROM odf_ports
        WHERE id = ?
        LIMIT 1
    ");
        $stmt->execute([$portId]);

        $label = $stmt->fetchColumn();
        if ($label) {
            return (string)$label;
        }

        $stmt = $this->db->prepare("
        SELECT CONCAT(
            COALESCE(frame, 0), '/',
            COALESCE(slot, 0), '/',
            COALESCE(port, 0)
        ) AS port_label
        FROM olt_ports
        WHERE id = ?
        LIMIT 1
    ");
        $stmt->execute([$portId]);

        $label = $stmt->fetchColumn();

        return $label !== false ? (string)$label : null;
    }
    public function syncPlannerProjectionFromBoxId(int $boxId): void
    {
        $this->syncPlannerNodeFromBoxId($boxId);
        $this->syncPlannerLinkFromBoxId($boxId);
    }

    public function rebuildPlannerProjection(): void
    {
        $this->db->exec("DELETE FROM network_links");
        $this->db->exec("DELETE FROM network_nodes");

        $boxes = $this->db->query("
        SELECT id
        FROM network_boxes
        WHERE deleted_at IS NULL
        ORDER BY id ASC
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($boxes as $box) {
            $this->syncPlannerNodeFromBoxId((int)$box['id']);
        }

        $odfs = $this->db->query("
        SELECT id
        FROM odf_nodes
        ORDER BY id ASC
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($odfs as $odf) {
            $this->syncPlannerNodeFromOdfId((int)$odf['id']);
        }

        foreach ($boxes as $box) {
            $this->syncPlannerLinkFromBoxId((int)$box['id']);
        }
    }

    public function isOltPortAlreadyAssignedToOdf(int $oltPortId, ?int $excludeOdfId = null): bool
    {
        if ($oltPortId <= 0) {
            return false;
        }

        $sql = "
        SELECT COUNT(*)
        FROM odf_nodes
        WHERE olt_port_id = :olt_port_id
    ";

        $params = [
            ':olt_port_id' => $oltPortId,
        ];

        if ($excludeOdfId !== null && $excludeOdfId > 0) {
            $sql .= " AND id <> :exclude_odf_id";
            $params[':exclude_odf_id'] = $excludeOdfId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn() > 0;
    }
    public function findOdfById(int $id): ?array
    {
        $stmt = $this->db->prepare("
        SELECT *
        FROM odf_nodes
        WHERE id = ?
        LIMIT 1
    ");
        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return $this->withMaintenanceMeta($row);
    }

    public function getAllOdfs(): array
    {
        $stmt = $this->db->query("
        SELECT
            o.*,
            od.name AS olt_name,
            CONCAT(
                COALESCE(op.frame, 0), '/',
                COALESCE(op.slot, 0), '/',
                COALESCE(op.port, 0)
            ) AS olt_port_path,

            COUNT(DISTINCT p.id) AS total_ports,

            COUNT(DISTINCT CASE
                WHEN (
                    o.input_port_number IS NOT NULL
                    AND o.input_port_number > 0
                    AND p.port_number = o.input_port_number
                ) THEN p.id
                WHEN lcp.id IS NOT NULL THEN p.id
                WHEN UPPER(COALESCE(p.status, 'AVAILABLE')) = 'USED' THEN p.id
                WHEN UPPER(COALESCE(p.status, 'AVAILABLE')) = 'RESERVED' THEN p.id
                WHEN UPPER(COALESCE(p.status, 'AVAILABLE')) = 'MAINTENANCE' THEN p.id
                ELSE NULL
            END) AS used_ports,

            COUNT(DISTINCT CASE
                WHEN (
                    (o.input_port_number IS NULL OR o.input_port_number <= 0 OR p.port_number <> o.input_port_number)
                    AND lcp.id IS NULL
                    AND UPPER(COALESCE(p.status, 'AVAILABLE')) = 'AVAILABLE'
                ) THEN p.id
                ELSE NULL
            END) AS free_ports

        FROM odf_nodes o
        LEFT JOIN olt_devices od
            ON od.id = o.olt_id
        LEFT JOIN olt_ports op
            ON op.id = o.olt_port_id
        LEFT JOIN odf_ports p
            ON p.odf_id = o.id
        LEFT JOIN network_boxes lcp
            ON lcp.box_type = 'LCP'
           AND lcp.parent_odf_id = o.id
           AND lcp.parent_odf_port_id = p.id
           AND lcp.deleted_at IS NULL
        GROUP BY
            o.id,
            o.odf_name,
            o.node_code,
            o.location,
            o.latitude,
            o.longitude,
            o.olt_id,
            o.olt_port_id,
            o.input_port_number,
            o.port_count,
            o.port_naming_mode,
            o.remarks,
            o.status,
            o.created_at,
            o.updated_at,
            od.name,
            op.frame,
            op.slot,
            op.port
        ORDER BY o.id DESC
    ");

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $row = $this->withMaintenanceMeta($row);
        }
        unset($row);

        return $rows;
    }

    public function createOdf(array $data): int
    {
        $nodeCode = trim((string)($data['node_code'] ?? ''));
        if ($nodeCode === '') {
            $nodeCode = trim((string)($data['odf_name'] ?? ''));
        }

        $stmt = $this->db->prepare("
        INSERT INTO odf_nodes (
            odf_name,
            node_code,
            location,
            latitude,
            longitude,
            olt_id,
            olt_port_id,
            input_port_number,
            port_count,
            port_naming_mode,
            remarks,
            status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

        $stmt->execute([
            trim((string)$data['odf_name']),
            $nodeCode,
            $this->normalizeNullableString($data['location'] ?? null),
            $this->normalizeNullableDecimal($data['latitude'] ?? null),
            $this->normalizeNullableDecimal($data['longitude'] ?? null),
            $this->normalizeIntOrNull($data['olt_id'] ?? null),
            $this->normalizeIntOrNull($data['olt_port_id'] ?? null),
            $this->normalizeIntOrNull($data['input_port_number'] ?? null),
            (int)$data['port_count'],
            trim((string)$data['port_naming_mode']),
            $this->normalizeNullableString($data['remarks'] ?? null),
            $this->normalizeStatus($data['status'] ?? 'ACTIVE'),
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function updateOdf(int $id, array $data): bool
    {
        $nodeCode = trim((string)($data['node_code'] ?? ''));
        if ($nodeCode === '') {
            $nodeCode = trim((string)($data['odf_name'] ?? ''));
        }

        $stmt = $this->db->prepare("
        UPDATE odf_nodes
        SET
            odf_name = ?,
            node_code = ?,
            location = ?,
            latitude = ?,
            longitude = ?,
            olt_id = ?,
            olt_port_id = ?,
            input_port_number = ?,
            port_count = ?,
            port_naming_mode = ?,
            remarks = ?,
            status = ?,
            updated_at = NOW()
        WHERE id = ?
    ");

        return $stmt->execute([
            trim((string)$data['odf_name']),
            $nodeCode,
            $this->normalizeNullableString($data['location'] ?? null),
            $this->normalizeNullableDecimal($data['latitude'] ?? null),
            $this->normalizeNullableDecimal($data['longitude'] ?? null),
            $this->normalizeIntOrNull($data['olt_id'] ?? null),
            $this->normalizeIntOrNull($data['olt_port_id'] ?? null),
            $this->normalizeIntOrNull($data['input_port_number'] ?? null),
            (int)$data['port_count'],
            trim((string)$data['port_naming_mode']),
            $this->normalizeNullableString($data['remarks'] ?? null),
            $this->normalizeStatus($data['status'] ?? 'ACTIVE'),
            $id,
        ]);
    }

    public function deleteOdf(int $id): void
    {
        $odf = $this->findOdfById($id);
        if (!$odf) {
            throw new RuntimeException('ODF not found.');
        }

        /*
        |--------------------------------------------------------------------------
        | Do not allow delete if any LCP is connected to this ODF
        |--------------------------------------------------------------------------
        */
        $stmtConnectedLcps = $this->db->prepare("
        SELECT COUNT(*)
        FROM network_boxes
        WHERE box_type = 'LCP'
          AND parent_odf_id = ?
          AND deleted_at IS NULL
    ");
        $stmtConnectedLcps->execute([$id]);

        $connectedLcpCount = (int) $stmtConnectedLcps->fetchColumn();
        if ($connectedLcpCount > 0) {
            throw new RuntimeException('Cannot delete ODF because one or more LCP boxes are still connected to it.');
        }

        /*
        |--------------------------------------------------------------------------
        | Do not allow delete if any ODF port is reserved / used / maintenance
        |--------------------------------------------------------------------------
        */
        $stmtUsedPorts = $this->db->prepare("
        SELECT COUNT(*)
        FROM odf_ports
        WHERE odf_id = ?
          AND UPPER(COALESCE(status, 'AVAILABLE')) <> 'AVAILABLE'
    ");
        $stmtUsedPorts->execute([$id]);

        $usedPortCount = (int) $stmtUsedPorts->fetchColumn();
        if ($usedPortCount > 0) {
            throw new RuntimeException('Cannot delete ODF because one or more ODF ports are already in use.');
        }

        /*
        |--------------------------------------------------------------------------
        | Remove planner projection first
        |--------------------------------------------------------------------------
        */
        $this->deletePlannerNodeByOdfId($id);

        /*
        |--------------------------------------------------------------------------
        | Safe delete
        |--------------------------------------------------------------------------
        */
        $stmtPorts = $this->db->prepare("
        DELETE FROM odf_ports
        WHERE odf_id = ?
    ");
        $stmtPorts->execute([$id]);

        $stmt = $this->db->prepare("
        DELETE FROM odf_nodes
        WHERE id = ?
    ");
        $stmt->execute([$id]);
    }
    public function generateNextOdfCode(): string
    {
        $stmt = $this->db->query("
        SELECT node_code
        FROM odf_nodes
        ORDER BY id DESC
        LIMIT 1
    ");

        $lastCode = (string)($stmt->fetchColumn() ?: '');

        if (preg_match('/^ODF\-(\d+)$/', $lastCode, $m)) {
            $next = ((int)$m[1]) + 1;
            return sprintf('ODF-%02d', $next);
        }

        return 'ODF-01';
    }

    public function createOdfPort(int $odfId, int $portNumber, ?string $portLabel = null): int
    {
        $stmt = $this->db->prepare("
        INSERT INTO odf_ports (
            odf_id,
            port_number,
            port_label,
            port_side,
            port_role,
            status
        )
        VALUES (?, ?, ?, 'GENERIC', 'PATCH', 'AVAILABLE')
    ");
        $stmt->execute([
            $odfId,
            $portNumber,
            $portLabel
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function deleteLcp(int $id): bool
    {
        $lcp = $this->findBoxById($id);

        if (!$lcp || strtoupper((string)($lcp['box_type'] ?? '')) !== 'LCP') {
            throw new RuntimeException('LCP not found.');
        }

        $deleteCheck = $this->canDeleteBox($id);
        if (!$deleteCheck['allowed']) {
            throw new RuntimeException((string)$deleteCheck['reason']);
        }

        $activeChildren = $this->findActiveChildrenBySourceBoxId($id);
        if (!empty($activeChildren)) {
            throw new RuntimeException('Cannot delete LCP because one or more NAP boxes are still connected to it.');
        }

        $ports = $this->getPortsByBoxId($id);

        foreach ($ports as $port) {
            $status = strtoupper((string)($port['status'] ?? 'AVAILABLE'));
            $connectedEntityType = strtoupper((string)($port['connected_entity_type'] ?? 'NONE'));
            $serviceId = (int)($port['service_id'] ?? 0);

            if ($serviceId > 0) {
                throw new RuntimeException('Cannot delete LCP because one or more splitter ports have active services.');
            }

            if ($connectedEntityType !== 'NONE') {
                throw new RuntimeException('Cannot delete LCP because one or more splitter ports are connected.');
            }

            if (in_array($status, ['USED', 'RESERVED', 'MAINTENANCE', 'FAULTY'], true)) {
                throw new RuntimeException('Cannot delete LCP because one or more splitter ports are not available.');
            }
        }

        if (!empty($lcp['parent_odf_id']) || !empty($lcp['parent_odf_port_id'])) {
            throw new RuntimeException('Cannot delete LCP while it is still connected to an ODF uplink.');
        }

        $this->beginTransaction();

        try {
            $this->deletePlannerNodeByBoxId($id);

            $splitter = $this->findSplitterByBoxId($id);
            if ($splitter) {
                $stmtDeletePorts = $this->db->prepare("
                DELETE FROM splitter_output_ports
                WHERE splitter_id = ?
            ");
                $stmtDeletePorts->execute([(int)$splitter['id']]);

                $stmtDeleteSplitter = $this->db->prepare("
                DELETE FROM box_splitters
                WHERE id = ?
            ");
                $stmtDeleteSplitter->execute([(int)$splitter['id']]);
            }

            $stmtDeleteBox = $this->db->prepare("
            DELETE FROM network_boxes
            WHERE id = ?
        ");
            $stmtDeleteBox->execute([$id]);

            $this->commit();
            return true;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }
    public function generateOdfPorts(int $odfId, int $portCount, string $namingMode = 'NUMERIC'): void
    {
        for ($i = 1; $i <= $portCount; $i++) {
            $label = strtoupper($namingMode) === 'CUSTOM' ? null : ('Port ' . $i);
            $this->createOdfPort($odfId, $i, $label);
        }
    }

    public function getOdfPorts(int $odfId): array
    {
        $stmt = $this->db->prepare("
        SELECT
            p.*,

            lcp.id AS connected_entity_id,
            'LCP' AS connected_entity_type,
            lcp.box_type AS child_box_type,
            lcp.box_code AS child_box_code,
            lcp.box_name AS child_box_name,

            CASE
                WHEN lcp.id IS NOT NULL THEN 'USED'
                WHEN EXISTS (
                    SELECT 1
                    FROM odf_nodes o2
                    WHERE o2.id = p.odf_id
                      AND o2.input_port_number = p.port_number
                ) THEN 'USED'
                WHEN UPPER(COALESCE(p.status, 'AVAILABLE')) = 'USED' THEN 'USED'
                WHEN UPPER(COALESCE(p.status, 'AVAILABLE')) = 'RESERVED' THEN 'RESERVED'
                WHEN UPPER(COALESCE(p.status, 'AVAILABLE')) = 'MAINTENANCE' THEN 'MAINTENANCE'
                ELSE 'AVAILABLE'
            END AS derived_status

        FROM odf_ports p
        LEFT JOIN network_boxes lcp
            ON lcp.box_type = 'LCP'
           AND lcp.parent_odf_id = p.odf_id
           AND lcp.parent_odf_port_id = p.id
           AND lcp.deleted_at IS NULL
        WHERE p.odf_id = ?
        ORDER BY p.port_number ASC
    ");
        $stmt->execute([$odfId]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $row['status'] = $row['derived_status'];

            if (!empty($row['child_box_name'])) {
                $row['connected_entity_name'] = $row['child_box_name'];
                $row['reserved_label'] = $row['child_box_name'];
            } else {
                $row['connected_entity_name'] = null;
                $row['reserved_label'] = null;
            }
        }

        return $rows;
    }

    public function getOdfPortGrid(): array
    {
        $odfs = $this->getAllOdfs();

        foreach ($odfs as &$odf) {
            $ports = $this->getOdfPorts((int)$odf['id']);

            $usedPorts = 0;
            $freePorts = 0;

            foreach ($ports as $port) {
                $status = strtoupper((string)($port['status'] ?? 'AVAILABLE'));

                if (in_array($status, ['USED', 'RESERVED', 'MAINTENANCE'], true)) {
                    $usedPorts++;
                } else {
                    $freePorts++;
                }
            }

            $odf['ports'] = $ports;
            $odf['box_type'] = 'ODF';
            $odf['box_name'] = $odf['odf_name'];
            $odf['box_code'] = $odf['node_code'];
            $odf['splitter_ports'] = (int)($odf['port_count'] ?? 0);
            $odf['used_ports'] = $usedPorts;
            $odf['free_ports'] = $freePorts;
            $odf['maintenance_mode'] = strtoupper((string)($odf['status'] ?? '')) === 'MAINTENANCE' ? 1 : 0;
            $odf['maintenance_source'] = $odf['maintenance_source'] ?? $this->extractMaintenanceSourceFromRemarks($odf['remarks'] ?? null);
            $odf['status_source'] = $odf['maintenance_source'];
        }
        unset($odf);

        return $odfs;
    }

    public function getOdfCandidates(): array
    {
        return array_values(array_filter(
            $this->getAllOdfs(),
            fn(array $row) => (int)($row['free_ports'] ?? 0) > 0
        ));
    }

    public function rebuildOdfPorts(int $odfId, int $newPortCount, string $namingMode = 'NUMERIC'): void
    {
        if ($newPortCount <= 0) {
            throw new RuntimeException('ODF port count must be greater than zero.');
        }

        $odf = $this->findOdfById($odfId);
        if (!$odf) {
            throw new RuntimeException('ODF not found.');
        }

        $stmt = $this->db->prepare("
        SELECT
            p.*,
            lcp.id AS linked_lcp_id,
            lcp.box_name AS linked_lcp_name
        FROM odf_ports p
        LEFT JOIN network_boxes lcp
            ON lcp.box_type = 'LCP'
           AND lcp.parent_odf_id = p.odf_id
           AND lcp.parent_odf_port_id = p.id
           AND lcp.deleted_at IS NULL
        WHERE p.odf_id = ?
        ORDER BY p.port_number ASC
    ");
        $stmt->execute([$odfId]);
        $ports = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $existingCount = count($ports);

        /*
        |--------------------------------------------------------------------------
        | Nothing to do if same size
        |--------------------------------------------------------------------------
        */
        if ($newPortCount === $existingCount) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Expand
        |--------------------------------------------------------------------------
        */
        if ($newPortCount > $existingCount) {
            for ($i = $existingCount + 1; $i <= $newPortCount; $i++) {
                $label = strtoupper($namingMode) === 'CUSTOM' ? null : ('Port ' . $i);
                $this->createOdfPort($odfId, $i, $label);
            }

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Shrink: validate ONLY ports above new limit
        |--------------------------------------------------------------------------
        */
        $blocked = [];
        $currentInputPortNumber = (int)($odf['input_port_number'] ?? 0);

        foreach ($ports as $port) {
            $portNumber = (int)($port['port_number'] ?? 0);

            if ($portNumber <= $newPortCount) {
                continue;
            }

            $status = strtoupper((string)($port['status'] ?? 'AVAILABLE'));
            $linkedLcpId = (int)($port['linked_lcp_id'] ?? 0);
            $linkedLcpName = trim((string)($port['linked_lcp_name'] ?? ''));

            $reasons = [];

            if ($currentInputPortNumber > 0 && $portNumber === $currentInputPortNumber) {
                $reasons[] = 'configured as ODF input port';
            }

            if ($linkedLcpId > 0) {
                $reasons[] = 'linked to LCP' . ($linkedLcpName !== '' ? ' ' . $linkedLcpName : '');
            }

            if (in_array($status, ['USED', 'RESERVED', 'MAINTENANCE', 'FAULTY'], true)) {
                $reasons[] = strtolower($status);
            }

            if (!empty($reasons)) {
                $blocked[] = 'Port ' . $portNumber . ' (' . implode(', ', array_unique($reasons)) . ')';
            }
        }

        if (!empty($blocked)) {
            throw new RuntimeException(
                'Cannot reduce ODF ports to ' . $newPortCount .
                '. Blocked ports above the new limit: ' . implode('; ', $blocked) . '.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Safe shrink
        |--------------------------------------------------------------------------
        */
        foreach ($ports as $port) {
            $portNumber = (int)($port['port_number'] ?? 0);

            if ($portNumber <= $newPortCount) {
                continue;
            }

            $stmtDelete = $this->db->prepare("
            DELETE FROM odf_ports
            WHERE id = ?
        ");
            $stmtDelete->execute([(int)$port['id']]);
        }
    }

    public function findPlannerNodeByOdfId(int $odfId): ?array
    {
        $stmt = $this->db->prepare("
        SELECT *
        FROM network_nodes
        WHERE reference_table = 'odf_nodes'
          AND reference_id = ?
        LIMIT 1
    ");
        $stmt->execute([$odfId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createPlannerNodeFromOdf(array $odf): int
    {
        $stmt = $this->db->prepare("
        INSERT INTO network_nodes (
            node_type,
            reference_table,
            reference_id,
            node_code,
            node_name,
            location,
            latitude,
            longitude,
            status,
            remarks
        ) VALUES (
            'ODF',
            'odf_nodes',
            :reference_id,
            :node_code,
            :node_name,
            :location,
            :latitude,
            :longitude,
            :status,
            :remarks
        )
    ");

        $stmt->execute([
            ':reference_id' => (int)$odf['id'],
            ':node_code' => (string)$odf['node_code'],
            ':node_name' => (string)$odf['odf_name'],
            ':location' => $odf['location'] ?? null,
            ':latitude' => $odf['latitude'] ?? null,
            ':longitude' => $odf['longitude'] ?? null,
            ':status' => strtoupper((string)($odf['status'] ?? 'ACTIVE')),
            ':remarks' => $odf['remarks'] ?? null,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function updatePlannerNodeFromOdf(int $nodeId, array $odf): bool
    {
        $stmt = $this->db->prepare("
        UPDATE network_nodes
        SET
            node_type = 'ODF',
            node_code = :node_code,
            node_name = :node_name,
            location = :location,
            latitude = :latitude,
            longitude = :longitude,
            status = :status,
            remarks = :remarks,
            updated_at = NOW()
        WHERE id = :id
    ");

        return $stmt->execute([
            ':id' => $nodeId,
            ':node_code' => (string)$odf['node_code'],
            ':node_name' => (string)$odf['odf_name'],
            ':location' => $odf['location'] ?? null,
            ':latitude' => $odf['latitude'] ?? null,
            ':longitude' => $odf['longitude'] ?? null,
            ':status' => strtoupper((string)($odf['status'] ?? 'ACTIVE')),
            ':remarks' => $odf['remarks'] ?? null,
        ]);
    }

    public function syncPlannerNodeFromOdfId(int $odfId): ?int
    {
        $odf = $this->findOdfById($odfId);
        if (!$odf) {
            return null;
        }

        $node = $this->findPlannerNodeByOdfId($odfId);

        if ($node) {
            $this->updatePlannerNodeFromOdf((int)$node['id'], $odf);
            return (int)$node['id'];
        }

        return $this->createPlannerNodeFromOdf($odf);
    }

    public function deletePlannerNodeByOdfId(int $odfId): void
    {
        $node = $this->findPlannerNodeByOdfId($odfId);
        if (!$node) {
            return;
        }

        $nodeId = (int)$node['id'];

        $this->deletePlannerLinksByNodeId($nodeId);

        $stmt = $this->db->prepare("
        DELETE FROM network_nodes
        WHERE id = ?
    ");
        $stmt->execute([$nodeId]);
    }

    public function setOdfMaintenance(
        int $odfId,
        bool $enabled,
        ?string $reason = null,
        ?string $resolution = null,
        string $source = 'SELF'
    ): bool {
        $odf = $this->findOdfById($odfId);
        if (!$odf) {
            throw new RuntimeException('ODF not found.');
        }

        $stmt = $this->db->prepare("
        UPDATE odf_nodes
        SET
            status = ?,
            remarks = ?,
            updated_at = NOW()
        WHERE id = ?
    ");

        return $stmt->execute([
            $enabled ? 'MAINTENANCE' : 'ACTIVE',
            $this->buildMaintenanceRemarks($enabled, $reason, $resolution, $source),
            $odfId,
        ]);
    }

    public function getLcpsByOdfId(int $odfId): array
    {
        $stmt = $this->db->prepare("
        SELECT id
        FROM network_boxes
        WHERE parent_odf_id = ?
          AND deleted_at IS NULL
    ");
        $stmt->execute([$odfId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getNapsByLcpId(int $lcpId): array
    {
        return $this->getNapChildrenBySourceBoxId($lcpId);
    }

    public function getChildBoxesBySourceBoxId(int $sourceBoxId): array
    {
        $stmt = $this->db->prepare("
        SELECT
            nb.*,
            buc.feed_mode,
            buc.source_box_id,
            buc.source_port_id,
            parent.box_name AS parent_box_name,
            parent.status AS parent_box_status
        FROM box_uplink_connections buc
        INNER JOIN network_boxes nb
            ON nb.id = buc.box_id
           AND nb.deleted_at IS NULL
        INNER JOIN network_boxes parent
            ON parent.id = buc.source_box_id
           AND parent.deleted_at IS NULL
        WHERE buc.source_box_id = ?
          AND buc.is_active = 1
        ORDER BY nb.box_type ASC, nb.box_name ASC, nb.id ASC
    ");
        $stmt->execute([$sourceBoxId]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $rowStatus = strtoupper((string)($row['status'] ?? 'ACTIVE'));
            $parentStatus = strtoupper((string)($row['parent_box_status'] ?? 'ACTIVE'));

            $row['is_self_maintenance'] = $rowStatus === 'MAINTENANCE' ? 1 : 0;
            $row['is_parent_maintenance'] = $parentStatus === 'MAINTENANCE' ? 1 : 0;
            $row['is_effective_maintenance'] = (
                $row['is_self_maintenance'] ||
                $row['is_parent_maintenance']
            ) ? 1 : 0;

            $row['maintenance_origin'] = $row['is_self_maintenance']
                ? 'SELF'
                : ($row['is_parent_maintenance'] ? 'PARENT' : 'NONE');
        }

        return $rows;
    }

    public function isBoxInMaintenance(int $boxId): bool
    {
        $box = $this->findBoxById($boxId);
        if (!$box) {
            return false;
        }

        return strtoupper((string)($box['status'] ?? 'ACTIVE')) === 'MAINTENANCE';
    }

    public function isOdfInMaintenance(int $odfId): bool
    {
        $odf = $this->findOdfById($odfId);
        if (!$odf) {
            return false;
        }

        return strtoupper((string)($odf['status'] ?? 'ACTIVE')) === 'MAINTENANCE';
    }

    public function getPortOwnerBoxStatus(int $portId): ?string
    {
        $stmt = $this->db->prepare("
        SELECT nb.status
        FROM splitter_output_ports sop
        INNER JOIN box_splitters bs
            ON bs.id = sop.splitter_id
           AND bs.deleted_at IS NULL
        INNER JOIN network_boxes nb
            ON nb.id = bs.box_id
           AND nb.deleted_at IS NULL
        WHERE sop.id = ?
          AND sop.deleted_at IS NULL
        LIMIT 1
    ");
        $stmt->execute([$portId]);

        $status = $stmt->fetchColumn();
        return $status !== false ? (string)$status : null;
    }

    public function getNapChildrenBySourceBoxId(int $sourceBoxId): array
    {
        $stmt = $this->db->prepare("
        SELECT
            nb.id,
            nb.box_name,
            nb.status,
            parent.box_name AS parent_box_name,
            parent.status AS parent_box_status
        FROM box_uplink_connections buc
        INNER JOIN network_boxes nb
            ON nb.id = buc.box_id
           AND nb.deleted_at IS NULL
        INNER JOIN network_boxes parent
            ON parent.id = buc.source_box_id
           AND parent.deleted_at IS NULL
        WHERE buc.source_box_id = ?
          AND buc.is_active = 1
          AND nb.box_type = 'NAP'
    ");
        $stmt->execute([$sourceBoxId]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $rowStatus = strtoupper((string)($row['status'] ?? 'ACTIVE'));
            $parentStatus = strtoupper((string)($row['parent_box_status'] ?? 'ACTIVE'));

            $row['is_self_maintenance'] = $rowStatus === 'MAINTENANCE' ? 1 : 0;
            $row['is_parent_maintenance'] = $parentStatus === 'MAINTENANCE' ? 1 : 0;
            $row['is_effective_maintenance'] = (
                $row['is_self_maintenance'] ||
                $row['is_parent_maintenance']
            ) ? 1 : 0;

            $row['maintenance_origin'] = $row['is_self_maintenance']
                ? 'SELF'
                : ($row['is_parent_maintenance'] ? 'PARENT' : 'NONE');
        }

        return $rows;
    }

    private function buildMaintenanceRemarks(
        bool $enabled,
        ?string $reason = null,
        ?string $resolution = null,
        string $source = 'SELF'
    ): ?string {
        $source = strtoupper(trim($source));
        if (!in_array($source, ['SELF', 'PARENT', 'CASCADE', 'INHERITED'], true)) {
            $source = 'SELF';
        }

        $parts = [
            $enabled ? 'MAINTENANCE' : 'ACTIVE',
            'SRC=' . $source,
        ];

        $reason = $this->normalizeNullableString($reason);
        $resolution = $this->normalizeNullableString($resolution);

        if ($reason !== null) {
            $parts[] = 'REASON=' . $reason;
        }

        if ($resolution !== null) {
            $parts[] = 'RESOLUTION=' . $resolution;
        }

        $remarks = trim(implode(' | ', array_filter($parts)));
        return $remarks !== '' ? $remarks : null;
    }

    private function extractMaintenanceSourceFromRemarks(?string $remarks): string
    {
        $remarks = strtoupper((string)($remarks ?? ''));

        if (preg_match('/(?:^|\|)\s*SRC\s*=\s*(SELF|PARENT|CASCADE|INHERITED)\s*(?:\||$)/', $remarks, $m)) {
            return strtoupper(trim((string)$m[1]));
        }

        return 'SELF';
    }

    private function withMaintenanceMeta(array $row): array
    {
        $status = strtoupper((string)($row['status'] ?? 'ACTIVE'));
        $source = $this->extractMaintenanceSourceFromRemarks($row['remarks'] ?? null);

        if ($status !== 'MAINTENANCE') {
            $source = 'SELF';
        }

        $row['maintenance_source'] = $source;
        $row['status_source'] = $source;
        $row['maintenance_mode'] = $status === 'MAINTENANCE' ? 1 : 0;

        return $row;
    }

    public function savePlannerNodePosition(int $nodeId, float $x, float $y): bool
    {
        $sql = "
        UPDATE network_nodes
        SET planner_x = :planner_x,
            planner_y = :planner_y,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':planner_x', $x);
        $stmt->bindValue(':planner_y', $y);
        $stmt->bindValue(':id', $nodeId, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public function clearPlannerNodePosition(int $nodeId): bool
    {
        $sql = "
        UPDATE network_nodes
        SET planner_x = NULL,
            planner_y = NULL,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $nodeId, \PDO::PARAM_INT);

        return $stmt->execute();
    }

}