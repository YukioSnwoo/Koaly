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
   Productos — "+ Agregar a inventario" (modal propio)
   Independiente de existencias: no navega a otra página ni usa
   parámetros en la URL, así que no puede reabrirse por su cuenta.
   ============================================================ */
function abrirModalAgregarInventario(btn) {
    const form = document.getElementById('formAgregarInventario');
    if (!form) return;
    form.reset();

    form.querySelector('[name="id_producto"]').value = btn.dataset.id;
    form.querySelector('[name="cantidad_inicial"]').value = '0';

    const precio = parseFloat(btn.dataset.precio);
    form.querySelector('[name="precio_venta"]').value = precio > 0 ? precio.toFixed(2) : '';

    const nombre = document.getElementById('agregarInvNombre');
    if (nombre) nombre.textContent = btn.dataset.nombre;

    const meta = document.getElementById('agregarInvMeta');
    if (meta) meta.textContent = btn.dataset.codigo + ' · ' + btn.dataset.categoria;

    const submit = form.querySelector('[type="submit"]');
    if (submit) submit.disabled = false;

    abrirModal('modalAgregarInventario');

    const cantidad = form.querySelector('[name="cantidad_inicial"]');
    if (cantidad) setTimeout(() => cantidad.select(), 50);
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
   Modales de cajas
   ============================================================ */
function abrirModalNuevaCaja() {
    const form = document.getElementById('formCaja');
    if (!form) return;
    form.reset();
    form.querySelector('[name="action"]').value = 'crear';
    form.querySelector('[name="id_caja"]').value = '';
    form.querySelector('[name="estado"]').value = 'Activa';
    form.querySelector('[name="id_cajero_asignado"]').value = '0';

    const titulo = document.getElementById('tituloModalCaja');
    if (titulo) titulo.textContent = 'Nueva caja';

    const aviso = document.getElementById('avisoReasignacion');
        if (aviso) aviso.classList.remove('visible');
            const selC = form.querySelector('[name="id_cajero_asignado"]');
        if (selC) {
            selC.removeEventListener('change', _actualizarAvisoReasignacion);
            selC.addEventListener('change', _actualizarAvisoReasignacion);
        }

    abrirModal('modalCaja');
}

function abrirModalEditarCaja(btn) {
    const form = document.getElementById('formCaja');
    if (!form) return;
    form.reset();
    form.querySelector('[name="action"]').value = 'editar';
    form.querySelector('[name="id_caja"]').value = btn.dataset.id;
    form.querySelector('[name="numero_caja"]').value = btn.dataset.numero;
    form.querySelector('[name="nombre"]').value = btn.dataset.nombre;
    form.querySelector('[name="estado"]').value = btn.dataset.estado;
    form.querySelector('[name="id_cajero_asignado"]').value = btn.dataset.cajero || '0';

    const titulo = document.getElementById('tituloModalCaja');
    if (titulo) titulo.textContent = 'Editar caja';

    // Si el select custom existe, refrescar el trigger
    const selectCustom = form.querySelector('[name="id_cajero_asignado"]');
    if (selectCustom) {
        selectCustom.dispatchEvent(new Event('change', { bubbles: true }));
            const selC = form.querySelector('[name="id_cajero_asignado"]');
            if (selC) {
                selC.removeEventListener('change', _actualizarAvisoReasignacion);
                selC.addEventListener('change', _actualizarAvisoReasignacion);
                // Disparar manualmente por si acaso
                setTimeout(_actualizarAvisoReasignacion, 50);
            }
    }

    abrirModal('modalCaja');
}


/* ============================================================
   Cajas — detectar reasignación de cajero
   ============================================================ */
function _actualizarAvisoReasignacion() {
    const form = document.getElementById('formCaja');
    if (!form) return;

    const select = form.querySelector('[name="id_cajero_asignado"]');
    const aviso  = document.getElementById('avisoReasignacion');
    if (!select || !aviso) return;

    const opt = select.options[select.selectedIndex];
    const cajaActual   = opt.dataset.cajaActual || '';
    const cajaActualId = parseInt(opt.dataset.cajaId || '0', 10);
    const editingId    = parseInt(form.querySelector('[name="id_caja"]').value || '0', 10);

    if (cajaActual && cajaActualId !== editingId) {
        aviso.innerHTML = `<strong>Este cajero ya está asignado a la caja ${cajaActual}.</strong> Si guardas, se moverá a esta caja y se desasignará de la anterior.`;
        aviso.classList.add('visible');

        form.dataset.confirm = `Este cajero ya está asignado a la caja ${cajaActual}. Si continúas, se moverá a esta caja y se desasignará de la anterior. ¿Deseas continuar?`;
        form.dataset.confirmTitulo = 'Reasignar cajero';
        form.dataset.confirmAceptar = 'Reasignar';
    } else {
        aviso.classList.remove('visible');
        delete form.dataset.confirm;
        delete form.dataset.confirmTitulo;
        delete form.dataset.confirmAceptar;
        delete form.dataset.confirmado;
    }
}


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
        const filtroInv = document.getElementById('filtroInventario');
            if (buscador || filtroInv) {
                const aplicarFiltroProductos = () => {
                    const q = buscador ? buscador.value.trim().toLowerCase() : '';
                    const tipo = filtroInv ? filtroInv.value : 'todos';

                    document.querySelectorAll('#tablaProductos tbody tr[data-nombre]').forEach(row => {
                        const coincideNombre = row.dataset.nombre.includes(q);
                        const enInv = row.dataset.enInventario === '1';
                        let coincideTipo = true;
                        if (tipo === 'con') coincideTipo = enInv;
                        else if (tipo === 'sin') coincideTipo = !enInv;

                        row.style.display = (coincideNombre && coincideTipo) ? '' : 'none';
                    });
                };
                if (buscador) buscador.addEventListener('input', aplicarFiltroProductos);
                if (filtroInv) filtroInv.addEventListener('change', aplicarFiltroProductos);
            }
    initFiltrosExistencias();
    initCustomSelects();
    initBuscadorProducto();
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
    resetBuscadorProducto();

    const btn = form.querySelector('[type="submit"]');
    if (btn) btn.disabled = false;

    abrirModal('modalAgregarProducto');

    // Foco directo en el buscador para empezar a escribir
    const buscador = document.getElementById('buscarProductoAgregar');
    if (buscador) setTimeout(() => buscador.focus(), 50);
}

