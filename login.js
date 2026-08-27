
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

// Crea una lista negra usando también palabras relacionadas
// con el empleado y la empresa obtenidas del correo.
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

// TRES NIVELES DE ADVERTENCIA
function evaluateStrength(password, email) {

    // Contraseña común o relacionada con empresa/empleado.
    if (isBlacklisted(password, email)) {
        return {
            level: 'weak',
            message: '❌ Contraseña muy fácil'
        };
    }

    // Menos de 8 caracteres.
    if (password.length < 8) {
        return {
            level: 'weak',
            message: '❌ Contraseña muy fácil'
        };
    }

    // Entre 8 y 13 caracteres.
    if (password.length < 14) {
        return {
            level: 'medium',
            message: '⚠️ Contraseña media'
        };
    }

    // 14 caracteres o más.
    return {
        level: 'strong',
        message: '✅ Contraseña segura'
    };
}

// Mínimo 8 caracteres y no debe estar en la lista negra.
function passwordIsAllowed(password, email) {
    return password.length >= 8 && !isBlacklisted(password, email);
}


// INICIO DE SESIÓN

function getUsers() {
    return JSON.parse(localStorage.getItem('users')) || {};
}

function loginUser(email, password) {
    const users = getUsers();

    if (!users[email]) {
        return {
            success: false,
            message: 'Correo no registrado.'
        };
    }

    if (users[email].password !== password) {
        return {
            success: false,
            message: 'Contraseña incorrecta.'
        };
    }

    sessionStorage.setItem('currentUser', email);

    return {
        success: true
    };
}

function getCurrentUser() {
    return sessionStorage.getItem('currentUser');
}

function logout() {
    sessionStorage.removeItem('currentUser');
}


// INTERFAZ

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

        if (!password) {
            loginStrength.className = 'strength-warning';
            loginStrength.textContent = '';
            return;
        }

        const result = evaluateStrength(password, email);

        loginStrength.className =
            'strength-warning visible ' + result.level;

        loginStrength.textContent = result.message;
    }

    loginPassword.addEventListener('input', updatePasswordWarning);
    loginEmail.addEventListener('input', updatePasswordWarning);

    loginForm.addEventListener('submit', (event) => {
        event.preventDefault();

        const email = loginEmail.value.trim();
        const password = loginPassword.value;

        if (!email || !email.includes('@')) {
            loginMessage.className = 'message error';
            loginMessage.textContent =
                'Ingresa un correo electrónico válido.';
            return;
        }

        const result = loginUser(email, password);

        if (result.success) {
            loginMessage.className = 'message success';
            loginMessage.textContent =
                'Inicio de sesión exitoso.';

            setTimeout(() => {
                window.location.href = 'index.html';
            }, 500);
        } else {
            loginMessage.className = 'message error';
            loginMessage.textContent = result.message;
        }
    });
});
