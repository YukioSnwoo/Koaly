<?php
session_start();

if (!isset($_SESSION['id_usuario']) || (int) $_SESSION['id_rol'] !== 3) {
    header('Location: ../index.php');
    exit;
}

require_once __DIR__ . '/../admin/csrf.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/../perfil/perfil.php';

// Solo para el menú de perfil: sin base de datos la caja abre igual (el menú sale sin nombre)
try {
    require_once __DIR__ . '/../admin/database.php';
} catch (PDOException $e) {
    $pdo = null;
}

// Textos de todos los idiomas (con respaldo en español): el idioma se cambia en
// el navegador sin recargar, para no perder la venta que se está cobrando
$textosPorIdioma = [];
foreach (array_keys(IDIOMAS) as $codigo) {
    $textosPorIdioma[$codigo] = $codigo === 'es'
        ? $GLOBALS['CAJA_TEXTOS_ES']
        : (require __DIR__ . "/lang/{$codigo}.php") + $GLOBALS['CAJA_TEXTOS_ES'];
}

$verCajeroCss = filemtime(__DIR__ . '/cajero.css');
?>
<!DOCTYPE html>
<html lang="<?= idiomaActual() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(t('caja.titulo')) ?> - Koaly</title>
    <script>
    // Tema elegido (claro/oscuro) antes de pintar, para evitar un destello. Sin elección: sigue al sistema.
    try { var tema = localStorage.getItem('koaly-tema') || localStorage.getItem('koalicius-cajero-tema'); if (tema === 'claro' || tema === 'oscuro') document.documentElement.setAttribute('data-tema', tema); } catch (e) {}
    </script>
    <meta name="csrf-token" content="<?= htmlspecialchars(csrfToken()) ?>">
    <link rel="stylesheet" href="cajero.css?v=<?= $verCajeroCss ?>">
    <!-- Estilo común de Koaly (colores claro/oscuro, fuentes y piel táctil); va al final para que gane -->
    <link rel="stylesheet" href="../estilos/koaly.css?v=<?= filemtime(__DIR__ . '/../estilos/koaly.css') ?>">
    <link rel="stylesheet" href="../perfil/perfil.css?v=<?= filemtime(__DIR__ . '/../perfil/perfil.css') ?>">
