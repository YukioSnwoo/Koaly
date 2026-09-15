<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión</title>
    <link rel="stylesheet" href="login/login.css">
</head>
<body>
    <div class="container">
        <div id="loginView">
            <h2>Iniciar Sesión</h2>

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
        </div>

        <div id="changeView" class="hidden">
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