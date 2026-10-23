<?php

session_start();

$rutasPorRol = [1 => 'admin/', 2 => 'gerente/', 3 => 'cajero/'];
$debeCambiar = false;

if (isset($_SESSION['id_usuario'])) {
    try {
        require_once __DIR__ . '/admin/database.php';
        $stmt = $pdo->prepare(
            "SELECT u.id_rol, u.estado, u.debe_cambiar, u.id_sucursal, s.estado AS sucursal_estado
             FROM Usuarios u
             LEFT JOIN Sucursales s ON s.id_sucursal = u.id_sucursal
             WHERE u.id_usuario = ?"
        );
        $stmt->execute([$_SESSION['id_usuario']]);
        $sesion = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $sesion = false; // sin base de datos se muestra el formulario normal
    }

    $valida = $sesion
        && $sesion['estado'] === 'Activo'
        && isset($rutasPorRol[(int) $sesion['id_rol']])
        && ($sesion['id_sucursal'] === null ? (int) $sesion['id_rol'] === 1 : $sesion['sucursal_estado'] === 'Activa');

    if (!$valida) {
        $_SESSION = [];
        session_destroy();
    } elseif ((int) $sesion['debe_cambiar'] === 1) {
        $debeCambiar = true;
    } else {
        header('Location: ' . $rutasPorRol[(int) $sesion['id_rol']]);
        exit;
    }
}

// Textos en el idioma elegido (el mismo de todo el sitio); login.js cambia de idioma sin recargar
require_once __DIR__ . '/login/i18n.php';

// La versión en la URL evita que el navegador use un CSS/JS viejo (Hostinger los guarda 7 días)
$verCss = filemtime(__DIR__ . '/login/login.css');
$verJs  = filemtime(__DIR__ . '/login/login.js');
$verKoaly = filemtime(__DIR__ . '/estilos/koaly.css');

// Botón de ojo para ver u ocultar la contraseña del campo $id (login.js lo maneja)
$botonVer = fn (string $id) => '<button type="button" class="toggle-password" data-target="' . $id . '"'
    . ' aria-label="' . e(t('contrasena.mostrar')) . '" data-i18n-aria-label="contrasena.mostrar" aria-pressed="false" title="' . e(t('contrasena.mostrar')) . '">'
    . '<svg class="icono-ojo" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
    . '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>'
    . '<svg class="icono-ojo-tachado" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
    . '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 19c-6.4 0-10-7-10-7a18.45 18.45 0 0 1 5.06-5.94M9.9 5.24A9.12 9.12 0 0 1 12 5c6.4 0 10 7 10 7a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="2" y1="2" x2="22" y2="22"/></svg>'
    . '</button>';

// Texto que login.js cambia de idioma: <etiqueta data-i18n="clave">texto</etiqueta>
$txt = fn (string $clave) => 'data-i18n="' . $clave . '">' . e(t($clave));
?>
<!DOCTYPE html>
<html lang="<?= $GLOBALS['LOGIN_IDIOMA'] ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(t('pagina.titulo')) ?></title>
    <script>
    // Tema antes de pintar: abre en claro salvo que ya se haya elegido oscuro (aquí o en un panel; la clave es común)
    try { var tema = localStorage.getItem('koaly-tema'); } catch (e) {}
    document.documentElement.setAttribute('data-tema', tema === 'oscuro' ? 'oscuro' : 'claro');
    // Textos de todos los idiomas: el botón de idioma los cambia sin recargar (login.js)
    window.LOGIN_TEXTOS = <?= textosLoginParaJs() ?>;
    </script>
    <link rel="stylesheet" href="login/login.css?v=<?= $verCss ?>">
    <!-- Estilo común de Koaly (colores, fuentes y modo oscuro), igual que en los paneles -->
    <link rel="stylesheet" href="estilos/koaly.css?v=<?= $verKoaly ?>">
