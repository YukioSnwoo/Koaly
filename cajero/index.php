<?php
session_start();

if (!isset($_SESSION['id_usuario']) || (int) $_SESSION['id_rol'] !== 3) {
    header('Location: ../index.php');
    exit;
}

require_once __DIR__ . '/../admin/csrf.php';
require_once __DIR__ . '/includes/i18n.php';

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
    try { var tema = localStorage.getItem('koalicius-cajero-tema'); if (tema === 'claro' || tema === 'oscuro') document.documentElement.setAttribute('data-tema', tema); } catch (e) {}
    </script>
    <meta name="csrf-token" content="<?= htmlspecialchars(csrfToken()) ?>">
    <link rel="stylesheet" href="cajero.css?v=<?= $verCajeroCss ?>">
</head>
<body>

    <!-- Encabezado -->
    <header class="header">
        <h1>Koaly <span class="header-sub" data-i18n="caja.titulo"><?= e(t('caja.titulo')) ?></span></h1>
        <div class="user-info">
            <details class="idioma">
                <summary aria-label="<?= e(t('idioma.cambiar')) ?>" data-i18n-aria-label="idioma.cambiar" title="<?= e(t('idioma.cambiar')) ?>" data-i18n-title="idioma.cambiar">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                    <span><?= strtoupper(idiomaActual()) ?></span>
                    <svg class="idioma-flecha" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                </summary>
                <form method="POST" action="idioma.php" class="idioma-menu">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <?php foreach (IDIOMAS as $codigo => $nombre): ?>
                        <button type="submit" name="idioma" value="<?= $codigo ?>" lang="<?= $codigo ?>"
                                <?= $codigo === idiomaActual() ? 'aria-current="true" class="is-activo"' : '' ?>>
                            <span class="idioma-codigo"><?= strtoupper($codigo) ?></span> <?= e($nombre) ?>
                        </button>
                    <?php endforeach; ?>
                </form>
            </details>
            <button type="button" class="tema-boton" id="botonTema" aria-label="<?= e(t('tema.a_oscuro')) ?>" title="<?= e(t('tema.a_oscuro')) ?>">
                <svg class="tema-luna" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                <svg class="tema-sol" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
            </button>
            <div class="user-datos">
                <span class="user-nombre" id="userName"><?= e(t('caja.cajero')) ?></span>
                <span class="user-sucursal"><span data-i18n="caja.sucursal"><?= e(t('caja.sucursal')) ?></span>: <span id="sucursalName">-</span></span>
            </div>
            <button type="button" class="btn-salir" onclick="abrirModal('modalSalir')" data-i18n="sesion.cerrar"><?= e(t('sesion.cerrar')) ?></button>
        </div>
    </header>

    <!-- Panel Principal -->
    <main class="pos-layout">

        <!-- Lateral Izquierdo -->
        <aside class="panel sidebar">
            <div class="sidebar-group">
                <div class="search-box">
                    <div class="input-buscar">
                        <svg class="icono-lupa" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                        <input type="text" id="inputBuscar" placeholder="<?= e(t('buscar.placeholder')) ?>" data-i18n-placeholder="buscar.placeholder" autocomplete="off"
                               role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="resultadosBusqueda">
                        <span class="spinner" aria-hidden="true"></span>
                        <div id="resultadosBusqueda" class="search-results" role="listbox" style="display:none;"></div>
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
    <script>
        const usuario = requireRol(3);
        function pintarUsuario() {
            if (!usuario) return;
            document.getElementById('userName').textContent = usuario.nombre || tr('caja.cajero');
            document.getElementById('sucursalName').textContent = usuario.sucursal_nombre || usuario.id_sucursal || tr('caja.sin_asignar');
        }
        pintarUsuario();

        let carrito = [];
        let indiceSeleccionado = null;
        let accionEliminar = null;
        let totalActual = 0;
        let cobrando = false;
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        const inputBuscar = document.getElementById('inputBuscar');
        const resultadosBusqueda = document.getElementById('resultadosBusqueda');
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

        // Clic fuera de la caja o Escape cierra el modal, excepto el de venta
        // completada: la venta solo se cierra con "Aceptar".
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay && overlay.id !== 'modalVenta') cerrarModal(overlay.id);
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
            if (abierto && abierto.id !== 'modalVenta') cerrarModal(abierto.id);
        });

        /* ---------- Tema claro / oscuro ---------- */
        // Igual que en el panel de gerente: <html data-tema="claro|oscuro"> si el
        // cajero eligió; si no, sigue al sistema. La elección se guarda por navegador.
        const CLAVE_TEMA = 'koalicius-cajero-tema';
        const botonTema = document.getElementById('botonTema');

        function temaActual() {
            const elegido = document.documentElement.getAttribute('data-tema');
            if (elegido) return elegido;
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'oscuro' : 'claro';
        }

        function actualizarBotonTema() {
            const etiqueta = temaActual() === 'oscuro' ? tr('tema.a_claro') : tr('tema.a_oscuro');
            botonTema.setAttribute('aria-label', etiqueta);
            botonTema.title = etiqueta;
        }

        botonTema.addEventListener('click', () => {
            const nuevo = temaActual() === 'oscuro' ? 'claro' : 'oscuro';
            document.documentElement.setAttribute('data-tema', nuevo);
            try { localStorage.setItem(CLAVE_TEMA, nuevo); } catch (err) { /* modo privado: solo esta página */ }
            actualizarBotonTema();
        });
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener?.('change', actualizarBotonTema);
        actualizarBotonTema();

        /* ---------- Selector de idioma ---------- */
        // El menú se cierra al hacer clic fuera o con Escape (igual que en el gerente)
        document.addEventListener('click', (e) => {
            document.querySelectorAll('details.idioma[open]').forEach(d => {
                if (!d.contains(e.target)) d.removeAttribute('open');
            });
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') document.querySelectorAll('details.idioma[open]').forEach(d => d.removeAttribute('open'));
        });

        // Igual que el tema, el idioma cambia aquí mismo sin recargar: si se está
        // cobrando, la venta (carrito, búsqueda, categoría elegida) sigue intacta.
        // En segundo plano se guarda en la sesión, para que los mensajes del
        // servidor al cobrar y la fecha del ticket salgan en el mismo idioma.
        const menuIdioma = document.querySelector('.idioma-menu');

        menuIdioma.addEventListener('submit', (e) => {
            e.preventDefault();
            const codigo = e.submitter?.value;
            menuIdioma.closest('details').removeAttribute('open');
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
            pintarUsuario();
            actualizarBotonTema();

            // Botón y menú de idioma
            document.querySelector('.idioma > summary > span').textContent = codigo.toUpperCase();
            menuIdioma.querySelectorAll('button[name="idioma"]').forEach(boton => {
                const activo = boton.value === codigo;
                boton.classList.toggle('is-activo', activo);
                if (activo) boton.setAttribute('aria-current', 'true');
                else boton.removeAttribute('aria-current');
            });

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
        // Sugerencias en vivo mientras se escribe; Enter (o un lector de código de
        // barras) agrega el producto resaltado o la coincidencia exacta de clave.
        const MIN_CARACTERES = 2;
        const ESPERA_MS = 250;
        let temporizadorBusqueda = null;
        let numeroBusqueda = 0;        // descarta respuestas que llegan fuera de orden
        let resultadosActuales = [];
        let consultaMostrada = null;   // consulta a la que pertenecen los resultados visibles
        let indiceActivo = -1;

        async function consultarProductos(q) {
            const res = await fetch(`buscar_producto.php?q=${encodeURIComponent(q)}`);
            const productos = await res.json();
            return Array.isArray(productos) ? productos : [];
        }

        inputBuscar.addEventListener('input', () => {
            clearTimeout(temporizadorBusqueda);
            const q = inputBuscar.value.trim();
            if (q.length < MIN_CARACTERES) {
                numeroBusqueda++;
                ocultarResultados();
                return;
            }
            temporizadorBusqueda = setTimeout(() => sugerir(q), ESPERA_MS);
        });

        inputBuscar.addEventListener('focus', () => {
            const q = inputBuscar.value.trim();
            if (q.length >= MIN_CARACTERES && q === consultaMostrada) mostrarResultados();
        });

        inputBuscar.addEventListener('keydown', (e) => {
            const abiertos = resultadosBusqueda.style.display !== 'none' && resultadosActuales.length > 0;
            if (e.key === 'ArrowDown' && abiertos) {
                e.preventDefault();
                activarResultado((indiceActivo + 1) % resultadosActuales.length);
            } else if (e.key === 'ArrowUp' && abiertos) {
                e.preventDefault();
                activarResultado((indiceActivo - 1 + resultadosActuales.length) % resultadosActuales.length);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                buscarProducto();
            } else if (e.key === 'Escape') {
                ocultarResultados();
            }
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.search-box')) ocultarResultados();
        });

        async function sugerir(q) {
            const numero = ++numeroBusqueda;
            buscando(true);
            let productos;
            try {
                productos = await consultarProductos(q);
            } catch (error) {
                if (numero !== numeroBusqueda) return;
                buscando(false);
                pintarMensaje(tr('buscar.sin_conexion'));
                return;
            }
            if (numero !== numeroBusqueda) return;
            buscando(false);
            pintarResultados(q, productos);
        }

        // Enter en el buscador
        async function buscarProducto() {
            clearTimeout(temporizadorBusqueda);
            const q = inputBuscar.value.trim();
            if (!q) {
                inputBuscar.focus();
                return;
            }

            // Si la lista visible corresponde a lo escrito, se agrega el resaltado
            if (q === consultaMostrada && resultadosActuales[indiceActivo]) {
                seleccionarResultado(resultadosActuales[indiceActivo]);
                return;
            }

            const numero = ++numeroBusqueda;
            buscando(true);
            let productos;
            try {
                productos = await consultarProductos(q);
            } catch (error) {
                buscando(false);
                alert(tr('buscar.sin_conexion_alerta'));
                return;
            }
            if (numero !== numeroBusqueda) return;
            buscando(false);

            const exacto = productos.find(p => String(p.codigo).toLowerCase() === q.toLowerCase());
            if (productos.length === 1 || exacto) {
                seleccionarResultado(exacto || productos[0]);
                return;
            }
            pintarResultados(q, productos);
        }

        function pintarResultados(q, productos) {
            resultadosActuales = productos;
            consultaMostrada = q;

            if (productos.length === 0) {
                pintarMensaje(tr('buscar.sin_resultados', { q }));
                return;
            }

            resultadosBusqueda.innerHTML = '';
            productos.forEach((p, i) => {
                const item = document.createElement('div');
                item.className = 'result-item';
                item.id = `resultado-${i}`;
                item.setAttribute('role', 'option');

                const thumb = document.createElement('img');
                thumb.className = 'result-thumb';
                thumb.alt = '';
                if (p.imagen) thumb.src = `../${p.imagen}`;
                else thumb.style.visibility = 'hidden';

                const info = document.createElement('div');
                info.className = 'result-info';
                const nombre = document.createElement('div');
                nombre.className = 'result-nombre';
                nombre.appendChild(resaltarCoincidencia(p.nombre, q));
                const detalle = document.createElement('small');
                detalle.append(tr('buscar.clave') + ': ', resaltarCoincidencia(String(p.codigo), q), ' · ');
                const stock = document.createElement('span');
                if (Number(p.stock) > 0) {
                    stock.textContent = tr('buscar.existencias', { n: p.stock });
                } else {
                    stock.className = 'agotado';
                    stock.textContent = tr('comun.agotado');
                }
                detalle.appendChild(stock);
                info.append(nombre, detalle);

                const precio = document.createElement('div');
                precio.className = 'result-precio';
                precio.textContent = formatoDinero(Number(p.precio) || 0);

                item.append(thumb, info, precio);
                item.addEventListener('mousedown', (e) => e.preventDefault()); // no quitar el foco del input
                item.addEventListener('mouseenter', () => activarResultado(i, false));
                item.addEventListener('click', () => seleccionarResultado(p));
                resultadosBusqueda.appendChild(item);
            });

            // Resalta la clave exacta si la hay; si no, el primero
            const exacto = productos.findIndex(p => String(p.codigo).toLowerCase() === q.toLowerCase());
            activarResultado(exacto >= 0 ? exacto : 0);
            mostrarResultados();
        }

        function pintarMensaje(texto) {
            resultadosActuales = [];
            indiceActivo = -1;
            const div = document.createElement('div');
            div.className = 'no-results';
            div.textContent = texto;
            resultadosBusqueda.replaceChildren(div);
            mostrarResultados();
        }

        // Devuelve el texto con la parte que coincide envuelta en <mark>
        function resaltarCoincidencia(texto, q) {
            const fragmento = document.createDocumentFragment();
            const pos = texto.toLowerCase().indexOf(q.toLowerCase());
            if (pos === -1) {
                fragmento.append(texto);
                return fragmento;
            }
            const mark = document.createElement('mark');
            mark.textContent = texto.slice(pos, pos + q.length);
            fragmento.append(texto.slice(0, pos), mark, texto.slice(pos + q.length));
            return fragmento;
        }

        function activarResultado(i, desplazar = true) {
            indiceActivo = i;
            resultadosBusqueda.querySelectorAll('.result-item').forEach((el, j) => {
                el.classList.toggle('active', j === i);
                el.setAttribute('aria-selected', j === i ? 'true' : 'false');
            });
            const activo = document.getElementById(`resultado-${i}`);
            if (activo) {
                inputBuscar.setAttribute('aria-activedescendant', activo.id);
                if (desplazar) activo.scrollIntoView({ block: 'nearest' });
            }
        }

        function seleccionarResultado(producto) {
            agregarAlCarrito(producto);
            cerrarResultados();
        }

        function buscando(activo) {
            document.querySelector('.search-box').classList.toggle('cargando', activo);
        }

        function mostrarResultados() {
            resultadosBusqueda.style.display = 'block';
            inputBuscar.setAttribute('aria-expanded', 'true');
        }

        function ocultarResultados() {
            resultadosBusqueda.style.display = 'none';
            inputBuscar.setAttribute('aria-expanded', 'false');
        }

        function cerrarResultados() {
            numeroBusqueda++;
            buscando(false);
            ocultarResultados();
            resultadosBusqueda.innerHTML = '';
            resultadosActuales = [];
            consultaMostrada = null;
            indiceActivo = -1;
            inputBuscar.removeAttribute('aria-activedescendant');
            inputBuscar.value = '';
            // Si se abrió un aviso (p. ej. producto agotado), el foco se queda en él
            if (!document.querySelector('.modal-overlay.open')) inputBuscar.focus();
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
        let categoriaActiva = 'todas';        // 'todas' o el id de una categoría
        const SIN_CATEGORIA = 0;              // productos sin categoría asignada
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
            });
            if (catalogo.some(p => p.id_categoria === SIN_CATEGORIA)) {
                categorias.push({ id: SIN_CATEGORIA, nombreBd: null });
            }
            if (categoriaActiva !== 'todas' && !categorias.some(c => c.id === categoriaActiva)) {
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

        // Una sección por categoría (las vacías también, con su aviso);
        // el filtro solo muestra u oculta secciones
        function renderCatalogo() {
            const contenedor = document.getElementById('catalogo');
            tarjetasCatalogo.clear();

            if (catalogo.length === 0 && categorias.length === 0) {
                document.getElementById('filtroCategorias').replaceChildren();
                contenedor.replaceChildren(mensajeCatalogo(tr('catalogo.vacio')));
                return;
            }

            const secciones = categorias.map(c => {
                const productos = catalogo.filter(p => p.id_categoria === c.id);

                const seccion = document.createElement('section');
                seccion.className = 'catalogo-seccion';
                seccion.dataset.categoria = c.id;
                seccion.dataset.vacia = productos.length === 0 ? '1' : '';

                const titulo = document.createElement('h3');
                titulo.className = 'catalogo-seccion-titulo';
                titulo.textContent = nombreCategoria(c);
                const conteo = document.createElement('span');
                conteo.className = 'catalogo-seccion-conteo';
                conteo.textContent = productos.length;
                titulo.appendChild(conteo);

                const grid = document.createElement('div');
                grid.className = 'catalogo-grid';
                if (productos.length === 0) {
                    grid.appendChild(mensajeCatalogo(tr('categorias.vacia')));
                } else {
                    grid.append(...productos.map(crearTarjeta));
                }

                seccion.append(titulo, grid);
                return seccion;
            });

            contenedor.replaceChildren(...secciones);
            renderFiltroCategorias();
            aplicarFiltroCategoria();
            actualizarCatalogo();
        }

        function renderFiltroCategorias() {
            const filtro = document.getElementById('filtroCategorias');
            const chip = (valor, nombre, cantidad) => {
                const boton = document.createElement('button');
                boton.type = 'button';
                boton.className = 'categoria-chip';
                boton.dataset.valor = valor;
                boton.classList.toggle('vacia', cantidad === 0);
                boton.textContent = nombre;
                const conteo = document.createElement('span');
                conteo.className = 'categoria-chip-conteo';
                conteo.textContent = cantidad;
                boton.appendChild(conteo);
                boton.addEventListener('click', () => {
                    categoriaActiva = valor;
                    aplicarFiltroCategoria();
                    document.getElementById('catalogo').scrollTop = 0;
                });
                return boton;
            };

            filtro.replaceChildren(
                chip('todas', tr('categorias.todas'), catalogo.length),
                ...categorias.map(c => chip(c.id, nombreCategoria(c), catalogo.filter(p => p.id_categoria === c.id).length))
            );
        }

        // "Todas" muestra solo las secciones con productos, para no llenar de avisos;
        // al elegir una categoría vacía se ve su sección con el aviso
        function aplicarFiltroCategoria() {
            document.querySelectorAll('#filtroCategorias .categoria-chip').forEach(boton => {
                const activo = String(boton.dataset.valor) === String(categoriaActiva);
                boton.classList.toggle('activo', activo);
                boton.setAttribute('aria-pressed', activo ? 'true' : 'false');
            });
            document.querySelectorAll('#catalogo .catalogo-seccion').forEach(seccion => {
                seccion.hidden = categoriaActiva === 'todas'
                    ? seccion.dataset.vacia === '1'
                    : Number(seccion.dataset.categoria) !== categoriaActiva;
            });
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

            // 409: las existencias cambiaron entre la verificación y el cobro
            cerrarModal('modalPago');
            cerrarModal('modalEfectivo');
            cerrarModal('modalVarios');
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

        /* ---------- Ticket ---------- */
        // Se imprime desde un iframe oculto con su propia hoja de estilos (80 mm),
        // así la impresión no depende del diseño de la caja.
        let ultimoTicket = null;

        const escaparHtml = (texto) => String(texto ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));

        // El método viaja al servidor en español ('Efectivo'/'Tarjeta'); en pantalla se traduce
        const nombreMetodo = (metodo) => tr('pago.' + String(metodo).toLowerCase());

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
<style>
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
</style></head>
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
            if (!ticket) return;
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
                document.getElementById('btnFinalizarVenta').focus();
            };
            marco.srcdoc = htmlTicket(ticket);
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
    </script>
</body>
</html>
