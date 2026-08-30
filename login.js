// ======================================================
// LOGIN SIN REGISTRO
// No usa localStorage y no busca usuarios registrados.
// ======================================================

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
            company,
            company.toLowerCase(),
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

    return getBlacklist(email).some(blocked =>
        blocked && passwordLower === blocked.toLowerCase()
    );
}

// 3 niveles del warning
function evaluateStrength(password, email) {
    if (isBlacklisted(password, email) || password.length < 8) {
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

document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
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

        // Validar correo
        if (!email || !email.includes('@')) {
            loginMessage.className = 'message error';
            loginMessage.textContent =
                'Ingresa un correo electrónico válido.';
            return;
        }

        // Bloquear contraseña muy fácil
        const strength = evaluateStrength(password, email);

        if (strength.level === 'weak') {
            loginMessage.className = 'message error';
            loginMessage.textContent =
                'La contraseña es muy fácil. Usa mínimo 8 caracteres y evita contraseñas comunes.';
            return;
        }

        // Login correcto SIN registro
        loginMessage.className = 'message success';
        loginMessage.textContent = 'Inicio de sesión exitoso.';

        // Abrir Derechos.html en otra pestaña
        setTimeout(() => {
            const derechosTab = window.open('Derechos.html', '_blank');

            if (derechosTab) {
                derechosTab.focus();
            }

            // Intentar cerrar la pestaña de inicio de sesión.
            // Algunos navegadores pueden bloquear window.close()
            // si esta pestaña fue abierta manualmente.
            window.close();

            // Respaldo: si el navegador no permite cerrarla,
            // ocultamos el login para que no siga mostrando el formulario.
            setTimeout(() => {
                if (!window.closed) {
                    document.body.innerHTML = `
                        <div style="
                            min-height:100vh;
                            display:flex;
                            justify-content:center;
                            align-items:center;
                            font-family:Segoe UI, sans-serif;
                            background:#f4f7fa;
                            color:#475569;
                            text-align:center;
                            padding:20px;
                        ">
                            <div>
                                <h2 style="color:#1e293b; margin-bottom:10px;">
                                    Sesión iniciada
                                </h2>
                                <p>Ya puedes cerrar esta pestaña.</p>
                            </div>
                        </div>
                    `;
                }
            }, 300);
        }, 300);
    });
});
