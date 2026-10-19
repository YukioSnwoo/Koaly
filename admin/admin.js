/* ═══════════════════════════════════════════════════
   Koalicius Admin — JavaScript
   ═══════════════════════════════════════════════════ */

document.addEventListener('DOMContentLoaded', () => {

  /* ── Theme toggle (dark / light) ── */
  const THEME_KEY = 'koalicius-admin-theme';
  const themeToggle = document.getElementById('themeToggle');
  if (themeToggle) {
    themeToggle.addEventListener('click', () => {
      const isLight = document.documentElement.getAttribute('data-theme') === 'light';
      if (isLight) {
        document.documentElement.removeAttribute('data-theme');
      } else {
        document.documentElement.setAttribute('data-theme', 'light');
      }
      try { localStorage.setItem(THEME_KEY, isLight ? 'dark' : 'light'); } catch (e) {}
    });
  }

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
