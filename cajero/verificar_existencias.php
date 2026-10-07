<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['id_usuario']) || (int) $_SESSION['id_rol'] !== 3) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/../admin/database.php';

// ids=1,2,3 → existencias actuales de esos productos en la sucursal del cajero.
// Se consulta justo antes de cobrar porque las del carrito pueden estar desactualizadas.
$ids = array_values(array_unique(array_filter(
    array_map('intval', explode(',', $_GET['ids'] ?? '')),
    fn($id) => $id > 0
)));

if (count($ids) === 0 || count($ids) > 200) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("SELECT id_sucursal FROM Usuarios WHERE id_usuario = ? LIMIT 1");
$stmt->execute([$_SESSION['id_usuario']]);
$idSucursal = (int) $stmt->fetchColumn();

// Un producto inactivo no aparece en la respuesta: el cliente lo trata como agotado
$marcas = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("
    SELECT p.id_producto, COALESCE(ip.cantidad_disponible, 0) AS stock
    FROM Productos p
    LEFT JOIN Inventario_Sucursal ip ON ip.id_producto = p.id_producto AND ip.id_sucursal = ?
    WHERE p.id_producto IN ($marcas) AND p.estado = 'Activo'
");
$stmt->execute(array_merge([$idSucursal], $ids));

echo json_encode($stmt->fetchAll());
