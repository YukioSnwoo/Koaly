/* ═══════════════════════════════════════════════════
   Koaly Admin — JavaScript
   ═══════════════════════════════════════════════════ */

/* ── Tema claro / oscuro ──
   <html data-tema="claro|oscuro"> si el usuario eligió; si no, sigue al
   sistema. La clave es común con gerente y cajero: el tema se conserva
   al pasar de un módulo a otro. */
const CLAVE_TEMA = 'koaly-tema';

function temaActual() {
  const elegido = document.documentElement.getAttribute('data-tema');
  if (elegido) return elegido;
  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'oscuro' : 'claro';
}

function actualizarBotonTema() {
  const boton = document.getElementById('botonTema');
  if (!boton) return;
  const etiqueta = temaActual() === 'oscuro' ? boton.dataset.aClaro : boton.dataset.aOscuro;
  boton.setAttribute('aria-label', etiqueta);
  boton.title = etiqueta;
}

document.addEventListener('click', (e) => {
  if (!e.target.closest('#botonTema')) return;
  const nuevo = temaActual() === 'oscuro' ? 'claro' : 'oscuro';
  document.documentElement.setAttribute('data-tema', nuevo);
  try { localStorage.setItem(CLAVE_TEMA, nuevo); } catch (err) { /* modo privado: solo esta página */ }
  actualizarBotonTema();
});
window.matchMedia('(prefers-color-scheme: dark)').addEventListener?.('change', actualizarBotonTema);


/* ── Cursor personalizado ──
   Solo con puntero fino (mouse). Si JS falla, el cursor nativo queda
   visible porque la clase .cursor-custom no se añade al body. */
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

  const interactivo = 'a, button, input, select, textarea, label, summary, [role="button"]';
  document.addEventListener('mouseover', (e) => {
    if (e.target.closest(interactivo)) dot.classList.add('is-hover');
  });
  document.addEventListener('mouseout', (e) => {
    if (e.target.closest(interactivo)) dot.classList.remove('is-hover');
  });

  render();
}


/* ── Barra lateral en celular ──
   El botón ☰ la abre encima del contenido. Se cierra con el fondo oscuro,
   con Esc, al elegir una página o al agrandar la ventana. */
function initBarraLateral() {
  const boton = document.getElementById('botonMenu');
  const fondo = document.getElementById('barraLateralFondo');
  const barra = document.getElementById('barraLateral');
  if (!boton || !barra) return;

  const abierta = () => document.body.classList.contains('sidebar-abierta');
  const poner = (abrir) => {
    document.body.classList.toggle('sidebar-abierta', abrir);
    boton.setAttribute('aria-expanded', abrir ? 'true' : 'false');
    if (abrir) {
      const actual = barra.querySelector('.sidebar__link--active') || barra.querySelector('.sidebar__link');
      if (actual) actual.focus();
    }
  };

  boton.addEventListener('click', () => poner(!abierta()));
  if (fondo) fondo.addEventListener('click', () => poner(false));
  barra.querySelectorAll('.sidebar__link').forEach(a => a.addEventListener('click', () => poner(false)));
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && abierta()) { poner(false); boton.focus(); }
  });
  window.matchMedia('(min-width: 901px)').addEventListener('change', (e) => { if (e.matches) poner(false); });
}


/* ── Menú de idioma (<details class="idioma">): cerrar al hacer clic fuera o con Esc ── */
document.addEventListener('click', (e) => {
  document.querySelectorAll('details.idioma[open]').forEach(d => {
    if (!d.contains(e.target)) d.removeAttribute('open');
  });
});


/* ── Modal helpers (generic) ── */
window.openModal = (id) => {
  const m = document.getElementById(id);
  if (m) m.style.display = 'flex';
};
window.closeModal = (id) => {
  const m = document.getElementById(id);
  if (m) m.style.display = 'none';
};

document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  document.querySelectorAll('details.idioma[open]').forEach(d => d.removeAttribute('open'));
  document.querySelectorAll('.modal-backdrop').forEach(m => {
    if (m.style.display === 'flex') m.style.display = 'none';
  });
});


document.addEventListener('DOMContentLoaded', () => {
  actualizarBotonTema();
  initCustomCursor();
  initBarraLateral();

  /* ── Date inputs default to today ── */
  document.querySelectorAll('input[type="date"]').forEach(inp => {
    if (!inp.value) inp.value = new Date().toISOString().slice(0, 10);
  });

  /* ── Simple search filter for tables ── */
  document.querySelectorAll('[data-search]').forEach(input => {
    const target = document.querySelector(input.dataset.search);
    if (!target) return;
    input.addEventListener('input', () => {
      const q = input.value.toLowerCase();
      target.querySelectorAll('tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
      });
    });
  });

  // Close modal on backdrop click
  document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
    backdrop.addEventListener('click', (e) => {
      if (e.target === backdrop) backdrop.style.display = 'none';
    });
  });
});