/* ============================================================
   Buscador de producto (existencias → "Agregar producto al inventario")
   Reemplaza el <select> por un campo de búsqueda con lista filtrable.
   Es independiente del select custom, así no se pisan entre sí.
   El valor real viaja en <input type="hidden" name="id_producto">.
   ============================================================ */
function _normalizarTexto(txt) {
    return (txt || '')
        .toString()
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')   // quita acentos: "café" == "cafe"
        .trim();
}

let _resetBuscadorProducto = null;

function resetBuscadorProducto() {
    if (_resetBuscadorProducto) _resetBuscadorProducto();
}

function initBuscadorProducto() {
    const picker = document.getElementById('pickerProducto');
    if (!picker || picker.dataset.init === '1') return;
    picker.dataset.init = '1';

    const form     = picker.closest('form');
    const inputId  = picker.querySelector('input[name="id_producto"]');
    const envoltura = picker.querySelector('.picker-input-wrap');
    const buscador = picker.querySelector('.picker-input');
    const btnLimpiar = picker.querySelector('.picker-clear');
    const lista    = picker.querySelector('.picker-lista');
    const vacio    = picker.querySelector('.picker-vacio');
    const items    = Array.from(lista.querySelectorAll('.picker-item'));
    const precio   = form ? form.querySelector('[name="precio_venta"]') : null;
    const MSG_ELEGIR = 'Selecciona un producto de la lista.';

    items.forEach(li => { li._buscar = _normalizarTexto(li.dataset.buscar); });

    // La lista flota sobre el <body> (position:fixed), igual que el panel del
    // select custom: así el modal, que tiene overflow, no la recorta.
    document.body.appendChild(lista);

    let seleccionado = null;   // <li> elegido
    let indiceActivo = -1;     // posición dentro de los visibles

    const visibles = () => items.filter(li => !li.hidden);
    const abierta  = () => lista.classList.contains('is-open');

    const marcarActivo = (i, desplazar = true) => {
        const vis = visibles();
        items.forEach(li => li.classList.remove('is-focused'));
        indiceActivo = (i >= 0 && i < vis.length) ? i : -1;
        if (indiceActivo >= 0) {
            vis[indiceActivo].classList.add('is-focused');
            if (desplazar) vis[indiceActivo].scrollIntoView({ block: 'nearest' });
        }
    };

    const filtrar = (mostrarTodos = false) => {
        const palabras = mostrarTodos
            ? []
            : _normalizarTexto(buscador.value).split(/\s+/).filter(Boolean);

        let hay = 0;
        items.forEach(li => {
            const coincide = palabras.every(p => li._buscar.includes(p));
            li.hidden = !coincide;
            if (coincide) hay++;
        });
        vacio.hidden = hay > 0;
        marcarActivo(hay > 0 ? 0 : -1, false);
        lista.scrollTop = 0;
    };

    const posicionarLista = () => {
        const r = envoltura.getBoundingClientRect();
        const alto = lista.offsetHeight;
        const espacioAbajo = window.innerHeight - r.bottom;
        const abrirArriba = espacioAbajo < alto + 12 && r.top > alto + 12;

        lista.style.width = r.width + 'px';
        lista.style.left  = r.left + 'px';
        lista.style.top   = abrirArriba
            ? (r.top - alto - 6) + 'px'
            : (r.bottom + 6) + 'px';
    };

    const abrirLista = () => {
        lista.classList.add('is-open');
        buscador.setAttribute('aria-expanded', 'true');
        posicionarLista();
    };

    const cerrarLista = () => {
        lista.classList.remove('is-open');
        buscador.setAttribute('aria-expanded', 'false');
    };

    const limpiarSeleccion = () => {
        seleccionado = null;
        inputId.value = '';
        items.forEach(li => li.classList.remove('is-selected'));
        btnLimpiar.hidden = true;
        buscador.setCustomValidity(MSG_ELEGIR);
    };

    const seleccionar = (li) => {
        seleccionado = li;
        inputId.value = li.dataset.id;
        buscador.value = li.dataset.nombre;
        buscador.setCustomValidity('');
        items.forEach(x => x.classList.toggle('is-selected', x === li));
        btnLimpiar.hidden = false;

        // Autocompletar precio. Si el usuario ya lo escribió a mano, no se toca.
        if (precio && (precio.value === '' || precio.dataset.auto === '1')) {
            const p = parseFloat(li.dataset.precio);
            if (p > 0) {
                precio.value = p.toFixed(2);
                precio.dataset.auto = '1';
            } else {
                precio.value = '';
                precio.dataset.auto = '';
            }
        }

        cerrarLista();

        // Siguiente paso natural: la cantidad
        const cantidad = form ? form.querySelector('[name="cantidad_inicial"]') : null;
        if (cantidad) { cantidad.focus(); cantidad.select(); }
    };

    if (precio) {
        precio.addEventListener('input', () => { precio.dataset.auto = '0'; });
    }

    /* --- Eventos --- */
    buscador.addEventListener('focus', () => {
        filtrar(seleccionado !== null);   // con algo elegido, muestra toda la lista
        abrirLista();
        if (seleccionado) buscador.select();
    });

    buscador.addEventListener('input', () => {
        if (seleccionado && buscador.value !== seleccionado.dataset.nombre) {
            limpiarSeleccion();
        }
        filtrar();
        abrirLista();
    });

    buscador.addEventListener('keydown', (e) => {
        const vis = visibles();

        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            if (!abierta()) {
                filtrar(seleccionado !== null);
                abrirLista();
                return;
            }
            if (!vis.length) return;
            const delta = e.key === 'ArrowDown' ? 1 : -1;
            marcarActivo((indiceActivo + delta + vis.length) % vis.length);

        } else if (e.key === 'Enter') {
            e.preventDefault();   // Enter en el buscador nunca envía el formulario
            if (abierta() && vis[indiceActivo]) seleccionar(vis[indiceActivo]);

        } else if (e.key === 'Escape') {
            if (abierta()) {
                e.stopPropagation();
                cerrarLista();
            }
        }
    });

    // mousedown + preventDefault: evita que el input pierda el foco
    // (y cierre la lista) antes de que se registre el click en la opción.
    lista.addEventListener('mousedown', (e) => e.preventDefault());

    lista.addEventListener('click', (e) => {
        const li = e.target.closest('.picker-item');
        if (li && !li.hidden) seleccionar(li);
    });

    lista.addEventListener('mouseover', (e) => {
        const li = e.target.closest('.picker-item');
        if (!li || li.hidden) return;
        marcarActivo(visibles().indexOf(li), false);
    });

    btnLimpiar.addEventListener('click', () => {
        limpiarSeleccion();
        buscador.value = '';
        buscador.focus();
        filtrar();
        abrirLista();
    });

    const reubicar = () => { if (abierta()) posicionarLista(); };
    window.addEventListener('resize', reubicar);
    window.addEventListener('scroll', reubicar, true);

    picker.addEventListener('focusout', (e) => {
        if (picker.contains(e.relatedTarget) || lista.contains(e.relatedTarget)) return;
        cerrarLista();
        if (seleccionado) buscador.value = seleccionado.dataset.nombre;
    });

    _resetBuscadorProducto = () => {
        limpiarSeleccion();
        buscador.value = '';
        if (precio) { precio.value = ''; precio.dataset.auto = ''; }
        cerrarLista();
        items.forEach(li => { li.hidden = false; li.classList.remove('is-focused'); });
        vacio.hidden = true;
        indiceActivo = -1;
    };

    // Estado inicial: nada elegido todavía
    limpiarSeleccion();
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
