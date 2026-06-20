<?php

namespace App\Modules\NapManagement\Validators;

class UpdateNapManagementValidator
{
    private CreateNapManagementValidator $baseValidator;

    public function __construct()
    {
        $this->baseValidator = new CreateNapManagementValidator();
    }

    public function validateLcp(int $id, array $data): array
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

    public function validateNap(int $id, array $data): array
    {
        $errors = [];

        if ($id <= 0) {
            $errors['id'] = 'Invalid NAP ID.';
            return $errors;
        }

        $errors = array_merge($errors, $this->baseValidator->validateNap($data));

        $parentType = strtoupper(trim((string)($data['parent_type'] ?? '')));
        $parentNapId = (int)($data['parent_nap_id'] ?? 0);

        if ($parentType === 'NAP' && $parentNapId === $id) {
            $errors['parent_nap_id'] = 'NAP cannot be its own parent.';
        }

        return $errors;
    }
}