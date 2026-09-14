/* ═══════════════════════════════════════════════════
   Koalicius Admin — JavaScript
   ═══════════════════════════════════════════════════ */

document.addEventListener('DOMContentLoaded', () => {

  /* ── Sidebar navigation (SPA-style page switching) ── */
  const links  = document.querySelectorAll('.sidebar__link[data-page]');
  const pages  = document.querySelectorAll('.admin-page');
  const title  = document.querySelector('.page-header h1');
  const subtitle = document.querySelector('.page-header p');

  const pageMeta = {
    'panel':      { title: 'Panel general',            sub: 'Vista consolidada de todas las sucursales' },
    'sucursales': { title: 'Sucursales',               sub: 'Administra las sedes de la empresa' },
    'gerentes':   { title: 'Gerentes',                 sub: 'Registra gerentes y asígnalos a una sucursal' },
    'inventario': { title: 'Inventario global',         sub: 'Consulta de solo lectura entre todas las sucursales' },
    'ventas':     { title: 'Ventas',                   sub: 'Historial consolidado de todas las sucursales' },
    'reportes':   { title: 'Reportes',                 sub: 'Comparativo de desempeño entre sucursales' },
    'config':     { title: 'Configuración',            sub: 'Ajustes generales del panel' },
  };

  function navigateTo(page) {
    links.forEach(l => l.classList.toggle('sidebar__link--active', l.dataset.page === page));
    pages.forEach(p => p.classList.toggle('admin-page--active', p.id === 'page-' + page));
    if (pageMeta[page]) {
      if (title)    title.textContent    = pageMeta[page].title;
      if (subtitle) subtitle.textContent = pageMeta[page].sub;
    }
    history.replaceState(null, '', '#' + page);
  }

  links.forEach(link => {
    link.addEventListener('click', e => {
      e.preventDefault();
      navigateTo(link.dataset.page);
    });
  });

  // Restore from hash or default to panel
  const initial = location.hash.replace('#', '') || 'panel';
  navigateTo(pageMeta[initial] ? initial : 'panel');

  /* ── Language selector (cosmetic) ── */
  const langSelect = document.getElementById('langSelect');
  if (langSelect) {
    langSelect.addEventListener('change', () => {
      // Placeholder — would POST to cambiar-idioma.php
      console.log('Idioma:', langSelect.value);
    });
  }

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

  /* ── Modal helpers (generic) ── */
  window.openModal = (id) => {
    const m = document.getElementById(id);
    if (m) m.style.display = 'flex';
  };
  window.closeModal = (id) => {
    const m = document.getElementById(id);
    if (m) m.style.display = 'none';
  };
  // Close modal on backdrop click
  document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
    backdrop.addEventListener('click', (e) => {
      if (e.target === backdrop) backdrop.style.display = 'none';
    });
  });
});
