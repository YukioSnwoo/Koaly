/* ============================================================
 * Gráficas de Ventas (gerente/ventas_sucursal.php) con Chart.js
 * (js/vendor/chart.umd.min.js, v4.4.1, licencia MIT).
 * Los datos los arma PHP con admin/metricas_ventas.php y llegan en
 * <script id="datosGraficas">: aquí solo se dibujan.
 * - Venta neta = barras moradas (acento); ganancia = línea verde (ok).
 * - Colores del tema: se leen de las variables CSS y se vuelve a pintar
 *   al cambiar entre claro y oscuro.
 * ============================================================ */
(() => {
    const nodo = document.getElementById('datosGraficas');
    if (!nodo || typeof Chart === 'undefined') return;
    const datos = JSON.parse(nodo.textContent);
    const T = datos.textos;

    const lang = document.documentElement.lang === 'en' ? 'en-US' : 'es-MX';
    const dinero = new Intl.NumberFormat(lang, { style: 'currency', currency: 'MXN' });
    const dineroCorto = new Intl.NumberFormat(lang, { style: 'currency', currency: 'MXN', notation: 'compact', maximumFractionDigits: 1 });
    const reduceMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const color = (v) => getComputedStyle(document.documentElement).getPropertyValue(v).trim();
    const graficas = [];

    // Opciones comunes: ejes con el color del texto y montos con formato de moneda
    const base = (formatoY) => ({
        responsive: true,
        maintainAspectRatio: false,
        animation: reduceMovimiento ? false : { duration: 400 },
        interaction: { mode: 'index', intersect: false },
        plugins: {
            // Leyenda en el orden de los datos (venta, ganancia), no en el de dibujo
            legend: { labels: { color: color('--texto-2'), boxWidth: 12, boxHeight: 12, sort: (a, b) => a.datasetIndex - b.datasetIndex } },
            tooltip: {
                callbacks: {
                    label: (c) => `${c.dataset.label}: ${c.raw === null ? '—' : (c.dataset.formato === 'num' ? c.raw : dinero.format(c.raw))}`,
                },
            },
        },
        scales: {
            x: { grid: { display: false }, ticks: { color: color('--texto-3'), maxRotation: 0, autoSkip: true } },
            y: {
                beginAtZero: true,
                grid: { color: color('--borde') },
                ticks: { color: color('--texto-3'), callback: formatoY },
            },
        },
    });

    const barrasVenta = (etiqueta, valores) => ({
        type: 'bar', label: etiqueta, data: valores, order: 2,
        backgroundColor: color('--acento-medio') || color('--acento'), borderColor: color('--acento'),
        borderWidth: 1.5, borderRadius: 4, maxBarThickness: 36,
    });
    const lineaGanancia = (valores) => ({
        type: 'line', label: T.ganancia, data: valores, order: 1,
        borderColor: color('--ok'), backgroundColor: color('--ok'),
        borderWidth: 2.5, pointRadius: 3, tension: 0.25, spanGaps: true,
    });

    const crear = (id, config) => {
        const lienzo = document.getElementById(id);
        if (lienzo) graficas.push(new Chart(lienzo, config));
    };

    const dibujar = () => {
        graficas.splice(0).forEach((g) => g.destroy());

        // Venta y ganancia en el tiempo
        const td = datos.tendencia;
        const hayGanancia = td.utilidad.some((v) => v !== null);
        crear('graficaTendencia', {
            data: {
                labels: td.etiquetas,
                datasets: [barrasVenta(T.venta, td.neta), ...(hayGanancia ? [lineaGanancia(td.utilidad)] : [])],
            },
            options: base((v) => dineroCorto.format(v)),
        });

        // Cuándo se vende: número de ventas (lo que importa para poner personal)
        for (const [id, serie] of [['graficaHora', datos.hora], ['graficaSemana', datos.semana]]) {
            if (!serie) continue;
            const opciones = base((v) => (Number.isInteger(v) ? v : ''));
            opciones.plugins.legend.display = false;
            crear(id, {
                data: {
                    labels: serie.etiquetas,
                    datasets: [{ ...barrasVenta(T.ventas, serie.num), formato: 'num' }],
                },
                options: opciones,
            });
        }

        // Por categoría: barras horizontales de venta y ganancia
        const cat = datos.categorias;
        if (cat && cat.etiquetas.length > 1) {
            const opciones = base((v) => dineroCorto.format(v));
            opciones.indexAxis = 'y';
            opciones.scales = {
                x: { beginAtZero: true, grid: { color: color('--borde') }, ticks: { color: color('--texto-3'), callback: (v) => dineroCorto.format(v) } },
                y: { grid: { display: false }, ticks: { color: color('--texto-2') } },
            };
            crear('graficaCategorias', {
                type: 'bar',
                data: {
                    labels: cat.etiquetas,
                    datasets: [
                        barrasVenta(T.venta, cat.neta),
                        { type: 'bar', label: T.ganancia, data: cat.utilidad, backgroundColor: color('--ok'), borderRadius: 4, maxBarThickness: 18 },
                    ],
                },
                options: opciones,
            });
        }
    };

    dibujar();
    // Cambio de tema (botón de la luna): mismos datos, colores nuevos
    new MutationObserver(dibujar).observe(document.documentElement, { attributes: true, attributeFilter: ['data-tema'] });
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', dibujar);
})();
