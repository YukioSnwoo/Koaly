<?php
session_start();

if (!isset($_SESSION['id_usuario']) || (int) $_SESSION['id_rol'] !== 3) {
    header('Location: ../index.php');
    exit;
}

$verCajeroCss = filemtime(__DIR__ . '/cajero.css');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Punto de Venta - Koaly</title>
    <link rel="stylesheet" href="cajero.css?v=<?= $verCajeroCss ?>">
</head>
<body>

    <!-- Encabezado -->
    <header class="header">
        <h1>Koaly - Punto de Venta</h1>
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
                    <input type="text" id="inputBuscar" placeholder="Buscar por nombre o clave..." autocomplete="off">
                    <button type="button" class="btn btn-primary btn-block" onclick="buscarProducto()">Buscar producto</button>
                    <div id="resultadosBusqueda" class="search-results" style="display:none;"></div>
                </div>
            </div>
            <div class="sidebar-group">
                <button type="button" class="btn btn-danger btn-block" onclick="eliminarFila()">Eliminar</button>
                <button type="button" class="btn btn-warning btn-block" onclick="limpiarTabla()">Limpiar</button>
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
                <div class="resumen-fila"><span>Impuestos (IVA)</span><span id="lblImpuestos">$0.00</span></div>
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
            document.getElementById('sucursalName').textContent = usuario.id_sucursal || 'Sin asignar';
        }

        let carrito = [];
        let filaSeleccionada = null;
        let totalActual = 0;

        const inputBuscar = document.getElementById('inputBuscar');
        const resultadosBusqueda = document.getElementById('resultadosBusqueda');
        const inputRecibido = document.getElementById('inputRecibido');

        const formatoDinero = (n) => `$${n.toFixed(2)}`;
        const aCentavos = (n) => Math.round(n * 100);

        inputBuscar.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') buscarProducto();
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.search-box')) {
                resultadosBusqueda.style.display = 'none';
            }
        });

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
        async function buscarProducto() {
            const q = inputBuscar.value.trim();
            if (!q) {
                inputBuscar.focus();
                return;
            }

            let productos = [];
            try {
                const res = await fetch(`buscar_producto.php?q=${encodeURIComponent(q)}`);
                productos = await res.json();
            } catch (error) {
                alert('No se pudo conectar con el servidor para buscar el producto.');
                return;
            }

            if (!Array.isArray(productos) || productos.length === 0) {
                resultadosBusqueda.innerHTML = '<div class="no-results">No se encontraron productos.</div>';
                resultadosBusqueda.style.display = 'block';
                return;
            }

            if (productos.length === 1) {
                agregarAlCarrito(productos[0]);
                cerrarResultados();
                return;
            }

            resultadosBusqueda.innerHTML = '';
            productos.forEach(p => {
                const item = document.createElement('div');
                item.className = 'result-item';

                const thumb = document.createElement('img');
                thumb.className = 'result-thumb';
                thumb.src = p.imagen ? `../${p.imagen}` : '';
                thumb.alt = '';
                if (!p.imagen) thumb.style.visibility = 'hidden';

                const info = document.createElement('div');
                const nombre = document.createElement('div');
                nombre.textContent = p.nombre;
                const detalle = document.createElement('small');
                detalle.textContent = `Clave: ${p.codigo} · Existencias: ${p.stock} · $${Number(p.precio).toFixed(2)}`;
                info.appendChild(nombre);
                info.appendChild(detalle);

                item.appendChild(thumb);
                item.appendChild(info);
                item.onclick = () => {
                    agregarAlCarrito(p);
                    cerrarResultados();
                };
                resultadosBusqueda.appendChild(item);
            });
            resultadosBusqueda.style.display = 'block';
        }

        function cerrarResultados() {
            resultadosBusqueda.style.display = 'none';
            resultadosBusqueda.innerHTML = '';
            inputBuscar.value = '';
            inputBuscar.focus();
        }

        /* ---------- Carrito ---------- */
        function agregarAlCarrito(producto) {
            const existente = carrito.find(item => item.id_producto === producto.id_producto);
            if (existente) {
                existente.cantidad += 1;
            } else {
                carrito.push({
                    id_producto: producto.id_producto,
                    codigo: producto.codigo,
                    nombre: producto.nombre,
                    imagen: producto.imagen || null,
                    cantidad: 1,
                    precio: Number(producto.precio) || 0
                });
            }
            renderCarrito();
        }

        function renderCarrito() {
            const tbody = document.getElementById('listaProductos');
            tbody.innerHTML = '';

            if (carrito.length === 0) {
                tbody.innerHTML = '<tr class="empty-row"><td colspan="5">Busca un producto para agregarlo a la venta</td></tr>';
                filaSeleccionada = null;
                calcularTotales();
                return;
            }

            carrito.forEach((item, index) => {
                const row = tbody.insertRow();
                row.dataset.index = index;

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

                const input = document.createElement('input');
                input.type = 'number';
                input.min = '1';
                input.step = '1';
                input.value = item.cantidad;
                input.className = 'input-cantidad';
                input.addEventListener('click', (e) => e.stopPropagation());
                input.addEventListener('input', (e) => {
                    let valor = parseFloat(e.target.value);
                    if (isNaN(valor) || valor < 1) valor = 1;
                    carrito[index].cantidad = valor;
                    renderCarrito();
                });
                row.insertCell().appendChild(input);

                const celdaPrecio = row.insertCell();
                celdaPrecio.className = 'col-num';
                celdaPrecio.textContent = formatoDinero(item.precio);

                const celdaImporte = row.insertCell();
                celdaImporte.className = 'col-num';
                celdaImporte.textContent = formatoDinero(importe);

                row.addEventListener('click', function () {
                    if (filaSeleccionada) filaSeleccionada.classList.remove('selected');
                    this.classList.add('selected');
                    filaSeleccionada = this;
                });

                if (filaSeleccionada && Number(filaSeleccionada.dataset.index) === index) {
                    row.classList.add('selected');
                    filaSeleccionada = row;
                }
            });

            calcularTotales();
        }

        function eliminarFila() {
            if (!filaSeleccionada) {
                alert('Selecciona una fila primero haciendo clic sobre ella.');
                return;
            }
            const index = Number(filaSeleccionada.dataset.index);
            carrito.splice(index, 1);
            filaSeleccionada = null;
            renderCarrito();
        }

        function limpiarTabla() {
            if (carrito.length === 0) return;
            if (!confirm('¿Vaciar toda la lista de productos?')) return;
            carrito = [];
            filaSeleccionada = null;
            renderCarrito();
        }

        function calcularTotales() {
            let subtotal = 0;

            carrito.forEach(item => {
                subtotal += item.cantidad * item.precio;
            });

            const impuestos = subtotal * 0.16;
            totalActual = aCentavos(subtotal + impuestos) / 100;

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

        function cobrarEfectivo() {
            const recibido = parseFloat(inputRecibido.value);
            if (isNaN(recibido) || aCentavos(recibido) < aCentavos(totalActual)) return;

            const cambio = (aCentavos(recibido) - aCentavos(totalActual)) / 100;

            cerrarModal('modalEfectivo');
            mostrarVentaCompletada('Efectivo', {
                recibido,
                cambio
            });
        }

        function pagarConTarjeta() {
            cerrarModal('modalPago');
            mostrarVentaCompletada('Tarjeta');
        }

        function mostrarVentaCompletada(metodo, efectivo = null) {
            document.getElementById('ventaMensaje').textContent =
                `Venta procesada por ${formatoDinero(totalActual)} con ${metodo}.`;

            const bloqueCambio = document.getElementById('ventaCambio');
            if (efectivo) {
                document.getElementById('ventaCambioMonto').textContent = formatoDinero(efectivo.cambio);
                document.getElementById('ventaCambioDetalle').textContent =
                    `Recibido: ${formatoDinero(efectivo.recibido)} · Total: ${formatoDinero(totalActual)}`;
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
            filaSeleccionada = null;
            renderCarrito();
            inputBuscar.focus();
        }

        renderCarrito();
    </script>
</body>
</html>
