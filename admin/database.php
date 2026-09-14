<?php
// database.php — Conexión PDO a la base de datos
// Se incluye desde header.php y cualquier página que necesite BD

$host   = 'localhost';
$dbname = 'u772860605_DATAK';
$user   = 'u772860605_JACEUF';
$pass   = 'j.2.4.6.4.R';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    die('Error de conexión a la base de datos');
}
