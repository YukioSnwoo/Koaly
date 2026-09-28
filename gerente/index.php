<?php
require_once __DIR__ . '/guardia.php';
$verGerenteCss = filemtime(__DIR__ . '/gerente.css');
$verGerenteJs  = filemtime(__DIR__ . '/gerente.js');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Gerente - Koaly</title>
    <link rel="stylesheet" href="gerente.css?v=<?= $verGerenteCss ?>">
</head>
<body>
    <header class="header">
        <h1>Koaly - Panel Gerente</h1>
        <div class="user-info">
            <span><?= htmlspecialchars($gerente['nombre']) ?></span>
            <button type="button" onclick="if (confirm('¿Seguro que deseas cerrar sesión?')) logout();">Cerrar sesión</button>
        </div>
    </header>

    <main class="container">
        <section class="hero" aria-labelledby="hero-title">
            <p class="hero-eyebrow">Bienvenido de vuelta</p>
            <h2 id="hero-title"><?= htmlspecialchars($gerente['nombre']) ?></h2>
            <div class="hero-meta">
                <span class="hero-chip">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3 9l1.5-6h15L21 9"/>
                        <path d="M3 9v11a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V9"/>
                        <path d="M3 9h18"/>
                        <path d="M9 21V13h6v8"/>
                    </svg>
                    <?= htmlspecialchars($gerente['sucursal_nombre'] ?? 'Sin sucursal') ?>
                </span>
            </div>
        </section>

        <section aria-labelledby="operacion-title" style="margin-bottom: 2rem;">
            <h2 id="operacion-title" class="section-title">Operación de tienda</h2>
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
                        <h3>Productos</h3>
                    </div>
                    <p>Catálogo global: registrar, consultar, modificar y desactivar productos.</p>
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
                        <h3>Existencias</h3>
                    </div>
                    <p>Consultar y ajustar el stock de los productos en tu sucursal.</p>
                </a>

                <a href="bajo_inventario.php" class="card">
                    <div class="card-head">
                        <span class="card-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                <line x1="12" y1="9" x2="12" y2="13"/>
                                <line x1="12" y1="17" x2="12.01" y2="17"/>
                            </svg>
                        </span>
                        <h3>Bajo inventario</h3>
                    </div>
                    <p>Productos con existencias por debajo del umbral en tu sucursal.</p>
                </a>

            </div>
        </section>

        <section aria-labelledby="personal-title" style="margin-bottom: 2rem;">
            <h2 id="personal-title" class="section-title">Personal y sucursal</h2>
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
                        <h3>Cajeros</h3>
                    </div>
                    <p>Administrar el personal de caja de tu sucursal.</p>
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
                        <h3>Cajas</h3>
                    </div>
                    <p>Registrar, editar y eliminar las cajas de tu sucursal.</p>
                </a>

                <a href="sucursal.php" class="card">
                    <div class="card-head">
                        <span class="card-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 9l1.5-6h15L21 9"/>
                                <path d="M3 9v11a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V9"/>
                                <path d="M3 9h18"/>
                                <path d="M9 21V13h6v8"/>
                            </svg>
                        </span>
                        <h3>Mi sucursal</h3>
                    </div>
                    <p>Información de contacto y datos de tu sucursal asignada.</p>
                </a>

            </div>
        </section>

        <section aria-labelledby="ventas-title">
            <h2 id="ventas-title" class="section-title">Ventas</h2>
            <div class="cards">

                <a href="ventas_sucursal.php" class="card">
                    <div class="card-head">
                        <span class="card-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="1" x2="12" y2="23"/>
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                            </svg>
                        </span>
                        <h3>Ventas de mi sucursal</h3>
                    </div>
                    <p>Historial de ventas realizadas en tu sucursal.</p>
                </a>

                <a href="historial_ventas.php" class="card">
                    <div class="card-head">
                        <span class="card-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="20" x2="12" y2="10"/>
                                <line x1="18" y1="20" x2="18" y2="4"/>
                                <line x1="6" y1="20" x2="6" y2="16"/>
                            </svg>
                        </span>
                        <h3>Historial general</h3>
                    </div>
                    <p>Ventas registradas en todas las sucursales del sistema.</p>
                </a>

            </div>
        </section>
    </main>

    <script src="../auth.js"></script>
    <script src="gerente.js?v=<?= $verGerenteJs ?>"></script>
</body>
</html>