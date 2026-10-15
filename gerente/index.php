<?php
require_once __DIR__ . '/guardia.php';
require_once __DIR__ . '/includes/productos.php';

/* ------------------------------------------------------------
   Resumen del día para el inicio
   ------------------------------------------------------------ */
$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN fecha_hora >= CURDATE() THEN total END), 0)        AS monto_hoy,
        COUNT(CASE WHEN fecha_hora >= CURDATE() THEN 1 END)                         AS num_hoy,
        COALESCE(SUM(CASE WHEN fecha_hora <  CURDATE() THEN total END), 0)        AS monto_ayer
    FROM Ventas
    WHERE id_sucursal = ? AND fecha_hora >= CURDATE() - INTERVAL 1 DAY
");
$stmt->execute([$idSucursalGerente]);
$ventasHoy = $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT COUNT(*) AS en_alerta,
           COUNT(CASE WHEN cantidad_disponible = 0 THEN 1 END) AS agotados
    FROM Inventario_Sucursal
    WHERE id_sucursal = ? AND cantidad_disponible < ?
");
$stmt->execute([$idSucursalGerente, STOCK_UMBRAL_BAJO]);
$stockAlerta = $stmt->fetch();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM Solicitudes_Productos WHERE id_sucursal = ? AND estado = 'Pendiente'");
$stmt->execute([$idSucursalGerente]);
$solicitudesPendientes = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*) AS total, COUNT(CASE WHEN estado = 'Activa' THEN 1 END) AS activas
    FROM Cajas WHERE id_sucursal = ?
");
$stmt->execute([$idSucursalGerente]);
$cajasResumen = $stmt->fetch();

$stmt = $pdo->prepare("SELECT direccion, telefono, email_contacto FROM Sucursales WHERE id_sucursal = ?");
$stmt->execute([$idSucursalGerente]);
$sucursal = $stmt->fetch() ?: [];

$pageTitle = t('inicio.titulo');
require __DIR__ . '/header.php';
?>

<section class="hero" aria-labelledby="hero-title">
    <p class="hero-eyebrow"><?= e(t('inicio.eyebrow')) ?></p>
    <h2 id="hero-title"><?= e($gerente['nombre']) ?></h2>
    <div class="hero-meta">
        <span class="hero-chip">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 9l1.5-6h15L21 9"/>
                <path d="M3 9v11a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V9"/>
                <path d="M3 9h18"/>
                <path d="M9 21V13h6v8"/>
            </svg>
            <?= e($gerente['sucursal_nombre']) ?>
        </span>
        <?php foreach (['direccion', 'telefono', 'email_contacto'] as $campo): ?>
            <?php if (!empty($sucursal[$campo])): ?>
                <span class="hero-chip hero-chip--muted"><?= e($sucursal[$campo]) ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</section>

