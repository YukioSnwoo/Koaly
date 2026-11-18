<?php
/**
 * Solicitudes de cambio de nombre y correo (sql/020_solicitudes_datos.sql).
 *
 * El cajero y el gerente piden el cambio desde "Mi perfil" (perfil/guardar.php):
 *   cajero  → lo revisa el gerente de su sucursal (gerente/cajeros.php); si la
 *             sucursal no tiene gerente activo, la administración.
 *   gerente → lo revisa la administración (admin/gerentes.php).
 * El admin cambia sus datos directamente. Quien revisa solo aprueba o rechaza
 * (no edita lo pedido); al rechazar, el título es obligatorio y el motivo no.
 * El resultado le llega a quien lo pidió en su campanita (perfil/perfil.php).
 *
 * Sin sql/020 las consultas de lectura devuelven vacío; crear o resolver lanzan
 * PDOException y quien llama avisa que falta la tabla.
 */
require_once __DIR__ . '/reglas_nombre.php';

const SOLICITUD_DATOS_TITULO_MAX = 80;
const SOLICITUD_DATOS_MOTIVO_MAX = 500;

/** Un paso que no se pudo dar; $clave se traduce en cada panel (datos.error_<clave>). */
class SolicitudDatosError extends RuntimeException
{
    public function __construct(public readonly string $clave)
    {
        parent::__construct($clave);
    }
}

/** ¿Quién revisa los cambios de este usuario? null: nadie, los cambia directo (el admin). */
function revisorSolicitudDatos(PDO $pdo, array $usuario): ?string
{
    $rol = (int) $usuario['id_rol'];
    if ($rol === 2) {
        return 'Admin';
    }
    if ($rol !== 3) {
        return null;
    }
    if ($usuario['id_sucursal'] === null) {
        return 'Admin';
    }
    $stmt = $pdo->prepare("SELECT 1 FROM Usuarios WHERE id_rol = 2 AND id_sucursal = ? AND estado = 'Activo' LIMIT 1");
    $stmt->execute([(int) $usuario['id_sucursal']]);
    return $stmt->fetchColumn() !== false ? 'Gerente' : 'Admin';
}

/** La solicitud más reciente del usuario (pendiente o ya resuelta), o null. */
function ultimaSolicitudDatos(PDO $pdo, int $idUsuario): ?array
{
    try {
        $stmt = $pdo->prepare("SELECT * FROM Solicitudes_Datos WHERE id_usuario = ? ORDER BY id_solicitud DESC LIMIT 1");
        $stmt->execute([$idUsuario]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException) {
        return null;
    }
}

/**
 * Crea la solicitud. $nombre: partes nuevas con el 'nombre' completo, o null si
 * no cambia; $email: el nuevo, o null. Lanza SolicitudDatosError('pendiente') si
 * ya tenía una pendiente (la llave única lo impide aunque lleguen dos a la vez).
 */
function crearSolicitudDatos(PDO $pdo, array $usuario, string $revisa, ?array $nombre, ?string $email): void
{
    try {
        $pdo->prepare("
            INSERT INTO Solicitudes_Datos
                (id_usuario, revisa, id_sucursal, nombre_antes, email_antes,
                 nombre, nombres, apellido_paterno, apellido_materno, email, pendiente_de)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            (int) $usuario['id_usuario'], $revisa, $usuario['id_sucursal'] !== null ? (int) $usuario['id_sucursal'] : null,
            $usuario['nombre'], $usuario['email'],
            $nombre['nombre'] ?? null, $nombre['nombres'] ?? null, $nombre['apellido_paterno'] ?? null, $nombre['apellido_materno'] ?? null,
            $email, (int) $usuario['id_usuario'],
        ]);
    } catch (PDOException $e) {
        if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
            throw new SolicitudDatosError('pendiente');
        }
        throw $e;
    }
}

