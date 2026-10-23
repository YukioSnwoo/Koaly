<?php
// header.php — Inicio de cada página del admin
session_start();

// Protección de acceso
if (!isset($_SESSION['id_usuario']) || $_SESSION['id_rol'] !== 1) {
    header('Location: ../index.php');
    exit;
}

// Conexión a BD
require_once __DIR__ . '/database.php';

// Datos del usuario en sesión
$stmt = $pdo->prepare("SELECT id_usuario, nombre, email, id_rol, id_sucursal FROM Usuarios WHERE id_usuario = ? LIMIT 1");
$stmt->execute([$_SESSION['id_usuario']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    session_destroy();
    header('Location: ../index.php');
    exit;
}

// Idioma: textos en admin/lang/{es,en}.php
require_once __DIR__ . '/i18n.php';

$pagina_actual = basename($_SERVER['PHP_SELF']);

$verAdminCss = filemtime(__DIR__ . '/admin.css');
$verKoaly    = filemtime(__DIR__ . '/../estilos/koaly.css');
$verPerfil   = filemtime(__DIR__ . '/../perfil/perfil.css');
?>
<!DOCTYPE html>
<html lang="<?= idiomaActual() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Koaly · Admin</title>
    <script>
    // Tema elegido antes de pintar (común con gerente y cajero). Sin elección: sigue al sistema.
    // La clave vieja del admin solo guardaba "light"; se respeta hasta que se elija de nuevo.
    try {
        var tema = localStorage.getItem('koaly-tema');
        if (!tema && localStorage.getItem('koalicius-admin-theme') === 'light') tema = 'claro';
        if (tema === 'claro' || tema === 'oscuro') document.documentElement.setAttribute('data-tema', tema);
    } catch (e) {}
    // Textos traducidos para el JavaScript de las páginas (admin.js define tj())
    window.I18N = <?= i18nParaJs() ?>;
    </script>
    <link rel="stylesheet" href="admin.css?v=<?= $verAdminCss ?>">
    <!-- Estilo común de Koaly (colores, fuentes y piel táctil); va al final para que gane -->
    <link rel="stylesheet" href="../estilos/koaly.css?v=<?= $verKoaly ?>">
    <link rel="stylesheet" href="../perfil/perfil.css?v=<?= $verPerfil ?>">
</head>
<body>
<div class="admin-layout">
