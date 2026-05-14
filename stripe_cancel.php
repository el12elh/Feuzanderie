<?php

include 'db.php';

$_SESSION['toast'] = [
    'type' => 'error',
    'message' => 'Stripe payment cancelled'
];

header('Location: ./#wallet');
exit;

?>
