<?php
require_once __DIR__ . '/guardia.php';
require_once __DIR__ . '/includes/correos_empleado.php';
require_once __DIR__ . '/../login/reglas_contrasena.php';
require_once __DIR__ . '/includes/vistas.php';
require_once __DIR__ . '/includes/turnos.php';
require_once __DIR__ . '/../admin/profanity_filter.php';
require_once __DIR__ . '/../admin/reglas_nombre.php';
require_once __DIR__ . '/../admin/eliminar_usuario.php';
require_once __DIR__ . '/../admin/solicitudes_datos.php';   // cambios de nombre y correo que piden los cajeros

/**
 * ¿El gerente puede cambiar el correo de este cajero? Solo mientras el cajero
 * nunca ha aceptado el aviso de privacidad: aceptarlo es abrir el enlace que
 * llegó a su buzón, así que desde ahí el correo es suyo. Después solo él lo
 * pide (en «Mi perfil»; el gerente aprueba lo que él escribió) o la
 * administración lo cambia; si no, el gerente podría poner su propio correo,
 * restablecer la contraseña y entrar a la caja como él.
 * Sin la tabla del aviso (sql/010) se usa "nunca ha entrado" (debe_cambiar).
 */
function puedeCambiarCorreo(PDO $pdo, int $idCajero, bool $debeCambiar): bool
{
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM avisos_privacidad WHERE id_usuario = ? AND aceptado_en IS NOT NULL LIMIT 1");
        $stmt->execute([$idCajero]);
        return $stmt->fetchColumn() === false;
    } catch (PDOException $e) {
        return $debeCambiar;
    }
}

/**
 * Al dar de baja a un cajero se liberan e inactivan sus cajas.
 * Devuelve cuántas cajas se afectaron. Llamar dentro de una transacción.
 */