</head>
<body<?= $debeCambiar ? ' data-debe-cambiar="1"' : '' ?>>
    <main class="container">
        <!-- Idioma (esquina izquierda): el mismo en todo el sitio, como el tema -->
        <button type="button" class="tema-boton idioma-boton" id="botonIdioma" aria-label="<?= e(t('idioma.cambiar')) ?>" title="<?= e(t('idioma.cambiar')) ?>" data-i18n-aria-label="idioma.cambiar" data-i18n-title="idioma.cambiar">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
            </svg>
            <span <?= $txt('idioma.corto') ?></span>
        </button>

        <!-- Tema claro / oscuro (esquina derecha): luna en claro, sol en oscuro -->
        <button type="button" class="tema-boton" id="botonTema" aria-label="<?= e(t('tema.boton')) ?>" data-i18n-aria-label="tema.boton" aria-pressed="false">
            <svg class="icono-luna" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
            </svg>
            <svg class="icono-sol" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="4.5"/>
                <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>
            </svg>
        </button>

        <!-- INICIAR SESIÓN -->
        <section id="loginView"<?= $debeCambiar ? ' class="hidden"' : '' ?>>
            
            <!-- Mascota  Koalaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa -->
                        
            <div class="mascota" id="mascota" aria-hidden="true">

            <img src="Imagenes/koala/koala-normal.png" id="mascotaCara" class="mascota-imagen" alt="Mascota Koala">
                <div class="mascota-burbuja" id="mascotaBurbuja" <?= $txt('koala.saludo') ?></div>
            </div>
            <!-- Mascota  Koalaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa -->
            
            <h1 <?= $txt('login.titulo') ?></h1>
            <p class="subtitle" <?= $txt('login.subtitulo') ?></p>

            <div id="loginMessage" class="message" role="alert"></div>

            <form id="loginForm" novalidate>
                <div class="form-group">
                    <label for="loginEmail" <?= $txt('campo.correo') ?></label>
                    <input type="email" id="loginEmail" placeholder="<?= e(t('campo.correo_ej')) ?>" data-i18n-placeholder="campo.correo_ej" autocomplete="username" required autofocus>
                </div>

                <div class="form-group">
                    <label for="loginPassword" <?= $txt('campo.contrasena') ?></label>
                    <div class="password-field">
                        <input type="password" id="loginPassword" placeholder="<?= e(t('campo.contrasena_ej')) ?>" data-i18n-placeholder="campo.contrasena_ej" autocomplete="current-password" required>
                        <?= $botonVer('loginPassword') ?>
                    </div>
                </div>

                <button type="submit" class="btn-primary" <?= $txt('login.ingresar') ?></button>
            </form>

            <button type="button" id="forgotLink" class="link-button forgot-link" <?= $txt('login.olvide') ?></button>
            <div class="quick-access" id="quickAccess" aria-label="<?= e(t('login.acceso_rapido')) ?>" data-i18n-aria-label="login.acceso_rapido">
                <span class="quick-access-title" <?= $txt('login.acceso_rapido') ?></span>
                <button type="button" class="quick-access-btn"
                        data-email="core.armored@tienda.com"
                        data-rol="1" <?= $txt('login.acceso_admin') ?></button>
                <button type="button" class="quick-access-btn"
                        data-email="angelmoises549@gmail.com"
                        data-rol="2" <?= $txt('login.acceso_gerente') ?></button>
                <button type="button" class="quick-access-btn"
                        data-email="aguzman66@ucol.mx"
                        data-rol="3" <?= $txt('login.acceso_cajero') ?></button>
            </div>
            <p class="footer-note" <?= $txt('login.pie') ?></p>
        </section>

        <!-- RECUPERAR CONTRASEÑA · PASO 1: CORREO -->
        <section id="recoverStep1" class="hidden">
            <button type="button" class="link-button volver-link" data-volver <?= $txt('recuperar.volver') ?></button>

            <ol class="steps" aria-label="<?= e(t('recuperar.paso1')) ?>" data-i18n-aria-label="recuperar.paso1">
                <li class="step active">1</li>
                <li class="step-line" aria-hidden="true"></li>
                <li class="step">2</li>
            </ol>

            <h1 <?= $txt('recuperar.titulo') ?></h1>
            <p class="subtitle" <?= $txt('recuperar.subtitulo') ?></p>

            <div id="recoverMsg1" class="message" role="alert"></div>

            <form id="recoverForm1" novalidate>
                <div class="form-group">
                    <label for="recoverEmail" <?= $txt('campo.correo') ?></label>
                    <input type="email" id="recoverEmail" placeholder="<?= e(t('campo.correo_ej')) ?>" data-i18n-placeholder="campo.correo_ej" autocomplete="username" required>
                </div>
                <button type="submit" id="btnSendCode" class="btn-primary" <?= $txt('recuperar.enviar') ?></button>
            </form>
        </section>

        <!-- RECUPERAR CONTRASEÑA · PASO 2: CÓDIGO + NUEVA CONTRASEÑA -->
        <section id="recoverStep2" class="hidden">
            <button type="button" class="link-button volver-link" data-volver <?= $txt('recuperar.volver') ?></button>

            <ol class="steps" aria-label="<?= e(t('recuperar.paso2')) ?>" data-i18n-aria-label="recuperar.paso2">
                <li class="step done">1</li>
                <li class="step-line done" aria-hidden="true"></li>
                <li class="step active">2</li>
            </ol>

            <h1 <?= $txt('nueva.titulo') ?></h1>
            <p class="subtitle"><span <?= $txt('nueva.subtitulo_antes') ?></span> <strong id="recoverEmailShown"></strong> <span <?= $txt('nueva.subtitulo_despues') ?></span></p>

            <div id="recoverMsg2" class="message" role="alert"></div>

            <form id="recoverForm2" novalidate>
                <div class="form-group">
                    <label for="recoverCode" <?= $txt('campo.codigo') ?></label>
                    <input type="text" id="recoverCode" class="code-input" placeholder="000000" maxlength="6"
                           inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" required>
                </div>
                <div class="form-group">
                    <label for="recoverNewPass" <?= $txt('campo.nueva') ?></label>
                    <div class="password-field">
                        <input type="password" id="recoverNewPass" placeholder="<?= e(t('campo.nueva_ej')) ?>" data-i18n-placeholder="campo.nueva_ej" autocomplete="new-password" required>
                        <?= $botonVer('recoverNewPass') ?>
                    </div>
                    <div id="recoverPassWarning" class="strength-warning" aria-live="polite"></div>
                </div>
                <div class="form-group">
                    <label for="recoverConfirmPass" <?= $txt('campo.confirmar') ?></label>
                    <div class="password-field">
                        <input type="password" id="recoverConfirmPass" placeholder="<?= e(t('campo.confirmar_ej')) ?>" data-i18n-placeholder="campo.confirmar_ej" autocomplete="new-password" required>
                        <?= $botonVer('recoverConfirmPass') ?>
                    </div>
                    <div id="recoverConfirmWarning" class="strength-warning" aria-live="polite"></div>
                </div>
                <button type="submit" class="btn-primary" <?= $txt('nueva.restablecer') ?></button>
            </form>

            <p class="resend">
                <span <?= $txt('reenviar.texto') ?></span>
                <button type="button" id="btnResend" class="link-button" <?= $txt('reenviar.boton') ?></button>
            </p>
        </section>

        <!-- CAMBIAR CONTRASEÑA (primer ingreso) -->
        <section id="changeView"<?= $debeCambiar ? '' : ' class="hidden"' ?>>
            <div class="logo" aria-hidden="true">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                </svg>
            </div>

            <h1 <?= $txt('cambiar.titulo') ?></h1>
            <p class="subtitle" <?= $txt('cambiar.subtitulo') ?></p>

            <div id="changeMessage" class="message" role="alert"></div>

            <form id="changeForm" novalidate>
                <div class="form-group">
                    <label for="newPassword" <?= $txt('campo.nueva') ?></label>
                    <div class="password-field">
                        <input type="password" id="newPassword" placeholder="<?= e(t('campo.nueva_ej')) ?>" data-i18n-placeholder="campo.nueva_ej" autocomplete="new-password" required>
                        <?= $botonVer('newPassword') ?>
                    </div>
                    <div id="newPasswordWarning" class="strength-warning" aria-live="polite"></div>
                </div>

                <div class="form-group">
                    <label for="confirmPassword" <?= $txt('campo.confirmar') ?></label>
                    <div class="password-field">
                        <input type="password" id="confirmPassword" placeholder="<?= e(t('campo.confirmar_ej')) ?>" data-i18n-placeholder="campo.confirmar_ej" autocomplete="new-password" required>
                        <?= $botonVer('confirmPassword') ?>
                    </div>
                    <div id="confirmPasswordWarning" class="strength-warning" aria-live="polite"></div>
                </div>

                <button type="submit" class="btn-primary" <?= $txt('cambiar.actualizar') ?></button>
            </form>

            <a href="logout.php" class="link-button volver-link logout-link" <?= $txt('cambiar.salir') ?></a>
        </section>
    </main>

    <script src="login/login.js?v=<?= $verJs ?>"></script>
</body>
</html>
