/* ============================================================
 * componentes.js — Bloques reutilizables en 2+ páginas.
 * Depende de: core.js (usa abrirModal).
 * Usado por: productos, existencias, cajeros, cajas.
 * ============================================================ */


/* ============================================================
 * Select custom — reemplaza el <select> nativo por un componente
 * con panel flotante, esquinas redondeadas, hover, checkmark en
 * la opción activa, y buscador cuando tiene >5 opciones.
 * El <select> original queda oculto (clip/opacity) y sigue
 * enviando el valor con el POST.
 * ============================================================ */

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
    const necesitaBuscador = opciones().length > 5;

    const pintarTrigger = () => {
        const opt = select.options[select.selectedIndex];
        const texto = opt ? opt.text : tj('seleccionar');
        // El texto va con textContent: opt.text ya viene decodificado y puede traer "<"
        trigger.innerHTML = `<span></span>
        <svg class="select-custom-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>`;
        trigger.querySelector('span').textContent = texto;
        trigger.title = texto;
        trigger.classList.toggle('is-placeholder', !opt || opt.value === '' || opt.disabled);
    };

    const pintarPanel = () => {
        panel.innerHTML = '';

        let inputBusqueda = null;
        if (necesitaBuscador) {
            const wrap = document.createElement('div');
            wrap.className = 'select-custom-search';

            inputBusqueda = document.createElement('input');
            inputBusqueda.type = 'text';
            inputBusqueda.className = 'select-custom-search-input';
            inputBusqueda.placeholder = tj('buscar');
            inputBusqueda.setAttribute('autocomplete', 'off');
            inputBusqueda.setAttribute('aria-label', tj('buscar_opcion'));

            wrap.appendChild(inputBusqueda);
            panel.appendChild(wrap);
        }

        opciones().forEach((opt, i) => {
            if (opt.hidden) return;   // p. ej. productos de otra categoría (barra de filtros)
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
            const etiqueta = document.createElement('span');
            etiqueta.textContent = opt.text;
            item.appendChild(etiqueta);
            // Barra de filtros: rango de actividad de la opción y si cae fuera del periodo
            if (opt.dataset.detalle) {
                const detalle = document.createElement('small');
                detalle.className = 'select-custom-detalle';
                const estado = opt.dataset.estado;
                detalle.textContent = estado === 'incompatible' ? tj('filtros_incompatible')
                    : (estado === 'fuera' ? tj('filtros_fuera') + ' · ' : '') + opt.dataset.detalle;
                item.classList.toggle('is-fuera', estado === 'fuera');
                item.appendChild(detalle);
            }

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

        if (inputBusqueda) {
            const aplicarFiltro = () => {
                const q = inputBusqueda.value.trim().toLowerCase();
                panel.querySelectorAll('.select-custom-option').forEach(item => {
                    const coincide = item.textContent.toLowerCase().includes(q);
                    item.style.display = coincide ? '' : 'none';
                });
            };

            inputBusqueda.addEventListener('input', aplicarFiltro);

            inputBusqueda.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    e.stopPropagation();
                    cerrar();
                } else if (e.key === 'Enter') {
                    const visible = Array.from(panel.querySelectorAll('.select-custom-option'))
                    .find(el => el.style.display !== 'none' && !el.classList.contains('is-disabled'));
                    if (visible) visible.click();
                }
            });

            requestAnimationFrame(() => inputBusqueda.focus());
        }
    };

    const posicionarPanel = () => {
        const r = trigger.getBoundingClientRect();
        const alturaPanel = Math.min(panel.scrollHeight || 320, 320);
        const espacioAbajo = window.innerHeight - r.bottom;
        const abrirArriba = espacioAbajo < alturaPanel + 12 && r.top > alturaPanel + 12;

        panel.style.minWidth = r.width + 'px';
        const ancho = panel.offsetWidth || r.width;
        panel.style.left = Math.max(8, Math.min(r.left, window.innerWidth - ancho - 8)) + 'px';
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
        posicionarPanel();                          // ya, sin esperar al siguiente cuadro (no parpadea)
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
            // Valor asignado por código (abrirModal lo pide): solo redibujar, sin "change"
            select.addEventListener('select-custom:sincronizar', pintarTrigger);

            // Reposicionar si el layout cambia mientras está abierto
            window.addEventListener('resize', () => {
                if (wrapper.classList.contains('is-open')) posicionarPanel();
            });

            // La lista es position: fixed; sin esto se quedaba quieta en pantalla
            // mientras la página hacía scroll. Captura también el scroll de modales.
            window.addEventListener('scroll', (e) => {
                if (!wrapper.classList.contains('is-open')) return;
                if (e.target instanceof Node && panel.contains(e.target)) return; // scroll dentro de la lista
                const r = trigger.getBoundingClientRect();
                if (r.bottom < 0 || r.top > window.innerHeight) cerrar();     // el botón salió de la vista
                else posicionarPanel();
            }, { capture: true, passive: true });

                pintarTrigger();
                _selectCustomActivos.add({ wrapper, close: cerrar });
}

