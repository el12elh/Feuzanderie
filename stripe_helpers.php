<?php

require_once 'stripe_config.php';

function stripe_api_request(string $method, string $path, array $params = []): array
{
    $secretKey = stripe_secret_key();
    if ($secretKey === '') {
        throw new RuntimeException('Stripe secret key is not configured.');
    }

    if (!function_exists('curl_init')) {
        throw new RuntimeException('The PHP cURL extension is required for Stripe payments.');
    }

    $ch = curl_init('https://api.stripe.com' . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_USERPWD, $secretKey . ':');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
    ]);

    if (!empty($params)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    }

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('Stripe request failed: ' . $error);
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Stripe returned an invalid response.');
    }

    if ($status < 200 || $status >= 300) {
        $message = $decoded['error']['message'] ?? 'Stripe request failed.';
        throw new RuntimeException($message);
    }

    return $decoded;
}

function stripe_signature_is_valid(string $payload, string $signatureHeader, string $endpointSecret): bool
{
    $parts = [];
    foreach (explode(',', $signatureHeader) as $item) {
        [$key, $value] = array_pad(explode('=', $item, 2), 2, '');
        $parts[$key][] = $value;
    }

    $timestamp = $parts['t'][0] ?? '';
    $signatures = $parts['v1'] ?? [];

    if ($timestamp === '' || empty($signatures)) {
        return false;
    }

    if (abs(time() - (int)$timestamp) > 300) {
        return false;
    }

    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $endpointSecret);
    foreach ($signatures as $signature) {
        if (hash_equals($expected, $signature)) {
            return true;
        }
    }

    return false;
}

function complete_stripe_topup(PDO $pdo, string $checkoutSessionId, ?string $paymentIntentId): bool
{

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT *
        FROM stripe_topups
        WHERE CHECKOUT_SESSION_ID = ?
        FOR UPDATE
    ");
    $stmt->execute([$checkoutSessionId]);
    $topup = $stmt->fetch();

    if (!$topup || $topup['STATUS'] === 'paid') {
        $pdo->commit();
        return false;
    }

    $stmt = $pdo->prepare("
        INSERT INTO wallet_topup (ID_USER, ID_CUSTOMER, ID_TOPUP_TYPE, AMOUNT)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $topup['ID_USER'],
        $topup['ID_CUSTOMER'],
        STRIPE_TOPUP_TYPE_ID,
        $topup['AMOUNT'],
    ]);

    $stmt = $pdo->prepare("UPDATE customers SET BALANCE = BALANCE + ?, IS_ACTIVE = 1 WHERE ID_CUSTOMER = ?");
    $stmt->execute([$topup['AMOUNT'], $topup['ID_CUSTOMER']]);

    $stmt = $pdo->prepare("
        UPDATE stripe_topups
        SET STATUS = 'paid', PAYMENT_INTENT_ID = ?, PAID_AT = NOW()
        WHERE ID_STRIPE_TOPUP = ?
    ");
    $stmt->execute([$paymentIntentId, $topup['ID_STRIPE_TOPUP']]);

    $pdo->commit();
    return true;
}

?>
