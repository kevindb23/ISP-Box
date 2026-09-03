<?php
namespace App\Modules\CgnatManagement\DTOs;
final class RemovePostroutingRulesDTO
{
    public function __construct(public readonly array $ruleHashes) {}
    public static function fromArray(array $data): self
    {
        return new self(array_values(array_unique(array_map('strval', is_array($data['rule_hashes'] ?? null) ? $data['rule_hashes'] : []))));
    }
}
