<?php

include 'db.php';

$_SESSION['toast'] = [
    'type' => 'success',
    'message' => 'Stripe Payment received'
];

header('Location: ./#wallet');
exit;

?>
