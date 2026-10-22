<?php
session_start();
require_once __DIR__ . '/csrf.php';
// El formulario está en el menú de perfil (perfil/perfil.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfValido() && in_array($_POST['idioma'] ?? '', ['es', 'en'], true)) {
    $_SESSION['idioma'] = $_POST['idioma'];
}
header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'panel.php'));
exit;
