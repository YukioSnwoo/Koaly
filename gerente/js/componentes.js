/* ============================================================
 * componentes.js — Bloques reutilizables en 2+ páginas.
 * Depende de: core.js (usa abrirModal).
 * Usado por: productos, existencias, cajeros, cajas.
 * ============================================================ */


/* ============================================================
 * Select custom — reemplaza el <select> nativo por un componente
 * con panel flotante, esquinas redondeadas, hover, checkmark en
 * la opción activa, y buscador cuando tiene >5 opciones.
 * El <select> original queda oculto (clip/opacity) y sigue
 * enviando el valor con el POST.
 * ============================================================ */

const _selectCustomActivos = new Set();

function _cerrarTodosLosSelects(excepto) {
    _selectCustomActivos.forEach(inst => {
        if (inst.wrapper !== excepto) inst.close();
    });
}

function initCustomSelect(select) {
    if (!select || select.dataset.customInit === '1') return;
    select.dataset.customInit = '1';

    const wrapper = document.createElement('div');
    wrapper.className = 'select-custom';
    select.parentNode.insertBefore(wrapper, select);
    wrapper.appendChild(select);

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'select-custom-trigger';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    wrapper.appendChild(trigger);

    const panel = document.createElement('div');
    panel.className = 'select-custom-panel';
    panel.setAttribute('role', 'listbox');
    document.body.appendChild(panel);

    const opciones = () => Array.from(select.options);
    const necesitaBuscador = opciones().length > 5;

    const pintarTrigger = () => {
        const opt = select.options[select.selectedIndex];
        const texto = opt ? opt.text : 'Selecciona…';
        trigger.innerHTML = `<span>${texto}</span>
        <svg class="select-custom-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>`;
        trigger.classList.toggle('is-placeholder', !opt || opt.value === '' || opt.disabled);
    };

    const pintarPanel = () => {
        panel.innerHTML = '';

        let inputBusqueda = null;
        if (necesitaBuscador) {
            const wrap = document.createElement('div');
            wrap.className = 'select-custom-search';

            inputBusqueda = document.createElement('input');
            inputBusqueda.type = 'text';
            inputBusqueda.className = 'select-custom-search-input';
            inputBusqueda.placeholder = 'Buscar...';
            inputBusqueda.setAttribute('autocomplete', 'off');
            inputBusqueda.setAttribute('aria-label', 'Buscar opción');

            wrap.appendChild(inputBusqueda);
            panel.appendChild(wrap);
        }

        opciones().forEach((opt, i) => {
            const item = document.createElement('div');
            item.className = 'select-custom-option';
            item.setAttribute('role', 'option');
            item.dataset.index = i;
            if (i === select.selectedIndex) {
                item.classList.add('is-selected');
                item.setAttribute('aria-selected', 'true');
            }
            if (opt.disabled) {
                item.classList.add('is-disabled');
            }
            item.innerHTML = `<span>${opt.text}</span>`;

            if (!opt.disabled) {
                item.addEventListener('click', () => {
                    select.selectedIndex = i;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    pintarTrigger();
                    pintarPanel();
                    cerrar();
                });
                item.addEventListener('mouseenter', () => {
                    panel.querySelectorAll('.is-focused').forEach(el => el.classList.remove('is-focused'));
                    item.classList.add('is-focused');
                });
            }
            panel.appendChild(item);
        });

        if (inputBusqueda) {
            const aplicarFiltro = () => {
                const q = inputBusqueda.value.trim().toLowerCase();
                panel.querySelectorAll('.select-custom-option').forEach(item => {
                    const coincide = item.textContent.toLowerCase().includes(q);
                    item.style.display = coincide ? '' : 'none';
                });
            };

            inputBusqueda.addEventListener('input', aplicarFiltro);

            inputBusqueda.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    e.stopPropagation();
                    cerrar();
                } else if (e.key === 'Enter') {
                    const visible = Array.from(panel.querySelectorAll('.select-custom-option'))
                    .find(el => el.style.display !== 'none' && !el.classList.contains('is-disabled'));
                    if (visible) visible.click();
                }
            });

            requestAnimationFrame(() => inputBusqueda.focus());
        }
    };

    const posicionarPanel = () => {
        const r = trigger.getBoundingClientRect();
        const alturaPanel = Math.min(panel.scrollHeight || 320, 320);
        const espacioAbajo = window.innerHeight - r.bottom;
        const abrirArriba = espacioAbajo < alturaPanel + 12 && r.top > alturaPanel + 12;

        panel.style.width = r.width + 'px';
        panel.style.left  = r.left + 'px';
        panel.style.top   = abrirArriba
        ? (r.top - alturaPanel - 6) + 'px'
        : (r.bottom + 6) + 'px';
    };

    const abrir = () => {
        _cerrarTodosLosSelects(wrapper);
        pintarPanel();
        panel.classList.add('is-open');
        wrapper.classList.add('is-open');
        trigger.setAttribute('aria-expanded', 'true');
        requestAnimationFrame(posicionarPanel);
    };

    const cerrar = () => {
        panel.classList.remove('is-open');
        wrapper.classList.remove('is-open');
        trigger.setAttribute('aria-expanded', 'false');
    };

    trigger.addEventListener('click', (e) => {
        e.stopPropagation();
        if (wrapper.classList.contains('is-open')) cerrar();
        else abrir();
    });

        trigger.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') cerrar();
            if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                if (!wrapper.classList.contains('is-open')) abrir();
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && wrapper.classList.contains('is-open')) cerrar();
        });

            // Si el select cambia externamente, refrescar
            select.addEventListener('change', () => {
                pintarTrigger();
                pintarPanel();
            });

            // Reposicionar si el layout cambia mientras está abierto
            window.addEventListener('resize', () => {
                if (wrapper.classList.contains('is-open')) posicionarPanel();
            });

                pintarTrigger();
                _selectCustomActivos.add({ wrapper, close: cerrar });
}

function initCustomSelects() {
    document.querySelectorAll('.toolbar select, .modal-box select').forEach(initCustomSelect);
}

// Cerrar al hacer click fuera
document.addEventListener('click', (e) => {
    if (!e.target.closest('.select-custom') && !e.target.closest('.select-custom-panel')) {
        _cerrarTodosLosSelects(null);
    }
});
