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
   Modales de cajeros
   ============================================================ */
function abrirModalNuevoCajero() {
    const form = document.getElementById('formCajero');
    if (!form) return;
    form.reset();
    form.querySelector('[name="action"]').value = 'crear';
    form.querySelector('[name="id_usuario"]').value = '';
    form.querySelector('[name="fecha_contratacion"]').value = new Date().toISOString().slice(0, 10);

    const titulo = document.getElementById('tituloModalCajero');
    if (titulo) titulo.textContent = 'Nuevo cajero';

    const grupoPassword = document.getElementById('grupoPassword');
    if (grupoPassword) grupoPassword.style.display = '';
    const grupoFecha = document.getElementById('grupoFechaContratacion');
    if (grupoFecha) grupoFecha.style.display = '';

    const inputPassword = form.querySelector('[name="password"]');
    if (inputPassword) inputPassword.required = true;

    abrirModal('modalCajero');
}

function abrirModalEditarCajero(btn) {
    const form = document.getElementById('formCajero');
    if (!form) return;
    form.reset();
    form.querySelector('[name="action"]').value = 'editar';
    form.querySelector('[name="id_usuario"]').value = btn.dataset.id;
    form.querySelector('[name="nombre"]').value = btn.dataset.nombre;
    form.querySelector('[name="email"]').value = btn.dataset.email;
    form.querySelector('[name="estado"]').value = btn.dataset.estado;

    const titulo = document.getElementById('tituloModalCajero');
    if (titulo) titulo.textContent = 'Editar cajero';

    const grupoPassword = document.getElementById('grupoPassword');
    if (grupoPassword) grupoPassword.style.display = 'none';
    const grupoFecha = document.getElementById('grupoFechaContratacion');
    if (grupoFecha) grupoFecha.style.display = 'none';

    const inputPassword = form.querySelector('[name="password"]');
    if (inputPassword) inputPassword.required = false;

    abrirModal('modalCajero');
}


/* ============================================================
   Select custom — reemplaza <select> nativo por componente
   con panel de esquinas redondeadas, hover y animación.
   El select original queda oculto para que el form lo envíe.
   ============================================================ */
const _selectCustomActivos = new Set();

function _cerrarTodosLosSelects(excepto) {
    _selectCustomActivos.forEach(inst => {
        if (inst.wrapper !== excepto) inst.close();
    });
}

function initCustomSelect(select) {
    if (!select || select.dataset.customInit === '1') return;
    select.dataset.customInit = '1';

    const wrapper = document.createElement('div');
    wrapper.className = 'select-custom';
    select.parentNode.insertBefore(wrapper, select);
    wrapper.appendChild(select);

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'select-custom-trigger';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    wrapper.appendChild(trigger);

    const panel = document.createElement('div');
    panel.className = 'select-custom-panel';
    panel.setAttribute('role', 'listbox');
    document.body.appendChild(panel);

    const opciones = () => Array.from(select.options);

    const pintarTrigger = () => {
        const opt = select.options[select.selectedIndex];
        const texto = opt ? opt.text : 'Selecciona…';
        trigger.innerHTML = `<span>${texto}</span>
            <svg class="select-custom-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>`;
        trigger.classList.toggle('is-placeholder', !opt || opt.value === '' || opt.disabled);
    };

    const pintarPanel = () => {
        panel.innerHTML = '';
        opciones().forEach((opt, i) => {
            const item = document.createElement('div');
            item.className = 'select-custom-option';
            item.setAttribute('role', 'option');
            item.dataset.index = i;
            if (i === select.selectedIndex) {
                item.classList.add('is-selected');
                item.setAttribute('aria-selected', 'true');
            }
            if (opt.disabled) {
                item.classList.add('is-disabled');
            }
            item.innerHTML = `<span>${opt.text}</span>`;

            if (!opt.disabled) {
                item.addEventListener('click', () => {
                    select.selectedIndex = i;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    pintarTrigger();
                    pintarPanel();
                    cerrar();
                });
                item.addEventListener('mouseenter', () => {
                    panel.querySelectorAll('.is-focused').forEach(el => el.classList.remove('is-focused'));
                    item.classList.add('is-focused');
                });
            }
            panel.appendChild(item);
        });
    };

    const posicionarPanel = () => {
        const r = trigger.getBoundingClientRect();
        const alturaPanel = Math.min(panel.scrollHeight || 280, 280);
        const espacioAbajo = window.innerHeight - r.bottom;
        const abrirArriba = espacioAbajo < alturaPanel + 12 && r.top > alturaPanel + 12;

        panel.style.width = r.width + 'px';
        panel.style.left  = r.left + 'px';
        panel.style.top   = abrirArriba
            ? (r.top - alturaPanel - 6) + 'px'
            : (r.bottom + 6) + 'px';
    };

    const abrir = () => {
        _cerrarTodosLosSelects(wrapper);
        pintarPanel();
        panel.classList.add('is-open');
        wrapper.classList.add('is-open');
        trigger.setAttribute('aria-expanded', 'true');
        // Necesitamos medir después de mostrar para posicionar bien
        requestAnimationFrame(posicionarPanel);
    };

    const cerrar = () => {
        panel.classList.remove('is-open');
        wrapper.classList.remove('is-open');
        trigger.setAttribute('aria-expanded', 'false');
    };

    trigger.addEventListener('click', (e) => {
        e.stopPropagation();
        if (wrapper.classList.contains('is-open')) cerrar();
        else abrir();
    });

    trigger.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') cerrar();
        if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            if (!wrapper.classList.contains('is-open')) abrir();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && wrapper.classList.contains('is-open')) cerrar();
    });

    // Si el select cambia externamente, refrescar
    select.addEventListener('change', () => {
        pintarTrigger();
        pintarPanel();
    });

    // Reposicionar si el layout cambia mientras está abierto
    window.addEventListener('resize', () => {
        if (wrapper.classList.contains('is-open')) posicionarPanel();
    });

    pintarTrigger();
    _selectCustomActivos.add({ wrapper, close: cerrar });
}

