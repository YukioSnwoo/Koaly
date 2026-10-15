<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión | Koaly</title>
    <link rel="stylesheet" href="login/login.css">
</head>
<body>
    <div class="container">
        <!-- LOGIN -->
        <div id="loginView">
            <div class="logo">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/>
                </svg>
            </div>

            <h2>Iniciar Sesión</h2>
            <p class="subtitle">Bienvenido de vuelta a Koaly</p>

            <div id="loginMessage" class="message"></div>

            <form id="loginForm">
                <div class="form-group">
                    <label for="loginEmail">Correo electrónico</label>
                    <input type="email" id="loginEmail" placeholder="tú@empresa.com" required>
                </div>

                <div class="form-group">
                    <label for="loginPassword">Contraseña</label>
                    <input type="password" id="loginPassword" placeholder="Tu contraseña" required>
                </div>

                <button type="submit">Ingresar</button>
            </form>

            <a href="#" id="forgotLink" class="forgot-link">Olvidé la contraseña</a>

            <p class="footer-note">Koaly · Gestión multi-sucursal</p>
        </div>

        <!-- RECUPERAR CONTRASEÑA - PASO 1: CORREO -->
        <div id="recoverStep1" class="hidden">
            <a href="#" id="backToLogin1" class="back-link">← Volver a inicio de sesión</a>

            <div class="steps">
                <div class="step active">1</div>
                <div class="step-line"></div>
                <div class="step">2</div>
            </div>

            <h2>Recupera tu contraseña</h2>
            <p class="subtitle">Ingresa el correo registrado para enviarte un código de verificación</p>

            <div id="recoverMsg1" class="message"></div>

            <form id="recoverForm1">
                <div class="form-group">
                    <label for="recoverEmail">Correo electrónico</label>
                    <input type="email" id="recoverEmail" placeholder="ejemplo@gmail.com" required>
                </div>
                <button type="submit" id="btnSendCode">Enviar código</button>
            </form>
        </div>

        <!-- RECUPERAR CONTRASEÑA - PASO 2: CÓDIGO + NUEVA CONTRASEÑA -->
        <div id="recoverStep2" class="hidden">
            <a href="#" id="backToLogin2" class="back-link">← Volver a inicio de sesión</a>

            <div class="steps">
                <div class="step done">1</div>
                <div class="step-line done"></div>
                <div class="step active">2</div>
            </div>

            <h2>Nueva contraseña</h2>
            <p class="subtitle">Ingresa el código que te enviamos y crea tu nueva contraseña</p>

            <div id="recoverMsg2" class="message"></div>

            <form id="recoverForm2">
                <div class="form-group">
                    <label for="recoverCode">Código de verificación</label>
                    <input type="text" id="recoverCode" placeholder="123456" maxlength="6" required>
                </div>
                <div class="form-group">
                    <label for="recoverNewPass">Nueva contraseña</label>
                    <input type="password" id="recoverNewPass" placeholder="Mínimo 8 caracteres" required>
                    <div id="recoverPassWarning" class="strength-warning" aria-live="polite"></div>
                </div>
                <div class="form-group">
                    <label for="recoverConfirmPass">Confirmar contraseña</label>
                    <input type="password" id="recoverConfirmPass" placeholder="Repite la contraseña" required>
                    <div id="recoverConfirmWarning" class="strength-warning" aria-live="polite"></div>
                </div>
                <button type="submit">Restablecer contraseña</button>
            </form>
        </div>

        <!-- CAMBIAR CONTRASEÑA (primer login) -->
        <div id="changeView" class="hidden">
            <div class="logo">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                </svg>
            </div>

            <h2>Cambiar Contraseña</h2>
            <p class="subtitle">Por seguridad debes cambiar tu contraseña antes de continuar.</p>

            <div id="changeMessage" class="message"></div>

            <form id="changeForm">
                <div class="form-group">
                    <label for="newPassword">Nueva contraseña</label>
                    <input type="password" id="newPassword" placeholder="Mínimo 8 caracteres" required>
                    <div id="newPasswordWarning" class="strength-warning" aria-live="polite"></div>
                </div>

                <div class="form-group">
                    <label for="confirmPassword">Confirmar contraseña</label>
                    <input type="password" id="confirmPassword" placeholder="Repite la contraseña" required>
                    <div id="confirmPasswordWarning" class="strength-warning" aria-live="polite"></div>
                </div>

                <button type="submit">Actualizar contraseña</button>
            </form>
        </div>
    </div>

    <script src="login/login.js"></script>
</body>
</html>
