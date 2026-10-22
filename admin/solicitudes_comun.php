<?php
/**
 * Solicitudes de productos nuevos: lógica común del gerente y del admin.
 *
 * Estados (Solicitudes_Productos.estado):
 *   Pendiente  turno del admin (nueva, o reenviada por el gerente con cambios)
 *   Devuelta   turno del gerente (el admin la editó y espera su visto bueno)
 *   Aprobada   el producto ya está en el catálogo
 *   Rechazada  la rechazó el admin
 *   Cancelada  el gerente rechazó los cambios del admin
 *
 * Cada paso queda en Solicitudes_Productos_Historial (sql/009).
 * Los errores se lanzan como RuntimeException con una clave corta
 * (p. ej. 'proveedor_contacto'); cada panel la traduce a su idioma.
 */

/** Campos de la ficha que el admin y el gerente pueden cambiar. */
const CAMPOS_FICHA_SOLICITUD = ['nombre_producto', 'descripcion', 'id_categoria', 'precio_sugerido', 'imagen'];
/** Datos del proveedor: solo el gerente los escribe. */
const CAMPOS_PROVEEDOR_SOLICITUD = ['proveedor_sugerido', 'proveedor_empresa', 'proveedor_telefono', 'proveedor_correo'];

const CARPETA_IMAGENES_SOLICITUD = 'Imagenes/solicitudes';

/** Solicitudes que esperan al admin (nuevas o reenviadas por el gerente). */
function contarSolicitudesParaAdmin(PDO $pdo): int
{
    return (int) $pdo->query("SELECT COUNT(*) FROM Solicitudes_Productos WHERE estado = 'Pendiente'")->fetchColumn();
}

/**
 * Qué tan urgente se ve el aviso de solicitudes en el admin:
 * '' sin solicitudes, 'aviso' (amarillo) 1–3, 'alerta' (rojo) 4–9 y
 * 'critico' 10 o más (además se ilumina "Inventario global").
 */
function nivelAvisoSolicitudes(int $n): string
{
    if ($n >= 10) return 'critico';
    if ($n >= 4) return 'alerta';
    if ($n >= 1) return 'aviso';
    return '';
}

/** Lee una solicitud bloqueándola: dos clics o dos personas no la resuelven dos veces. */
function leerSolicitudBloqueada(PDO $pdo, int $idSolicitud): array
{
    $stmt = $pdo->prepare("SELECT * FROM Solicitudes_Productos WHERE id_solicitud = ? FOR UPDATE");
    $stmt->execute([$idSolicitud]);
    $sol = $stmt->fetch();
    if ($sol === false) {
        throw new RuntimeException('no_existe');
    }
    return $sol;
}

/**
 * Valida nombre, descripción, categoría y precio del formulario.
 * Devuelve los valores listos para guardar.
 */
function leerFichaSolicitud(PDO $pdo, array $post): array
{
    $nombre      = trim((string) ($post['nombre_producto'] ?? ''));
    $descripcion = trim((string) ($post['descripcion'] ?? ''));
    $idCategoria = (int) ($post['id_categoria'] ?? 0);
    $precio      = is_numeric($post['precio_sugerido'] ?? null) ? round((float) $post['precio_sugerido'], 2) : 0.0;

    if ($nombre === '' || mb_strlen($nombre) > 100) {
        throw new RuntimeException('nombre');
    }
    if (mb_strlen($descripcion) > 255) {
        throw new RuntimeException('descripcion');
    }
    if ($precio <= 0 || $precio >= 10000000) {
        throw new RuntimeException('precio');
    }
    $check = $pdo->prepare("SELECT 1 FROM Categorias WHERE id_categoria = ?");
    $check->execute([$idCategoria]);
    if ($idCategoria <= 0 || $check->fetch() === false) {
        throw new RuntimeException('categoria');
    }

    return [
        'nombre_producto' => $nombre,
        'descripcion'     => $descripcion !== '' ? $descripcion : null,
        'id_categoria'    => $idCategoria,
        'precio_sugerido' => number_format($precio, 2, '.', ''),
    ];
}

/**
 * Proveedor sugerido (opcional). Si el gerente lo agrega, el nombre es
 * obligatorio y hace falta al menos un contacto: teléfono o correo.
 */
