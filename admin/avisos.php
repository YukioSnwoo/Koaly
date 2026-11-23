<?php
// avisos.php — Avisos de privacidad (cajeros, gerentes y clientes).
//
//   avisos.php             una tarjeta por aviso: versión vigente, quién lo aceptó,
//                          quién falta y el botón para avisarles por correo; abajo,
//                          el historial de versiones publicadas.
//   avisos.php?editar=X    editor: el texto vigente en el formato de
//                          ../privacidad/formato.php, vista previa en vivo (en
//                          español o inglés) y "Publicar".
//   avisos.php?ver=ID      el texto de una versión publicada (las viejas no se borran).
//
// Publicar crea una versión nueva en avisos_versiones (nunca se edita una
// publicada); los cajeros o gerentes la aceptan al iniciar sesión (login.php).
// Lógica y textos de los avisos en ../privacidad/aviso.php. Tablas: sql/018.
ob_start();
require 'header.php';
require 'sidebar.php';
require_once __DIR__ . '/../privacidad/aviso.php';

// Fechas de versión y de publicación en hora de México, como el resto del sistema
date_default_timezone_set('America/Mexico_City');
$pdo->exec("SET time_zone = '" . (new DateTimeImmutable())->format('P') . "'");

$idioma = idiomaActual();
$tipoValido = static fn($tipo): ?string => in_array($tipo, AVISO_TIPOS, true) ? $tipo : null;
$seAcepta = static fn(string $tipo): bool => array_key_exists($tipo, AVISO_ROL_TIPO);

// ¿Ya se corrieron sql/018 (versiones y correos) y sql/010 (aceptaciones)?
$hayTablas = true;
try {
    $pdo->query('SELECT 1 FROM avisos_versiones LIMIT 1');
    $pdo->query('SELECT 1 FROM avisos_notificaciones LIMIT 1');
} catch (PDOException) {
    $hayTablas = false;
}
$hayAceptaciones = true;
try {
    $pdo->query('SELECT 1 FROM avisos_privacidad LIMIT 1');
} catch (PDOException) {
    $hayAceptaciones = false;
}

avisosPublicados($pdo);

$error = '';
$mensaje = $_SESSION['avisos_mensaje'] ?? null;     // ['texto', 'tono'] tras publicar o avisar (PRG)
unset($_SESSION['avisos_mensaje']);

$editar = $tipoValido($_GET['editar'] ?? '');
$borrador = null;       // lo escrito en el editor, si publicar falló (para no perderlo)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = (string) ($_POST['action'] ?? '');
    $tipo = $tipoValido($_POST['tipo'] ?? '');

    try {
        if (!csrfValido()) {
            throw new RuntimeException(t('error.formulario_expirado'));
        }
        if ($tipo === null) {
            throw new RuntimeException(t('av.error_tipo'));
        }

        // Correo de "actualizamos el aviso" a los pendientes que aún no lo reciben
        if ($accion === 'avisar') {
            if (!$hayTablas) {
                throw new RuntimeException(t('av.falta_tabla'));
            }
            if (!$seAcepta($tipo)) {
                throw new RuntimeException(t('av.error_tipo'));
            }
            $r = avisarAvisoActualizado($pdo, $tipo);
            $texto = $r['enviados'] > 0 ? tn('av.enviados', $r['enviados']) : t('av.nadie_por_avisar');
            if ($r['fallidos']) {
                $texto .= ' ' . t('av.fallidos', ['nombres' => implode(', ', $r['fallidos'])]);
            }
            $_SESSION['avisos_mensaje'] = ['texto' => $texto, 'tono' => $r['fallidos'] ? 'warning' : 'success'];
            header('Location: avisos.php');
            exit;
        }

        if ($accion === 'publicar') {
            $editar = $tipo;
            $borrador = [
                'texto_es'   => (string) ($_POST['texto_es'] ?? ''),
                'texto_en'   => (string) ($_POST['texto_en'] ?? ''),
                'cambios_es' => trim((string) ($_POST['cambios_es'] ?? '')),
                'cambios_en' => trim((string) ($_POST['cambios_en'] ?? '')),
            ];
            $textoEs = normalizarTextoAviso($borrador['texto_es']);
            $textoEn = normalizarTextoAviso($borrador['texto_en']);
            if ($textoEs === '') {
                throw new RuntimeException(t('av.error_vacio'));
            }
            if (trim($borrador['texto_en']) !== '' && $textoEn === '') {
                throw new RuntimeException(t('av.error_vacio_en'));
            }
            if (!$hayTablas) {
                throw new RuntimeException(t('av.falta_tabla'));
            }
            // Una versión nueva obliga a aceptar otra vez: solo si de verdad cambió algo
            $igual = $textoEs === normalizarTextoAviso(textoVigenteAviso($tipo, 'es'))
                && $textoEn === normalizarTextoAviso(textoVigenteAviso($tipo, 'en'));
            if ($igual) {
                throw new RuntimeException(t('av.error_sin_cambios'));
            }
            if ($seAcepta($tipo) && $borrador['cambios_es'] === '') {
                throw new RuntimeException(t('av.error_cambios'));
            }

            $version = nuevaVersionAviso($pdo, $tipo);
            $pdo->prepare("
                INSERT INTO avisos_versiones (tipo, version, texto_es, texto_en, cambios_es, cambios_en, publicado_por)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $tipo, $version, $textoEs, $textoEn !== '' ? $textoEn : null,
                $borrador['cambios_es'] !== '' ? $borrador['cambios_es'] : null,
                $borrador['cambios_en'] !== '' ? $borrador['cambios_en'] : null,
                (int) $admin['id_usuario'],
            ]);
            $_SESSION['avisos_mensaje'] = [
                'texto' => t('av.publicado', ['aviso' => t('av.tipo.' . $tipo), 'fecha' => fechaVersionAviso($idioma, $tipo, $version)])
                    . ($seAcepta($tipo) ? ' ' . t('av.publicado_pendientes') : ''),
                'tono'  => 'success',
            ];
            header('Location: avisos.php');
            exit;
        }
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }
}

