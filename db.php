<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = '127.0.0.1';
$db   = '';
$user = '';
$pass = '';
$charset = 'utf8mb4';

try {
     $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
     $pdo = new PDO($dsn, $user, $pass, [
         PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
     ]);
     $parisOffset = (new DateTime('now', new DateTimeZone('Europe/Paris')))->format('P');
     $pdo->exec("SET time_zone = '{$parisOffset}'");
} catch (PDOException $e) {
     // This will print the error to the screen so you can debug
     die("Connection failed: " . $e->getMessage());
}
?>