function initCustomSelects() {
    document.querySelectorAll('.toolbar select, .modal-box select, .filtros select').forEach(initCustomSelect);
}

// Cerrar al hacer click fuera
document.addEventListener('click', (e) => {
    if (!e.target.closest('.select-custom') && !e.target.closest('.select-custom-panel')) {
        _cerrarTodosLosSelects(null);
    }
});


/* ============================================================
 * Selector de vista estilo Finder — cajas y cajeros
 * <div data-vistas="idDelContenedor" data-vistas-clave="cajas"> con
 * botones [data-vista]: iconos, lista, columnas, galeria.
 * La vista se pone en data-vista del contenedor (el CSS hace el resto)
 * y se recuerda en este navegador. En "columnas" las tarjetas se
 * agrupan por su data-columna (el estado), ordenadas por data-columna-orden.
 * ============================================================ */
const VISTAS_VALIDAS = ['iconos', 'lista', 'columnas', 'galeria'];

function initVistas() {
    document.querySelectorAll('[data-vistas]').forEach(barra => {
        const contenedor = document.getElementById(barra.dataset.vistas);
        if (!contenedor) return;

        const botones = barra.querySelectorAll('[data-vista]');
        const clave   = 'koaly.vista.' + barra.dataset.vistasClave;
        // Orden original de las tarjetas, para volver a él al salir de columnas
        const tarjetas = Array.from(contenedor.children);

        const pintar = (vista) => {
            contenedor.dataset.vista = vista;
            botones.forEach(b => {
                const activo = b.dataset.vista === vista;
                b.classList.toggle('is-activo', activo);
                b.setAttribute('aria-pressed', activo ? 'true' : 'false');
            });

            contenedor.replaceChildren();
            if (vista !== 'columnas') {
                contenedor.append(...tarjetas);
                return;
            }

            const columnas = new Map();
            tarjetas.forEach(card => {
                const titulo = card.dataset.columna || '';
                if (!columnas.has(titulo)) {
                    columnas.set(titulo, { orden: parseInt(card.dataset.columnaOrden, 10) || 0, cards: [] });
                }
                columnas.get(titulo).cards.push(card);
            });
            [...columnas.entries()]
                .sort((a, b) => a[1].orden - b[1].orden)
                .forEach(([titulo, { cards }]) => {
                    const col = document.createElement('section');
                    col.className = 'vista-columna';
                    const encabezado = document.createElement('h2');
                    encabezado.className = 'vista-columna-titulo';
                    encabezado.textContent = titulo;
                    const conteo = document.createElement('span');
                    conteo.className = 'segmentado-conteo';
                    conteo.textContent = cards.length;
                    encabezado.append(' ', conteo);
                    col.append(encabezado, ...cards);
                    contenedor.append(col);
                });
        };

        botones.forEach(btn => btn.addEventListener('click', () => {
            pintar(btn.dataset.vista);
            try { localStorage.setItem(clave, btn.dataset.vista); } catch (err) { /* modo privado */ }
        }));

        let guardada = null;
        try { guardada = localStorage.getItem(clave); } catch (err) { /* modo privado */ }
        if (VISTAS_VALIDAS.includes(guardada) && guardada !== 'iconos') pintar(guardada);
    });
}

