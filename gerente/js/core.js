const CLAVE_TEMA = 'koaly-tema';

function temaActual() {
    const elegido = document.documentElement.getAttribute('data-tema');
    if (elegido) return elegido;
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'oscuro' : 'claro';
}

function actualizarBotonTema() {
    const boton = document.getElementById('botonTema');
    if (!boton) return;
    const etiqueta = temaActual() === 'oscuro' ? tj('tema_a_claro') : tj('tema_a_oscuro');
    boton.setAttribute('aria-label', etiqueta);
    boton.title = etiqueta;
}


function tj(clave, vars = {}) {
    let texto = (window.I18N && window.I18N[clave]) || clave;
    for (const [k, v] of Object.entries(vars)) texto = texto.split('{' + k + '}').join(String(v));
    return texto;
}


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

            resolve(window.confirm(mensaje));
            return;
        }

        const titulo = document.getElementById('confirmTitulo');
        const mensajeEl = document.getElementById('confirmMensaje');
        const btnAceptar = document.getElementById('confirmAceptar');

        titulo.textContent = opciones.titulo || tj('confirmar_titulo');
        mensajeEl.textContent = mensaje;
        btnAceptar.textContent = opciones.textoAceptar || tj('aceptar');

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

    try { sessionStorage.removeItem('usuario'); } catch (e) {}
    window.location.href = '../logout.php';
}


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



document.addEventListener('click', (e) => {
    if (!e.target.closest('#botonTema')) return;
    const nuevo = temaActual() === 'oscuro' ? 'claro' : 'oscuro';
    document.documentElement.setAttribute('data-tema', nuevo);
    try { localStorage.setItem(CLAVE_TEMA, nuevo); } catch (err) {  }
    actualizarBotonTema();
}); 

document.addEventListener('DOMContentLoaded', () => {
    const btnToggleSidebar = document.getElementById('btnToggleSidebar');
    const layout = document.querySelector('.layout');

    if (btnToggleSidebar && layout) {
        btnToggleSidebar.addEventListener('click', () => {
            layout.classList.toggle('sidebar-collapsed');
        });
    }

    actualizarBotonTema();
});

window.matchMedia('(prefers-color-scheme: dark)').addEventListener?.('change', actualizarBotonTema);