<section class="kpi-grid" aria-label="<?= e(t('inicio.resumen_aria')) ?>">
    <a href="ventas_sucursal.php?periodo=hoy" class="kpi kpi--ok kpi--link">
        <span class="kpi-label"><?= e(t('inicio.kpi_ventas')) ?></span>
        <span class="kpi-value kpi-value--ok"><?= dinero($ventasHoy['monto_hoy']) ?></span>
        <span class="kpi-sub">
            <?= e(tn('ventas.n_ventas', (int) $ventasHoy['num_hoy'])) ?>
            · <?= e(t('inicio.kpi_ventas_ayer', ['monto' => dinero($ventasHoy['monto_ayer'])])) ?>
        </span>
    </a>
    <a href="existencias.php?stock=alerta" class="kpi <?= (int) $stockAlerta['en_alerta'] > 0 ? 'kpi--danger' : '' ?> kpi--link">
        <span class="kpi-label"><?= e(t('inicio.kpi_alerta')) ?></span>
        <span class="kpi-value <?= (int) $stockAlerta['en_alerta'] > 0 ? 'kpi-value--danger' : '' ?>"><?= (int) $stockAlerta['en_alerta'] ?></span>
        <span class="kpi-sub">
            <?= e(tn('inicio.kpi_alerta_agotados', (int) $stockAlerta['agotados'])) ?>
            · <?= e(t('exist.kpi_bajo_sub', ['n' => STOCK_UMBRAL_BAJO])) ?>
        </span>
    </a>
    <a href="productos.php" class="kpi <?= $solicitudesPendientes > 0 ? 'kpi--warn' : '' ?> kpi--link">
        <span class="kpi-label"><?= e(t('inicio.kpi_solicitudes')) ?></span>
        <span class="kpi-value <?= $solicitudesPendientes > 0 ? 'kpi-value--warn' : '' ?>"><?= $solicitudesPendientes ?></span>
        <span class="kpi-sub"><?= e(t('inicio.kpi_solicitudes_sub')) ?></span>
    </a>
    <a href="cajas.php" class="kpi kpi--link">
        <span class="kpi-label"><?= e(t('inicio.kpi_cajas')) ?></span>
        <span class="kpi-value"><?= (int) $cajasResumen['activas'] ?> / <?= (int) $cajasResumen['total'] ?></span>
        <span class="kpi-sub"><?= e(t('inicio.kpi_cajas_sub')) ?></span>
    </a>
</section>

<section aria-labelledby="operacion-title" style="margin-bottom: 2rem;">
    <h2 id="operacion-title" class="section-title"><?= e(t('inicio.seccion_tienda')) ?></h2>
    <div class="cards">
        <a href="productos.php" class="card">
            <div class="card-head">
                <span class="card-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                        <line x1="12" y1="22.08" x2="12" y2="12"/>
                    </svg>
                </span>
                <h3><?= e(t('cat.titulo')) ?></h3>
            </div>
            <p><?= e(t('inicio.card_catalogo')) ?></p>
        </a>

        <a href="existencias.php" class="card">
            <div class="card-head">
                <span class="card-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="12 2 2 7 12 12 22 7 12 2"/>
                        <polyline points="2 17 12 22 22 17"/>
                        <polyline points="2 12 12 17 22 12"/>
                    </svg>
                </span>
                <h3><?= e(t('exist.titulo')) ?></h3>
            </div>
            <p><?= e(t('inicio.card_existencias')) ?></p>
        </a>
    </div>
</section>

<section aria-labelledby="personal-title" style="margin-bottom: 2rem;">
    <h2 id="personal-title" class="section-title"><?= e(t('inicio.seccion_equipo')) ?></h2>
    <div class="cards">
        <a href="cajeros.php" class="card">
            <div class="card-head">
                <span class="card-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </span>
                <h3><?= e(t('cajeros.titulo')) ?></h3>
            </div>
            <p><?= e(t('inicio.card_cajeros')) ?></p>
        </a>

        <a href="cajas.php" class="card">
            <div class="card-head">
                <span class="card-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                        <line x1="8" y1="21" x2="16" y2="21"/>
                        <line x1="12" y1="17" x2="12" y2="21"/>
                    </svg>
                </span>
                <h3><?= e(t('cajas.titulo')) ?></h3>
            </div>
            <p><?= e(t('inicio.card_cajas')) ?></p>
        </a>
    </div>
</section>

<section aria-labelledby="ventas-title">
    <h2 id="ventas-title" class="section-title"><?= e(t('inicio.seccion_ventas')) ?></h2>
    <div class="cards">
        <a href="ventas_sucursal.php" class="card">
            <div class="card-head">
                <span class="card-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="1" x2="12" y2="23"/>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                </span>
                <h3><?= e(t('ventas.titulo')) ?></h3>
            </div>
            <p><?= e(t('inicio.card_ventas')) ?></p>
        </a>
    </div>
</section>

<?php require __DIR__ . '/footer.php'; ?>