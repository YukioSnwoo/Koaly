<?php
/**
 * El cajero quiso agregar a la venta un producto agotado: se avisa al gerente de
 * la sucursal (campanita de notificaciones). POST: id_producto y csrf_token.
 *
 * Se revisan las existencias reales, porque el catálogo de la caja puede tener el
 * dato viejo: si ya hay piezas no se avisa y se devuelve cuántas, para que la caja
 * deje agregarlo. Solo se avisa de productos que la sucursal maneja (tienen fila
 * en Inventario_Sucursal) y siguen activos.
 * Lo de no repetir avisos lo resuelve alertarAgotado() (admin/agotados_comun.php).
 *
 * Respuesta: {ok, avisado, stock}
 */
session_start();

header('Content-Type: application/json');

function responderAgotado(int $codigo, array $datos): void
{
    http_response_code($codigo);
    echo json_encode($datos);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderAgotado(405, ['ok' => false]);
}
if (!isset($_SESSION['id_usuario']) || (int) $_SESSION['id_rol'] !== 3) {
    responderAgotado(401, ['ok' => false]);
}

require_once __DIR__ . '/../admin/database.php';
require_once __DIR__ . '/../admin/csrf.php';
require_once __DIR__ . '/includes/caja.php';
require_once __DIR__ . '/../admin/agotados_comun.php';

usarHoraLocal($pdo);

if (!csrfValido()) {
    responderAgotado(403, ['ok' => false]);
}

// Cajero y sucursal desde la BD, nunca desde el cliente
$cajero = cajeroConCaja($pdo, (int) $_SESSION['id_usuario']);
if ($cajero === null) {
    responderAgotado(403, ['ok' => false]);
}
$idSucursal = (int) $cajero['id_sucursal'];

$idProducto = (int) ($_POST['id_producto'] ?? 0);
if ($idProducto <= 0) {
    responderAgotado(400, ['ok' => false]);
}

$stmt = $pdo->prepare("
    SELECT ip.cantidad_disponible AS stock
    FROM Productos p
    INNER JOIN Inventario_Sucursal ip ON ip.id_producto = p.id_producto AND ip.id_sucursal = ?
    WHERE p.id_producto = ? AND p.estado = 'Activo'
    LIMIT 1
");
$stmt->execute([$idSucursal, $idProducto]);
$stock = $stmt->fetchColumn();
if ($stock === false) {
    responderAgotado(200, ['ok' => true, 'avisado' => false, 'stock' => 0]);
}
$stock = (int) $stock;

try {
    if ($stock > 0) {
        cerrarAlertasAgotados($pdo, $idSucursal, [$idProducto]);
        responderAgotado(200, ['ok' => true, 'avisado' => false, 'stock' => $stock]);
    }
    alertarAgotado($pdo, $idSucursal, $idProducto, 'Intento', (int) $cajero['id_usuario']);
    responderAgotado(200, ['ok' => true, 'avisado' => true, 'stock' => 0]);
} catch (PDOException $e) {
    // Sin sql/019 la caja sigue igual, solo sin avisar
    error_log('avisar_agotado: ' . $e->getMessage());
    responderAgotado(200, ['ok' => false, 'avisado' => false, 'stock' => $stock]);
}
