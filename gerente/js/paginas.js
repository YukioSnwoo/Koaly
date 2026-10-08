/* ============================================================
 * paginas.js — Código específico de cada página del gerente.
 * Depende de: core.js (abrirModal) y componentes.js (initCustomSelects).
 * Usado por: productos, existencias, cajeros, cajas.
 * ============================================================ */


/* ============================================================
 * ██ PRODUCTOS — Modales y filtros
 * ============================================================ */

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
 * ██ CAJEROS — Modales
 * ============================================================ */

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
 * ██ CAJAS — Modales y aviso de reasignación
 * ============================================================ */

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
            setTimeout(_actualizarAvisoReasignacion, 50);
        }
    }

    abrirModal('modalCaja');
}

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
 * ██ EXISTENCIAS — Modales y filtros
 * ============================================================ */

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

    const buscador = document.getElementById('buscarProductoAgregar');
    if (buscador) setTimeout(() => buscador.focus(), 50);
}

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


/* ============================================================
 * ██ EXISTENCIAS — Buscador de producto (picker)
 * Reemplaza el <select> por un campo de texto con lista filtrable.
 * Independiente del select custom.
 * ============================================================ */

function _normalizarTexto(txt) {
    return (txt || '')
    .toString()
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
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

    // La lista flota sobre el <body> para escapar del overflow del modal
    document.body.appendChild(lista);

    let seleccionado = null;
    let indiceActivo = -1;

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

        const cantidad = form ? form.querySelector('[name="cantidad_inicial"]') : null;
        if (cantidad) { cantidad.focus(); cantidad.select(); }
    };

    if (precio) {
        precio.addEventListener('input', () => { precio.dataset.auto = '0'; });
    }

    buscador.addEventListener('focus', () => {
        filtrar(seleccionado !== null);
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
                e.preventDefault();
                if (abierta() && vis[indiceActivo]) seleccionar(vis[indiceActivo]);

            } else if (e.key === 'Escape') {
                if (abierta()) {
                    e.stopPropagation();
                    cerrarLista();
                }
            }
        });

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

                limpiarSeleccion();
}


/* ============================================================
 * ██ AUTOCOMPLETAR PRECIO
 * Cuando se selecciona un producto en el modal "Agregar producto
 * al inventario" de existencias, autocompletar el precio de venta
 * si el usuario no lo ha escrito a mano.
 * ============================================================ */
document.addEventListener('change', (e) => {
    if (e.target.name !== 'id_producto') return;
    const form = e.target.closest('#formAgregarProducto');
    if (!form) return;

    const opt = e.target.options[e.target.selectedIndex];
    const precioInput = form.querySelector('[name="precio_venta"]');
    if (opt && opt.dataset.precio && precioInput && !precioInput.value) {
        const p = parseFloat(opt.dataset.precio);
        if (p > 0) precioInput.value = p.toFixed(2);
    }
});


/* ============================================================
 * ██ ARRANQUE
 * Se ejecuta en TODAS las páginas. Cada init detecta si su DOM
 * existe antes de hacer algo.
 * ============================================================ */
document.addEventListener('DOMContentLoaded', () => {
    // Core
    initCustomCursor();
    crearModalConfirmacion();

    // Componentes
    initCustomSelects();

    // Productos — preview de imagen + filtro
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

    // Existencias
    initFiltrosExistencias();
    initBuscadorProducto();
});
