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

// La versión en la URL evita que el navegador use un CSS/JS viejo (Hostinger los guarda 7 días)
$verCss = filemtime(__DIR__ . '/login/login.css');
$verJs  = filemtime(__DIR__ . '/login/login.js');
$verKoaly = filemtime(__DIR__ . '/estilos/koaly.css');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión | Koaly</title>
    <script>
    // Tema antes de pintar: abre en claro salvo que ya se haya elegido oscuro (aquí o en un panel; la clave es común)
    try { var tema = localStorage.getItem('koaly-tema'); } catch (e) {}
    document.documentElement.setAttribute('data-tema', tema === 'oscuro' ? 'oscuro' : 'claro');
    </script>
    <link rel="stylesheet" href="login/login.css?v=<?= $verCss ?>">
    <!-- Estilo común de Koaly (colores, fuentes y modo oscuro), igual que en los paneles -->
    <link rel="stylesheet" href="estilos/koaly.css?v=<?= $verKoaly ?>">
</head>
<body<?= $debeCambiar ? ' data-debe-cambiar="1"' : '' ?>>
    <main class="container">
        <!-- Tema claro / oscuro: luna en claro, sol en oscuro -->
        <button type="button" class="tema-boton" id="botonTema" aria-label="Modo oscuro" aria-pressed="false">
            <svg class="icono-luna" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
            </svg>
            <svg class="icono-sol" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="4.5"/>
                <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>
            </svg>
        </button>

        <!-- INICIAR SESIÓN -->
                <!-- INICIAR SESIÓN -->
        <section id="loginView"<?= $debeCambiar ? ' class="hidden"' : '' ?>>
            
            <!-- Mascota  Koalaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa -->
                        
            <div class="mascota" id="mascota" aria-hidden="true">

            <img src="Imagenes/koala/koala-normal.png" id="mascotaCara" class="mascota-imagen" alt="Mascota Koala">
                <div class="mascota-burbuja" id="mascotaBurbuja">¿Listo para vender?</div>
            </div>
            <!-- Mascota  Koalaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa -->
            
            <h1>Iniciar sesión</h1>
            <p class="subtitle">Bienvenido de vuelta a Koaly</p>

            <div id="loginMessage" class="message" role="alert"></div>

            <form id="loginForm" novalidate>
                <div class="form-group">
                    <label for="loginEmail">Correo electrónico</label>
                    <input type="email" id="loginEmail" placeholder="tú@empresa.com" autocomplete="username" required autofocus>
                </div>

                <div class="form-group">
                    <label for="loginPassword">Contraseña</label>
                    <div class="password-field">
                        <input type="password" id="loginPassword" placeholder="Tu contraseña" autocomplete="current-password" required>
                        <button type="button" class="toggle-password" aria-label="Mostrar contraseña" data-target="loginPassword">Ver</button>
                    </div>
                </div>

                <button type="submit" class="btn-primary">Ingresar</button>
            </form>

            <button type="button" id="forgotLink" class="link-button forgot-link">Olvidé mi contraseña</button>
            <div class="quick-access" id="quickAccess" aria-label="Acceso rápido beta">
    <span class="quick-access-title">Acceso rápido (beta)</span>
    <button type="button" class="quick-access-btn"
            data-email="core.armored@tienda.com"
            data-rol="1">1 · Admin</button>
    <button type="button" class="quick-access-btn"
            data-email="angelmoises549@gmail.com"
            data-rol="2">2 · Gerente</button>
    <button type="button" class="quick-access-btn"
            data-email="aguzman66@ucol.mx"
            data-rol="3">3 · Cajero</button>
