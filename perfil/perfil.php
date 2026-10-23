<?php
/**
 * Menú de perfil común de admin, gerente y cajero (esquina superior derecha):
 * botón con el nombre y un engranaje que despliega foto, nombre, puesto,
 * "Mi perfil", notificaciones, modo claro/oscuro, idioma y cerrar sesión.
 *
 * Cada panel lo pinta con menuPerfil() en su barra superior y enlaza
 * perfil/perfil.css y perfil/perfil.js. Los endpoints son perfil/guardar.php
 * y perfil/notificaciones.php.
 *
 * Las notificaciones no tienen tabla propia: salen de las solicitudes de
 * producto y de los turnos de caja de los últimos 30 días, según el rol.
 * Las columnas foto y notificaciones_vistas_en vienen de sql/011_perfil.sql;
 * sin ellas el menú funciona igual, solo sin foto ni avisos de "nueva".
 */

require_once __DIR__ . '/../admin/csrf.php';

const PERFIL_CARPETA_FOTOS       = 'Imagenes/perfiles';
const PERFIL_DIAS_NOTIFICACIONES = 30;
const PERFIL_MAX_NOTIFICACIONES  = 30;
const PERFIL_IDIOMAS             = ['es' => 'Español', 'en' => 'English'];

function perfilTextos(): array
{
    static $textos = null;
    return $textos ??= require __DIR__ . '/textos.php';
}

function perfilIdioma(): string
{
    $idioma = (string) ($_SESSION['idioma'] ?? 'es');
    return isset(perfilTextos()[$idioma]) ? $idioma : 'es';
}

/** Texto del menú en el idioma de la sesión; {nombre} se reemplaza con $vars. */
function perfilT(string $clave, array $vars = []): string
{
    $textos = perfilTextos();
    $texto = $textos[perfilIdioma()][$clave] ?? $textos['es'][$clave] ?? $clave;
    foreach ($vars as $k => $v) {
        $texto = str_replace('{' . $k . '}', (string) $v, $texto);
    }
    return $texto;
}

