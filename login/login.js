function getUsuario() {
    const data = sessionStorage.getItem('usuario');
    if (!data) return null;
    try {
        return JSON.parse(data);
    } catch {
        return null;
    }
}

function requireRol(rolEsperado) {
    const usuario = getUsuario();
    if (!usuario || Number(usuario.id_rol) !== Number(rolEsperado)) {
        window.location.href = '../index.php';
        return null;
    }

    if (Number(rolEsperado) !== 1 && !usuario.id_sucursal) {
        sessionStorage.removeItem('usuario');
        window.location.href = '../index.php';
        return null;
    }

    return usuario;
}

function logout() {
    sessionStorage.removeItem('usuario');
    window.location.href = '../index.php';
}

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
    if (isBlacklisted(password, email) || password.length < 8) {
        return {
            level: 'weak',
            message: 'Contraseña muy fácil'
        };
    }

    if (password.length < 14) {
        return {
            level: 'medium',
            message: 'Contraseña media'
        };
    }

    return {
        level: 'strong',
        message: 'Contraseña segura'
    };
}

const ROLE_ROUTES = {
    1: 'admin/',
    2: 'gerente/',
    3: 'cajero/'
};

async function loginUser(email, password) {
    const response = await fetch('login/login.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ email, password })
    });

    const result = await response.json();

    if (!response.ok || !result.success) {
        return {
            success: false,
            message: result.message || 'No fue posible iniciar sesión.'
        };
    }

    const user = result.user;
    const destination = ROLE_ROUTES[Number(user.id_rol)];

    if (!destination || !user.id_usuario || !user.email) {
        return {
            success: false,
            message: 'La cuenta no tiene un rol válido configurado.'
        };
    }

    sessionStorage.setItem('usuario', JSON.stringify(user));

    return {
        success: true,
        destination
    };
}

document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');

    if (!loginForm) {
        return;
    }

    const loginEmail = document.getElementById('loginEmail');
    const loginPassword = document.getElementById('loginPassword');
    const loginMessage = document.getElementById('loginMessage');
    const loginStrength = document.getElementById('loginStrengthWarning');

    function updatePasswordWarning() {
        const email = loginEmail.value.trim();
        const password = loginPassword.value;

        if (!loginStrength) {
            return;
        }

        if (!password) {
            loginStrength.className = 'strength-warning';
            loginStrength.textContent = '';
            return;
        }

        const result = evaluateStrength(password, email);
        loginStrength.className = 'strength-warning visible ' + result.level;
        loginStrength.textContent = result.message;
    }

    loginPassword.addEventListener('input', updatePasswordWarning);
    loginEmail.addEventListener('input', updatePasswordWarning);

    loginForm.addEventListener('submit', async event => {
        event.preventDefault();

        const email = loginEmail.value.trim();
        const password = loginPassword.value;

        if (!email || !email.includes('@')) {
            loginMessage.className = 'message error';
            loginMessage.textContent = 'Ingresa un correo electrónico válido.';
            return;
        }

        let result;

        try {
            result = await loginUser(email, password);
        } catch (error) {
            loginMessage.className = 'message error';
            loginMessage.textContent =
                'No se pudo conectar con el servidor. Intenta nuevamente.';
            return;
        }

        if (result.success) {
            loginMessage.className = 'message success';
            loginMessage.textContent = 'Inicio de sesión exitoso.';

            setTimeout(() => {
                window.location.href = result.destination;
            }, 500);
        } else {
            loginMessage.className = 'message error';
            loginMessage.textContent = result.message;
        }
    });
});