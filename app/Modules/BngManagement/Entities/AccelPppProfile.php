<?php
namespace App\Modules\BngManagement\Entities;
final class AccelPppProfile
{
    public function __construct(private array $row){}
    public function toArray(): array
    {
        return ['id'=>(int)$this->row['id'],'profile_name'=>(string)$this->row['profile_name'],'config'=>$this->row['config']??[],'rendered_config'=>(string)$this->row['rendered_config'],'config_hash'=>(string)$this->row['config_hash'],'status'=>(string)$this->row['status'],'restart_required'=>(bool)$this->row['restart_required'],'staged_at'=>$this->row['staged_at']??null,'staged_by'=>$this->row['staged_by']??null,'activated_at'=>$this->row['activated_at']??null,'activated_by'=>$this->row['activated_by']??null,'last_error'=>$this->row['last_error']??null,'created_by'=>$this->row['created_by']??null,'updated_by'=>$this->row['updated_by']??null,'last_validated_at'=>$this->row['last_validated_at']??null,'created_at'=>$this->row['created_at']??null,'updated_at'=>$this->row['updated_at']??null];
    }
}
