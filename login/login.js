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

// ===== Idioma =====
// Es el de todo el sitio (cookie koaly_idioma, la leen los paneles al entrar).
// index.php pinta la página en ese idioma y deja aquí los textos de todos.
const COOKIE_IDIOMA = 'koaly_idioma';
const TEXTOS = window.LOGIN_TEXTOS || {};
let idioma = document.documentElement.lang in TEXTOS ? document.documentElement.lang : 'es';

/** Texto en el idioma actual (si falta, en español); {nombre} se reemplaza con vars. */
function tl(clave, vars = {}) {
    let texto = TEXTOS[idioma]?.[clave] ?? TEXTOS.es?.[clave] ?? clave;
    for (const [k, v] of Object.entries(vars)) texto = texto.split('{' + k + '}').join(String(v));
    return texto;
}

/** Mismas reglas que passwordIsTooSimple() en reglas_contrasena.php (salvo el nombre, que solo conoce el servidor). */
function problemaContrasena(password, email) {
    if (password.length < 8) return tl('regla.min8');
    if (/^[a-zA-Z]+$/.test(password)) return tl('regla.solo_letras');
    if (/^[0-9]+$/.test(password)) return tl('regla.solo_numeros');
    if (/^[^a-zA-Z0-9]+$/.test(password)) return tl('regla.solo_simbolos');

    const minusculas = password.toLowerCase();
    if (CONTRASENAS_COMUNES.includes(minusculas)) return tl('regla.comun');

    const usuarioCorreo = (email || '').split('@')[0].toLowerCase();
    if (usuarioCorreo.length >= 3 && minusculas.includes(usuarioCorreo)) return tl('regla.correo');

    return null;
}

