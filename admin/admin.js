/* ═══════════════════════════════════════════════════
   Koaly Admin — JavaScript
   ═══════════════════════════════════════════════════ */

/* ── Traducciones ──
   header.php deja en window.I18N las claves "js.*" de admin/lang/{idioma}.php.
   tj('clave', {n: 3}) reemplaza {n}. */
function tj(clave, vars = {}) {
  let texto = (window.I18N && window.I18N[clave]) || clave;
  for (const [k, v] of Object.entries(vars)) texto = texto.split('{' + k + '}').join(String(v));
  return texto;
}


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

  const interactivo = 'a, button, input, select, textarea, label, summary, [role="button"], [data-abrir-ficha]';
  document.addEventListener('mouseover', (e) => {
    if (e.target.closest(interactivo)) dot.classList.add('is-hover');
  });
  document.addEventListener('mouseout', (e) => {
    if (e.target.closest(interactivo)) dot.classList.remove('is-hover');
  });

  render();
}


/* La barra lateral (indicador, contraer y cajón de celular) está en
   ../estilos/barra-lateral.js, común con el gerente. */


/* ── Modal helpers (generic) ── */
window.openModal = (id) => {
  const m = document.getElementById(id);
  if (m) m.style.display = 'flex';
};
window.closeModal = (id) => {
  const m = document.getElementById(id);
  if (m) m.style.display = 'none';
};

/* ── Ver / ocultar contraseña ──
   <div class="campo-contrasena"><input type="password"><button class="ver-contrasena"></div> */
document.addEventListener('click', (e) => {
  const boton = e.target.closest('.ver-contrasena');
  if (!boton) return;
  const visible = boton.getAttribute('aria-pressed') !== 'true';
  boton.previousElementSibling.type = visible ? 'text' : 'password';
  boton.setAttribute('aria-pressed', visible ? 'true' : 'false');
});

document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  document.querySelectorAll('.modal-backdrop').forEach(m => {
    if (m.style.display === 'flex') m.style.display = 'none';
  });
});


document.addEventListener('DOMContentLoaded', () => {
  initCustomCursor();

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
