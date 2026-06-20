<?php

namespace App\Modules\CgnatManagement\Services;

use App\Modules\Audit\Services\AuditService;
use App\Modules\CgnatManagement\DTOs\ApplyNatPoolDTO;
use App\Modules\CgnatManagement\DTOs\CreateNatPoolDTO;
use App\Modules\CgnatManagement\DTOs\UpdateNatPoolDTO;
use App\Modules\CgnatManagement\Entities\CgnatDeployment;
use App\Modules\CgnatManagement\Entities\NatPool;
use App\Modules\CgnatManagement\Entities\SubscriberUsage;
use App\Modules\CgnatManagement\Repositories\CgnatManagementRepository;
use InvalidArgumentException;
use Throwable;

class CgnatManagementService
{
    private CgnatManagementRepository $repo;
    private BngConnectionService $bng;
    private ?AuditService $audit;

    public function __construct(
        CgnatManagementRepository $repo,
        BngConnectionService $bng,
        ?AuditService $audit = null
    ) {
        $this->repo = $repo;
        $this->bng = $bng;
        $this->audit = $audit;
    }

    public function getIndexData(): array
    {
        $pools = $this->getPools();
        $deployments = $this->getDeployments();
        $usage = array_map(fn($x) => $x->toArray(), $this->getUsage());

        return [
            'pools' => $pools,
            'deployments' => $deployments,
            'usage' => $usage,
        ];
    }

    public function getPools(): array
    {
        return array_map(
            fn($row) => (new NatPool($row))->toArray(),
            $this->repo->getAllPools()
        );
    }

    public function getPoolById(int $id): ?array
    {
        $row = $this->repo->findPoolById($id);
        return $row ? (new NatPool($row))->toArray() : null;
    }

    public function createPool(CreateNatPoolDTO $dto): array
    {
        $id = $this->repo->createPool($dto->toArray());
        $row = $this->repo->findPoolById($id);

        $pool = $row ? (new NatPool($row))->toArray() : [];

        $this->auditLog(
            'CREATE_POOL',
            sprintf(
                'Created CGNAT pool %s Network=%s Range=%s-%s Gateway=%s',
                $pool['pool_name'] ?? ('#' . $id),
                $pool['network'] ?? '',
                $pool['range_start'] ?? '',
                $pool['range_end'] ?? '',
                $pool['gateway'] ?? ''
            )
        );

        return $pool;
    }

    public function updatePool(UpdateNatPoolDTO $dto): array
    {
        $existing = $this->repo->findPoolById($dto->id);

        if (!$existing) {
            throw new InvalidArgumentException('NAT pool not found.');
        }

        $this->repo->updatePool($dto->id, $dto->toArray());

        $row = $this->repo->findPoolById($dto->id);
        $pool = $row ? (new NatPool($row))->toArray() : [];

        $this->auditLog(
            'UPDATE_POOL',
            sprintf(
                'Updated CGNAT pool %s Network=%s Range=%s-%s Gateway=%s',
                $pool['pool_name'] ?? ('#' . $dto->id),
                $pool['network'] ?? '',
                $pool['range_start'] ?? '',
                $pool['range_end'] ?? '',
                $pool['gateway'] ?? ''
            )
        );

        return $pool;
    }

    public function deletePool(int $id): bool
    {
        $existing = $this->repo->findPoolById($id);

        if (!$existing) {
            throw new InvalidArgumentException('NAT pool not found.');
        }

        $deleted = $this->repo->deletePool($id);

        if ($deleted) {
            $this->auditLog(
                'DELETE_POOL',
                sprintf(
                    'Deleted CGNAT pool %s Network=%s Range=%s-%s',
                    $existing['pool_name'] ?? ('#' . $id),
                    $existing['network'] ?? '',
                    $existing['range_start'] ?? '',
                    $existing['range_end'] ?? ''
                )
            );
        }

        return $deleted;
    }

    public function previewPool(int $id): array
    {
        $pool = $this->repo->findPoolById($id);

        if (!$pool) {
            throw new InvalidArgumentException('NAT pool not found.');
        }

        return [
            'pool' => (new NatPool($pool))->toArray(),
            'accel' => $this->generateAccelConfig($pool),
            'frr' => $this->generateFrrConfig($pool),
        ];
    }

    public function getDeployments(?int $poolId = null): array
    {
        return array_map(
            fn($row) => (new CgnatDeployment($row))->toArray() + [
                    'pool_name' => $row['pool_name'] ?? null,
                    'pool_type' => $row['pool_type'] ?? null,
                ],
            $this->repo->getDeployments($poolId)
        );
    }

