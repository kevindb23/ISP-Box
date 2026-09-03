<?php

declare(strict_types=1);

$base = dirname(__DIR__, 2);
$contracts = [
    'Subscribers' => [
        'view' => 'subscriberViewModal', 'loading' => 'subscriberViewBody',
        'fields' => ['provisioning_status','olt_name','olt_port_name','ont_serial','nap_name','cvlan','svlan'],
    ],
    'Tickets' => [
        'view' => 'ticketDetailsModal', 'loading' => 'ticketModalLoading',
        'fields' => ['ticket_no','subscriber_name','service_number','work_order_no'],
    ],
    'WorkOrders' => [
        'view' => 'workOrderDetailsModal', 'loading' => 'workOrderModalLoading',
        'fields' => ['work_order_no','subscriber_name','ticket_no','assigned_user_name'],
    ],
];
$failures = [];
$layout = file_get_contents($base . '/app/UI/Views/layouts/app.php');

foreach ([
    '.main-content .modal.show' => 'transparent modal viewport',
    '.modal-backdrop.show' => 'shared modal backdrop',
    '.modal .nx-field > div' => 'detail-value contrast',
    '-webkit-text-fill-color: #0f172a' => 'light detail text',
    '-webkit-text-fill-color: #f8fafc' => 'dark detail text',
] as $needle => $description) {
    if (!str_contains($layout, $needle)) $failures[] = "Global modal theme omits {$description}";
}

foreach ($contracts as $module => $contract) {
    $view = implode("\n", array_map('file_get_contents', glob($base . "/app/Modules/{$module}/Views/*.php") ?: []));
    $js = implode("\n", array_map('file_get_contents', glob($base . "/app/Modules/{$module}/Assets/js/*.js") ?: []));
    foreach ([$contract['view'], $contract['loading']] as $id) {
        if (!str_contains($view . $js, $id)) $failures[] = "{$module} omits modal contract ID {$id}";
    }
    foreach ($contract['fields'] as $field) {
        if (!str_contains($js, $field)) $failures[] = "{$module} modal omits API field {$field}";
    }
    if (!preg_match('/loading/i', $js) || !preg_match('/failed|error/i', $js)) {
        $failures[] = "{$module} modal lacks explicit loading/error state";
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo 'frontend_modal_contract=PASS modules=' . count($contracts) . PHP_EOL;
