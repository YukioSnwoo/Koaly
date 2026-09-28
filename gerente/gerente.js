/* ============================================================
   Modal helpers (usados por productos.php)
   ============================================================ */
function abrirModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.add('open');
}

function cerrarModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.remove('open');
}

document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('open');
    }
});

function abrirModalNuevo() {
    const form = document.getElementById('formProducto');
    if (!form) return;
    form.reset();
    form.querySelector('[name="action"]').value = 'crear';
    form.querySelector('[name="id_producto"]').value = '';
    const titulo = document.getElementById('tituloModalProducto');
    if (titulo) titulo.textContent = 'Nuevo producto';
    const preview = document.getElementById('previewImagen');
    if (preview) preview.innerHTML = '';
    abrirModal('modalProducto');
}

function abrirModalEditar(btn) {
    const form = document.getElementById('formProducto');
    if (!form) return;
    form.reset();
    form.querySelector('[name="action"]').value = 'editar';
    form.querySelector('[name="id_producto"]').value = btn.dataset.id;
    form.querySelector('[name="codigo"]').value = btn.dataset.codigo;
    form.querySelector('[name="nombre"]').value = btn.dataset.nombre;
    form.querySelector('[name="descripcion"]').value = btn.dataset.descripcion;
    form.querySelector('[name="precio"]').value = btn.dataset.precio;
    form.querySelector('[name="id_categoria"]').value = btn.dataset.categoria;
    form.querySelector('[name="cantidad_inventario"]').value = btn.dataset.cantidad;

    const preview = document.getElementById('previewImagen');
    if (preview) {
        preview.innerHTML = btn.dataset.imagen
            ? `<img src="../${btn.dataset.imagen}" alt=""><span>Imagen actual (sube una nueva para reemplazarla)</span>`
            : '<span>Sin imagen actual</span>';
    }

    const titulo = document.getElementById('tituloModalProducto');
    if (titulo) titulo.textContent = 'Editar producto';
    abrirModal('modalProducto');
}

/* ============================================================
   Cursor personalizado
   ============================================================ */
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

    const interactivo = 'a, button, input, select, textarea, label, [role="button"]';
    document.addEventListener('mouseover', (e) => {
        if (e.target.closest(interactivo)) dot.classList.add('is-hover');
    });
    document.addEventListener('mouseout', (e) => {
        if (e.target.closest(interactivo)) dot.classList.remove('is-hover');
    });

    document.addEventListener('mouseleave', () => document.body.classList.add('cursor-outside'));
    document.addEventListener('mouseenter', () => document.body.classList.remove('cursor-outside'));

    render(); // posiciona de inmediato en el centro
}

/* ============================================================
   Arranque
   ============================================================ */
document.addEventListener('DOMContentLoaded', () => {
    initCustomCursor();

    const inputImagen = document.getElementById('inputImagen');
    if (inputImagen) {
        inputImagen.addEventListener('change', () => {
            const preview = document.getElementById('previewImagen');
            const archivo = inputImagen.files[0];
            if (!archivo || !preview) return;
            const url = URL.createObjectURL(archivo);
            preview.innerHTML = `<img src="${url}" alt=""><span>${archivo.name}</span>`;
        });
    }

    const buscador = document.getElementById('buscarProducto');
    if (buscador) {
        buscador.addEventListener('input', () => {
            const q = buscador.value.trim().toLowerCase();
            document.querySelectorAll('#tablaProductos tbody tr[data-nombre]').forEach(row => {
                row.style.display = row.dataset.nombre.includes(q) ? '' : 'none';
            });
        });
    }
});