    public function getUsage(): array
    {
        return array_map(
            fn($row) => new SubscriberUsage($row),
            $this->repo->getUsageRows()
        );
    }

    public function applyPool(ApplyNatPoolDTO $dto): array
    {
        $pool = $this->repo->findPoolById($dto->pool_id);

        if (!$pool) {
            throw new InvalidArgumentException('NAT pool not found.');
        }

        $results = [];

        $svlanId = $this->extractSvlanFromPool($pool);

        if ($svlanId !== null) {
            try {
                $results['svlan_interface'] = $this->ensureSvlanInterface($svlanId);
            } catch (Throwable $e) {
                $results['svlan_interface'] = [
                    'status' => 'FAILED',
                    'vlan_id' => $svlanId,
                    'message' => $e->getMessage(),
                ];

                $this->auditLog(
                    'ENSURE_SVLAN_INTERFACE_FAILED',
                    sprintf(
                        'Failed to ensure BNG S-VLAN interface for pool %s VLAN=%d. Error: %s',
                        $pool['pool_name'] ?? ('#' . $dto->pool_id),
                        $svlanId,
                        $e->getMessage()
                    )
                );
            }
        }

        if ($dto->apply_accel) {
            $config = $this->generateAccelConfig($pool);

            $this->repo->createDeployment([
                'pool_id' => $dto->pool_id,
                'target_type' => 'ACCEL',
                'status' => 'PENDING',
                'rendered_config' => $config,
                'result_message' => 'ACCEL staged only. No restart/reload performed.',
                'applied_at' => null,
            ]);

            $results['accel'] = [
                'status' => 'PENDING',
                'config' => $config,
                'message' => 'ACCEL staged only. No restart/reload performed.',
            ];

            $this->auditLog(
                'STAGE_ACCEL_CONFIG',
                sprintf(
                    'Staged ACCEL config for CGNAT pool %s Network=%s',
                    $pool['pool_name'] ?? ('#' . $dto->pool_id),
                    $pool['network'] ?? ''
                )
            );
        }

        if ($dto->apply_frr) {
            $config = $this->generateFrrConfig($pool);

            $this->repo->createDeployment([
                'pool_id' => $dto->pool_id,
                'target_type' => 'FRR',
                'status' => 'PENDING',
                'rendered_config' => $config,
                'result_message' => 'FRR config generated only.',
                'applied_at' => null,
            ]);

            $results['frr'] = [
                'status' => 'PENDING',
                'config' => $config,
                'message' => 'FRR config generated only.',
            ];

            $this->auditLog(
                'STAGE_FRR_CONFIG',
                sprintf(
                    'Staged FRR config for CGNAT pool %s Network=%s',
                    $pool['pool_name'] ?? ('#' . $dto->pool_id),
                    $pool['network'] ?? ''
                )
            );
        }

        $this->auditLog(
            'APPLY_POOL',
            sprintf(
                'Applied/staged CGNAT pool %s. ACCEL=%s FRR=%s SVLAN=%s',
                $pool['pool_name'] ?? ('#' . $dto->pool_id),
                $dto->apply_accel ? 'YES' : 'NO',
                $dto->apply_frr ? 'YES' : 'NO',
                $svlanId !== null ? (string)$svlanId : 'N/A'
            )
        );

        return $results;
    }

