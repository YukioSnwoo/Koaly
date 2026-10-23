/* ============================================================
 * paginas.js — Código específico de cada página del gerente.
 * Depende de: core.js (abrirModal) y componentes.js (initCustomSelects).
 * Usado por: productos, existencias, cajeros, cajas.
 * ============================================================ */


/* ============================================================
 * ██ PRODUCTOS — Modales y filtros
 * ============================================================ */

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

/* ------------------------------------------------------------
 * Solicitudes de producto. El mismo modal sirve para pedir un
 * producto nuevo y para editar una solicitud que el admin devolvió:
 * los textos que cambian llevan data-nuevo y data-editar.
 * ------------------------------------------------------------ */
const LIMITE_IMAGEN_SOLICITUD = 5 * 1024 * 1024;

function modoFormularioSolicitud(form, editar) {
    const modal = form.closest('.modal-overlay');
    modal.querySelectorAll('[data-nuevo]').forEach(el => {
        el.textContent = editar ? el.dataset.editar : el.dataset.nuevo;
    });
    form.querySelector('[name="action"]').value = editar ? 'editar_solicitud' : 'solicitar_producto';
    form.querySelector('[name="imagen"]').required = !editar;

    // Nuevo: justificación. Editar: motivo de los cambios para el admin.
    form.querySelectorAll('[data-modo]').forEach(grupo => {
        const visible = grupo.dataset.modo === (editar ? 'editar' : 'nuevo');
        grupo.hidden = !visible;
        grupo.querySelectorAll('textarea').forEach(campo => {
            campo.disabled = !visible;
            campo.required = visible;
        });
    });
}

function mostrarCamposProveedor(form, mostrar) {
    const campos = form.querySelector('#camposProveedor');
    form.querySelector('#conProveedor').checked = mostrar;
    campos.hidden = !mostrar;
    campos.disabled = !mostrar;
    validarContactoProveedor(form);
}

// Al menos un contacto del proveedor: teléfono o correo
function validarContactoProveedor(form) {
    const telefono = form.querySelector('[name="proveedor_telefono"]');
    const correo = form.querySelector('[name="proveedor_correo"]');
    const falta = !form.querySelector('#camposProveedor').disabled
        && telefono.value.trim() === '' && correo.value.trim() === '';
    telefono.setCustomValidity(falta ? tj('sol_contacto') : '');
}

function mostrarVistaPreviaSolicitud(form, src) {
    const vista = form.querySelector('.imagen-preview');
    vista.hidden = !src;
    vista.querySelector('img').src = src || '';
}

function prepararFormularioSolicitud(form) {
    form.reset();
    mostrarVistaPreviaSolicitud(form, '');
    form.querySelector('[name="imagen"]').setCustomValidity('');
    const submit = form.querySelector('[type="submit"]');
    if (submit) submit.disabled = false;
}

function abrirModalSolicitarProducto() {
    const form = document.getElementById('formSolicitarProducto');
    if (!form) return;
    prepararFormularioSolicitud(form);
    modoFormularioSolicitud(form, false);
    form.querySelector('[name="id_solicitud"]').value = '';
    mostrarCamposProveedor(form, false);

    abrirModal('modalSolicitarProducto');
}

function abrirModalEditarSolicitud(btn) {
    const form = document.getElementById('formSolicitarProducto');
    if (!form) return;
    prepararFormularioSolicitud(form);
    modoFormularioSolicitud(form, true);

    const d = btn.dataset;
    form.querySelector('[name="id_solicitud"]').value = d.id;
    form.querySelector('[name="nombre_producto"]').value = d.nombre;
    form.querySelector('[name="descripcion"]').value = d.descripcion;
    form.querySelector('[name="id_categoria"]').value = d.categoria;
    form.querySelector('[name="precio_sugerido"]').value = parseFloat(d.precio).toFixed(2);
    form.querySelector('[name="proveedor_sugerido"]').value = d.proveedor;
    form.querySelector('[name="proveedor_empresa"]').value = d.empresa;
    form.querySelector('[name="proveedor_telefono"]').value = d.telefono;
    form.querySelector('[name="proveedor_correo"]').value = d.correo;
    mostrarCamposProveedor(form, d.proveedor !== '');
    mostrarVistaPreviaSolicitud(form, d.imagen ? '../' + d.imagen : '');

    cerrarModal('fichaSolicitud' + d.id);
    abrirModal('modalSolicitarProducto');
}

