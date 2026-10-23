<?php
/**
 * Cambia el idioma del panel y regresa a la página en la que estaba el gerente.
 * El idioma es el mismo en todo el sitio (admin/preferencia_idioma.php).
 */
require_once __DIR__ . '/guardia.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfValido() && isset(IDIOMAS[$_POST['idioma'] ?? ''])) {
    guardarIdioma($_POST['idioma']);
}

// Solo se regresa a páginas de este panel (evita redirecciones a otros sitios)
$volver = (string) ($_POST['volver'] ?? '');
if (!preg_match('~^[a-z_]+\.php(\?[^\s]*)?$~', $volver)) {
    $volver = 'index.php';
}

header('Location: ' . $volver);
exit;