function liberarCajasDeCajero(PDO $pdo, int $idCajero, int $idSucursal): int
{
    $stmt = $pdo->prepare("
        UPDATE Cajas
        SET estado = 'Inactiva', id_cajero_asignado = NULL
        WHERE id_cajero_asignado = ? AND id_sucursal = ?
    ");
    $stmt->execute([$idCajero, $idSucursal]);
    return $stmt->rowCount();
}

$ESTADOS_VALIDOS = ['Activo', 'Vacaciones', 'Incapacidad', 'Licencia', 'Baja'];

$errores = [];
$exito   = '';
$avisosCorreo     = [];   // [['ok' => bool, 'msg' => string]]: correos enviados al empleado

/* ------------------------------------------------------------
   Flash (PRG)
   ------------------------------------------------------------ */
if (!empty($_SESSION['flash'])) {
    $exito = $_SESSION['flash']['msg'] ?? '';
    $avisosCorreo     = $_SESSION['flash']['avisos'] ?? [];
    unset($_SESSION['flash']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $mensajeExito = '';
    $flashAvisos = [];
    // Resultado de cada correo al empleado: la acción ya quedó guardada aunque el correo falle
    $avisar = static function (bool $ok, string $email, string $claveOk = 'cajeros.notificado') use (&$flashAvisos): void {
        $flashAvisos[] = ['ok' => $ok, 'msg' => t($ok ? $claveOk : 'cajeros.notificacion_fallo', ['email' => $email])];
    };

    try {
        if (!csrfValido()) {
            throw new RuntimeException(t('error.formulario_expirado'));
        }

        if ($action === 'crear') {
            $email    = trim($_POST['email'] ?? '');
            $estado   = $_POST['estado'] ?? 'Activo';
            $fechaContratacion = $_POST['fecha_contratacion'] ?? date('Y-m-d');

            // Nombre(s) y apellidos por separado; $nombre es el completo (admin/reglas_nombre.php)
            try {
                $partesNombre = nombreDesdeFormulario($_POST);
            } catch (NombreInvalido $e) {
                throw new RuntimeException(t('error.nombre_' . $e->regla));
            }
            $nombre = $partesNombre['nombre'];
            try {
                rechazarSiProfano($nombre);
            } catch (RuntimeException) {
                throw new RuntimeException(t('error.profano'));
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
                throw new RuntimeException(t('error.email_invalido'));
            }
            try {
                rechazarCorreoSiProfano($email);
            } catch (RuntimeException) {
                throw new RuntimeException(t('error.email_profano'));
            }
            if (!in_array($estado, $ESTADOS_VALIDOS, true)) {
                throw new RuntimeException(t('error.estado_invalido'));
            }
            $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $fechaContratacion);
            if (!$fecha || $fecha->format('Y-m-d') !== $fechaContratacion) {
                throw new RuntimeException(t('error.fecha_contratacion'));
            }
            if ($fecha > new DateTimeImmutable('today')) {
                throw new RuntimeException(t('error.fecha_contratacion_futura'));
            }

            $check = $pdo->prepare("SELECT nombre, estado, id_rol, id_sucursal FROM Usuarios WHERE email = ?");
            $check->execute([$email]);
            if (($dueno = $check->fetch()) !== false) {
                // Dar de baja no libera el correo. Si es un cajero de esta sucursal dado
                // de baja, se dice quién es y qué hacer (de otras sucursales no se revela nada).
                $esBajaPropia = (int) $dueno['id_rol'] === 3 && $dueno['estado'] === 'Baja'
                    && (int) $dueno['id_sucursal'] === $idSucursalGerente;
                throw new RuntimeException($esBajaPropia
                    ? t('error.email_de_baja', ['nombre' => $dueno['nombre']])
                    : t('error.email_duplicado'));
            }

            // La contraseña la genera el sistema y solo llega al cajero por correo: el gerente
            // nunca la ve. El hash de relleno no lo sabe nadie; la temporal se le pone y se le
            // envía, con el enlace del aviso de privacidad, cuando el gerente le asigna caja
            // (ver enviarAccesoAlAsignarCaja() en cajas.php).
            $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO Usuarios
                    (nombre, email, contrasena_hash, id_rol, id_sucursal, estado,
                     fecha_inicio_estado, fecha_contratacion, debe_cambiar)
                VALUES (?, ?, ?, 3, ?, ?, CURDATE(), ?, 1)
            ");
            $stmt->execute([$nombre, $email, $hash, $idSucursalGerente, $estado, $fechaContratacion]);
            $idNuevo = (int) $pdo->lastInsertId();
            guardarPartesNombre($pdo, $idNuevo, $partesNombre);
            $mensajeExito = t('cajeros.creado_sin_acceso');

        } elseif ($action === 'editar') {
            $id     = (int) ($_POST['id_usuario'] ?? 0);
            $email  = trim($_POST['email'] ?? '');
            $estado = $_POST['estado'] ?? 'Activo';
            $motivoBaja = (string) ($_POST['motivo_baja'] ?? '');

            if ($id <= 0) {
                throw new RuntimeException(t('error.cajero_invalido'));
            }
            try {
                $partesNombre = nombreDesdeFormulario($_POST);
            } catch (NombreInvalido $e) {
                throw new RuntimeException(t('error.nombre_' . $e->regla));
            }
            $nombre = $partesNombre['nombre'];
            try {
                rechazarSiProfano($nombre);
            } catch (RuntimeException) {
                throw new RuntimeException(t('error.profano'));
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
                throw new RuntimeException(t('error.email_invalido'));
            }
            try {
                rechazarCorreoSiProfano($email);
            } catch (RuntimeException) {
                throw new RuntimeException(t('error.email_profano'));
            }
            if (!in_array($estado, $ESTADOS_VALIDOS, true)) {
                throw new RuntimeException(t('error.estado_invalido'));
            }

            // Verificar que el cajero pertenece a esta sucursal y es rol 3 (evita IDOR)
            $check = $pdo->prepare("
                SELECT estado, email, debe_cambiar FROM Usuarios
                WHERE id_usuario = ? AND id_sucursal = ? AND id_rol = 3
            ");
            $check->execute([$id, $idSucursalGerente]);
            $anterior = $check->fetch();
            if ($anterior === false) {
                throw new RuntimeException(t('error.cajero_invalido'));
            }
            $estadoAnterior = $anterior['estado'];
            if (strcasecmp($email, $anterior['email']) !== 0
                && !puedeCambiarCorreo($pdo, $id, (int) $anterior['debe_cambiar'] === 1)) {
                throw new RuntimeException(t('error.email_bloqueado'));
            }
            // Salir de "Activo" aparta al cajero de su caja: no con un turno abierto
            if ($estadoAnterior === 'Activo' && $estado !== 'Activo'
                && ($abierta = cajaConTurnoAbierto($pdo, $idSucursalGerente, null, $id)) !== null) {
                throw new RuntimeException(t('turnos.error_cajero_abierta', ['nombre' => $nombre, 'caja' => $abierta]));
            }
            if ($estado === 'Baja' && $estadoAnterior !== 'Baja' && !isset(MOTIVOS_BAJA[$motivoBaja])) {
                throw new RuntimeException(t('error.motivo_baja'));
            }

            $check = $pdo->prepare("SELECT 1 FROM Usuarios WHERE email = ? AND id_usuario <> ?");
            $check->execute([$email, $id]);
            if ($check->fetch() !== false) {
                throw new RuntimeException(t('error.email_duplicado'));
            }

            $pdo->beginTransaction();

            // fecha_inicio_estado solo cambia si el estado cambia. Va ANTES de
            // "estado = ?": MySQL evalúa el SET de izquierda a derecha.
            $stmt = $pdo->prepare("
                UPDATE Usuarios
                SET nombre = ?, email = ?,
                    fecha_inicio_estado = IF(estado = ?, fecha_inicio_estado, CURDATE()),
                    estado = ?
                WHERE id_usuario = ?
            ");
            $stmt->execute([$nombre, $email, $estado, $estado, $id]);
            guardarPartesNombre($pdo, $id, $partesNombre);

            // Correo nuevo: los enlaces del aviso que no se han usado dejan de servir,
            // para que un enlace no termine en un buzón que el empleado no confirmó
            if (strcasecmp($email, $anterior['email']) !== 0) {
                try {
                    $pdo->prepare("DELETE FROM avisos_privacidad WHERE id_usuario = ? AND aceptado_en IS NULL")
                        ->execute([$id]);
                } catch (PDOException $e) {
                    // Sin la tabla del aviso (sql/010) no hay enlaces que cancelar
                }
            }

            $mensajeExito = t('cajeros.actualizado');
            if ($estado === 'Baja' && $estadoAnterior !== 'Baja'
                && liberarCajasDeCajero($pdo, $id, $idSucursalGerente) > 0) {
                $mensajeExito .= ' ' . t('cajeros.cajas_inactivas');
            } elseif ($estado !== 'Activo' && $estado !== 'Baja') {
                // Vacaciones, incapacidad o licencia: su caja no puede seguir activa,
                // pero conserva la asignación para cuando regrese.
                $stmt = $pdo->prepare("
                    UPDATE Cajas SET estado = 'Inactiva'
                    WHERE id_cajero_asignado = ? AND id_sucursal = ? AND estado = 'Activa'
                ");
                $stmt->execute([$id, $idSucursalGerente]);
                if ($stmt->rowCount() > 0) {
                    $mensajeExito .= ' ' . t('cajeros.caja_inactiva_temporal');
                }
            }

            $pdo->commit();

            // Correos al empleado: cambio de estado y, si cambió su correo, aviso a la dirección anterior
            $empleado = ['nombre' => $nombre, 'email' => $email];
            if ($estado !== $estadoAnterior || strcasecmp($email, $anterior['email']) !== 0) {
                $datosCorreo = datosParaCorreos($pdo, (int) $gerente['id_usuario'], $idSucursalGerente);
            }
            if ($estado !== $estadoAnterior) {
                $avisar(enviarAvisoCambioEstado($empleado, $datosCorreo, $estado, $motivoBaja ?: null), $email);
            }
            if (strcasecmp($email, $anterior['email']) !== 0) {
                $avisar(enviarAvisoCambioCorreo($anterior['email'], $empleado, $datosCorreo), $anterior['email']);
            }

        } elseif ($action === 'cambiar_estado') {
            $id = (int) ($_POST['id_usuario'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException(t('error.cajero_invalido'));
            }

            $check = $pdo->prepare("
                SELECT estado, nombre, email FROM Usuarios
                WHERE id_usuario = ? AND id_sucursal = ? AND id_rol = 3
            ");
            $check->execute([$id, $idSucursalGerente]);
            $empleado = $check->fetch();
            if ($empleado === false) {
                throw new RuntimeException(t('error.cajero_invalido'));
            }

            $nuevo = $empleado['estado'] === 'Activo' ? 'Baja' : 'Activo';
            if ($nuevo === 'Baja' && ($abierta = cajaConTurnoAbierto($pdo, $idSucursalGerente, null, $id)) !== null) {
                throw new RuntimeException(t('turnos.error_cajero_abierta', ['nombre' => $empleado['nombre'], 'caja' => $abierta]));
            }
            $motivoBaja = (string) ($_POST['motivo_baja'] ?? '');
            if ($nuevo === 'Baja' && !isset(MOTIVOS_BAJA[$motivoBaja])) {
                throw new RuntimeException(t('error.motivo_baja'));
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                UPDATE Usuarios
                SET estado = ?, fecha_inicio_estado = CURDATE()
                WHERE id_usuario = ?
            ");
            $stmt->execute([$nuevo, $id]);

            $mensajeExtra = '';
            if ($nuevo === 'Baja' && liberarCajasDeCajero($pdo, $id, $idSucursalGerente) > 0) {
                $mensajeExtra = ' ' . t('cajeros.cajas_inactivas');
            }

            $pdo->commit();

            $mensajeExito = t($nuevo === 'Activo' ? 'cajeros.reactivado' : 'cajeros.baja') . $mensajeExtra;

            $datosCorreo = datosParaCorreos($pdo, (int) $gerente['id_usuario'], $idSucursalGerente);
            $avisar(enviarAvisoCambioEstado($empleado, $datosCorreo, $nuevo, $nuevo === 'Baja' ? $motivoBaja : null), $empleado['email']);

        } elseif ($action === 'reenviar_acceso') {
            // Si aún no entra (debe_cambiar = 1), contraseña temporal nueva: la anterior pudo
            // perderse, vencer o no llegar. Va con el enlace del aviso si falta aceptarlo.
            $id = (int) ($_POST['id_usuario'] ?? 0);
            $check = $pdo->prepare("
                SELECT u.nombre, u.email, u.estado, u.debe_cambiar, " . columnasApellidos($pdo) . "
                FROM Usuarios u
                WHERE u.id_usuario = ? AND u.id_sucursal = ? AND u.id_rol = 3
            ");
            $check->execute([$id, $idSucursalGerente]);
            $empleado = $check->fetch();
            if ($empleado === false || $empleado['estado'] === 'Baja') {
                throw new RuntimeException(t('error.cajero_invalido'));
            }
            $empleado['nombres'] = partesNombre($empleado)['nombres'];
            $sinEntrar = (int) $empleado['debe_cambiar'] === 1;
            try {
                $falteAviso = !avisoAceptado($pdo, $id);
            } catch (PDOException $e) {
                $falteAviso = false;   // sin la tabla del aviso (sql/010) solo se manda el acceso
            }
            if (!$sinEntrar && !$falteAviso) {
                throw new RuntimeException(t('cajeros.acceso_al_dia', ['nombre' => $empleado['nombre']]));
            }
            // El primer acceso se envía al asignarle caja: sin caja todavía no hay nada que reenviar
            $stmt = $pdo->prepare("SELECT numero_caja, nombre FROM Cajas WHERE id_cajero_asignado = ? AND id_sucursal = ? ORDER BY numero_caja LIMIT 1");
            $stmt->execute([$id, $idSucursalGerente]);
            $caja = $stmt->fetch() ?: null;
            if ($sinEntrar && $caja === null) {
                throw new RuntimeException(t('cajeros.reenviar_sin_caja', ['nombre' => $empleado['nombre']]));
            }
            $acceso = $sinEntrar ? ponerPasswordTemporal($pdo, $id) : null;
            $datosCorreo = datosParaCorreos($pdo, (int) $gerente['id_usuario'], $idSucursalGerente);
            $enviado = $falteAviso
                ? enviarAvisoPrivacidad($pdo, $id, $empleado, $datosCorreo, $acceso, true, $caja)
                : enviarAvisoPasswordRestablecida($empleado, $datosCorreo, $acceso, true);
            if (!$enviado) {
                throw new RuntimeException(t('cajeros.notificacion_fallo', ['email' => $empleado['email']]));
            }
            $mensajeExito = t($sinEntrar ? 'cajeros.acceso_reenviado' : 'cajeros.aviso_reenviado', ['email' => $empleado['email']]);

        } elseif ($action === 'restablecer_password') {
            $id = (int) ($_POST['id_usuario'] ?? 0);

            $check = $pdo->prepare("
                SELECT nombre, email, estado, debe_cambiar,
                       EXISTS (SELECT 1 FROM Cajas c WHERE c.id_cajero_asignado = u.id_usuario
                                                     AND c.id_sucursal = u.id_sucursal) AS tiene_caja
                FROM Usuarios u
                WHERE id_usuario = ? AND id_sucursal = ? AND id_rol = 3
            ");
            $check->execute([$id, $idSucursalGerente]);
            $empleado = $check->fetch();
            // Dado de baja ya no tiene acceso: no se le manda contraseña nueva
            if ($empleado === false || $empleado['estado'] === 'Baja') {
                throw new RuntimeException(t('error.cajero_invalido'));
            }
            // Si nunca ha entrado, su primer acceso llega al asignarle caja
            if ((int) $empleado['debe_cambiar'] === 1 && !(int) $empleado['tiene_caja']) {
                throw new RuntimeException(t('cajeros.reenviar_sin_caja', ['nombre' => $empleado['nombre']]));
            }
            $nombreCajero = $empleado['nombre'];

            // La temporal solo llega al cajero por correo; si no sale, "Reenviar acceso" genera otra
            $acceso = ponerPasswordTemporal($pdo, $id);
            $mensajeExito = t('cajeros.password_restablecida', ['nombre' => $nombreCajero]);

            $datosCorreo = datosParaCorreos($pdo, (int) $gerente['id_usuario'], $idSucursalGerente);
            $avisar(enviarAvisoPasswordRestablecida($empleado, $datosCorreo, $acceso), $empleado['email'], 'cajeros.password_enviada');

        } elseif ($action === 'eliminar') {
            // Eliminar permanentemente: solo después de dar de baja y si nunca trabajó
            // (sin ventas, turnos ni retiros). Ver admin/eliminar_usuario.php
            $id = (int) ($_POST['id_usuario'] ?? 0);
            $check = $pdo->prepare("
                SELECT nombre, estado FROM Usuarios
                WHERE id_usuario = ? AND id_sucursal = ? AND id_rol = 3
            ");
            $check->execute([$id, $idSucursalGerente]);
            $empleado = $check->fetch();
            if ($empleado === false) {
                throw new RuntimeException(t('error.cajero_invalido'));
            }
            if ($empleado['estado'] !== 'Baja') {
                throw new RuntimeException(t('error.eliminar_no_baja'));
            }
            try {
                eliminarCuentaDadaDeBaja($pdo, $id, 3);
            } catch (CuentaConHistorial) {
                throw new RuntimeException(t('error.eliminar_historial', ['nombre' => $empleado['nombre']]));
            }
            $mensajeExito = t('cajeros.eliminado', ['nombre' => $empleado['nombre']]);

        /* -------- Cambio de nombre o correo que pidió un cajero (sql/020) --------
           Solo se aprueba o se rechaza: lo pedido no se edita. */
        } elseif ($action === 'aprobar_datos') {
            try {
                $s = aprobarSolicitudDatos($pdo, (int) ($_POST['id_solicitud'] ?? 0), (int) $gerente['id_usuario'], 'Gerente', $idSucursalGerente);
            } catch (SolicitudDatosError $e) {
                throw new RuntimeException(t('datos.error_' . $e->clave));
            }
            $mensajeExito = t('datos.aprobada', ['nombre' => $s['nombre'] ?? $s['nombre_antes']]);
            if ($s['email'] !== null) {
                $avisar(avisarCorreoCambiado($s, 'tu gerente'), $s['email_antes'], 'datos.aviso_correo_anterior');
            }

        } elseif ($action === 'rechazar_datos') {
            try {
                $s = rechazarSolicitudDatos(
                    $pdo, (int) ($_POST['id_solicitud'] ?? 0), (int) $gerente['id_usuario'], 'Gerente', $idSucursalGerente,
                    (string) ($_POST['rechazo_titulo'] ?? ''), (string) ($_POST['rechazo_motivo'] ?? '')
                );
            } catch (SolicitudDatosError $e) {
                throw new RuntimeException(t('datos.error_' . $e->clave));
            }
            $mensajeExito = t('datos.rechazada', ['nombre' => $s['nombre_antes']]);
        }

        // PRG: tras guardar, redirigir para que F5 no reenvíe el formulario
        if ($mensajeExito !== '') {
            $_SESSION['flash'] = ['msg' => $mensajeExito, 'avisos' => $flashAvisos];
            header('Location: cajeros.php');
            exit;
        }
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errores[] = $e->getMessage();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errores[] = t('error.base_datos');
    }
}

// Si aún no se ejecutó sql/010_aviso_privacidad.sql, la página funciona sin el
// estado del aviso y avisa qué falta (antes tronaba con un error fatal).
$hayTablaAviso = true;
try {
    $pdo->query("SELECT 1 FROM avisos_privacidad LIMIT 1");
} catch (PDOException $e) {
    if ($e->getCode() !== '42S02') throw $e;
    $hayTablaAviso = false;
    $errores[] = t('cajeros.falta_tabla_aviso');
}
$columnaAviso = $hayTablaAviso
    ? "(SELECT MAX(a.aceptado_en) FROM avisos_privacidad a
        WHERE a.id_usuario = u.id_usuario AND a.version = ? AND a.aceptado_en IS NOT NULL)"
    : "NULL";
// Aceptó alguna versión: el aviso pendiente es solo la versión nueva, y su correo ya es suyo
$columnaAvisoAntes = $hayTablaAviso
    ? "EXISTS (SELECT 1 FROM avisos_privacidad a WHERE a.id_usuario = u.id_usuario AND a.aceptado_en IS NOT NULL)"
    : "0";

// Vencimiento de la contraseña temporal (sql/013); sin la columna no vence
$hayVencimiento = true;
try {
    $pdo->query("SELECT temporal_expira_en FROM Usuarios LIMIT 1");
} catch (PDOException $e) {
    if ($e->getCode() !== '42S22') throw $e;
    $hayVencimiento = false;
}
$columnaVence = $hayVencimiento ? 'u.temporal_expira_en' : 'NULL';
// Nombre(s) y apellidos por separado para el formulario de edición (sql/017)
$columnasApellidos = columnasApellidos($pdo);

$stmt = $pdo->prepare("
    SELECT u.id_usuario, u.nombre, $columnasApellidos, u.email, u.estado, u.fecha_contratacion, u.fecha_inicio_estado,
           u.debe_cambiar, $columnaVence AS temporal_expira_en,
           COUNT(v.id_venta) AS total_ventas,
           COALESCE(SUM(v.total), 0) AS monto_total,
           (SELECT GROUP_CONCAT(CONCAT_WS(' · ', c.numero_caja, c.nombre) ORDER BY c.numero_caja SEPARATOR ', ')
            FROM Cajas c WHERE c.id_cajero_asignado = u.id_usuario AND c.id_sucursal = u.id_sucursal) AS cajas,
           $columnaAviso AS aviso_aceptado,
           $columnaAvisoAntes AS aviso_alguna_vez,
           tc.numero_caja AS turno_caja, t.fecha_apertura AS turno_desde
    FROM Usuarios u
    LEFT JOIN Ventas v
           ON v.id_cajero = u.id_usuario AND v.id_sucursal = ?
          AND v.fecha_hora >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
    -- Caja que tiene abierta ahora (pieza «Ahora» de la tarjeta). Solo la más
    -- reciente: con dos turnos abiertos, el cajero saldría dos veces y con
    -- las ventas al doble
    LEFT JOIN Turnos_Caja t ON t.id_turno = (
        SELECT t2.id_turno FROM Turnos_Caja t2
        WHERE t2.id_cajero_apertura = u.id_usuario AND t2.estado = 'Abierto'
        ORDER BY t2.fecha_apertura DESC, t2.id_turno DESC
        LIMIT 1
    )
    LEFT JOIN Cajas tc ON tc.id_caja = t.id_caja
    WHERE u.id_rol = 3 AND u.id_sucursal = ?
    GROUP BY u.id_usuario, u.nombre, nombres, apellido_paterno, apellido_materno, u.email, u.estado,
             u.fecha_contratacion, u.fecha_inicio_estado, u.debe_cambiar, temporal_expira_en,
             tc.numero_caja, t.fecha_apertura
    ORDER BY u.nombre
");
avisosPublicados($pdo);   // la versión vigente puede venir del panel del admin (sql/018)
$stmt->execute(array_merge($hayTablaAviso ? [versionAviso('empleado')] : [], [$idSucursalGerente, $idSucursalGerente]));
$cajeros = $stmt->fetchAll();

// Cambios de nombre o correo que piden los cajeros de esta sucursal (vacío sin sql/020)
$solicitudesDatos = solicitudesDatosPorRevisar($pdo, 'Gerente', $idSucursalGerente);

$totalActivos = 0;
$totalSinAviso = 0; // cajeros que no pueden entrar a la caja hasta aceptar el aviso
foreach ($cajeros as &$c) {
    if ($c['estado'] === 'Activo') $totalActivos++;
    if ($hayTablaAviso && $c['estado'] !== 'Baja' && !$c['aviso_aceptado']) $totalSinAviso++;
    // Lo que le falta para entrar a la caja (lo usan la tarjeta y la tabla)
    $c['sin_entrar']  = (int) $c['debe_cambiar'] === 1;
    $c['falta_aviso'] = $hayTablaAviso && !$c['aviso_aceptado'];
    $c['vencido']     = $c['sin_entrar'] && $c['temporal_expira_en'] && strtotime($c['temporal_expira_en']) < time();
    $c['faltas']      = [];
    if ($c['estado'] !== 'Baja') {
        if ($c['falta_aviso']) {
            $c['faltas'][] = ['badge-warning', t($c['aviso_alguna_vez'] ? 'cajeros.falta_aviso_nuevo' : 'cajeros.falta_aviso'), t('cajeros.aviso_pendiente_ayuda')];
        }
        if ($c['vencido']) {
            $c['faltas'][] = ['badge-danger', t('cajeros.acceso_vencido'), t('cajeros.acceso_vencido_ayuda')];
        } elseif ($c['sin_entrar']) {
            $c['faltas'][] = ['badge-warning', $c['temporal_expira_en']
                ? t('cajeros.falta_entrar_vence', ['fecha' => fecha($c['temporal_expira_en'], false)])
                : t('cajeros.falta_entrar'), t('cajeros.acceso_pendiente_ayuda')];
        }
    }
}
unset($c);

/* ---------- Piezas de la tarjeta (ver includes/vistas.php) ---------- */
$BADGE_ESTADO = ['Activo' => 'badge-activo', 'Vacaciones' => 'badge-warning', 'Incapacidad' => 'badge-warning',
                 'Licencia' => 'badge-warning', 'Baja' => 'badge-danger'];

/** "Ahora": en caja, fuera de turno, sin caja, fuera o de baja. Corto para la tabla, completo en tarjeta y detalle. */
$ahoraCajero = static function (array $c, bool $corto): string {
    if ($c['turno_caja'] !== null) {
        $caja = t('tarjeta.en_caja', ['caja' => $c['turno_caja']]);
        return piezaAhora('en-turno', $corto ? e($caja) : '<span class="pz-linea">' . e($caja) . '</span><span class="pz-linea pz-linea--2">'
            . e(t('tarjeta.desde_dura', ['hora' => date('H:i', strtotime($c['turno_desde'])), 'dura' => duracionTurno($c['turno_desde'])])) . '</span>');
    }
    if ($c['estado'] === 'Baja') {
        return piezaAhora('', e(t($corto ? 'tarjeta.de_baja' : 'tarjeta.dado_baja')));
    }
    if ($c['estado'] !== 'Activo') {
        return piezaAhora('alerta', e($corto ? t('tarjeta.fuera')
            : ($c['cajas'] ? t('tarjeta.fuera_conserva', ['caja' => $c['cajas']]) : t('tarjeta.fuera_regresa'))));
    }
    if ($c['cajas']) {
        return piezaAhora('', e($corto ? t('tarjeta.fuera_turno') : t('tarjeta.fuera_turno_caja', ['caja' => $c['cajas']])));
    }
    return piezaAhora('', e(t($corto ? 'tarjeta.sin_caja' : 'tarjeta.sin_caja_asignada')));
};

/** Botón "Reenviar acceso": contraseña temporal nueva si aún no entra y, si falta, el enlace del aviso. */
$botonReenviar = static fn(int $id): string => '<form method="POST" class="pz-reenviar">'
    . '<input type="hidden" name="action" value="reenviar_acceso">'
    . '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">'
    . '<input type="hidden" name="id_usuario" value="' . $id . '">'
    . '<button type="submit" class="enlace-boton">' . e(t('cajeros.reenviar_acceso')) . '</button></form>';

/** Acceso: siempre una caja del mismo alto (listo, le falta o sin acceso), para que la tarjeta no cambie de forma. */
$accesoCajero = static function (array $c) use ($hayTablaAviso, $botonReenviar): string {
    if ($c['estado'] === 'Baja') {
        return '<div class="pz-acceso pz-acceso--nada">' . e(t('tarjeta.sin_acceso')) . '</div>';
    }
    if ($c['faltas']) {
        $insignias = '';
        foreach ($c['faltas'] as [$clase, $texto, $ayuda]) {
            $insignias .= '<span class="badge ' . $clase . '" title="' . e($ayuda) . '">' . e($texto) . '</span>';
        }
        return '<div class="pz-acceso pz-acceso--falta"><span class="pz-acceso-titulo">' . e(t('tarjeta.falta_titulo')) . '</span>'
            . '<div class="pz-insignias">' . $insignias . '</div>' . $botonReenviar((int) $c['id_usuario']) . '</div>';
    }
    $lineas = $hayTablaAviso ? '<li>' . e(t('tarjeta.listo_aviso', ['fecha' => fecha($c['aviso_aceptado'], false)])) . '</li>' : '';
    return '<div class="pz-acceso pz-acceso--listo"><span class="pz-acceso-titulo">✓ ' . e(t('tarjeta.listo_titulo')) . '</span>'
        . '<ul>' . $lineas . '<li>' . e(t('tarjeta.listo_entro')) . '</li></ul></div>';
};

/** Datos fijos: contratación y estado (siempre dos filas). */
$filasCajero = static fn(array $c): string => '<dl class="pz-filas">'
    . '<div><dt>' . e(t('tarjeta.contratado')) . '</dt><dd>' . e(fecha($c['fecha_contratacion'], false) . ' · ' . antiguedad($c['fecha_contratacion'])) . '</dd></div>'
    . '<div><dt>' . e($c['estado'] === 'Activo' ? t('tarjeta.estado') : t('tarjeta.estado_desde', ['estado' => t('estado_usuario.' . $c['estado'])])) . '</dt>'
    . '<dd>' . e($c['estado'] === 'Activo' ? t('tarjeta.trabajando') : fecha($c['fecha_inicio_estado'], false)) . '</dd></div></dl>';

/**
 * Acciones. $modo: 'tarjeta' (Editar y Dar de baja con nombre, contraseña con ícono),
 * 'fila' (Editar y contraseña con ícono) o 'detalle' (todo con nombre).
 */
$accionesCajero = static function (array $c, string $modo) use ($hayTablaAviso): string {
    $id = (int) $c['id_usuario'];
    $conNombre = $modo !== 'fila';
    $iconoEditar = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>';
    $iconoLlave = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6M15.5 7.5l3 3L22 7l-3-3"/></svg>';

    $partes = partesNombre($c);
    $html = '<div class="pz-acciones">'
        . '<button type="button" class="btn btn-ghost btn-sm' . ($conNombre ? ' pz-principal' : ' btn-icono') . '" onclick="abrirModalEditarCajero(this)"'
        . ' data-id="' . $id . '" data-nombre="' . e($c['nombre']) . '" data-email="' . e($c['email']) . '" data-estado="' . e($c['estado']) . '"'
        . ' data-nombres="' . e($partes['nombres']) . '" data-paterno="' . e($partes['apellido_paterno']) . '" data-materno="' . e($partes['apellido_materno']) . '"'
        . ' data-correo-bloqueado="' . ($c['aviso_alguna_vez'] || (!$hayTablaAviso && (int) $c['debe_cambiar'] === 0) ? '1' : '0') . '"'
        . ($conNombre ? '' : ' title="' . e(t('comun.editar')) . '" aria-label="' . e(t('comun.editar') . ' · ' . $c['nombre']) . '"') . '>'
        . $iconoEditar . ($conNombre ? '<span>' . e(t('comun.editar')) . '</span>' : '') . '</button>';

    if ($c['estado'] !== 'Baja') {
        $detalle = $modo === 'detalle';
        $html .= '<form method="POST" class="cajero-accion" data-confirm="' . e(t('cajeros.confirmar_password', ['nombre' => $c['nombre']])) . '"'
            . ' data-confirm-titulo="' . e(t('cajeros.restablecer_titulo')) . '" data-confirm-aceptar="' . e(t('cajeros.restablecer')) . '">'
            . '<input type="hidden" name="action" value="restablecer_password">'
            . '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">'
            . '<input type="hidden" name="id_usuario" value="' . $id . '">'
            . '<button type="submit" class="btn btn-ghost btn-sm' . ($detalle ? '' : ' btn-icono') . '"'
            . ($detalle ? '' : ' title="' . e(t('cajeros.restablecer_titulo')) . '" aria-label="' . e(t('cajeros.restablecer_titulo') . ' · ' . $c['nombre']) . '"') . '>'
            . $iconoLlave . ($detalle ? '<span>' . e(t('cajeros.restablecer_titulo')) . '</span>' : '') . '</button></form>';
    }

    if ($c['estado'] === 'Activo') {
        // Con la caja abierta no se puede dar de baja (el servidor también lo rechaza)
        $html .= '<button type="button" class="btn btn-danger btn-sm pz-fin" onclick="abrirModalBaja(this)"'
            . ' data-id="' . $id . '" data-nombre="' . e($c['nombre']) . '"'
            . ($c['turno_caja'] !== null ? ' disabled title="' . e(t('tarjeta.bloqueado_turno')) . '"' : '') . '>'
            . e(t('cajeros.baja_titulo')) . '</button>';
    } else {
        $html .= '<form method="POST" class="cajero-accion" data-confirm="' . e(t('cajeros.confirmar_reactivar', ['nombre' => $c['nombre']])) . '"'
            . ' data-confirm-titulo="' . e(t('cajeros.reactivar_titulo')) . '" data-confirm-aceptar="' . e(t('cajeros.reactivar_titulo')) . '">'
            . '<input type="hidden" name="action" value="cambiar_estado">'
            . '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">'
            . '<input type="hidden" name="id_usuario" value="' . $id . '">'
            . '<button type="submit" class="btn btn-success btn-sm pz-fin">' . e(t('cajeros.reactivar_titulo')) . '</button></form>';
    }

    // Ya dado de baja: se puede eliminar permanentemente (ocupa el lugar de la llave,
    // así la tarjeta no cambia de forma). El servidor rechaza si tiene historial.
    if ($c['estado'] === 'Baja') {
        $detalle = $modo === 'detalle';
        $iconoBasura = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/></svg>';
        $html .= '<form method="POST" class="cajero-accion" data-confirm="' . e(t('cajeros.confirmar_eliminar')) . '"'
            . ' data-confirm-titulo="' . e(t('cajeros.eliminar_titulo', ['nombre' => $c['nombre']])) . '" data-confirm-aceptar="' . e(t('cajeros.eliminar_aceptar')) . '" data-confirm-peligroso="1">'
            . '<input type="hidden" name="action" value="eliminar">'
            . '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">'
            . '<input type="hidden" name="id_usuario" value="' . $id . '">'
            . '<button type="submit" class="btn btn-danger btn-sm' . ($detalle ? '' : ' btn-icono') . '"'
            . ($detalle ? '' : ' title="' . e(t('cajeros.eliminar')) . '" aria-label="' . e(t('cajeros.eliminar') . ' · ' . $c['nombre']) . '"') . '>'
            . $iconoBasura . ($detalle ? '<span>' . e(t('cajeros.eliminar')) . '</span>' : '') . '</button></form>';
    }
    return $html . '</div>';
};

$pageTitle    = t('cajeros.titulo');
require __DIR__ . '/header.php';
?>

<div class="page-header">
    <div>
        <h1><?= e(t('cajeros.titulo')) ?></h1>
        <p>
            <?= e(t('cajeros.resumen', ['activos' => numero($totalActivos), 'total' => numero(count($cajeros))])) ?>
            <?php if ($totalSinAviso > 0): ?>
                · <span class="texto-pendiente"><?= e(tn('cajeros.n_sin_aviso', $totalSinAviso)) ?></span>
            <?php endif; ?>
            <?php if ($solicitudesDatos): ?>
                · <a class="texto-pendiente" href="#solicitudes-datos"><?= e(tn('datos.n_por_revisar', count($solicitudesDatos))) ?></a>
            <?php endif; ?>
        </p>
    </div>
    <button type="button" class="btn btn-primary" onclick="abrirModalNuevoCajero()">+ <?= e(t('cajeros.nuevo')) ?></button>
</div>

<?php if ($exito): ?>
    <div class="alert alert-success"><?= htmlspecialchars($exito) ?></div>
<?php endif; ?>
<?php foreach ($avisosCorreo as $aviso): ?>
    <div class="alert <?= $aviso['ok'] ? 'alert-success' : 'alert-warning' ?>"><?= e($aviso['msg']) ?></div>
<?php endforeach; ?>
<?php foreach ($errores as $error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endforeach; ?>

<?php // Cambios de nombre o correo por aprobar: se aprueban o se rechazan, no se editan ?>
<?php if ($solicitudesDatos): ?>
    <section class="panel solicitudes-datos" id="solicitudes-datos" aria-labelledby="tituloSolicitudesDatos">
        <div class="solicitudes-datos__cabecera">
            <h2 id="tituloSolicitudesDatos"><?= e(t('datos.titulo')) ?> <span class="badge badge-warning"><?= count($solicitudesDatos) ?></span></h2>
            <p><?= e(t('datos.ayuda')) ?></p>
        </div>
        <ul class="solicitudes-datos__lista">
            <?php foreach ($solicitudesDatos as $s): $idSolicitud = (int) $s['id_solicitud']; ?>
                <li class="solicitud-datos">
                    <div class="pz-avatar" aria-hidden="true"><?= e(inicialesSolicitante($s)) ?></div>
                    <div class="solicitud-datos__cuerpo">
                        <div class="solicitud-datos__quien">
                            <strong><?= e($s['nombre_antes']) ?></strong>
                            <span class="texto-tenue"><?= e(t('datos.enviada', ['fecha' => fecha($s['creado_en'])])) ?></span>
                        </div>
                        <dl class="solicitud-datos__cambios">
                            <?php foreach (cambiosSolicitudDatos($s) as $c): ?>
                                <div>
                                    <dt><?= e(t('datos.campo_' . $c['campo'])) ?></dt>
                                    <dd><span class="solicitud-datos__antes"><?= e($c['antes']) ?></span><span class="solicitud-datos__flecha" aria-hidden="true">→</span><span class="sr-only"><?= e(t('datos.cambia_a')) ?></span><strong><?= e($c['despues']) ?></strong></dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>
                    </div>
                    <div class="solicitud-datos__acciones">
                        <button type="button" class="btn btn-ghost btn-sm" onclick="abrirRechazoDatos(this)"
                                data-id="<?= $idSolicitud ?>" data-texto="<?= e(t('datos.rechazar_texto', ['nombre' => $s['nombre_antes']])) ?>"><?= e(t('datos.rechazar')) ?></button>
                        <form method="POST" data-confirm="<?= e(t('datos.confirmar_aprobar', ['nombre' => $s['nombre_antes']])) ?>"
                              data-confirm-titulo="<?= e(t('datos.aprobar_titulo')) ?>" data-confirm-aceptar="<?= e(t('datos.aprobar')) ?>">
                            <input type="hidden" name="action" value="aprobar_datos">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="id_solicitud" value="<?= $idSolicitud ?>">
                            <button type="submit" class="btn btn-primary btn-sm"><?= e(t('datos.aprobar')) ?></button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<?php if (empty($cajeros)): ?>
    <div class="panel">
        <div class="empty-state">
            <?= e(t('cajeros.vacio')) ?>
        </div>
    </div>
<?php else: ?>
    <div class="vista-barra"><?= selectorVistas('gridCajeros', 'cajeros') ?></div>
    <?php // Tarjetas: íconos, columnas y galería (initVistas las reacomoda; el CSS cambia su forma) ?>
    <div class="tarjetas vista-coleccion" id="gridCajeros" data-vista="iconos">
        <?php foreach ($cajeros as $c): $id = (int) $c['id_usuario']; ?>
            <article class="tarjeta<?= $c['estado'] === 'Activo' ? '' : ' is-apagada' ?>"
                     data-columna="<?= e(t('estado_usuario.' . $c['estado'])) ?>"
                     data-columna-orden="<?= (int) array_search($c['estado'], $ESTADOS_VALIDOS, true) ?>">
                <header class="pz-identidad">
                    <div class="pz-avatar" aria-hidden="true"><?= e(iniciales($c)) ?></div>
                    <div class="pz-nombres">
                        <h2 class="pz-nombre"><?= e($c['nombre']) ?></h2>
                        <span class="pz-sub"><?= e($c['email']) ?></span>
                    </div>
                    <span class="badge pz-estado <?= $BADGE_ESTADO[$c['estado']] ?? 'badge-inactivo' ?>"><?= e(t('estado_usuario.' . $c['estado'])) ?></span>
                </header>
                <?= $ahoraCajero($c, false) ?>
                <?= $filasCajero($c) ?>
                <?= $accesoCajero($c) ?>
                <a class="pz-cifras" href="ventas_sucursal.php?periodo=mes&amp;id_cajero=<?= $id ?>">
                    <span class="pz-cifras-cab"><span><?= e(t('cajeros.este_mes')) ?></span><span class="pz-ir"><?= e(t('cajeros.ver_ventas')) ?></span></span>
                    <span class="pz-cifras-val">
                        <span><strong><?= numero((int) $c['total_ventas']) ?></strong> <?= e(tn('cajeros.n_ventas', (int) $c['total_ventas'])) ?></span>
                        <span><strong><?= dinero($c['monto_total']) ?></strong> <?= e(t('cajeros.vendido')) ?></span>
                    </span>
                </a>
                <?= $accionesCajero($c, 'tarjeta') ?>
            </article>
        <?php endforeach; ?>
    </div>

    <?php // Lista: tabla corta; al tocar una fila se abre debajo su detalle ?>
    <div class="vista-tabla">
        <table class="tabla-tarjetas">
            <thead><tr>
                <th><?= e(t('tarjeta.cajero')) ?></th>
                <th><?= e(t('tarjeta.estado')) ?></th>
                <th><?= e(t('tarjeta.ahora')) ?></th>
                <th><?= e(t('tarjeta.acceso')) ?></th>
                <th class="num"><?= e(t('cajeros.este_mes')) ?></th>
                <th><span class="sr-only"><?= e(t('tarjeta.acciones')) ?></span></th>
            </tr></thead>
            <tbody>
            <?php foreach ($cajeros as $c): $id = (int) $c['id_usuario']; ?>
                <tr class="fila-desplegable<?= $c['estado'] === 'Activo' ? '' : ' is-apagada' ?>" aria-expanded="false">
                    <td><div class="fila-id">
                        <button type="button" class="fila-abrir" aria-expanded="false" aria-label="<?= e(t('tarjeta.ver_detalle', ['nombre' => $c['nombre']])) ?>">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 6 15 12 9 18"/></svg>
                        </button>
                        <div class="pz-avatar" aria-hidden="true"><?= e(iniciales($c)) ?></div>
                        <div class="pz-nombres"><span class="pz-nombre"><?= e($c['nombre']) ?></span><span class="pz-sub"><?= e($c['email']) ?></span></div>
                    </div></td>
                    <td><span class="badge <?= $BADGE_ESTADO[$c['estado']] ?? 'badge-inactivo' ?>"><?= e(t('estado_usuario.' . $c['estado'])) ?></span></td>
                    <td><?= $ahoraCajero($c, true) ?></td>
                    <td><?php if ($c['estado'] === 'Baja'): ?><span class="texto-tenue">—</span>
                        <?php elseif ($c['faltas']): ?><span class="badge badge-warning"><?= e(tn('tarjeta.falta_n', count($c['faltas']))) ?></span>
                        <?php else: ?><span class="texto-ok">✓ <?= e(t('tarjeta.listo')) ?></span><?php endif; ?></td>
                    <td class="num"><strong><?= dinero($c['monto_total']) ?></strong><span class="fila-segunda"><?= e(tn('tarjeta.ventas', (int) $c['total_ventas'])) ?></span></td>
                    <td class="fila-acciones"><?= $accionesCajero($c, 'fila') ?></td>
                </tr>
                <tr class="fila-detalle" hidden>
                    <td colspan="6">
                        <div class="det-rejilla">
                            <?= datoDetalle(t('tarjeta.ahora'), $ahoraCajero($c, false)) ?>
                            <?= datoDetalle(t('tarjeta.caja_asignada'), '<span>' . e($c['cajas'] ?: t('tarjeta.sin_caja')) . '</span>') ?>
                            <?= datoDetalle(t('tarjeta.contratado'), '<span>' . e(fecha($c['fecha_contratacion'], false) . ' · ' . antiguedad($c['fecha_contratacion'])) . '</span>') ?>
                            <?= datoDetalle(t('tarjeta.correo'), '<span class="det-largo">' . e($c['email']) . '</span>') ?>
                            <?= datoDetalle(t('cajeros.este_mes'), '<span>' . e(tn('tarjeta.monto_en_ventas', (int) $c['total_ventas'], ['monto' => dinero($c['monto_total'])])) . '</span>'
                                . '<a class="enlace-boton" href="ventas_sucursal.php?periodo=mes&amp;id_cajero=' . $id . '">' . e(t('tarjeta.ver_sus_ventas')) . '</a>') ?>
                            <?= datoDetalle(t('tarjeta.estado'), '<span>' . e(t('estado_usuario.' . $c['estado'])
                                . ($c['estado'] === 'Activo' ? '' : ' · ' . t('tarjeta.desde_fecha', ['fecha' => fecha($c['fecha_inicio_estado'], false)]))) . '</span>') ?>
                            <?= datoDetalle(t('tarjeta.acceso'), $accesoCajero($c), 'det-lateral') ?>
                        </div>
                        <?= str_replace('class="pz-acciones"', 'class="pz-acciones det-acciones"', $accionesCajero($c, 'detalle')) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- Modal: Nuevo / Editar cajero -->
<div class="modal-overlay" id="modalCajero">
    <div class="modal-box modal-cajero" role="dialog" aria-modal="true" aria-labelledby="tituloModalCajero">
        <h2 id="tituloModalCajero"><?= e(t('cajeros.nuevo')) ?></h2>
        <form id="formCajero" method="POST" onsubmit="this.querySelector('[type=submit]').disabled = true;">
            <input type="hidden" name="action" value="crear">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="id_usuario" value="">

            <fieldset class="modal-seccion">
                <legend><?= e(t('cajeros.seccion_datos')) ?></legend>
                <?php // Nombre(s) y apellidos por separado; el servidor arma el completo (admin/reglas_nombre.php) ?>
                <div class="form-group">
                    <label for="cajeroNombres"><?= e(t('cajeros.campo_nombres')) ?></label>
                    <input type="text" id="cajeroNombres" name="nombres" placeholder="<?= e(t('cajeros.campo_nombres_ej')) ?>"
                           maxlength="<?= NOMBRE_MAX_NOMBRES ?>" autocomplete="off" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="cajeroPaterno"><?= e(t('cajeros.campo_paterno')) ?></label>
                        <input type="text" id="cajeroPaterno" name="apellido_paterno" placeholder="<?= e(t('cajeros.campo_paterno_ej')) ?>"
                               maxlength="<?= NOMBRE_MAX_APELLIDO ?>" autocomplete="off" aria-describedby="ayudaApellidos">
                    </div>
                    <div class="form-group">
                        <label for="cajeroMaterno"><?= e(t('cajeros.campo_materno')) ?></label>
                        <input type="text" id="cajeroMaterno" name="apellido_materno" placeholder="<?= e(t('cajeros.campo_materno_ej')) ?>"
                               maxlength="<?= NOMBRE_MAX_APELLIDO ?>" autocomplete="off" aria-describedby="ayudaApellidos">
                    </div>
                </div>
                <?php // Basta con un apellido, pero no los dos vacíos (paginas.js: validarApellidos) ?>
                <small class="form-ayuda form-ayuda--fila" id="ayudaApellidos"><?= e(t('cajeros.apellidos_ayuda')) ?></small>
                <div class="form-group">
                    <label for="cajeroEmail"><?= e(t('cajeros.campo_email')) ?></label>
                    <input type="email" id="cajeroEmail" name="email" placeholder="<?= e(t('cajeros.campo_email_ej')) ?>"
                           maxlength="100" autocomplete="off" required>
                    <small class="form-ayuda" id="ayudaAviso"><?= e(t('cajeros.email_aviso_ayuda')) ?></small>
                    <small class="form-ayuda" id="ayudaCorreoBloqueado" hidden><?= e(t('error.email_bloqueado')) ?></small>
                </div>
            </fieldset>

            <fieldset class="modal-seccion">
                <legend><?= e(t('cajeros.seccion_laboral')) ?></legend>
                <div class="form-row">
                    <div class="form-group">
                        <label for="cajeroEstado"><?= e(t('cat.col_estado')) ?></label>
                        <select id="cajeroEstado" name="estado" required>
                            <?php foreach ($ESTADOS_VALIDOS as $est): ?>
                                <option value="<?= $est ?>"><?= e(t('estado_usuario.' . $est)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" id="grupoFechaContratacion">
                        <label for="cajeroFecha"><?= e(t('cajeros.campo_fecha')) ?></label>
                        <input type="date" id="cajeroFecha" name="fecha_contratacion" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <div class="form-group" id="grupoMotivoBaja" hidden>
                    <label for="cajeroMotivoBaja"><?= e(t('cajeros.motivo_baja')) ?></label>
                    <select id="cajeroMotivoBaja" name="motivo_baja">
                        <option value=""><?= e(t('cajeros.elegir_motivo')) ?></option>
                        <?php foreach (array_keys(MOTIVOS_BAJA) as $motivo): ?>
                            <option value="<?= $motivo ?>"><?= e(t('motivo_baja.' . $motivo)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <small class="form-ayuda" id="ayudaEstado" hidden></small>
            </fieldset>

            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="cerrarModal('modalCajero')"><?= e(t('comun.cancelar')) ?></button>
                <button type="submit" class="btn btn-primary"><?= e(t('comun.guardar')) ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: dar de baja (con motivo, que va en el correo al empleado) -->
<div class="modal-overlay" id="modalBaja">
    <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="tituloModalBaja">
        <h2 id="tituloModalBaja"><?= e(t('cajeros.baja_titulo')) ?></h2>
        <p class="modal-ayuda" id="textoModalBaja"></p>
        <form id="formBaja" method="POST" onsubmit="this.querySelector('[type=submit]').disabled = true;">
            <input type="hidden" name="action" value="cambiar_estado">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="id_usuario" value="">
            <div class="form-group">
                <label for="bajaMotivo"><?= e(t('cajeros.motivo_baja')) ?></label>
                <select id="bajaMotivo" name="motivo_baja" required>
                    <option value=""><?= e(t('cajeros.elegir_motivo')) ?></option>
                    <?php foreach (array_keys(MOTIVOS_BAJA) as $motivo): ?>
                        <option value="<?= $motivo ?>"><?= e(t('motivo_baja.' . $motivo)) ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="form-ayuda"><?= e(t('cajeros.baja_ayuda')) ?></small>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="cerrarModal('modalBaja')"><?= e(t('comun.cancelar')) ?></button>
                <button type="submit" class="btn btn-danger-solid"><?= e(t('cajeros.baja_titulo')) ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: rechazar un cambio de nombre o correo (título obligatorio, motivo opcional) -->
<div class="modal-overlay" id="modalRechazoDatos">
    <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="tituloRechazoDatos">
        <h2 id="tituloRechazoDatos"><?= e(t('datos.rechazar_titulo')) ?></h2>
        <p class="modal-ayuda" id="textoRechazoDatos"></p>
        <form id="formRechazoDatos" method="POST" onsubmit="this.querySelector('[type=submit]').disabled = true;">
            <input type="hidden" name="action" value="rechazar_datos">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="id_solicitud" value="">
            <div class="form-group">
                <label for="rechazoTitulo"><?= e(t('datos.titulo_rechazo')) ?></label>
                <input type="text" id="rechazoTitulo" name="rechazo_titulo" maxlength="<?= SOLICITUD_DATOS_TITULO_MAX ?>" required autocomplete="off">
                <div class="sugerencias" role="group" aria-label="<?= e(t('datos.sugerencias')) ?>">
                    <?php foreach (['identificacion', 'correo', 'escritura'] as $sugerencia): ?>
                        <button type="button" class="chip-sugerencia" data-titulo-sugerido="<?= e(t('datos.sugerencia_' . $sugerencia)) ?>"><?= e(t('datos.sugerencia_' . $sugerencia)) ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="form-group">
                <label for="rechazoMotivo"><?= e(t('datos.motivo_rechazo')) ?></label>
                <textarea id="rechazoMotivo" name="rechazo_motivo" maxlength="<?= SOLICITUD_DATOS_MOTIVO_MAX ?>" rows="3"></textarea>
                <small class="form-ayuda"><?= e(t('datos.motivo_ayuda')) ?></small>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="cerrarModal('modalRechazoDatos')"><?= e(t('comun.cancelar')) ?></button>
                <button type="submit" class="btn btn-danger-solid"><?= e(t('datos.rechazar')) ?></button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>