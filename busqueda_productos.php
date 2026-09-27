<?php
session_start();

if (!isset($_SESSION['id_usuario']) || (int)($_SESSION['id_rol'] ?? 0) !== 3) {
    header('Location: ../index.php');
    exit;
}

$nombreUsuario = $_SESSION['nombre'] ?? 'Cajero';
$idSucursal = $_SESSION['id_sucursal'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Koaly - Búsqueda de productos</title>
    <link rel="stylesheet" href="busqueda_productos.css">
</head>
<body>
<header class="topbar">
    <div class="brand">Koaly</div>
    <div class="store-info">Punto de venta</div>
    <div class="cashier-info">
        <span><?= htmlspecialchars($nombreUsuario, ENT_QUOTES, 'UTF-8') ?></span>
        <small>Sucursal: <?= htmlspecialchars((string)$idSucursal, ENT_QUOTES, 'UTF-8') ?></small>
    </div>
    <div class="avatar">IMG</div>
</header>

<main class="pos-layout">
    <aside class="sidebar">
        <button class="side-button active" type="button" data-action="search">Buscar producto</button>
        <button class="side-button" type="button" data-action="key">Buscar Producto (ID)</button>
        <button class="side-button" type="button" data-action="coupon">Cupón</button>
        <button class="side-button" type="button" data-action="points">Tarjeta de puntos</button>

        <div class="side-spacer"></div>

        <button class="danger-button" id="btnEliminar" type="button">Eliminar</button>
        <button class="orange-button" id="btnLimpiar" type="button">Limpiar</button>
    </aside>

    <section class="catalog-panel">
        <div class="catalog-heading">
            <div>
                <span class="eyebrow">Búsqueda de productos</span>
                <h1>Productos</h1>
            </div>

            <form id="searchForm" class="catalog-search" role="search">
                <input id="inputBuscar"
                       type="search"
                       placeholder="Buscar producto, clave o ID"
                       autocomplete="off">
                <button type="submit">Buscar</button>
            </form>
        </div>

        <p class="catalog-message" id="mensajeBusqueda">
            Escribe un nombre, clave o ID para buscar.
        </p>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Clave</th>
                        <th>Producto</th>
                        <th>Categoría</th>
                        <th>Cantidad</th>
                        <th>Precio</th>
                        <th>Descuento</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody id="tbodyProductos">
                    <tr>
                        <td colspan="7" class="empty-row">
                            Realiza una búsqueda para mostrar productos.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="table-footer">
            <div class="info-pill">
                Productos encontrados: <strong id="totalProductos">0</strong>
            </div>
        </div>
    </section>

    <aside class="checkout-panel">
        <h2>Precios</h2>

        <div class="cart-list" id="cartList">
            <p class="cart-empty">Selecciona un producto</p>
        </div>

        <div class="totals">
            <div><span>Subtotal:</span><strong id="subtotal">$0.00</strong></div>
            <div><span>Descuento:</span><strong id="discount">$0.00</strong></div>
            <div><span>Impuestos:</span><strong id="tax">$0.00</strong></div>
            <div class="savings"><span>Te ahorras:</span><strong id="savings">$0.00</strong></div>
            <div class="total"><span>Total:</span><strong id="total">$0.00</strong></div>
        </div>

        <button class="pay-button" type="button" id="payButton">Pagar</button>
    </aside>
</main>

<div class="toast" id="toast" role="status"></div>

<script src="busqueda_productos.js"></script>
</body>
</html>
