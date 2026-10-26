/* ============================================================
 * barra-lateral.js — Barra lateral común del admin y el gerente
 * (estilos/barra-lateral.css; marcado en admin/sidebar.php y
 * gerente/header.php).
 * - Indicador: el bloque morado se desliza al enlace bajo el cursor
 *   o con foco y regresa a la página activa al salir.
 * - Escritorio: «Contraer» la deja en solo íconos; se recuerda en
 *   localStorage y cada header.php la aplica antes de pintar.
 * - Celular: el botón ☰ (que se vuelve ✕) abre un cajón debajo de la
 *   barra superior. Se cierra con el fondo oscuro, con Esc, al elegir
 *   una página o al agrandar la ventana.
 * ============================================================ */
const CLAVE_BARRA_CONTRAIDA = 'koaly-barra';

function initBarraLateral() {
    const boton = document.getElementById('botonMenu');
    const fondo = document.getElementById('barraLateralFondo');
    const barra = document.getElementById('barraLateral');
    if (!boton || !barra) return;

    const nav = barra.querySelector('.sidebar-nav');
    const indicador = barra.querySelector('.sidebar-indicador');
    const enlaces = [...barra.querySelectorAll('.sidebar-link')];
    const activo = barra.querySelector('.sidebar-link.active');
    const escritorio = window.matchMedia('(min-width: 901px)');

    // --- Indicador deslizante
    enlaces.forEach((a, i) => a.style.setProperty('--i', i)); // escalonado al abrir en celular
    const moverIndicador = (enlace) => {
        if (!indicador) return;
        indicador.hidden = !enlace; // páginas fuera del menú (p. ej. producto.php): sin indicador
        if (!enlace) return;
        indicador.style.transform = `translateY(${enlace.offsetTop}px)`;
        indicador.style.height = enlace.offsetHeight + 'px';
        enlaces.forEach(a => a.classList.toggle('con-indicador', a === enlace));
    };
    if (nav) {
        nav.classList.add('sin-animacion');
        moverIndicador(activo);
        requestAnimationFrame(() => requestAnimationFrame(() => nav.classList.remove('sin-animacion')));
        enlaces.forEach(a => {
            a.addEventListener('mouseenter', () => moverIndicador(a));
            a.addEventListener('focus', () => moverIndicador(a));
        });
        nav.addEventListener('mouseleave', () => moverIndicador(activo));
        nav.addEventListener('focusout', (e) => { if (!nav.contains(e.relatedTarget)) moverIndicador(activo); });
    }

    // --- Contraer a solo íconos (escritorio)
    const raiz = document.documentElement;
    const contraer = document.getElementById('botonContraer');
    const contraida = () => raiz.classList.contains('barra-contraida');
    const marcarContraer = () => {
        if (!contraer) return;
        const texto = contraida() ? contraer.dataset.textoExpandir : contraer.dataset.textoContraer;
        contraer.setAttribute('aria-pressed', contraida() ? 'true' : 'false');
        contraer.setAttribute('aria-label', texto);
        contraer.querySelector('.sidebar-texto').textContent = texto;
    };
    marcarContraer();
    if (contraer) {
        contraer.addEventListener('click', () => {
            raiz.classList.toggle('barra-contraida');
            try { localStorage.setItem(CLAVE_BARRA_CONTRAIDA, contraida() ? 'contraida' : 'abierta'); } catch (e) {}
            marcarContraer();
            ocultarTip();
        });
    }

    // Nombre del enlace junto al ícono cuando la barra está contraída
    let tip = null;
    const mostrarTip = (el) => {
        if (!contraida() || !escritorio.matches) return;
        if (!tip) {
            tip = document.createElement('span');
            tip.className = 'sidebar-tip';
            tip.setAttribute('aria-hidden', 'true'); // los enlaces ya tienen su texto
            document.body.appendChild(tip);
        }
        const r = el.getBoundingClientRect();
        tip.textContent = el.dataset.titulo;
        tip.style.top = (r.top + r.height / 2) + 'px';
        tip.style.left = (barra.getBoundingClientRect().right + 8) + 'px';
        tip.hidden = false;
    };
    function ocultarTip() { if (tip) tip.hidden = true; }
    barra.querySelectorAll('[data-titulo]').forEach(el => {
        el.addEventListener('mouseenter', () => mostrarTip(el));
        el.addEventListener('focus', () => mostrarTip(el));
        el.addEventListener('mouseleave', ocultarTip);
        el.addEventListener('blur', ocultarTip);
    });

    // --- Cajón en celular
    const barraSuperior = boton.closest('header');
    const abierta = () => document.body.classList.contains('sidebar-abierta');
    const poner = (abrir) => {
        if (abrir && barraSuperior) {
            raiz.style.setProperty('--alto-barra', barraSuperior.getBoundingClientRect().bottom + 'px');
        }
        document.body.classList.toggle('sidebar-abierta', abrir);
        boton.setAttribute('aria-expanded', abrir ? 'true' : 'false');
        const etiqueta = abrir ? boton.dataset.textoCerrar : boton.dataset.textoAbrir;
        if (etiqueta) {
            boton.setAttribute('aria-label', etiqueta);
            boton.title = etiqueta;
        }
        if (abrir) {
            const actual = activo || enlaces[0];
            if (actual) actual.focus();
        }
    };

    boton.addEventListener('click', () => poner(!abierta()));
    if (fondo) fondo.addEventListener('click', () => poner(false));
    enlaces.forEach(a => a.addEventListener('click', () => poner(false)));
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && abierta()) { poner(false); boton.focus(); }
    });
    escritorio.addEventListener('change', (e) => { if (e.matches) poner(false); });
}
document.addEventListener('DOMContentLoaded', initBarraLateral);
