/* ============================================================
 * Gráficas del gerente con Chart.js (js/vendor/chart.umd.min.js, v4.4.1, MIT).
 * Los datos los arma PHP con admin/metricas_ventas.php y llegan en
 * <script id="datosGraficas">: aquí solo se dibujan. Cada gráfica se dibuja
 * solo si su <canvas> está en la página:
 *   graficaTendencia  venta (barras) y ganancia (línea)    panel e Gráficas
 *   graficaMetodos    cómo pagan (dona)                      Gráficas
 *   mapaCalor         ventas por día y hora (HTML)           Gráficas
 *   graficaMargen     margen por categoría (barras)          Gráficas
 *   graficaMatriz     unidades contra margen (burbujas)      Gráficas
 *   graficaABC        Pareto: venta y % acumulado            Gráficas
 * Colores del tema: se leen de las variables CSS y se vuelve a pintar al
 * cambiar entre claro y oscuro.
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
    // Color del tema con transparencia (los tokens son #rrggbb; si no, se usa tal cual)
    const transparente = (c, a) => {
        const m = /^#([0-9a-f]{6})$/i.exec(c);
        if (!m) return c;
        const n = parseInt(m[1], 16);
        return `rgba(${n >> 16}, ${(n >> 8) & 255}, ${n & 255}, ${a})`;
    };
    const graficas = [];

    const ejeX = () => ({ grid: { display: false }, ticks: { color: color('--texto-3'), maxRotation: 0, autoSkip: true } });
    const ejeY = (callback) => ({ beginAtZero: true, grid: { color: color('--borde') }, ticks: { color: color('--texto-3'), callback } });

    // Opciones comunes; la leyenda va en el orden de los datos, no en el de dibujo
    const base = (extra = {}) => ({
        responsive: true,
        maintainAspectRatio: false,
        animation: reduceMovimiento ? false : { duration: 400 },
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { labels: { color: color('--texto-2'), boxWidth: 12, boxHeight: 12, sort: (a, b) => a.datasetIndex - b.datasetIndex } },
            tooltip: { callbacks: { label: (c) => `${c.dataset.label}: ${c.raw === null ? '—' : dinero.format(c.raw)}` } },
        },
        scales: { x: ejeX(), y: ejeY((v) => dineroCorto.format(v)) },
        ...extra,
    });
    const sinLeyenda = (o) => { o.plugins.legend.display = false; return o; };

    const crear = (id, config) => {
        const lienzo = document.getElementById(id);
        if (lienzo) graficas.push(new Chart(lienzo, config));
    };

    const dibujar = () => {
        graficas.splice(0).forEach((g) => g.destroy());
        const acento = color('--acento'), ok = color('--ok'), peligro = color('--peligro'), aviso = color('--aviso');
        const acentoSuave = color('--acento-medio') || transparente(acento, 0.5);

        // Venta (barras) y ganancia (línea) en el tiempo
        const td = datos.tendencia;
        if (td) {
            const hayGanancia = td.utilidad.some((v) => v !== null);
            crear('graficaTendencia', {
                data: {
                    labels: td.etiquetas,
                    datasets: [
                        { type: 'bar', label: T.venta, data: td.neta, order: 2, backgroundColor: acentoSuave, borderColor: acento, borderWidth: 1.5, borderRadius: 4, maxBarThickness: 36 },
                        ...(hayGanancia ? [{ type: 'line', label: T.ganancia, data: td.utilidad, order: 1, borderColor: ok, backgroundColor: ok, borderWidth: 2.5, pointRadius: 2.5, tension: 0.25, spanGaps: true }] : []),
                    ],
                },
                options: base(),
            });
        }

        // Cómo pagan: dona
        const mp = datos.metodos;
        if (mp) {
            crear('graficaMetodos', {
                type: 'doughnut',
                data: { labels: mp.etiquetas, datasets: [{ data: mp.neta, backgroundColor: [acento, ok, aviso, peligro], borderColor: color('--superficie'), borderWidth: 3 }] },
                options: {
                    responsive: true, maintainAspectRatio: false, cutout: '62%',
                    animation: reduceMovimiento ? false : { duration: 400 },
                    plugins: {
                        legend: { position: 'bottom', labels: { color: color('--texto-2'), boxWidth: 12, boxHeight: 12 } },
                        tooltip: { callbacks: { label: (c) => {
                            const total = c.dataset.data.reduce((a, b) => a + b, 0);
                            return `${c.label}: ${dinero.format(c.raw)} (${Math.round(c.raw / total * 100)}%)`;
                        } } },
                    },
                },
            });
        }

        // Margen por categoría: barras horizontales, en rojo las de margen bajo
        const cat = datos.categorias;
        if (cat && cat.etiquetas.length) {
            const opciones = sinLeyenda(base({ indexAxis: 'y' }));
            opciones.scales = { x: ejeY((v) => v + '%'), y: { grid: { display: false }, ticks: { color: color('--texto-2') } } };
            opciones.plugins.tooltip.callbacks.label = (c) => `${T.margen}: ${c.raw}%`;
            crear('graficaMargen', {
                type: 'bar',
                data: { labels: cat.etiquetas, datasets: [{ label: T.margen, data: cat.margen, backgroundColor: cat.margen.map((m) => (m < cat.bajo ? peligro : ok)), borderRadius: 4, maxBarThickness: 22 }] },
                options: opciones,
            });
        }

        // Matriz de productos: burbujas con cuatro cuadrantes
        const mz = datos.matriz;
        if (mz && mz.puntos.length) {
            const maxVenta = Math.max(...mz.puntos.map((p) => p.venta)) || 1;
            const cuadrante = (p) => (p.y >= mz.corteY ? (p.x >= mz.corteX ? ok : aviso) : (p.x >= mz.corteX ? acento : peligro));
            const opciones = sinLeyenda(base({ interaction: { mode: 'nearest', intersect: true } }));
            opciones.scales = {
                x: { ...ejeY((v) => v), title: { display: true, text: T.unidades, color: color('--texto-3') } },
                y: { ...ejeY((v) => v + '%'), title: { display: true, text: T.margen, color: color('--texto-3') } },
            };
            opciones.plugins.tooltip.callbacks.label = (c) => `${c.raw.n}: ${c.raw.x} u · ${c.raw.y}% · ${dinero.format(c.raw.venta)}`;
            crear('graficaMatriz', {
                type: 'bubble',
                data: { datasets: [{
                    label: T.margen,
                    data: mz.puntos.map((p) => ({ ...p, r: 5 + Math.sqrt(p.venta / maxVenta) * 16 })),
                    backgroundColor: mz.puntos.map((p) => transparente(cuadrante(p), 0.7)),
                    borderColor: mz.puntos.map(cuadrante),
                }] },
                options: opciones,
                plugins: [{
                    // Líneas de los cortes y nombre de cada producto
                    id: 'cuadrantes',
                    beforeDatasetsDraw(ch) {
                        const { ctx, chartArea: a, scales: { x, y } } = ch;
                        ctx.save();
                        ctx.strokeStyle = color('--borde-fuerte') || color('--texto-4');
                        ctx.setLineDash([5, 4]);
                        ctx.beginPath();
                        ctx.moveTo(x.getPixelForValue(mz.corteX), a.top); ctx.lineTo(x.getPixelForValue(mz.corteX), a.bottom);
                        ctx.moveTo(a.left, y.getPixelForValue(mz.corteY)); ctx.lineTo(a.right, y.getPixelForValue(mz.corteY));
                        ctx.stroke();
                        ctx.restore();
                    },
                    afterDatasetsDraw(ch) {
                        if (mz.puntos.length > 25) return;   // con muchos, solo al pasar el mouse
                        const ctx = ch.ctx;
                        ctx.save();
                        ctx.font = '11px system-ui, sans-serif';
                        ctx.fillStyle = color('--texto-2');
                        ch.getDatasetMeta(0).data.forEach((pt, i) => {
                            // A la derecha de la burbuja; si no cabe, a la izquierda
                            const texto = mz.puntos[i].n, ancho = ctx.measureText(texto).width, r = pt.options.radius;
                            const x = pt.x + r + 3 + ancho > ch.chartArea.right ? pt.x - r - 3 - ancho : pt.x + r + 3;
                            ctx.fillText(texto, x, pt.y + 4);
                        });
                        ctx.restore();
                    },
                }],
            });
        }

        // Análisis ABC: barras de venta (color por clase) y línea del % acumulado
        const abc = datos.abc;
        if (abc && abc.etiquetas.length) {
            const porClase = { A: acento, B: acentoSuave, C: color('--superficie-3') };
            const opciones = base();
            opciones.scales = {
                x: { ...ejeX(), ticks: { color: color('--texto-3'), maxRotation: 50, autoSkip: false, font: { size: 10 } } },
                y: ejeY((v) => dineroCorto.format(v)),
                y2: { position: 'right', min: 0, max: 100, grid: { display: false }, ticks: { color: color('--texto-3'), callback: (v) => v + '%' } },
            };
            opciones.plugins.tooltip.callbacks.label = (c) => (c.dataset.yAxisID === 'y2' ? `${T.acumulado}: ${c.raw}%` : `${T.venta}: ${dinero.format(c.raw)} (${abc.clase[c.dataIndex]})`);
            crear('graficaABC', {
                data: {
                    labels: abc.etiquetas,
                    datasets: [
                        { type: 'bar', label: T.venta, data: abc.neta, yAxisID: 'y', order: 2, backgroundColor: abc.clase.map((k) => porClase[k]), borderColor: acento, borderWidth: 1, borderRadius: 4 },
                        { type: 'line', label: T.acumulado, data: abc.acumulado, yAxisID: 'y2', order: 1, borderColor: color('--texto-2'), backgroundColor: color('--texto-2'), borderWidth: 2, pointRadius: 2 },
                    ],
                },
                options: opciones,
            });
        }

        // Mapa de calor (HTML: Chart.js no lo trae). Intensidad = ventas ÷ la hora más llena
        const mc = datos.calor, cont = document.getElementById('mapaCalor');
        if (mc && cont && !cont.childElementCount) {
            const max = Math.max(1, ...mc.filas.flat());
            cont.style.setProperty('--columnas', mc.horas.length);
            const celdas = ['<span></span>', ...mc.horas.map((h) => `<span class="mc-hora">${h}</span>`)];
            mc.filas.forEach((fila, d) => {
                celdas.push(`<span class="mc-dia">${mc.dias[d]}</span>`);
                fila.forEach((n, i) => celdas.push(`<span class="mc-celda" style="--n:${(n / max).toFixed(2)}" title="${mc.dias[d]} ${mc.horas[i]}:00 · ${n} ${T.ventas.toLowerCase()}"></span>`));
            });
            cont.innerHTML = celdas.join('');
        }
    };

    dibujar();
    // Cambio de tema (botón de la luna): mismos datos, colores nuevos
    new MutationObserver(dibujar).observe(document.documentElement, { attributes: true, attributeFilter: ['data-tema'] });
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', dibujar);
})();