    public function ensureSvlanInterface(int $vlanId): array
    {
        if ($vlanId < 1 || $vlanId > 4094) {
            throw new InvalidArgumentException('Invalid VLAN ID.');
        }

        $setting = $this->bng->getFullSetting();

        if ((int)($setting['auto_create_svlan_interface'] ?? 1) !== 1) {
            $this->auditLog(
                'ENSURE_SVLAN_INTERFACE_SKIPPED',
                sprintf(
                    'Skipped BNG S-VLAN interface creation for VLAN %d because auto-create is disabled.',
                    $vlanId
                )
            );

            return [
                'status' => 'SKIPPED',
                'vlan_id' => $vlanId,
                'message' => 'Auto-create SVLAN interface is disabled.',
            ];
        }

        $parent = trim((string)($setting['bng_parent_interface'] ?? ''));

        if ($parent === '') {
            $parent = trim((string)($setting['preferred_interface'] ?? ''));
        }

        if ($parent === '') {
            throw new InvalidArgumentException('BNG parent interface is not set.');
        }

        $iface = "{$parent}.{$vlanId}";

        $check = $this->bng->runRemoteCommand(
            $setting,
            "ip link show " . escapeshellarg($iface)
        );

        if ((int)$check['exit_code'] === 0) {
            $this->recordBngVlanInterface($vlanId, $iface);

            $this->auditLog(
                'ENSURE_SVLAN_INTERFACE_EXISTS',
                sprintf(
                    'BNG S-VLAN interface %s already exists for VLAN %d.',
                    $iface,
                    $vlanId
                )
            );

            return [
                'status' => 'EXISTS',
                'vlan_id' => $vlanId,
                'parent_interface' => $parent,
                'interface' => $iface,
                'message' => "Interface {$iface} already exists.",
                'results' => [
                    'check' => $check,
                ],
            ];
        }

        $create = $this->bng->runRemoteCommand(
            $setting,
            "ip link add link " . escapeshellarg($parent)
            . " name " . escapeshellarg($iface)
            . " type vlan id " . (int)$vlanId
        );

        if ((int)$create['exit_code'] !== 0) {
            $this->auditLog(
                'ENSURE_SVLAN_INTERFACE_FAILED',
                sprintf(
                    'Failed to create BNG S-VLAN interface %s for VLAN %d. Error: %s',
                    $iface,
                    $vlanId,
                    $create['stderr'] ?: 'Unknown error'
                )
            );

            throw new InvalidArgumentException(
                'Failed to create BNG S-VLAN interface: ' . ($create['stderr'] ?: 'Unknown error')
            );
        }

        $up = $this->bng->runRemoteCommand(
            $setting,
            "ip link set " . escapeshellarg($iface) . " up"
        );

        if ((int)$up['exit_code'] !== 0) {
            $this->auditLog(
                'ENSURE_SVLAN_INTERFACE_FAILED',
                sprintf(
                    'Failed to bring BNG S-VLAN interface %s up for VLAN %d. Error: %s',
                    $iface,
                    $vlanId,
                    $up['stderr'] ?: 'Unknown error'
                )
            );

            throw new InvalidArgumentException(
                'Failed to bring BNG S-VLAN interface up: ' . ($up['stderr'] ?: 'Unknown error')
            );
        }

        $this->recordBngVlanInterface($vlanId, $iface);

        $this->auditLog(
            'ENSURE_SVLAN_INTERFACE_CREATED',
            sprintf(
                'Created and enabled BNG S-VLAN interface %s for VLAN %d.',
                $iface,
                $vlanId
            )
        );

        return [
            'status' => 'CREATED',
            'vlan_id' => $vlanId,
            'parent_interface' => $parent,
            'interface' => $iface,
            'message' => "Interface {$iface} created and brought up.",
            'results' => [
                'check' => $check,
                'create' => $create,
                'up' => $up,
            ],
        ];
    }

    private function recordBngVlanInterface(int $vlanId, string $iface): void
    {
        if (method_exists($this->repo, 'insertBngVlanInterface')) {
            $this->repo->insertBngVlanInterface($vlanId, $iface);
        }
    }

    private function extractSvlanFromPool(array $pool): ?int
    {
        if (!empty($pool['svlan_id'])) {
            return (int)$pool['svlan_id'];
        }

        if (!empty($pool['svlan'])) {
            return (int)$pool['svlan'];
        }

        return null;
    }

    private function generateAccelConfig(array $pool): string
    {
        $gateway = trim((string)($pool['gateway'] ?? ''));
        $rangeStart = trim((string)($pool['range_start'] ?? ''));
        $rangeEnd = trim((string)($pool['range_end'] ?? ''));
        $accelPoolName = trim((string)($pool['accel_pool_name'] ?? 'pool1'));

        return "[pppoe]\n"
            . "ip-pool={$accelPoolName}\n\n"
            . "[ip]\n"
            . "gw-ip-address={$gateway}\n\n"
            . "[ip-pool]\n"
            . "gw-ip-address={$gateway}\n"
            . "{$rangeStart}-{$rangeEnd},{$accelPoolName}\n";
    }

    private function generateFrrConfig(array $pool): string
    {
        $network = trim((string)($pool['network'] ?? ''));

        return "configure terminal\n"
            . "ip route {$network} Null0\n"
            . "router bgp 65001\n"
            . " address-family ipv4 unicast\n"
            . "  network {$network}\n"
            . " exit-address-family\n"
            . "end\n"
            . "write memory\n";
    }

    private function auditLog(string $action, string $description): void
    {
        if (!$this->audit) {
            return;
        }

        try {
            $this->audit->log(
                'CGNAT',
                $action,
                $description
            );
        } catch (Throwable $e) {
        }
    }
}