function initCustomSelects() {
    document.querySelectorAll('.toolbar select, .modal-box select').forEach(initCustomSelect);
}

// Cerrar al hacer click fuera
document.addEventListener('click', (e) => {
    if (!e.target.closest('.select-custom') && !e.target.closest('.select-custom-panel')) {
        _cerrarTodosLosSelects(null);
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
    initFiltrosExistencias();
    initCustomSelects();
});

/* ============================================================
   Modales de existencias
   ============================================================ */
function abrirModalAjustarStock(btn) {
    const form = document.getElementById('formAjustarStock');
    if (!form) return;
    form.reset();

    form.querySelector('[name="id_producto"]').value    = btn.dataset.id;
    form.querySelector('[name="cantidad_actual"]').value = btn.dataset.stock;
    form.querySelector('[name="cantidad_nueva"]').value  = btn.dataset.stock;

    const nombre = document.getElementById('ajustarProductoNombre');
    if (nombre) nombre.textContent = btn.dataset.nombre;

    const actual = document.getElementById('ajustarStockActual');
    if (actual) actual.textContent = btn.dataset.stock + ' unidades';

    const precio = document.getElementById('ajustarStockPrecio');
    if (precio) precio.textContent = '$' + parseFloat(btn.dataset.precio).toFixed(2);

    abrirModal('modalAjustarStock');
}

function abrirModalAgregarProducto() {
    const form = document.getElementById('formAgregarProducto');
    if (!form) return;
    form.reset();
    abrirModal('modalAgregarProducto');
}

/* ============================================================
   Existencias — filtros cliente
   ============================================================ */
function initFiltrosExistencias() {
    const buscador = document.getElementById('buscarExistencia');
    const filtro   = document.getElementById('filtroStock');

    if (!buscador && !filtro) return;

    const aplicar = () => {
        const q    = buscador ? buscador.value.trim().toLowerCase() : '';
        const tipo = filtro   ? filtro.value : 'todos';

        document.querySelectorAll('#tablaExistencias tbody tr[data-nombre]').forEach(row => {
            const coincideNombre = row.dataset.nombre.includes(q);
            const stock = parseInt(row.dataset.stock, 10);

            let coincideTipo = true;
            if (tipo === 'agotado') coincideTipo = stock === 0;
            else if (tipo === 'bajo') coincideTipo = stock > 0 && stock < 10;
            else if (tipo === 'normal') coincideTipo = stock >= 10;

            row.style.display = (coincideNombre && coincideTipo) ? '' : 'none';
        });
    };

    if (buscador) buscador.addEventListener('input', aplicar);
    if (filtro)   filtro.addEventListener('change', aplicar);
}