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

function abrirModalSolicitarProducto() {
    const form = document.getElementById('formSolicitarProducto');
    if (!form) return;
    form.reset();

    const submit = form.querySelector('[type="submit"]');
    if (submit) submit.disabled = false;

    abrirModal('modalSolicitarProducto');
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

    // Contraseña, fecha de contratación y aviso de privacidad solo al dar de alta
    ['grupoPassword', 'grupoFechaContratacion', 'ayudaAviso'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.hidden = !nuevo;
    });
    const inputPassword = form.querySelector('[name="password"]');
    if (inputPassword) inputPassword.required = nuevo;

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

/** Contraseña temporal de 10 caracteres, sin letras ni números que se confundan (0/O, 1/l/I). */
function generarPasswordCajero() {
    const alfabeto = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
    const azar = new Uint32Array(10);
    crypto.getRandomValues(azar);
    const input = document.querySelector('#formCajero [name="password"]');
    input.value = Array.from(azar, n => alfabeto[n % alfabeto.length]).join('');
    input.focus();
    input.select();
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
    initCustomSelects();
    initVistas();

    // Productos — filtros
    initFiltrosProductos();

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
 * ██ PRODUCTOS — Pestañas (grupo), búsqueda, categoría, stock y orden
 * El filtro de stock solo aplica a "En mi sucursal": en las demás
 * pestañas se oculta, porque esos productos no tienen stock.
 * Al ordenar por stock, los que no tienen (fuera de la sucursal) van al final.
 * ============================================================ */
function initFiltrosProductos() {
    const grid = document.getElementById('gridProductos');
    if (!grid) return;

    const pestanas  = document.querySelectorAll('#pestanasProductos [data-grupo]');
    const buscador  = document.getElementById('buscarProducto');
    const categoria = document.getElementById('filtroCategoria');
    const filtro    = document.getElementById('filtroStock');
    const orden     = document.getElementById('ordenProductos');
    const vacio     = document.getElementById('productosSinResultados');
    const marcada   = document.querySelector('#pestanasProductos .is-activo');
    const umbral    = (filtro && parseInt(filtro.dataset.umbral, 10)) || 10;
    // El select de stock puede estar envuelto por el select personalizado
    const cajaStock = filtro ? (filtro.closest('.select-custom') || filtro) : null;
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

    const aplicar = () => {
        const q    = buscador ? buscador.value.trim().toLowerCase() : '';
        const cat  = categoria ? categoria.value : '';
        const tipo = filtro && grupo === 'con' ? filtro.value : 'todos';
        if (cajaStock) cajaStock.hidden = grupo !== 'con';

        let visibles = 0;
        grid.querySelectorAll('[data-grupo]').forEach(card => {
            const visible = (grupo === 'todos' || card.dataset.grupo === grupo)
                && (!cat || card.dataset.categoria === cat)
                && card.dataset.nombre.includes(q)
                && (tipo === 'todos' || cumpleStock(parseInt(card.dataset.stock, 10), tipo));
            card.hidden = !visible;
            if (visible) visibles++;
        });
        if (vacio) vacio.hidden = visibles > 0;
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
    };

    pestanas.forEach(btn => btn.addEventListener('click', () => {
        grupo = btn.dataset.grupo;
        pestanas.forEach(b => {
            b.classList.toggle('is-activo', b === btn);
            b.setAttribute('aria-pressed', b === btn ? 'true' : 'false');
        });
        aplicar();
    }));
    if (buscador)  buscador.addEventListener('input', aplicar);
    if (categoria) categoria.addEventListener('change', aplicar);
    if (filtro)    filtro.addEventListener('change', aplicar);
    if (orden)     orden.addEventListener('change', ordenar);
    ordenar(); // el orden también puede venir de la URL (?orden=stock_asc)
    aplicar(); // pestaña, búsqueda y stock pueden venir de la URL (?stock=alerta)
}