function evaluarFuerza(password, email) {
    const problema = problemaContrasena(password, email);
    if (problema) return { nivel: 'weak', texto: '❌ ' + problema };
    if (password.length < 12) return { nivel: 'medium', texto: tl('fuerza.aceptable') };
    return { nivel: 'strong', texto: tl('fuerza.segura') };
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
        // Si se cambió de idioma mientras esperaba, el texto sale ya en el nuevo
        boton.textContent = boton.dataset.i18n ? tl(boton.dataset.i18n) : boton.dataset.texto;
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
        return { success: false, message: tl('msg.sin_conexion') };
    }

    try {
        return await respuesta.json();
    } catch (error) {
        return { success: false, message: tl('msg.respuesta_invalida') };
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
            return mostrarAviso(avisoConfirmacion, 'weak', tl('fuerza.no_coinciden'));
        }
        mostrarAviso(avisoConfirmacion, 'strong', tl('fuerza.coinciden'));
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
    if (problema) return tl('msg.no_valida', { motivo: problema.toLowerCase() });
    if (password !== confirmar) return tl('msg.no_coinciden');
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
        vista.querySelectorAll('.toggle-password').forEach((b) => verContrasena(b, false)); // reset() no regresa el tipo
        vista.querySelectorAll('.message').forEach(limpiarMensaje);
        vista.querySelectorAll('.strength-warning').forEach((a) => mostrarAviso(a));
        const primero = vista.querySelector('input:not([type=hidden])');
        if (primero) primero.focus();
    }

    // ===== Tema claro / oscuro =====
    // index.php abre en claro si no hay elección; se guarda con la misma clave que los paneles
    const botonTema = $('botonTema');

    function pintarBotonTema() {
        const oscuro = document.documentElement.getAttribute('data-tema') === 'oscuro';
        botonTema.setAttribute('aria-pressed', oscuro ? 'true' : 'false');
        botonTema.title = tl(oscuro ? 'tema.a_claro' : 'tema.a_oscuro');
    }

    botonTema.addEventListener('click', () => {
        const nuevo = document.documentElement.getAttribute('data-tema') === 'oscuro' ? 'claro' : 'oscuro';
        document.documentElement.setAttribute('data-tema', nuevo);
        try { localStorage.setItem('koaly-tema', nuevo); } catch (e) { /* modo privado: solo esta página */ }
        pintarBotonTema();
    });
    pintarBotonTema();

    // ===== Mostrar / ocultar contraseña (botón de ojo) =====
    function verContrasena(boton, visible) {
        $(boton.dataset.target).type = visible ? 'text' : 'password';
        boton.setAttribute('aria-pressed', visible ? 'true' : 'false');
        boton.title = tl(visible ? 'contrasena.ocultar' : 'contrasena.mostrar');
    }

    // ===== Idioma (botón de la esquina izquierda) =====
    // Cambia los textos sin recargar y guarda la cookie que leen login.php y los paneles
    function aplicarIdioma(codigo) {
        idioma = codigo;
        document.documentElement.lang = codigo;
        document.title = tl('pagina.titulo');
        document.querySelectorAll('[data-i18n]').forEach((el) => { el.textContent = tl(el.dataset.i18n); });
        ['placeholder', 'aria-label', 'title'].forEach((attr) => {
            document.querySelectorAll(`[data-i18n-${attr}]`).forEach((el) => {
                el.setAttribute(attr, tl(el.getAttribute(`data-i18n-${attr}`)));
            });
        });
        // Textos que dependen del estado
        pintarBotonTema();
        document.querySelectorAll('.toggle-password').forEach((b) => verContrasena(b, b.getAttribute('aria-pressed') === 'true'));
    }

    $('botonIdioma').addEventListener('click', () => {
        const nuevo = idioma === 'es' ? 'en' : 'es';
        document.cookie = `${COOKIE_IDIOMA}=${nuevo}; path=/; max-age=${365 * 86400}; samesite=lax`;
        aplicarIdioma(nuevo);
    });

    document.querySelectorAll('.toggle-password').forEach((boton) => {
        boton.addEventListener('click', () => verContrasena(boton, boton.getAttribute('aria-pressed') !== 'true'));
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

        if (!correoValido($('loginEmail'))) return mostrarMensaje(caja, 'error', tl('msg.correo_invalido'));
        if (!password) return mostrarMensaje(caja, 'error', tl('msg.escribe_contrasena'));

        ocupado(boton, true, tl('msg.ingresando'));
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
            return mostrarMensaje(caja, 'error', resultado.message || tl('msg.error_login'));
        }

        usuarioActual = resultado.user;
        sessionStorage.setItem('usuario', JSON.stringify(usuarioActual)); // lo usa auth.js

        if (resultado.debe_cambiar) {
            ocupado(boton, false);
            return mostrarVista($('changeView'));
        }

        mostrarMensaje(caja, 'success', tl('msg.entrando'));
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

        ocupado(boton, true, tl('msg.guardando'));
        const resultado = await api({ action: 'change_password', password });

        if (!resultado.success) {
            ocupado(boton, false);
            return mostrarMensaje(caja, 'error', resultado.message || tl('msg.error_actualizar'));
        }

        if (usuarioActual) {
            usuarioActual.debe_cambiar = 0;
            sessionStorage.setItem('usuario', JSON.stringify(usuarioActual));
        }
        mostrarMensaje(caja, 'success', tl('msg.actualizada'));
        window.location.href = resultado.destino || 'index.php';
    });

    // ===== Recuperación · paso 1: pedir el código =====
    async function enviarCodigo(caja, boton) {
        ocupado(boton, true, tl('msg.enviando'));
        const resultado = await api({ action: 'send_recover_code', email: correoRecuperacion });
        ocupado(boton, false);

        if (!resultado.success) {
            mostrarMensaje(caja, 'error', resultado.message || tl('msg.error_codigo'));
            return false;
        }
        return true;
    }

    function iniciarEsperaReenvio() {
        const boton = $('btnResend');
        let restantes = SEGUNDOS_REENVIO;
        const pintar = () => {
            boton.disabled = restantes > 0;
            boton.textContent = restantes > 0 ? tl('reenviar.espera', { n: restantes }) : tl('reenviar.boton');
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

        if (!correoValido($('recoverEmail'))) return mostrarMensaje(caja, 'error', tl('msg.correo_invalido'));

        correoRecuperacion = $('recoverEmail').value.trim();
        if (!(await enviarCodigo(caja, $('btnSendCode')))) return;

        mostrarVista($('recoverStep2'));
        $('recoverEmailShown').textContent = correoRecuperacion;
        mostrarMensaje($('recoverMsg2'), 'success', tl('msg.codigo_enviado'));
        iniciarEsperaReenvio();
    });

    $('btnResend').addEventListener('click', async () => {
        const caja = $('recoverMsg2');
        if (!(await enviarCodigo(caja, $('btnResend')))) return;
        mostrarMensaje(caja, 'success', tl('msg.codigo_nuevo'));
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

        if (!/^\d{6}$/.test(codigo)) return mostrarMensaje(caja, 'error', tl('msg.codigo_6'));
        const error = validarNueva(password, $('recoverConfirmPass').value, correoRecuperacion);
        if (error) return mostrarMensaje(caja, 'error', error);

        ocupado(boton, true, tl('msg.guardando'));
        const resultado = await api({ action: 'reset_password', email: correoRecuperacion, code: codigo, password });
        ocupado(boton, false);

        if (!resultado.success) return mostrarMensaje(caja, 'error', resultado.message || tl('msg.error_restablecer'));

        clearInterval(temporizadorReenvio);
        mostrarVista($('loginView'));
        $('loginEmail').value = correoRecuperacion;
        $('loginPassword').focus();
        mostrarMensaje($('loginMessage'), 'success', resultado.message || tl('msg.restablecida'));
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
        emailInput.addEventListener('focus', () => animarKoala('normal', tl('koala.nombre')));
        
        emailInput.addEventListener('input', () => {
            const email = emailInput.value.trim();
            if (email === '') animarKoala('normal', tl('koala.correo'));
            else if (email.includes('@') && email.includes('.')) animarKoala('feliz', tl('koala.bien'));
            else animarKoala('pensando', tl('koala.correo_completo'));
        });

        passInput.addEventListener('focus', () => {
            if (emailInput.value.includes('@')) animarKoala('secreto', tl('koala.secreto'));
            else animarKoala('pensando', tl('koala.primero_correo'));
        });

        passInput.addEventListener('input', () => {
            const pass = passInput.value;
            if (pass.length === 0) animarKoala('normal', tl('koala.contrasena_secreta'));
            else if (pass.length < 8) animarKoala('pensando', tl('koala.mas_segura'));
            else animarKoala('fiesta', tl('koala.listos'), true);
        }); 
    } 
}); 