// Inicio de sesión, cambio obligatorio y recuperación de contraseña.
// Habla con login/login.php; el servidor vuelve a validar todo.

const API = 'login/login.php';
const SEGUNDOS_REENVIO = 60; // igual que CODIGO_SEGUNDOS_ESPERA en login.php

const CONTRASENAS_COMUNES = [
    '12345678', 'password', '123456789', 'qwerty', 'abc123',
    'password123', 'admin', 'letmein', 'welcome', '12345',
    '1234567890', 'qwerty123', '1q2w3e4r', 'iloveyou', 'monkey'
];

const $ = (id) => document.getElementById(id);

/** Mismas reglas que passwordIsTooSimple() en reglas_contrasena.php (salvo el nombre, que solo conoce el servidor). */
function problemaContrasena(password, email) {
    if (password.length < 8) return 'Debe tener mínimo 8 caracteres';
    if (/^[a-zA-Z]+$/.test(password)) return 'No puede ser solo letras';
    if (/^[0-9]+$/.test(password)) return 'No puede ser solo números';
    if (/^[^a-zA-Z0-9]+$/.test(password)) return 'No puede ser solo símbolos';

    const minusculas = password.toLowerCase();
    if (CONTRASENAS_COMUNES.includes(minusculas)) return 'Es demasiado común';

    const usuarioCorreo = (email || '').split('@')[0].toLowerCase();
    if (usuarioCorreo.length >= 3 && minusculas.includes(usuarioCorreo)) return 'No puede incluir tu correo';

    return null;
}

function evaluarFuerza(password, email) {
    const problema = problemaContrasena(password, email);
    if (problema) return { nivel: 'weak', texto: '❌ ' + problema };
    if (password.length < 12) return { nivel: 'medium', texto: '⚠️ Aceptable. Con 12 caracteres o más es más segura' };
    return { nivel: 'strong', texto: '✅ Contraseña segura' };
}

function mostrarMensaje(caja, tipo, texto) {
    caja.className = 'message ' + tipo;
    caja.textContent = texto;
}

function limpiarMensaje(caja) {
    caja.className = 'message';
    caja.textContent = '';
}

/** Aviso bajo un campo; sin texto lo oculta. */
function mostrarAviso(caja, nivel, texto) {
    caja.className = texto ? 'strength-warning visible ' + nivel : 'strength-warning';
    caja.textContent = texto || '';
}

/** Desactiva el botón mientras espera al servidor (evita envíos dobles). */
function ocupado(boton, activo, textoEspera) {
    if (activo) {
        boton.dataset.texto = boton.textContent;
        boton.textContent = textoEspera;
    } else if (boton.dataset.texto) {
        boton.textContent = boton.dataset.texto;
    }
    boton.disabled = activo;
}

async function api(datos) {
    let respuesta;
    try {
        respuesta = await fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(datos)
        });
    } catch (error) {
        return { success: false, message: 'No se pudo conectar con el servidor. Revisa tu conexión.' };
    }

    try {
        return await respuesta.json();
    } catch (error) {
        return { success: false, message: 'Respuesta inválida del servidor. Intenta de nuevo.' };
    }
}

function correoValido(input) {
    return input.value.trim() !== '' && input.checkValidity();
}

/** Muestra la fuerza de la contraseña y si la confirmación coincide, mientras se escribe. */
function vigilarContrasena(campo, confirmacion, avisoCampo, avisoConfirmacion, obtenerCorreo) {
    const revisarConfirmacion = () => {
        if (!confirmacion.value) return mostrarAviso(avisoConfirmacion);
        if (confirmacion.value !== campo.value) {
            return mostrarAviso(avisoConfirmacion, 'weak', '❌ Las contraseñas no coinciden');
        }
        mostrarAviso(avisoConfirmacion, 'strong', '✅ Las contraseñas coinciden');
    };

    campo.addEventListener('input', () => {
        if (!campo.value) {
            mostrarAviso(avisoCampo);
        } else {
            const fuerza = evaluarFuerza(campo.value, obtenerCorreo());
            mostrarAviso(avisoCampo, fuerza.nivel, fuerza.texto);
        }
        if (confirmacion.value) revisarConfirmacion();
    });
    confirmacion.addEventListener('input', revisarConfirmacion);
}