function abrirModalCancelarSolicitud(btn) {
    const modal = document.getElementById('modalCancelarSolicitud');
    if (!modal) return;
    const form = modal.querySelector('form');
    form.reset();
    form.querySelector('[name="id_solicitud"]').value = btn.dataset.id;
    form.querySelector('[type="submit"]').disabled = false;
    document.getElementById('cancelarSolicitudNombre').textContent = btn.dataset.nombre;

    cerrarModal('fichaSolicitud' + btn.dataset.id);
    abrirModal('modalCancelarSolicitud');
}

function initFormularioSolicitud() {
    const form = document.getElementById('formSolicitarProducto');
    if (!form) return;

    form.querySelector('#conProveedor').addEventListener('change', (e) => mostrarCamposProveedor(form, e.target.checked));
    ['proveedor_telefono', 'proveedor_correo'].forEach(nombre => {
        form.querySelector(`[name="${nombre}"]`).addEventListener('input', () => validarContactoProveedor(form));
    });

    const imagen = form.querySelector('[name="imagen"]');
    imagen.addEventListener('change', () => {
        const archivo = imagen.files[0];
        imagen.setCustomValidity(archivo && archivo.size > LIMITE_IMAGEN_SOLICITUD ? tj('sol_imagen_grande') : '');
        if (archivo) mostrarVistaPreviaSolicitud(form, URL.createObjectURL(archivo));
        imagen.reportValidity();
    });
}

/* ============================================================
 * ██ CAJEROS — Modales
 * ============================================================ */

function _prepararModalCajero(modo) {
    const form = document.getElementById('formCajero');
    form.reset();
    form.querySelector('[name="action"]').value = modo;

    const nuevo = modo === 'crear';
    const titulo = document.getElementById('tituloModalCajero');
    if (titulo) titulo.textContent = tj(nuevo ? 'nuevo_cajero' : 'editar_cajero');

    // Fecha de contratación y aviso solo al dar de alta. La contraseña la genera el
    // servidor y solo llega al cajero por correo: el gerente no la ve ni la escribe.
    ['grupoFechaContratacion', 'ayudaAviso'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.hidden = !nuevo;
    });

    const submit = form.querySelector('[type="submit"]');
    if (submit) submit.disabled = false;
    return form;
}

function abrirModalNuevoCajero() {
    const form = _prepararModalCajero('crear');
    form.dataset.estadoOriginal = '';
    form.querySelector('[name="id_usuario"]').value = '';
    form.querySelector('[name="fecha_contratacion"]').value = new Date().toISOString().slice(0, 10);
    _actualizarAyudaEstado();
    abrirModal('modalCajero');
    form.querySelector('[name="nombre"]').focus();
}

function abrirModalEditarCajero(btn) {
    const form = _prepararModalCajero('editar');
    form.querySelector('[name="id_usuario"]').value = btn.dataset.id;
    form.querySelector('[name="nombre"]').value = btn.dataset.nombre;
    form.querySelector('[name="email"]').value = btn.dataset.email;
    form.querySelector('[name="estado"]').value = btn.dataset.estado;
    form.dataset.estadoOriginal = btn.dataset.estado;
    _actualizarAyudaEstado();
    abrirModal('modalCajero');
}

/**
 * Explica qué pasa con su caja al elegir un estado distinto de "Activo" y, al
 * dar de baja a alguien que no lo estaba, pide el motivo (va en su correo).
 */
function _actualizarAyudaEstado() {
    const form   = document.getElementById('formCajero');
    const select = form && form.querySelector('[name="estado"]');
    const ayuda  = document.getElementById('ayudaEstado');
    if (!select || !ayuda) return;
    const texto = select.value === 'Baja' ? tj('estado_ayuda_baja')
        : select.value !== 'Activo' ? tj('estado_ayuda_temporal') : '';
    ayuda.textContent = texto;
    ayuda.hidden = texto === '';

    const grupoMotivo = document.getElementById('grupoMotivoBaja');
    if (grupoMotivo) {
        const pedir = select.value === 'Baja' && form.dataset.estadoOriginal !== 'Baja'
            && form.querySelector('[name="action"]').value === 'editar';
        grupoMotivo.hidden = !pedir;
        const motivo = grupoMotivo.querySelector('select');
        motivo.required = pedir;
        if (!pedir) motivo.value = '';
        motivo.dispatchEvent(new Event('select-custom:sincronizar'));
    }
}

