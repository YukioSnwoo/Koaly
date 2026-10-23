<?php
session_start();
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/i18n.php';
// El formulario está en el menú de perfil (perfil/perfil.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfValido() && isset(IDIOMAS[$_POST['idioma'] ?? ''])) {
    guardarIdioma($_POST['idioma']);
}
header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'panel.php'));
exit;
