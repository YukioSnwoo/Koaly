/* ============================================================
 * Gráfica del panel del admin con Chart.js (../gerente/js/vendor/chart.umd.min.js).
 * Los datos los arma PHP en panel.php y llegan en <script id="datosGraficas">:
 * aquí solo se dibujan. graficaTendencia: venta (barras) y ganancia (línea)
 * de la sucursal elegida. Los colores se leen de las variables CSS y se vuelve
 * a pintar al cambiar entre claro y oscuro.
 * ============================================================ */
(() => {
    const nodo = document.getElementById('datosGraficas');
    const lienzo = document.getElementById('graficaTendencia');
    if (!nodo || !lienzo || typeof Chart === 'undefined') return;
    const datos = JSON.parse(nodo.textContent);
    const T = datos.textos;
    const td = datos.tendencia;

    const lang = document.documentElement.lang === 'en' ? 'en-US' : 'es-MX';
    const dinero = new Intl.NumberFormat(lang, { style: 'currency', currency: 'MXN' });
    const dineroCorto = new Intl.NumberFormat(lang, { style: 'currency', currency: 'MXN', notation: 'compact', maximumFractionDigits: 1 });
    const reduceMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const color = (v) => getComputedStyle(document.documentElement).getPropertyValue(v).trim();

    let grafica = null;
    const dibujar = () => {
        if (grafica) grafica.destroy();
        const acento = color('--acento'), ok = color('--ok');
        const hayGanancia = td.utilidad.some((v) => v !== null);
        grafica = new Chart(lienzo, {
            data: {
                labels: td.etiquetas,
                datasets: [
                    { type: 'bar', label: T.venta, data: td.neta, order: 2, backgroundColor: color('--acento-medio'), borderColor: acento, borderWidth: 1.5, borderRadius: 4, maxBarThickness: 36 },
                    ...(hayGanancia ? [{ type: 'line', label: T.ganancia, data: td.utilidad, order: 1, borderColor: ok, backgroundColor: ok, borderWidth: 2.5, pointRadius: 2.5, tension: 0.25, spanGaps: true }] : []),
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: reduceMovimiento ? false : { duration: 400 },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { labels: { color: color('--texto-2'), boxWidth: 12, boxHeight: 12, sort: (a, b) => a.datasetIndex - b.datasetIndex } },
                    tooltip: { callbacks: { label: (c) => `${c.dataset.label}: ${c.raw === null ? '—' : dinero.format(c.raw)}` } },
                },
                scales: {
                    x: { grid: { display: false }, ticks: { color: color('--texto-3'), maxRotation: 0, autoSkip: true } },
                    y: { beginAtZero: true, grid: { color: color('--borde') }, ticks: { color: color('--texto-3'), callback: (v) => dineroCorto.format(v) } },
                },
            },
        });
    };

    dibujar();
    // Cambio de tema (botón de la luna): mismos datos, colores nuevos
    new MutationObserver(dibujar).observe(document.documentElement, { attributes: true, attributeFilter: ['data-tema'] });
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', dibujar);
})();
