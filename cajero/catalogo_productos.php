<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['id_usuario']) || (int) $_SESSION['id_rol'] !== 3) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/../admin/database.php';

// Existencias solo de la sucursal del cajero: es de ahí de donde se descuenta la venta
$stmt = $pdo->prepare("SELECT id_sucursal FROM Usuarios WHERE id_usuario = ? LIMIT 1");
$stmt->execute([$_SESSION['id_usuario']]);
$idSucursal = (int) $stmt->fetchColumn();

// Todas las categorías, aunque no tengan productos: el cajero las muestra igual
$categorias = $pdo->query("SELECT id_categoria, nombre_categoria FROM Categorias ORDER BY nombre_categoria")->fetchAll();

// Mismos campos que buscar_producto.php (más la categoría), para que el catálogo
// y el buscador agreguen al carrito el mismo objeto
$stmt = $pdo->prepare("
    SELECT p.id_producto, p.codigo, p.nombre, p.precio, p.imagen, p.id_categoria,
           COALESCE(ip.cantidad_disponible, 0) AS stock
    FROM Productos p
    LEFT JOIN Inventario_Sucursal ip ON ip.id_producto = p.id_producto AND ip.id_sucursal = ?
    WHERE p.estado = 'Activo'
    ORDER BY p.nombre
");
$stmt->execute([$idSucursal]);

echo json_encode([
    'categorias' => $categorias,
    'productos'  => $stmt->fetchAll(),
]);
