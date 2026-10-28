<?php
require_once __DIR__ . '/guardia.php';
require_once __DIR__ . '/includes/turnos.php';
require_once __DIR__ . '/includes/vistas.php';

$ESTADOS_VALIDOS = ['Activa', 'Inactiva', 'Mantenimiento'];

/**
 * Valida el cajero elegido para una caja y devuelve su id (o null).
 * Regla: una caja Activa necesita un cajero asignado y en estado Activo.
 */
function validarCajeroDeCaja(PDO $pdo, int $idCajero, int $idSucursal, string $estadoCaja): ?int
{
    if ($idCajero <= 0) {
        if ($estadoCaja === 'Activa') {
            throw new RuntimeException(t('error.caja_sin_cajero'));
        }
        return null;
    }

    $check = $pdo->prepare("
        SELECT estado FROM Usuarios
        WHERE id_usuario = ? AND id_sucursal = ? AND id_rol = 3
    ");
    $check->execute([$idCajero, $idSucursal]);
    $estadoCajero = $check->fetchColumn();
    if ($estadoCajero === false) {
        throw new RuntimeException(t('error.cajero_invalido_sucursal'));
    }
    if ($estadoCaja === 'Activa' && $estadoCajero !== 'Activo') {
        throw new RuntimeException(t('error.cajero_no_activo', ['estado' => t('estado_usuario.' . $estadoCajero)]));
    }
    return $idCajero;
}

$errores = [];
$exito   = '';

/* ------------------------------------------------------------
   Flash (PRG)
   ------------------------------------------------------------ */
if (!empty($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $exito = $flash['msg'] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if (!csrfValido()) {
            throw new RuntimeException(t('error.formulario_expirado'));
        }

        /* -------- Crear -------- */
        if ($action === 'crear') {
            $numeroCaja   = trim($_POST['numero_caja'] ?? '');
            $nombre       = trim($_POST['nombre'] ?? '');
            $estado       = $_POST['estado'] ?? 'Activa';
            $idCajero     = (int) ($_POST['id_cajero_asignado'] ?? 0);

            if ($numeroCaja === '' || mb_strlen($numeroCaja) > 20) {
                throw new RuntimeException(t('error.numero_caja'));
            }
            if (mb_strlen($nombre) > 50) {
                throw new RuntimeException(t('error.nombre_caja'));
            }
            if (!in_array($estado, $ESTADOS_VALIDOS, true)) {
                throw new RuntimeException(t('error.estado_invalido'));
            }
            $idCajero = validarCajeroDeCaja($pdo, $idCajero, $idSucursalGerente, $estado);

            if ($idCajero !== null) {
                $check = $pdo->prepare("SELECT numero_caja FROM Cajas WHERE id_cajero_asignado = ? AND id_sucursal = ?");
                $check->execute([$idCajero, $idSucursalGerente]);
                $otraCaja = $check->fetchColumn();
                if ($otraCaja !== false) {
                    throw new RuntimeException(t('error.cajero_ya_asignado', ['caja' => $otraCaja]));
                }
            }

            $check = $pdo->prepare("
                SELECT 1 FROM Cajas WHERE id_sucursal = ? AND numero_caja = ?
            ");
            $check->execute([$idSucursalGerente, $numeroCaja]);
            if ($check->fetch() !== false) {
                throw new RuntimeException(t('error.caja_duplicada'));
            }

            $stmt = $pdo->prepare("
                INSERT INTO Cajas
                    (id_sucursal, numero_caja, nombre, estado, id_cajero_asignado)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $idSucursalGerente,
                $numeroCaja,
                $nombre !== '' ? $nombre : null,
                $estado,
                $idCajero
            ]);

            $_SESSION['flash'] = ['msg' => t('cajas.creada')];
            header('Location: cajas.php');
            exit;

        /* -------- Editar -------- */
        } elseif ($action === 'editar') {
            $id           = (int) ($_POST['id_caja'] ?? 0);
            $numeroCaja   = trim($_POST['numero_caja'] ?? '');
            $nombre       = trim($_POST['nombre'] ?? '');
            $estado       = $_POST['estado'] ?? 'Activa';
            $idCajero     = (int) ($_POST['id_cajero_asignado'] ?? 0);

            if ($id <= 0) {
                throw new RuntimeException(t('error.caja_invalida'));
            }
            if ($numeroCaja === '' || mb_strlen($numeroCaja) > 20) {
                throw new RuntimeException(t('error.numero_caja'));
            }
            if (mb_strlen($nombre) > 50) {
                throw new RuntimeException(t('error.nombre_caja'));
            }
            if (!in_array($estado, $ESTADOS_VALIDOS, true)) {
                throw new RuntimeException(t('error.estado_invalido'));
            }

            // IDOR: verificar que la caja pertenece a la sucursal del gerente
            $check = $pdo->prepare("
                SELECT estado, id_cajero_asignado FROM Cajas WHERE id_caja = ? AND id_sucursal = ?
            ");
            $check->execute([$id, $idSucursalGerente]);
            $cajaAntes = $check->fetch();
            if ($cajaAntes === false) {
                throw new RuntimeException(t('error.caja_invalida'));
            }

            $idCajero = validarCajeroDeCaja($pdo, $idCajero, $idSucursalGerente, $estado);

            // Con el turno abierto solo se puede cambiar el número o el nombre
            $cambiaCajero = (int) $cajaAntes['id_cajero_asignado'] !== (int) $idCajero;
            if (($cambiaCajero || $estado !== 'Activa')
                && ($abierta = cajaConTurnoAbierto($pdo, $idSucursalGerente, $id)) !== null) {
                throw new RuntimeException(t('turnos.error_caja_abierta', ['caja' => $abierta]));
            }
            // Y el cajero elegido no puede estar a media jornada en otra caja
            if ($cambiaCajero && $idCajero !== null
                && ($abierta = cajaConTurnoAbierto($pdo, $idSucursalGerente, null, $idCajero)) !== null) {
                throw new RuntimeException(t('turnos.error_caja_abierta', ['caja' => $abierta]));
            }

            $check = $pdo->prepare("
                SELECT 1 FROM Cajas
                WHERE id_sucursal = ? AND numero_caja = ? AND id_caja <> ?
            ");
            $check->execute([$idSucursalGerente, $numeroCaja, $id]);
            if ($check->fetch() !== false) {
                throw new RuntimeException(t('error.caja_duplicada'));
            }

            // Desasignar de otra caja + actualizar esta: todo o nada
            $pdo->beginTransaction();

            // Si el cajero ya estaba en OTRA caja, esa caja se queda sin cajero:
            // se desasigna y pasa a Inactiva (una caja activa necesita cajero).
            $mensajeExtra = '';
            if ($idCajero !== null) {
                $stmt = $pdo->prepare("
                    UPDATE Cajas
                    SET id_cajero_asignado = NULL,
                        estado = IF(estado = 'Activa', 'Inactiva', estado)
                    WHERE id_cajero_asignado = ? AND id_sucursal = ? AND id_caja <> ?
                ");
                $stmt->execute([$idCajero, $idSucursalGerente, $id]);
                if ($stmt->rowCount() > 0) {
                    $mensajeExtra = ' ' . t('cajas.anterior_inactiva');
                }
            }

            $stmt = $pdo->prepare("
                UPDATE Cajas
                SET numero_caja = ?, nombre = ?, estado = ?, id_cajero_asignado = ?
                WHERE id_caja = ?
            ");
            $stmt->execute([
                $numeroCaja,
                $nombre !== '' ? $nombre : null,
                $estado,
                $idCajero,
                $id
            ]);

            $pdo->commit();

            $_SESSION['flash'] = ['msg' => t('cajas.actualizada') . $mensajeExtra];
            header('Location: cajas.php');
            exit;

        /* -------- Cambiar estado -------- */
        } elseif ($action === 'cambiar_estado') {
            $id = (int) ($_POST['id_caja'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException(t('error.caja_invalida'));
            }

            $check = $pdo->prepare("
                SELECT c.estado, c.id_cajero_asignado
                FROM Cajas c
                WHERE c.id_caja = ? AND c.id_sucursal = ?
            ");
            $check->execute([$id, $idSucursalGerente]);
            $caja = $check->fetch();
            if ($caja === false) {
                throw new RuntimeException(t('error.caja_invalida'));
            }

            $nuevo = $caja['estado'] === 'Activa' ? 'Inactiva' : 'Activa';
            if ($nuevo !== 'Activa' && ($abierta = cajaConTurnoAbierto($pdo, $idSucursalGerente, $id)) !== null) {
                throw new RuntimeException(t('turnos.error_caja_abierta', ['caja' => $abierta]));
            }
            if ($nuevo === 'Activa') {
                validarCajeroDeCaja($pdo, (int) $caja['id_cajero_asignado'], $idSucursalGerente, 'Activa');
            }
            $stmt = $pdo->prepare("UPDATE Cajas SET estado = ? WHERE id_caja = ?");
            $stmt->execute([$nuevo, $id]);

            $_SESSION['flash'] = ['msg' => $nuevo === 'Activa' ? t('cajas.activada') : t('cajas.desactivada')];
            header('Location: cajas.php');
            exit;

        /* -------- Fondo base (lo que se deja al cerrar) y límite de efectivo (alerta de excedente) -------- */
        } elseif ($action === 'fondo') {
            $id = (int) ($_POST['id_caja'] ?? 0);
            $fondoCentavos = montoFormularioEnCentavos($_POST['fondo_fijo'] ?? '');
            if ($fondoCentavos === null || $fondoCentavos > 10000000) {
                throw new RuntimeException(t('turnos.error_fondo'));
            }
            $limiteCentavos = montoFormularioEnCentavos($_POST['limite_efectivo'] ?? '');
            if ($limiteCentavos === null || $limiteCentavos <= $fondoCentavos || $limiteCentavos > 100000000) {
                throw new RuntimeException(t('turnos.error_limite'));
            }
            $stmt = $pdo->prepare("UPDATE Cajas SET fondo_fijo = ?, limite_efectivo = ? WHERE id_caja = ? AND id_sucursal = ?");
            $stmt->execute([
                number_format($fondoCentavos / 100, 2, '.', ''),
                number_format($limiteCentavos / 100, 2, '.', ''),
                $id,
                $idSucursalGerente,
            ]);

            $_SESSION['flash'] = ['msg' => t('turnos.fondo_guardado')];
            header('Location: cajas.php');
            exit;

        /* -------- Eliminar -------- */
        } elseif ($action === 'eliminar') {
            $id = (int) ($_POST['id_caja'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException(t('error.caja_invalida'));
            }

            $check = $pdo->prepare("
                SELECT 1 FROM Cajas WHERE id_caja = ? AND id_sucursal = ?
            ");
            $check->execute([$id, $idSucursalGerente]);
            if ($check->fetch() === false) {
                throw new RuntimeException(t('error.caja_invalida'));
            }

            if (($abierta = cajaConTurnoAbierto($pdo, $idSucursalGerente, $id)) !== null) {
                throw new RuntimeException(t('turnos.error_caja_abierta', ['caja' => $abierta]));
            }

            // Una caja con ventas no se borra: perdería el historial (y la FK lo impide).
            $check = $pdo->prepare("SELECT COUNT(*) FROM Ventas WHERE id_caja = ?");
            $check->execute([$id]);
            $numVentas = (int) $check->fetchColumn();
            if ($numVentas > 0) {
                throw new RuntimeException(tn('error.caja_con_ventas', $numVentas));
            }

            $stmt = $pdo->prepare("DELETE FROM Cajas WHERE id_caja = ?");
            $stmt->execute([$id]);

            $_SESSION['flash'] = ['msg' => t('cajas.eliminada')];
            header('Location: cajas.php');
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

/* ------------------------------------------------------------
   Datos
   ------------------------------------------------------------ */
$stmt = $pdo->prepare("
    SELECT c.id_caja, c.numero_caja, c.nombre, c.estado,
           c.id_cajero_asignado, c.fondo_fijo, c.limite_efectivo,
           t.fecha_apertura AS turno_desde, ut.nombre AS turno_cajero,
           u.nombre AS cajero_nombre,
           u.estado AS cajero_estado,
           c.creado_en,
           (SELECT COUNT(*) FROM Ventas v WHERE v.id_caja = c.id_caja) AS num_ventas,
           (SELECT COALESCE(SUM(v.total), 0) FROM Ventas v
             WHERE v.id_caja = c.id_caja AND v.fecha_hora >= CURDATE()) AS monto_hoy,
           (SELECT COUNT(*) FROM Ventas v
             WHERE v.id_caja = c.id_caja AND v.fecha_hora >= CURDATE()) AS ventas_hoy,
           (SELECT COUNT(*) FROM Turnos_Caja tr
             WHERE tr.id_caja = c.id_caja AND tr.estado_revision = 'Pendiente') AS por_recibir
    FROM Cajas c
    LEFT JOIN Usuarios u ON u.id_usuario = c.id_cajero_asignado
    LEFT JOIN Turnos_Caja t ON t.id_caja = c.id_caja AND t.estado = 'Abierto'
    LEFT JOIN Usuarios ut ON ut.id_usuario = t.id_cajero_apertura
    WHERE c.id_sucursal = ?
    ORDER BY c.numero_caja
");
$stmt->execute([$idSucursalGerente]);
$cajas = $stmt->fetchAll();

// Cortes que los cajeros entregaron y falta recibir
$stmt = $pdo->prepare("SELECT COUNT(*) FROM Turnos_Caja WHERE id_sucursal = ? AND estado_revision = 'Pendiente'");
$stmt->execute([$idSucursalGerente]);
$cortesPendientes = (int) $stmt->fetchColumn();

// Cajeros activos + los que ya tienen caja aunque no estén activos (vacaciones,
// licencia…): si no aparecieran, editar la caja los desasignaría sin avisar.
$stmt = $pdo->prepare("
    SELECT id_usuario, nombre, estado
    FROM Usuarios
    WHERE id_rol = 3 AND id_sucursal = ?
      AND (estado = 'Activo'
           OR id_usuario IN (SELECT id_cajero_asignado FROM Cajas
                             WHERE id_sucursal = ? AND id_cajero_asignado IS NOT NULL))
    ORDER BY nombre
");
$stmt->execute([$idSucursalGerente, $idSucursalGerente]);
$cajerosDisponibles = $stmt->fetchAll();

// Mapa de cajeros ya asignados a alguna caja de esta sucursal
// (para avisar cuando se intente reasignar)
$stmt = $pdo->prepare("
    SELECT id_cajero_asignado, id_caja, numero_caja
    FROM Cajas
    WHERE id_sucursal = ? AND id_cajero_asignado IS NOT NULL
");
$stmt->execute([$idSucursalGerente]);
$cajerosConCaja = [];
foreach ($stmt->fetchAll() as $row) {
    $cajerosConCaja[(int) $row['id_cajero_asignado']] = [
        'id_caja'     => (int) $row['id_caja'],
        'numero_caja' => $row['numero_caja'],
    ];
}

$totalActivas = 0;
foreach ($cajas as $c) {
    if ($c['estado'] === 'Activa') $totalActivas++;
}

function inicialesCorta(string $nombre): string
{
    $partes = preg_split('/\s+/', trim($nombre));
    $ini = '';
    foreach (array_slice($partes, 0, 2) as $p) {
        $ini .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $ini !== '' ? $ini : '?';
}

/* ---------- Piezas de la tarjeta (ver includes/vistas.php) ---------- */
$BADGE_ESTADO = ['Activa' => 'badge-activo', 'Inactiva' => 'badge-inactivo', 'Mantenimiento' => 'badge-warning'];

/** "Ahora": abierta (por quién, desde cuándo y cuánto lleva) o cerrada. Corto para la tabla. */
$ahoraCaja = static function (array $c, bool $corto): string {
    if (!$c['turno_desde']) {
        return piezaAhora('', e(t('tarjeta.cerrada')));
    }
    if ($corto) {
        return piezaAhora('en-turno', e(t('tarjeta.abierta')));
    }
    $quien = strtok((string) ($c['turno_cajero'] ?? '—'), ' ');
    return piezaAhora('en-turno', '<span class="pz-linea">' . e(t('tarjeta.abierta_por', ['nombre' => $quien])) . '</span>'
        . '<span class="pz-linea pz-linea--2">' . e(t('tarjeta.desde_dura', ['hora' => date('H:i', strtotime($c['turno_desde'])), 'dura' => duracionTurno($c['turno_desde'])])) . '</span>');
};

/** Alertas de la caja: [clase de insignia, texto]. */
$alertasDeCaja = static function (array $c): array {
    $a = [];
    if ($c['estado'] === 'Activa' && empty($c['id_cajero_asignado'])) {
        $a[] = ['badge-danger', t('tarjeta.activa_sin_cajero')];
    }
    if (!empty($c['id_cajero_asignado']) && $c['cajero_estado'] !== 'Activo') {
        $a[] = ['badge-warning', t('tarjeta.cajero_fuera', ['estado' => mb_strtolower(t('estado_usuario.' . $c['cajero_estado']))])];
    }
    if ((int) $c['por_recibir'] > 0) {
        $a[] = ['badge-info', tn('tarjeta.cortes_por_recibir', (int) $c['por_recibir'])];
    }
    return $a;
};
$alertasCaja = static function (array $c) use ($alertasDeCaja): string {
    $html = '';
    foreach ($alertasDeCaja($c) as [$clase, $texto]) {
        $html .= '<span class="badge ' . $clase . '">' . e($texto) . '</span>';
    }
    return '<div class="pz-alertas">' . ($html ?: '<span class="texto-tenue">' . e(t('tarjeta.sin_alertas')) . '</span>') . '</div>';
};

$cajeroCaja = static fn(array $c): string => !empty($c['id_cajero_asignado'])
    ? '<div class="pz-cajero"><div class="pz-avatar pz-avatar--chico" aria-hidden="true">' . e(inicialesCorta($c['cajero_nombre'])) . '</div>'
      . '<div class="pz-nombres"><span class="pz-sub">' . e(t('tarjeta.cajero_asignado')) . '</span><span class="pz-cajero-nombre">' . e($c['cajero_nombre']) . '</span></div></div>'
    : '<div class="pz-cajero"><div class="pz-nombres"><span class="pz-sub">' . e(t('tarjeta.cajero_asignado')) . '</span><span class="pz-cajero-nombre texto-tenue">' . e(t('cajas.sin_asignar')) . '</span></div></div>';

$efectivoCaja = static fn(array $c): string => '<dl class="pz-filas">'
    . '<div><dt>' . e(t('turnos.fondo_base')) . '</dt><dd>' . dinero($c['fondo_fijo']) . '</dd></div>'
    . '<div><dt>' . e(t('turnos.limite')) . '</dt><dd>' . dinero($c['limite_efectivo']) . '</dd></div></dl>';

/**
 * Acciones. $modo: 'tarjeta' (Editar y Activar/Desactivar con nombre, el resto con ícono),
 * 'fila' (Editar y efectivo con ícono) o 'detalle' (todo con nombre).
 * Con turno abierto, Desactivar y Eliminar salen apagados (el servidor también lo rechaza).
 */
$accionesCaja = static function (array $c, string $modo): string {
    $id = (int) $c['id_caja'];
    $conNombre = $modo !== 'fila';
    $detalle = $modo === 'detalle';
    $abierta = (bool) $c['turno_desde'];
    $esActiva = $c['estado'] === 'Activa';
    $bloqueo = $abierta ? ' disabled title="' . e(t('tarjeta.bloqueado_turno')) . '"' : '';
    $icono = static fn(string $d): string => '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';

    $html = '<div class="pz-acciones">'
        . '<button type="button" class="btn btn-ghost btn-sm' . ($conNombre ? ' pz-principal' : ' btn-icono') . '" onclick="abrirModalEditarCaja(this)"'
        . ' data-id="' . $id . '" data-numero="' . e($c['numero_caja']) . '" data-nombre="' . e($c['nombre'] ?? '') . '"'
        . ' data-estado="' . e($c['estado']) . '" data-cajero="' . (int) ($c['id_cajero_asignado'] ?? 0) . '"'
        . ($conNombre ? '' : ' title="' . e(t('comun.editar')) . '" aria-label="' . e(t('comun.editar') . ' · ' . $c['numero_caja']) . '"') . '>'
        . $icono('<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>') . ($conNombre ? '<span>' . e(t('comun.editar')) . '</span>' : '') . '</button>'
        . '<button type="button" class="btn btn-ghost btn-sm' . ($detalle ? '' : ' btn-icono') . '" onclick="abrirModalFondo(this)"'
        . ' data-id="' . $id . '" data-numero="' . e($c['numero_caja']) . '"'
        . ' data-fondo="' . e(number_format((float) $c['fondo_fijo'], 2, '.', '')) . '" data-limite="' . e(number_format((float) $c['limite_efectivo'], 2, '.', '')) . '"'
        . ($detalle ? '' : ' title="' . e(t('tarjeta.fondo_limite')) . '" aria-label="' . e(t('tarjeta.fondo_limite') . ' · ' . $c['numero_caja']) . '"') . '>'
        . $icono('<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/>') . ($detalle ? '<span>' . e(t('tarjeta.fondo_limite')) . '</span>' : '') . '</button>'
        . '<form method="POST" class="cajero-accion"'
        . ' data-confirm="' . e(t($esActiva ? 'cajas.confirmar_desactivar' : 'cajas.confirmar_activar', ['caja' => $c['numero_caja']])) . '"'
        . ' data-confirm-titulo="' . e(t($esActiva ? 'cajas.desactivar_titulo' : 'cajas.activar_titulo')) . '"'
        . ' data-confirm-aceptar="' . e(t($esActiva ? 'comun.desactivar' : 'comun.activar')) . '"' . ($esActiva ? ' data-confirm-peligroso="1"' : '') . '>'
        . '<input type="hidden" name="action" value="cambiar_estado">'
        . '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">'
        . '<input type="hidden" name="id_caja" value="' . $id . '">'
        . '<button type="submit" class="btn btn-sm pz-fin ' . ($esActiva ? 'btn-danger' : 'btn-success') . '"' . ($esActiva ? $bloqueo : '') . '>'
        . e(t($esActiva ? 'comun.desactivar' : 'comun.activar')) . '</button></form>';

    if ((int) $c['num_ventas'] === 0) {
        $html .= '<form method="POST" class="cajero-accion" data-confirm="' . e(t('cajas.confirmar_eliminar', ['caja' => $c['numero_caja']])) . '"'
            . ' data-confirm-titulo="' . e(t('cajas.eliminar_titulo')) . '" data-confirm-aceptar="' . e(t('comun.eliminar')) . '" data-confirm-peligroso="1">'
            . '<input type="hidden" name="action" value="eliminar">'
            . '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">'
            . '<input type="hidden" name="id_caja" value="' . $id . '">'
            . '<button type="submit" class="btn btn-danger btn-sm' . ($detalle ? '' : ' btn-icono') . '"' . $bloqueo
            . ($detalle ? '' : ' aria-label="' . e(t('cajas.eliminar_titulo') . ' · ' . $c['numero_caja']) . '"' . ($abierta ? '' : ' title="' . e(t('cajas.eliminar_titulo')) . '"')) . '>'
            . $icono('<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>') . ($detalle ? '<span>' . e(t('comun.eliminar')) . '</span>' : '') . '</button></form>';
    }
    return $html . '</div>';
};

$pageTitle    = t('cajas.titulo');
require __DIR__ . '/header.php';
?>

<div class="page-header">
    <div>
        <h1><?= e(t('cajas.titulo')) ?></h1>
        <p>
            <?= e(t('cajas.resumen', ['activas' => numero($totalActivas), 'total' => numero(count($cajas))])) ?>
        </p>
    </div>
    <div class="acciones">
        <a href="cortes.php" class="btn btn-ghost">
            <?= e(t('turnos.ver_cortes')) ?>
            <?php if ($cortesPendientes > 0): ?>
                <span class="badge badge-warning"><?= e(tn('turnos.n_pendientes', $cortesPendientes)) ?></span>
            <?php endif; ?>
        </a>
        <button type="button" class="btn btn-primary" onclick="abrirModalNuevaCaja()">+ <?= e(t('cajas.nueva')) ?></button>
    </div>
</div>

<?php if ($exito): ?>
    <div class="alert alert-success"><?= htmlspecialchars($exito) ?></div>
<?php endif; ?>
<?php foreach ($errores as $error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endforeach; ?>

<?php if (empty($cajas)): ?>
    <div class="panel">
        <div class="empty-state">
            <?= e(t('cajas.vacio', ['nueva' => t('cajas.nueva')])) ?>
        </div>
    </div>
<?php else: ?>
    <div class="vista-barra"><?= selectorVistas('gridCajas', 'cajas') ?></div>
    <?php // Tarjetas: íconos, columnas y galería (initVistas las reacomoda; el CSS cambia su forma) ?>
    <div class="tarjetas vista-coleccion" id="gridCajas" data-vista="iconos">
        <?php foreach ($cajas as $c): $id = (int) $c['id_caja']; ?>
            <article class="tarjeta<?= $c['estado'] === 'Activa' ? '' : ' is-apagada' ?>"
                     data-columna="<?= e(t('estado_caja.' . $c['estado'])) ?>"
                     data-columna-orden="<?= (int) array_search($c['estado'], $ESTADOS_VALIDOS, true) ?>">
                <header class="pz-identidad">
                    <div class="pz-tecla" aria-hidden="true"><?= e($c['numero_caja']) ?></div>
                    <div class="pz-nombres">
                        <h2 class="pz-nombre<?= empty($c['nombre']) ? ' texto-tenue' : '' ?>"><?= e($c['nombre'] ?: t('cajas.sin_nombre')) ?></h2>
                        <span class="pz-sub"><?= e(t('cajas.caja_n', ['n' => $c['numero_caja']]) . ' · ' . tn('cajas.ventas_total', (int) $c['num_ventas'])) ?></span>
                    </div>
                    <span class="badge pz-estado <?= $BADGE_ESTADO[$c['estado']] ?? 'badge-inactivo' ?>"><?= e(t('estado_caja.' . $c['estado'])) ?></span>
                </header>
                <?= $ahoraCaja($c, false) ?>
                <?= $alertasCaja($c) ?>
                <?= $cajeroCaja($c) ?>
                <a class="pz-cifras" href="ventas_sucursal.php?periodo=hoy&amp;id_caja=<?= $id ?>">
                    <span class="pz-cifras-cab"><span><?= e(t('tarjeta.hoy')) ?></span><span class="pz-ir"><?= e(t('cajeros.ver_ventas')) ?></span></span>
                    <span class="pz-cifras-val">
                        <span><strong><?= dinero($c['monto_hoy']) ?></strong> <?= e(t('cajeros.vendido')) ?></span>
                        <span><strong><?= numero((int) $c['ventas_hoy']) ?></strong> <?= e(tn('cajeros.n_ventas', (int) $c['ventas_hoy'])) ?></span>
                    </span>
                </a>
                <?= $efectivoCaja($c) ?>
                <?= $accionesCaja($c, 'tarjeta') ?>
            </article>
        <?php endforeach; ?>
    </div>

    <?php // Lista: tabla corta; al tocar una fila se abre debajo su detalle ?>
    <div class="vista-tabla">
        <table class="tabla-tarjetas">
            <thead><tr>
                <th><?= e(t('tarjeta.caja')) ?></th>
                <th><?= e(t('tarjeta.estado')) ?></th>
                <th><?= e(t('tarjeta.ahora')) ?></th>
                <th><?= e(t('tarjeta.cajero')) ?></th>
                <th class="num"><?= e(t('tarjeta.hoy')) ?></th>
                <th><?= e(t('tarjeta.alertas')) ?></th>
                <th><span class="sr-only"><?= e(t('tarjeta.acciones')) ?></span></th>
            </tr></thead>
            <tbody>
            <?php foreach ($cajas as $c): $id = (int) $c['id_caja']; $alertas = $alertasDeCaja($c); ?>
                <tr class="fila-desplegable<?= $c['estado'] === 'Activa' ? '' : ' is-apagada' ?>" aria-expanded="false">
                    <td><div class="fila-id">
                        <button type="button" class="fila-abrir" aria-expanded="false" aria-label="<?= e(t('tarjeta.ver_detalle', ['nombre' => t('cajas.caja_n', ['n' => $c['numero_caja']])])) ?>">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 6 15 12 9 18"/></svg>
                        </button>
                        <div class="pz-tecla" aria-hidden="true"><?= e($c['numero_caja']) ?></div>
                        <div class="pz-nombres"><span class="pz-nombre<?= empty($c['nombre']) ? ' texto-tenue' : '' ?>"><?= e($c['nombre'] ?: t('cajas.sin_nombre')) ?></span></div>
                    </div></td>
                    <td><span class="badge <?= $BADGE_ESTADO[$c['estado']] ?? 'badge-inactivo' ?>"><?= e(t('estado_caja.' . $c['estado'])) ?></span></td>
                    <td><?= $ahoraCaja($c, true) ?></td>
                    <td><?php if (!empty($c['id_cajero_asignado'])): ?><span class="fila-cajero"><span class="pz-avatar pz-avatar--chico" aria-hidden="true"><?= e(inicialesCorta($c['cajero_nombre'])) ?></span><?= e($c['cajero_nombre']) ?></span>
                        <?php else: ?><span class="texto-tenue"><?= e(t('cajas.sin_asignar')) ?></span><?php endif; ?></td>
                    <td class="num"><strong><?= dinero($c['monto_hoy']) ?></strong><span class="fila-segunda"><?= e(tn('tarjeta.ventas', (int) $c['ventas_hoy'])) ?></span></td>
                    <td><?php if ($alertas): $peor = in_array('badge-danger', array_column($alertas, 0), true) ? 'badge-danger' : (in_array('badge-warning', array_column($alertas, 0), true) ? 'badge-warning' : 'badge-info'); ?>
                        <span class="badge <?= $peor ?>"><?= e(tn('tarjeta.n_alertas', count($alertas))) ?></span>
                        <?php else: ?><span class="texto-tenue">—</span><?php endif; ?></td>
                    <td class="fila-acciones"><?= $accionesCaja($c, 'fila') ?></td>
                </tr>
                <tr class="fila-detalle" hidden>
                    <td colspan="7">
                        <div class="det-rejilla">
                            <?= datoDetalle(t('tarjeta.ahora'), $ahoraCaja($c, false)) ?>
                            <?= datoDetalle(t('tarjeta.cajero_asignado'), '<span>' . e(!empty($c['id_cajero_asignado'])
                                ? $c['cajero_nombre'] . ($c['cajero_estado'] !== 'Activo' ? ' (' . mb_strtolower(t('estado_usuario.' . $c['cajero_estado'])) . ')' : '')
                                : t('cajas.sin_asignar')) . '</span>') ?>
                            <?= datoDetalle(t('tarjeta.efectivo'), $efectivoCaja($c)) ?>
                            <?= datoDetalle(t('tarjeta.hoy'), '<span>' . e(tn('tarjeta.monto_en_ventas', (int) $c['ventas_hoy'], ['monto' => dinero($c['monto_hoy'])])) . '</span>'
                                . '<a class="enlace-boton" href="ventas_sucursal.php?periodo=hoy&amp;id_caja=' . $id . '">' . e(t('tarjeta.ver_ventas_caja')) . '</a>') ?>
                            <?= datoDetalle(t('tarjeta.historial'), '<span>' . e(tn('cajas.ventas_total', (int) $c['num_ventas'])) . '</span>') ?>
                            <?= datoDetalle(t('tarjeta.alertas'), $alertasCaja($c), 'det-lateral') ?>
                        </div>
                        <?= str_replace('class="pz-acciones"', 'class="pz-acciones det-acciones"', $accionesCaja($c, 'detalle')) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- Modal: Nueva / Editar caja -->
<div class="modal-overlay" id="modalCaja">
    <div class="modal-box">
        <h2 id="tituloModalCaja"><?= e(t('cajas.nueva')) ?></h2>
        <form id="formCaja" method="POST">
            <input type="hidden" name="action" value="crear">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
            <input type="hidden" name="id_caja" value="">

            <div class="form-row">
                <div class="form-group">
                    <label><?= e(t('cajas.campo_numero')) ?></label>
                    <input type="text" name="numero_caja" placeholder="<?= e(t('cajas.campo_numero_ej')) ?>" maxlength="20" required>
                </div>
                <div class="form-group">
                    <label><?= e(t('cat.col_estado')) ?></label>
                    <select name="estado" required>
                        <?php foreach ($ESTADOS_VALIDOS as $est): ?>
                            <option value="<?= $est ?>"><?= e(t('estado_caja.' . $est)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label><?= e(t('cajas.campo_nombre')) ?></label>
                <input type="text" name="nombre" placeholder="<?= e(t('cajas.campo_nombre_ej')) ?>" maxlength="50">
            </div>

            <div class="form-group">
                <label><?= e(t('cajas.campo_cajero')) ?></label>
                <select name="id_cajero_asignado">
                    <option value="0"><?= e(t('cajas.sin_asignar')) ?></option>
                    <?php foreach ($cajerosDisponibles as $cj):
                        $info = $cajerosConCaja[(int) $cj['id_usuario']] ?? null;
                    ?>
                        <option value="<?= (int) $cj['id_usuario'] ?>"
                                data-caja-actual="<?= $info ? htmlspecialchars($info['numero_caja'], ENT_QUOTES) : '' ?>"
                                data-caja-id="<?= $info ? (int) $info['id_caja'] : 0 ?>">
                            <?= e($cj['nombre']) ?><?= $cj['estado'] !== 'Activo' ? ' (' . e(t('estado_usuario.' . $cj['estado'])) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div id="avisoReasignacion" class="aviso-reasignacion" aria-live="polite"></div>
                <small class="texto-tenue" style="display:block; font-size:0.75rem; margin-top:0.3rem;">
                    <?= e(t('cajas.campo_cajero_ayuda')) ?>
                </small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="cerrarModal('modalCaja')"><?= e(t('comun.cancelar')) ?></button>
                <button type="submit" class="btn btn-primary"><?= e(t('comun.guardar')) ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: fondo base y límite de efectivo de una caja -->
<div class="modal-overlay" id="modalFondo">
    <div class="modal-box modal-box--sm">
        <h2 id="tituloFondo"></h2>
        <p class="modal-ayuda"><?= e(t('turnos.fondo_ayuda')) ?></p>
        <form method="POST" id="formFondo">
            <input type="hidden" name="action" value="fondo">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
            <input type="hidden" name="id_caja" value="">
            <div class="form-group">
                <label for="inputFondo"><?= e(t('turnos.fondo_base')) ?></label>
                <input type="number" id="inputFondo" name="fondo_fijo" min="0" max="100000" step="0.01" inputmode="decimal" required>
            </div>
            <div class="form-group">
                <label for="inputLimite"><?= e(t('turnos.limite')) ?></label>
                <input type="number" id="inputLimite" name="limite_efectivo" min="0" max="1000000" step="0.01" inputmode="decimal" required aria-describedby="ayudaLimite">
                <p class="modal-ayuda" id="ayudaLimite"><?= e(t('turnos.limite_ayuda')) ?></p>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="cerrarModal('modalFondo')"><?= e(t('comun.cancelar')) ?></button>
                <button type="submit" class="btn btn-primary"><?= e(t('comun.guardar')) ?></button>
            </div>
        </form>
    </div>
</div>

<script>
    const TITULO_FONDO = <?= json_encode(t('turnos.fondo_titulo'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;

    function abrirModalFondo(boton) {
        const form = document.getElementById('formFondo');
        form.id_caja.value = boton.dataset.id;
        form.fondo_fijo.value = boton.dataset.fondo;
        form.limite_efectivo.value = boton.dataset.limite;
        document.getElementById('tituloFondo').textContent = TITULO_FONDO.replace('{caja}', boton.dataset.numero);
        abrirModal('modalFondo');
        form.fondo_fijo.select();
    }
</script>

<?php require __DIR__ . '/footer.php'; ?>