</div>
            <p class="footer-note">Koaly · Gestión multisucursal</p>
        </section>

        <!-- RECUPERAR CONTRASEÑA · PASO 1: CORREO -->
        <section id="recoverStep1" class="hidden">
            <button type="button" class="link-button volver-link" data-volver>← Volver a iniciar sesión</button>

            <ol class="steps" aria-label="Paso 1 de 2">
                <li class="step active">1</li>
                <li class="step-line" aria-hidden="true"></li>
                <li class="step">2</li>
            </ol>

            <h1>Recupera tu contraseña</h1>
            <p class="subtitle">Escribe el correo de tu cuenta y te enviaremos un código de verificación.</p>

            <div id="recoverMsg1" class="message" role="alert"></div>

            <form id="recoverForm1" novalidate>
                <div class="form-group">
                    <label for="recoverEmail">Correo electrónico</label>
                    <input type="email" id="recoverEmail" placeholder="tú@empresa.com" autocomplete="username" required>
                </div>
                <button type="submit" id="btnSendCode" class="btn-primary">Enviar código</button>
            </form>
        </section>

        <!-- RECUPERAR CONTRASEÑA · PASO 2: CÓDIGO + NUEVA CONTRASEÑA -->
        <section id="recoverStep2" class="hidden">
            <button type="button" class="link-button volver-link" data-volver>← Volver a iniciar sesión</button>

            <ol class="steps" aria-label="Paso 2 de 2">
                <li class="step done">1</li>
                <li class="step-line done" aria-hidden="true"></li>
                <li class="step active">2</li>
            </ol>

            <h1>Nueva contraseña</h1>
            <p class="subtitle">Escribe el código que enviamos a <strong id="recoverEmailShown"></strong> y elige tu nueva contraseña.</p>

            <div id="recoverMsg2" class="message" role="alert"></div>

            <form id="recoverForm2" novalidate>
                <div class="form-group">
                    <label for="recoverCode">Código de verificación</label>
                    <input type="text" id="recoverCode" class="code-input" placeholder="000000" maxlength="6"
                           inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" required>
                </div>
                <div class="form-group">
                    <label for="recoverNewPass">Nueva contraseña</label>
                    <div class="password-field">
                        <input type="password" id="recoverNewPass" placeholder="Mínimo 8 caracteres" autocomplete="new-password" required>
                        <button type="button" class="toggle-password" aria-label="Mostrar contraseña" data-target="recoverNewPass">Ver</button>
                    </div>
                    <div id="recoverPassWarning" class="strength-warning" aria-live="polite"></div>
                </div>
                <div class="form-group">
                    <label for="recoverConfirmPass">Confirmar contraseña</label>
                    <input type="password" id="recoverConfirmPass" placeholder="Repite la contraseña" autocomplete="new-password" required>
                    <div id="recoverConfirmWarning" class="strength-warning" aria-live="polite"></div>
                </div>
                <button type="submit" class="btn-primary">Restablecer contraseña</button>
            </form>

            <p class="resend">
                ¿No te llegó? Revisa la carpeta de spam o
                <button type="button" id="btnResend" class="link-button">reenvía el código</button>
            </p>
        </section>

        <!-- CAMBIAR CONTRASEÑA (primer ingreso) -->
        <section id="changeView"<?= $debeCambiar ? '' : ' class="hidden"' ?>>
            <div class="logo" aria-hidden="true">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                </svg>
            </div>

            <h1>Cambia tu contraseña</h1>
            <p class="subtitle">Por seguridad, elige una contraseña nueva antes de continuar.</p>

            <div id="changeMessage" class="message" role="alert"></div>

            <form id="changeForm" novalidate>
                <div class="form-group">
                    <label for="newPassword">Nueva contraseña</label>
                    <div class="password-field">
                        <input type="password" id="newPassword" placeholder="Mínimo 8 caracteres" autocomplete="new-password" required>
                        <button type="button" class="toggle-password" aria-label="Mostrar contraseña" data-target="newPassword">Ver</button>
                    </div>
                    <div id="newPasswordWarning" class="strength-warning" aria-live="polite"></div>
                </div>

                <div class="form-group">
                    <label for="confirmPassword">Confirmar contraseña</label>
                    <input type="password" id="confirmPassword" placeholder="Repite la contraseña" autocomplete="new-password" required>
                    <div id="confirmPasswordWarning" class="strength-warning" aria-live="polite"></div>
                </div>

                <button type="submit" class="btn-primary">Actualizar contraseña</button>
            </form>

            <a href="logout.php" class="link-button volver-link logout-link">Salir sin cambiarla</a>
        </section>
    </main>

    <script src="login/login.js?v=<?= $verJs ?>"></script>
</body>
</html>
