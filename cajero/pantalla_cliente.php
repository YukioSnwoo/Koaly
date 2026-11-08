<?php
/**
 * Pantalla del cliente: segunda ventana de la caja, para el monitor que ve el comprador.
 * Solo muestra: todo lo recibe de cajero/index.php por BroadcastChannel (pantalla_cliente.js),
 * sin consultar el servidor. Se abre con el botón "Pantalla cliente" de la caja.
 */
session_start();

if (!isset($_SESSION['id_usuario']) || (int) $_SESSION['id_rol'] !== 3) {
    header('Location: ../index.php');
    exit;
}

require_once __DIR__ . '/includes/i18n.php';

// Solo los textos de esta pantalla, en todos los idiomas: cambia junto con la caja, sin recargar
$claves = array_flip([
    'cliente.titulo',
    'cliente.bienvenida',
    'cliente.caja_cerrada',
    'cliente.preparar',
    'cliente.pantalla_completa',
    'cliente.listo',
    'resumen.escaneo',
    'resumen.articulos',
    'resumen.precio_unidad',
    'comun.total',
    'efectivo.recibido',
    'efectivo.cambio',
    'varios.tarjeta',
    'varios.restante',
    'ticket.gracias',
]);

$textosPorIdioma = [];
foreach (array_keys(IDIOMAS) as $codigo) {
    $todos = $codigo === 'es'
        ? $GLOBALS['CAJA_TEXTOS_ES']
        : (require __DIR__ . "/lang/{$codigo}.php") + $GLOBALS['CAJA_TEXTOS_ES'];
    $textosPorIdioma[$codigo] = array_intersect_key($todos, $claves);
}
?>
<!DOCTYPE html>
<html lang="<?= idiomaActual() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(t('cliente.titulo')) ?> - Koaly</title>
    <script>
    // Mismo tema que la caja, antes de pintar para evitar un destello
    try { var tema = localStorage.getItem('koaly-tema') || localStorage.getItem('koalicius-cajero-tema'); if (tema === 'claro' || tema === 'oscuro') document.documentElement.setAttribute('data-tema', tema); } catch (e) {}
    </script>
    <link rel="stylesheet" href="pantalla_cliente.css?v=<?= filemtime(__DIR__ . '/pantalla_cliente.css') ?>">
    <link rel="stylesheet" href="../estilos/koaly.css?v=<?= filemtime(__DIR__ . '/../estilos/koaly.css') ?>">
</head>
<body>

    <!-- Espera, caja cerrada y "gracias": fondo morado con el koala -->
    <main class="portada" id="vistaKoala">
        <div class="mascota">
            <div class="mascota-sello">
                <img class="mascota-imagen" id="koala" src="../Imagenes/koala/koala-pensando.png" alt="">
            </div>
            <div class="mascota-burbuja" id="burbuja"></div>
        </div>
        <div class="gracias-detalle" id="graciasDetalle" hidden>
            <div class="gracias-dato">
                <span data-i18n="comun.total"></span>
                <strong id="graciasTotal"></strong>
            </div>
            <div class="gracias-dato" id="graciasBloqueCambio" hidden>
                <span data-i18n="efectivo.cambio"></span>
                <strong id="graciasCambio"></strong>
            </div>
        </div>
    </main>

    <!-- Venta en curso: lo escaneado, en orden, y la cuenta -->
    <main class="venta" id="vistaVenta" hidden>
        <section class="venta-escaneo">
            <h1 data-i18n="resumen.escaneo"></h1>
            <ul class="lista" id="listaEscaneo"></ul>
        </section>
        <aside class="venta-cuenta">
            <div class="cuenta-fila">
                <span data-i18n="resumen.articulos"></span>
                <strong id="totalArticulos"></strong>
            </div>
            <div class="cuenta-total">
                <span data-i18n="comun.total"></span>
                <strong id="totalVenta"></strong>
            </div>
            <div class="cuenta-cobro" id="cobro" hidden></div>
        </aside>
    </main>

    <!-- Solo al abrir: los navegadores piden un clic para entrar a pantalla completa -->
    <div class="preparar" id="preparar">
        <p data-i18n="cliente.preparar"></p>
        <div class="preparar-botones">
            <button type="button" id="btnListo" data-i18n="cliente.listo"></button>
            <button type="button" class="preparar-principal" id="btnPantallaCompleta" data-i18n="cliente.pantalla_completa"></button>
        </div>
    </div>

    <script>
    window.TEXTOS_CLIENTE = <?= json_encode($textosPorIdioma, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    window.IDIOMA_CLIENTE = <?= json_encode(idiomaActual()) ?>;
    </script>
    <script src="pantalla_cliente.js?v=<?= filemtime(__DIR__ . '/pantalla_cliente.js') ?>"></script>
</body>
</html>
