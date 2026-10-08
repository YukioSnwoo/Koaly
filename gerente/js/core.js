/* ============================================================
 * core.js — Funciones que TODA página del gerente necesita.
 * Depende de: nada (es la capa base).
 * ============================================================ */


/* ============================================================
 * Modal helpers — abrir, cerrar, click en overlay
 * ============================================================ */
function abrirModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.add('open');
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
    <h2 id="confirmTitulo">Confirmar acción</h2>
    <p class="confirm-mensaje" id="confirmMensaje"></p>
    <div class="modal-actions">
    <button type="button" class="btn btn-ghost" id="confirmCancelar">Cancelar</button>
    <button type="button" class="btn btn-primary" id="confirmAceptar">Aceptar</button>
    </div>
    </div>
    </div>
    `;
    document.body.insertAdjacentHTML('beforeend', html);

    const overlay = document.getElementById('modalConfirm');
    const btnCancelar = document.getElementById('confirmCancelar');
    const btnAceptar = document.getElementById('confirmAceptar');

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

        titulo.textContent = opciones.titulo || 'Confirmar acción';
        mensajeEl.textContent = mensaje;
        btnAceptar.textContent = opciones.textoAceptar || 'Aceptar';

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
        'Se cerrará tu sesión actual. ¿Deseas continuar?',
        {
            titulo: 'Cerrar sesión',
            textoAceptar: 'Cerrar sesión',
            peligroso: true
        }
    );
    if (ok && typeof logout === 'function') logout();
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
        titulo: form.dataset.confirmTitulo || 'Confirmar acción',
        textoAceptar: form.dataset.confirmAceptar || 'Aceptar',
        peligroso: form.dataset.confirmPeligroso === '1'
    });

    if (ok) {
        form.dataset.confirmado = '1';
        form.submit();
    }
});
