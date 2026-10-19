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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfValido() && isset(IDIOMAS[$_POST['idioma'] ?? ''])) {
    $_SESSION['idioma'] = $_POST['idioma'];
}

header('Location: index.php');
exit;
