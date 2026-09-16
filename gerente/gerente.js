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
    form.reset();
    form.querySelector('[name="action"]').value = 'crear';
    form.querySelector('[name="id_producto"]').value = '';
    document.getElementById('tituloModalProducto').textContent = 'Nuevo producto';
    document.getElementById('previewImagen').innerHTML = '';
    abrirModal('modalProducto');
}

function abrirModalEditar(btn) {
    const form = document.getElementById('formProducto');
    form.reset();
    form.querySelector('[name="action"]').value = 'editar';
    form.querySelector('[name="id_producto"]').value = btn.dataset.id;
    form.querySelector('[name="nombre"]').value = btn.dataset.nombre;
    form.querySelector('[name="descripcion"]').value = btn.dataset.descripcion;
    form.querySelector('[name="precio"]').value = btn.dataset.precio;
    form.querySelector('[name="id_categoria"]').value = btn.dataset.categoria;
    form.querySelector('[name="estado"]').value = btn.dataset.estado;

    const preview = document.getElementById('previewImagen');
    preview.innerHTML = btn.dataset.imagen
        ? `<img src="../${btn.dataset.imagen}" alt=""><span>Imagen actual (sube una nueva para reemplazarla)</span>`
        : '<span>Sin imagen actual</span>';

    document.getElementById('tituloModalProducto').textContent = 'Editar producto';
    abrirModal('modalProducto');
}

document.addEventListener('DOMContentLoaded', () => {
    const inputImagen = document.getElementById('inputImagen');
    if (inputImagen) {
        inputImagen.addEventListener('change', () => {
            const preview = document.getElementById('previewImagen');
            const archivo = inputImagen.files[0];
            if (!archivo) return;
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