function leerProveedorSolicitud(array $post): array
{
    if (empty($post['con_proveedor'])) {
        return array_fill_keys(CAMPOS_PROVEEDOR_SOLICITUD, null);
    }

    $nombre   = trim((string) ($post['proveedor_sugerido'] ?? ''));
    $empresa  = trim((string) ($post['proveedor_empresa'] ?? ''));
    $telefono = trim((string) ($post['proveedor_telefono'] ?? ''));
    $correo   = trim((string) ($post['proveedor_correo'] ?? ''));

    if ($nombre === '' || mb_strlen($nombre) > 100) {
        throw new RuntimeException('proveedor_nombre');
    }
    if (mb_strlen($empresa) > 100) {
        throw new RuntimeException('proveedor_empresa');
    }
    if ($telefono === '' && $correo === '') {
        throw new RuntimeException('proveedor_contacto');
    }
    if ($telefono !== '' && (!preg_match('/^\+?[0-9 ()\-]{7,20}$/', $telefono) || preg_match_all('/\d/', $telefono) < 7)) {
        throw new RuntimeException('proveedor_telefono');
    }
    if ($correo !== '' && (mb_strlen($correo) > 150 || filter_var($correo, FILTER_VALIDATE_EMAIL) === false)) {
        throw new RuntimeException('proveedor_correo');
    }

    return [
        'proveedor_sugerido' => $nombre,
        'proveedor_empresa'  => $empresa !== '' ? $empresa : null,
        'proveedor_telefono' => $telefono !== '' ? $telefono : null,
        'proveedor_correo'   => $correo !== '' ? $correo : null,
    ];
}

/**
 * Guarda una imagen subida (PNG, JPG o WEBP de hasta 5 MB) y devuelve su
 * ruta relativa a la raíz del sitio, o null si no se subió ninguna.
 */
function guardarImagenProducto(array $archivo, string $carpetaDestino, string $rutaRelativaBase, string $prefijo = 'prod_'): ?string
{
    if (!isset($archivo['error']) || $archivo['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($archivo['error'] === UPLOAD_ERR_INI_SIZE || $archivo['error'] === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException('imagen_tamano');
    }
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('imagen_subida');
    }
    if ($archivo['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('imagen_tamano');
    }

    $tiposPermitidos = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
    $mime = mime_content_type($archivo['tmp_name']);

    if (!isset($tiposPermitidos[$mime])) {
        throw new RuntimeException('imagen_formato');
    }

    if (!is_dir($carpetaDestino)) {
        mkdir($carpetaDestino, 0755, true);
    }

    $nombreArchivo = uniqid($prefijo, true) . '.' . $tiposPermitidos[$mime];
    $rutaDestino = $carpetaDestino . '/' . $nombreArchivo;

    if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
        throw new RuntimeException('imagen_guardar');
    }

    return $rutaRelativaBase . '/' . $nombreArchivo;
}

/** Imagen de una solicitud en Imagenes/solicitudes, o null si no se subió. */
function guardarImagenSolicitud(array $archivo): ?string
{
    return guardarImagenProducto($archivo, __DIR__ . '/../' . CARPETA_IMAGENES_SOLICITUD, CARPETA_IMAGENES_SOLICITUD);
}

/** Borra una imagen recién subida cuando el guardado falla (solo de Imagenes/solicitudes). */
function borrarImagenSolicitud(?string $ruta): void
{
    if ($ruta !== null && str_starts_with($ruta, CARPETA_IMAGENES_SOLICITUD . '/')) {
        @unlink(__DIR__ . '/../' . $ruta);
    }
}

/** Valor normalizado para comparar lo guardado con lo que viene del formulario. */
function valorComparableSolicitud(string $campo, $valor): string
{
    if ($valor === null) return '';
    if ($campo === 'id_categoria') return (string) (int) $valor;
    if ($campo === 'precio_sugerido') return number_format((float) $valor, 2, '.', '');
    return trim((string) $valor);
}

/**
 * Campos que cambian entre la solicitud guardada y los valores nuevos:
 * [campo => ['antes' => …, 'despues' => …]]. Solo revisa los campos de
 * $campos que vienen en $nuevos.
 */
function diferenciasSolicitud(array $antes, array $nuevos, array $campos): array
{
    $cambios = [];
    foreach ($campos as $campo) {
        if (!array_key_exists($campo, $nuevos)) continue;
        if (valorComparableSolicitud($campo, $antes[$campo] ?? null) !== valorComparableSolicitud($campo, $nuevos[$campo])) {
            $cambios[$campo] = ['antes' => $antes[$campo] ?? null, 'despues' => $nuevos[$campo]];
        }
    }
    return $cambios;
}

/** Aplica los cambios a la solicitud y la deja en el estado indicado. */
function actualizarSolicitud(PDO $pdo, int $idSolicitud, array $cambios, string $estado): void
{
    $sets = [];
    $params = [];
    foreach ($cambios as $campo => $valores) {
        // Los nombres vienen de CAMPOS_FICHA_SOLICITUD / CAMPOS_PROVEEDOR_SOLICITUD, nunca del usuario
        $sets[] = "$campo = ?";
        $params[] = $valores['despues'];
    }
    $sets[] = 'estado = ?';
    $params[] = $estado;
    $sets[] = 'actualizado_en = NOW()';
    $params[] = $idSolicitud;

    $pdo->prepare("UPDATE Solicitudes_Productos SET " . implode(', ', $sets) . " WHERE id_solicitud = ?")
        ->execute($params);
}

