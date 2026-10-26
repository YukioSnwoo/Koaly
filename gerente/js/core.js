/* ============================================================
 * core.js — Funciones que TODA página del gerente necesita.
 * Depende de: nada (es la capa base).
 * ============================================================ */


/* ============================================================
 * Traducciones: footer.php deja en window.I18N las claves "js.*"
 * de gerente/lang/{idioma}.php. tj('clave', {n: 3}) reemplaza {n}.
 * ============================================================ */
function tj(clave, vars = {}) {
    let texto = (window.I18N && window.I18N[clave]) || clave;
    for (const [k, v] of Object.entries(vars)) texto = texto.split('{' + k + '}').join(String(v));
    return texto;
}


/* ============================================================
 * Modal helpers — abrir, cerrar, click en overlay
 * ============================================================ */
function abrirModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    // Los formularios se llenan con select.value = ..., que no dispara "change":
    // sin esto el select personalizado seguía mostrando el valor anterior
    // (p. ej. "Activo" en un cajero que está de vacaciones).
    modal.querySelectorAll('select').forEach(s => s.dispatchEvent(new Event('select-custom:sincronizar')));
    modal.classList.add('open');
}

function cerrarModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.remove('open');
}

document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-overlay') && e.target.id !== 'modalConfirm') {
        e.target.classList.remove('open');
    }
});


/* ============================================================
 * Cursor personalizado
 * Solo se activa en dispositivos con puntero fino (mouse).
 * Si JS falla, el cursor nativo queda visible porque la clase
 * .cursor-custom no se añade al body.
 * ============================================================ */
function initCustomCursor() {
    if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;

    const dot = document.createElement('div');
    dot.className = 'cursor-dot';
    dot.setAttribute('aria-hidden', 'true');
    document.body.appendChild(dot);
    document.body.classList.add('cursor-custom');

    let x = window.innerWidth / 2;
    let y = window.innerHeight / 2;
    let raf = null;

    const render = () => {
        dot.style.transform = `translate3d(${x}px, ${y}px, 0) translate(-50%, -50%)`;
        raf = null;
    };

    document.addEventListener('mousemove', (e) => {
        x = e.clientX;
        y = e.clientY;
        if (!raf) raf = requestAnimationFrame(render);
    });

        const interactivo = 'a, button, input, select, textarea, label, [role="button"]';
        document.addEventListener('mouseover', (e) => {
            if (e.target.closest(interactivo)) dot.classList.add('is-hover');
        });
            document.addEventListener('mouseout', (e) => {
                if (e.target.closest(interactivo)) dot.classList.remove('is-hover');
            });

                render();
}


/* ============================================================
 * Modal de confirmación (reemplaza window.confirm)
 * Se crea dinámicamente al cargar la página.
 * ============================================================ */
function crearModalConfirmacion() {
    if (document.getElementById('modalConfirm')) return;

    const html = `
    <div class="modal-overlay" id="modalConfirm" role="dialog" aria-modal="true" aria-labelledby="confirmTitulo">
    <div class="modal-box modal-box--sm">
    <h2 id="confirmTitulo"></h2>
    <p class="confirm-mensaje" id="confirmMensaje"></p>
    <div class="modal-actions">
    <button type="button" class="btn btn-ghost" id="confirmCancelar"></button>
    <button type="button" class="btn btn-primary" id="confirmAceptar"></button>
    </div>
    </div>
    </div>
    `;
    document.body.insertAdjacentHTML('beforeend', html);

    const overlay = document.getElementById('modalConfirm');
    const btnCancelar = document.getElementById('confirmCancelar');
    const btnAceptar = document.getElementById('confirmAceptar');
    btnCancelar.textContent = tj('cancelar');

    overlay.addEventListener('click', (e) => {
        if (e.target === overlay && overlay._rechazar) overlay._rechazar();
    });

        btnCancelar.addEventListener('click', () => {
            if (overlay._rechazar) overlay._rechazar();
        });

            btnAceptar.addEventListener('click', () => {
                if (overlay._resolver) overlay._resolver();
            });

                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && overlay.classList.contains('open')) {
                        if (overlay._rechazar) overlay._rechazar();
                    }
                });
}

