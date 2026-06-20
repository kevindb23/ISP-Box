<?php

namespace App\Modules\SubscriberPortal\Entities;

class SubscriberPortalInvoice
{
    public int $id;
    public ?string $invoice_no;
    public ?int $service_id;
    public int $subscriber_id;
    public ?int $plan_id;
    public ?string $plan_name;
    public ?string $billing_period_start;
    public ?string $billing_period_end;
    public ?string $issue_date;
    public ?string $due_date;
    public float $total_amount;
    public float $paid_amount;
    public float $balance_amount;
    public string $status;
    public ?string $notes;
    public ?string $created_at;
    public ?string $updated_at;

    public function __construct(array $data = [])
    {
        $this->id = (int)($data['id'] ?? $data['invoice_id'] ?? 0);
        $this->invoice_no = $data['invoice_no'] ?? null;
        $this->service_id = isset($data['service_id']) ? (int)$data['service_id'] : null;
        $this->subscriber_id = (int)($data['subscriber_id'] ?? 0);
        $this->plan_id = isset($data['plan_id']) ? (int)$data['plan_id'] : null;
        $this->plan_name = $data['plan_name'] ?? null;
        $this->billing_period_start = $data['billing_period_start'] ?? null;
        $this->billing_period_end = $data['billing_period_end'] ?? null;
        $this->issue_date = $data['issue_date'] ?? null;
        $this->due_date = $data['due_date'] ?? null;
        $this->total_amount = (float)($data['total_amount'] ?? 0);
        $this->paid_amount = (float)($data['paid_amount'] ?? 0);
        $this->balance_amount = (float)($data['balance_amount'] ?? 0);
        $this->status = strtoupper((string)($data['status'] ?? 'UNPAID'));
        $this->notes = $data['notes'] ?? null;
        $this->created_at = $data['created_at'] ?? null;
        $this->updated_at = $data['updated_at'] ?? null;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->id,
            'invoice_no' => $this->invoice_no,
            'service_id' => $this->service_id,
            'subscriber_id' => $this->subscriber_id,
            'plan_id' => $this->plan_id,
            'plan_name' => $this->plan_name,
            'billing_period_start' => $this->billing_period_start,
            'billing_period_end' => $this->billing_period_end,
            'issue_date' => $this->issue_date,
            'due_date' => $this->due_date,
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            'balance_amount' => $this->balance_amount,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
