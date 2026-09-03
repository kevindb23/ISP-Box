<?php
namespace App\Modules\CgnatManagement\Validators;
final class RemovePostroutingRulesValidator
{
    public function validate(array $hashes): array
    {
        $errors=[];
        if($hashes===[])$errors[]='Select at least one POSTROUTING rule.';
        if(count($hashes)>20)$errors[]='At most 20 POSTROUTING rules can be removed at once.';
        foreach($hashes as $hash)if(!preg_match('/^[a-f0-9]{64}$/',(string)$hash)){$errors[]='A selected rule identifier is invalid.';break;}
        return $errors;
    }
}
