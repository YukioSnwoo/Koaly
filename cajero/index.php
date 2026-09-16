<?php
session_start();

if (!isset($_SESSION['id_usuario']) || (int) $_SESSION['id_rol'] !== 3) {
    header('Location: ../index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Punto de Venta - Koaly</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }

        body {
            background-color: #d1d5db;
            padding: 15px;
            color: #333;
        }

        /* --- ENCABEZADO SUPERIOR --- */
        .top-bar {
            display: grid;
            grid-template-columns: 200px 1fr 280px;
            gap: 15px;
            margin-bottom: 15px;
        }

        .header-box {
            background-color: #527d53;
            color: white;
            padding: 12px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .header-logo { font-size: 1.5rem; font-weight: bold; }
        .header-user { justify-content: space-between; padding: 8px 15px; }
        .user-avatar {
            width: 38px;
            height: 38px;
            background: #2d4d2e;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
        }

        /* --- CONTENEDOR PRINCIPAL --- */
        .main-layout {
            display: grid;
            grid-template-columns: 260px 1fr 300px;
            gap: 15px;
            height: calc(100vh - 100px);
        }

        /* --- PANEL IZQUIERDO (BOTONES) --- */
        .sidebar {
            background-color: #527d53;
            border-radius: 12px;
            padding: 15px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .btn-group-top, .btn-group-bottom {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 10px 12px;
            border-radius: 12px;
            border: 1px solid #7cb07d;
            font-size: 0.9rem;
            margin-bottom: 8px;
        }

        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.25);
            z-index: 10;
            max-height: 260px;
            overflow-y: auto;
        }

        .search-results .result-item {
            padding: 10px 12px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
            color: #333;
        }

        .search-results .result-item:last-child { border-bottom: none; }
        .search-results .result-item:hover { background: #e5f0e5; }
        .search-results .result-item small { display: block; color: #6b7280; }
        .search-results .no-results { padding: 10px 12px; color: #6b7280; font-size: 0.85rem; }

        .btn-green {
            background-color: #639264;
            color: white;
            border: 1px solid #7cb07d;
            padding: 12px 10px;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: background 0.2s;
            text-align: center;
            width: 100%;
        }

        .btn-green:hover { background-color: #436944; }

        .btn-danger {
            background-color: #8a252a;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 12px;
            cursor: pointer;
            font-weight: bold;
        }

        .btn-warning {
            background-color: #f56e11;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 12px;
            cursor: pointer;
            font-weight: bold;
        }

        /* --- PANEL CENTRAL (TABLA DE PRODUCTOS) --- */
        .table-container {
            background-color: white;
            border-radius: 12px;
            padding: 10px;
            border: 2px solid #8b71d0;
            overflow-y: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background-color: #527d53;
            color: white;
            padding: 10px;
            border-radius: 10px;
            font-size: 0.95rem;
        }

        td {
            padding: 10px;
            text-align: center;
            border-bottom: 1px solid #e5e7eb;
            font-size: 0.9rem;
        }

        td.producto-nombre { text-align: left; font-weight: 600; }

        td input {
            width: 70px;
            padding: 6px;
            text-align: center;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.9rem;
        }

        tr.selected {
            background-color: #639264 !important;
            color: white;
        }

        tr.empty-row td {
            color: #9ca3af;
            padding: 30px 10px;
        }

        /* --- PANEL DERECHO (RESUMEN Y COBRO) --- */
        .summary-panel {
            background-color: #f3f4f6;
            border-radius: 12px;
            padding: 15px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .summary-header {
            background-color: #527d53;
            color: white;
            text-align: center;
            padding: 10px;
            border-radius: 12px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .pill-field {
            background-color: #e5e0e0;
            border-radius: 10px;
            padding: 8px 15px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            font-weight: 600;
            font-size: 0.9rem;
            color: #4b5563;
        }

        .btn-pay {
            background-color: #527d53;
            color: white;
            border: none;
            padding: 15px;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            margin-top: 10px;
        }

        .btn-pay:hover { background-color: #436944; }

        /* --- MODAL DE PAGO --- */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
            z-index: 100;
        }

        .modal-overlay.open { display: flex; }

        .modal-box {
            background: white;
            border-radius: 14px;
            padding: 25px;
            width: 320px;
            text-align: center;
        }

        .modal-box h2 { margin-bottom: 5px; color: #1f2937; }
        .modal-box .modal-total { font-size: 1.4rem; font-weight: bold; color: #527d53; margin-bottom: 20px; }

        .modal-payment-options {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }

        .modal-payment-options button {
            flex: 1;
            padding: 18px 10px;
            border-radius: 12px;
            border: 2px solid #527d53;
            background: white;
            color: #527d53;
            font-weight: bold;
            cursor: pointer;
            font-size: 0.95rem;
        }

        .modal-payment-options button:hover {
            background: #527d53;
            color: white;
        }

        .modal-cancel {
            background: none;
            border: none;
            color: #6b7280;
            cursor: pointer;
            font-size: 0.85rem;
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <!-- Encabezado -->
    <div class="top-bar">
        <div class="header-box header-logo">Koaly</div>
        <div class="header-box">Tel. Oficina 00 0000 0000</div>
        <div class="header-box header-user">
            <div>
                <div id="userName" style="font-size: 0.85rem;">Cajero</div>
                <div style="font-size: 0.7rem; opacity: 0.8;">Sucursal: <span id="sucursalName">-</span></div>
            </div>
            <div class="user-avatar" onclick="logout()" style="cursor:pointer;" title="Cerrar sesión">IMG</div>
        </div>
    </div>

    <!-- Panel Principal -->
    <div class="main-layout">

        <!-- Lateral Izquierdo -->
        <div class="sidebar">
            <div class="btn-group-top">
                <div class="search-box">
                    <input type="text" id="inputBuscar" placeholder="Buscar por nombre o clave...">
                    <button class="btn-green" onclick="buscarProducto()">Buscar producto</button>
                    <div id="resultadosBusqueda" class="search-results" style="display:none;"></div>
                </div>
            </div>
            <div class="btn-group-bottom">
                <button class="btn-danger" onclick="eliminarFila()">Eliminar</button>
                <button class="btn-warning" onclick="limpiarTabla()">Limpiar</button>
            </div>
        </div>

        <!-- Tabla Central -->
        <div class="table-container">
            <table id="tablaVenta">
                <thead>
                    <tr>
                        <th>Clave</th>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Precio</th>
                        <th>Importe</th>
                    </tr>
                </thead>
                <tbody id="listaProductos">
                    <!-- Filas dinámicas -->
                </tbody>
            </table>
        </div>

        <!-- Lateral Derecho (Totales) -->
        <div class="summary-panel">
            <div>
                <div class="summary-header">Resumen</div>
                <div id="desglosePrecios">
                    <div class="pill-field">Artículos: <span id="totalArticulos">0</span></div>
                </div>
            </div>

            <div class="summary-totals">
                <div class="pill-field">Subtotal: <span id="lblSubtotal">$0.00</span></div>
                <div class="pill-field">Impuestos (IVA): <span id="lblImpuestos">$0.00</span></div>
                <div class="pill-field" style="background-color: #d1d5db; font-size: 1rem; color: #111;">
                    Total: <span id="lblTotal">$0.00</span>
                </div>
                <button class="btn-pay" onclick="abrirModalPago()">Pagar</button>
            </div>
        </div>

    </div>

    <!-- Modal de método de pago -->
    <div class="modal-overlay" id="modalPago">
        <div class="modal-box">
            <h2>Método de pago</h2>
            <div class="modal-total" id="modalTotal">$0.00</div>
            <div class="modal-payment-options">
                <button onclick="procesarPago('Efectivo')">💵 Efectivo</button>
                <button onclick="procesarPago('Tarjeta')">💳 Tarjeta</button>
            </div>
            <button class="modal-cancel" onclick="cerrarModalPago()">Cancelar</button>
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

        const inputBuscar = document.getElementById('inputBuscar');
        const resultadosBusqueda = document.getElementById('resultadosBusqueda');

        inputBuscar.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') buscarProducto();
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.search-box')) {
                resultadosBusqueda.style.display = 'none';
            }
        });

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
                item.style.display = 'flex';
                item.style.alignItems = 'center';
                item.style.gap = '10px';

                const thumb = document.createElement('img');
                thumb.src = p.imagen ? `../${p.imagen}` : '';
                thumb.alt = '';
                thumb.style.cssText = 'width:36px;height:36px;border-radius:6px;object-fit:cover;background:#eee;flex-shrink:0;';
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

                const thumbHtml = item.imagen
                    ? `<img src="../${item.imagen}" alt="" style="width:32px;height:32px;border-radius:6px;object-fit:cover;vertical-align:middle;margin-right:8px;">`
                    : '';

                row.innerHTML = `
                    <td>${item.codigo}</td>
                    <td class="producto-nombre">${thumbHtml}${item.nombre}</td>
                    <td><input type="number" min="1" step="1" value="${item.cantidad}" data-field="cantidad"></td>
                    <td>$${item.precio.toFixed(2)}</td>
                    <td>$${importe.toFixed(2)}</td>
                `;

                row.querySelectorAll('input').forEach(input => {
                    input.addEventListener('click', (e) => e.stopPropagation());
                    input.addEventListener('input', (e) => {
                        const campo = e.target.dataset.field;
                        let valor = parseFloat(e.target.value);
                        if (isNaN(valor) || valor < 0) valor = 0;
                        if (campo === 'cantidad' && valor < 1) valor = 1;
                        carrito[index][campo] = valor;
                        renderCarrito();
                    });
                });

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
            const total = subtotal + impuestos;

            document.getElementById('totalArticulos').innerText = carrito.reduce((n, i) => n + i.cantidad, 0);
            document.getElementById('lblSubtotal').innerText = `$${subtotal.toFixed(2)}`;
            document.getElementById('lblImpuestos').innerText = `$${impuestos.toFixed(2)}`;
            document.getElementById('lblTotal').innerText = `$${total.toFixed(2)}`;
        }

        function abrirModalPago() {
            const total = document.getElementById('lblTotal').innerText;
            if (total === '$0.00') {
                alert('No hay productos en la lista de cobro.');
                return;
            }
            document.getElementById('modalTotal').innerText = total;
            document.getElementById('modalPago').classList.add('open');
        }

        function cerrarModalPago() {
            document.getElementById('modalPago').classList.remove('open');
        }

        function procesarPago(metodo) {
            const total = document.getElementById('lblTotal').innerText;
            alert(`Venta procesada por ${total} con ${metodo}.`);
            cerrarModalPago();
            carrito = [];
            filaSeleccionada = null;
            renderCarrito();
        }

        renderCarrito();
    </script>
</body>
</html>