function perfilE(?string $texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

/**
 * Datos del usuario para el menú, o null si no existe. Si aún no se corre
 * sql/011_perfil.sql, foto y notificaciones_vistas_en llegan en null.
 */
function perfilUsuario(PDO $pdo, int $idUsuario): ?array
{
    $sql = "
        SELECT u.id_usuario, u.nombre, u.email, u.id_rol, u.estado, u.id_sucursal, %s
               s.nombre AS sucursal
        FROM Usuarios u
        LEFT JOIN Sucursales s ON s.id_sucursal = u.id_sucursal
        WHERE u.id_usuario = ?
    ";
    try {
        $stmt = $pdo->prepare(sprintf($sql, 'u.foto, u.notificaciones_vistas_en,'));
        $stmt->execute([$idUsuario]);
    } catch (PDOException $e) {
        $stmt = $pdo->prepare(sprintf($sql, 'NULL AS foto, NULL AS notificaciones_vistas_en,'));
        $stmt->execute([$idUsuario]);
    }
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    return $usuario ?: null;
}

/** "AB" a partir del nombre, para cuando no hay foto. */
function perfilIniciales(string $nombre): string
{
    $partes = preg_split('/\s+/', trim($nombre), -1, PREG_SPLIT_NO_EMPTY);
    $iniciales = '';
    foreach (array_slice($partes, 0, 2) as $parte) {
        $iniciales .= mb_strtoupper(mb_substr($parte, 0, 1));
    }
    return $iniciales !== '' ? $iniciales : '?';
}

/** Ruta de la foto vista desde un panel (admin/, gerente/, cajero/), o null. */
function perfilFotoUrl(?string $foto): ?string
{
    return $foto ? '../' . $foto : null;
}

/** Datos del usuario que necesita el navegador después de guardar el perfil. */
function perfilParaJs(array $usuario): array
{
    return [
        'nombre'    => $usuario['nombre'],
        'email'     => $usuario['email'],
        'foto'      => perfilFotoUrl($usuario['foto']),
        'iniciales' => perfilIniciales($usuario['nombre']),
    ];
}

function perfilAvatar(array $usuario, string $clase): string
{
    $foto = perfilFotoUrl($usuario['foto']);
    return '<span class="perfil-avatar ' . $clase . '" data-perfil-avatar>'
        . '<img alt=""' . ($foto ? ' src="' . perfilE($foto) . '"' : ' hidden') . '>'
        . '<span class="perfil-avatar__iniciales"' . ($foto ? ' hidden' : '') . '>' . perfilE(perfilIniciales($usuario['nombre'])) . '</span>'
        . '</span>';
}

/* ------------------------------------------------------------
   Notificaciones
   ------------------------------------------------------------ */

function perfilDinero($n): string
{
    return '$' . number_format(abs((float) $n), 2, '.', ',');
}

function perfilCaja(array $fila): string
{
    return trim((string) ($fila['caja_nombre'] ?? '')) !== ''
        ? $fila['caja_nombre']
        : perfilT('notif.caja', ['n' => $fila['numero_caja']]);
}

function perfilResultado(array $fila): string
{
    return perfilT('resultado.' . $fila['resultado'], ['monto' => perfilDinero($fila['diferencia'])]);
}

function perfilTonoCorte(array $fila): string
{
    if ($fila['resultado'] === 'Faltante') return 'peligro';
    if ($fila['resultado'] === 'Sobrante' || (int) $fila['requiere_revision'] === 1) return 'aviso';
    return 'ok';
}

/** Ejecuta una consulta; si la tabla aún no existe en esta base, no hay notificaciones de ese tipo. */
function perfilConsultar(PDO $pdo, string $sql, array $params): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Notificaciones del usuario, de la más nueva a la más vieja:
 * [['texto', 'detalle', 'tono' (ok|peligro|aviso|info), 'enlace', 'fecha', 'nueva']].
 * Las fechas se comparan en hora de México (notificaciones.php fija la zona).
 *   Admin:   solicitudes nuevas, reenviadas o canceladas por los gerentes,
 *            y cortes de caja con diferencia en cualquier sucursal.
 *   Gerente: respuestas del admin a sus solicitudes y cortes de sus cajeros.
 *   Cajero:  cortes suyos que el gerente ya recibió.
 */
function perfilNotificaciones(PDO $pdo, array $usuario): array
{
    $desde = (new DateTimeImmutable('-' . PERFIL_DIAS_NOTIFICACIONES . ' days'))->format('Y-m-d H:i:s');
    $items = [];

    switch ((int) $usuario['id_rol']) {
        case 1:
            $filas = perfilConsultar($pdo, "
                SELECT s.tipo, s.estado, s.nombre_producto, s.creado_en AS fecha, su.nombre AS sucursal
                FROM Solicitudes_Productos s
                INNER JOIN Sucursales su ON su.id_sucursal = s.id_sucursal
                WHERE s.creado_en >= ?
            ", [$desde]);
            foreach ($filas as $f) {
                $items[] = [
                    'texto'  => perfilT(($f['tipo'] ?? 'Agregar') === 'Eliminar' ? 'notif.sol_eliminar' : 'notif.sol_agregar',
                                        ['sucursal' => $f['sucursal'], 'producto' => $f['nombre_producto']]),
                    'tono'   => $f['estado'] === 'Pendiente' ? 'aviso' : 'info',
                    // La bandeja abre en "Por revisar"; estado vacío = todas
                    'enlace' => 'inventario.php?vista=solicitudes' . ($f['estado'] === 'Pendiente' ? '' : '&estado='),
                    'fecha'  => $f['fecha'],
                ];
            }

            $filas = perfilConsultar($pdo, "
                SELECT h.accion, h.nota, h.creado_en AS fecha, s.nombre_producto, su.nombre AS sucursal
                FROM Solicitudes_Productos_Historial h
                INNER JOIN Solicitudes_Productos s ON s.id_solicitud = h.id_solicitud
                INNER JOIN Sucursales su ON su.id_sucursal = s.id_sucursal
                WHERE h.rol = 'Gerente' AND h.accion IN ('Editada', 'Cancelada') AND h.creado_en >= ?
            ", [$desde]);
            foreach ($filas as $f) {
                $cancelada = $f['accion'] === 'Cancelada';
                $items[] = [
                    'texto'   => perfilT($cancelada ? 'notif.sol_cancelada' : 'notif.sol_reenviada',
                                         ['sucursal' => $f['sucursal'], 'producto' => $f['nombre_producto']]),
                    'detalle' => $f['nota'],
                    'tono'    => $cancelada ? 'info' : 'aviso',
                    'enlace'  => 'inventario.php?vista=solicitudes&estado=' . ($cancelada ? 'Cancelada' : 'Pendiente'),
                    'fecha'   => $f['fecha'],
                ];
            }

            $filas = perfilConsultar($pdo, "
                SELECT t.resultado, t.diferencia, t.requiere_revision, t.fecha_cierre AS fecha,
                       c.numero_caja, c.nombre AS caja_nombre, su.nombre AS sucursal
                FROM Turnos_Caja t
                INNER JOIN Cajas c ON c.id_caja = t.id_caja
                INNER JOIN Sucursales su ON su.id_sucursal = t.id_sucursal
                WHERE t.estado = 'Cerrado' AND t.requiere_revision = 1 AND t.fecha_cierre >= ?
            ", [$desde]);
            foreach ($filas as $f) {
                $items[] = [
                    'texto' => perfilT('notif.corte_diferencia', [
                        'resultado' => perfilResultado($f), 'sucursal' => $f['sucursal'], 'caja' => perfilCaja($f),
                    ]),
                    'tono'  => perfilTonoCorte($f),
                    'fecha' => $f['fecha'],
                ];
            }
            break;

        case 2:
            $filas = perfilConsultar($pdo, "
                SELECT h.accion, h.nota, h.creado_en AS fecha, s.nombre_producto
                FROM Solicitudes_Productos_Historial h
                INNER JOIN Solicitudes_Productos s ON s.id_solicitud = h.id_solicitud
                WHERE h.rol = 'Admin' AND s.id_sucursal = ? AND h.creado_en >= ?
            ", [$usuario['id_sucursal'], $desde]);
            $porAccion = [
                'Aprobada'  => ['notif.sol_aprobada', 'ok'],
                'Rechazada' => ['notif.sol_rechazada', 'peligro'],
                'Editada'   => ['notif.sol_editada', 'aviso'],
            ];
            foreach ($filas as $f) {
                if (!isset($porAccion[$f['accion']])) continue;
                [$clave, $tono] = $porAccion[$f['accion']];
                $items[] = [
                    'texto'   => perfilT($clave, ['producto' => $f['nombre_producto']]),
                    'detalle' => $f['nota'],
                    'tono'    => $tono,
                    'enlace'  => 'productos.php#solicitudes',
                    'fecha'   => $f['fecha'],
                ];
            }

            $filas = perfilConsultar($pdo, "
                SELECT t.resultado, t.diferencia, t.requiere_revision, t.estado_revision, t.efectivo_retirado,
                       t.fecha_cierre AS fecha, c.numero_caja, c.nombre AS caja_nombre, u.nombre AS cajero
                FROM Turnos_Caja t
                INNER JOIN Cajas c ON c.id_caja = t.id_caja
                LEFT JOIN Usuarios u ON u.id_usuario = t.id_cajero_cierre
                WHERE t.id_sucursal = ? AND t.estado = 'Cerrado' AND t.fecha_cierre >= ?
            ", [$usuario['id_sucursal'], $desde]);
            foreach ($filas as $f) {
                $porRecibir = $f['estado_revision'] === 'Pendiente';
                $items[] = [
                    'texto'   => perfilT('notif.corte_cerrado', [
                        'cajero' => $f['cajero'] ?? '—', 'caja' => perfilCaja($f), 'resultado' => perfilResultado($f),
                    ]),
                    'detalle' => $porRecibir ? perfilT('notif.corte_por_recibir', ['monto' => perfilDinero($f['efectivo_retirado'])]) : null,
                    'tono'    => perfilTonoCorte($f),
                    'enlace'  => $porRecibir ? 'cortes.php?ver=pendientes' : 'cortes.php?ver=todos',
                    'fecha'   => $f['fecha'],
                ];
            }
            break;

        case 3:
            $filas = perfilConsultar($pdo, "
                SELECT t.monto_recibido, t.nota_revision, t.fecha_revision AS fecha, c.numero_caja, c.nombre AS caja_nombre
                FROM Turnos_Caja t
                INNER JOIN Cajas c ON c.id_caja = t.id_caja
                WHERE t.id_cajero_cierre = ? AND t.estado_revision = 'Recibido' AND t.fecha_revision >= ?
            ", [$usuario['id_usuario'], $desde]);
            foreach ($filas as $f) {
                $items[] = [
                    'texto'   => perfilT('notif.corte_recibido', ['caja' => perfilCaja($f), 'monto' => perfilDinero($f['monto_recibido'])]),
                    'detalle' => $f['nota_revision'],
                    'tono'    => 'ok',
                    'fecha'   => $f['fecha'],
                ];
            }
            break;
    }

    $vistasEn = $usuario['notificaciones_vistas_en'] ? strtotime($usuario['notificaciones_vistas_en']) : null;
    $formato = perfilIdioma() === 'en' ? 'm/d/Y H:i' : 'd/m/Y H:i';

    usort($items, fn($a, $b) => strcmp((string) $b['fecha'], (string) $a['fecha']));
    $items = array_slice($items, 0, PERFIL_MAX_NOTIFICACIONES);

    return array_map(function (array $item) use ($vistasEn, $formato) {
        $ts = strtotime((string) $item['fecha']);
        return [
            'texto'   => $item['texto'],
            'detalle' => ($item['detalle'] ?? '') !== '' ? $item['detalle'] : null,
            'tono'    => $item['tono'],
            'enlace'  => $item['enlace'] ?? null,
            'fecha'   => $ts ? date($formato, $ts) : '',
            'nueva'   => $ts && ($vistasEn === null || $ts > $vistasEn),
        ];
    }, $items);
}

/* ------------------------------------------------------------
   Vista
   ------------------------------------------------------------ */

/**
 * HTML del menú de perfil. Opciones:
 *   idioma_accion  a dónde se manda el formulario de idioma (el del panel)
 *   idioma_campos  campos ocultos extra para ese formulario (p. ej. volver)
 *   salir          ['href' => '../logout.php'] o ['onclick' => "logoutConfirmado()"]
 * Sin conexión ($pdo null o con error) el menú sale igual, sin nombre ni foto.
 */
function menuPerfil(?PDO $pdo, int $idUsuario, array $opciones): string
{
    try {
        $usuario = $pdo ? perfilUsuario($pdo, $idUsuario) : null;
    } catch (PDOException $e) {
        $usuario = null;
    }
    $usuario ??= [
        'id_usuario' => $idUsuario, 'nombre' => '—', 'email' => '', 'id_rol' => (int) ($_SESSION['id_rol'] ?? 0),
        'sucursal' => null, 'foto' => null,
    ];

    $idioma = perfilIdioma();
    $rol = 'rol.' . (int) $usuario['id_rol'];
    // Texto que el cajero puede cambiar de idioma sin recargar (perfil.js lo vuelve a pintar)
    $txt = fn(string $clave) => '<span data-perfil-t="' . $clave . '">' . perfilE(perfilT($clave)) . '</span>';
    $salir = $opciones['salir'] ?? ['href' => '../logout.php'];
    $iconoSalir = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>';
    // Ojo para ver u ocultar una contraseña (perfil.js lo maneja); el input va justo antes
    $botonVer = '<button type="button" class="perfil-ver" aria-pressed="false"'
        . ' aria-label="' . perfilE(perfilT('form.ver_contrasena')) . '" title="' . perfilE(perfilT('form.ver_contrasena')) . '" data-perfil-t-attr="form.ver_contrasena">'
        . '<svg class="perfil-ver__ojo" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>'
        . '<svg class="perfil-ver__tachado" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 19c-6.4 0-10-7-10-7a18.45 18.45 0 0 1 5.06-5.94M9.9 5.24A9.12 9.12 0 0 1 12 5c6.4 0 10 7 10 7a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="2" y1="2" x2="22" y2="22"/></svg>'
        . '</button>';

    ob_start();
    ?>
<div class="perfil" data-perfil data-api="../perfil/">
    <button type="button" class="perfil-boton" id="perfilBoton" aria-haspopup="true" aria-expanded="false" aria-controls="perfilMenu"
            aria-label="<?= perfilE(perfilT('menu.abrir')) ?>" title="<?= perfilE(perfilT('menu.abrir')) ?>" data-perfil-t-attr="menu.abrir">
        <?= perfilAvatar($usuario, 'perfil-avatar--mini') ?>
        <span class="perfil-boton__nombre" data-perfil-nombre><?= perfilE($usuario['nombre']) ?></span>
        <span class="perfil-icono perfil-icono--engranaje" aria-hidden="true"></span>
        <span class="perfil-punto" data-perfil-punto hidden></span>
    </button>

    <div class="perfil-menu" id="perfilMenu" hidden>
        <div class="perfil-menu__vista" data-perfil-vista="principal">
            <div class="perfil-tarjeta">
                <?= perfilAvatar($usuario, 'perfil-avatar--grande') ?>
                <div class="perfil-tarjeta__datos">
                    <strong class="perfil-tarjeta__nombre" data-perfil-nombre><?= perfilE($usuario['nombre']) ?></strong>
                    <span class="perfil-tarjeta__puesto"><?= $txt($rol) ?><?= $usuario['sucursal'] ? ' · ' . perfilE($usuario['sucursal']) : '' ?></span>
                    <span class="perfil-tarjeta__email" data-perfil-email><?= perfilE($usuario['email']) ?></span>
                </div>
            </div>

            <div class="perfil-lista">
                <button type="button" class="perfil-item" data-perfil-editar>
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <?= $txt('menu.mi_perfil') ?>
                </button>
                <button type="button" class="perfil-item" data-perfil-ir="notificaciones">
                    <span class="perfil-icono perfil-icono--notificacion" aria-hidden="true"></span>
                    <?= $txt('menu.notificaciones') ?>
                    <span class="perfil-contador" data-perfil-contador hidden></span>
                    <svg class="perfil-item__flecha" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 6 15 12 9 18"/></svg>
                </button>
                <button type="button" class="perfil-item" role="switch" aria-checked="false" data-perfil-tema>
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                    <?= $txt('menu.tema_oscuro') ?>
                    <span class="perfil-switch" aria-hidden="true"></span>
                </button>
                <form method="POST" action="<?= perfilE($opciones['idioma_accion']) ?>" class="perfil-item perfil-item--idioma" data-perfil-idioma>
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                    <span id="perfilIdiomaTitulo" data-perfil-t="menu.idioma"><?= perfilE(perfilT('menu.idioma')) ?></span>
                    <input type="hidden" name="csrf_token" value="<?= perfilE(csrfToken()) ?>">
                    <?php foreach ($opciones['idioma_campos'] ?? [] as $nombre => $valor): ?>
                        <input type="hidden" name="<?= perfilE($nombre) ?>" value="<?= perfilE($valor) ?>">
                    <?php endforeach; ?>
                    <span class="perfil-segmentado" role="group" aria-labelledby="perfilIdiomaTitulo">
                        <?php foreach (PERFIL_IDIOMAS as $codigo => $nombre): ?>
                            <button type="submit" name="idioma" value="<?= $codigo ?>" lang="<?= $codigo ?>" title="<?= perfilE($nombre) ?>"
                                    aria-pressed="<?= $codigo === $idioma ? 'true' : 'false' ?>"><?= strtoupper($codigo) ?></button>
                        <?php endforeach; ?>
                    </span>
                </form>
            </div>

            <div class="perfil-lista perfil-lista--salir">
                <?php if (isset($salir['onclick'])): ?>
                    <button type="button" class="perfil-item perfil-item--salir" onclick="<?= perfilE($salir['onclick']) ?>"><?= $iconoSalir ?><?= $txt('menu.salir') ?></button>
                <?php else: ?>
                    <a href="<?= perfilE($salir['href']) ?>" class="perfil-item perfil-item--salir"><?= $iconoSalir ?><?= $txt('menu.salir') ?></a>
                <?php endif; ?>
            </div>
        </div>

        <div class="perfil-menu__vista" data-perfil-vista="notificaciones" hidden>
            <div class="perfil-menu__cabecera">
                <button type="button" class="perfil-volver" data-perfil-ir="principal"
                        aria-label="<?= perfilE(perfilT('menu.volver')) ?>" title="<?= perfilE(perfilT('menu.volver')) ?>" data-perfil-t-attr="menu.volver">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 6 9 12 15 18"/></svg>
                </button>
                <strong data-perfil-t="menu.notificaciones"><?= perfilE(perfilT('menu.notificaciones')) ?></strong>
            </div>
            <ul class="perfil-notificaciones" data-perfil-notificaciones aria-live="polite"></ul>
        </div>
    </div>

    <dialog class="perfil-dialogo" id="perfilDialogo" aria-labelledby="perfilDialogoTitulo">
        <form class="perfil-form" data-perfil-form enctype="multipart/form-data" novalidate>
            <h2 id="perfilDialogoTitulo" data-perfil-t="form.titulo"><?= perfilE(perfilT('form.titulo')) ?></h2>
            <div class="perfil-mensaje" data-perfil-mensaje role="alert" hidden></div>

            <div class="perfil-foto">
                <?= perfilAvatar($usuario, 'perfil-avatar--foto') ?>
                <div class="perfil-foto__acciones">
                    <label class="perfil-btn">
                        <?= $txt('form.foto_cambiar') ?>
                        <input type="file" name="foto" accept="image/png,image/jpeg,image/webp" class="perfil-oculto">
                    </label>
                    <button type="button" class="perfil-btn perfil-btn--plano" data-perfil-quitar-foto<?= $usuario['foto'] ? '' : ' hidden' ?>><?= $txt('form.foto_quitar') ?></button>
                    <input type="hidden" name="quitar_foto" value="0">
                    <small><?= $txt('form.foto_ayuda') ?></small>
                </div>
            </div>

            <label class="perfil-campo">
                <?= $txt('form.nombre') ?>
                <input type="text" name="nombre" maxlength="100" required autocomplete="name" value="<?= perfilE($usuario['nombre']) ?>">
            </label>
            <label class="perfil-campo">
                <?= $txt('form.email') ?>
                <input type="email" name="email" maxlength="100" required autocomplete="email" value="<?= perfilE($usuario['email']) ?>">
            </label>

            <fieldset class="perfil-grupo">
                <legend data-perfil-t="form.contrasena"><?= perfilE(perfilT('form.contrasena')) ?></legend>
                <div class="perfil-campo">
                    <label for="perfilNueva"><?= $txt('form.nueva') ?></label>
                    <span class="perfil-contrasena">
                        <input type="password" id="perfilNueva" name="nueva" autocomplete="new-password">
                        <?= $botonVer ?>
                    </span>
                    <small><?= $txt('form.nueva_ayuda') ?></small>
                </div>
                <div class="perfil-campo">
                    <label for="perfilConfirmar"><?= $txt('form.confirmar') ?></label>
                    <span class="perfil-contrasena">
                        <input type="password" id="perfilConfirmar" name="confirmar" autocomplete="new-password">
                        <?= $botonVer ?>
                    </span>
                </div>
            </fieldset>

            <div class="perfil-campo">
                <label for="perfilActual"><?= $txt('form.actual') ?></label>
                <span class="perfil-contrasena">
                    <input type="password" id="perfilActual" name="actual" autocomplete="current-password">
                    <?= $botonVer ?>
                </span>
                <small><?= $txt('form.actual_ayuda') ?></small>
            </div>

            <input type="hidden" name="csrf_token" value="<?= perfilE(csrfToken()) ?>">
            <div class="perfil-form__acciones">
                <button type="button" class="perfil-btn perfil-btn--plano" data-perfil-cerrar><?= $txt('form.cancelar') ?></button>
                <button type="submit" class="perfil-btn perfil-btn--primario" data-perfil-guardar><?= $txt('form.guardar') ?></button>
            </div>
        </form>
    </dialog>

    <script type="application/json" id="perfilDatos"><?= json_encode([
        'idioma' => $idioma,
        'textos' => perfilTextos(),
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
</div>
    <?php
    return ob_get_clean();
}
