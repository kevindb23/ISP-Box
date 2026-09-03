<?php

declare(strict_types=1);

$base = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string)file_get_contents($base . '/' . $path);
$service = $read('app/Modules/SubscriberPortal/Services/SubscriberPortalService.php');
$repo = $read('app/Modules/SubscriberPortal/Repositories/SubscriberPortalRepository.php');
$entity = $read('app/Modules/SubscriberPortal/Entities/SubscriberPortalServiceAccount.php');
$workOrders = $read('app/Modules/WorkOrders/Services/WorkOrdersService.php');
$gateway = $read('app/Modules/PaymentGateway/Services/PaymentGatewayService.php');
$frontend = $read('app/Modules/SubscriberPortal/Assets/js/SubscriberPortal.js');

$checks = [
    'schedule transaction' => str_contains($service, 'return $this->repo->transaction(function () use ($sessionData'),
    'reply transaction' => str_contains($service, '$messageId = $this->repo->transaction('),
    'subscriber work-order path' => str_contains($service, 'createFromSubscriberTicket'),
    'subscriber attribution guard' => str_contains($workOrders, "ticket['subscriber_user_id']") && str_contains($workOrders, '!== $userId'),
    'service ownership selection' => str_contains($repo, 'findServiceForSubscriber'),
    'acs join' => str_contains($repo, 'LEFT JOIN ont_acs oa'),
    'acs entity fields' => str_contains($entity, "'acs_status' =>") && str_contains($entity, "'wan_ip' =>"),
    'zero technician capacity' => str_contains($repo, 'return max(0, (int)$stmt->fetchColumn());'),
    'signed webhook' => str_contains($gateway, 'assertValidPayMongoSignature'),
    'server pagination' => str_contains($frontend, 'state.invoiceTotal') && str_contains($frontend, 'loadInvoicesPage();'),
    'ticket service picker' => str_contains($frontend, 'populateTicketServiceSelect'),
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
if ($failed !== []) {
    fwrite(STDERR, 'subscriber_portal_integration_contract=FAIL ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo 'subscriber_portal_integration_contract=PASS checks=' . count($checks) . PHP_EOL;