/** Cancela la solicitud pendiente del usuario. false si no tenía. */
function cancelarSolicitudDatos(PDO $pdo, int $idUsuario): bool
{
    $stmt = $pdo->prepare("
        UPDATE Solicitudes_Datos
        SET estado = 'Cancelada', pendiente_de = NULL, resuelto_en = CURRENT_TIMESTAMP
        WHERE pendiente_de = ?
    ");
    $stmt->execute([$idUsuario]);
    return $stmt->rowCount() > 0;
}

/** Condición de "le toca a quien revisa": el gerente solo ve las de su sucursal. */
function _alcanceSolicitudDatos(string $revisa, ?int $idSucursal, string $alias = ''): array
{
    $p = $alias !== '' ? $alias . '.' : '';
    return $revisa === 'Gerente'
        ? ["{$p}revisa = 'Gerente' AND {$p}id_sucursal = ?", [(int) $idSucursal]]
        : ["{$p}revisa = 'Admin'", []];
}

/** Pendientes que le tocan a quien revisa, de la más vieja a la más nueva. */
function solicitudesDatosPorRevisar(PDO $pdo, string $revisa, ?int $idSucursal = null): array
{
    [$alcance, $params] = _alcanceSolicitudDatos($revisa, $idSucursal, 'sd');
    // Las partes del nombre actual van con otro alias: sd.* ya trae nombres y apellidos (los pedidos)
    $partesActuales = hayColumnasApellidos($pdo)
        ? 'u.nombres AS u_nombres, u.apellido_paterno AS u_paterno, u.apellido_materno AS u_materno'
        : 'NULL AS u_nombres, NULL AS u_paterno, NULL AS u_materno';
    try {
        $stmt = $pdo->prepare("
            SELECT sd.*, u.id_rol, $partesActuales, s.nombre AS sucursal
            FROM Solicitudes_Datos sd
            INNER JOIN Usuarios u ON u.id_usuario = sd.id_usuario
            LEFT JOIN Sucursales s ON s.id_sucursal = sd.id_sucursal
            WHERE sd.estado = 'Pendiente' AND $alcance
            ORDER BY sd.creado_en, sd.id_solicitud
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException) {
        return [];
    }
}

/** Iniciales de quien pide el cambio, con su nombre de ahora (no el pedido). */
function inicialesSolicitante(array $s): string
{
    return inicialesNombre([
        'nombre'           => $s['nombre_antes'],
        'nombres'          => $s['u_nombres'] ?? null,
        'apellido_paterno' => $s['u_paterno'] ?? null,
        'apellido_materno' => $s['u_materno'] ?? null,
    ]);
}

/**
 * Lo que cambia, para mostrarlo: [['campo' => 'nombre'|'email', 'antes', 'despues']].
 */
function cambiosSolicitudDatos(array $s): array
{
    $cambios = [];
    if ($s['nombre'] !== null) {
        $cambios[] = ['campo' => 'nombre', 'antes' => $s['nombre_antes'], 'despues' => $s['nombre']];
    }
    if ($s['email'] !== null) {
        $cambios[] = ['campo' => 'email', 'antes' => $s['email_antes'], 'despues' => $s['email']];
    }
    return $cambios;
}

/** 'nombre', 'correo' o 'ambos': qué se pidió cambiar (para los textos). */
function queCambiaSolicitudDatos(array $s): string
{
    return match (true) {
        $s['nombre'] !== null && $s['email'] !== null => 'ambos',
        $s['email'] !== null                           => 'correo',
        default                                        => 'nombre',
    };
}

/**
 * Aprueba: aplica el nombre y/o el correo pedidos y devuelve la solicitud (con el
 * correo anterior, para avisarle). Lanza SolicitudDatosError: 'no_encontrada' (ya
 * se resolvió, se canceló o no le toca) o 'email_usado' (otra cuenta tomó el
 * correo mientras tanto: hay que rechazarla).
 */
function aprobarSolicitudDatos(PDO $pdo, int $idSolicitud, int $idRevisor, string $revisa, ?int $idSucursal): array
{
    [$alcance, $params] = _alcanceSolicitudDatos($revisa, $idSucursal);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM Solicitudes_Datos WHERE id_solicitud = ? AND estado = 'Pendiente' AND $alcance FOR UPDATE");
        $stmt->execute(array_merge([$idSolicitud], $params));
        $s = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$s) {
            throw new SolicitudDatosError('no_encontrada');
        }
        $idUsuario = (int) $s['id_usuario'];

        $sets = [];
        $valores = [];
        if ($s['nombre'] !== null) {
            $sets[] = 'nombre = ?';
            $valores[] = $s['nombre'];
        }
        if ($s['email'] !== null) {
            $stmt = $pdo->prepare("SELECT 1 FROM Usuarios WHERE email = ? AND id_usuario <> ?");
            $stmt->execute([$s['email'], $idUsuario]);
            if ($stmt->fetchColumn() !== false) {
                throw new SolicitudDatosError('email_usado');
            }
            $sets[] = 'email = ?';
            $valores[] = $s['email'];
        }
        if ($sets !== []) {
            $pdo->prepare('UPDATE Usuarios SET ' . implode(', ', $sets) . ' WHERE id_usuario = ?')
                ->execute(array_merge($valores, [$idUsuario]));
        }
        if ($s['nombre'] !== null) {
            guardarPartesNombre($pdo, $idUsuario, [
                'nombres'          => $s['nombres'],
                'apellido_paterno' => $s['apellido_paterno'],
                'apellido_materno' => $s['apellido_materno'],
            ]);
        }
        // Correo nuevo: los enlaces del aviso de privacidad sin usar ya no sirven
        // (igual que cuando el gerente edita el correo de un cajero)
        if ($s['email'] !== null) {
            try {
                $pdo->prepare("DELETE FROM avisos_privacidad WHERE id_usuario = ? AND aceptado_en IS NULL")->execute([$idUsuario]);
            } catch (PDOException) {
                // Sin sql/010 no hay enlaces que cancelar
            }
        }

        $pdo->prepare("
            UPDATE Solicitudes_Datos
            SET estado = 'Aprobada', pendiente_de = NULL, resuelto_en = CURRENT_TIMESTAMP, resuelto_por = ?
            WHERE id_solicitud = ?
        ")->execute([$idRevisor, $idSolicitud]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e instanceof PDOException && (int) ($e->errorInfo[1] ?? 0) === 1062) {
            throw new SolicitudDatosError('email_usado');
        }
        throw $e;
    }
    return $s;
}