// Una versión publicada, para leerla tal como quedó
$verVersion = null;
if (!$editar && $hayTablas && ($idVer = (int) ($_GET['ver'] ?? 0)) > 0) {
    $stmt = $pdo->prepare("
        SELECT v.*, u.nombre AS publicado_por_nombre FROM avisos_versiones v
        LEFT JOIN Usuarios u ON u.id_usuario = v.publicado_por WHERE v.id = ?
    ");
    $stmt->execute([$idVer]);
    $verVersion = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Tarjetas: versión vigente y, en los que se aceptan, quién falta
$tarjetas = [];
if (!$editar && !$verVersion) {
    foreach (AVISO_TIPOS as $tipo) {
        $tarjeta = ['tipo' => $tipo, 'publicado' => avisosPublicados()[$tipo] ?? null, 'personas' => null];
        if ($seAcepta($tipo) && $hayAceptaciones) {
            $tarjeta['personas'] = personasAviso($pdo, $tipo, $hayTablas);
        }
        $tarjetas[] = $tarjeta;
    }
}

$historial = [];
if (!$editar && !$verVersion && $hayTablas) {
    $historial = $pdo->query("
        SELECT v.id, v.tipo, v.version, v.cambios_es, v.cambios_en, v.publicado_en, u.nombre AS publicado_por_nombre
        FROM avisos_versiones v
        LEFT JOIN Usuarios u ON u.id_usuario = v.publicado_por
        ORDER BY v.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

$fechaHora = static fn(?string $f): string => $f ? date($idioma === 'en' ? 'm/d/Y H:i' : 'd/m/Y H:i', strtotime($f)) : '—';
$aviso = static fn(string $texto, string $tono = 'muted'): string
    => '<div class="aviso-mensaje aviso-mensaje--' . $tono . '" role="' . ($tono === 'danger' ? 'alert' : 'status') . '">' . e($texto) . '</div>';
?>

<?php if ($editar): ?>
    <?php
    $valores = $borrador ?? [
        'texto_es'   => textoVigenteAviso($editar, 'es'),
        'texto_en'   => textoVigenteAviso($editar, 'en'),
        'cambios_es' => '',
        'cambios_en' => '',
    ];
    ?>
    <div class="page-header">
        <div>
            <h1><?= e(t('av.editar_titulo', ['aviso' => t('av.tipo.' . $editar)])) ?></h1>
            <p><?= e(t($seAcepta($editar) ? 'av.editar_ayuda' : 'av.editar_ayuda_cliente')) ?></p>
        </div>
        <a href="avisos.php" class="btn btn--ghost btn--sm"><i class="ti ti-arrow-left"></i><?= e(t('av.volver')) ?></a>
    </div>

    <?php if (!$hayTablas): ?><?= $aviso(t('av.falta_tabla'), 'warning') ?><?php endif; ?>
    <?php if ($error !== ''): ?><?= $aviso($error, 'danger') ?><?php endif; ?>

    <div class="aviso-editor aviso-editor--vivo">
        <form method="POST" action="avisos.php?editar=<?= e($editar) ?>" class="aviso-form"
              data-confirmar-publicar="<?= e(t($seAcepta($editar) ? 'av.publicar_confirmar' : 'av.publicar_confirmar_cliente')) ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="tipo" value="<?= e($editar) ?>">

            <details class="aviso-formato" open>
                <summary><?= e(t('av.formato_titulo')) ?></summary>
                <pre><?= e(t('av.formato')) ?></pre>
            </details>

            <label class="aviso-campo">
                <span><?= e(t('av.texto_es')) ?></span>
                <textarea name="texto_es" class="input aviso-texto" rows="24" spellcheck="true" lang="es" required><?= e($valores['texto_es']) ?></textarea>
            </label>
            <label class="aviso-campo">
                <span><?= e(t('av.texto_en')) ?></span>
                <textarea name="texto_en" class="input aviso-texto" rows="12" spellcheck="true" lang="en"><?= e($valores['texto_en']) ?></textarea>
                <small><?= e(t('av.texto_en_ayuda')) ?></small>
            </label>
            <div class="grid-2">
                <label class="aviso-campo">
                    <span><?= e(t($seAcepta($editar) ? 'av.cambios_es' : 'av.cambios_es_opcional')) ?></span>
                    <textarea name="cambios_es" class="input" rows="4" lang="es"><?= e($valores['cambios_es']) ?></textarea>
                    <small><?= e(t('av.cambios_ayuda')) ?></small>
                </label>
                <label class="aviso-campo">
                    <span><?= e(t('av.cambios_en')) ?></span>
                    <textarea name="cambios_en" class="input" rows="4" lang="en"><?= e($valores['cambios_en']) ?></textarea>
                </label>
            </div>

            <div class="aviso-form__acciones">
                <a href="avisos.php" class="btn btn--ghost"><?= e(t('comun.cancelar')) ?></a>
                <button type="submit" name="action" value="publicar" class="btn btn--primary"<?= $hayTablas ? '' : ' disabled' ?>>
                    <i class="ti ti-send"></i><?= e(t('av.publicar')) ?>
                </button>
            </div>
        </form>

        <?php
        // Vista previa en vivo: el script de abajo la vuelve a armar con cada tecla, con
        // las mismas reglas de ../privacidad/formato.php y el HTML de htmlSeccionesAviso().
        // Lo que se ve aquí sin JavaScript es el español tal como llegó.
        $textosVista = [];
        foreach (['es', 'en'] as $codigo) {
            $textosVista[$codigo] = [
                'loVen'   => tAviso('lo_ven', $codigo),
                'cambios' => tAviso('cambios.version', $codigo),
            ];
        }
        $textosVista['sinIngles'] = t('av.vista_sin_en');
        $textosVista['vacio']     = t('av.vista_vacia');
        ?>
        <section class="aviso-vista aviso-vista--viva" id="vistaPrevia" aria-labelledby="tituloVistaPrevia"
                 data-textos="<?= e(json_encode($textosVista, JSON_UNESCAPED_UNICODE)) ?>">
            <div class="aviso-vista__barra">
                <div>
                    <p class="aviso-vista__etiqueta" id="tituloVistaPrevia"><?= e(t('av.vista_previa')) ?></p>
                    <p class="aviso-vista__ayuda"><?= e(t('av.vista_viva')) ?></p>
                </div>
                <div class="segmentado aviso-idiomas" role="group" aria-label="<?= e(t('av.vista_idioma')) ?>">
                    <button type="button" data-idioma="es" class="is-activo" aria-pressed="true"><?= e(t('av.idioma_es')) ?></button>
                    <button type="button" data-idioma="en" aria-pressed="false"><?= e(t('av.idioma_en')) ?></button>
                </div>
            </div>
            <p class="aviso-vista__nota" hidden></p>
            <div class="aviso-vista__cuerpo" aria-live="off">
                <?= htmlSeccionesAviso(seccionesDesdeTexto($valores['texto_es']), tAviso('lo_ven', 'es')) ?>
            </div>
        </section>
    </div>

<?php elseif ($verVersion): ?>
    <div class="page-header">
        <div>
            <h1><?= e(t('av.version_titulo', [
                'aviso' => t('av.tipo.' . $verVersion['tipo']),
                'fecha' => fechaVersionAviso($idioma, $verVersion['tipo'], $verVersion['version']),
            ])) ?></h1>
            <p><?= e(t('av.publicada_por', [
                'nombre' => $verVersion['publicado_por_nombre'] ?? '—',
                'fecha'  => $fechaHora($verVersion['publicado_en']),
            ])) ?> · <code><?= e($verVersion['version']) ?></code></p>
        </div>
        <a href="avisos.php" class="btn btn--ghost btn--sm"><i class="ti ti-arrow-left"></i><?= e(t('av.volver')) ?></a>
    </div>
    <div class="aviso-vista">
        <?php $cambiosVer = array_filter(array_map('trim', preg_split('/\R/u', (string) $verVersion['cambios_es']))); ?>
        <?php if ($cambiosVer): ?>
            <p class="aviso-vista__etiqueta"><?= e(t('av.col_cambios')) ?></p>
            <ul class="aviso-vista__cambios"><?php foreach ($cambiosVer as $cambio): ?><li><?= e(ltrim($cambio, '- ')) ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
        <?= htmlSeccionesAviso(seccionesDesdeTexto((string) $verVersion['texto_es']), tAviso('lo_ven', 'es')) ?>
    </div>

<?php else: ?>
    <div class="page-header">
        <div>
            <h1><?= e(t('nav.avisos')) ?></h1>
            <p><?= e(t('av.subtitulo')) ?></p>
        </div>
    </div>

    <?php if (!$hayTablas): ?><?= $aviso(t('av.falta_tabla'), 'warning') ?><?php endif; ?>
    <?php if ($mensaje): ?><?= $aviso($mensaje['texto'], $mensaje['tono']) ?><?php endif; ?>
    <?php if ($error !== ''): ?><?= $aviso($error, 'danger') ?><?php endif; ?>

    <div class="avisos-grid">
        <?php foreach ($tarjetas as $tarjeta): ?>
            <?php
            $tipo = $tarjeta['tipo'];
            $publicado = $tarjeta['publicado'];
            $personas = $tarjeta['personas'];
            $total = $personas === null ? 0 : count($personas);
            $aceptaron = $personas === null ? 0 : count(array_filter($personas, static fn($p) => (int) $p['aceptado'] === 1));
            $pendientes = array_values(array_filter($personas ?? [], static fn($p) => (int) $p['aceptado'] === 0));
            $porAvisar = count(array_filter($pendientes, static fn($p) => (int) $p['avisado'] === 0));
            ?>
            <article class="branch-card aviso-card">
                <div class="branch-card__header">
                    <h2 class="branch-card__name"><?= e(t('av.tipo.' . $tipo)) ?></h2>
                    <span class="badge badge--muted"><?= e(t('av.version', ['fecha' => fechaVersionAviso($idioma, $tipo)])) ?></span>
                </div>
                <p class="branch-card__detail">
                    <i class="ti ti-<?= $publicado ? 'user-edit' : 'code' ?>" aria-hidden="true"></i>
                    <?= e($publicado
                        ? t('av.publicada_por', ['nombre' => $publicado['publicado_por_nombre'] ?? '—', 'fecha' => $fechaHora($publicado['publicado_en'])])
                        : t('av.del_sistema')) ?>
                </p>

                <?php if ($seAcepta($tipo)): ?>
                    <?php if ($personas === null): ?>
                        <p class="branch-card__detail"><i class="ti ti-alert-triangle" aria-hidden="true"></i><?= e(t('av.falta_tabla_aceptaciones')) ?></p>
                    <?php else: ?>
                        <div class="aviso-cifras">
                            <div><strong><?= $aceptaron ?></strong><span><?= e(t('av.aceptaron')) ?></span></div>
                            <div class="<?= $pendientes ? 'is-pendiente' : '' ?>"><strong><?= count($pendientes) ?></strong><span><?= e(t('av.pendientes')) ?></span></div>
                            <div><strong><?= $porAvisar ?></strong><span><?= e(t('av.por_avisar')) ?></span></div>
                        </div>
                        <?php if ($pendientes): ?>
                            <details class="aviso-pendientes">
                                <summary><?= e(tn('av.ver_pendientes', count($pendientes))) ?></summary>
                                <ul>
                                    <?php foreach ($pendientes as $p): ?>
                                        <li>
                                            <span><?= e($p['nombre']) ?></span>
                                            <?php if ((int) $p['avisado'] === 1): ?>
                                                <span class="badge badge--success"><?= e(t('av.ya_avisado')) ?></span>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </details>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="branch-card__detail"><i class="ti ti-receipt" aria-hidden="true"></i><?= e(t('av.cliente_ayuda')) ?></p>
                <?php endif; ?>

                <div class="branch-card__footer">
                    <span class="branch-card__count"><?= $seAcepta($tipo) && $personas !== null ? e(tn('av.deben_aceptar', $total)) : '' ?></span>
                    <div class="branch-card__actions">
                        <a class="btn btn--ghost btn--sm" href="<?= e(urlAviso($tipo)) ?>" target="_blank" rel="noopener"><i class="ti ti-external-link"></i><?= e(t('av.ver')) ?></a>
                        <a class="btn btn--ghost btn--sm" href="avisos.php?editar=<?= e($tipo) ?>"><i class="ti ti-pencil"></i><?= e(t('comun.editar')) ?></a>
                    </div>
                </div>

                <?php if ($seAcepta($tipo) && $personas !== null): ?>
                    <form method="POST" action="avisos.php" class="aviso-avisar"
                          data-confirmar="<?= e(tn('av.avisar_confirmar', $porAvisar)) ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="tipo" value="<?= e($tipo) ?>">
                        <input type="hidden" name="action" value="avisar">
                        <button type="submit" class="btn btn--primary btn--sm"<?= $porAvisar > 0 && $hayTablas ? '' : ' disabled' ?>>
                            <i class="ti ti-mail"></i><?= e($porAvisar > 0 ? tn('av.avisar', $porAvisar) : t('av.nadie_por_avisar_corto')) ?>
                        </button>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>

    <h2 class="section-title aviso-historial-titulo"><?= e(t('av.historial')) ?></h2>
    <?php if (!$historial): ?>
        <p class="aviso-historial-vacio"><?= e(t($hayTablas ? 'av.historial_vacio' : 'av.falta_tabla')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr>
                    <th><?= e(t('av.col_aviso')) ?></th>
                    <th><?= e(t('av.col_version')) ?></th>
                    <th><?= e(t('av.col_publicada')) ?></th>
                    <th><?= e(t('av.col_cambios')) ?></th>
                    <th></th>
                </tr></thead>
                <tbody>
                    <?php foreach ($historial as $v): ?>
                        <?php $vigente = (int) (avisosPublicados()[$v['tipo']]['id'] ?? 0) === (int) $v['id']; ?>
                        <tr>
                            <td><?= e(t('av.tipo.' . $v['tipo'])) ?></td>
                            <td>
                                <?= e(fechaVersionAviso($idioma, $v['tipo'], $v['version'])) ?>
                                <?php if ($vigente): ?><span class="badge badge--success"><?= e(t('av.vigente')) ?></span><?php endif; ?>
                            </td>
                            <td><?= e($fechaHora($v['publicado_en'])) ?> · <?= e($v['publicado_por_nombre'] ?? '—') ?></td>
                            <td class="aviso-historial-cambios"><?= e(trim((string) (($idioma === 'en' && $v['cambios_en']) ? $v['cambios_en'] : $v['cambios_es'])) ?: '—') ?></td>
                            <td><a class="btn btn--ghost btn--sm" href="avisos.php?ver=<?= (int) $v['id'] ?>"><?= e(t('av.ver_texto')) ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>

<script>
// Confirmaciones: avisar por correo y publicar (una versión nueva obliga a aceptar otra vez)
document.querySelectorAll('form[data-confirmar]').forEach((form) => {
    form.addEventListener('submit', (e) => { if (!confirm(form.dataset.confirmar)) e.preventDefault(); });
});
document.querySelectorAll('form[data-confirmar-publicar]').forEach((form) => {
    form.addEventListener('submit', (e) => {
        if (e.submitter?.value === 'publicar' && !confirm(form.dataset.confirmarPublicar)) e.preventDefault();
    });
});

// Vista previa en vivo del editor: se vuelve a armar con cada tecla, en español o en
// inglés (cambia sola al escribir en un campo del otro idioma) y marca la sección
// donde está el cursor. leerSecciones() es el mismo formato que seccionesDesdeTexto()
// de ../privacidad/formato.php y pintarSecciones() el HTML de htmlSeccionesAviso():
// si se cambia uno, hay que cambiar el otro.
(() => {
    const vista = document.getElementById('vistaPrevia');
    const form = document.querySelector('.aviso-form');
    if (!vista || !form) return;

    const textos = JSON.parse(vista.dataset.textos);
    const cuerpo = vista.querySelector('.aviso-vista__cuerpo');
    const nota = vista.querySelector('.aviso-vista__nota');
    const barra = vista.querySelector('.aviso-vista__barra');
    const botones = vista.querySelectorAll('[data-idioma]');
    const campo = (nombre) => form.elements[nombre];
    let idioma = 'es';

    const RENGLON = /\r\n|[\n\v\f\r\x85\u2028\u2029]/;
    const recortar = (s) => s.replace(/^[ \t\n\r\0\x0B]+|[ \t\n\r\0\x0B]+$/g, '');   // trim() de PHP
    const esc = (s) => s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));

    function leerSecciones(texto) {
        const secciones = new Map();    // título → bloques, en el orden en que aparecen
        let titulo = null;
        let bloque = null;              // { tipo: 'p' | 'lista' | 'tabla', datos }
        const cerrar = () => {
            const lleno = bloque && (bloque.tipo === 'tabla' ? bloque.datos.size : bloque.datos.length);
            if (titulo !== null && lleno) {
                secciones.get(titulo).push(bloque.tipo === 'p' ? bloque.datos.join(' ') : bloque);
            }
            bloque = null;
        };
        for (const crudo of texto.split(RENGLON)) {
            const linea = recortar(crudo);
            if (linea.startsWith('# ')) {
                cerrar();
                titulo = recortar(linea.slice(2));
                if (!secciones.has(titulo)) secciones.set(titulo, []);
                continue;
            }
            if (titulo === null) continue;
            if (linea === '') { cerrar(); continue; }

            const tipo = linea.startsWith('- ') ? 'lista' : (linea.startsWith('|') ? 'tabla' : 'p');
            if (!bloque || bloque.tipo !== tipo) {
                cerrar();
                bloque = { tipo, datos: tipo === 'tabla' ? new Map() : [] };
            }
            if (tipo === 'lista') {
                bloque.datos.push(recortar(linea.slice(2)));
            } else if (tipo === 'tabla') {
                const celdas = linea.replace(/^[| \t]+|[| \t]+$/g, '').split('|').map(recortar);
                const termino = celdas.shift();
                if (termino === '' || !celdas.length) continue;
                bloque.datos.set(termino, [celdas[0], celdas.slice(1).join(' | ')]);
            } else {
                bloque.datos.push(linea);
            }
        }
        cerrar();
        return [...secciones].filter(([, bloques]) => bloques.length);
    }

    const pintarSecciones = (secciones, loVen) => secciones.map(([titulo, bloques], i) =>
        `<section id="seccion-${i + 1}"><h2>${esc(titulo)}</h2>` + bloques.map((b) => {
            if (typeof b === 'string') return `<p>${esc(b)}</p>`;
            if (b.tipo === 'lista') return '<ul>' + b.datos.map((item) => `<li>${esc(item)}</li>`).join('') + '</ul>';
            return '<dl class="datos">' + [...b.datos].map(([termino, [dato, quienVe]]) =>
                `<div><dt>${esc(termino)}</dt><dd>${esc(dato)}`
                + (quienVe !== '' ? ` <span class="quien-ve">${esc(loVen)} ${esc(quienVe)}</span>` : '')
                + '</dd></div>').join('') + '</dl>';
        }).join('') + '</section>').join('');

    // Qué cambió: un punto por renglón, sin el guion o la viñeta (como cambiosAviso())
    const leerCambios = (texto) => texto.split(RENGLON)
        .map((l) => recortar(recortar(l).replace(/^[-•*]\s*/u, '')))
        .filter((l) => l !== '');

    // En inglés sin traducción se muestra el español, igual que en la página del aviso
    const campoMostrado = () => (idioma === 'en' && recortar(campo('texto_en').value) !== '' ? 'texto_en' : 'texto_es');

    function pintar() {
        const sinIngles = idioma === 'en' && campoMostrado() === 'texto_es';
        const secciones = leerSecciones(campo(campoMostrado()).value);
        const cambiosEn = campo('cambios_en').value;
        const cambios = leerCambios(idioma === 'en' && recortar(cambiosEn) !== '' ? cambiosEn : campo('cambios_es').value);

        nota.textContent = textos.sinIngles;
        nota.hidden = !sinIngles;
        cuerpo.lang = sinIngles ? 'es' : idioma;
        cuerpo.innerHTML = (cambios.length
            ? `<div class="aviso-vista__novedades"><strong>${esc(textos[idioma].cambios)}</strong><ul>`
              + cambios.map((c) => `<li>${esc(c)}</li>`).join('') + '</ul></div>'
            : '')
            + (secciones.length ? pintarSecciones(secciones, textos[idioma].loVen) : `<p class="aviso-vista__vacio">${esc(textos.vacio)}</p>`);
        marcarSeccion(false);
    }

    // La sección donde está el cursor: la del último "# " antes de él
    function marcarSeccion(llevar) {
        cuerpo.querySelector('section.is-actual')?.classList.remove('is-actual');
        const area = document.activeElement;
        if (!area || area.name !== campoMostrado()) return;

        const fin = area.value.indexOf('\n', area.selectionStart);
        const renglones = area.value.slice(0, fin === -1 ? undefined : fin).split(RENGLON);
        let titulo = null;
        for (let i = renglones.length - 1; i >= 0 && titulo === null; i--) {
            const linea = recortar(renglones[i]);
            if (linea.startsWith('# ')) titulo = recortar(linea.slice(2));
        }
        const seccion = titulo === null ? null
            : [...cuerpo.querySelectorAll('section')].find((s) => s.firstElementChild.textContent === titulo);
        if (!seccion) return;
        seccion.classList.add('is-actual');
        if (llevar) llevarAVista(seccion);
    }

    // Si la vista previa tiene su propio scroll (pantalla ancha), la lleva a la sección
    // cuando no se ve; en pantalla angosta no mueve la página.
    function llevarAVista(seccion) {
        if (vista.scrollHeight <= vista.clientHeight + 1) return;
        const arriba = vista.getBoundingClientRect().top + barra.offsetHeight;
        const abajo = vista.getBoundingClientRect().bottom;
        const r = seccion.getBoundingClientRect();
        const seVe = (r.top >= arriba && r.top < abajo - 40) || (r.top < arriba && r.bottom > arriba + 40);
        if (!seVe) vista.scrollBy({ top: r.top - arriba - 12, behavior: 'smooth' });
    }

    function elegirIdioma(nuevo) {
        if (nuevo === idioma || !textos[nuevo]) return;
        idioma = nuevo;
        botones.forEach((b) => {
            const activo = b.dataset.idioma === idioma;
            b.classList.toggle('is-activo', activo);
            b.setAttribute('aria-pressed', String(activo));
        });
        pintar();
    }

    let pendiente = 0;
    form.addEventListener('input', () => {
        cancelAnimationFrame(pendiente);
        pendiente = requestAnimationFrame(() => { pintar(); marcarSeccion(true); });
    });
    form.addEventListener('focusin', (e) => {
        if (e.target.matches('textarea[lang]')) elegirIdioma(e.target.lang);
        marcarSeccion(true);
    });
    form.addEventListener('focusout', () => setTimeout(() => marcarSeccion(false)));
    ['click', 'keyup'].forEach((evento) => form.addEventListener(evento, (e) => {
        if (e.target.classList.contains('aviso-texto')) marcarSeccion(true);
    }));
    botones.forEach((b) => b.addEventListener('click', () => elegirIdioma(b.dataset.idioma)));
    pintar();
})();
</script>

<?php require 'footer.php'; ?>
