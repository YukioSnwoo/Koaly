<?php
/**
 * Cambia el idioma de la caja y regresa a ella.
 * Usa la misma clave de sesión que el gerente y el admin ($_SESSION['idioma']).
 */
session_start();

if (!isset($_SESSION['id_usuario']) || (int) $_SESSION['id_rol'] !== 3) {
    header('Location: ../index.php');
    exit;
}

require_once __DIR__ . '/../admin/csrf.php';
require_once __DIR__ . '/includes/i18n.php';

$valido = $_SERVER['REQUEST_METHOD'] === 'POST' && csrfValido() && isset(IDIOMAS[$_POST['idioma'] ?? '']);
if ($valido) {
    $_SESSION['idioma'] = $_POST['idioma'];
}

// La caja lo llama con fetch y cambia los textos sin recargar, para no perder
// la venta en curso; solo necesita saber si se guardó
if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
    header('Content-Type: application/json');
    http_response_code($valido ? 200 : 400);
    echo json_encode(['ok' => $valido]);
    exit;
}

header('Location: index.php');
exit;
