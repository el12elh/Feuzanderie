<?php

include 'db.php';

$_SESSION['toast'] = [
    'type' => 'success',
    'message' => 'Stripe payment received'
];

header('Location: ./#wallet');
exit;

?>
