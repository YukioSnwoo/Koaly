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
            grid-template-columns: 220px 1fr 300px;
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
            border: 2px solid #8b71d0; /* Borde de acento como en el prototipo */
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

        tr.selected {
            background-color: #639264 !important;
            color: white;
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
                <button class="btn-green">Buscar producto</button>
                <button class="btn-green" onclick="agregarProductoPorID()">Buscar Producto (ID)</button>
                <button class="btn-green">Cupón</button>
                <button class="btn-green">Agregar tarjeta de puntos</button>
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
                        <th>Cantidad</th>
                        <th>Precio</th>
                        <th>Descuento</th>
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
                <div class="summary-header">Precios</div>
                <div id="desglosePrecios">
                    <div class="pill-field"><span id="precioMain">$0.00</span></div>
                </div>
            </div>

            <div class="summary-totals">
                <div class="pill-field">Subtotal: <span id="lblSubtotal">$0.00</span></div>
                <div class="pill-field">Descuento: <span id="lblDescuento">$0.00</span></div>
                <div class="pill-field">Impuestos (IVA): <span id="lblImpuestos">$0.00</span></div>
                <div class="pill-field">Te ahorraste: <span id="lblAhorro">$0.00</span></div>
                <div class="pill-field" style="background-color: #d1d5db; font-size: 1rem; color: #111;">
                    Total: <span id="lblTotal">$0.00</span>
                </div>
                <button class="btn-pay" onclick="procesarPago()">Pagar</button>
            </div>
        </div>

    </div>

    <script src="../auth.js"></script>
    <script>
        // Verificación de autenticación de tu código base
        const usuario = requireRol(3);
        if (usuario) {
            document.getElementById('userName').textContent = usuario.nombre || 'Cajero';
            document.getElementById('sucursalName').textContent = usuario.id_sucursal || 'Sin asignar';
        }

        let filaSeleccionada = null;

        // Agregar producto por ID (Demostración de interacción)
        function agregarProductoPorID() {
            const id = prompt("Ingrese la clave o ID del producto:");
            if (!id) return;

            const tbody = document.getElementById('listaProductos');
            const row = tbody.insertRow();
            
            // Datos de prueba asignados dinámicamente
            const precioBase = 5600.00;
            const descuentoPct = 0;

            row.innerHTML = `
                <td>${id}</td>
                <td>1. uni</td>
                <td>$ ${precioBase.toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                <td>%${descuentoPct}</td>
            `;
            
            row.onclick = function() {
                if (filaSeleccionada) filaSeleccionada.classList.remove('selected');
                this.classList.add('selected');
                filaSeleccionada = this;
            };

            calcularTotales();
        }

        // Eliminar producto seleccionado
        function eliminarFila() {
            if (filaSeleccionada) {
                filaSeleccionada.remove();
                filaSeleccionada = null;
                calcularTotales();
            } else {
                alert("Selecciona una fila primero haciendo clic sobre ella.");
            }
        }

        // Limpiar toda la lista
        function limpiarTabla() {
            document.getElementById('listaProductos').innerHTML = '';
            filaSeleccionada = null;
            calcularTotales();
        }

        // Cálculos dinámicos
        function calcularTotales() {
            const filas = document.querySelectorAll('#listaProductos tr');
            let subtotal = 0;

            filas.forEach(f => {
                subtotal += 5600.00; // Valor base de la prueba
            });

            const descuento = 0.00;
            const impuestos = subtotal * 0.16;
            const total = subtotal + impuestos - descuento;

            document.getElementById('precioMain').innerText = `$${subtotal.toFixed(2)}`;
            document.getElementById('lblSubtotal').innerText = `$${subtotal.toFixed(2)}`;
            document.getElementById('lblDescuento').innerText = `$${descuento.toFixed(2)}`;
            document.getElementById('lblImpuestos').innerText = `$${impuestos.toFixed(2)}`;
            document.getElementById('lblAhorro').innerText = `$${descuento.toFixed(2)}`;
            document.getElementById('lblTotal').innerText = `$${total.toFixed(2)}`;
        }

        function procesarPago() {
            const total = document.getElementById('lblTotal').innerText;
            if (total === "$0.00") {
                alert("No hay productos en la lista de cobro.");
                return;
            }
            alert(`Procesando venta por un total de ${total}`);
        }
    </script>
</body>
</html>