/** Error de la nueva contraseña antes de enviarla, o null si se puede enviar. */
function validarNueva(password, confirmar, email) {
    const problema = problemaContrasena(password, email);
    if (problema) return 'La contraseña no es válida: ' + problema.toLowerCase() + '.';
    if (password !== confirmar) return 'Las contraseñas no coinciden.';
    return null;
}

document.addEventListener('DOMContentLoaded', () => {
    const vistas = ['loginView', 'recoverStep1', 'recoverStep2', 'changeView'].map($);

    let usuarioActual = null;
    let correoRecuperacion = '';
    let temporizadorReenvio = null;

    function mostrarVista(vista) {
        vistas.forEach((v) => v.classList.toggle('hidden', v !== vista));
        vista.querySelectorAll('form').forEach((f) => f.reset());
        vista.querySelectorAll('.message').forEach(limpiarMensaje);
        vista.querySelectorAll('.strength-warning').forEach((a) => mostrarAviso(a));
        const primero = vista.querySelector('input:not([type=hidden])');
        if (primero) primero.focus();
    }

    // ===== Mostrar / ocultar contraseña =====
    document.querySelectorAll('.toggle-password').forEach((boton) => {
        boton.addEventListener('click', () => {
            const campo = $(boton.dataset.target);
            const mostrar = campo.type === 'password';
            campo.type = mostrar ? 'text' : 'password';
            boton.textContent = mostrar ? 'Ocultar' : 'Ver';
            boton.setAttribute('aria-label', mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña');
        });
    });
    // ===== Acceso rápido (BETA) — borrar cuando terminemos de programar =====
document.querySelectorAll('#quickAccess .quick-access-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
        $('loginEmail').value    = btn.dataset.email;
        $('loginPassword').value = '123';
        // requestSubmit dispara el submit real (y su validación + API)
        $('loginForm').requestSubmit();
    });
});

    // ===== Navegación =====
    $('forgotLink').addEventListener('click', () => {
        const correo = $('loginEmail').value.trim();
        mostrarVista($('recoverStep1'));
        $('recoverEmail').value = correo; // ahorra volver a escribirlo
    });

    document.querySelectorAll('[data-volver]').forEach((boton) => {
        boton.addEventListener('click', () => {
            clearInterval(temporizadorReenvio);
            mostrarVista($('loginView'));
            if (correoRecuperacion) $('loginEmail').value = correoRecuperacion;
        });
    });

    // ===== Iniciar sesión =====
    $('loginForm').addEventListener('submit', async (evento) => {
        evento.preventDefault();
        const caja = $('loginMessage');
        const boton = evento.submitter || evento.target.querySelector('[type=submit]');
        const email = $('loginEmail').value.trim();
        const password = $('loginPassword').value;

        if (!correoValido($('loginEmail'))) return mostrarMensaje(caja, 'error', 'Escribe un correo electrónico válido.');
        if (!password) return mostrarMensaje(caja, 'error', 'Escribe tu contraseña.');

        ocupado(boton, true, 'Ingresando…');
        const resultado = await api({ action: 'login', email, password });

        // Cajero que aún no acepta el aviso de privacidad: primero lo lee y lo acepta
        if (resultado.requiere_aviso && resultado.enlace) {
            mostrarMensaje(caja, 'success', resultado.message);
            setTimeout(() => { window.location.href = resultado.enlace; }, 1200);
            return;
        }

        if (!resultado.success) {
            ocupado(boton, false);
            $('loginPassword').value = '';
            $('loginPassword').focus();
            return mostrarMensaje(caja, 'error', resultado.message || 'No fue posible iniciar sesión.');
        }

        usuarioActual = resultado.user;
        sessionStorage.setItem('usuario', JSON.stringify(usuarioActual)); // lo usa auth.js

        if (resultado.debe_cambiar) {
            ocupado(boton, false);
            return mostrarVista($('changeView'));
        }

        mostrarMensaje(caja, 'success', 'Inicio de sesión exitoso. Entrando…');
        window.location.href = resultado.destino;
    });

    // ===== Cambio obligatorio (primer ingreso) =====
    vigilarContrasena($('newPassword'), $('confirmPassword'), $('newPasswordWarning'), $('confirmPasswordWarning'),
        () => (usuarioActual ? usuarioActual.email : ''));

    $('changeForm').addEventListener('submit', async (evento) => {
        evento.preventDefault();
        const caja = $('changeMessage');
        const boton = evento.submitter || evento.target.querySelector('[type=submit]');
        const password = $('newPassword').value;

        const error = validarNueva(password, $('confirmPassword').value, usuarioActual ? usuarioActual.email : '');
        if (error) return mostrarMensaje(caja, 'error', error);

        ocupado(boton, true, 'Guardando…');
        const resultado = await api({ action: 'change_password', password });

        if (!resultado.success) {
            ocupado(boton, false);
            return mostrarMensaje(caja, 'error', resultado.message || 'No fue posible actualizar la contraseña.');
        }

        if (usuarioActual) {
            usuarioActual.debe_cambiar = 0;
            sessionStorage.setItem('usuario', JSON.stringify(usuarioActual));
        }
        mostrarMensaje(caja, 'success', 'Contraseña actualizada. Entrando…');
        window.location.href = resultado.destino || 'index.php';
    });

    // ===== Recuperación · paso 1: pedir el código =====
    async function enviarCodigo(caja, boton) {
        ocupado(boton, true, 'Enviando…');
        const resultado = await api({ action: 'send_recover_code', email: correoRecuperacion });
        ocupado(boton, false);

        if (!resultado.success) {
            mostrarMensaje(caja, 'error', resultado.message || 'No se pudo enviar el código.');
            return false;
        }
        return true;
    }

    function iniciarEsperaReenvio() {
        const boton = $('btnResend');
        let restantes = SEGUNDOS_REENVIO;
        const pintar = () => {
            boton.disabled = restantes > 0;
            boton.textContent = restantes > 0 ? `reenvía el código (${restantes} s)` : 'reenvía el código';
        };
        clearInterval(temporizadorReenvio);
        pintar();
        temporizadorReenvio = setInterval(() => {
            restantes--;
            pintar();
            if (restantes <= 0) clearInterval(temporizadorReenvio);
        }, 1000);
    }

    $('recoverForm1').addEventListener('submit', async (evento) => {
        evento.preventDefault();
        const caja = $('recoverMsg1');

        if (!correoValido($('recoverEmail'))) return mostrarMensaje(caja, 'error', 'Escribe un correo electrónico válido.');

        correoRecuperacion = $('recoverEmail').value.trim();
        if (!(await enviarCodigo(caja, $('btnSendCode')))) return;

        mostrarVista($('recoverStep2'));
        $('recoverEmailShown').textContent = correoRecuperacion;
        mostrarMensaje($('recoverMsg2'), 'success', 'Si el correo está registrado, te llegará un código en unos minutos.');
        iniciarEsperaReenvio();
    });

    $('btnResend').addEventListener('click', async () => {
        const caja = $('recoverMsg2');
        if (!(await enviarCodigo(caja, $('btnResend')))) return;
        mostrarMensaje(caja, 'success', 'Te enviamos un código nuevo. El anterior ya no sirve.');
        $('recoverCode').value = '';
        $('recoverCode').focus();
        iniciarEsperaReenvio();
    });

    // ===== Recuperación · paso 2: código + contraseña nueva =====
    $('recoverCode').addEventListener('input', (evento) => {
        evento.target.value = evento.target.value.replace(/\D/g, '').slice(0, 6);
    });

    vigilarContrasena($('recoverNewPass'), $('recoverConfirmPass'), $('recoverPassWarning'), $('recoverConfirmWarning'),
        () => correoRecuperacion);

    $('recoverForm2').addEventListener('submit', async (evento) => {
        evento.preventDefault();
        const caja = $('recoverMsg2');
        const boton = evento.submitter || evento.target.querySelector('[type=submit]');
        const codigo = $('recoverCode').value.trim();
        const password = $('recoverNewPass').value;

        if (!/^\d{6}$/.test(codigo)) return mostrarMensaje(caja, 'error', 'El código tiene 6 dígitos.');
        const error = validarNueva(password, $('recoverConfirmPass').value, correoRecuperacion);
        if (error) return mostrarMensaje(caja, 'error', error);

        ocupado(boton, true, 'Guardando…');
        const resultado = await api({ action: 'reset_password', email: correoRecuperacion, code: codigo, password });
        ocupado(boton, false);

        if (!resultado.success) return mostrarMensaje(caja, 'error', resultado.message || 'No se pudo restablecer la contraseña.');

        clearInterval(temporizadorReenvio);
        mostrarVista($('loginView'));
        $('loginEmail').value = correoRecuperacion;
        $('loginPassword').focus();
        mostrarMensaje($('loginMessage'), 'success', resultado.message || 'Contraseña restablecida. Ya puedes iniciar sesión.');
    });

    // Los paneles mandan aquí a quien debe cambiar su contraseña: index.php ya abrió esa vista
    if (document.body.dataset.debeCambiar === '1') {
        $('newPassword').focus();
    }
    // ===== Koalaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa =====

    const mascotaCara = $('mascotaCara');
    const mascotaBurbuja = $('mascotaBurbuja');
    const emailInput = $('loginEmail');
    const passInput = $('loginPassword');

    const IMAGENES_KOALA = {
        normal: 'Imagenes/koala/koala-normal.png',
        feliz: 'Imagenes/koala/koala-feliz.png',
        pensando: 'Imagenes/koala/koala-pensando.png',
        secreto: 'Imagenes/koala/koala-secreto.png',
        fiesta: 'Imagenes/koala/koala-fiesta.png' 
    };

    function animarKoala(estado, mensaje, esFiesta = false) {
        if (!mascotaCara) return;

        
        mascotaCara.src = IMAGENES_KOALA[estado] || IMAGENES_KOALA.normal;
        
        
        mascotaCara.classList.remove('saltando', 'emocionada');
        void mascotaCara.offsetWidth; 
        mascotaCara.classList.add(esFiesta ? 'emocionada' : 'saltando');

        if (mensaje !== undefined) {
            mascotaBurbuja.textContent = mensaje;
        }
    }

    if (emailInput && passInput && mascotaCara) {
        emailInput.addEventListener('focus', () => animarKoala('normal', '¿Cómo te llamas?'));
        
        emailInput.addEventListener('input', () => {
            const email = emailInput.value.trim();
            if (email === '') animarKoala('normal', '¿Cuál es tu correo?');
            else if (email.includes('@') && email.includes('.')) animarKoala('feliz', '¡Muy bien!');
            else animarKoala('pensando', 'Escribe tu correo completo');
        });

        passInput.addEventListener('focus', () => {
            if (emailInput.value.includes('@')) animarKoala('secreto', '¡Secreto.');
            else animarKoala('pensando', 'Primero necesito tu correo');
        });

        passInput.addEventListener('input', () => {
            const pass = passInput.value;
            if (pass.length === 0) animarKoala('normal', 'Tu contraseña es secreta.');
            else if (pass.length < 8) animarKoala('pensando', 'Un poco más segura...');
            else animarKoala('fiesta', '¡Listos para entrar!', true);
        }); 
    } 
}); 