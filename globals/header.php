<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/database.php';

$logueado = false;
$usuario = null;
$usuario_id_js = 0;

if (isset($_SESSION['usuario_id'])) {
    $stmt = $pdo->prepare("SELECT id, nombre, email, imagen_perfil FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['usuario_id']]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($usuario) {
        $logueado = true;
        $usuario_id_js = $usuario['id'];
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<header>
    <div class="header-top">
        <div class="logo">
            <a href="index.php">
                <img src="imageness/Koalii.png" alt="Koalicius">
            </a>
        </div>
        <div class="search-bar">
            <i class="fa-solid fa-search"></i>
            <input type="text" placeholder="Buscar recetas...">
        </div>
        <div class="user-profile">
            <?php if ($logueado): ?>
                <div class="profile-trigger" id="profile-trigger">
                    <img src="<?= !empty($usuario['imagen_perfil']) ? htmlspecialchars($usuario['imagen_perfil']) : 'imageness/Logo Sesion.png' ?>" alt="Perfil">
                    <span><?= htmlspecialchars($usuario['nombre']) ?></span>
                    <i class="fa-solid fa-chevron-down"></i>
                </div>
                <div class="profile-dropdown" id="profile-dropdown">
                    <a href="perfil.php?id=<?= $usuario['id'] ?>"><i class="fa-solid fa-user"></i> Mi perfil</a>
                    <div class="dropdown-divider"></div>
                    <div class="theme-switch" id="theme-switch">
                        <i class="fa-solid fa-moon"></i> Claro / Oscuro
                    </div>
                    <div class="language-switch" id="language-switch">
                        <i class="fa-solid fa-globe"></i> Idioma
                        <span class="current-lang">Español</span>
                    </div>
                    <div class="dropdown-divider"></div>
                    <div id="saved-accounts-list"></div>
                    <div class="add-account" id="add-account">
                        <i class="fa-solid fa-plus"></i> Iniciar sesión
                    </div>
                    <div class="dropdown-divider"></div>
                    <div class="logout-btn" id="logout-btn">
                        <i class="fa-solid fa-sign-out-alt"></i> Cerrar sesión
                    </div>
                </div>
            <?php else: ?>
                <a href="login.php">
                    <img src="imageness/Logo Sesion.png" alt="Perfil">
                    <span>Inicia sesión</span>
                </a>
            <?php endif; ?>
        </div>
        <button id="menu-hamburger" class="menu-hamburger" aria-label="Menú">
            <i class="fa-solid fa-bars"></i>
        </button>
    </div>

    <nav class="header-nav">
        <a href="index.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>"><i class="fa-solid fa-house"></i> Inicio</a>
        <a href="tendencia.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'tendencia.php' ? 'active' : '' ?>"><i class="fa-solid fa-fire"></i> Tendencia</a>
        <a href="videos.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'videos.php' ? 'active' : '' ?>"><i class="fa-solid fa-play"></i> Videos</a>
        <a href="notificacion.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'notificacion.php' ? 'active' : '' ?>"><i class="fa-solid fa-bell"></i> Notificación</a>
        <a href="crear_receta.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'crear_receta.php' ? 'active' : '' ?>"><i class="fa-solid fa-utensils"></i> Crear Receta</a>
        <a href="configuracion.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'configuracion.php' ? 'active' : '' ?>"><i class="fa-solid fa-gear"></i> Configuración</a>
    </nav>
</header>

<div id="side-menu" class="side-menu">
    <div class="side-menu-header">
        <button id="close-menu" class="close-menu" aria-label="Cerrar menú">
            <i class="fa-solid fa-times"></i>
        </button>
    </div>
    <div class="side-menu-content">
        <div class="side-menu-profile">
            <a href="<?= $logueado ? 'perfil.php?id=' . $usuario['id'] : 'login.php' ?>">
                <img src="<?= $logueado && !empty($usuario['imagen_perfil']) ? htmlspecialchars($usuario['imagen_perfil']) : 'imageness/Logo Sesion.png' ?>" alt="Perfil">
                <span><?= $logueado ? htmlspecialchars($usuario['nombre']) : 'Inicia sesión' ?></span>
            </a>
        </div>
        <nav class="side-menu-nav">
            <a href="index.php"><i class="fa-solid fa-house"></i> Inicio</a>
            <a href="tendencia.php"><i class="fa-solid fa-fire"></i> Tendencia</a>
            <a href="videos.php"><i class="fa-solid fa-play"></i> Videos</a>
            <a href="notificacion.php"><i class="fa-solid fa-bell"></i> Notificación</a>
            <a href="crear_receta.php"><i class="fa-solid fa-utensils"></i> Crear Receta</a>
            <a href="configuracion.php"><i class="fa-solid fa-gear"></i> Configuración</a>
        </nav>
    </div>
</div>

<script>
    window.koaliciusUser = <?= $usuario_id_js ?>;
</script>