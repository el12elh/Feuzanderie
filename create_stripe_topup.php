<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include 'db.php';
require_once 'stripe_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    header('Location: ./#signin');
    exit;
}

$amount = (int)($_POST['amount'] ?? 0);

if ($amount < STRIPE_TOPUP_MIN_EUR || $amount > STRIPE_TOPUP_MAX_EUR) {
    $_SESSION['toast'] = [
        'type' => 'error',
        'message' => 'Invalid top-up amount'
    ];
    header('Location: ./#wallet');
    exit;
}

$stmt = $pdo->prepare("
    SELECT c.ID_CUSTOMER, c.FIRST_NAME, c.LAST_NAME, u.EMAIL
    FROM users u
    JOIN users_customers uc ON uc.ID_USER = u.ID_USER
    JOIN customers c ON c.ID_CUSTOMER = uc.ID_CUSTOMER
    WHERE u.ID_USER = ?
");
$stmt->execute([$_SESSION['user_id']]);
$customer = $stmt->fetch();

if (!$customer) {
    $_SESSION['toast'] = [
        'type' => 'error',
        'message' => 'Your account is not linked to a member profile yet'
    ];
    header('Location: ./#wallet');
    exit;
}

try {

    $baseUrl = app_base_url();
    $session = stripe_api_request('POST', '/v1/checkout/sessions', [
        'mode' => 'payment',
        'client_reference_id' => (string)$_SESSION['user_id'],
        'customer_email' => $customer['EMAIL'],
        'success_url' => $baseUrl . '/stripe_success',
        'cancel_url' => $baseUrl . '/stripe_cancel',
        'line_items' => [[
            'price_data' => [
                'currency' => STRIPE_TOPUP_CURRENCY,
                'product_data' => [
                    'name' => 'Wallet Top-Up',
                    'description' => trim($customer['FIRST_NAME'] . ' ' . $customer['LAST_NAME']),
                ],
                'unit_amount' => $amount * 100,
            ],
            'quantity' => 1,
        ]],
        'metadata' => [
            'id_user' => (string)$_SESSION['user_id'],
            'id_customer' => (string)$customer['ID_CUSTOMER'],
            'amount_eur' => (string)$amount,
        ],
    ]);

    $stmt = $pdo->prepare("
        INSERT INTO stripe_topups
            (ID_USER, ID_CUSTOMER, AMOUNT, CURRENCY, CHECKOUT_SESSION_ID, STATUS)
        VALUES (?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        $customer['ID_CUSTOMER'],
        $amount,
        STRIPE_TOPUP_CURRENCY,
        $session['id'],
    ]);

    header('Location: ' . $session['url'], true, 303);
    exit;
} catch (Throwable $e) {
    $_SESSION['toast'] = [
        'type' => 'error',
        'message' => $e->getMessage()
    ];
    header('Location: ./#wallet');
    exit;
}

?>