/** Baja desde la tarjeta: pide el motivo, que se incluye en el correo al empleado. */
function abrirModalBaja(btn) {
    const form = document.getElementById('formBaja');
    if (!form) return;
    form.reset();
    form.querySelector('[name="id_usuario"]').value = btn.dataset.id;
    const texto = document.getElementById('textoModalBaja');
    if (texto) texto.textContent = tj('baja_confirmar', { nombre: btn.dataset.nombre });
    const submit = form.querySelector('[type="submit"]');
    if (submit) submit.disabled = false;
    abrirModal('modalBaja');
}

document.addEventListener('change', (e) => {
    if (e.target.matches('#formCajero [name="estado"]')) _actualizarAyudaEstado();
});


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
    if (titulo) titulo.textContent = tj('nueva_caja');

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
    if (titulo) titulo.textContent = tj('editar_caja');

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
        // textContent: el número de caja lo captura el gerente
        aviso.textContent = tj('reasignar_aviso', { caja: cajaActual });
        aviso.classList.add('visible');

        form.dataset.confirm = tj('reasignar_confirmar', { caja: cajaActual });
        form.dataset.confirmTitulo = tj('reasignar_titulo');
        form.dataset.confirmAceptar = tj('reasignar_aceptar');
    } else {
        aviso.classList.remove('visible');
        delete form.dataset.confirm;
        delete form.dataset.confirmTitulo;
        delete form.dataset.confirmAceptar;
        delete form.dataset.confirmado;
    }
}


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
    initFiltros();
    initCustomSelects();
    initVistas();

    // Productos — filtros y solicitudes
    initFiltrosProductos();
    initFormularioSolicitud();

    // Ventas y Movimientos
    initScrollInfinito();
});


/* ============================================================
 * ██ CARGA PROGRESIVA — Ventas y Movimientos
 * <tbody data-scroll-infinito data-url data-pagina data-total-paginas>
 * Al acercarse al final pide ?pagina=N&parcial=1 y agrega las filas.
 * El servidor responde X-Hay-Mas, X-Ultimo-Grupo y X-Mostrando.
 * Sin JS queda el enlace "Cargar más" (abre la página siguiente).
 * ============================================================ */
function initScrollInfinito() {
    const formato = new Intl.NumberFormat(document.documentElement.lang || 'es');

    document.querySelectorAll('tbody[data-scroll-infinito]').forEach(tbody => {
        const zona = tbody.closest('section, .panel')?.querySelector('.carga-progresiva');
        const boton = zona?.querySelector('[data-cargar-mas]');
        const contador = zona?.querySelector('[data-contador]');
        if (!zona || !boton) return;

        let pagina = parseInt(tbody.dataset.pagina, 10) || 1;
        const totalPaginas = parseInt(tbody.dataset.totalPaginas, 10) || 1;
        let cargando = false;
        let observador = null;

        const terminar = () => {
            if (observador) observador.disconnect();
            zona.remove();
        };

        const cerca = () => zona.isConnected && zona.getBoundingClientRect().top < window.innerHeight + 400;

        const cargar = async () => {
            if (cargando || pagina >= totalPaginas) return;
            cargando = true;
            boton.textContent = tj('cargando');
            boton.setAttribute('aria-busy', 'true');

            const url = new URL(tbody.dataset.url, window.location.href);
            url.searchParams.set('pagina', pagina + 1);
            if (tbody.dataset.ultimoGrupo !== undefined) url.searchParams.set('grupo_previo', tbody.dataset.ultimoGrupo);

            try {
                const res = await fetch(url, { credentials: 'same-origin' });
                if (!res.ok) throw new Error(res.status);
                tbody.insertAdjacentHTML('beforeend', await res.text());
                pagina++;
                tbody.dataset.ultimoGrupo = decodeURIComponent(res.headers.get('X-Ultimo-Grupo') || '');

                const mostrando = res.headers.get('X-Mostrando');
                if (contador && mostrando) {
                    contador.textContent = tj('mostrando', {
                        n: formato.format(mostrando),
                        total: formato.format(contador.dataset.total || mostrando),
                    });
                }
                if (res.headers.get('X-Hay-Mas') !== '1') terminar();
            } catch (e) {
                boton.textContent = tj('reintentar');
                cargando = false;
                return;
            }

            cargando = false;
            boton.textContent = tj('cargar_mas');
            boton.removeAttribute('aria-busy');
            // Si las filas nuevas no llenan la pantalla, seguir cargando
            if (cerca()) cargar();
        };

        boton.addEventListener('click', (e) => { e.preventDefault(); cargar(); });

        if ('IntersectionObserver' in window) {
            observador = new IntersectionObserver(
                (entradas) => { if (entradas.some(en => en.isIntersecting)) cargar(); },
                { rootMargin: '400px 0px' }
            );
            observador.observe(zona);
        }
    });
}


