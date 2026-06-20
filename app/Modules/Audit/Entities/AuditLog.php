<?php

namespace App\Modules\Audit\Entities;

class AuditLog
{
    public int $id;
    public int $userId;
    public string $username;
    public string $module;
    public string $action;
    public string $description;
    public ?string $ipAddress;
    public string $createdAt;
}