/* ============================================================
 * Filas desplegables de la vista de lista (.tabla-tarjetas)
 * Tocar una fila abre o cierra su detalle (la fila .fila-detalle que
 * sigue). Los botones, formularios y enlaces de la fila hacen lo suyo
 * sin abrirla; la flecha (.fila-abrir) es el control para teclado.
 * ============================================================ */
function initFilasDesplegables() {
    document.addEventListener('click', (e) => {
        const fila = e.target.closest('tr.fila-desplegable');
        if (!fila) return;
        if (e.target.closest('a, form, button:not(.fila-abrir), input, select, label')) return;
        const detalle = fila.nextElementSibling;
        if (!detalle || !detalle.classList.contains('fila-detalle')) return;
        const abrir = fila.getAttribute('aria-expanded') !== 'true';
        fila.setAttribute('aria-expanded', abrir ? 'true' : 'false');
        const flecha = fila.querySelector('.fila-abrir');
        if (flecha) flecha.setAttribute('aria-expanded', abrir ? 'true' : 'false');
        detalle.hidden = !abrir;
    });
}


/* ============================================================
 * Barra de filtros unificada (includes/barra_filtros.php)
 * Periodo + facetas se limitan entre sí:
 * - Fechas posibles: [primera actividad, hoy] ∩ rango de cada opción
 *   elegida (data-desde / data-hasta). Una caja desactivada hace tres
 *   meses solo deja elegir hasta su última venta.
 * - Al elegir una opción sin actividad en el periodo, el periodo se mueve
 *   a sus últimos días activos (misma duración) y se avisa.
 * - Las opciones que no coinciden con las demás quedan desactivadas; las
 *   que no tienen actividad en el periodo lo dicen en la lista.
 * Las fechas van como 'YYYY-MM-DD' y se comparan como texto.
 * ============================================================ */
const _fechaUTC = (s) => { const [a, m, d] = s.split('-').map(Number); return new Date(Date.UTC(a, m - 1, d)); };
const _aTexto   = (f) => f.toISOString().slice(0, 10);
const _sumarDias = (s, n) => { const f = _fechaUTC(s); f.setUTCDate(f.getUTCDate() + n); return _aTexto(f); };
const _diasEntre = (a, b) => Math.round((_fechaUTC(b) - _fechaUTC(a)) / 864e5);
const _maxF = (a, b) => (a > b ? a : b);
const _minF = (a, b) => (a < b ? a : b);
const CLAVE_AVISO_FILTROS = 'koaly.filtros.aviso';

