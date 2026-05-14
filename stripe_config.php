<?php

const STRIPE_TOPUP_TYPE_ID = 7; // ID_TOPUP_TYPE for Stripe in ref_topup_type
const STRIPE_TOPUP_CURRENCY = 'eur';
const STRIPE_TOPUP_MIN_EUR = 1;
const STRIPE_TOPUP_MAX_EUR = 200;

$localStripeConfig = __DIR__ . '/stripe_config.local.php';
if (is_file($localStripeConfig)) {
    require $localStripeConfig;
}

function stripe_secret_key(): string
{
    $key = getenv('STRIPE_SECRET_KEY') ?: ($_ENV['STRIPE_SECRET_KEY'] ?? '');
    return trim($key);
}

function stripe_webhook_secret(): string
{
    $secret = getenv('STRIPE_WEBHOOK_SECRET') ?: ($_ENV['STRIPE_WEBHOOK_SECRET'] ?? '');
    return trim($secret);
}

function app_base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');

    return $scheme . '://' . $host . ($basePath === '/' ? '' : $basePath);
}

?>
