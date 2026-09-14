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

    let currentUser = null;

    if (!loginForm || !changeForm) {
        return;
    }

    function showChangeView(user) {
        currentUser = user;
        loginView.classList.add('hidden');
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

        sessionStorage.setItem('usuario', JSON.stringify(user));
        loginMessage.className = 'message success';
        loginMessage.textContent = 'Inicio de sesión exitoso.';

        if (Number(user.debe_cambiar) === 1) {
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
            response = await apiRequest({ action: 'change_password', password: value });
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