function pedirConfirmacion(mensaje, opciones = {}) {
    return new Promise((resolve) => {
        const overlay = document.getElementById('modalConfirm');
        if (!overlay) {
            // Fallback si por alguna razón no se creó el modal
            resolve(window.confirm(mensaje));
            return;
        }

        const titulo = document.getElementById('confirmTitulo');
        const mensajeEl = document.getElementById('confirmMensaje');
        const btnAceptar = document.getElementById('confirmAceptar');

        titulo.textContent = opciones.titulo || tj('confirmar_titulo');
        mensajeEl.textContent = mensaje;
        btnAceptar.textContent = opciones.textoAceptar || tj('aceptar');

        // Estilo peligroso: botón rojo sólido
        btnAceptar.classList.remove('btn-primary', 'btn-danger-solid');
        btnAceptar.classList.add(opciones.peligroso ? 'btn-danger-solid' : 'btn-primary');

        const cerrar = () => {
            overlay.classList.remove('open');
            overlay._resolver = null;
            overlay._rechazar = null;
        };

        overlay._resolver = () => { cerrar(); resolve(true); };
        overlay._rechazar = () => { cerrar(); resolve(false); };

        overlay.classList.add('open');
    });
}


/* ============================================================
 * Logout con confirmación
 * Uso en HTML: onclick="logoutConfirmado()"
 * ============================================================ */
async function logoutConfirmado() {
    const ok = await pedirConfirmacion(
        tj('cerrar_sesion_mensaje'),
        {
            titulo: tj('cerrar_sesion'),
            textoAceptar: tj('cerrar_sesion'),
            peligroso: true
        }
    );
    if (!ok) return;
    window.location.href = '../logout.php';
}


/* ============================================================
 * Interceptor de formularios con data-confirm
 * Cualquier <form data-confirm="..."> pide confirmación antes
 * de enviarse.
 * ============================================================ */
document.addEventListener('submit', async (e) => {
    const form = e.target;
    if (!form.matches('form[data-confirm]')) return;
    if (form.dataset.confirmado === '1') return;

    e.preventDefault();

    const ok = await pedirConfirmacion(form.dataset.confirm, {
        titulo: form.dataset.confirmTitulo || tj('confirmar_titulo'),
        textoAceptar: form.dataset.confirmAceptar || tj('aceptar'),
        peligroso: form.dataset.confirmPeligroso === '1'
    });

    if (ok) {
        form.dataset.confirmado = '1';
        form.submit();
    }
});


/* ============================================================
 * Avisos de campos sin llenar o con un dato inválido.
 * En lugar de la burbuja gris del navegador, el aviso sale en
 * morado debajo del campo (.campo-aviso). El navegador sigue
 * validando (required, min, pattern, setCustomValidity…); aquí
 * solo cambia cómo se muestra. Fuera de un .form-group se deja
 * la burbuja normal.
 * ============================================================ */
let avisoCampoEnfocado = false;

function marcarCampoInvalido(campo, grupo) {
    grupo.classList.add('campo-invalido');
    let aviso = grupo.querySelector('.campo-aviso');
    if (!aviso) {
        aviso = document.createElement('p');
        aviso.className = 'campo-aviso';
        aviso.setAttribute('role', 'alert');
        grupo.appendChild(aviso);
    }
    aviso.textContent = campo.validity.valueMissing ? tj('campo_obligatorio') : campo.validationMessage;
}

// Quita el aviso de los campos que ya quedaron bien (un campo puede arreglar a otro: teléfono o correo)
function limpiarAvisosCampos(form, todos = false) {
    form.querySelectorAll('.form-group.campo-invalido').forEach(grupo => {
        const campos = grupo.querySelectorAll('input, select, textarea');
        if (todos || [...campos].every(c => !c.willValidate || c.validity.valid)) {
            grupo.classList.remove('campo-invalido');
            grupo.querySelector('.campo-aviso')?.remove();
        }
    });
}

// "invalid" no burbujea: se escucha en captura
document.addEventListener('invalid', (e) => {
    const campo = e.target;
    const grupo = campo.closest('.form-group');
    if (!grupo) return;
    e.preventDefault();
    marcarCampoInvalido(campo, grupo);

    // Al enviar llegan juntos los "invalid" de todos los campos: el foco va solo al primero
    if (avisoCampoEnfocado) return;
    avisoCampoEnfocado = true;
    setTimeout(() => { avisoCampoEnfocado = false; });
    (campo.closest('.select-custom')?.querySelector('.select-custom-trigger') || campo).focus();
}, true);

['input', 'change'].forEach(tipo => document.addEventListener(tipo, (e) => {
    if (e.target.form) limpiarAvisosCampos(e.target.form);
}));
document.addEventListener('reset', (e) => limpiarAvisosCampos(e.target, true));
