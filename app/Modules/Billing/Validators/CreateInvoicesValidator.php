<?php

namespace App\Modules\Billing\Validators;

use App\Modules\Billing\DTOs\BillingSettingsDTO;
use App\Modules\Billing\DTOs\CreateAdjustmentDTO;
use App\Modules\Billing\DTOs\CreateInvoicesDTO;
use App\Modules\Billing\DTOs\CreatePaymentDTO;

final class CreateInvoicesValidator
{
    public function invoice(CreateInvoicesDTO $dto): array
    {
        $errors = [];
        if ($dto->serviceId <= 0) $errors['service_id'] = 'Service is required.';
        if ($dto->discountAmount < 0) $errors['discount_amount'] = 'Discount cannot be negative.';
        if ($dto->taxAmount < 0) $errors['tax_amount'] = 'Tax cannot be negative.';
        foreach ([
            'billing_period_start' => $dto->billingPeriodStart,
            'billing_period_end' => $dto->billingPeriodEnd,
            'issue_date' => $dto->issueDate,
            'due_date' => $dto->dueDate,
        ] as $field => $date) {
            if ($date !== null && !$this->dateIsValid($date)) $errors[$field] = 'Date must use YYYY-MM-DD format.';
        }
        if ($dto->billingPeriodStart !== null && $dto->billingPeriodEnd !== null
            && $dto->billingPeriodEnd < $dto->billingPeriodStart) {
            $errors['billing_period_end'] = 'Billing period end cannot be earlier than its start.';
        }
        if ($dto->issueDate !== null && $dto->dueDate !== null && $dto->dueDate < $dto->issueDate) {
            $errors['due_date'] = 'Due date cannot be earlier than the issue date.';
        }
        foreach ($dto->items as $index => $item) {
            if ((float)($item['quantity'] ?? 0) <= 0) $errors["items.$index.quantity"] = 'Quantity must be greater than zero.';
            if ((float)($item['unit_price'] ?? 0) < 0) $errors["items.$index.unit_price"] = 'Unit price cannot be negative.';
        }
        return $errors;
    }

    public function payment(CreatePaymentDTO $dto): array
    {
        $data = $dto->toArray();
        $errors = [];
        if ($data['invoice_id'] <= 0) $errors['invoice_id'] = 'Invoice is required.';
        if ($data['amount'] <= 0) $errors['amount'] = 'Payment amount must be greater than zero.';
        if (!in_array($data['method'], ['CASH','BANK_TRANSFER','GCASH','MAYA','PAYMONGO','XENDIT'], true)) $errors['method'] = 'Invalid payment method.';
        if (!in_array($data['payment_status'], ['PENDING','POSTED'], true)) $errors['payment_status'] = 'Invalid payment status.';
        return $errors;
    }

    public function adjustment(CreateAdjustmentDTO $dto): array
    {
        $data = $dto->toArray();
        $errors = [];
        if ($data['invoice_id'] <= 0) $errors['invoice_id'] = 'Invoice is required.';
        if ($data['amount'] <= 0) $errors['amount'] = 'Adjustment amount must be greater than zero.';
        if ($data['reason'] === '') $errors['reason'] = 'Adjustment reason is required.';
        if (!in_array($data['adjustment_type'], ['CREDIT', 'DEBIT', 'DISCOUNT', 'REBATE', 'WAIVER', 'CORRECTION'], true)) {
            $errors['adjustment_type'] = 'Invalid adjustment type.';
        }
        return $errors;
    }

    public function settings(BillingSettingsDTO $dto): array
    {
        $data = $dto->toArray();
        $errors = [];
        if (isset($data['currency']) && !preg_match('/^[A-Z]{3}$/', strtoupper((string)$data['currency']))) {
            $errors['currency'] = 'Currency must be a three-letter code.';
        }
        foreach (['default_due_days', 'grace_period_days'] as $field) {
            if (isset($data[$field]) && (int)$data[$field] < 0) $errors[$field] = 'Value cannot be negative.';
        }
        return $errors;
    }

    private function dateIsValid(string $date): bool
    {
        $value = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $value !== false && $value->format('Y-m-d') === $date;
    }
}