/* ============================================================
 * ██ PRODUCTOS — Pestañas (grupo) y barra de filtros: búsqueda,
 * categoría, existencias y orden.
 * El filtro de existencias solo aplica a "En mi sucursal": en las demás
 * pestañas se oculta, porque esos productos no tienen stock.
 * Cada categoría dice cuántos productos tiene en la pestaña actual y se
 * desactiva si no tiene ninguno (como las facetas de Ventas).
 * Al ordenar por stock, los que no tienen (fuera de la sucursal) van al final.
 * El estado queda en la URL (?grupo=&buscar=&categoria=&stock=&orden=) para
 * que recargar, volver atrás o compartir el enlace muestre lo mismo.
 * ============================================================ */
function initFiltrosProductos() {
    const grid = document.getElementById('gridProductos');
    if (!grid) return;

    const form      = document.getElementById('filtrosProductos');
    const pestanas  = document.querySelectorAll('#pestanasProductos [data-grupo]');
    const buscador  = document.getElementById('buscarProducto');
    const categoria = document.getElementById('filtroCategoria');
    const filtro    = document.getElementById('filtroStock');
    const orden     = document.getElementById('ordenProductos');
    const campoStock = document.getElementById('campoStock');
    const conteo    = document.getElementById('conteoProductos');
    const limpiar   = document.getElementById('limpiarFiltrosProductos');
    const inGrupo   = form ? form.querySelector('[data-filtro-grupo]') : null;
    const vacio     = document.getElementById('productosSinResultados');
    const marcada   = document.querySelector('#pestanasProductos .is-activo');
    const umbral    = (filtro && parseInt(filtro.dataset.umbral, 10)) || 10;
    let grupo = marcada ? marcada.dataset.grupo : 'con';
    // El servidor las manda por nombre: ese es el orden base y el desempate
    const tarjetas = Array.from(grid.querySelectorAll('[data-grupo]'));
    tarjetas.forEach((card, i) => { card.dataset.posicion = i; });

    const cumpleStock = (stock, tipo) => {
        if (tipo === 'agotado') return stock === 0;
        if (tipo === 'bajo')    return stock > 0 && stock < umbral;
        if (tipo === 'normal')  return stock >= umbral;
        if (tipo === 'alerta')  return stock < umbral;
        return true;
    };
    const enPestana = card => grupo === 'todos' || card.dataset.grupo === grupo;

    // Cuántos productos de cada categoría hay en la pestaña actual
    const contarCategorias = () => {
        if (!categoria) return;
        const porCategoria = {};
        tarjetas.forEach(card => {
            if (enPestana(card)) porCategoria[card.dataset.categoria] = (porCategoria[card.dataset.categoria] || 0) + 1;
        });
        Array.from(categoria.options).forEach(opt => {
            if (!opt.value) return;
            const n = porCategoria[opt.value] || 0;
            opt.dataset.detalle = n === 0 ? tj('prods_ninguno_pestana')
                : tj(n === 1 ? 'prods_en_pestana_uno' : 'prods_en_pestana', { n });
            // La elegida no se desactiva aunque quede vacía: así se ve por qué no hay resultados
            opt.disabled = n === 0 && !opt.selected;
        });
    };

    const guardarEnUrl = () => {
        const params = new URLSearchParams();
        params.set('grupo', grupo);
        if (buscador && buscador.value.trim()) params.set('buscar', buscador.value.trim());
        if (categoria && categoria.value) params.set('categoria', categoria.value);
        if (filtro && grupo === 'con' && filtro.value !== 'todos') params.set('stock', filtro.value);
        if (orden && orden.value !== 'nombre') params.set('orden', orden.value);
        history.replaceState(null, '', location.pathname + '?' + params.toString() + location.hash);
        if (inGrupo) inGrupo.value = grupo;
        if (limpiar) limpiar.href = 'productos.php?grupo=' + encodeURIComponent(grupo);
    };

    const aplicar = () => {
        const q    = buscador ? buscador.value.trim().toLowerCase() : '';
        const cat  = categoria ? categoria.value : '';
        const tipo = filtro && grupo === 'con' ? filtro.value : 'todos';
        if (campoStock) campoStock.hidden = grupo !== 'con';

        let visibles = 0, total = 0;
        tarjetas.forEach(card => {
            const deLaPestana = enPestana(card);
            if (deLaPestana) total++;
            const visible = deLaPestana
                && (!cat || card.dataset.categoria === cat)
                && card.dataset.nombre.includes(q)
                && (tipo === 'todos' || cumpleStock(parseInt(card.dataset.stock, 10), tipo));
            card.hidden = !visible;
            if (visible) visibles++;
        });
        if (vacio) vacio.hidden = visibles > 0;

        const hayFiltros = q !== '' || cat !== '' || tipo !== 'todos';
        if (conteo) {
            conteo.textContent = hayFiltros
                ? tj('prods_mostrando', { n: visibles, total })
                : tj(total === 1 ? 'prods_total_uno' : 'prods_total', { n: total });
        }
        if (limpiar) limpiar.hidden = !hayFiltros;
        guardarEnUrl();
    };

    const ordenar = () => {
        const [campo, sentido] = (orden ? orden.value : 'nombre').split('_');
        const signo = sentido === 'desc' ? -1 : 1;
        const valor = card => {
            const v = parseFloat(campo === 'stock' ? card.dataset.stock : card.dataset.precio);
            return Number.isNaN(v) ? null : v;
        };
        const lista = tarjetas.slice();
        if (campo === 'stock' || campo === 'precio') {
            lista.sort((a, b) => {
                const va = valor(a), vb = valor(b);
                if (va === null || vb === null) {
                    if (va !== vb) return va === null ? 1 : -1;
                } else if (va !== vb) {
                    return (va - vb) * signo;
                }
                return a.dataset.posicion - b.dataset.posicion;
            });
        }
        grid.append(...lista);
        guardarEnUrl();
    };

    pestanas.forEach(btn => btn.addEventListener('click', () => {
        grupo = btn.dataset.grupo;
        pestanas.forEach(b => {
            b.classList.toggle('is-activo', b === btn);
            b.setAttribute('aria-pressed', b === btn ? 'true' : 'false');
        });
        contarCategorias();
        aplicar();
    }));
    // Con JavaScript se filtra al momento: el envío (Enter en la búsqueda) no recarga
    if (form) form.addEventListener('submit', e => e.preventDefault());
    if (buscador)  buscador.addEventListener('input', aplicar);
    if (categoria) categoria.addEventListener('change', () => { contarCategorias(); aplicar(); });
    if (filtro)    filtro.addEventListener('change', aplicar);
    if (orden)     orden.addEventListener('change', ordenar);
    if (limpiar)   limpiar.addEventListener('click', e => {
        e.preventDefault();
        if (buscador)  buscador.value = '';
        [categoria, filtro].forEach(sel => {
            if (!sel) return;
            sel.selectedIndex = 0;
            sel.dispatchEvent(new Event('select-custom:sincronizar'));
        });
        contarCategorias();
        aplicar();
        if (buscador) buscador.focus();
    });
    contarCategorias();
    ordenar(); // el orden también puede venir de la URL (?orden=stock_asc)
    aplicar(); // pestaña, búsqueda, categoría y stock pueden venir de la URL (?stock=alerta)
}
