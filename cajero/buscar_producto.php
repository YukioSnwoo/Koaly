<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['id_usuario']) || (int) $_SESSION['id_rol'] !== 3) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/../admin/database.php';

$q = trim($_GET['q'] ?? '');

if ($q === '') {
    echo json_encode([]);
    exit;
}

// Existencias solo de la sucursal del cajero: es de ahí de donde se descuenta la venta
$stmt = $pdo->prepare("SELECT id_sucursal FROM Usuarios WHERE id_usuario = ? LIMIT 1");
$stmt->execute([$_SESSION['id_usuario']]);
$idSucursal = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT p.id_producto, p.codigo, p.nombre, p.precio, p.imagen, COALESCE(ip.cantidad_disponible, 0) AS stock
    FROM Productos p
    LEFT JOIN Inventario_Sucursal ip ON ip.id_producto = p.id_producto AND ip.id_sucursal = ?
    WHERE (p.codigo LIKE ? OR p.nombre LIKE ?) AND p.estado = 'Activo'
    ORDER BY p.nombre
    LIMIT 20
");
$stmt->execute([$idSucursal, "%$q%", "%$q%"]);

echo json_encode($stmt->fetchAll());
