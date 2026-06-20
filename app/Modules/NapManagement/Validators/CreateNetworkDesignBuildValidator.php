<?php

namespace App\Modules\NapManagement\Validators;

use App\Modules\NapManagement\Repositories\NapManagementRepository;

class CreateNetworkDesignBuildValidator
{
    private NapManagementRepository $repo;

    public function __construct(NapManagementRepository $repo)
    {
        $this->repo = $repo;
    }

    public function validate(array $data): array
    {
        $errors = [];

        $profileId = (int)($data['profile_id'] ?? 0);
        $oltId = (int)($data['olt_id'] ?? 0);
        $oltPortId = (int)($data['olt_port_id'] ?? 0);
        $feederCableId = isset($data['feeder_cable_id']) && $data['feeder_cable_id'] !== ''
            ? (int)$data['feeder_cable_id']
            : null;
        $feederFiberCoreId = isset($data['feeder_fiber_core_id']) && $data['feeder_fiber_core_id'] !== ''
            ? (int)$data['feeder_fiber_core_id']
            : null;
        $buildName = trim((string)($data['build_name'] ?? ''));
        $lcpName = trim((string)($data['lcp_name'] ?? ''));
        $latitude = trim((string)($data['latitude'] ?? ''));
        $longitude = trim((string)($data['longitude'] ?? ''));
        $napCount = (int)($data['nap_count'] ?? 0);

        if ($profileId <= 0) {
            $errors['profile_id'] = 'Design profile is required.';
        }

        if ($oltId <= 0) {
            $errors['olt_id'] = 'OLT device is required.';
        }

        if ($oltPortId <= 0) {
            $errors['olt_port_id'] = 'OLT port is required.';
        }

        if ($buildName === '') {
            $errors['build_name'] = 'Build name is required.';
        }

        if ($lcpName === '') {
            $errors['lcp_name'] = 'LCP name is required.';
        }

        if ($napCount <= 0) {
            $errors['nap_count'] = 'NAP count must be greater than 0.';
        }

        if ($latitude !== '' && !is_numeric($latitude)) {
            $errors['latitude'] = 'Latitude must be numeric.';
        }

        if ($longitude !== '' && !is_numeric($longitude)) {
            $errors['longitude'] = 'Longitude must be numeric.';
        }

        if (!empty($errors)) {
            return $errors;
        }

        $profile = $this->repo->getDesignProfileById($profileId);
        if (!$profile) {
            $errors['profile_id'] = 'Selected design profile was not found.';
            return $errors;
        }

        if (strtoupper((string)($profile['status'] ?? '')) !== 'ACTIVE') {
            $errors['profile_id'] = 'Selected design profile is not active.';
        }

        if ($napCount > (int)($profile['max_nap_count'] ?? 0)) {
            $errors['nap_count'] = 'NAP count exceeds the selected profile limit.';
        }

        if ((int)($profile['require_feeder_fiber_core'] ?? 0) === 1 && $feederFiberCoreId === null) {
            $errors['feeder_fiber_core_id'] = 'Feeder fiber core is required for this design profile.';
        }

        if ($this->repo->isOltPortUsed($oltPortId)) {
            $errors['olt_port_id'] = 'OLT port is already in use by another active design/uplink.';
        }

        if ($feederFiberCoreId !== null && $this->repo->isFiberCoreUsed($feederFiberCoreId)) {
            $errors['feeder_fiber_core_id'] = 'Feeder fiber core is already in use.';
        }

        if ($feederFiberCoreId !== null) {
            $fiberCore = $this->repo->getFiberCoreById($feederFiberCoreId);
            if (!$fiberCore) {
                $errors['feeder_fiber_core_id'] = 'Selected feeder fiber core was not found.';
            } else {
                if ($feederCableId !== null && (int)($fiberCore['cable_id'] ?? 0) !== $feederCableId) {
                    $errors['feeder_fiber_core_id'] = 'Selected fiber core does not belong to the selected feeder cable.';
                }
            }
        }

        return $errors;
    }
}