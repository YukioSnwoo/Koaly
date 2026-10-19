<?php
/**
 * Inicio de sesión. La lógica está en login/login.php (API JSON) y login/login.js.
 *
 * Si ya hay sesión válida, manda directo al panel del rol. Si la cuenta debe
 * cambiar su contraseña (los paneles redirigen aquí), abre esa vista.
 */
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión | Koaly</title>
    <link rel="stylesheet" href="login/login.css?v=<?= $verCss ?>">
</head>
<body<?= $debeCambiar ? ' data-debe-cambiar="1"' : '' ?>>
    <main class="container">
        <!-- INICIAR SESIÓN -->
        <section id="loginView"<?= $debeCambiar ? ' class="hidden"' : '' ?>>
            <div class="logo" aria-hidden="true">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/>
                </svg>
            </div>

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

            <p class="footer-note">Koaly · Gestión multisucursal</p>
        </section>

        <!-- RECUPERAR CONTRASEÑA · PASO 1: CORREO -->
        <section id="recoverStep1" class="hidden">
            <button type="button" class="link-button back-link" data-volver>← Volver a iniciar sesión</button>

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
            <button type="button" class="link-button back-link" data-volver>← Volver a iniciar sesión</button>

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

            <a href="logout.php" class="link-button back-link logout-link">Salir sin cambiarla</a>
        </section>
    </main>

    <script src="login/login.js?v=<?= $verJs ?>"></script>
</body>
</html>