</head>
<body class="caja-bloqueada">

    <!-- Encabezado -->
    <header class="header">
        <h1>Koaly <span class="header-sub" data-i18n="caja.titulo"><?= e(t('caja.titulo')) ?></span></h1>
        <div class="user-info">
            <button type="button" class="corte-boton" onclick="abrirCorte()" disabled>
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/></svg>
                <span data-i18n="corte.boton"><?= e(t('corte.boton')) ?></span>
            </button>
            <?= menuPerfil($pdo, (int) $_SESSION['id_usuario'], [
                'idioma_accion' => 'idioma.php',
                'salir'         => ['onclick' => "abrirModal('modalSalir')"],
            ]) ?>
        </div>
    </header>

    <!-- Panel Principal -->
    <main class="pos-layout" inert>

        <!-- Lateral Izquierdo -->
        <aside class="panel sidebar">
            <div class="sidebar-group">
                <div class="search-box">
                    <div class="input-buscar">
                        <svg class="icono-lupa" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                        <input type="text" id="inputBuscar" placeholder="<?= e(t('buscar.placeholder')) ?>" data-i18n-placeholder="buscar.placeholder" autocomplete="off"
                               aria-controls="catalogo" enterkeyhint="search">
                        <span class="spinner" aria-hidden="true"></span>
                    </div>
                </div>
            </div>
            <nav class="sidebar-categorias" aria-labelledby="tituloCategorias">
                <div class="panel-title" id="tituloCategorias" data-i18n="categorias.titulo"><?= e(t('categorias.titulo')) ?></div>
                <div class="categorias-filtro" id="filtroCategorias" role="group" aria-labelledby="tituloCategorias">
                    <!-- Categorías dinámicas -->
                </div>
            </nav>
            <div class="sidebar-group">
                <button type="button" class="btn btn-warning btn-block" onclick="limpiarTabla()" data-i18n="comun.limpiar"><?= e(t('comun.limpiar')) ?></button>
            </div>
        </aside>

        <!-- Catálogo Central -->
        <section class="panel catalogo-panel">
            <h2 class="catalogo-titulo" data-i18n="catalogo.titulo"><?= e(t('catalogo.titulo')) ?></h2>
            <div class="catalogo-contenido" id="catalogo">
                <!-- Secciones por categoría con sus tarjetas -->
            </div>
            <div class="catalogo-pie">
                <button type="button" class="btn btn-ghost btn-lista" onclick="abrirLista()">
                    <span data-i18n="lista.titulo"><?= e(t('lista.titulo')) ?></span> <span class="contador-lista" id="contadorLista">0</span>
                </button>
            </div>
        </section>

        <!-- Lateral Derecho (Totales) -->
        <aside class="panel resumen">
            <!-- Escaneo: historial en tiempo real, los escaneos seguidos del mismo producto se juntan (p1 ×3, p2, p1) -->
            <div class="panel-title" data-i18n="resumen.escaneo"><?= e(t('resumen.escaneo')) ?></div>
            <ul class="resumen-lista" id="escaneoLista"></ul>

            <!-- Resumen: todo agrupado por producto (p1 ×4, p2) -->
            <div class="resumen-agrupado" id="resumenAgrupado" hidden>
                <div class="panel-title" data-i18n="resumen.resumen"><?= e(t('resumen.resumen')) ?></div>
                <ul class="resumen-lista" id="resumenLista"></ul>
            </div>

            <div>
                <div class="resumen-fila"><span data-i18n="resumen.articulos"><?= e(t('resumen.articulos')) ?></span><span id="totalArticulos">0</span></div>
                <div class="resumen-total"><span data-i18n="comun.total"><?= e(t('comun.total')) ?></span><span id="lblTotal">$0.00</span></div>
                <button type="button" class="btn btn-primary btn-block btn-pagar" onclick="abrirModalPago()" data-i18n="resumen.pagar"><?= e(t('resumen.pagar')) ?></button>
            </div>
        </aside>

    </main>

    <!-- Caja cerrada: todo en gris y solo el botón para abrirla (o por qué no se puede) -->
    <div class="caja-cerrada" id="cajaCerrada">
        <div class="caja-cerrada-card">
            <div class="caja-cerrada-icono" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </div>
            <h2 id="cajaCerradaTitulo"><?= e(t('caja.revisando')) ?></h2>
            <p id="cajaCerradaTexto"></p>
            <button type="button" class="btn btn-primary btn-abrir-caja" id="btnAbrirCaja" onclick="abrirModalApertura()" data-i18n="caja.abrir" hidden><?= e(t('caja.abrir')) ?></button>
            <button type="button" class="btn btn-ghost" id="btnReintentarCaja" onclick="cargarTurno()" data-i18n="caja.reintentar" hidden><?= e(t('caja.reintentar')) ?></button>
        </div>
    </div>

    <!-- Modal: cerrar sesión -->
    <div class="modal-overlay" id="modalSalir" role="dialog" aria-modal="true" aria-labelledby="tituloSalir">
        <div class="modal-box">
            <h2 id="tituloSalir" data-i18n="sesion.cerrar"><?= e(t('sesion.cerrar')) ?></h2>
            <p class="modal-mensaje" data-i18n="sesion.pregunta"><?= e(t('sesion.pregunta')) ?></p>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="cerrarModal('modalSalir')" data-i18n="sesion.quedarme"><?= e(t('sesion.quedarme')) ?></button>
                <button type="button" class="btn btn-danger-solid" onclick="logout()" data-i18n="sesion.confirmar"><?= e(t('sesion.confirmar')) ?></button>
            </div>
        </div>
    </div>

    <!-- Modal: lista de venta (historial: una fila por cada vez que se agregó un producto).
         Va antes de los demás modales para que la confirmación y los avisos queden encima. -->
    <div class="modal-overlay" id="modalLista" role="dialog" aria-modal="true" aria-labelledby="tituloLista">
        <div class="modal-box modal-lista">
            <h2 id="tituloLista" data-i18n="lista.titulo"><?= e(t('lista.titulo')) ?></h2>
            <p class="atajo-hint" id="ayudaLista"><?= i18nReemplazar(e(t('lista.ayuda')), ['supr' => '<kbd>' . e(t('lista.tecla_supr')) . '</kbd>', 'borrar' => '<kbd>⌫</kbd>']) ?></p>
            <div class="tabla-scroll">
                <table class="tabla-venta">
                    <thead>
                        <tr>
                            <th data-i18n="lista.col_clave"><?= e(t('lista.col_clave')) ?></th>
                            <th data-i18n="lista.col_producto"><?= e(t('lista.col_producto')) ?></th>
                            <th data-i18n="lista.col_cantidad"><?= e(t('lista.col_cantidad')) ?></th>
                            <th class="col-num" data-i18n="lista.col_precio"><?= e(t('lista.col_precio')) ?></th>
                            <th class="col-num" data-i18n="lista.col_importe"><?= e(t('lista.col_importe')) ?></th>
                            <th class="col-acciones"><span class="sr-only" data-i18n="lista.col_acciones"><?= e(t('lista.col_acciones')) ?></span></th>
                        </tr>
                    </thead>
                    <tbody id="listaProductos">
                        <!-- Filas dinámicas -->
                    </tbody>
                </table>
            </div>
            <div class="modal-actions">
                <span class="lista-total"><span data-i18n="comun.total"><?= e(t('comun.total')) ?></span> <strong id="listaTotal">$0.00</strong></span>
                <button type="button" class="btn btn-primary" onclick="cerrarModal('modalLista')" data-i18n="comun.cerrar"><?= e(t('comun.cerrar')) ?></button>
            </div>
        </div>
    </div>

    <!-- Turno de caja. Orden de apilamiento: apertura < conteo < cierre < resultado,
         y todos antes de la confirmación y los avisos para que esos queden encima. -->

    <!-- Modal: abrir caja (viene lleno con lo que dejó el turno anterior) -->
    <div class="modal-overlay" id="modalApertura" role="dialog" aria-modal="true" aria-labelledby="tituloApertura">
        <div class="modal-box">
            <h2 id="tituloApertura"></h2>
            <div class="apertura-anterior" id="aperturaAnterior"></div>
            <div class="form-group">
                <label for="inputApertura" data-i18n="apertura.cuanto_hay"><?= e(t('apertura.cuanto_hay')) ?></label>
                <div class="input-dinero">
                    <span>$</span>
                    <input type="number" id="inputApertura" min="0" step="0.01" inputmode="decimal" placeholder="0.00" aria-describedby="aperturaAviso">
                </div>
                <div class="apertura-aviso" id="aperturaAviso" aria-live="polite"></div>
            </div>
            <button type="button" class="btn-enlace" onclick="contarApertura()" data-i18n="apertura.contar"><?= e(t('apertura.contar')) ?></button>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="cerrarModal('modalApertura')" data-i18n="comun.cancelar"><?= e(t('comun.cancelar')) ?></button>
                <button type="button" class="btn btn-primary" id="btnConfirmarApertura" onclick="confirmarApertura()" data-i18n="caja.abrir"><?= e(t('caja.abrir')) ?></button>
            </div>
        </div>
    </div>

    <!-- Modal: conteo de billetes y monedas. Sirve para abrir caja y para el corte (cierre). -->
    <div class="modal-overlay" id="modalCorte" role="dialog" aria-modal="true" aria-labelledby="tituloCorte">
        <div class="modal-box modal-corte">
            <div class="corte-encabezado">
                <h2 id="tituloCorte"></h2>
                <!-- Carga el fondo regular ($1,000 en piezas chicas) en el conteo -->
                <button type="button" class="corte-plantilla" onclick="pedirPlantillaFondo()" data-i18n-title="corte.plantilla_ayuda" title="<?= e(t('corte.plantilla_ayuda')) ?>">
                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18M9 4v16"/></svg>
                    <span data-i18n="corte.plantilla"><?= e(t('corte.plantilla')) ?></span>
                    <small id="cortePlantillaTotal"></small>
                </button>
            </div>
            <p class="atajo-hint" id="corteAyuda"></p>

            <h3 class="corte-seccion" data-i18n="corte.billetes"><?= e(t('corte.billetes')) ?></h3>
            <div class="dinero-grid" id="corteBilletes">
                <!-- Tarjetas de billetes (JS) -->
            </div>

            <h3 class="corte-seccion" data-i18n="corte.monedas"><?= e(t('corte.monedas')) ?></h3>
            <div class="dinero-grid dinero-grid--monedas" id="corteMonedas">
                <!-- Tarjetas de monedas (JS) -->
            </div>

            <!-- Solo en el corte: lo cobrado con tarjeta según los comprobantes de la terminal -->
            <div class="corte-tarjeta" id="corteTarjeta">
                <h3 class="corte-seccion" data-i18n="corte.tarjeta"><?= e(t('corte.tarjeta')) ?></h3>
                <div class="form-group">
                    <label for="inputVouchers" data-i18n="corte.vouchers"><?= e(t('corte.vouchers')) ?></label>
                    <div class="input-dinero">
                        <span>$</span>
                        <input type="number" id="inputVouchers" min="0" step="0.01" inputmode="decimal" placeholder="0.00" aria-describedby="errorVouchers">
                    </div>
                    <div class="campo-error" id="errorVouchers"></div>
                </div>
            </div>

            <!-- Resumen: billetes, monedas y total en efectivo -->
            <div class="corte-resumen" aria-live="polite">
                <div class="corte-fila">
                    <span><span data-i18n="corte.billetes"><?= e(t('corte.billetes')) ?></span> <small id="cortePiezasBilletes"></small></span>
                    <span id="corteTotalBilletes">$0.00</span>
                </div>
                <div class="corte-fila">
                    <span><span data-i18n="corte.monedas"><?= e(t('corte.monedas')) ?></span> <small id="cortePiezasMonedas"></small></span>
                    <span id="corteTotalMonedas">$0.00</span>
                </div>
                <div class="corte-fila corte-fila--total">
                    <span data-i18n="corte.total_efectivo"><?= e(t('corte.total_efectivo')) ?></span>
                    <strong id="corteTotal">$0.00</strong>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost corte-limpiar" onclick="pedirLimpiarCorte()" data-i18n="corte.limpiar"><?= e(t('corte.limpiar')) ?></button>
                <button type="button" class="btn btn-ghost" id="btnCorteCancelar" onclick="cerrarModal('modalCorte')" data-i18n="comun.cancelar"><?= e(t('comun.cancelar')) ?></button>
                <button type="button" class="btn btn-primary" id="btnCorteUsar" onclick="usarConteoApertura()" data-i18n="corte.usar_total"><?= e(t('corte.usar_total')) ?></button>
                <button type="button" class="btn btn-primary" id="btnCorteContinuar" onclick="continuarCierre()" data-i18n="corte.continuar"><?= e(t('corte.continuar')) ?></button>
            </div>
        </div>
    </div>

    <!-- Modal: cerrar caja — cuánto se deja y cuánto se entrega al gerente -->
    <div class="modal-overlay" id="modalCierre" role="dialog" aria-modal="true" aria-labelledby="tituloCierre">
        <div class="modal-box">
            <h2 id="tituloCierre" data-i18n="cierre.titulo"><?= e(t('cierre.titulo')) ?></h2>
            <div class="varios-resumen">
                <div class="varios-fila"><span data-i18n="cierre.contado"><?= e(t('cierre.contado')) ?></span><span id="cierreContado">$0.00</span></div>
                <div class="varios-fila"><span data-i18n="cierre.vouchers"><?= e(t('cierre.vouchers')) ?></span><span id="cierreVouchers">$0.00</span></div>
            </div>
            <div class="form-group cierre-dejar">
                <label for="inputDejado" data-i18n="cierre.dejar"><?= e(t('cierre.dejar')) ?></label>
                <div class="input-dinero">
                    <span>$</span>
                    <input type="number" id="inputDejado" min="0" step="0.01" inputmode="decimal" placeholder="0.00" aria-describedby="errorDejado cierreFondo">
                </div>
                <div class="campo-error" id="errorDejado"></div>
                <small class="cierre-fondo" id="cierreFondo"></small>
            </div>
            <div class="corte-resumen">
                <div class="corte-fila corte-fila--total">
                    <span data-i18n="cierre.entregas"><?= e(t('cierre.entregas')) ?></span>
                    <strong id="cierreEntrega">$0.00</strong>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="cerrarModal('modalCierre')" data-i18n="comun.atras"><?= e(t('comun.atras')) ?></button>
                <button type="button" class="btn btn-primary" id="btnConfirmarCierre" onclick="confirmarCierre()" data-i18n="cierre.titulo"><?= e(t('cierre.titulo')) ?></button>
            </div>
        </div>
    </div>

    <!-- Modal: resultado del corte (solo se cierra con "Aceptar") -->
    <div class="modal-overlay" id="modalCorteResultado" role="dialog" aria-modal="true" aria-labelledby="tituloCorteResultado">
        <div class="modal-box modal-confirmar">
            <div class="corte-sello" id="corteSello"></div>
            <h2 id="tituloCorteResultado" data-i18n="resultado.titulo"><?= e(t('resultado.titulo')) ?></h2>
            <div class="varios-resumen corte-detalle" id="corteDetalle"></div>
            <p class="modal-mensaje corte-revision" id="corteRevision" data-i18n="resultado.revision" hidden><?= e(t('resultado.revision')) ?></p>
            <p class="modal-mensaje" data-i18n="resultado.enviado"><?= e(t('resultado.enviado')) ?></p>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="imprimirCorte()">🧾 <span data-i18n="resultado.imprimir"><?= e(t('resultado.imprimir')) ?></span></button>
                <button type="button" class="btn btn-primary" id="btnAceptarCorte" onclick="aceptarCorte()" data-i18n="comun.aceptar"><?= e(t('comun.aceptar')) ?></button>
            </div>
        </div>
    </div>

    <!-- Modal: confirmar eliminación (un producto o toda la lista) -->
    <div class="modal-overlay" id="modalEliminar" role="dialog" aria-modal="true" aria-labelledby="tituloEliminar">
        <div class="modal-box modal-confirmar">
            <div class="icono-peligro" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
            </div>
            <h2 id="tituloEliminar" data-i18n="eliminar.titulo"><?= e(t('eliminar.titulo')) ?></h2>
            <div id="eliminarContenido"></div>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="cerrarModal('modalEliminar')" data-i18n="comun.cancelar"><?= e(t('comun.cancelar')) ?></button>
                <button type="button" class="btn btn-danger-solid" id="btnConfirmarEliminar" onclick="confirmarEliminar()" data-i18n="eliminar.confirmar"><?= e(t('eliminar.confirmar')) ?></button>
            </div>
        </div>
    </div>

    <!-- Modal: método de pago -->
    <div class="modal-overlay" id="modalPago" role="dialog" aria-modal="true" aria-labelledby="tituloPago">
        <div class="modal-box">
            <h2 id="tituloPago" data-i18n="pago.titulo"><?= e(t('pago.titulo')) ?></h2>
            <div class="modal-total-label" data-i18n="pago.total"><?= e(t('pago.total')) ?></div>
            <div class="modal-total" id="modalTotal">$0.00</div>
            <div class="metodos-pago">
                <button type="button" class="metodo-btn" onclick="abrirModalEfectivo()"><span class="metodo-icono" aria-hidden="true">💵</span><span data-i18n="pago.efectivo"><?= e(t('pago.efectivo')) ?></span></button>
                <button type="button" class="metodo-btn" onclick="pagarConTarjeta()"><span class="metodo-icono" aria-hidden="true">💳</span><span data-i18n="pago.tarjeta"><?= e(t('pago.tarjeta')) ?></span></button>
                <button type="button" class="metodo-btn" onclick="abrirModalVarios()"><span class="metodo-icono" aria-hidden="true">💵💳</span><span data-i18n="pago.varios"><?= e(t('pago.varios')) ?></span></button>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="cerrarModal('modalPago')" data-i18n="comun.cancelar"><?= e(t('comun.cancelar')) ?></button>
            </div>
        </div>
    </div>

    <!-- Modal: pago en efectivo -->
    <div class="modal-overlay" id="modalEfectivo" role="dialog" aria-modal="true" aria-labelledby="tituloEfectivo">
        <div class="modal-box">
            <h2 id="tituloEfectivo" data-i18n="efectivo.titulo"><?= e(t('efectivo.titulo')) ?></h2>
            <div class="modal-total-label" data-i18n="pago.total"><?= e(t('pago.total')) ?></div>
            <div class="modal-total" id="efectivoTotal">$0.00</div>
            <div class="form-group">
                <label for="inputRecibido" data-i18n="efectivo.recibido"><?= e(t('efectivo.recibido')) ?></label>
                <div class="input-dinero">
                    <span>$</span>
                    <input type="number" id="inputRecibido" min="0" step="0.01" inputmode="decimal" placeholder="0.00">
                </div>
            </div>
            <div class="cambio-preview" id="cambioPreview"></div>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="volverAMetodos()" data-i18n="comun.atras"><?= e(t('comun.atras')) ?></button>
                <button type="button" class="btn btn-primary" id="btnCobrarEfectivo" onclick="cobrarEfectivo()" disabled data-i18n="efectivo.cobrar"><?= e(t('efectivo.cobrar')) ?></button>
            </div>
        </div>
    </div>

    <!-- Modal: pago combinado (una parte con tarjeta y el resto en efectivo, con cambio) -->
    <div class="modal-overlay" id="modalVarios" role="dialog" aria-modal="true" aria-labelledby="tituloVarios">
        <div class="modal-box">
            <h2 id="tituloVarios" data-i18n="varios.titulo"><?= e(t('varios.titulo')) ?></h2>
            <div class="modal-total-label" data-i18n="pago.total"><?= e(t('pago.total')) ?></div>
            <div class="modal-total" id="variosTotal">$0.00</div>
            <div class="form-group">
                <label for="inputVariosEfectivo" data-i18n="efectivo.recibido"><?= e(t('efectivo.recibido')) ?></label>
                <div class="input-dinero">
                    <span>$</span>
                    <input type="number" id="inputVariosEfectivo" min="0" step="0.01" inputmode="decimal" placeholder="0.00">
                </div>
            </div>
            <!-- Se llena sola con lo que falta después del efectivo, hasta que el cajero la escriba -->
            <div class="form-group">
                <label for="inputVariosTarjeta" data-i18n="varios.tarjeta"><?= e(t('varios.tarjeta')) ?></label>
                <div class="input-dinero">
                    <span>$</span>
                    <input type="number" id="inputVariosTarjeta" min="0" step="0.01" inputmode="decimal" placeholder="0.00" aria-describedby="errorVariosTarjeta">
                </div>
                <div class="campo-error" id="errorVariosTarjeta"></div>
            </div>
            <!-- Desglose: lo capturado y, al final, lo que falta o el cambio -->
            <div class="varios-resumen" aria-live="polite">
                <div class="varios-fila"><span data-i18n="efectivo.recibido"><?= e(t('efectivo.recibido')) ?></span><span id="variosResEfectivo">$0.00</span></div>
                <div class="varios-fila"><span data-i18n="varios.tarjeta"><?= e(t('varios.tarjeta')) ?></span><span id="variosResTarjeta">$0.00</span></div>
                <div class="varios-fila varios-estado" id="variosEstado"><span id="variosEstadoEtiqueta"></span><span id="variosEstadoMonto"></span></div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="volverAMetodos()" data-i18n="comun.atras"><?= e(t('comun.atras')) ?></button>
                <button type="button" class="btn btn-primary" id="btnCobrarVarios" onclick="cobrarVarios()" disabled data-i18n="efectivo.cobrar"><?= e(t('efectivo.cobrar')) ?></button>
            </div>
        </div>
    </div>

    <!-- Modal: venta completada -->
    <div class="modal-overlay" id="modalVenta" role="dialog" aria-modal="true" aria-labelledby="tituloVenta">
        <div class="modal-box">
            <h2 id="tituloVenta" data-i18n="venta.titulo"><?= e(t('venta.titulo')) ?></h2>
            <p class="modal-mensaje" id="ventaMensaje"></p>
            <div id="ventaCambio" style="display:none;">
                <div class="cambio-resultado">
                    <div class="cambio-label" data-i18n="efectivo.cambio"><?= e(t('efectivo.cambio')) ?></div>
                    <div class="cambio-monto" id="ventaCambioMonto">$0.00</div>
                </div>
                <div class="cambio-detalle" id="ventaCambioDetalle"></div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" id="btnReimprimir" onclick="imprimirTicket(ultimoTicket)">🧾 <span data-i18n="venta.imprimir"><?= e(t('venta.imprimir')) ?></span></button>
                <button type="button" class="btn btn-primary" id="btnFinalizarVenta" onclick="finalizarVenta()" data-i18n="comun.aceptar"><?= e(t('comun.aceptar')) ?></button>
            </div>
        </div>
    </div>

    <!-- Modal: avisos (existencias, errores al cobrar) -->
    <div class="modal-overlay" id="modalAviso" role="alertdialog" aria-modal="true" aria-labelledby="tituloAviso" aria-describedby="avisoMensaje">
        <div class="modal-box modal-confirmar">
            <div class="icono-aviso" id="avisoIcono" aria-hidden="true"></div>
            <h2 id="tituloAviso"></h2>
            <p class="modal-mensaje" id="avisoMensaje"></p>
            <div id="avisoContenido"></div>
            <div class="modal-actions" id="avisoAcciones"></div>
        </div>
    </div>

    <!-- Notificaciones breves (existencias bajas, ajustes) -->
    <div class="toasts" id="toasts" role="status" aria-live="polite"></div>

    <script>
    // Textos traducidos (cajero/lang/*.php). tr('clave', {var}) y trn('clave', n, {var}) para plurales.
    // Llegan los de todos los idiomas; cambiarIdioma() solo cambia cuál se usa.
    window.TEXTOS_IDIOMAS = <?= json_encode($textosPorIdioma, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    window.IDIOMA = <?= json_encode(idiomaActual()) ?>;
    window.TEXTOS = window.TEXTOS_IDIOMAS[window.IDIOMA];
    function trReemplazar(texto, vars) {
        return String(texto).replace(/\{(\w+)\}/g, (m, k) => (vars && k in vars) ? vars[k] : m);
    }
    function tr(clave, vars = {}) {
        const texto = window.TEXTOS[clave];
        return trReemplazar(Array.isArray(texto) ? texto[1] : (texto ?? clave), vars);
    }
    function trn(clave, n, vars = {}) {
        const formas = window.TEXTOS[clave] || [clave, clave];
        return trReemplazar(Math.abs(n) === 1 ? formas[0] : formas[1], { n, ...vars });
    }
    </script>
    <script src="../auth.js"></script>
    <script src="../perfil/perfil.js?v=<?= filemtime(__DIR__ . '/../perfil/perfil.js') ?>"></script>
    <script>
        requireRol(3);

        let carrito = [];
        let indiceSeleccionado = null;
        let accionEliminar = null;
        let totalActual = 0;
        let cobrando = false;
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        const inputBuscar = document.getElementById('inputBuscar');
        const inputRecibido = document.getElementById('inputRecibido');

        // Precios con separador de miles y dos decimales: $1,249.90 (también en el ticket)
        const FORMATO_PESOS = new Intl.NumberFormat('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const formatoDinero = (n) => `$${FORMATO_PESOS.format(Number(n) || 0)}`;
        const aCentavos = (n) => Math.round(n * 100);

        /* ---------- Modales ---------- */
        function abrirModal(id) {
            document.getElementById(id).classList.add('open');
        }

        function cerrarModal(id) {
            document.getElementById(id).classList.remove('open');
        }

        const MODALES_SIN_ESCAPE = ['modalVenta', 'modalCorteResultado'];

        // Clic fuera de la caja o Escape cierra el modal, excepto el de venta
        // completada: la venta solo se cierra con "Aceptar".
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay && !MODALES_SIN_ESCAPE.includes(overlay.id)) cerrarModal(overlay.id);
            });
        });

        // El modal de encima es el último abierto en el documento
        function modalSuperior() {
            return [...document.querySelectorAll('.modal-overlay.open')].pop() || null;
        }

        // Escape cierra solo el de encima: la lista puede tener una confirmación abierta
        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Escape') return;
            const abierto = modalSuperior();
            if (abierto && !MODALES_SIN_ESCAPE.includes(abierto.id)) cerrarModal(abierto.id);
        });

        /* ---------- Idioma ---------- */
        // El tema claro/oscuro y el menú los maneja ../perfil/perfil.js.
        // El idioma (en el menú de perfil) cambia aquí mismo sin recargar: si se está
        // cobrando, la venta (carrito, búsqueda, categoría elegida) sigue intacta.
        // En segundo plano se guarda en la sesión, para que los mensajes del
        // servidor al cobrar y la fecha del ticket salgan en el mismo idioma.
        const menuIdioma = document.querySelector('[data-perfil-idioma]');

        menuIdioma.addEventListener('submit', (e) => {
            e.preventDefault();
            const codigo = e.submitter?.value;
            if (!codigo || codigo === window.IDIOMA) return;

            aplicarIdioma(codigo);

            const datos = new FormData(menuIdioma);
            datos.set('idioma', codigo);
            fetch(menuIdioma.action, { method: 'POST', body: datos, headers: { Accept: 'application/json' } })
                .catch(() => { /* sin conexión: la caja ya cambió; la sesión se queda con el anterior */ });
        });

        function aplicarIdioma(codigo) {
            if (!window.TEXTOS_IDIOMAS[codigo]) return;
            window.IDIOMA = codigo;
            window.TEXTOS = window.TEXTOS_IDIOMAS[codigo];
            document.documentElement.lang = codigo;
            document.title = `${tr('caja.titulo')} - Koaly`;

            // Textos fijos de la página, marcados con su clave
            document.querySelectorAll('[data-i18n]').forEach(el => { el.textContent = tr(el.dataset.i18n); });
            ['placeholder', 'aria-label', 'title'].forEach(attr => {
                document.querySelectorAll(`[data-i18n-${attr}]`).forEach(el => {
                    el.setAttribute(attr, tr(el.getAttribute(`data-i18n-${attr}`)));
                });
            });
            pintarAyudaLista();
            pintarEstadoCaja();
            window.perfilAplicarIdioma?.(codigo);   // menú de perfil (perfil/perfil.js)

            // Lo que dibuja el JS: catálogo (conserva la categoría elegida), lista de venta y panel derecho
            if (catalogo.length || categorias.length) {
                const contenedor = document.getElementById('catalogo');
                const scroll = contenedor.scrollTop;   // sin esto regresaría hasta arriba
                renderCatalogo();
                contenedor.scrollTop = scroll;
            }
            renderCarrito();
        }

        // "Selecciona un producto y presiona [Supr] o [⌫]..." con las teclas en <kbd>
        function pintarAyudaLista() {
            const teclas = { supr: tr('lista.tecla_supr'), borrar: '⌫' };
            const partes = tr('lista.ayuda').split(/(\{supr\}|\{borrar\})/);
            document.getElementById('ayudaLista').replaceChildren(...partes.map(parte => {
                const tecla = parte.match(/^\{(supr|borrar)\}$/);
                if (!tecla) return document.createTextNode(parte);
                const kbd = document.createElement('kbd');
                kbd.textContent = teclas[tecla[1]];
                return kbd;
            }));
        }

        /* ---------- Cursor personalizado ---------- */
        // Punto morado que sigue al mouse (como en el panel de gerente). Solo con
        // mouse; en pantallas táctiles se queda el cursor normal.
        function initCustomCursor() {
            if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;

            const dot = document.createElement('div');
            dot.className = 'cursor-dot';
            dot.setAttribute('aria-hidden', 'true');
            document.body.appendChild(dot);
            document.body.classList.add('cursor-custom');

            let x = window.innerWidth / 2;
            let y = window.innerHeight / 2;
            let raf = null;

            const render = () => {
                dot.style.transform = `translate3d(${x}px, ${y}px, 0) translate(-50%, -50%)`;
                raf = null;
            };

            document.addEventListener('mousemove', (e) => {
                x = e.clientX;
                y = e.clientY;
                if (!raf) raf = requestAnimationFrame(render);
            });

            // Crece sobre lo que se puede pulsar
            const interactivo = 'a, button, input, select, textarea, label, [role="button"], [role="option"], .result-item, table.tabla-venta tbody tr:not(.empty-row)';
            document.addEventListener('mouseover', (e) => {
                if (e.target.closest(interactivo)) dot.classList.add('is-hover');
            });
            document.addEventListener('mouseout', (e) => {
                if (e.target.closest(interactivo)) dot.classList.remove('is-hover');
            });

            render();
        }
        initCustomCursor();

        /* ---------- Búsqueda ---------- */
        // Lo que se escribe filtra el catálogo por nombre o clave (código de
        // barras), en todas las categorías y sin distinguir mayúsculas ni acentos.
        // Enter (o el lector de código de barras) agrega la clave exacta o el único
        // producto que queda; si no está en el catálogo cargado se pregunta al
        // servidor (puede ser un producto dado de alta después de abrir la caja).
        let textoBusqueda = '';        // lo que escribió el cajero, para los mensajes
        let filtroBusqueda = '';       // el mismo texto normalizado; '' = sin búsqueda
        let numeroBusqueda = 0;        // descarta respuestas del servidor que llegan tarde
        let cuadroBusqueda = null;

        // Sin acentos ni mayúsculas: "Café" y "cafe" coinciden
        function normalizar(texto) {
            return String(texto ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
        }

        inputBuscar.addEventListener('input', () => {
            // Un lector de código de barras escribe muy rápido: se filtra una vez por cuadro
            if (cuadroBusqueda) return;
            cuadroBusqueda = requestAnimationFrame(() => {
                cuadroBusqueda = null;
                filtrarCatalogo(inputBuscar.value);
            });
        });

        inputBuscar.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                agregarDesdeBusqueda();
            } else if (e.key === 'Escape' && inputBuscar.value !== '') {
                limpiarBusqueda();
            }
        });

        function filtrarCatalogo(texto) {
            const filtro = normalizar(texto);
            textoBusqueda = texto.trim();
            if (filtro === filtroBusqueda) return;
            filtroBusqueda = filtro;
            aplicarFiltros();
            document.getElementById('catalogo').scrollTop = 0;
        }

        // Cada palabra debe estar en el nombre o en la clave, en cualquier orden:
        // "coca lata" encuentra "Coca-Cola Lata 355ml"
        function palabrasDe(filtro) {
            return filtro.split(/\s+/).filter(Boolean);
        }

        // nombreBusqueda y claveBusqueda se calculan al cargar el catálogo
        function coincideBusqueda(p, palabras) {
            return palabras.every(w => p.nombreBusqueda.includes(w) || p.claveBusqueda.includes(w));
        }

        // El producto que agrega Enter: la clave exacta, o el único que coincide
        function productoDeBusqueda(filtro) {
            const exacto = catalogo.find(p => p.claveBusqueda === filtro);
            if (exacto) return exacto;
            const palabras = palabrasDe(filtro);
            const coincidencias = catalogo.filter(p => coincideBusqueda(p, palabras));
            return coincidencias.length === 1 ? coincidencias[0] : null;
        }

        async function agregarDesdeBusqueda() {
            const texto = inputBuscar.value.trim();
            if (!texto) return;
            filtrarCatalogo(texto);

            const producto = productoDeBusqueda(filtroBusqueda);
            if (producto) {
                agregarYLimpiar(producto);
                return;
            }
            // Varias coincidencias: el cajero elige la tarjeta
            const palabras = palabrasDe(filtroBusqueda);
            if (catalogo.some(p => coincideBusqueda(p, palabras))) return;

            const numero = ++numeroBusqueda;
            buscando(true);
            let productos;
            try {
                const res = await fetch(`buscar_producto.php?q=${encodeURIComponent(texto)}`);
                productos = await res.json();
                if (!Array.isArray(productos)) productos = [];
            } catch (error) {
                if (numero !== numeroBusqueda) return;
                buscando(false);
                alert(tr('buscar.sin_conexion_alerta'));
                return;
            }
            if (numero !== numeroBusqueda) return;
            buscando(false);

            const exacto = productos.find(p => normalizar(p.codigo) === normalizar(texto));
            if (exacto || productos.length === 1) {
                agregarYLimpiar(exacto || productos[0]);
                cargarCatalogo();   // para que su tarjeta aparezca
            }
        }

        function agregarYLimpiar(producto) {
            const antes = cantidadDeProducto(producto.id_producto);
            agregarAlCarrito(producto);
            limpiarBusqueda();
            const datos = tarjetasCatalogo.get(producto.id_producto);
            if (datos && cantidadDeProducto(producto.id_producto) > antes) animarTarjeta(datos.tarjeta);
        }

        function buscando(activo) {
            document.querySelector('.search-box').classList.toggle('cargando', activo);
        }

        // Vacía el buscador y vuelve a mostrar todo el catálogo
        function limpiarBusqueda({ enfocar = true } = {}) {
            numeroBusqueda++;
            buscando(false);
            inputBuscar.value = '';
            filtrarCatalogo('');
            // Si se abrió un aviso (p. ej. producto agotado), el foco se queda en él
            if (enfocar && !document.querySelector('.modal-overlay.open')) inputBuscar.focus();
        }

        /* ---------- Carrito ---------- */
        const ICONO_BASURA = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>';
        let indiceResaltado = null;

        const STOCK_BAJO = 5;

        // El carrito es el historial de escaneo: si el producto es el mismo que el
        // de la última fila se suma ahí; si no, se crea una fila nueva (p1 ×3, p2, p1).
        // El resumen y el ticket las agrupan por producto, y las existencias se
        // validan contra la suma de todas sus filas.
        function agregarAlCarrito(producto) {
            const stock = Number(producto.stock) || 0;
            actualizarStockProducto(producto.id_producto, stock);

            if (stock <= 0) {
                avisoAgotado(producto.nombre);
                renderCarrito();
                return;
            }

            const enLista = cantidadDeProducto(producto.id_producto);
            if (enLista + 1 > stock) {
                avisoInsuficiente(producto.nombre, stock, enLista + 1);
                renderCarrito();
                return;
            }

            const ultima = carrito[carrito.length - 1];
            if (ultima && ultima.id_producto === producto.id_producto) {
                ultima.cantidad++;
            } else {
                carrito.push({
                    id_producto: producto.id_producto,
                    codigo: producto.codigo,
                    nombre: producto.nombre,
                    imagen: producto.imagen || null,
                    cantidad: 1,
                    precio: Number(producto.precio) || 0,
                    stock: stock
                });
            }
            indiceResaltado = carrito.length - 1;
            renderCarrito();

            // La fila nueva queda abajo: se desplaza la tabla para que se vea
            const filaNueva = document.querySelector('#listaProductos tr:last-child');
            if (filaNueva) filaNueva.scrollIntoView({ block: 'nearest', behavior: 'smooth' });

            if (stock < STOCK_BAJO) {
                mostrarToast(tr('aviso.quedan_solo', { unidades: trn('comun.n_unidades', stock), p: producto.nombre }), 'aviso');
            }
        }

        // Unidades de un producto sumando todas sus filas (opcionalmente sin contar una)
        function cantidadDeProducto(idProducto, exceptoIndex = -1) {
            return carrito.reduce((n, item, i) =>
                (i !== exceptoIndex && item.id_producto === idProducto) ? n + item.cantidad : n, 0);
        }

        function actualizarStockProducto(idProducto, stock) {
            carrito.forEach(item => {
                if (item.id_producto === idProducto) item.stock = stock;
            });
            const enCatalogo = catalogo.find(p => p.id_producto === idProducto);
            if (enCatalogo) enCatalogo.stock = stock;
        }

        // Cantidad de una fila entre 1 y lo que queda disponible para ella (existencias
        // menos lo que ya piden las otras filas del mismo producto). Si se pide de más,
        // avisa y la deja en el máximo (el servidor vuelve a validar al cobrar).
        function cambiarCantidad(index, nuevaCantidad) {
            const item = carrito[index];
            let valor = Math.floor(nuevaCantidad);
            if (isNaN(valor) || valor < 1) valor = 1;
            const otras = cantidadDeProducto(item.id_producto, index);
            if (otras + valor > item.stock) {
                avisoInsuficiente(item.nombre, item.stock, otras + valor);
                valor = Math.max(1, item.stock - otras);
            }
            item.cantidad = valor;
            indiceResaltado = index;
            renderCarrito();
        }

        // Quita una fila; si las de antes y después son del mismo producto se
        // juntan, para que el escaneo siga sin líneas seguidas repetidas
        function quitarFila(index) {
            carrito.splice(index, 1);
            if (indiceSeleccionado === index) indiceSeleccionado = null;
            else if (indiceSeleccionado !== null && indiceSeleccionado > index) indiceSeleccionado--;

            const antes = carrito[index - 1];
            const despues = carrito[index];
            if (antes && despues && antes.id_producto === despues.id_producto) {
                antes.cantidad += despues.cantidad;
                carrito.splice(index, 1);
                if (indiceSeleccionado === index) indiceSeleccionado = index - 1;
                else if (indiceSeleccionado !== null && indiceSeleccionado > index) indiceSeleccionado--;
            }
        }

        // Productos agrupados en el orden en que se agregaron por primera vez
        function agruparCarrito() {
            const grupos = new Map();
            carrito.forEach(item => {
                let g = grupos.get(item.id_producto);
                if (!g) {
                    g = { id_producto: item.id_producto, nombre: item.nombre, precio: item.precio, stock: item.stock, cantidad: 0, importe: 0 };
                    grupos.set(item.id_producto, g);
                }
                g.cantidad += item.cantidad;
                g.importe += item.cantidad * item.precio;
            });
            return [...grupos.values()];
        }

        /* ---------- Catálogo ---------- */
        // Tarjetas de todos los productos activos; un clic agrega uno a la venta.
        // Las tarjetas se crean una vez y solo se actualizan sus etiquetas.
        const ICONO_PRODUCTO = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5"/><path d="M12 13v8"/></svg>';
        let catalogo = [];
        let categorias = [];                  // { id, nombre } en el orden en que se muestran
        let categoriaActiva = 'todas';        // 'todas', el id de una categoría o AGOTADOS
        const SIN_CATEGORIA = 0;              // productos sin categoría asignada
        const AGOTADOS = 'agotados';          // sección y chip de los productos sin existencias, al final
        const tarjetasCatalogo = new Map();   // id_producto -> elementos de su tarjeta

        async function cargarCatalogo() {
            const contenedor = document.getElementById('catalogo');
            if (catalogo.length === 0) contenedor.replaceChildren(mensajeCatalogo(tr('catalogo.cargando')));
            let datos;
            try {
                const res = await fetch('catalogo_productos.php');
                datos = await res.json();
                if (!Array.isArray(datos?.productos) || !Array.isArray(datos?.categorias)) throw new Error('Respuesta inválida');
            } catch (error) {
                // Si falla una recarga se conserva el catálogo que ya se ve
                if (catalogo.length === 0) contenedor.replaceChildren(mensajeCatalogo(tr('catalogo.error')));
                return;
            }
            catalogo = datos.productos;
            categorias = datos.categorias.map(c => ({ id: Number(c.id_categoria), nombreBd: c.nombre_categoria }));

            // Los productos sin categoría (o con una que ya no existe) van al final
            const idsCategorias = new Set(categorias.map(c => c.id));
            catalogo.forEach(p => {
                p.id_categoria = Number(p.id_categoria) || SIN_CATEGORIA;
                if (!idsCategorias.has(p.id_categoria)) p.id_categoria = SIN_CATEGORIA;
                // Para el buscador, una vez por carga y no en cada tecla
                p.nombreBusqueda = normalizar(p.nombre);
                p.claveBusqueda = normalizar(p.codigo);
            });
            if (catalogo.some(p => p.id_categoria === SIN_CATEGORIA)) {
                categorias.push({ id: SIN_CATEGORIA, nombreBd: null });
            }
            if (categoriaActiva !== 'todas' && categoriaActiva !== AGOTADOS && !categorias.some(c => c.id === categoriaActiva)) {
                categoriaActiva = 'todas';
            }

            renderCatalogo();
        }

        // Nombre a mostrar en el idioma actual: categoria.<nombre en la BD> si existe, si no tal cual
        function nombreCategoria(c) {
            if (c.id === SIN_CATEGORIA) return tr('categorias.sin_categoria');
            return window.TEXTOS['categoria.' + c.nombreBd] ?? c.nombreBd;
        }

        function mensajeCatalogo(texto) {
            const div = document.createElement('div');
            div.className = 'catalogo-mensaje';
            div.textContent = texto;
            return div;
        }

        const estaAgotado = (p) => (Number(p.stock) || 0) <= 0;

        // Productos de una categoría que sí se pueden vender (los agotados van a su propia sección)
        const disponiblesDe = (idCategoria) => catalogo.filter(p => p.id_categoria === idCategoria && !estaAgotado(p));

        function crearSeccion(valor, nombre, productos, mensajeVacia, clase = '') {
            const seccion = document.createElement('section');
            seccion.className = ('catalogo-seccion ' + clase).trim();
            seccion.dataset.categoria = valor;
            seccion.dataset.vacia = productos.length === 0 ? '1' : '';

            const titulo = document.createElement('h3');
            titulo.className = 'catalogo-seccion-titulo';
            titulo.textContent = nombre;
            const conteo = document.createElement('span');
            conteo.className = 'catalogo-seccion-conteo';
            conteo.dataset.total = productos.length;
            conteo.textContent = productos.length;
            titulo.appendChild(conteo);

            const grid = document.createElement('div');
            grid.className = 'catalogo-grid';
            if (productos.length === 0) {
                grid.appendChild(mensajeCatalogo(mensajeVacia));
            } else {
                grid.append(...productos.map(crearTarjeta));
            }

            seccion.append(titulo, grid);
            return seccion;
        }

        // Una sección por categoría (las vacías también, con su aviso) con los
        // productos que hay, y al final "Agotados" con todos los que no tienen
        // existencias. Los filtros solo muestran u ocultan secciones y tarjetas.
        function renderCatalogo() {
            const contenedor = document.getElementById('catalogo');
            tarjetasCatalogo.clear();

            if (catalogo.length === 0 && categorias.length === 0) {
                document.getElementById('filtroCategorias').replaceChildren();
                contenedor.replaceChildren(mensajeCatalogo(tr('catalogo.vacio')));
                return;
            }

            // Así actualizarCatalogo() sabe si un producto cambió de sección
            catalogo.forEach(p => { p.enAgotados = estaAgotado(p); });

            const secciones = categorias.map(c => {
                const hayAgotados = catalogo.some(p => p.id_categoria === c.id && p.enAgotados);
                return crearSeccion(c.id, nombreCategoria(c), disponiblesDe(c.id),
                    tr(hayAgotados ? 'categorias.todos_agotados' : 'categorias.vacia'));
            });
            secciones.push(crearSeccion(AGOTADOS, tr('categorias.agotados'), catalogo.filter(estaAgotado),
                tr('categorias.sin_agotados'), 'catalogo-seccion--agotados'));

            const sinResultados = mensajeCatalogo('');
            sinResultados.id = 'catalogoSinResultados';
            sinResultados.setAttribute('role', 'status');

            contenedor.replaceChildren(...secciones, sinResultados);
            renderFiltroCategorias();
            aplicarFiltros();
            actualizarCatalogo();
        }

        function renderFiltroCategorias() {
            const filtro = document.getElementById('filtroCategorias');
            const chip = (valor, nombre, cantidad, clase = '') => {
                const boton = document.createElement('button');
                boton.type = 'button';
                boton.className = ('categoria-chip ' + clase).trim();
                boton.dataset.valor = valor;
                boton.classList.toggle('vacia', cantidad === 0);
                boton.textContent = nombre;
                const conteo = document.createElement('span');
                conteo.className = 'categoria-chip-conteo';
                conteo.textContent = cantidad;
                boton.appendChild(conteo);
                boton.addEventListener('click', () => {
                    categoriaActiva = valor;
                    // Elegir una categoría deja la búsqueda
                    if (filtroBusqueda) limpiarBusqueda({ enfocar: false });
                    aplicarFiltros();
                    document.getElementById('catalogo').scrollTop = 0;
                });
                return boton;
            };

            filtro.replaceChildren(
                chip('todas', tr('categorias.todas'), catalogo.length),
                ...categorias.map(c => chip(c.id, nombreCategoria(c), disponiblesDe(c.id).length)),
                chip(AGOTADOS, tr('categorias.agotados'), catalogo.filter(estaAgotado).length, 'categoria-chip--agotados')
            );
        }

        // Sin búsqueda: "Todas" muestra solo las secciones con productos, para no
        // llenar de avisos; al elegir una categoría vacía se ve su sección con el aviso.
        // Con búsqueda: se busca en todas las categorías (ninguna queda marcada) y
        // solo se ven las tarjetas que coinciden, con su conteo por sección.
        function aplicarFiltros() {
            const conBusqueda = filtroBusqueda !== '';
            document.querySelectorAll('#filtroCategorias .categoria-chip').forEach(boton => {
                const activo = !conBusqueda && String(boton.dataset.valor) === String(categoriaActiva);
                boton.classList.toggle('activo', activo);
                boton.setAttribute('aria-pressed', activo ? 'true' : 'false');
            });

            // La tarjeta que agregaría Enter se marca
            const objetivo = conBusqueda ? productoDeBusqueda(filtroBusqueda) : null;
            const palabras = palabrasDe(filtroBusqueda);
            let visibles = 0;
            tarjetasCatalogo.forEach(({ producto, tarjeta }) => {
                const ver = !conBusqueda || coincideBusqueda(producto, palabras);
                tarjeta.hidden = !ver;
                tarjeta.classList.toggle('objetivo-enter', ver && producto === objetivo);
                if (ver) visibles++;
            });

            document.querySelectorAll('#catalogo .catalogo-seccion').forEach(seccion => {
                const conteo = seccion.querySelector('.catalogo-seccion-conteo');
                if (conBusqueda) {
                    const enSeccion = seccion.querySelectorAll('.producto-card:not([hidden])').length;
                    seccion.hidden = enSeccion === 0;
                    conteo.textContent = enSeccion;
                } else {
                    seccion.hidden = categoriaActiva === 'todas'
                        ? seccion.dataset.vacia === '1'
                        : seccion.dataset.categoria !== String(categoriaActiva);
                    conteo.textContent = conteo.dataset.total;
                }
            });

            // Línea que separa los agotados, solo si hay otras secciones arriba
            const agotados = document.querySelector('#catalogo .catalogo-seccion--agotados');
            if (agotados) {
                const hayArriba = [...document.querySelectorAll('#catalogo .catalogo-seccion:not(.catalogo-seccion--agotados)')].some(sec => !sec.hidden);
                agotados.classList.toggle('separada', hayArriba);
            }

            const sinResultados = document.getElementById('catalogoSinResultados');
            if (sinResultados) {
                sinResultados.hidden = !conBusqueda || visibles > 0;
                sinResultados.textContent = conBusqueda && visibles === 0 ? tr('buscar.sin_resultados', { q: textoBusqueda }) : '';
            }
        }

        function crearTarjeta(p) {
            // La tarjeta lleva dos botones: toda el área agrega uno y el círculo
            // rojo de la esquina resta uno (para corregir un clic de más)
            const tarjeta = document.createElement('div');
            tarjeta.className = 'producto-card';

            const agregar = document.createElement('button');
            agregar.type = 'button';
            agregar.className = 'producto-card-agregar';

            const marco = document.createElement('div');
            marco.className = 'producto-card-img';
            if (p.imagen) {
                const img = document.createElement('img');
                img.src = `../${p.imagen}`;
                img.alt = '';
                img.loading = 'lazy';
                marco.appendChild(img);
            } else {
                marco.innerHTML = ICONO_PRODUCTO;
            }

            const enVenta = document.createElement('span');
            enVenta.className = 'producto-card-cantidad';

            const nombre = document.createElement('span');
            nombre.className = 'producto-card-nombre';
            nombre.textContent = p.nombre;

            const pie = document.createElement('span');
            pie.className = 'producto-card-pie';
            const precio = document.createElement('span');
            precio.className = 'producto-card-precio';
            precio.textContent = formatoDinero(Number(p.precio) || 0);
            const existencias = document.createElement('span');
            pie.append(precio, existencias);

            agregar.append(marco, enVenta, nombre, pie);
            agregar.addEventListener('click', () => {
                const antes = cantidadDeProducto(p.id_producto);
                agregarAlCarrito(p);
                if (cantidadDeProducto(p.id_producto) > antes) animarTarjeta(tarjeta);
            });

            const restar = document.createElement('button');
            restar.type = 'button';
            restar.className = 'producto-card-restar';
            restar.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true"><path d="M6 12h12"/></svg>';
            restar.title = tr('catalogo.quitar_uno');
            restar.setAttribute('aria-label', tr('catalogo.quitar_uno_de', { p: p.nombre }));
            restar.addEventListener('click', () => {
                restarUno(p.id_producto);
                animarTarjeta(tarjeta);
            });

            tarjeta.append(agregar, restar);
            tarjetasCatalogo.set(p.id_producto, { producto: p, tarjeta, agregar, enVenta, existencias });
            return tarjeta;
        }

        function animarTarjeta(tarjeta) {
            tarjeta.classList.remove('bump');
            void tarjeta.offsetWidth;   // reinicia la animación
            tarjeta.classList.add('bump');
        }

        // Resta una unidad de la fila más reciente del producto; si esa fila
        // queda en 0 se quita de la lista
        function restarUno(idProducto) {
            const index = carrito.findLastIndex(item => item.id_producto === idProducto);
            if (index === -1) return;
            if (carrito[index].cantidad > 1) {
                carrito[index].cantidad--;
                indiceResaltado = index;
            } else {
                quitarFila(index);
            }
            renderCarrito();
        }

        // Cantidad en la venta y existencias de cada tarjeta
        function actualizarCatalogo() {
            // Si al revisar existencias un producto se agotó (o volvió a haber), cambia
            // de sección: se arma de nuevo sin mover el scroll. renderCatalogo() vuelve
            // a llamar aquí, ya con todo en su lugar.
            if (catalogo.some(p => p.enAgotados !== undefined && p.enAgotados !== estaAgotado(p))) {
                const contenedor = document.getElementById('catalogo');
                const scroll = contenedor.scrollTop;
                renderCatalogo();
                contenedor.scrollTop = scroll;
                return;
            }
            tarjetasCatalogo.forEach(({ producto, tarjeta, agregar, enVenta, existencias }) => {
                const stock = Number(producto.stock) || 0;
                const cantidad = cantidadDeProducto(producto.id_producto);

                tarjeta.classList.toggle('en-venta', cantidad > 0);
                tarjeta.classList.toggle('agotado', stock <= 0);
                enVenta.textContent = cantidad > 0 ? `×${cantidad}` : '';

                if (stock <= 0) {
                    existencias.className = 'stock-badge stock-agotado';
                    existencias.textContent = tr('comun.agotado');
                } else if (stock < STOCK_BAJO) {
                    existencias.className = 'stock-badge stock-bajo';
                    existencias.textContent = tr('stock.quedan', { n: stock });
                } else {
                    existencias.className = '';
                    existencias.textContent = '';
                }

                agregar.setAttribute('aria-label', tr('catalogo.agregar_aria', { p: producto.nombre, precio: formatoDinero(Number(producto.precio) || 0) }) +
                    (cantidad > 0 ? tr('catalogo.en_venta_aria', { n: cantidad }) : '') + (stock <= 0 ? tr('catalogo.agotado_aria') : ''));
            });
        }

        /* ---------- Avisos de existencias ---------- */
        function avisoAgotado(nombre) {
            mostrarAviso({
                tipo: 'peligro',
                titulo: tr('aviso.agotado_titulo'),
                mensaje: tr('aviso.agotado', { p: nombre })
            });
        }

        function avisoInsuficiente(nombre, stock, pedida) {
            mostrarAviso({
                tipo: 'aviso',
                titulo: tr('aviso.insuficiente_titulo'),
                mensaje: stock > 0
                    ? tr('aviso.insuficiente', { pedida, p: nombre, disponibles: trn('comun.n_disponibles', stock) })
                    : tr('aviso.sin_existencias', { p: nombre })
            });
        }

        // Texto de la etiqueta de existencias de una fila (null si no hace falta)
        function etiquetaExistencias(item) {
            if (item.stock <= 0) return { texto: tr('comun.agotado'), clase: 'stock-agotado' };
            if (cantidadDeProducto(item.id_producto) > item.stock) return { texto: tr('stock.solo_hay', { n: item.stock }), clase: 'stock-agotado' };
            if (item.stock < STOCK_BAJO) return { texto: tr('stock.quedan', { n: item.stock }), clase: 'stock-bajo' };
            return null;
        }

        // Pide al servidor las existencias actuales y las aplica al carrito.
        // Devuelve los productos (agrupados) que piden más de lo disponible,
        // o null si no hubo conexión.
        async function actualizarExistencias() {
            const ids = [...new Set(carrito.map(i => i.id_producto))].join(',');
            let filas;
            try {
                const res = await fetch(`verificar_existencias.php?ids=${encodeURIComponent(ids)}`);
                filas = await res.json();
                if (!Array.isArray(filas)) return null;
            } catch (error) {
                return null;
            }
            const stockPorId = new Map(filas.map(f => [Number(f.id_producto), Number(f.stock)]));
            carrito.forEach(item => {
                item.stock = stockPorId.get(Number(item.id_producto)) ?? 0;
            });
            catalogo.forEach(p => {
                const stock = stockPorId.get(Number(p.id_producto));
                if (stock !== undefined) p.stock = stock;
            });
            renderCarrito();
            return agruparCarrito().filter(g => g.cantidad > g.stock);
        }

        function avisoFaltantes(faltantes) {
            const lista = document.createElement('ul');
            lista.className = 'lista-faltantes';
            faltantes.forEach(g => {
                const li = document.createElement('li');
                const nombre = document.createElement('strong');
                nombre.textContent = g.nombre;
                const detalle = document.createElement('span');
                detalle.textContent = g.stock > 0
                    ? tr('aviso.pides', { pedida: g.cantidad, disponibles: trn('comun.n_disponibles', g.stock) })
                    : tr('comun.agotado');
                if (g.stock <= 0) detalle.className = 'stock-agotado';
                li.append(nombre, detalle);
                lista.appendChild(li);
            });

            mostrarAviso({
                tipo: 'aviso',
                titulo: tr('aviso.insuficiente_titulo'),
                mensaje: tr('aviso.faltantes'),
                contenido: lista,
                botones: [
                    { texto: tr('aviso.revisar'), clase: 'btn-ghost' },
                    { texto: tr('aviso.ajustar'), clase: 'btn-primary', accion: ajustarADisponible }
                ]
            });
        }

        // Deja cada producto en lo que hay: se descuenta de sus últimas filas
        // (las más recientes) y se quitan las que quedan en 0 y los agotados.
        function ajustarADisponible() {
            const agotados = new Set(carrito.filter(i => i.stock <= 0).map(i => i.id_producto)).size;
            const restante = new Map();
            carrito.forEach(item => restante.set(item.id_producto, Math.max(0, item.stock)));
            carrito.forEach(item => {
                const queda = restante.get(item.id_producto);
                item.cantidad = Math.min(item.cantidad, queda);
                restante.set(item.id_producto, queda - item.cantidad);
            });
            carrito = carrito.filter(item => item.cantidad > 0);
            indiceSeleccionado = null;
            renderCarrito();
            mostrarToast(agotados > 0
                ? trn('aviso.ajustadas_quitados', agotados)
                : tr('aviso.ajustadas'), 'exito');
        }

        function crearBotonCantidad(texto, etiqueta, onClick) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'qty-btn';
            btn.textContent = texto;
            btn.title = etiqueta;
            btn.setAttribute('aria-label', etiqueta);
            btn.addEventListener('click', onClick);
            return btn;
        }

        function renderCarrito() {
            const tbody = document.getElementById('listaProductos');
            tbody.innerHTML = '';

            if (carrito.length === 0) {
                tbody.innerHTML = '<tr class="empty-row"><td colspan="6"></td></tr>';
                tbody.querySelector('td').textContent = tr('lista.vacia');
                indiceSeleccionado = null;
                indiceResaltado = null;
                calcularTotales();
                actualizarCatalogo();
                return;
            }

            carrito.forEach((item, index) => {
                const row = tbody.insertRow();
                row.dataset.index = index;
                if (index === indiceSeleccionado) row.classList.add('selected');

                const importe = item.cantidad * item.precio;

                row.insertCell().textContent = item.codigo;

                const celdaProducto = row.insertCell();
                const producto = document.createElement('div');
                producto.className = 'producto-celda';
                if (item.imagen) {
                    const img = document.createElement('img');
                    img.className = 'producto-thumb';
                    img.src = `../${item.imagen}`;
                    img.alt = '';
                    producto.appendChild(img);
                }
                const nombreProducto = document.createElement('div');
                nombreProducto.textContent = item.nombre;
                const etiqueta = etiquetaExistencias(item);
                if (etiqueta) {
                    const badge = document.createElement('span');
                    badge.className = `stock-badge ${etiqueta.clase}`;
                    badge.textContent = etiqueta.texto;
                    nombreProducto.appendChild(badge);
                    if (etiqueta.clase === 'stock-agotado') row.classList.add('fila-sin-stock');
                }
                producto.appendChild(nombreProducto);
                celdaProducto.appendChild(producto);

                // Selector de cantidad: [−] [n] [+]. Con 1 unidad, "−" ofrece quitar el producto.
                const stepper = document.createElement('div');
                stepper.className = 'qty-stepper';

                const btnMenos = crearBotonCantidad('−',
                    item.cantidad <= 1 ? tr('lista.quitar_producto') : tr('catalogo.quitar_uno'),
                    () => item.cantidad <= 1 ? pedirEliminarProducto(index) : cambiarCantidad(index, item.cantidad - 1));
                if (item.cantidad <= 1) btnMenos.classList.add('qty-quitar');

                const input = document.createElement('input');
                input.type = 'number';
                input.min = '1';
                input.step = '1';
                input.value = item.cantidad;
                input.className = 'input-cantidad';
                input.setAttribute('aria-label', tr('lista.cantidad_de', { p: item.nombre }));
                input.addEventListener('click', (e) => e.stopPropagation());
                input.addEventListener('change', (e) => cambiarCantidad(index, parseFloat(e.target.value)));
                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') e.target.blur();
                });

                // "+" sigue activo en el máximo: al pulsarlo avisa cuántos hay disponibles
                const btnMas = crearBotonCantidad('+', tr('lista.agregar_uno'), () => cambiarCantidad(index, item.cantidad + 1));
                if (cantidadDeProducto(item.id_producto) >= item.stock) {
                    btnMas.classList.add('qty-tope');
                    btnMas.title = tr('stock.solo_hay_existencia', { n: item.stock });
                }

                stepper.append(btnMenos, input, btnMas);
                row.insertCell().appendChild(stepper);

                const celdaPrecio = row.insertCell();
                celdaPrecio.className = 'col-num';
                celdaPrecio.textContent = formatoDinero(item.precio);

                const celdaImporte = row.insertCell();
                celdaImporte.className = 'col-num col-importe';
                celdaImporte.textContent = formatoDinero(importe);

                const btnBorrar = document.createElement('button');
                btnBorrar.type = 'button';
                btnBorrar.className = 'btn-borrar';
                btnBorrar.title = tr('lista.eliminar_producto');
                btnBorrar.setAttribute('aria-label', tr('lista.eliminar_de', { p: item.nombre }));
                btnBorrar.innerHTML = ICONO_BASURA;
                btnBorrar.addEventListener('click', () => pedirEliminarProducto(index));
                const celdaAcciones = row.insertCell();
                celdaAcciones.className = 'col-acciones';
                celdaAcciones.appendChild(btnBorrar);

                if (index === indiceResaltado) row.classList.add('bump');

                row.addEventListener('click', () => seleccionarFila(index));
            });

            calcularTotales();          // el resumen también resalta la línea nueva
            indiceResaltado = null;
            actualizarCatalogo();
        }

        function abrirLista() {
            abrirModal('modalLista');
            const seleccionada = document.querySelector('#listaProductos tr.selected') ||
                                 document.querySelector('#listaProductos tr:last-child');
            if (seleccionada) seleccionada.scrollIntoView({ block: 'nearest' });
        }

        function seleccionarFila(index) {
            indiceSeleccionado = index;
            document.querySelectorAll('#listaProductos tr').forEach(tr => {
                tr.classList.toggle('selected', Number(tr.dataset.index) === index);
            });
        }

        /* ---------- Eliminar (con confirmación) ---------- */
        function pedirEliminarProducto(index) {
            const item = carrito[index];
            if (!item) return;
            seleccionarFila(index);

            const tarjeta = document.createElement('div');
            tarjeta.className = 'eliminar-producto';
            if (item.imagen) {
                const img = document.createElement('img');
                img.src = `../${item.imagen}`;
                img.alt = '';
                tarjeta.appendChild(img);
            }
            const info = document.createElement('div');
            const nombre = document.createElement('strong');
            nombre.textContent = item.nombre;
            const detalle = document.createElement('small');
            detalle.textContent = `${item.cantidad} × ${formatoDinero(item.precio)} = ${formatoDinero(item.cantidad * item.precio)}`;
            info.append(nombre, detalle);
            tarjeta.appendChild(info);

            abrirConfirmacion(tr('eliminar.titulo'), tarjeta, tr('eliminar.confirmar'), () => quitarProducto(index));
        }

        function limpiarTabla() {
            if (carrito.length === 0) return;
            const articulos = carrito.reduce((n, i) => n + i.cantidad, 0);
            const mensaje = document.createElement('p');
            mensaje.className = 'modal-mensaje';
            mensaje.textContent = tr('vaciar.mensaje', { productos: trn('comun.n_productos', carrito.length), articulos: trn('comun.n_articulos', articulos) });

            abrirConfirmacion(tr('vaciar.titulo'), mensaje, tr('vaciar.confirmar'), () => {
                carrito = [];
                indiceSeleccionado = null;
                renderCarrito();
                inputBuscar.focus();
            });
        }

        function abrirConfirmacion(titulo, contenido, textoBoton, accion) {
            document.getElementById('tituloEliminar').textContent = titulo;
            document.getElementById('eliminarContenido').replaceChildren(contenido);
            document.getElementById('btnConfirmarEliminar').textContent = textoBoton;
            accionEliminar = accion;
            abrirModal('modalEliminar');
            // Foco en "Sí, eliminar": Enter confirma, Escape cancela
            document.getElementById('btnConfirmarEliminar').focus();
        }

        function confirmarEliminar() {
            cerrarModal('modalEliminar');
            const accion = accionEliminar;
            accionEliminar = null;
            if (accion) accion();
        }

        // Anima la salida de la fila y luego la quita del carrito
        function quitarProducto(index) {
            const fila = document.querySelector(`#listaProductos tr[data-index="${index}"]`);
            const quitar = () => {
                quitarFila(index);
                renderCarrito();
            };
            if (!fila) return quitar();
            fila.classList.add('removing');
            setTimeout(quitar, 200);
        }

        // Supr / ⌫ elimina la fila seleccionada de la lista (si no se está escribiendo en un campo)
        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Delete' && e.key !== 'Backspace') return;
            if (e.target.closest('input, textarea, select, [contenteditable="true"]')) return;
            if (modalSuperior()?.id !== 'modalLista') return;
            if (indiceSeleccionado === null || !carrito[indiceSeleccionado]) return;
            e.preventDefault();
            pedirEliminarProducto(indiceSeleccionado);
        });

        // Línea de las listas del panel derecho: [×n] nombre / precio c/u   importe
        function lineaResumen({ nombre, precio, cantidad }, nueva) {
            const li = document.createElement('li');
            li.className = 'resumen-item';
            if (nueva) li.classList.add('nuevo');

            const etiquetaCantidad = document.createElement('span');
            etiquetaCantidad.className = 'resumen-item-cantidad';
            etiquetaCantidad.textContent = `×${cantidad}`;

            const info = document.createElement('span');
            info.className = 'resumen-item-info';
            const etiquetaNombre = document.createElement('span');
            etiquetaNombre.className = 'resumen-item-nombre';
            etiquetaNombre.textContent = nombre;
            etiquetaNombre.title = nombre;
            const etiquetaPrecio = document.createElement('small');
            etiquetaPrecio.textContent = tr('resumen.precio_unidad', { precio: formatoDinero(precio) });
            info.append(etiquetaNombre, etiquetaPrecio);

            const importe = document.createElement('span');
            importe.className = 'resumen-item-importe';
            importe.textContent = formatoDinero(cantidad * precio);

            li.append(etiquetaCantidad, info, importe);
            return li;
        }

        function renderResumen() {
            const escaneo = document.getElementById('escaneoLista');
            const resumen = document.getElementById('resumenLista');
            document.getElementById('resumenAgrupado').hidden = carrito.length === 0;

            if (carrito.length === 0) {
                const vacio = document.createElement('li');
                vacio.className = 'resumen-vacio';
                vacio.textContent = tr('resumen.sin_productos');
                escaneo.replaceChildren(vacio);
                resumen.replaceChildren();
                return;
            }

            // Escaneo: las filas del carrito tal cual, en el orden en que se registraron
            const lineas = carrito.map((item, index) => lineaResumen(item, index === indiceResaltado));
            escaneo.replaceChildren(...lineas);

            // Resumen: agrupado por producto; se resalta el del último movimiento
            const idResaltado = carrito[indiceResaltado]?.id_producto;
            resumen.replaceChildren(...agruparCarrito().map(g => lineaResumen(g, g.id_producto === idResaltado)));

            // Mantiene a la vista lo último que se registró
            const nueva = lineas[indiceResaltado];
            if (nueva) nueva.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            const grupoNuevo = resumen.querySelector('.nuevo');
            if (grupoNuevo) grupoNuevo.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }

        // Los precios exhibidos ya son el total a pagar (impuestos incluidos).
        // Mismo cálculo en centavos que registrar_venta.php.
        function calcularTotales() {
            renderResumen();
            const totalCentavos = carrito.reduce((n, i) => n + aCentavos(i.precio) * i.cantidad, 0);
            const articulos = carrito.reduce((n, i) => n + i.cantidad, 0);
            totalActual = totalCentavos / 100;

            document.getElementById('totalArticulos').innerText = articulos;
            document.getElementById('lblTotal').innerText = formatoDinero(totalActual);
            document.getElementById('listaTotal').innerText = formatoDinero(totalActual);
            document.getElementById('contadorLista').innerText = articulos;
        }

        /* ---------- Avisos y notificaciones ---------- */
        const ICONOS_AVISO = {
            aviso: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>',
            peligro: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m4.9 4.9 14.2 14.2"/></svg>'
        };

        // botones: [{ texto, clase, accion }]. Todos cierran el aviso; luego corre la acción.
        function mostrarAviso({ tipo = 'aviso', titulo, mensaje = '', contenido = null, botones = null }) {
            const icono = document.getElementById('avisoIcono');
            icono.className = `icono-aviso tipo-${tipo}`;
            icono.innerHTML = ICONOS_AVISO[tipo] || ICONOS_AVISO.aviso;
            document.getElementById('tituloAviso').textContent = titulo;
            document.getElementById('avisoMensaje').textContent = mensaje;
            document.getElementById('avisoContenido').replaceChildren(...(contenido ? [contenido] : []));

            const acciones = document.getElementById('avisoAcciones');
            acciones.replaceChildren();
            (botones || [{ texto: tr('comun.entendido'), clase: 'btn-primary' }]).forEach(b => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = `btn ${b.clase}`;
                btn.textContent = b.texto;
                btn.addEventListener('click', () => {
                    cerrarModal('modalAviso');
                    if (b.accion) b.accion();
                    else inputBuscar.focus();
                });
                acciones.appendChild(btn);
            });

            abrirModal('modalAviso');
            // Enter acepta el botón principal (el último)
            acciones.lastElementChild.focus();
        }

        function mostrarToast(texto, tipo = 'aviso') {
            const toast = document.createElement('div');
            toast.className = `toast toast-${tipo}`;
            toast.textContent = texto;
            document.getElementById('toasts').appendChild(toast);
            setTimeout(() => {
                toast.classList.add('saliendo');
                setTimeout(() => toast.remove(), 250);
            }, 4000);
        }

        /* ---------- Pago ---------- */
        let verificandoPago = false;

        // Antes de cobrar se confirman las existencias reales: las del carrito
        // pueden haber cambiado desde que se agregó el producto.
        async function abrirModalPago() {
            if (carrito.length === 0 || totalActual <= 0) {
                alert(tr('pago.sin_productos'));
                return;
            }
            if (verificandoPago) return;

            const btnPagar = document.querySelector('.btn-pagar');
            verificandoPago = true;
            btnPagar.disabled = true;
            btnPagar.textContent = tr('pago.verificando');
            const faltantes = await actualizarExistencias();
            verificandoPago = false;
            btnPagar.disabled = false;
            btnPagar.textContent = tr('resumen.pagar');

            // Sin conexión (null) se sigue: registrar_venta.php vuelve a validar
            if (faltantes && faltantes.length > 0) {
                avisoFaltantes(faltantes);
                return;
            }

            document.getElementById('modalTotal').innerText = formatoDinero(totalActual);
            abrirModal('modalPago');
        }

        function abrirModalEfectivo() {
            cerrarModal('modalPago');
            document.getElementById('efectivoTotal').innerText = formatoDinero(totalActual);
            inputRecibido.value = '';
            actualizarCambioPreview();
            abrirModal('modalEfectivo');
            inputRecibido.focus();
        }

        function volverAMetodos() {
            cerrarModal('modalEfectivo');
            cerrarModal('modalVarios');
            abrirModal('modalPago');
        }

        /* ---------- Pago combinado ---------- */
        // Se captura lo de tarjeta y lo de efectivo, en cualquier orden, y se
        // cobra en cuanto se cubre el total:
        //  - tarjeta = total: todo con tarjeta (se ignora el efectivo)
        //  - sin tarjeta y efectivo >= total: todo en efectivo, con cambio
        //  - tarjeta parcial: el efectivo cubre el resto y el cambio sale de ahí
        //    (cuenta $400, tarjeta $200, recibe $500 -> cambio $300)
        // La tarjeta nunca pasa del total: no da cambio.
        const inputVariosTarjeta = document.getElementById('inputVariosTarjeta');
        const inputVariosEfectivo = document.getElementById('inputVariosEfectivo');

        // Monto capturado en centavos; vacío o negativo cuenta como 0
        const centavosDe = (input) => Math.max(0, aCentavos(parseFloat(input.value)) || 0);

        function abrirModalVarios() {
            cerrarModal('modalPago');
            document.getElementById('variosTotal').innerText = formatoDinero(totalActual);
            inputVariosTarjeta.value = '';
            inputVariosEfectivo.value = '';
            tarjetaManual = false;
            actualizarVariosPreview();
            abrirModal('modalVarios');
            inputVariosEfectivo.focus();
        }

        // Lo de tarjeta se calcula solo con lo que falta después del efectivo
        // (cuenta $257, efectivo $200 -> tarjeta $57) mientras el cajero no la
        // escriba a mano; si la borra, vuelve a calcularse.
        let tarjetaManual = false;

        function autollenarTarjeta() {
            if (tarjetaManual) return;
            const total = aCentavos(totalActual);
            const recibido = centavosDe(inputVariosEfectivo);
            const sinEfectivo = inputVariosEfectivo.value.trim() === '';
            inputVariosTarjeta.value = sinEfectivo || recibido >= total ? '' : ((total - recibido) / 100).toFixed(2);
        }

        // Cómo se cobra con lo capturado (montos en centavos), o null si no alcanza el total
        function montosVarios() {
            const total = aCentavos(totalActual);
            const tarjeta = centavosDe(inputVariosTarjeta);
            const recibido = centavosDe(inputVariosEfectivo);

            if (tarjeta > total) return null;
            if (tarjeta === total) return { metodo: 'Tarjeta' };
            if (tarjeta === 0) {
                return recibido >= total ? { metodo: 'Efectivo', recibido, cambio: recibido - total } : null;
            }
            const resto = total - tarjeta;
            return recibido >= resto ? { metodo: 'Varios', tarjeta, recibido, cambio: recibido - resto } : null;
        }

        // Desglose en vivo: tarjeta, efectivo y, al final, "Restante" mientras no
        // se cubra el total o "Cambio" cuando ya se cubrió
        function actualizarVariosPreview() {
            const total = aCentavos(totalActual);
            const tarjeta = centavosDe(inputVariosTarjeta);
            const recibido = centavosDe(inputVariosEfectivo);
            const montos = montosVarios();
            const excede = tarjeta > total;
            const todoTarjeta = montos?.metodo === 'Tarjeta';

            document.getElementById('btnCobrarVarios').disabled = montos === null;

            // La tarjeta no puede pasar del total: el error va en su propio campo
            inputVariosTarjeta.closest('.input-dinero').classList.toggle('con-error', excede);
            document.getElementById('errorVariosTarjeta').textContent =
                excede ? tr('varios.tarjeta_excede') : '';

            document.getElementById('variosResTarjeta').textContent = formatoDinero(tarjeta / 100);
            // Si la tarjeta cubre todo, el efectivo no se usa
            document.getElementById('variosResEfectivo').textContent = formatoDinero((todoTarjeta ? 0 : recibido) / 100);

            const estado = document.getElementById('variosEstado');
            const etiqueta = document.getElementById('variosEstadoEtiqueta');
            const monto = document.getElementById('variosEstadoMonto');
            estado.hidden = excede;
            estado.classList.toggle('is-restante', !montos && !excede);
            estado.classList.toggle('is-cubierto', !!montos);

            if (todoTarjeta) {
                etiqueta.textContent = tr('varios.todo_tarjeta');
                monto.textContent = '✓';
            } else if (montos) {
                etiqueta.textContent = tr('efectivo.cambio');
                monto.textContent = formatoDinero(montos.cambio / 100);
            } else {
                etiqueta.textContent = tr('varios.restante');
                monto.textContent = formatoDinero((total - tarjeta - recibido) / 100);
            }
        }

        inputVariosEfectivo.addEventListener('input', () => {
            autollenarTarjeta();
            actualizarVariosPreview();
        });
        inputVariosTarjeta.addEventListener('input', () => {
            tarjetaManual = inputVariosTarjeta.value.trim() !== '';
            if (!tarjetaManual) autollenarTarjeta();
            actualizarVariosPreview();
        });

        // Enter en el efectivo: si hay parte con tarjeta pasa a revisarla; si no, cobra.
        // Enter en la tarjeta: cobra si ya se cubrió el total.
        const puedeCobrarVarios = () => !document.getElementById('btnCobrarVarios').disabled;
        inputVariosEfectivo.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter') return;
            if (centavosDe(inputVariosTarjeta) > 0) {
                inputVariosTarjeta.focus();
                inputVariosTarjeta.select();
            } else if (puedeCobrarVarios()) {
                cobrarVarios();
            }
        });
        inputVariosTarjeta.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter') return;
            if (puedeCobrarVarios()) cobrarVarios();
            else inputVariosEfectivo.focus();
        });

        async function cobrarVarios() {
            if (cobrando) return;
            const montos = montosVarios();
            if (!montos) return;

            bloquearCobro(true);
            let venta;
            if (montos.metodo === 'Tarjeta') venta = await registrarVenta('Tarjeta');
            else if (montos.metodo === 'Efectivo') venta = await registrarVenta('Efectivo', montos.recibido / 100);
            else venta = await registrarVenta('Varios', montos.recibido / 100, montos.tarjeta / 100);
            bloquearCobro(false);
            if (!venta) return;

            cerrarModal('modalVarios');
            mostrarVentaCompletada(montos.metodo, venta);
        }

        // Muestra en vivo cuánto falta o cuánto cambio hay que dar
        function actualizarCambioPreview() {
            const preview = document.getElementById('cambioPreview');
            const btnCobrar = document.getElementById('btnCobrarEfectivo');
            const recibido = parseFloat(inputRecibido.value);

            preview.classList.remove('error');

            if (isNaN(recibido)) {
                preview.textContent = '';
                btnCobrar.disabled = true;
                return;
            }

            const diferencia = aCentavos(recibido) - aCentavos(totalActual);
            if (diferencia < 0) {
                preview.textContent = tr('efectivo.faltan', { monto: formatoDinero(-diferencia / 100) });
                preview.classList.add('error');
                btnCobrar.disabled = true;
            } else {
                preview.innerHTML = `${escaparHtml(tr('efectivo.cambio'))}: <strong>${formatoDinero(diferencia / 100)}</strong>`;
                btnCobrar.disabled = false;
            }
        }

        inputRecibido.addEventListener('input', actualizarCambioPreview);
        inputRecibido.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !document.getElementById('btnCobrarEfectivo').disabled) cobrarEfectivo();
        });

        // Guarda la venta en la BD. Devuelve la respuesta del servidor o null si falló.
        async function registrarVenta(metodo, efectivoRecibido = null, montoTarjeta = null) {
            const datos = new FormData();
            datos.append('csrf_token', csrfToken);
            datos.append('metodo', metodo);
            datos.append('items', JSON.stringify(
                carrito.map(i => ({ id_producto: i.id_producto, cantidad: i.cantidad }))
            ));
            if (efectivoRecibido !== null) datos.append('efectivo_recibido', efectivoRecibido.toFixed(2));
            if (montoTarjeta !== null) datos.append('monto_tarjeta', montoTarjeta.toFixed(2));

            let res, json;
            try {
                res = await fetch('registrar_venta.php', { method: 'POST', body: datos });
                json = await res.json();
            } catch (error) {
                mostrarAviso({
                    tipo: 'peligro',
                    titulo: tr('error.sin_conexion_titulo'),
                    mensaje: tr('error.sin_conexion')
                });
                return null;
            }
            if (json.success) return json;

            // Sin cambio en caja: la ventana de pago sigue abierta para cambiar el monto o el método
            if (json.sin_cambio) {
                mostrarAviso({ tipo: 'aviso', titulo: tr('aviso.sin_cambio_titulo'), mensaje: json.message });
                return null;
            }

            // 409: las existencias cambiaron entre la verificación y el cobro
            cerrarModal('modalPago');
            cerrarModal('modalEfectivo');
            cerrarModal('modalVarios');
            if (json.caja_cerrada) {
                cargarTurno();
                mostrarAviso({ tipo: 'aviso', titulo: tr('caja.cerrada_aviso'), mensaje: json.message });
                return null;
            }
            if (res.status === 409) {
                const faltantes = await actualizarExistencias();
                if (faltantes && faltantes.length > 0) {
                    avisoFaltantes(faltantes);
                    return null;
                }
            }
            mostrarAviso({
                tipo: 'peligro',
                titulo: tr('error.registrar_titulo'),
                mensaje: json.message || tr('error.registrar')
            });
            return null;
        }

        function bloquearCobro(bloquear) {
            cobrando = bloquear;
            document.querySelectorAll('#modalPago .metodo-btn, #btnCobrarEfectivo, #btnCobrarVarios').forEach(b => {
                b.disabled = bloquear;
            });
            ['btnCobrarEfectivo', 'btnCobrarVarios'].forEach(id => {
                document.getElementById(id).textContent = bloquear ? tr('pago.guardando') : tr('efectivo.cobrar');
            });
            if (!bloquear) {
                actualizarCambioPreview();
                actualizarVariosPreview();
            }
        }

        async function cobrarEfectivo() {
            if (cobrando) return;
            const recibido = parseFloat(inputRecibido.value);
            if (isNaN(recibido) || aCentavos(recibido) < aCentavos(totalActual)) return;

            bloquearCobro(true);
            const venta = await registrarVenta('Efectivo', recibido);
            bloquearCobro(false);
            if (!venta) return;

            cerrarModal('modalEfectivo');
            mostrarVentaCompletada('Efectivo', venta);
        }

        async function pagarConTarjeta() {
            if (cobrando) return;

            bloquearCobro(true);
            const venta = await registrarVenta('Tarjeta');
            bloquearCobro(false);
            if (!venta) return;

            cerrarModal('modalPago');
            mostrarVentaCompletada('Tarjeta', venta);
        }

        /* ---------- Corte de caja: conteo de billetes y monedas ---------- */
        // Tarjetas como las del catálogo: clic en la pieza suma uno, el círculo
        // rojo resta uno y la cantidad también se puede escribir. El conteo se
        // conserva aunque se cierre la ventana, hasta que se limpie.
        // Montos en centavos; la llave es el id porque $20 es billete y moneda.
        const DENOMINACIONES = [
            { id: 'b20',   tipo: 'billete', centavos: 2000,   imagen: 'billete20.jpg' },
            { id: 'b50',   tipo: 'billete', centavos: 5000,   imagen: 'billete50.jpg' },
            { id: 'b100',  tipo: 'billete', centavos: 10000,  imagen: 'billete100.jpg' },
            { id: 'b200',  tipo: 'billete', centavos: 20000,  imagen: 'billete200.jpg' },
            { id: 'b500',  tipo: 'billete', centavos: 50000,  imagen: 'billete500.jpg' },
            { id: 'b1000', tipo: 'billete', centavos: 100000, imagen: 'billete1000.jpg' },
            { id: 'm050',  tipo: 'moneda',  centavos: 50,     imagen: 'moneda050.jpg' },
            { id: 'm1',    tipo: 'moneda',  centavos: 100,    imagen: 'moneda1.jpg' },
            { id: 'm2',    tipo: 'moneda',  centavos: 200,    imagen: 'moneda2.jpg' },
            { id: 'm5',    tipo: 'moneda',  centavos: 500,    imagen: 'moneda5.jpg' },
            { id: 'm10',   tipo: 'moneda',  centavos: 1000,   imagen: 'moneda10.jpg' },
            { id: 'm20',   tipo: 'moneda',  centavos: 2000,   imagen: 'moneda20.jpg' },
        ];
        const conteoCorte = new Map(DENOMINACIONES.map(d => [d.id, 0]));

        // "$20", "$1,000", "$0.50"
        const denominacion = (centavos) => centavos % 100 === 0
            ? '$' + (centavos / 100).toLocaleString('es-MX')
            : formatoDinero(centavos / 100);

        // El mismo conteo sirve para abrir caja ('apertura') y para el corte ('cierre');
        // al cambiar de uso se empieza de cero
        let modoCorte = 'cierre';
        let modoConteo = null;      // para qué es el conteo guardado

        function mostrarCorte(modo) {
            if (modoConteo !== modo) limpiarConteo();
            modoCorte = modoConteo = modo;
            const cierre = modo === 'cierre';
            document.getElementById('tituloCorte').textContent = tr(cierre ? 'corte.titulo' : 'corte.titulo_apertura');
            document.getElementById('corteAyuda').textContent = tr(cierre ? 'corte.ayuda_cierre' : 'corte.ayuda');
            document.getElementById('corteTarjeta').hidden = !cierre;
            document.getElementById('btnCorteCancelar').hidden = !cierre;
            document.getElementById('btnCorteContinuar').hidden = !cierre;
            document.getElementById('btnCorteUsar').hidden = cierre;
            document.getElementById('errorVouchers').textContent = '';
            renderCorte();
            abrirModal('modalCorte');
            document.querySelector('#corteBilletes .dinero-piezas')?.focus();
        }

        // Botón "Corte" de la barra: cerrar caja. No con una venta a medias.
        function abrirCorte() {
            if (estadoCaja !== 'abierta') return;
            if (carrito.length > 0) {
                mostrarAviso({ tipo: 'aviso', titulo: tr('cierre.venta_en_curso_titulo'), mensaje: tr('cierre.venta_en_curso') });
                return;
            }
            mostrarCorte('cierre');
        }

        function limpiarConteo() {
            conteoCorte.forEach((_, id) => conteoCorte.set(id, 0));
            document.getElementById('inputVouchers').value = '';
        }

        // Fondo regular: $1,000 en billetes chicos y monedas para dar cambio.
        // Es lo que se deja normalmente al cerrar la caja.
        const PLANTILLA_FONDO = { b100: 2, b50: 6, b20: 10, m10: 20, m5: 10, m2: 15, m1: 15, m050: 10 };
        const totalPlantillaFondo = () => DENOMINACIONES.reduce((total, d) => total + (PLANTILLA_FONDO[d.id] ?? 0) * d.centavos, 0);
        document.getElementById('cortePlantillaTotal').textContent = denominacion(totalPlantillaFondo());

        function cargarPlantillaFondo(pintar = true) {
            conteoCorte.forEach((_, id) => conteoCorte.set(id, PLANTILLA_FONDO[id] ?? 0));
            if (pintar) renderCorte();
        }

        // Si ya hay piezas contadas, pregunta antes de reemplazarlas
        function pedirPlantillaFondo() {
            if (sumarCorte().piezas === 0) {
                cargarPlantillaFondo();
                return;
            }
            const mensaje = document.createElement('p');
            mensaje.className = 'modal-mensaje';
            mensaje.textContent = tr('corte.plantilla_mensaje');
            abrirConfirmacion(tr('corte.plantilla_titulo'), mensaje, tr('corte.plantilla_confirmar'), () => cargarPlantillaFondo());
        }

        function renderCorte() {
            const tarjetas = (tipo) => DENOMINACIONES.filter(d => d.tipo === tipo).map(crearTarjetaDinero);
            document.getElementById('corteBilletes').replaceChildren(...tarjetas('billete'));
            document.getElementById('corteMonedas').replaceChildren(...tarjetas('moneda'));
            actualizarTotalCorte();
        }

        function crearTarjetaDinero(d) {
            const valor = denominacion(d.centavos);
            const tarjeta = document.createElement('div');
            tarjeta.className = `dinero-card dinero-card--${d.tipo}`;

            const agregar = document.createElement('button');
            agregar.type = 'button';
            agregar.className = 'dinero-agregar';
            agregar.setAttribute('aria-label', tr(`corte.agregar_${d.tipo}`, { valor }));
            const img = document.createElement('img');
            img.src = `../Imagenes/dinero/${d.imagen}`;
            img.alt = '';
            const etiqueta = document.createElement('span');
            etiqueta.className = 'dinero-valor';
            etiqueta.textContent = valor;
            agregar.append(img, etiqueta);

            const restar = document.createElement('button');
            restar.type = 'button';
            restar.className = 'dinero-restar';
            restar.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true"><path d="M6 12h12"/></svg>';
            restar.title = tr('catalogo.quitar_uno');
            restar.setAttribute('aria-label', tr(`corte.quitar_${d.tipo}`, { valor }));

            const pie = document.createElement('div');
            pie.className = 'dinero-pie';
            const piezas = document.createElement('input');
            piezas.type = 'number';
            piezas.min = '0';
            piezas.step = '1';
            piezas.inputMode = 'numeric';
            piezas.placeholder = '0';
            piezas.className = 'dinero-piezas';
            piezas.setAttribute('aria-label', tr(`corte.piezas_${d.tipo}`, { valor }));
            const subtotal = document.createElement('span');
            subtotal.className = 'dinero-subtotal';
            pie.append(piezas, subtotal);

            // Mientras se escribe no se reescribe el campo; al salir se normaliza ("007" -> 7)
            const pintar = () => {
                const n = conteoCorte.get(d.id);
                tarjeta.classList.toggle('con-piezas', n > 0);
                if (document.activeElement !== piezas) piezas.value = n > 0 ? n : '';
                subtotal.textContent = formatoDinero(n * d.centavos / 100);
                actualizarTotalCorte();
            };
            const fijar = (n) => {
                conteoCorte.set(d.id, Math.max(0, n));
                pintar();
                animarTarjeta(tarjeta);
            };

            agregar.addEventListener('click', () => fijar(conteoCorte.get(d.id) + 1));
            restar.addEventListener('click', () => fijar(conteoCorte.get(d.id) - 1));
            piezas.addEventListener('input', () => {
                const n = parseInt(piezas.value, 10);
                conteoCorte.set(d.id, Number.isNaN(n) || n < 0 ? 0 : n);
                pintar();
            });
            piezas.addEventListener('blur', pintar);
            piezas.addEventListener('keydown', (e) => {
                // Solo piezas enteras y positivas
                if (['-', '+', 'e', 'E', '.', ','].includes(e.key)) e.preventDefault();
                // Enter pasa a la siguiente pieza (de los billetes sigue a las monedas)
                if (e.key === 'Enter') {
                    const campos = [...document.querySelectorAll('#modalCorte .dinero-piezas')];
                    campos[campos.indexOf(piezas) + 1]?.focus();
                }
            });

            tarjeta.append(agregar, restar, pie);
            pintar();
            return tarjeta;
        }

        // Total y piezas por tipo, en centavos
        function sumarCorte(tipo = null) {
            let centavos = 0;
            let piezas = 0;
            DENOMINACIONES.forEach(d => {
                if (tipo && d.tipo !== tipo) return;
                const n = conteoCorte.get(d.id);
                centavos += n * d.centavos;
                piezas += n;
            });
            return { centavos, piezas };
        }

        function actualizarTotalCorte() {
            const billetes = sumarCorte('billete');
            const monedas = sumarCorte('moneda');
            document.getElementById('corteTotalBilletes').textContent = formatoDinero(billetes.centavos / 100);
            document.getElementById('corteTotalMonedas').textContent = formatoDinero(monedas.centavos / 100);
            document.getElementById('cortePiezasBilletes').textContent = billetes.piezas > 0 ? `(${trn('corte.n_billetes', billetes.piezas)})` : '';
            document.getElementById('cortePiezasMonedas').textContent = monedas.piezas > 0 ? `(${trn('corte.n_monedas', monedas.piezas)})` : '';
            document.getElementById('corteTotal').textContent = formatoDinero((billetes.centavos + monedas.centavos) / 100);
        }

        function pedirLimpiarCorte() {
            if (sumarCorte().piezas === 0) return;
            const mensaje = document.createElement('p');
            mensaje.className = 'modal-mensaje';
            mensaje.textContent = tr('corte.limpiar_mensaje');
            abrirConfirmacion(tr('corte.limpiar_titulo'), mensaje, tr('corte.limpiar_confirmar'), () => {
                limpiarConteo();
                renderCorte();
            });
        }

        /* ---------- Turno de caja: abrir y cerrar ---------- */
        // Sin turno abierto todo se ve en gris y solo queda "Abrir caja". Al
        // abrir se confirma lo que dejó el turno anterior; al cerrar se cuenta a
        // ciegas (lo esperado llega hasta guardar), se decide cuánto se deja y el
        // corte queda pendiente para el gerente.
        let cajaActual = null;
        let turnoActual = null;
        let turnoAnterior = null;
        let estadoCaja = 'cargando';    // cargando | abierta | cerrada | sin_caja | error
        let mensajeErrorCaja = '';
        let ultimoCorte = null;
        let desgloseApertura = null;    // piezas, si la apertura se contó con billetes y monedas
        let guardandoTurno = false;

        const centavosDeTexto = (texto) => {
            const valor = String(texto ?? '').trim();
            return /^\d{1,9}(\.\d{1,2})?$/.test(valor) ? Math.round(parseFloat(valor) * 100) : null;
        };

        // "2026-10-19 21:40:05" de la BD -> "19/10/2026 21:40" (o mm/dd en inglés)
        function fechaHoraCorta(valor) {
            const [f, h = ''] = String(valor ?? '').split(' ');
            const [a, m, d] = f.split('-');
            if (!d) return valor ?? '';
            return (window.IDIOMA === 'en' ? `${m}/${d}/${a}` : `${d}/${m}/${a}`) + (h ? ' ' + h.slice(0, 5) : '');
        }

        async function cargarTurno() {
            pintarEstadoCaja('cargando');
            try {
                const res = await fetch('turno_estado.php');
                const datos = await res.json();
                if (!datos.success) throw new Error(datos.message || '');
                cajaActual = datos.caja;
                turnoActual = datos.turno;
                turnoAnterior = datos.anterior;
                pintarEstadoCaja(!cajaActual ? 'sin_caja' : (turnoActual ? 'abierta' : 'cerrada'));
            } catch (error) {
                mensajeErrorCaja = error.message || tr('buscar.sin_conexion');
                pintarEstadoCaja('error');
            }
        }

        function pintarEstadoCaja(estado = estadoCaja) {
            estadoCaja = estado;
            const abierta = estado === 'abierta';
            document.body.classList.toggle('caja-bloqueada', !abierta);
            document.querySelector('main.pos-layout').inert = !abierta;
            document.getElementById('cajaCerrada').hidden = abierta;
            document.querySelector('.corte-boton').disabled = !abierta;

            const titulo = document.getElementById('cajaCerradaTitulo');
            const texto = document.getElementById('cajaCerradaTexto');
            document.getElementById('btnAbrirCaja').hidden = estado !== 'cerrada';
            document.getElementById('btnReintentarCaja').hidden = estado !== 'error';

            if (estado === 'cargando') {
                titulo.textContent = tr('caja.revisando');
                texto.textContent = '';
            } else if (estado === 'sin_caja') {
                titulo.textContent = tr('caja.sin_caja_titulo');
                texto.textContent = tr('caja.sin_caja_texto');
            } else if (estado === 'error') {
                titulo.textContent = tr('caja.error_titulo');
                texto.textContent = mensajeErrorCaja;
            } else if (estado === 'cerrada') {
                titulo.textContent = tr('caja.cerrada_titulo', { n: cajaActual.numero });
                texto.textContent = tr('caja.cerrada_texto');
                document.getElementById('btnAbrirCaja').focus();
            } else if (abierta && !modalSuperior()) {
                inputBuscar.focus();
            }
        }

        /* --- Abrir caja --- */
        const inputApertura = document.getElementById('inputApertura');

        function abrirModalApertura() {
            if (!cajaActual) return;
            const dejado = turnoAnterior?.efectivo_dejado;
            document.getElementById('tituloApertura').textContent = tr('apertura.titulo', { n: cajaActual.numero });
            document.getElementById('aperturaAnterior').textContent = dejado != null
                ? tr('apertura.anterior', {
                    cajero: turnoAnterior.cajero || tr('apertura.turno_anterior'),
                    monto: formatoDinero(dejado),
                    fecha: fechaHoraCorta(turnoAnterior.fecha_cierre)
                })
                : tr('apertura.primer_turno', { monto: formatoDinero(cajaActual.fondo_fijo) });
            inputApertura.value = Number(dejado ?? cajaActual.fondo_fijo).toFixed(2);
            desgloseApertura = null;
            actualizarAvisoApertura();
            abrirModal('modalApertura');
            inputApertura.focus();
            inputApertura.select();
        }

        // Si no coincide con lo que se dejó, se avisa (y queda registrado para el gerente)
        function actualizarAvisoApertura() {
            const aviso = document.getElementById('aperturaAviso');
            const centavos = centavosDeTexto(inputApertura.value);
            const dejado = turnoAnterior?.efectivo_dejado;
            document.getElementById('btnConfirmarApertura').disabled = centavos === null || guardandoTurno;
            aviso.textContent = '';
            if (centavos === null || dejado == null) return;
            const diferencia = centavos - aCentavos(dejado);
            if (diferencia !== 0) {
                aviso.textContent = tr(diferencia < 0 ? 'apertura.faltan' : 'apertura.sobran', { monto: formatoDinero(Math.abs(diferencia) / 100) })
                    + ' ' + tr('apertura.registrado');
            }
        }

        inputApertura.addEventListener('input', () => {
            desgloseApertura = null;    // lo escrito a mano reemplaza el conteo
            actualizarAvisoApertura();
        });
        inputApertura.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !document.getElementById('btnConfirmarApertura').disabled) confirmarApertura();
        });

        // Contar con billetes y monedas: el conteo se abre encima de la apertura
        // Contar con billetes y monedas. Si el turno anterior dejó el fondo regular
        // (lo normal), el conteo ya viene con esas piezas: solo se confirma.
        function contarApertura() {
            if (modoConteo !== 'apertura') {
                limpiarConteo();
                modoConteo = 'apertura';
                const dejado = turnoAnterior?.efectivo_dejado ?? cajaActual.fondo_fijo;
                if (aCentavos(dejado) === totalPlantillaFondo()) cargarPlantillaFondo(false);
            }
            mostrarCorte('apertura');
        }

        function usarConteoApertura() {
            desgloseApertura = Object.fromEntries(conteoCorte);
            inputApertura.value = (sumarCorte().centavos / 100).toFixed(2);
            cerrarModal('modalCorte');
            actualizarAvisoApertura();
            inputApertura.focus();
        }

        async function confirmarApertura() {
            const centavos = centavosDeTexto(inputApertura.value);
            if (centavos === null || guardandoTurno) return;

            const datos = new FormData();
            datos.append('csrf_token', csrfToken);
            datos.append('efectivo', (centavos / 100).toFixed(2));
            // El desglose solo vale si sigue sumando lo que dice el campo
            if (desgloseApertura && sumarCorte().centavos === centavos) {
                datos.append('desglose', JSON.stringify(desgloseApertura));
            }

            const boton = document.getElementById('btnConfirmarApertura');
            guardandoTurno = true;
            boton.disabled = true;
            boton.textContent = tr('apertura.abriendo');
            let respuesta = null;
            try {
                const res = await fetch('turno_abrir.php', { method: 'POST', body: datos });
                respuesta = await res.json();
            } catch (error) {
                respuesta = { success: false, message: tr('buscar.sin_conexion') };
            }
            guardandoTurno = false;
            boton.textContent = tr('caja.abrir');
            actualizarAvisoApertura();

            if (!respuesta.success) {
                mostrarAviso({ tipo: 'peligro', titulo: tr('apertura.error_titulo'), mensaje: respuesta.message || tr('error.registrar') });
                return;
            }
            turnoActual = respuesta.turno;
            cerrarModal('modalApertura');
            limpiarConteo();
            modoConteo = null;
            pintarEstadoCaja('abierta');
            mostrarToast(tr('apertura.lista'), 'exito');
        }

        /* --- Cerrar caja --- */
        const inputVouchers = document.getElementById('inputVouchers');
        const inputDejado = document.getElementById('inputDejado');

        inputVouchers.addEventListener('input', () => {
            document.getElementById('errorVouchers').textContent = '';
        });
        inputVouchers.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') continuarCierre();
        });

        // Paso 2: con el conteo listo, cuánto se deja (sugerido: el fondo base de la caja)
        function continuarCierre() {
            const vouchers = inputVouchers.value.trim() === '' ? 0 : centavosDeTexto(inputVouchers.value);
            if (vouchers === null) {
                document.getElementById('errorVouchers').textContent = tr('corte.vouchers_invalidos');
                inputVouchers.focus();
                return;
            }
            const contado = sumarCorte().centavos;
            document.getElementById('cierreContado').textContent = formatoDinero(contado / 100);
            document.getElementById('cierreVouchers').textContent = formatoDinero(vouchers / 100);
            document.getElementById('cierreFondo').textContent = tr('cierre.fondo_base', { monto: formatoDinero(cajaActual.fondo_fijo) });
            inputDejado.value = (Math.min(aCentavos(cajaActual.fondo_fijo), contado) / 100).toFixed(2);
            actualizarEntrega();
            abrirModal('modalCierre');
            inputDejado.focus();
            inputDejado.select();
        }

        function actualizarEntrega() {
            const contado = sumarCorte().centavos;
            const dejado = centavosDeTexto(inputDejado.value);
            const error = document.getElementById('errorDejado');
            const excede = dejado !== null && dejado > contado;
            inputDejado.closest('.input-dinero').classList.toggle('con-error', excede);
            error.textContent = excede ? tr('cierre.dejado_excede') : '';
            document.getElementById('cierreEntrega').textContent =
                formatoDinero((dejado === null || excede ? 0 : contado - dejado) / 100);
            document.getElementById('btnConfirmarCierre').disabled = dejado === null || excede || guardandoTurno;
        }

        inputDejado.addEventListener('input', actualizarEntrega);
        inputDejado.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !document.getElementById('btnConfirmarCierre').disabled) confirmarCierre();
        });

        async function confirmarCierre() {
            const dejado = centavosDeTexto(inputDejado.value);
            const vouchers = inputVouchers.value.trim() === '' ? 0 : centavosDeTexto(inputVouchers.value);
            if (dejado === null || vouchers === null || guardandoTurno) return;

            const datos = new FormData();
            datos.append('csrf_token', csrfToken);
            datos.append('desglose', JSON.stringify(Object.fromEntries(conteoCorte)));
            datos.append('vouchers', (vouchers / 100).toFixed(2));
            datos.append('dejado', (dejado / 100).toFixed(2));

            const boton = document.getElementById('btnConfirmarCierre');
            guardandoTurno = true;
            boton.disabled = true;
            boton.textContent = tr('cierre.cerrando');
            let respuesta = null;
            try {
                const res = await fetch('turno_cerrar.php', { method: 'POST', body: datos });
                respuesta = await res.json();
            } catch (error) {
                respuesta = { success: false, message: tr('buscar.sin_conexion') };
            }
            guardandoTurno = false;
            boton.textContent = tr('cierre.titulo');
            actualizarEntrega();

            if (!respuesta.success) {
                if (respuesta.caja_cerrada) {
                    cerrarModal('modalCierre');
                    cerrarModal('modalCorte');
                    cargarTurno();
                }
                mostrarAviso({ tipo: 'peligro', titulo: tr('cierre.error_titulo'), mensaje: respuesta.message || tr('error.registrar') });
                return;
            }

            const corte = respuesta.corte;
            ultimoCorte = corte;
            turnoActual = null;
            turnoAnterior = { efectivo_dejado: corte.dejado, fecha_cierre: corte.fecha_cierre, cajero: corte.cajero };
            cerrarModal('modalCierre');
            cerrarModal('modalCorte');
            limpiarConteo();
            modoConteo = null;
            pintarEstadoCaja('cerrada');
            mostrarResultadoCorte(corte);
        }

        // Lo esperado se ve hasta aquí, ya guardado el corte
        function mostrarResultadoCorte(c) {
            const sello = document.getElementById('corteSello');
            const clase = { Cuadrada: 'is-cuadrada', Faltante: 'is-faltante', Sobrante: 'is-sobrante' }[c.resultado];
            sello.className = `corte-sello ${clase}`;
            sello.textContent = c.resultado === 'Cuadrada'
                ? tr('resultado.cuadrada')
                : tr(c.resultado === 'Faltante' ? 'resultado.faltante' : 'resultado.sobrante', { monto: formatoDinero(Math.abs(c.diferencia)) });

            const fila = (etiqueta, valor, clase = '') => {
                const div = document.createElement('div');
                div.className = `varios-fila ${clase}`;
                const a = document.createElement('span');
                a.textContent = etiqueta;
                const b = document.createElement('span');
                b.textContent = valor;
                div.append(a, b);
                return div;
            };
            const conSigno = (n) => (n > 0 ? '+' : n < 0 ? '−' : '') + formatoDinero(Math.abs(n));
            document.getElementById('corteDetalle').replaceChildren(
                fila(tr('resultado.ventas'), c.num_ventas),
                fila(tr('resultado.efectivo_esperado'), formatoDinero(c.efectivo_esperado)),
                fila(tr('cierre.contado'), formatoDinero(c.efectivo_contado)),
                fila(tr('resultado.tarjeta_esperada'), formatoDinero(c.teorico_tarjeta)),
                fila(tr('cierre.vouchers'), formatoDinero(c.vouchers)),
                fila(tr('resultado.diferencia'), conSigno(c.diferencia), 'varios-estado ' + (c.diferencia === 0 ? 'is-cubierto' : 'is-restante')),
                fila(tr('cierre.dejas'), formatoDinero(c.dejado)),
                fila(tr('cierre.entregas'), formatoDinero(c.retirado))
            );
            document.getElementById('corteRevision').hidden = !c.requiere_revision;
            abrirModal('modalCorteResultado');
            document.getElementById('btnAceptarCorte').focus();
            imprimirCorte();
        }

        function imprimirCorte() {
            if (ultimoCorte) imprimirHtml(htmlTicketCorte(ultimoCorte), 'btnAceptarCorte');
        }

        function aceptarCorte() {
            cerrarModal('modalCorteResultado');
            pintarEstadoCaja();
        }

        /* ---------- Ticket ---------- */
        // Se imprime desde un iframe oculto con su propia hoja de estilos (80 mm),
        // así la impresión no depende del diseño de la caja.
        let ultimoTicket = null;

        const escaparHtml = (texto) => String(texto ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));

        // El método viaja al servidor en español ('Efectivo'/'Tarjeta'); en pantalla se traduce
        const nombreMetodo = (metodo) => tr('pago.' + String(metodo).toLowerCase());

        // Hoja de 80 mm para la impresora térmica (venta y corte)
        const ESTILO_TICKET = `
    @page { size: 80mm auto; margin: 0; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        width: 80mm; padding: 5mm 4mm 8mm;
        font-family: 'Courier New', Courier, monospace;
        font-size: 12px; line-height: 1.35; color: #000;
    }
    .centro { text-align: center; }
    .marca { font-size: 20px; font-weight: 700; letter-spacing: 0.2em; }
    .sucursal { font-weight: 700; margin-top: 2px; }
    .chico { font-size: 11px; }
    hr { border: none; border-top: 1px dashed #000; margin: 6px 0; }
    .fila { display: flex; justify-content: space-between; gap: 8px; }
    .fila span:last-child { text-align: right; white-space: nowrap; }
    .producto { margin-bottom: 4px; }
    .producto-nombre { font-weight: 700; word-break: break-word; }
    .producto .fila { padding-left: 8px; }
    .total { font-size: 16px; font-weight: 700; margin: 2px 0; }
    .destacado { font-weight: 700; }
    .gracias { margin-top: 8px; font-weight: 700; }
    .titulo-ticket { font-weight: 700; letter-spacing: 0.1em; margin-top: 4px; }
    .firma { margin-top: 26px; border-top: 1px solid #000; padding-top: 2px; text-align: center; font-size: 11px; }
`;

        function htmlTicket(t) {
            const fila = (izq, der, clase = '') =>
                `<div class="fila ${clase}"><span>${izq}</span><span>${der}</span></div>`;
            const articulos = t.lineas.reduce((n, l) => n + Number(l.cantidad), 0);

            const lineas = t.lineas.map(l => `
                <div class="producto">
                    <div class="producto-nombre">${escaparHtml(l.nombre)}</div>
                    ${fila(`${l.cantidad} x ${formatoDinero(Number(l.precio))}`, formatoDinero(Number(l.importe)))}
                </div>`).join('');

            // Pago combinado: lo de la tarjeta y luego el efectivo con su cambio
            const pago = (t.tarjeta != null ? fila(tr('ticket.pago_tarjeta'), formatoDinero(t.tarjeta)) : '') +
                (t.recibido != null
                    ? fila(tr('ticket.efectivo_recibido'), formatoDinero(t.recibido)) +
                      fila(tr('ticket.cambio'), formatoDinero(t.cambio), 'destacado')
                    : '');

            return `<!DOCTYPE html>
<html lang="${window.IDIOMA}"><head><meta charset="UTF-8"><title>Ticket #${escaparHtml(t.folio)}</title>
<style>${ESTILO_TICKET}</style></head>
<body>
    <div class="centro">
        <div class="marca">KOALY</div>
        ${t.sucursal ? `<div class="sucursal">${escaparHtml(tr('ticket.sucursal', { s: t.sucursal }))}</div>` : ''}
        ${t.direccion ? `<div class="chico">${escaparHtml(t.direccion)}</div>` : ''}
        ${t.telefono ? `<div class="chico">${escaparHtml(tr('ticket.tel', { t: t.telefono }))}</div>` : ''}
    </div>
    <hr>
    ${fila(tr('ticket.folio'), `#${escaparHtml(t.folio)}`, 'destacado')}
    ${fila(tr('ticket.fecha'), escaparHtml(t.fecha))}
    ${fila(tr('ticket.hora'), escaparHtml(t.hora))}
    ${t.caja ? fila(tr('ticket.caja'), escaparHtml(t.caja)) : ''}
    ${t.cajero ? fila(tr('ticket.atendio'), escaparHtml(t.cajero)) : ''}
    <hr>
    ${lineas}
    <hr>
    ${fila(tr('ticket.articulos'), articulos)}
    ${fila(tr('ticket.subtotal'), formatoDinero(t.subtotal))}
    ${fila(tr('ticket.iva'), formatoDinero(t.iva))}
    ${fila(tr('ticket.total'), formatoDinero(t.total), 'total')}
    <hr>
    ${fila(tr('ticket.forma_pago'), escaparHtml(nombreMetodo(t.metodo)))}
    ${pago}
    <hr>
    <div class="centro chico">${escaparHtml(tr('ticket.iva_incluido'))}</div>
    <div class="centro gracias">${escaparHtml(tr('ticket.gracias'))}</div>
</body></html>`;
        }

        function imprimirTicket(ticket) {
            if (ticket) imprimirHtml(htmlTicket(ticket), 'btnFinalizarVenta');
        }

        // Ticket del corte: conteo, lo esperado, el resultado, lo que se deja y lo que se entrega
        function htmlTicketCorte(c) {
            const fila = (izq, der, clase = '') =>
                `<div class="fila ${clase}"><span>${escaparHtml(izq)}</span><span>${escaparHtml(der)}</span></div>`;
            const piezas = DENOMINACIONES
                .filter(d => (c.desglose?.[d.id] ?? 0) > 0)
                .map(d => fila(`${c.desglose[d.id]} x ${denominacion(d.centavos)}`, formatoDinero(c.desglose[d.id] * d.centavos / 100)))
                .join('');
            const resultado = c.resultado === 'Cuadrada'
                ? tr('resultado.cuadrada')
                : tr(c.resultado === 'Faltante' ? 'resultado.faltante' : 'resultado.sobrante', { monto: formatoDinero(Math.abs(c.diferencia)) });

            return `<!DOCTYPE html>
<html lang="${window.IDIOMA}"><head><meta charset="UTF-8"><title>${escaparHtml(tr('ticket_corte.titulo'))} #${escaparHtml(c.folio)}</title>
<style>${ESTILO_TICKET}</style></head>
<body>
    <div class="centro">
        <div class="marca">KOALY</div>
        <div class="titulo-ticket">${escaparHtml(tr('ticket_corte.titulo'))}</div>
    </div>
    <hr>
    ${fila(tr('ticket.folio'), '#' + c.folio, 'destacado')}
    ${fila(tr('ticket.caja'), c.caja)}
    ${fila(tr('caja.cajero'), c.cajero)}
    ${fila(tr('ticket_corte.apertura'), fechaHoraCorta(c.fecha_apertura))}
    ${fila(tr('ticket_corte.cierre'), fechaHoraCorta(c.fecha_cierre))}
    ${fila(tr('resultado.ventas'), c.num_ventas)}
    <hr>
    ${piezas}
    ${fila(tr('cierre.contado'), formatoDinero(c.efectivo_contado), 'destacado')}
    ${fila(tr('cierre.vouchers'), formatoDinero(c.vouchers), 'destacado')}
    <hr>
    ${fila(tr('ticket_corte.fondo_inicial'), formatoDinero(c.efectivo_apertura))}
    ${fila(tr('ticket_corte.ventas_efectivo'), formatoDinero(c.teorico_efectivo))}
    ${fila(tr('resultado.efectivo_esperado'), formatoDinero(c.efectivo_esperado))}
    ${fila(tr('ticket_corte.ventas_tarjeta'), formatoDinero(c.teorico_tarjeta))}
    <hr>
    <div class="centro total">${escaparHtml(resultado)}</div>
    <hr>
    ${fila(tr('cierre.dejas'), formatoDinero(c.dejado))}
    ${fila(tr('cierre.entregas'), formatoDinero(c.retirado), 'total')}
    <div class="firma">${escaparHtml(tr('ticket_corte.firma_cajero'))}</div>
    <div class="firma">${escaparHtml(tr('ticket_corte.firma_gerente'))}</div>
</body></html>`;
        }

        // Imprime un HTML desde un iframe oculto y regresa el foco al botón indicado
        function imprimirHtml(html, idEnfoque) {
            let marco = document.getElementById('marcoTicket');
            if (!marco) {
                marco = document.createElement('iframe');
                marco.id = 'marcoTicket';
                marco.className = 'marco-ticket';
                marco.setAttribute('aria-hidden', 'true');
                marco.tabIndex = -1;
                document.body.appendChild(marco);
            }
            marco.onload = () => {
                marco.contentWindow.focus();
                marco.contentWindow.print();
                document.getElementById(idEnfoque)?.focus();
            };
            marco.srcdoc = html;
        }

        function mostrarVentaCompletada(metodo, venta) {
            document.getElementById('ventaMensaje').textContent =
                tr('venta.mensaje', { folio: venta.id_venta, total: formatoDinero(venta.total), metodo: nombreMetodo(metodo) });

            const bloqueCambio = document.getElementById('ventaCambio');
            if (venta.cambio !== null) {
                document.getElementById('ventaCambioMonto').textContent = formatoDinero(venta.cambio);
                document.getElementById('ventaCambioDetalle').textContent = venta.tarjeta != null
                    ? tr('venta.detalle_varios', { tarjeta: formatoDinero(venta.tarjeta), recibido: formatoDinero(venta.recibido), total: formatoDinero(venta.total) })
                    : tr('venta.detalle', { recibido: formatoDinero(venta.recibido), total: formatoDinero(venta.total) });
                bloqueCambio.style.display = 'block';
            } else {
                bloqueCambio.style.display = 'none';
            }

            ultimoTicket = venta.ticket || null;
            document.getElementById('btnReimprimir').style.display = ultimoTicket ? '' : 'none';

            abrirModal('modalVenta');
            document.getElementById('btnFinalizarVenta').focus();
            imprimirTicket(ultimoTicket);
        }

        function finalizarVenta() {
            cerrarModal('modalVenta');
            carrito = [];
            indiceSeleccionado = null;
            renderCarrito();
            cargarCatalogo();   // existencias nuevas después de la venta
            inputBuscar.focus();
        }

        renderCarrito();
        cargarCatalogo();
        cargarTurno();
    </script>
</body>
</html>
