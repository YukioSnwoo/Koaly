/* ============================================================
   Modal helpers (usados por productos.php)
   ============================================================ */
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

function abrirModalNuevo() {
    const form = document.getElementById('formProducto');
    if (!form) return;
    form.reset();
    form.querySelector('[name="action"]').value = 'crear';
    form.querySelector('[name="id_producto"]').value = '';
    const titulo = document.getElementById('tituloModalProducto');
    if (titulo) titulo.textContent = 'Nuevo producto';
    const preview = document.getElementById('previewImagen');
    if (preview) preview.innerHTML = '';
    abrirModal('modalProducto');
}

function abrirModalEditar(btn) {
    const form = document.getElementById('formProducto');
    if (!form) return;
    form.reset();
    form.querySelector('[name="action"]').value = 'editar';
    form.querySelector('[name="id_producto"]').value = btn.dataset.id;
    form.querySelector('[name="codigo"]').value = btn.dataset.codigo;
    form.querySelector('[name="nombre"]').value = btn.dataset.nombre;
    form.querySelector('[name="descripcion"]').value = btn.dataset.descripcion;
    form.querySelector('[name="precio"]').value = btn.dataset.precio;
    form.querySelector('[name="id_categoria"]').value = btn.dataset.categoria;
    form.querySelector('[name="cantidad_inventario"]').value = btn.dataset.cantidad;

    const preview = document.getElementById('previewImagen');
    if (preview) {
        preview.innerHTML = btn.dataset.imagen
            ? `<img src="../${btn.dataset.imagen}" alt=""><span>Imagen actual (sube una nueva para reemplazarla)</span>`
            : '<span>Sin imagen actual</span>';
    }

    const titulo = document.getElementById('tituloModalProducto');
    if (titulo) titulo.textContent = 'Editar producto';
    abrirModal('modalProducto');
}

/* ============================================================
   Cursor personalizado
   ============================================================ */
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
   Modal de confirmación (reemplaza window.confirm)
   Se crea dinámicamente, no requiere HTML en cada página.
   ============================================================ */
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
   Logout con confirmación (reemplaza confirm() nativo)
   Uso en HTML: onclick="logoutConfirmado()"
   ============================================================ */
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
   Interceptar formularios con data-confirm
   ============================================================ */
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

/* ============================================================
   Arranque
   ============================================================ */
document.addEventListener('DOMContentLoaded', () => {
    initCustomCursor();
    crearModalConfirmacion();

    const inputImagen = document.getElementById('inputImagen');
    if (inputImagen) {
        inputImagen.addEventListener('change', () => {
            const preview = document.getElementById('previewImagen');
            const archivo = inputImagen.files[0];
            if (!archivo || !preview) return;
            const url = URL.createObjectURL(archivo);
            preview.innerHTML = `<img src="${url}" alt=""><span>${archivo.name}</span>`;
        });
    }

    const buscador = document.getElementById('buscarProducto');
    if (buscador) {
        buscador.addEventListener('input', () => {
            const q = buscador.value.trim().toLowerCase();
            document.querySelectorAll('#tablaProductos tbody tr[data-nombre]').forEach(row => {
                row.style.display = row.dataset.nombre.includes(q) ? '' : 'none';
            });
        });
    }
});