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

$stmt = $pdo->prepare("
    SELECT p.id_producto, p.nombre, p.precio, p.imagen, COALESCE(SUM(ip.cantidad_disponible), 0) AS stock
    FROM Productos p
    LEFT JOIN Inventario_Sucursal ip ON ip.id_producto = p.id_producto
    WHERE (p.id_producto = ? OR p.nombre LIKE ?) AND p.estado = 'Activo'
    GROUP BY p.id_producto, p.nombre, p.precio, p.imagen
    ORDER BY p.nombre
    LIMIT 20
");
$stmt->execute([ctype_digit($q) ? (int) $q : 0, "%$q%"]);

echo json_encode($stmt->fetchAll());
