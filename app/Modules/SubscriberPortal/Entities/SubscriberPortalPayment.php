<?php

namespace App\Modules\SubscriberPortal\Entities;

class SubscriberPortalPayment
{
    public int $id;
    public ?string $payment_no;
    public ?int $invoice_id;
    public ?string $invoice_no;
    public int $subscriber_id;
    public ?int $service_id;
    public float $amount;
    public ?string $payment_date;
    public string $method;
    public ?string $reference_no;
    public string $payment_status;
    public ?string $remarks;
    public ?string $created_at;
    public ?string $updated_at;

    public function __construct(array $data = [])
    {
        $this->id = (int)($data['id'] ?? $data['payment_id'] ?? 0);
        $this->payment_no = $data['payment_no'] ?? null;
        $this->invoice_id = isset($data['invoice_id']) ? (int)$data['invoice_id'] : null;
        $this->invoice_no = $data['invoice_no'] ?? null;
        $this->subscriber_id = (int)($data['subscriber_id'] ?? 0);
        $this->service_id = isset($data['service_id']) ? (int)$data['service_id'] : null;
        $this->amount = (float)($data['amount'] ?? 0);
        $this->payment_date = $data['payment_date'] ?? null;
        $this->method = strtoupper((string)($data['method'] ?? 'CASH'));
        $this->reference_no = $data['reference_no'] ?? null;
        $this->payment_status = strtoupper((string)($data['payment_status'] ?? 'POSTED'));
        $this->remarks = $data['remarks'] ?? null;
        $this->created_at = $data['created_at'] ?? null;
        $this->updated_at = $data['updated_at'] ?? null;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'payment_id' => $this->id,
            'payment_no' => $this->payment_no,
            'invoice_id' => $this->invoice_id,
            'invoice_no' => $this->invoice_no,
            'subscriber_id' => $this->subscriber_id,
            'service_id' => $this->service_id,
            'amount' => $this->amount,
            'payment_date' => $this->payment_date,
            'method' => $this->method,
            'reference_no' => $this->reference_no,
            'payment_status' => $this->payment_status,
            'remarks' => $this->remarks,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}