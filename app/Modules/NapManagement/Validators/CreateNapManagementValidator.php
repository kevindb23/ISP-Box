<?php

namespace App\Modules\NapManagement\Validators;

class CreateNapManagementValidator
{
    public function validateLcp(array $data): array
    {
        $errors = [];

        $name = trim((string)($data['lcp_name'] ?? ''));
        if ($name === '') {
            $errors['lcp_name'][] = 'LCP name is required.';
        }

        $boxCode = trim((string)($data['box_code'] ?? ''));
        if ($boxCode === '') {
            $errors['box_code'][] = 'LCP code is required.';
        }

        $totalPorts = (int)($data['total_ports'] ?? 0);
        if ($totalPorts <= 0) {
            $errors['total_ports'][] = 'Splitter ports must be greater than 0.';
        }

        $parentOdfId = (int)($data['parent_odf_id'] ?? 0);
        if ($parentOdfId <= 0) {
            $errors['parent_odf_id'][] = 'Parent ODF is required.';
        }

        $parentOdfPortId = (int)($data['parent_odf_port_id'] ?? 0);
        if ($parentOdfPortId <= 0) {
            $errors['parent_odf_port_id'][] = 'ODF port is required.';
        }

        return $errors;
    }
    public function validateNap(array $data): array
    {
        $errors = [];

        $napName = trim((string)($data['nap_name'] ?? ''));
        $parentType = strtoupper(trim((string)($data['parent_type'] ?? '')));
        $parentLcpId = (int)($data['parent_lcp_id'] ?? 0);
        $parentNapId = (int)($data['parent_nap_id'] ?? 0);
        $parentPortId = (int)($data['parent_port_id'] ?? 0);
        $splitterPorts = (int)($data['splitter_ports'] ?? 0);
        $latitude = trim((string)($data['latitude'] ?? ''));
        $longitude = trim((string)($data['longitude'] ?? ''));
        $status = strtolower(trim((string)($data['status'] ?? 'active')));

        if ($napName === '') {
            $errors['nap_name'] = 'NAP name is required.';
        }

        if (!in_array($parentType, ['LCP', 'NAP'], true)) {
            $errors['parent_type'] = 'Parent type must be LCP or NAP.';
        }

        if ($parentType === 'LCP' && $parentLcpId <= 0) {
            $errors['parent_lcp_id'] = 'Parent LCP is required.';
        }

        if ($parentType === 'NAP' && $parentNapId <= 0) {
            $errors['parent_nap_id'] = 'Parent NAP is required.';
        }

        if ($parentPortId <= 0) {
            $errors['parent_port_id'] = 'Parent port is required.';
        }

        if ($splitterPorts <= 0) {
            $errors['splitter_ports'] = 'Splitter ports must be greater than 0.';
        }

        if ($latitude !== '' && !is_numeric($latitude)) {
            $errors['latitude'] = 'Latitude must be numeric.';
        }

        if ($longitude !== '' && !is_numeric($longitude)) {
            $errors['longitude'] = 'Longitude must be numeric.';
        }

        if (!in_array($status, ['active', 'inactive', 'maintenance'], true)) {
            $errors['status'] = 'Status must be active, inactive, or maintenance.';
        }

        return $errors;
    }
}