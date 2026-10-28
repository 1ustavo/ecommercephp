<?php

require_once __DIR__ . "/../config/bootstrap.php";

// Webhook endpoint para Stripe
// Deve ser configurado no Dashboard Stripe com o endpoint:
// https://seusite.com/api/stripe_webhook.php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

$rawPayload = @file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (!$rawPayload) {
    http_response_code(400);
    echo 'Empty payload';
    exit;
}

// Delegate para GatewayPagamento (valida assinatura internamente)
$handled = GatewayPagamento::processarWebhook('stripe', $rawPayload, $sigHeader);

if ($handled) {
    http_response_code(200);
    echo 'OK';
    exit;
}

// Se não foi tratado, responder 400 para que Stripe reporte
http_response_code(400);
echo 'Unhandled event';
exit;