/**
 * Rechaza con un título (obligatorio) y un motivo (opcional). Lanza
 * SolicitudDatosError: 'titulo_falta', 'titulo_largo', 'motivo_largo' o 'no_encontrada'.
 */
function rechazarSolicitudDatos(PDO $pdo, int $idSolicitud, int $idRevisor, string $revisa, ?int $idSucursal, string $titulo, string $motivo): array
{
    $titulo = trim(preg_replace('/\s+/u', ' ', $titulo));
    $motivo = trim(str_replace("\r\n", "\n", $motivo));
    if ($titulo === '') {
        throw new SolicitudDatosError('titulo_falta');
    }
    if (mb_strlen($titulo) > SOLICITUD_DATOS_TITULO_MAX) {
        throw new SolicitudDatosError('titulo_largo');
    }
    if (mb_strlen($motivo) > SOLICITUD_DATOS_MOTIVO_MAX) {
        throw new SolicitudDatosError('motivo_largo');
    }

    [$alcance, $params] = _alcanceSolicitudDatos($revisa, $idSucursal);
    $stmt = $pdo->prepare("SELECT * FROM Solicitudes_Datos WHERE id_solicitud = ? AND estado = 'Pendiente' AND $alcance");
    $stmt->execute(array_merge([$idSolicitud], $params));
    $s = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        UPDATE Solicitudes_Datos
        SET estado = 'Rechazada', pendiente_de = NULL, rechazo_titulo = ?, rechazo_motivo = ?,
            resuelto_en = CURRENT_TIMESTAMP, resuelto_por = ?
        WHERE id_solicitud = ? AND estado = 'Pendiente' AND $alcance
    ");
    $stmt->execute(array_merge([$titulo, $motivo !== '' ? $motivo : null, $idRevisor, $idSolicitud], $params));
    if (!$s || $stmt->rowCount() === 0) {
        throw new SolicitudDatosError('no_encontrada');
    }
    return $s;
}

/**
 * Al aprobar un cambio de correo, aviso a la dirección anterior (por si no fue
 * su dueño quien lo pidió). Va en español: el sistema no guarda el idioma de
 * cada persona. $quienAvisar: "tu gerente" o "la administración".
 */
function avisarCorreoCambiado(array $s, string $quienAvisar): bool
{
    require_once __DIR__ . '/../login/correo.php';
    $nombre = $s['nombre'] ?? $s['nombre_antes'];
    $parrafos = [
        "Hola, {$nombre}:",
        'Se aprobó el cambio de correo que pediste en Koaly. Desde ahora entras con esta dirección: ' . $s['email'],
        'Este correo ya no sirve para entrar.',
    ];
    $pie = "Si tú no pediste este cambio, avísale de inmediato a {$quienAvisar}.";

    return enviarCorreo(
        $s['email_antes'],
        'Cambió tu correo de acceso a Koaly',
        correoTexto($parrafos, $pie),
        null,
        correoHtml($parrafos, correoE($pie))
    );
}
