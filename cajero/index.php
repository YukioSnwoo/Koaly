<?php
session_start();

if (!isset($_SESSION['id_usuario']) || (int) $_SESSION['id_rol'] !== 3) {
    header('Location: ../index.php');
    exit;
}

require_once __DIR__ . '/../admin/csrf.php';

$verCajeroCss = filemtime(__DIR__ . '/cajero.css');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAJA</title>
    <meta name="csrf-token" content="<?= htmlspecialchars(csrfToken()) ?>">
    <link rel="stylesheet" href="cajero.css?v=<?= $verCajeroCss ?>">
</head>
<body>

    <!-- Encabezado -->
    <header class="header">
        <h1>Koaly</h1>
        <div class="user-info">
            <div class="user-datos">
                <span class="user-nombre" id="userName">Cajero</span>
                <span class="user-sucursal">Sucursal: <span id="sucursalName">-</span></span>
            </div>
            <button type="button" class="btn-salir" onclick="abrirModal('modalSalir')" title="Cerrar sesión" aria-label="Cerrar sesión">
                <img src="../Imagenes/puertaSalida.jpeg" alt="">
            </button>
        </div>
    </header>

    <!-- Panel Principal -->
    <main class="pos-layout">

        <!-- Lateral Izquierdo -->
        <aside class="panel sidebar">
            <div class="sidebar-group">
                <div class="panel-title">Agregar producto</div>
                <div class="search-box">
                    <div class="input-buscar">
                        <svg class="icono-lupa" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                        <input type="text" id="inputBuscar" placeholder="Buscar por nombre o clave..." autocomplete="off"
                               role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="resultadosBusqueda">
                        <span class="spinner" aria-hidden="true"></span>
                        <div id="resultadosBusqueda" class="search-results" role="listbox" style="display:none;"></div>
                    </div>
                    <button type="button" class="btn btn-primary btn-block" onclick="buscarProducto()">Buscar producto</button>
                </div>
            </div>
            <div class="sidebar-group">
                <button type="button" class="btn btn-danger btn-block" onclick="eliminarFila()">Eliminar</button>
                <button type="button" class="btn btn-warning btn-block" onclick="limpiarTabla()">Limpiar</button>
                <p class="atajo-hint">Selecciona un producto y presiona <kbd>Supr</kbd> o <kbd>⌫</kbd> para quitarlo.</p>
            </div>
        </aside>

        <!-- Tabla Central -->
        <section class="panel tabla-panel">
            <table class="tabla-venta">
                <thead>
                    <tr>
                        <th>Clave</th>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th class="col-num">Precio</th>
                        <th class="col-num">Importe</th>
                        <th class="col-acciones"><span class="sr-only">Acciones</span></th>
                    </tr>
                </thead>
                <tbody id="listaProductos">
                    <!-- Filas dinámicas -->
                </tbody>
            </table>
        </section>

        <!-- Lateral Derecho (Totales) -->
        <aside class="panel resumen">
            <div>
                <div class="panel-title">Resumen</div>
                <div class="resumen-fila"><span>Artículos</span><span id="totalArticulos">0</span></div>
            </div>

            <div>
                <div class="resumen-fila"><span>Subtotal</span><span id="lblSubtotal">$0.00</span></div>
                <div class="resumen-fila"><span>IVA 16% (incluido)</span><span id="lblImpuestos">$0.00</span></div>
                <div class="resumen-total"><span>Total</span><span id="lblTotal">$0.00</span></div>
                <button type="button" class="btn btn-primary btn-block btn-pagar" onclick="abrirModalPago()">Pagar</button>
            </div>
        </aside>

    </main>

    <!-- Modal: cerrar sesión -->
    <div class="modal-overlay" id="modalSalir" role="dialog" aria-modal="true" aria-labelledby="tituloSalir">
        <div class="modal-box">
            <h2 id="tituloSalir">Cerrar sesión</h2>
            <p class="modal-mensaje">¿Deseas cerrar sesión?</p>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="cerrarModal('modalSalir')">No, me quedo</button>
                <button type="button" class="btn btn-danger-solid" onclick="logout()">Sí, cerrar sesión</button>
            </div>
        </div>
    </div>

    <!-- Modal: confirmar eliminación (un producto o toda la lista) -->
    <div class="modal-overlay" id="modalEliminar" role="dialog" aria-modal="true" aria-labelledby="tituloEliminar">
        <div class="modal-box modal-confirmar">
            <div class="icono-peligro" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
            </div>
            <h2 id="tituloEliminar">¿Eliminar producto?</h2>
            <div id="eliminarContenido"></div>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="cerrarModal('modalEliminar')">Cancelar</button>
                <button type="button" class="btn btn-danger-solid" id="btnConfirmarEliminar" onclick="confirmarEliminar()">Sí, eliminar</button>
            </div>
        </div>
    </div>

    <!-- Modal: método de pago -->
    <div class="modal-overlay" id="modalPago" role="dialog" aria-modal="true" aria-labelledby="tituloPago">
        <div class="modal-box">
            <h2 id="tituloPago">Método de pago</h2>
            <div class="modal-total-label">Total a cobrar</div>
            <div class="modal-total" id="modalTotal">$0.00</div>
            <div class="metodos-pago">
                <button type="button" class="metodo-btn" onclick="abrirModalEfectivo()"><span>💵</span>Efectivo</button>
                <button type="button" class="metodo-btn" onclick="pagarConTarjeta()"><span>💳</span>Tarjeta</button>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="cerrarModal('modalPago')">Cancelar</button>
            </div>
        </div>
    </div>

    <!-- Modal: pago en efectivo -->
    <div class="modal-overlay" id="modalEfectivo" role="dialog" aria-modal="true" aria-labelledby="tituloEfectivo">
        <div class="modal-box">
            <h2 id="tituloEfectivo">Pago en efectivo</h2>
            <div class="modal-total-label">Total a cobrar</div>
            <div class="modal-total" id="efectivoTotal">$0.00</div>
            <div class="form-group">
                <label for="inputRecibido">Efectivo recibido</label>
                <div class="input-dinero">
                    <span>$</span>
                    <input type="number" id="inputRecibido" min="0" step="0.01" inputmode="decimal" placeholder="0.00">
                </div>
            </div>
            <div class="cambio-preview" id="cambioPreview"></div>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="volverAMetodos()">Atrás</button>
                <button type="button" class="btn btn-primary" id="btnCobrarEfectivo" onclick="cobrarEfectivo()" disabled>Cobrar</button>
            </div>
        </div>
    </div>

    <!-- Modal: venta completada -->
    <div class="modal-overlay" id="modalVenta" role="dialog" aria-modal="true" aria-labelledby="tituloVenta">
        <div class="modal-box">
            <h2 id="tituloVenta">Venta completada</h2>
            <p class="modal-mensaje" id="ventaMensaje"></p>
            <div id="ventaCambio" style="display:none;">
                <div class="cambio-resultado">
                    <div class="cambio-label">Cambio</div>
                    <div class="cambio-monto" id="ventaCambioMonto">$0.00</div>
                </div>
                <div class="cambio-detalle" id="ventaCambioDetalle"></div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-primary" id="btnFinalizarVenta" onclick="finalizarVenta()">Aceptar</button>
            </div>
        </div>
    </div>

    <script src="../auth.js"></script>
    <script>
        const usuario = requireRol(3);
        if (usuario) {
            document.getElementById('userName').textContent = usuario.nombre || 'Cajero';
            document.getElementById('sucursalName').textContent = usuario.sucursal_nombre || usuario.id_sucursal || 'Sin asignar';
        }

        let carrito = [];
        let indiceSeleccionado = null;
        let accionEliminar = null;
        let totalActual = 0;
        let cobrando = false;
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        const inputBuscar = document.getElementById('inputBuscar');
        const resultadosBusqueda = document.getElementById('resultadosBusqueda');
        const inputRecibido = document.getElementById('inputRecibido');

        const formatoDinero = (n) => `$${n.toFixed(2)}`;
        const aCentavos = (n) => Math.round(n * 100);

        /* ---------- Modales ---------- */
        function abrirModal(id) {
            document.getElementById(id).classList.add('open');
        }

        function cerrarModal(id) {
            document.getElementById(id).classList.remove('open');
        }

        // Clic fuera de la caja o Escape cierra el modal, excepto el de venta
        // completada: la venta solo se cierra con "Aceptar".
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay && overlay.id !== 'modalVenta') cerrarModal(overlay.id);
            });
        });

        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Escape') return;
            document.querySelectorAll('.modal-overlay.open').forEach(overlay => {
                if (overlay.id !== 'modalVenta') cerrarModal(overlay.id);
            });
        });

        /* ---------- Búsqueda ---------- */
        // Sugerencias en vivo mientras se escribe; Enter (o un lector de código de
        // barras) agrega el producto resaltado o la coincidencia exacta de clave.
        const MIN_CARACTERES = 2;
        const ESPERA_MS = 250;
        let temporizadorBusqueda = null;
        let numeroBusqueda = 0;        // descarta respuestas que llegan fuera de orden
        let resultadosActuales = [];
        let consultaMostrada = null;   // consulta a la que pertenecen los resultados visibles
        let indiceActivo = -1;

        async function consultarProductos(q) {
            const res = await fetch(`buscar_producto.php?q=${encodeURIComponent(q)}`);
            const productos = await res.json();
            return Array.isArray(productos) ? productos : [];
        }

        inputBuscar.addEventListener('input', () => {
            clearTimeout(temporizadorBusqueda);
            const q = inputBuscar.value.trim();
            if (q.length < MIN_CARACTERES) {
                numeroBusqueda++;
                ocultarResultados();
                return;
            }
            temporizadorBusqueda = setTimeout(() => sugerir(q), ESPERA_MS);
        });

        inputBuscar.addEventListener('focus', () => {
            const q = inputBuscar.value.trim();
            if (q.length >= MIN_CARACTERES && q === consultaMostrada) mostrarResultados();
        });

        inputBuscar.addEventListener('keydown', (e) => {
            const abiertos = resultadosBusqueda.style.display !== 'none' && resultadosActuales.length > 0;
            if (e.key === 'ArrowDown' && abiertos) {
                e.preventDefault();
                activarResultado((indiceActivo + 1) % resultadosActuales.length);
            } else if (e.key === 'ArrowUp' && abiertos) {
                e.preventDefault();
                activarResultado((indiceActivo - 1 + resultadosActuales.length) % resultadosActuales.length);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                buscarProducto();
            } else if (e.key === 'Escape') {
                ocultarResultados();
            }
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.search-box')) ocultarResultados();
        });

        async function sugerir(q) {
            const numero = ++numeroBusqueda;
            buscando(true);
            let productos;
            try {
                productos = await consultarProductos(q);
            } catch (error) {
                if (numero !== numeroBusqueda) return;
                buscando(false);
                pintarMensaje('No se pudo conectar con el servidor.');
                return;
            }
            if (numero !== numeroBusqueda) return;
            buscando(false);
            pintarResultados(q, productos);
        }

        // Enter o botón "Buscar producto"
        async function buscarProducto() {
            clearTimeout(temporizadorBusqueda);
            const q = inputBuscar.value.trim();
            if (!q) {
                inputBuscar.focus();
                return;
            }

            // Si la lista visible corresponde a lo escrito, se agrega el resaltado
            if (q === consultaMostrada && resultadosActuales[indiceActivo]) {
                seleccionarResultado(resultadosActuales[indiceActivo]);
                return;
            }

            const numero = ++numeroBusqueda;
            buscando(true);
            let productos;
            try {
                productos = await consultarProductos(q);
            } catch (error) {
                buscando(false);
                alert('No se pudo conectar con el servidor para buscar el producto.');
                return;
            }
            if (numero !== numeroBusqueda) return;
            buscando(false);

            const exacto = productos.find(p => String(p.codigo).toLowerCase() === q.toLowerCase());
            if (productos.length === 1 || exacto) {
                seleccionarResultado(exacto || productos[0]);
                return;
            }
            pintarResultados(q, productos);
        }

        function pintarResultados(q, productos) {
            resultadosActuales = productos;
            consultaMostrada = q;

            if (productos.length === 0) {
                pintarMensaje(`Sin resultados para "${q}".`);
                return;
            }

            resultadosBusqueda.innerHTML = '';
            productos.forEach((p, i) => {
                const item = document.createElement('div');
                item.className = 'result-item';
                item.id = `resultado-${i}`;
                item.setAttribute('role', 'option');

                const thumb = document.createElement('img');
                thumb.className = 'result-thumb';
                thumb.alt = '';
                if (p.imagen) thumb.src = `../${p.imagen}`;
                else thumb.style.visibility = 'hidden';

                const info = document.createElement('div');
                info.className = 'result-info';
                const nombre = document.createElement('div');
                nombre.className = 'result-nombre';
                nombre.appendChild(resaltarCoincidencia(p.nombre, q));
                const detalle = document.createElement('small');
                detalle.append('Clave: ', resaltarCoincidencia(String(p.codigo), q), ' · ');
                const stock = document.createElement('span');
                if (Number(p.stock) > 0) {
                    stock.textContent = `Existencias: ${p.stock}`;
                } else {
                    stock.className = 'agotado';
                    stock.textContent = 'Agotado';
                }
                detalle.appendChild(stock);
                info.append(nombre, detalle);

                const precio = document.createElement('div');
                precio.className = 'result-precio';
                precio.textContent = formatoDinero(Number(p.precio) || 0);

                item.append(thumb, info, precio);
                item.addEventListener('mousedown', (e) => e.preventDefault()); // no quitar el foco del input
                item.addEventListener('mouseenter', () => activarResultado(i, false));
                item.addEventListener('click', () => seleccionarResultado(p));
                resultadosBusqueda.appendChild(item);
            });

            // Resalta la clave exacta si la hay; si no, el primero
            const exacto = productos.findIndex(p => String(p.codigo).toLowerCase() === q.toLowerCase());
            activarResultado(exacto >= 0 ? exacto : 0);
            mostrarResultados();
        }

        function pintarMensaje(texto) {
            resultadosActuales = [];
            indiceActivo = -1;
            const div = document.createElement('div');
            div.className = 'no-results';
            div.textContent = texto;
            resultadosBusqueda.replaceChildren(div);
            mostrarResultados();
        }

        // Devuelve el texto con la parte que coincide envuelta en <mark>
        function resaltarCoincidencia(texto, q) {
            const fragmento = document.createDocumentFragment();
            const pos = texto.toLowerCase().indexOf(q.toLowerCase());
            if (pos === -1) {
                fragmento.append(texto);
                return fragmento;
            }
            const mark = document.createElement('mark');
            mark.textContent = texto.slice(pos, pos + q.length);
            fragmento.append(texto.slice(0, pos), mark, texto.slice(pos + q.length));
            return fragmento;
        }

        function activarResultado(i, desplazar = true) {
            indiceActivo = i;
            resultadosBusqueda.querySelectorAll('.result-item').forEach((el, j) => {
                el.classList.toggle('active', j === i);
                el.setAttribute('aria-selected', j === i ? 'true' : 'false');
            });
            const activo = document.getElementById(`resultado-${i}`);
            if (activo) {
                inputBuscar.setAttribute('aria-activedescendant', activo.id);
                if (desplazar) activo.scrollIntoView({ block: 'nearest' });
            }
        }

        function seleccionarResultado(producto) {
            agregarAlCarrito(producto);
            cerrarResultados();
        }

        function buscando(activo) {
            document.querySelector('.search-box').classList.toggle('cargando', activo);
        }

        function mostrarResultados() {
            resultadosBusqueda.style.display = 'block';
            inputBuscar.setAttribute('aria-expanded', 'true');
        }

        function ocultarResultados() {
            resultadosBusqueda.style.display = 'none';
            inputBuscar.setAttribute('aria-expanded', 'false');
        }

        function cerrarResultados() {
            numeroBusqueda++;
            buscando(false);
            ocultarResultados();
            resultadosBusqueda.innerHTML = '';
            resultadosActuales = [];
            consultaMostrada = null;
            indiceActivo = -1;
            inputBuscar.removeAttribute('aria-activedescendant');
            inputBuscar.value = '';
            inputBuscar.focus();
        }

        /* ---------- Carrito ---------- */
        const ICONO_BASURA = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>';
        let indiceResaltado = null;

        function agregarAlCarrito(producto) {
            const stock = Number(producto.stock);
            const existente = carrito.find(item => item.id_producto === producto.id_producto);
            if (existente) {
                existente.cantidad += 1;
                existente.stock = stock;
                indiceResaltado = carrito.indexOf(existente);
            } else {
                carrito.push({
                    id_producto: producto.id_producto,
                    codigo: producto.codigo,
                    nombre: producto.nombre,
                    imagen: producto.imagen || null,
                    cantidad: 1,
                    precio: Number(producto.precio) || 0,
                    stock: stock
                });
                indiceResaltado = carrito.length - 1;
            }
            renderCarrito();
        }

        // Cantidad entre 1 y las existencias de la sucursal (el servidor vuelve a validar al cobrar)
        function cambiarCantidad(index, nuevaCantidad) {
            const item = carrito[index];
            let valor = Math.floor(nuevaCantidad);
            if (isNaN(valor) || valor < 1) valor = 1;
            if (item.stock >= 1 && valor > item.stock) valor = item.stock;
            item.cantidad = valor;
            indiceResaltado = index;
            renderCarrito();
        }

        function crearBotonCantidad(texto, etiqueta, onClick) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'qty-btn';
            btn.textContent = texto;
            btn.title = etiqueta;
            btn.setAttribute('aria-label', etiqueta);
            btn.addEventListener('click', onClick);
            return btn;
        }

        function renderCarrito() {
            const tbody = document.getElementById('listaProductos');
            tbody.innerHTML = '';

            if (carrito.length === 0) {
                tbody.innerHTML = '<tr class="empty-row"><td colspan="6">Busca un producto para agregarlo a la venta</td></tr>';
                indiceSeleccionado = null;
                indiceResaltado = null;
                calcularTotales();
                return;
            }

            carrito.forEach((item, index) => {
                const row = tbody.insertRow();
                row.dataset.index = index;
                if (index === indiceSeleccionado) row.classList.add('selected');

                const importe = item.cantidad * item.precio;

                row.insertCell().textContent = item.codigo;

                const celdaProducto = row.insertCell();
                const producto = document.createElement('div');
                producto.className = 'producto-celda';
                if (item.imagen) {
                    const img = document.createElement('img');
                    img.className = 'producto-thumb';
                    img.src = `../${item.imagen}`;
                    img.alt = '';
                    producto.appendChild(img);
                }
                producto.appendChild(document.createTextNode(item.nombre));
                celdaProducto.appendChild(producto);

                // Selector de cantidad: [−] [n] [+]. Con 1 unidad, "−" ofrece quitar el producto.
                const stepper = document.createElement('div');
                stepper.className = 'qty-stepper';

                const btnMenos = crearBotonCantidad('−',
                    item.cantidad <= 1 ? 'Quitar producto' : 'Quitar uno',
                    () => item.cantidad <= 1 ? pedirEliminarProducto(index) : cambiarCantidad(index, item.cantidad - 1));
                if (item.cantidad <= 1) btnMenos.classList.add('qty-quitar');

                const input = document.createElement('input');
                input.type = 'number';
                input.min = '1';
                input.step = '1';
                if (item.stock >= 1) input.max = item.stock;
                input.value = item.cantidad;
                input.className = 'input-cantidad';
                input.setAttribute('aria-label', `Cantidad de ${item.nombre}`);
                input.addEventListener('click', (e) => e.stopPropagation());
                input.addEventListener('change', (e) => cambiarCantidad(index, parseFloat(e.target.value)));
                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') e.target.blur();
                });

                const btnMas = crearBotonCantidad('+', 'Agregar uno', () => cambiarCantidad(index, item.cantidad + 1));
                if (item.stock >= 1 && item.cantidad >= item.stock) {
                    btnMas.disabled = true;
                    btnMas.title = `Solo hay ${item.stock} en existencia`;
                }

                stepper.append(btnMenos, input, btnMas);
                row.insertCell().appendChild(stepper);

                const celdaPrecio = row.insertCell();
                celdaPrecio.className = 'col-num';
                celdaPrecio.textContent = formatoDinero(item.precio);

                const celdaImporte = row.insertCell();
                celdaImporte.className = 'col-num col-importe';
                celdaImporte.textContent = formatoDinero(importe);

                const btnBorrar = document.createElement('button');
                btnBorrar.type = 'button';
                btnBorrar.className = 'btn-borrar';
                btnBorrar.title = 'Eliminar producto';
                btnBorrar.setAttribute('aria-label', `Eliminar ${item.nombre}`);
                btnBorrar.innerHTML = ICONO_BASURA;
                btnBorrar.addEventListener('click', () => pedirEliminarProducto(index));
                const celdaAcciones = row.insertCell();
                celdaAcciones.className = 'col-acciones';
                celdaAcciones.appendChild(btnBorrar);

                if (index === indiceResaltado) row.classList.add('bump');

                row.addEventListener('click', () => seleccionarFila(index));
            });

            indiceResaltado = null;
            calcularTotales();
        }

        function seleccionarFila(index) {
            indiceSeleccionado = index;
            document.querySelectorAll('#listaProductos tr').forEach(tr => {
                tr.classList.toggle('selected', Number(tr.dataset.index) === index);
            });
        }

        /* ---------- Eliminar (con confirmación) ---------- */
        function pedirEliminarProducto(index) {
            const item = carrito[index];
            if (!item) return;
            seleccionarFila(index);

            const tarjeta = document.createElement('div');
            tarjeta.className = 'eliminar-producto';
            if (item.imagen) {
                const img = document.createElement('img');
                img.src = `../${item.imagen}`;
                img.alt = '';
                tarjeta.appendChild(img);
            }
            const info = document.createElement('div');
            const nombre = document.createElement('strong');
            nombre.textContent = item.nombre;
            const detalle = document.createElement('small');
            detalle.textContent = `${item.cantidad} × ${formatoDinero(item.precio)} = ${formatoDinero(item.cantidad * item.precio)}`;
            info.append(nombre, detalle);
            tarjeta.appendChild(info);

            abrirConfirmacion('¿Eliminar producto?', tarjeta, 'Sí, eliminar', () => quitarProducto(index));
        }

        function eliminarFila() {
            if (indiceSeleccionado === null || !carrito[indiceSeleccionado]) {
                alert('Selecciona una fila primero haciendo clic sobre ella.');
                return;
            }
            pedirEliminarProducto(indiceSeleccionado);
        }

        function limpiarTabla() {
            if (carrito.length === 0) return;
            const articulos = carrito.reduce((n, i) => n + i.cantidad, 0);
            const mensaje = document.createElement('p');
            mensaje.className = 'modal-mensaje';
            mensaje.textContent = `Se quitarán ${carrito.length} producto(s) (${articulos} artículo(s)) de la venta actual.`;

            abrirConfirmacion('¿Vaciar la venta?', mensaje, 'Sí, vaciar', () => {
                carrito = [];
                indiceSeleccionado = null;
                renderCarrito();
                inputBuscar.focus();
            });
        }

        function abrirConfirmacion(titulo, contenido, textoBoton, accion) {
            document.getElementById('tituloEliminar').textContent = titulo;
            document.getElementById('eliminarContenido').replaceChildren(contenido);
            document.getElementById('btnConfirmarEliminar').textContent = textoBoton;
            accionEliminar = accion;
            abrirModal('modalEliminar');
            // Foco en "Sí, eliminar": Enter confirma, Escape cancela
            document.getElementById('btnConfirmarEliminar').focus();
        }

        function confirmarEliminar() {
            cerrarModal('modalEliminar');
            const accion = accionEliminar;
            accionEliminar = null;
            if (accion) accion();
        }

        // Anima la salida de la fila y luego la quita del carrito
        function quitarProducto(index) {
            const fila = document.querySelector(`#listaProductos tr[data-index="${index}"]`);
            const quitar = () => {
                carrito.splice(index, 1);
                if (indiceSeleccionado === index) indiceSeleccionado = null;
                else if (indiceSeleccionado !== null && indiceSeleccionado > index) indiceSeleccionado--;
                renderCarrito();
            };
            if (!fila) return quitar();
            fila.classList.add('removing');
            setTimeout(quitar, 200);
        }

        // Supr / ⌫ elimina la fila seleccionada (si no se está escribiendo en un campo)
        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Delete' && e.key !== 'Backspace') return;
            if (e.target.closest('input, textarea, select, [contenteditable="true"]')) return;
            if (document.querySelector('.modal-overlay.open')) return;
            if (indiceSeleccionado === null || !carrito[indiceSeleccionado]) return;
            e.preventDefault();
            pedirEliminarProducto(indiceSeleccionado);
        });

        // Los precios ya incluyen IVA (LFPC art. 7 bis: el precio exhibido es el
        // total a pagar). El IVA solo se desglosa hacia atrás para el ticket.
        // Mismo cálculo en centavos que registrar_venta.php.
        function calcularTotales() {
            let totalCentavos = 0;

            carrito.forEach(item => {
                totalCentavos += aCentavos(item.precio) * item.cantidad;
            });

            const subtotalCentavos = Math.round(totalCentavos / 1.16);
            const subtotal = subtotalCentavos / 100;
            const impuestos = (totalCentavos - subtotalCentavos) / 100;
            totalActual = totalCentavos / 100;

            document.getElementById('totalArticulos').innerText = carrito.reduce((n, i) => n + i.cantidad, 0);
            document.getElementById('lblSubtotal').innerText = formatoDinero(subtotal);
            document.getElementById('lblImpuestos').innerText = formatoDinero(impuestos);
            document.getElementById('lblTotal').innerText = formatoDinero(totalActual);
        }

        /* ---------- Pago ---------- */
        function abrirModalPago() {
            if (carrito.length === 0 || totalActual <= 0) {
                alert('No hay productos en la lista de cobro.');
                return;
            }
            document.getElementById('modalTotal').innerText = formatoDinero(totalActual);
            abrirModal('modalPago');
        }

        function abrirModalEfectivo() {
            cerrarModal('modalPago');
            document.getElementById('efectivoTotal').innerText = formatoDinero(totalActual);
            inputRecibido.value = '';
            actualizarCambioPreview();
            abrirModal('modalEfectivo');
            inputRecibido.focus();
        }

        function volverAMetodos() {
            cerrarModal('modalEfectivo');
            abrirModal('modalPago');
        }

        // Muestra en vivo cuánto falta o cuánto cambio hay que dar
        function actualizarCambioPreview() {
            const preview = document.getElementById('cambioPreview');
            const btnCobrar = document.getElementById('btnCobrarEfectivo');
            const recibido = parseFloat(inputRecibido.value);

            preview.classList.remove('error');

            if (isNaN(recibido)) {
                preview.textContent = '';
                btnCobrar.disabled = true;
                return;
            }

            const diferencia = aCentavos(recibido) - aCentavos(totalActual);
            if (diferencia < 0) {
                preview.textContent = `Faltan ${formatoDinero(-diferencia / 100)}`;
                preview.classList.add('error');
                btnCobrar.disabled = true;
            } else {
                preview.innerHTML = `Cambio: <strong>${formatoDinero(diferencia / 100)}</strong>`;
                btnCobrar.disabled = false;
            }
        }

        inputRecibido.addEventListener('input', actualizarCambioPreview);
        inputRecibido.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !document.getElementById('btnCobrarEfectivo').disabled) cobrarEfectivo();
        });

        // Guarda la venta en la BD. Devuelve la respuesta del servidor o null si falló.
        async function registrarVenta(metodo, efectivoRecibido = null) {
            const datos = new FormData();
            datos.append('csrf_token', csrfToken);
            datos.append('metodo', metodo);
            datos.append('items', JSON.stringify(
                carrito.map(i => ({ id_producto: i.id_producto, cantidad: i.cantidad }))
            ));
            if (efectivoRecibido !== null) datos.append('efectivo_recibido', efectivoRecibido.toFixed(2));

            try {
                const res = await fetch('registrar_venta.php', { method: 'POST', body: datos });
                const json = await res.json();
                if (!json.success) {
                    alert(json.message || 'No se pudo registrar la venta.');
                    return null;
                }
                return json;
            } catch (error) {
                alert('No se pudo conectar con el servidor. La venta NO se registró.');
                return null;
            }
        }

        function bloquearCobro(bloquear) {
            cobrando = bloquear;
            document.querySelectorAll('#modalPago .metodo-btn, #btnCobrarEfectivo').forEach(b => {
                b.disabled = bloquear;
            });
            document.getElementById('btnCobrarEfectivo').textContent = bloquear ? 'Guardando...' : 'Cobrar';
            if (!bloquear) actualizarCambioPreview();
        }

        async function cobrarEfectivo() {
            if (cobrando) return;
            const recibido = parseFloat(inputRecibido.value);
            if (isNaN(recibido) || aCentavos(recibido) < aCentavos(totalActual)) return;

            bloquearCobro(true);
            const venta = await registrarVenta('Efectivo', recibido);
            bloquearCobro(false);
            if (!venta) return;

            cerrarModal('modalEfectivo');
            mostrarVentaCompletada('Efectivo', venta);
        }

        async function pagarConTarjeta() {
            if (cobrando) return;

            bloquearCobro(true);
            const venta = await registrarVenta('Tarjeta');
            bloquearCobro(false);
            if (!venta) return;

            cerrarModal('modalPago');
            mostrarVentaCompletada('Tarjeta', venta);
        }

        function mostrarVentaCompletada(metodo, venta) {
            document.getElementById('ventaMensaje').textContent =
                `Venta #${venta.id_venta} registrada por ${formatoDinero(venta.total)} con ${metodo}.`;

            const bloqueCambio = document.getElementById('ventaCambio');
            if (venta.cambio !== null) {
                document.getElementById('ventaCambioMonto').textContent = formatoDinero(venta.cambio);
                document.getElementById('ventaCambioDetalle').textContent =
                    `Recibido: ${formatoDinero(venta.recibido)} · Total: ${formatoDinero(venta.total)}`;
                bloqueCambio.style.display = 'block';
            } else {
                bloqueCambio.style.display = 'none';
            }

            abrirModal('modalVenta');
            document.getElementById('btnFinalizarVenta').focus();
        }

        function finalizarVenta() {
            cerrarModal('modalVenta');
            carrito = [];
            indiceSeleccionado = null;
            renderCarrito();
            inputBuscar.focus();
        }

        renderCarrito();
    </script>
</body>
</html>
