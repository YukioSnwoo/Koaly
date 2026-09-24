<?php
header('Content-Type: application/json');
header('Cache-Control: no-store');

$productos = [
    ["id" => "1042", "nombre" => "Paquete de 6 Pepsi", "categoria" => "Refresco", "stock" => 24, "precio" => 560, "descuento" => 0, "estado" => "Activo"],
    ["id" => "1043", "nombre" => "Pepsi individual", "categoria" => "Refresco", "stock" => 12, "precio" => 18, "descuento" => 0, "estado" => "Activo"],
    ["id" => "1044", "nombre" => "Pepsi colaboración", "categoria" => "Refresco", "stock" => 9, "precio" => 25, "descuento" => 5, "estado" => "Activo"],
    ["id" => "2041", "nombre" => "Paquete de 24 Pepsi", "categoria" => "Refresco", "stock" => 18, "precio" => 980, "descuento" => 10, "estado" => "Activo"],
    ["id" => "2042", "nombre" => "Paquete de 12 Pepsi", "categoria" => "Refresco", "stock" => 16, "precio" => 520, "descuento" => 0, "estado" => "Activo"],
    ["id" => "3050", "nombre" => "Paquete de 6 7UP", "categoria" => "Refresco", "stock" => 20, "precio" => 290, "descuento" => 0, "estado" => "Activo"],
    ["id" => "3051", "nombre" => "Paquete de 4 7UP", "categoria" => "Refresco", "stock" => 14, "precio" => 210, "descuento" => 0, "estado" => "Activo"],
    ["id" => "4060", "nombre" => "Paquete de 6 Mirinda", "categoria" => "Refresco", "stock" => 11, "precio" => 260, "descuento" => 8, "estado" => "Activo"],
    ["id" => "4061", "nombre" => "Paquete de 6 Squirt", "categoria" => "Refresco", "stock" => 8, "precio" => 275, "descuento" => 0, "estado" => "Activo"]
];

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Método no permitido"]);
    exit;
}

$query = isset($_GET['q']) ? trim(strtolower($_GET['q'])) : '';
$modo = isset($_GET['modo']) ? $_GET['modo'] : 'nombre';

if ($query === '') {
    echo json_encode(["success" => true, "message" => "Campo vacío, se muestran todos", "total" => count($productos), "productos" => $productos]);
    exit;
}

$resultados = [];
foreach ($productos as $p) {
    if ($modo === 'id') {
        if (strpos(strtolower($p['id']), $query) !== false) $resultados[] = $p;
    } else {
        if (strpos(strtolower($p['nombre']), $query) !== false || strpos(strtolower($p['id']), $query) !== false) $resultados[] = $p;
    }
}

if (count($resultados) === 0) {
    echo json_encode(["success" => false, "message" => "Producto no existe", "total" => 0, "productos" => []]);
} elseif (count($resultados) === 1) {
    $p = $resultados[0];
    echo json_encode([
        "success" => true,
        "message" => "Producto encontrado",
        "total" => 1,
        "datos_mostrados" => ["clave" => $p['id'], "cantidad" => $p['stock'], "precio" => $p['precio'], "descuento" => $p['descuento']],
        "productos" => $resultados
    ]);
} else {
    echo json_encode(["success" => true, "message" => "Varios productos similares encontrados", "total" => count($resultados), "productos" => $resultados]);
}
