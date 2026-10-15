const COMMON_PASSWORDS = [
    '12345678',
    'password',
    '123456789',
    'qwerty',
    'abc123',
    'password123',
    'admin',
    'letmein',
    'welcome',
    '12345',
    '1234567890',
    'qwerty123',
    '1q2w3e4r',
    'iloveyou',
    'monkey'
];

const ROLE_ROUTES = {
    1: 'admin/',
    2: 'gerente/',
    3: 'cajero/'
};

function getBlacklist(email) {
    const variants = [];

    if (email) {
        const parts = email.split('@');
        const employee = parts[0] || '';
        const company = (parts[1] || '').split('.')[0] || '';

        variants.push(
            employee,
            employee.toLowerCase(),
            employee.toUpperCase(),
            company,
            company.toLowerCase(),
            company.toUpperCase(),
            employee + company,
            company + employee,
            employee + '123',
            company + '123',
            employee + '2024',
            company + '2024'
        );
    }

    return [...new Set([...COMMON_PASSWORDS, ...variants])];
}

function isBlacklisted(password, email) {
    const passwordLower = password.toLowerCase();

    return getBlacklist(email).some(blocked => {
        return blocked && passwordLower === blocked.toLowerCase();
    });
}

function evaluateStrength(password, email) {
    if (isBlacklisted(password, email)) {
        return {
            level: 'weak',
            message: '❌ Contraseña muy fácil'
        };
    }

    if (password.length < 8) {
        return {
            level: 'weak',
            message: '❌ Contraseña muy fácil'
        };
    }

    if (password.length < 14) {
        return {
            level: 'medium',
            message: '⚠️ Contraseña media'
        };
    }

    return {
        level: 'strong',
        message: '✅ Contraseña segura'
    };
}

function passwordIsAllowed(password, email) {
    return password.length >= 8 && !isBlacklisted(password, email);
}

async function apiRequest(payload) {
    const response = await fetch('login/login.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
    });

    let result;

    try {
        result = await response.json();
    } catch (error) {
        result = { success: false, message: 'Respuesta inválida del servidor.' };
    }

    return { ok: response.ok, result };
}

