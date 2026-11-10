/* ============================================================
 * Gráficas de Reportes del admin con Chart.js (../gerente/js/vendor/chart.umd.min.js).
 * Los datos los arma PHP en reportes.php y llegan en <script id="datosReportes">:
 * aquí solo se dibujan. graficaTendencia: venta (barras) y ganancia (línea) de
 * todas las sucursales; graficaSucursales: venta y ganancia por sucursal.
 * Los colores se leen de las variables CSS y se vuelve a pintar al cambiar
 * entre claro y oscuro.
 * ============================================================ */
(() => {
    const nodo = document.getElementById('datosReportes');
    if (!nodo || typeof Chart === 'undefined') return;
    const datos = JSON.parse(nodo.textContent);
    const T = datos.textos;

    const lang = document.documentElement.lang === 'en' ? 'en-US' : 'es-MX';
    const dinero = new Intl.NumberFormat(lang, { style: 'currency', currency: 'MXN' });
    const dineroCorto = new Intl.NumberFormat(lang, { style: 'currency', currency: 'MXN', notation: 'compact', maximumFractionDigits: 1 });
    const reduceMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const color = (v) => getComputedStyle(document.documentElement).getPropertyValue(v).trim();

    const lienzoTendencia = document.getElementById('graficaTendencia');
    const lienzoSucursales = document.getElementById('graficaSucursales');
    let graficas = [];

    const opciones = (horizontal) => {
        const valor = horizontal ? 'x' : 'y', categoria = horizontal ? 'y' : 'x';
        return {
            responsive: true,
            maintainAspectRatio: false,
            animation: reduceMovimiento ? false : { duration: 400 },
            indexAxis: horizontal ? 'y' : 'x',
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { labels: { color: color('--texto-2'), boxWidth: 12, boxHeight: 12, sort: (a, b) => a.datasetIndex - b.datasetIndex } },
                tooltip: { callbacks: { label: (c) => `${c.dataset.label}: ${c.raw === null ? '—' : dinero.format(c.raw)}` } },
            },
            scales: {
                [categoria]: { grid: { display: false }, ticks: { color: color('--texto-3'), maxRotation: 0, autoSkip: true } },
                [valor]: { beginAtZero: true, grid: { color: color('--borde') }, ticks: { color: color('--texto-3'), callback: (v) => dineroCorto.format(v) } },
            },
        };
    };

    const dibujar = () => {
        graficas.forEach((g) => g.destroy());
        graficas = [];
        const acento = color('--acento'), ok = color('--ok');

        if (lienzoTendencia) {
            const td = datos.tendencia;
            const hayGanancia = td.utilidad.some((v) => v !== null);
            graficas.push(new Chart(lienzoTendencia, {
                data: {
                    labels: td.etiquetas,
                    datasets: [
                        { type: 'bar', label: T.venta, data: td.neta, order: 2, backgroundColor: color('--acento-medio'), borderColor: acento, borderWidth: 1.5, borderRadius: 4, maxBarThickness: 36 },
                        ...(hayGanancia ? [{ type: 'line', label: T.ganancia, data: td.utilidad, order: 1, borderColor: ok, backgroundColor: ok, borderWidth: 2.5, pointRadius: 2.5, tension: 0.25, spanGaps: true }] : []),
                    ],
                },
                options: opciones(false),
            }));
        }

        if (lienzoSucursales) {
            const cs = datos.comparativo;
            const hayGanancia = cs.utilidad.some((v) => v !== null);
            graficas.push(new Chart(lienzoSucursales, {
                type: 'bar',
                data: {
                    labels: cs.etiquetas,
                    datasets: [
                        { label: T.venta, data: cs.neta, backgroundColor: color('--acento-medio'), borderColor: acento, borderWidth: 1.5, borderRadius: 4, maxBarThickness: 22 },
                        ...(hayGanancia ? [{ label: T.ganancia, data: cs.utilidad, backgroundColor: ok, borderColor: ok, borderWidth: 1.5, borderRadius: 4, maxBarThickness: 22 }] : []),
                    ],
                },
                options: opciones(true),
            }));
        }
    };

    dibujar();
    // Cambio de tema (botón de la luna): mismos datos, colores nuevos
    new MutationObserver(dibujar).observe(document.documentElement, { attributes: true, attributeFilter: ['data-tema'] });
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', dibujar);
})();
