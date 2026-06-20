<?php

use App\Modules\SubscriberPortal\Controllers\SubscriberPortalApiController;

$router->get('/api/v1/subscriber-portal/me', [SubscriberPortalApiController::class, 'me']);

$router->get('/api/v1/subscriber-portal/dashboard', [SubscriberPortalApiController::class, 'dashboard']);

$router->get('/api/v1/subscriber-portal/services', [SubscriberPortalApiController::class, 'services']);

$router->get('/api/v1/subscriber-portal/invoices', [SubscriberPortalApiController::class, 'invoices']);

$router->get('/api/v1/subscriber-portal/invoices/show/{id}', [SubscriberPortalApiController::class, 'invoiceShow']);

$router->get('/api/v1/subscriber-portal/payments', [SubscriberPortalApiController::class, 'payments']);

$router->post('/api/v1/subscriber-portal/change-password', [SubscriberPortalApiController::class, 'changePassword']);

$router->get('/api/v1/subscriber-portal/tickets', [SubscriberPortalApiController::class, 'tickets']);

$router->post('/api/v1/subscriber-portal/tickets', [SubscriberPortalApiController::class, 'createTicket']);

$router->get('/api/v1/subscriber-portal/tickets/visit-slots', [SubscriberPortalApiController::class, 'visitSlots']);

$router->get('/api/v1/subscriber-portal/tickets/show/{id}', [SubscriberPortalApiController::class, 'ticketShow']);

$router->post('/api/v1/subscriber-portal/tickets/reply', [SubscriberPortalApiController::class, 'ticketReply']);

$router->post('/api/v1/subscriber-portal/tickets/schedule-visit', [SubscriberPortalApiController::class, 'scheduleVisit']);