/** Registra un paso de la solicitud: quién, qué cambió y por qué. */
function registrarHistorialSolicitud(
    PDO $pdo,
    int $idSolicitud,
    int $idUsuario,
    string $rol,
    string $accion,
    array $cambios = [],
    ?string $nota = null
): void {
    $stmt = $pdo->prepare("
        INSERT INTO Solicitudes_Productos_Historial (id_solicitud, id_usuario, rol, accion, cambios, nota)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $idSolicitud,
        $idUsuario,
        $rol,
        $accion,
        $cambios ? json_encode($cambios, JSON_UNESCAPED_UNICODE) : null,
        $nota !== null && $nota !== '' ? $nota : null,
    ]);
}

/**
 * Historial de varias solicitudes, del paso más viejo al más nuevo:
 * [id_solicitud => [pasos]], con 'cambios' ya decodificado y 'autor'.
 */
function historialSolicitudes(PDO $pdo, array $ids): array
{
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (!$ids) return [];

    $marcas = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT h.*, u.nombre AS autor
        FROM Solicitudes_Productos_Historial h
        LEFT JOIN Usuarios u ON u.id_usuario = h.id_usuario
        WHERE h.id_solicitud IN ($marcas)
        ORDER BY h.id_historial
    ");
    $stmt->execute($ids);

    $porSolicitud = [];
    foreach ($stmt->fetchAll() as $paso) {
        $paso['cambios'] = $paso['cambios'] ? (json_decode($paso['cambios'], true) ?: []) : [];
        $porSolicitud[(int) $paso['id_solicitud']][] = $paso;
    }
    return $porSolicitud;
}

/** El último paso si fue una edición: son los cambios que se pintan en amarillo. */
function ultimaEdicionSolicitud(array $pasos): ?array
{
    $ultimo = $pasos ? $pasos[count($pasos) - 1] : null;
    return $ultimo !== null && $ultimo['accion'] === 'Editada' ? $ultimo : null;
}

/**
 * Agrega al catálogo el producto de una solicitud de alta, con su imagen.
 * Si hay un producto descontinuado con el mismo nombre, lo reactiva tal como
 * estaba en lugar de duplicarlo. Debe llamarse dentro de una transacción.
 *
 * @return array{0: int, 1: bool} [id del producto, true si se reactivó]
 */
function crearProductoDesdeSolicitud(PDO $pdo, array $sol): array
{
    $nombre      = trim((string) $sol['nombre_producto']);
    $descripcion = trim((string) ($sol['descripcion'] ?? ''));
    $idCategoria = (int) $sol['id_categoria'];
    $precio      = is_numeric($sol['precio_sugerido']) ? round((float) $sol['precio_sugerido'], 2) : -1;

    if ($nombre === '' || mb_strlen($nombre) > 100) {
        throw new RuntimeException('nombre');
    }
    if ($precio <= 0) {
        throw new RuntimeException('precio');
    }
    $check = $pdo->prepare("SELECT 1 FROM Categorias WHERE id_categoria = ?");
    $check->execute([$idCategoria]);
    if ($check->fetch() === false) {
        throw new RuntimeException('categoria');
    }

    $check = $pdo->prepare("SELECT id_producto, estado FROM Productos WHERE LOWER(nombre) = LOWER(?) ORDER BY (estado = 'Activo') DESC LIMIT 1 FOR UPDATE");
    $check->execute([$nombre]);
    $existente = $check->fetch();
    if ($existente !== false && $existente['estado'] === 'Activo') {
        throw new RuntimeException('duplicado');
    }

    if ($existente !== false) {
        $idProducto = (int) $existente['id_producto'];
        $pdo->prepare("UPDATE Productos SET estado = 'Activo', modificado_en = NOW() WHERE id_producto = ?")
            ->execute([$idProducto]);
        return [$idProducto, true];
    }

    // Código provisional único; el definitivo se genera como en sql/002.
    $stmt = $pdo->prepare("
        INSERT INTO Productos (codigo, nombre, descripcion, precio, imagen, id_categoria, estado, creado_en, modificado_en)
        VALUES (?, ?, ?, ?, ?, ?, 'Activo', NOW(), NOW())
    ");
    $stmt->execute([
        'TMP' . bin2hex(random_bytes(8)),
        $nombre,
        $descripcion !== '' ? $descripcion : null,
        $precio,
        !empty($sol['imagen']) ? $sol['imagen'] : null,
        $idCategoria,
    ]);
    $idProducto = (int) $pdo->lastInsertId();
    $pdo->prepare("UPDATE Productos SET codigo = ? WHERE id_producto = ?")
        ->execute(['750' . str_pad((string) $idProducto, 10, '0', STR_PAD_LEFT), $idProducto]);

    return [$idProducto, false];
}

/** Marca la solicitud como aprobada con el producto que se creó. */
function marcarSolicitudAprobada(PDO $pdo, int $idSolicitud, int $idProducto, int $idUsuario): void
{
    $pdo->prepare("UPDATE Solicitudes_Productos SET estado = 'Aprobada', id_producto_creado = ?, resuelto_en = NOW(), resuelto_por = ?, actualizado_en = NOW() WHERE id_solicitud = ?")
        ->execute([$idProducto, $idUsuario, $idSolicitud]);
}
