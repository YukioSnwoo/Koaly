<?php
require_once __DIR__ . '/guardia.php';
require_once __DIR__ . '/includes/correos_empleado.php';
require_once __DIR__ . '/../login/reglas_contrasena.php';
require_once __DIR__ . '/includes/vistas.php';
require_once __DIR__ . '/includes/turnos.php';

/**
 * ¿El gerente puede cambiar el correo de este cajero? Solo mientras el cajero
 * nunca ha aceptado el aviso de privacidad: aceptarlo es abrir el enlace que
 * llegó a su buzón, así que desde ahí el correo es suyo. Después solo él (en
 * «Mi perfil») o la administración lo cambian; si no, el gerente podría poner
 * su propio correo, restablecer la contraseña y entrar a la caja como él.
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
 * Calcula las iniciales de un nombre (máx. 2 letras).
 */
function iniciales(string $nombre): string
{
    $partes = preg_split('/\s+/', trim($nombre));
    $ini = '';
    foreach (array_slice($partes, 0, 2) as $p) {
        $ini .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $ini !== '' ? $ini : '?';
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
            $nombre   = trim($_POST['nombre'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $estado   = $_POST['estado'] ?? 'Activo';
            $fechaContratacion = $_POST['fecha_contratacion'] ?? date('Y-m-d');

            if ($nombre === '' || mb_strlen($nombre) > 100) {
                throw new RuntimeException(t('error.nombre_cajero'));
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
                throw new RuntimeException(t('error.email_invalido'));
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

            $check = $pdo->prepare("SELECT 1 FROM Usuarios WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch() !== false) {
                throw new RuntimeException(t('error.email_duplicado'));
            }

            // La contraseña la genera el sistema y solo llega al cajero por correo: el gerente
            // nunca la ve. El hash de relleno se reemplaza enseguida con la temporal.
            $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO Usuarios
                    (nombre, email, contrasena_hash, id_rol, id_sucursal, estado,
                     fecha_inicio_estado, fecha_contratacion, debe_cambiar)
                VALUES (?, ?, ?, 3, ?, ?, CURDATE(), ?, 1)
            ");
            $stmt->execute([$nombre, $email, $hash, $idSucursalGerente, $estado, $fechaContratacion]);
            $idNuevo = (int) $pdo->lastInsertId();
            $acceso  = ponerPasswordTemporal($pdo, $idNuevo);
            $mensajeExito = t('cajeros.creado');

            // Bienvenida con la cuenta, la contraseña temporal y el enlace del aviso de privacidad
            // (sin aceptarlo no entra a la caja). Si no sale, "Reenviar acceso" genera otra.
            $datosCorreo = datosParaCorreos($pdo, (int) $gerente['id_usuario'], $idSucursalGerente);
            $avisar(enviarAvisoPrivacidad($pdo, $idNuevo, ['nombre' => $nombre, 'email' => $email], $datosCorreo, $acceso),
                $email, 'cajeros.aviso_enviado');

        } elseif ($action === 'editar') {
            $id     = (int) ($_POST['id_usuario'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $email  = trim($_POST['email'] ?? '');
            $estado = $_POST['estado'] ?? 'Activo';
            $motivoBaja = (string) ($_POST['motivo_baja'] ?? '');

            if ($id <= 0) {
                throw new RuntimeException(t('error.cajero_invalido'));
            }
            if ($nombre === '' || mb_strlen($nombre) > 100) {
                throw new RuntimeException(t('error.nombre_cajero'));
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
                throw new RuntimeException(t('error.email_invalido'));
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
                SELECT nombre, email, estado, debe_cambiar FROM Usuarios
                WHERE id_usuario = ? AND id_sucursal = ? AND id_rol = 3
            ");
            $check->execute([$id, $idSucursalGerente]);
            $empleado = $check->fetch();
            if ($empleado === false || $empleado['estado'] === 'Baja') {
                throw new RuntimeException(t('error.cajero_invalido'));
            }
            $sinEntrar = (int) $empleado['debe_cambiar'] === 1;
            try {
                $falteAviso = !avisoAceptado($pdo, $id);
            } catch (PDOException $e) {
                $falteAviso = false;   // sin la tabla del aviso (sql/010) solo se manda el acceso
            }
            if (!$sinEntrar && !$falteAviso) {
                throw new RuntimeException(t('cajeros.acceso_al_dia', ['nombre' => $empleado['nombre']]));
            }
            $acceso = $sinEntrar ? ponerPasswordTemporal($pdo, $id) : null;
            $datosCorreo = datosParaCorreos($pdo, (int) $gerente['id_usuario'], $idSucursalGerente);
            $enviado = $falteAviso
                ? enviarAvisoPrivacidad($pdo, $id, $empleado, $datosCorreo, $acceso, true)
                : enviarAvisoPasswordRestablecida($empleado, $datosCorreo, $acceso, true);
            if (!$enviado) {
                throw new RuntimeException(t('cajeros.notificacion_fallo', ['email' => $empleado['email']]));
            }
            $mensajeExito = t($sinEntrar ? 'cajeros.acceso_reenviado' : 'cajeros.aviso_reenviado', ['email' => $empleado['email']]);

        } elseif ($action === 'restablecer_password') {
            $id = (int) ($_POST['id_usuario'] ?? 0);

            $check = $pdo->prepare("
                SELECT nombre, email FROM Usuarios
                WHERE id_usuario = ? AND id_sucursal = ? AND id_rol = 3
            ");
            $check->execute([$id, $idSucursalGerente]);
            $empleado = $check->fetch();
            if ($empleado === false) {
                throw new RuntimeException(t('error.cajero_invalido'));
            }
            $nombreCajero = $empleado['nombre'];

            // La temporal solo llega al cajero por correo; si no sale, "Reenviar acceso" genera otra
            $acceso = ponerPasswordTemporal($pdo, $id);
            $mensajeExito = t('cajeros.password_restablecida', ['nombre' => $nombreCajero]);

            $datosCorreo = datosParaCorreos($pdo, (int) $gerente['id_usuario'], $idSucursalGerente);
            $avisar(enviarAvisoPasswordRestablecida($empleado, $datosCorreo, $acceso), $empleado['email'], 'cajeros.password_enviada');
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

$stmt = $pdo->prepare("
    SELECT u.id_usuario, u.nombre, u.email, u.estado, u.fecha_contratacion, u.fecha_inicio_estado,
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
    -- Caja que tiene abierta ahora (pieza «Ahora» de la tarjeta)
    LEFT JOIN Turnos_Caja t ON t.id_cajero_apertura = u.id_usuario AND t.estado = 'Abierto'
    LEFT JOIN Cajas tc ON tc.id_caja = t.id_caja
    WHERE u.id_rol = 3 AND u.id_sucursal = ?
    GROUP BY u.id_usuario, u.nombre, u.email, u.estado, u.fecha_contratacion, u.fecha_inicio_estado,
             u.debe_cambiar, temporal_expira_en, tc.numero_caja, t.fecha_apertura
    ORDER BY u.nombre
");
$stmt->execute(array_merge($hayTablaAviso ? [AVISO_PRIVACIDAD_VERSION] : [], [$idSucursalGerente, $idSucursalGerente]));
$cajeros = $stmt->fetchAll();

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

    $html = '<div class="pz-acciones">'
        . '<button type="button" class="btn btn-ghost btn-sm' . ($conNombre ? ' pz-principal' : ' btn-icono') . '" onclick="abrirModalEditarCajero(this)"'
        . ' data-id="' . $id . '" data-nombre="' . e($c['nombre']) . '" data-email="' . e($c['email']) . '" data-estado="' . e($c['estado']) . '"'
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
                    <div class="pz-avatar" aria-hidden="true"><?= e(iniciales($c['nombre'])) ?></div>
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
                        <div class="pz-avatar" aria-hidden="true"><?= e(iniciales($c['nombre'])) ?></div>
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
                <div class="form-group">
                    <label for="cajeroNombre"><?= e(t('cajeros.campo_nombre')) ?></label>
                    <input type="text" id="cajeroNombre" name="nombre" placeholder="<?= e(t('cajeros.campo_nombre_ej')) ?>"
                           maxlength="100" autocomplete="off" required>
                </div>
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

<?php require __DIR__ . '/footer.php'; ?>