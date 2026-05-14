<?php

include 'db.php';
require_once 'stripe_helpers.php';

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$endpointSecret = stripe_webhook_secret();

// LOG 1
file_put_contents(__DIR__ . '/webhook_debug.log', date('Y-m-d H:i:s') . ' - payload reçu' . PHP_EOL, FILE_APPEND);

if ($endpointSecret === '' || !stripe_signature_is_valid($payload, $signature, $endpointSecret)) {
    file_put_contents(__DIR__ . '/webhook_debug.log', date('Y-m-d H:i:s') . ' - SIGNATURE INVALIDE - secret=' . $endpointSecret . PHP_EOL, FILE_APPEND);
    http_response_code(400);
    echo 'Invalid signature';
    exit;
}

// LOG 2
file_put_contents(__DIR__ . '/webhook_debug.log', date('Y-m-d H:i:s') . ' - signature OK' . PHP_EOL, FILE_APPEND);

$event = json_decode($payload, true);

// LOG 3
file_put_contents(__DIR__ . '/webhook_debug.log', date('Y-m-d H:i:s') . ' - event type=' . ($event['type'] ?? 'unknown') . PHP_EOL, FILE_APPEND);

try {
    if (($event['type'] ?? '') === 'checkout.session.completed') {
        $session = $event['data']['object'] ?? [];

        // LOG 4
        file_put_contents(__DIR__ . '/webhook_debug.log', date('Y-m-d H:i:s') . ' - payment_status=' . ($session['payment_status'] ?? 'none') . ' session_id=' . ($session['id'] ?? 'none') . PHP_EOL, FILE_APPEND);

        if (($session['payment_status'] ?? '') === 'paid' && !empty($session['id'])) {
            $result = complete_stripe_topup($pdo, $session['id'], $session['payment_intent'] ?? null);
            
            // LOG 5
            file_put_contents(__DIR__ . '/webhook_debug.log', date('Y-m-d H:i:s') . ' - complete_stripe_topup result=' . ($result ? 'true' : 'false') . PHP_EOL, FILE_APPEND);
        }
    }

    http_response_code(200);
    echo 'OK';
} catch (Throwable $e) {
    file_put_contents(__DIR__ . '/webhook_debug.log', date('Y-m-d H:i:s') . ' - ERREUR: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
    http_response_code(500);
    echo 'Webhook error';
}

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$endpointSecret = stripe_webhook_secret();

if ($endpointSecret === '' || !stripe_signature_is_valid($payload, $signature, $endpointSecret)) {
    http_response_code(400);
    echo 'Invalid signature';
    exit;
}

$event = json_decode($payload, true);
if (!is_array($event)) {
    http_response_code(400);
    echo 'Invalid payload';
    exit;
}

try {
    if (($event['type'] ?? '') === 'checkout.session.completed') {
        $session = $event['data']['object'] ?? [];

        if (($session['payment_status'] ?? '') === 'paid' && !empty($session['id'])) {
            complete_stripe_topup(
                $pdo,
                $session['id'],
                $session['payment_intent'] ?? null
            );
        }
    }

    http_response_code(200);
    echo 'OK';
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Webhook error';
}

?>