function initFiltros() {
    document.querySelectorAll('[data-filtros]').forEach(form => {
        const datos   = JSON.parse(form.querySelector('[data-filtros-datos]').textContent);
        const inDesde = form.querySelector('[data-filtros-desde]');
        const inHasta = form.querySelector('[data-filtros-hasta]');
        const selects = Array.from(form.querySelectorAll('select[data-faceta]'));
        const aviso   = form.querySelector('[data-filtros-aviso]');
        const trigger = form.querySelector('[data-periodo-abrir]');
        const flechas = Array.from(form.querySelectorAll('[data-periodo-mover]'));
        const activos = new Set(datos.dias);
        const idioma  = document.documentElement.lang || 'es';
        const fmtDia  = new Intl.DateTimeFormat(idioma, { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' });
        const fmtMes  = new Intl.DateTimeFormat(idioma, { month: 'long', year: 'numeric', timeZone: 'UTC' });
        const fmtSem  = new Intl.DateTimeFormat(idioma, { weekday: 'narrow', timeZone: 'UTC' });
        const textoDia = (s) => fmtDia.format(_fechaUTC(s));

        // Rango posible = [inicio, hoy] ∩ rango de cada opción elegida (menos la de "excepto")
        const rangoPermitido = (excepto = null) => {
            let desde = datos.inicio, hasta = datos.hoy;
            const nombres = [];
            selects.forEach(sel => {
                if (sel === excepto) return;
                const o = sel.selectedOptions[0];
                if (o && o.dataset.desde) {
                    desde = _maxF(desde, o.dataset.desde);
                    hasta = _minF(hasta, o.dataset.hasta);
                    nombres.push(o.text);
                }
            });
            return { desde, hasta, nombres };
        };

        // Producto según categoría (data-depende / data-padre)
        const aplicarDependencias = () => {
            selects.filter(s => s.dataset.depende).forEach(hijo => {
                const padre = form.querySelector(`select[name="${hijo.dataset.depende}"]`);
                if (!padre) return;
                const sinPadre = padre.value === padre.options[0].value;
                Array.from(hijo.options).slice(1).forEach(o => { o.hidden = !sinPadre && o.dataset.padre !== padre.value; });
                if (hijo.selectedOptions[0] && hijo.selectedOptions[0].hidden) hijo.selectedIndex = 0;
            });
        };

        // Estado de cada opción frente a las demás facetas y al periodo
        const pintarOpciones = () => {
            selects.forEach(sel => {
                const otros = rangoPermitido(sel);
                Array.from(sel.options).slice(1).forEach(o => {
                    if (!o.dataset.desde) return;   // sin actividad: viene desactivada del servidor
                    const compatible = o.dataset.desde <= otros.hasta && o.dataset.hasta >= otros.desde;
                    const enPeriodo  = o.dataset.desde <= inHasta.value && o.dataset.hasta >= inDesde.value;
                    o.disabled = !compatible;
                    o.dataset.estado = !compatible ? 'incompatible' : (enPeriodo ? '' : 'fuera');
                });
                sel.dispatchEvent(new Event('select-custom:sincronizar'));
            });
        };

        // Periodo dentro de lo posible; si queda fuera, sus últimos días activos con la misma duración
        const acotar = (desde, hasta, r) => {
            const largo = _diasEntre(desde, hasta);
            desde = _maxF(desde, r.desde);
            hasta = _minF(hasta, r.hasta);
            if (desde > hasta) {
                hasta = r.hasta;
                desde = _maxF(r.desde, _sumarDias(hasta, -largo));
            }
            return [desde, hasta];
        };

        const enviar = (desde, hasta, mensaje = '') => {
            inDesde.value = desde;
            inHasta.value = hasta;
            if (mensaje) {
                try { sessionStorage.setItem(CLAVE_AVISO_FILTROS, mensaje); } catch (err) { /* modo privado */ }
            }
            form.submit();
        };

        selects.forEach(sel => sel.addEventListener('change', () => {
            aplicarDependencias();
            const r = rangoPermitido();
            const [desde, hasta] = acotar(inDesde.value, inHasta.value, r);
            const movido = desde !== inDesde.value || hasta !== inHasta.value;
            enviar(desde, hasta, movido
                ? tj('filtros_ajustado', { nombre: r.nombres.join(' · '), desde: textoDia(desde), hasta: textoDia(hasta) })
                : '');
        }));

        // ‹ › mueven el periodo su propia duración, sin salir de lo posible
        const pintarFlechas = () => {
            const r = rangoPermitido();
            flechas.forEach(b => {
                const atras = b.dataset.periodoMover === '-1';
                b.disabled = atras ? inDesde.value <= r.desde : inHasta.value >= r.hasta;
            });
        };
        flechas.forEach(b => b.addEventListener('click', () => {
            const r = rangoPermitido();
            const largo = _diasEntre(inDesde.value, inHasta.value) + 1;
            const paso = Number(b.dataset.periodoMover) * largo;
            let desde = _sumarDias(inDesde.value, paso), hasta = _sumarDias(inHasta.value, paso);
            if (desde < r.desde) { desde = r.desde; hasta = _minF(r.hasta, _sumarDias(desde, largo - 1)); }
            if (hasta > r.hasta) { hasta = r.hasta; desde = _maxF(r.desde, _sumarDias(hasta, -(largo - 1))); }
            enviar(desde, hasta);
        }));

        /* ---------- Calendario ---------- */
        const panel = document.createElement('div');
        panel.className = 'calendario';
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-label', tj('filtros_elegir_periodo'));
        document.body.appendChild(panel);

        let selDesde, selHasta, eligiendoFin, mes, hoverDia = null;

        const pintar = () => {
            const r = rangoPermitido();
            const finVisible = eligiendoFin && hoverDia ? hoverDia : selHasta;
            const [a, b] = selDesde <= finVisible ? [selDesde, finVisible] : [finVisible, selDesde];
            panel.replaceChildren();

            // Atajos (acotados a lo posible)
            const lista = document.createElement('div');
            lista.className = 'calendario-atajos';
            datos.atajos.forEach(at => {
                const desde = _maxF(at.desde, r.desde), hasta = _minF(at.hasta, r.hasta);
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = at.texto;
                btn.disabled = desde > hasta;
                btn.classList.toggle('is-activo', !eligiendoFin && desde === selDesde && hasta === selHasta);
                btn.addEventListener('click', () => enviar(desde, hasta));
                lista.appendChild(btn);
            });

            // Mes
            const cuerpo = document.createElement('div');
            cuerpo.className = 'calendario-cuerpo';
            const cab = document.createElement('div');
            cab.className = 'calendario-cabecera';
            const mesAnterior = _aTexto(new Date(Date.UTC(+mes.slice(0, 4), +mes.slice(5, 7) - 2, 1)));
            const mesSiguiente = _aTexto(new Date(Date.UTC(+mes.slice(0, 4), +mes.slice(5, 7), 1)));
            const navMes = (destino, etiqueta, flecha, desactivado) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'calendario-nav';
                btn.innerHTML = flecha;
                btn.setAttribute('aria-label', etiqueta);
                btn.title = etiqueta;
                btn.disabled = desactivado;
                btn.addEventListener('click', () => { mes = destino; pintar(); });
                return btn;
            };
            const titulo = document.createElement('strong');
            const nombreMes = fmtMes.format(_fechaUTC(mes));   // solo la primera letra en mayúscula
            titulo.textContent = nombreMes.charAt(0).toUpperCase() + nombreMes.slice(1);
            cab.append(
                navMes(mesAnterior, tj('filtros_mes_anterior'), '‹', _sumarDias(mes, -1) < r.desde),
                titulo,
                navMes(mesSiguiente, tj('filtros_mes_siguiente'), '›', mesSiguiente > r.hasta)
            );

            const grid = document.createElement('div');
            grid.className = 'calendario-dias';
            for (let i = 0; i < 7; i++) {               // lunes a domingo (5 ene 1970 fue lunes)
                const s = document.createElement('span');
                s.className = 'calendario-semana';
                s.textContent = fmtSem.format(new Date(Date.UTC(1970, 0, 5 + i)));
                grid.appendChild(s);
            }
            const primero = _fechaUTC(mes);
            const huecos = (primero.getUTCDay() + 6) % 7;
            for (let i = 0; i < huecos; i++) grid.appendChild(document.createElement('span'));
            const ultimo = _sumarDias(mesSiguiente, -1);
            for (let dia = mes; dia <= ultimo; dia = _sumarDias(dia, 1)) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'calendario-dia';
                btn.textContent = String(+dia.slice(8));
                btn.dataset.dia = dia;
                const posible = dia >= r.desde && dia <= r.hasta;
                btn.disabled = !posible;
                btn.classList.toggle('con-actividad', activos.has(dia));
                btn.classList.toggle('es-hoy', dia === datos.hoy);
                btn.classList.toggle('en-rango', dia >= a && dia <= b);
                btn.classList.toggle('es-inicio', dia === a);
                btn.classList.toggle('es-fin', dia === b);
                btn.setAttribute('aria-label', textoDia(dia) + (activos.has(dia) ? ' · ' + tj('filtros_con_actividad') : ''));
                btn.setAttribute('aria-pressed', dia >= a && dia <= b ? 'true' : 'false');
                btn.addEventListener('click', () => {
                    if (!eligiendoFin) {
                        selDesde = selHasta = dia;
                        eligiendoFin = true;
                    } else {
                        [selDesde, selHasta] = dia < selDesde ? [dia, selDesde] : [selDesde, dia];
                        eligiendoFin = false;
                    }
                    hoverDia = null;
                    pintar();
                    panel.querySelector(`[data-dia="${dia}"]`)?.focus();
                });
                btn.addEventListener('mouseenter', () => {
                    if (eligiendoFin && hoverDia !== dia) { hoverDia = dia; pintar(); }
                });
                grid.appendChild(btn);
            }

            // Resumen y acciones
            const pie = document.createElement('div');
            pie.className = 'calendario-pie';
            const resumen = document.createElement('span');
            resumen.className = 'calendario-resumen';
            resumen.textContent = eligiendoFin
                ? tj('filtros_elige_fin')
                : (selDesde === selHasta ? textoDia(selDesde)
                    : tj('filtros_rango', { desde: textoDia(selDesde), hasta: textoDia(selHasta), n: _diasEntre(selDesde, selHasta) + 1 }));
            const cancelar = document.createElement('button');
            cancelar.type = 'button';
            cancelar.className = 'btn btn-ghost btn-sm';
            cancelar.textContent = tj('cancelar');
            cancelar.addEventListener('click', cerrar);
            const aplicar = document.createElement('button');
            aplicar.type = 'button';
            aplicar.className = 'btn btn-primary btn-sm';
            aplicar.textContent = tj('filtros_aplicar');
            aplicar.disabled = eligiendoFin;
            aplicar.addEventListener('click', () => enviar(selDesde, selHasta));
            pie.append(resumen, cancelar, aplicar);

            cuerpo.append(cab, grid);
            panel.append(lista, cuerpo, pie);
        };

        const posicionar = () => {
            if (window.matchMedia('(max-width: 640px)').matches) {   // hoja inferior (CSS)
                panel.style.left = panel.style.top = '';
                return;
            }
            const r = trigger.getBoundingClientRect();
            const ancho = panel.offsetWidth, alto = panel.offsetHeight;
            const arriba = window.innerHeight - r.bottom < alto + 12 && r.top > alto + 12;
            panel.style.left = Math.max(8, Math.min(r.left, window.innerWidth - ancho - 8)) + 'px';
            panel.style.top  = (arriba ? r.top - alto - 6 : r.bottom + 6) + 'px';
        };

        const abrir = () => {
            selDesde = inDesde.value;
            selHasta = inHasta.value;
            eligiendoFin = false;
            hoverDia = null;
            mes = selHasta.slice(0, 8) + '01';
            pintar();
            panel.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
            requestAnimationFrame(() => {
                posicionar();
                (panel.querySelector('.calendario-dia.es-fin') || panel.querySelector('.calendario-dia:not(:disabled)'))?.focus();
            });
        };
        function cerrar() {
            panel.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
        }

        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            panel.classList.contains('is-open') ? cerrar() : abrir();
        });
        panel.addEventListener('click', (e) => e.stopPropagation());
        panel.addEventListener('mouseleave', () => { if (hoverDia) { hoverDia = null; pintar(); } });
        document.addEventListener('click', () => { if (panel.classList.contains('is-open')) cerrar(); });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && panel.classList.contains('is-open')) { cerrar(); trigger.focus(); }
        });
        window.addEventListener('resize', () => { if (panel.classList.contains('is-open')) posicionar(); });
        window.addEventListener('scroll', (e) => {
            if (panel.classList.contains('is-open') && !(e.target instanceof Node && panel.contains(e.target))) posicionar();
        }, { capture: true, passive: true });

        // Estado inicial
        aplicarDependencias();
        pintarOpciones();
        pintarFlechas();
        try {
            const mensaje = sessionStorage.getItem(CLAVE_AVISO_FILTROS);
            if (mensaje && aviso) {
                aviso.textContent = mensaje;
                sessionStorage.removeItem(CLAVE_AVISO_FILTROS);
            }
        } catch (err) { /* modo privado */ }
    });
}