document.addEventListener('DOMContentLoaded', () => {
    const loginView = document.getElementById('loginView');
    const changeView = document.getElementById('changeView');
    const recoverStep1 = document.getElementById('recoverStep1');
    const recoverStep2 = document.getElementById('recoverStep2');

    const loginForm = document.getElementById('loginForm');
    const loginEmail = document.getElementById('loginEmail');
    const loginPassword = document.getElementById('loginPassword');
    const loginMessage = document.getElementById('loginMessage');

    const changeForm = document.getElementById('changeForm');
    const newPassword = document.getElementById('newPassword');
    const confirmPassword = document.getElementById('confirmPassword');
    const newPasswordWarning = document.getElementById('newPasswordWarning');
    const confirmPasswordWarning = document.getElementById('confirmPasswordWarning');
    const changeMessage = document.getElementById('changeMessage');

    const forgotLink = document.getElementById('forgotLink');
    const backToLogin1 = document.getElementById('backToLogin1');
    const backToLogin2 = document.getElementById('backToLogin2');
    const recoverForm1 = document.getElementById('recoverForm1');
    const recoverForm2 = document.getElementById('recoverForm2');
    const recoverEmail = document.getElementById('recoverEmail');
    const recoverMsg1 = document.getElementById('recoverMsg1');
    const recoverMsg2 = document.getElementById('recoverMsg2');
    const recoverCode = document.getElementById('recoverCode');
    const recoverNewPass = document.getElementById('recoverNewPass');
    const recoverConfirmPass = document.getElementById('recoverConfirmPass');
    const recoverPassWarning = document.getElementById('recoverPassWarning');
    const recoverConfirmWarning = document.getElementById('recoverConfirmWarning');
    const btnSendCode = document.getElementById('btnSendCode');

    let currentUser = null;
    let recoverEmailSaved = '';

    if (!loginForm || !changeForm) {
        return;
    }

    function hideAllViews() {
        loginView.classList.add('hidden');
        changeView.classList.add('hidden');
        recoverStep1.classList.add('hidden');
        recoverStep2.classList.add('hidden');
    }

    function showLoginView() {
        hideAllViews();
        loginView.classList.remove('hidden');
        loginMessage.className = 'message';
        loginMessage.textContent = '';
    }

    function showRecoverStep1() {
        hideAllViews();
        recoverStep1.classList.remove('hidden');
        recoverEmail.value = '';
        recoverMsg1.className = 'message';
        recoverMsg1.textContent = '';
        btnSendCode.disabled = false;
        btnSendCode.textContent = 'Enviar código';
    }

    function showRecoverStep2() {
        hideAllViews();
        recoverStep2.classList.remove('hidden');
        recoverCode.value = '';
        recoverNewPass.value = '';
        recoverConfirmPass.value = '';
        recoverPassWarning.className = 'strength-warning';
        recoverPassWarning.textContent = '';
        recoverConfirmWarning.className = 'strength-warning';
        recoverConfirmWarning.textContent = '';
        recoverMsg2.className = 'message';
        recoverMsg2.textContent = '';
    }

    function showChangeView(user) {
        currentUser = user;
        hideAllViews();
        changeView.classList.remove('hidden');
        newPassword.value = '';
        confirmPassword.value = '';
        newPasswordWarning.className = 'strength-warning';
        newPasswordWarning.textContent = '';
        confirmPasswordWarning.className = 'strength-warning';
        confirmPasswordWarning.textContent = '';
        changeMessage.className = 'message';
        changeMessage.textContent = '';
    }

    // ===== Navegación recuperar =====
    forgotLink.addEventListener('click', (e) => {
        e.preventDefault();
        showRecoverStep1();
    });

    backToLogin1.addEventListener('click', (e) => {
        e.preventDefault();
        showLoginView();
    });

    backToLogin2.addEventListener('click', (e) => {
        e.preventDefault();
        showLoginView();
    });

    // ===== Paso 1: enviar código (BACKEND) =====
    recoverForm1.addEventListener('submit', async (e) => {
        e.preventDefault();

        const email = recoverEmail.value.trim();

        if (!email || !email.includes('@')) {
            recoverMsg1.className = 'message error';
            recoverMsg1.textContent = 'Ingresa un correo válido.';
            return;
        }

        btnSendCode.disabled = true;
        btnSendCode.textContent = 'Enviando...';

        let response;
        try {
            response = await apiRequest({ action: 'send_recover_code', email });
        } catch (err) {
            recoverMsg1.className = 'message error';
            recoverMsg1.textContent = 'No se pudo conectar con el servidor.';
            btnSendCode.disabled = false;
            btnSendCode.textContent = 'Enviar código';
            return;
        }

        const { ok, result } = response;

        if (!ok || !result.success) {
            recoverMsg1.className = 'message error';
            recoverMsg1.textContent = result.message || 'No se pudo enviar el código.';
            btnSendCode.disabled = false;
            btnSendCode.textContent = 'Enviar código';
            return;
        }

        recoverEmailSaved = email;

        recoverMsg1.className = 'message success';
        recoverMsg1.textContent = result.message || 'Código enviado. Revisa tu correo.';

        setTimeout(() => {
            showRecoverStep2();
        }, 1100);
    });

    // ===== Paso 2: validar código + nueva pass (BACKEND) =====
    function updateRecoverPassWarning() {
        const value = recoverNewPass.value;
        if (!value) {
            recoverPassWarning.className = 'strength-warning';
            recoverPassWarning.textContent = '';
            return;
        }
        const result = evaluateStrength(value, recoverEmailSaved);
        recoverPassWarning.className = 'strength-warning visible ' + result.level;
        recoverPassWarning.textContent = result.message;
    }

    function updateRecoverConfirmWarning() {
        const value = recoverConfirmPass.value;
        if (!value) {
            recoverConfirmWarning.className = 'strength-warning';
            recoverConfirmWarning.textContent = '';
            return;
        }
        if (value !== recoverNewPass.value) {
            recoverConfirmWarning.className = 'strength-warning visible weak';
            recoverConfirmWarning.textContent = '❌ Las contraseñas no coinciden';
            return;
        }
        recoverConfirmWarning.className = 'strength-warning visible strong';
        recoverConfirmWarning.textContent = '✅ Las contraseñas coinciden';
    }

    recoverNewPass.addEventListener('input', () => {
        updateRecoverPassWarning();
        if (recoverConfirmPass.value) updateRecoverConfirmWarning();
    });
    recoverConfirmPass.addEventListener('input', updateRecoverConfirmWarning);

    recoverForm2.addEventListener('submit', async (e) => {
        e.preventDefault();

        const code = recoverCode.value.trim();
        const pass = recoverNewPass.value;
        const confirm = recoverConfirmPass.value;

        if (!code || code.length < 4) {
            recoverMsg2.className = 'message error';
            recoverMsg2.textContent = 'Ingresa el código de verificación.';
            return;
        }

        if (!passwordIsAllowed(pass, recoverEmailSaved)) {
            recoverMsg2.className = 'message error';
            recoverMsg2.textContent = 'La contraseña es demasiado simple. Usa al menos 8 caracteres.';
            return;
        }

        if (pass !== confirm) {
            recoverMsg2.className = 'message error';
            recoverMsg2.textContent = 'Las contraseñas no coinciden.';
            return;
        }

        let response;
        try {
            response = await apiRequest({
                action: 'reset_password',
                email: recoverEmailSaved,
                code: code,
                password: pass
            });
        } catch (err) {
            recoverMsg2.className = 'message error';
            recoverMsg2.textContent = 'No se pudo conectar con el servidor.';
            return;
        }

        const { ok, result } = response;

        if (!ok || !result.success) {
            recoverMsg2.className = 'message error';
            recoverMsg2.textContent = result.message || 'No se pudo restablecer la contraseña.';
            return;
        }

        recoverMsg2.className = 'message success';
        recoverMsg2.textContent = result.message || '¡Contraseña restablecida! Ya puedes iniciar sesión.';

        setTimeout(() => {
            showLoginView();
            loginEmail.value = recoverEmailSaved;
            loginPassword.value = '';
        }, 1200);
    });

    // ===== Login normal =====
    function updateNewPasswordWarning() {
        const value = newPassword.value;

        if (!value) {
            newPasswordWarning.className = 'strength-warning';
            newPasswordWarning.textContent = '';
            return;
        }

        const result = evaluateStrength(value, currentUser ? currentUser.email : '');

        newPasswordWarning.className = 'strength-warning visible ' + result.level;
        newPasswordWarning.textContent = result.message;
    }

    function updateConfirmPasswordWarning() {
        const value = confirmPassword.value;

        if (!value) {
            confirmPasswordWarning.className = 'strength-warning';
            confirmPasswordWarning.textContent = '';
            return;
        }

        if (value !== newPassword.value) {
            confirmPasswordWarning.className = 'strength-warning visible weak';
            confirmPasswordWarning.textContent = '❌ Las contraseñas no coinciden';
            return;
        }

        confirmPasswordWarning.className = 'strength-warning visible strong';
        confirmPasswordWarning.textContent = '✅ Las contraseñas coinciden';
    }

    newPassword.addEventListener('input', () => {
        updateNewPasswordWarning();
        if (confirmPassword.value) {
            updateConfirmPasswordWarning();
        }
    });

    confirmPassword.addEventListener('input', updateConfirmPasswordWarning);

    loginForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        const email = loginEmail.value.trim();
        const password = loginPassword.value;

        if (!email || !email.includes('@')) {
            loginMessage.className = 'message error';
            loginMessage.textContent = 'Ingresa un correo electrónico válido.';
            return;
        }

        let response;

        try {
            response = await apiRequest({ action: 'login', email, password });
        } catch (error) {
            loginMessage.className = 'message error';
            loginMessage.textContent = 'No se pudo conectar con el servidor. Intenta nuevamente.';
            return;
        }

        const { ok, result } = response;

        if (!ok || !result.success) {
            loginMessage.className = 'message error';
            loginMessage.textContent = result.message || 'No fue posible iniciar sesión.';
            return;
        }

        const user = result.user;

        if (!user || !user.id_usuario || !user.email) {
            loginMessage.className = 'message error';
            loginMessage.textContent = 'La cuenta no tiene un rol válido configurado.';
            return;
        }

        user.debe_cambiar = result.debe_cambiar ? 1 : 0;

        sessionStorage.setItem('usuario', JSON.stringify(user));
        loginMessage.className = 'message success';
        loginMessage.textContent = 'Inicio de sesión exitoso.';

        if (Number(user.debe_cambiar) === 1 || result.debe_cambiar) {
            setTimeout(() => showChangeView(user), 400);
            return;
        }

        const destination = ROLE_ROUTES[Number(user.id_rol)];

        if (!destination) {
            loginMessage.className = 'message error';
            loginMessage.textContent = 'La cuenta no tiene un rol válido configurado.';
            return;
        }

        setTimeout(() => {
            window.location.href = destination;
        }, 500);
    });

    changeForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!currentUser || !currentUser.email) {
            changeMessage.className = 'message error';
            changeMessage.textContent = 'Sesión no válida. Vuelve a iniciar sesión.';
            return;
        }

        const value = newPassword.value;
        const confirmValue = confirmPassword.value;

        if (!passwordIsAllowed(value, currentUser.email)) {
            changeMessage.className = 'message error';
            changeMessage.textContent = 'La contraseña es demasiado simple. Usa al menos 8 caracteres y evita palabras comunes.';
            return;
        }

        if (value !== confirmValue) {
            changeMessage.className = 'message error';
            changeMessage.textContent = 'Las contraseñas no coinciden.';
            return;
        }

        let response;

        try {
            response = await apiRequest({
                action: 'change_password',
                password: value
            });
        } catch (error) {
            changeMessage.className = 'message error';
            changeMessage.textContent = 'No se pudo conectar con el servidor. Intenta nuevamente.';
            return;
        }

        const { ok, result } = response;

        if (!ok || !result.success) {
            changeMessage.className = 'message error';
            changeMessage.textContent = result.message || 'No fue posible actualizar la contraseña.';
            return;
        }

        changeMessage.className = 'message success';
        changeMessage.textContent = 'Contraseña actualizada correctamente.';

        currentUser.debe_cambiar = 0;
        sessionStorage.setItem('usuario', JSON.stringify(currentUser));

        const destination = ROLE_ROUTES[Number(currentUser.id_rol)] || 'index.php';

        setTimeout(() => {
            window.location.href = destination;
        }, 800);
    });
});
