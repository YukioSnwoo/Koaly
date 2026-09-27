// =====================================================
// PRODUCTOS DE EJEMPLO
// Después puedes reemplazarlos por los productos
// que lleguen desde buscar_producto.php.
// =====================================================
const productosEjemplo = [
    {
        id_producto: "1042",
        codigo: "1042",
        nombre: "Paquete de 6 Pepsi",
        categoria: "Refresco",
        stock: 24,
        precio: 560,
        descuento: 0,
        estado: "Activo"
    },
    {
        id_producto: "1043",
        codigo: "1043",
        nombre: "Pepsi individual",
        categoria: "Refresco",
        stock: 12,
        precio: 18,
        descuento: 0,
        estado: "Activo"
    },
    {
        id_producto: "1044",
        codigo: "1044",
        nombre: "Pepsi colaboración",
        categoria: "Refresco",
        stock: 9,
        precio: 25,
        descuento: 5,
        estado: "Activo"
    },
    {
        id_producto: "2041",
        codigo: "2041",
        nombre: "Paquete de 24 Pepsi",
        categoria: "Refresco",
        stock: 18,
        precio: 980,
        descuento: 10,
        estado: "Activo"
    },
    {
        id_producto: "2042",
        codigo: "2042",
        nombre: "Paquete de 12 Pepsi",
        categoria: "Refresco",
        stock: 16,
        precio: 520,
        descuento: 0,
        estado: "Activo"
    },
    {
        id_producto: "3050",
        codigo: "3050",
        nombre: "Paquete de 6 7UP",
        categoria: "Refresco",
        stock: 20,
        precio: 290,
        descuento: 0,
        estado: "Activo"
    },
    {
        id_producto: "3051",
        codigo: "3051",
        nombre: "Paquete de 4 7UP",
        categoria: "Refresco",
        stock: 14,
        precio: 210,
        descuento: 0,
        estado: "Activo"
    },
    {
        id_producto: "4060",
        codigo: "4060",
        nombre: "Paquete de 6 Mirinda",
        categoria: "Refresco",
        stock: 11,
        precio: 260,
        descuento: 8,
        estado: "Activo"
    },
    {
        id_producto: "4061",
        codigo: "4061",
        nombre: "Paquete de 6 Squirt",
        categoria: "Refresco",
        stock: 8,
        precio: 275,
        descuento: 0,
        estado: "Activo"
    }
];

let productosActuales = [...productosEjemplo];
let modoBusqueda = "nombre";
let cuponActivo = false;

const cart = [];

const tbody = document.getElementById("tbodyProductos");
const inputBuscar = document.getElementById("inputBuscar");
const mensaje = document.getElementById("mensajeBusqueda");
const totalSpan = document.getElementById("totalProductos");
const toast = document.getElementById("toast");
const cartList = document.getElementById("cartList");

function showToast(text, type = "") {
    toast.textContent = text;
    toast.className = "toast visible " + type;

    setTimeout(() => {
        toast.classList.remove("visible");
    }, 2200);
}

function escapeHtml(valor) {
    return String(valor ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

function renderTabla(lista) {
    tbody.innerHTML = "";
    totalSpan.textContent = lista.length;

    if (lista.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="empty-row">
                    No se encontraron productos.
                </td>
            </tr>
        `;
        return;
    }

    lista.forEach(producto => {
        const tr = document.createElement("tr");

        tr.dataset.id = producto.id_producto;

        tr.innerHTML = `
            <td>${escapeHtml(producto.codigo)}</td>

            <td>
                <div class="prod-cell">
                    <div class="prod-emoji">📦</div>

                    <div>
                        <div class="prod-name">
                            ${escapeHtml(producto.nombre)}
                        </div>

                        <div class="prod-id">
                            ID: ${escapeHtml(producto.id_producto)}
                        </div>
                    </div>
                </div>
            </td>

            <td>${escapeHtml(producto.categoria)}</td>

            <td>${Number(producto.stock) || 0}</td>

            <td>
                $${Number(producto.precio || 0).toFixed(2)}
            </td>

            <td>
                ${Number(producto.descuento || 0).toFixed(2)}%
            </td>

            <td class="${
                producto.estado === "Activo"
                    ? "estado-activo"
                    : "estado-otro"
            }">
                ${escapeHtml(producto.estado)}
            </td>
        `;

        tr.addEventListener("click", () => {

            document
                .querySelectorAll("#tbodyProductos tr")
                .forEach(fila => {
                    fila.classList.remove("selected");
                });

            tr.classList.add("selected");

            addToCart(producto);
        });

        tbody.appendChild(tr);
    });
}

// =====================================================
// BÚSQUEDA
// Primero intenta buscar en PHP.
// Si PHP no está disponible, utiliza los productos
// de ejemplo para que puedas probar la interfaz.
// =====================================================
async function buscarProducto() {

    const q = inputBuscar.value.trim();

    if (q === "") {
        productosActuales = [...productosEjemplo];
        renderTabla(productosActuales);

        mensaje.textContent =
            "Productos de ejemplo. Busca por nombre, clave o ID.";

        return;
    }

    mensaje.textContent = "Buscando...";

    try {

        const response = await fetch(
            `buscar_producto.php?q=${encodeURIComponent(q)}&modo=${encodeURIComponent(modoBusqueda)}`,
            {
                cache: "no-store"
            }
        );

        if (!response.ok) {
            throw new Error("PHP no disponible");
        }

        const data = await response.json();

        productosActuales =
            Array.isArray(data.productos)
                ? data.productos
                : [];

        renderTabla(productosActuales);

        mostrarResultadoBusqueda(productosActuales);

    } catch (error) {

        // Si no existe buscar_producto.php o estás probando
        // solo el HTML, se usan los productos de ejemplo.
        buscarEnProductosEjemplo(q);
    }
}

function buscarEnProductosEjemplo(q) {

    const texto = q.toLowerCase();

    let resultados = [];

    if (modoBusqueda === "id") {

        resultados = productosEjemplo.filter(producto =>
            String(producto.id_producto)
                .toLowerCase()
                .includes(texto) ||
            String(producto.codigo)
                .toLowerCase()
                .includes(texto)
        );

    } else {

        resultados = productosEjemplo.filter(producto =>
            producto.nombre
                .toLowerCase()
                .includes(texto) ||
            String(producto.codigo)
                .toLowerCase()
                .includes(texto) ||
            String(producto.id_producto)
                .toLowerCase()
                .includes(texto)
        );
    }

    productosActuales = resultados;

    renderTabla(resultados);

    mostrarResultadoBusqueda(resultados);
}

function mostrarResultadoBusqueda(resultados) {

    if (resultados.length === 0) {

        mensaje.textContent =
            "No se encontraron productos.";

        showToast(
            "Producto no encontrado",
            "error"
        );

    } else if (resultados.length === 1) {

        const p = resultados[0];

        mensaje.textContent =
            `Encontrado: ${p.nombre} | ` +
            `Clave: ${p.codigo} | ` +
            `Cantidad: ${p.stock} | ` +
            `Precio: $${Number(p.precio).toFixed(2)} | ` +
            `Descuento: ${Number(p.descuento).toFixed(2)}%`;

    } else {

        mensaje.textContent =
            `Se encontraron ${resultados.length} productos.`;
    }
}

// =====================================================
// CARRITO
// =====================================================
function addToCart(producto) {

    const existente = cart.find(
        item =>
            String(item.id_producto) ===
            String(producto.id_producto)
    );

    if (existente) {

        const stock =
            Number(producto.stock) || 0;

        if (existente.quantity >= stock) {

            showToast(
                "No hay más existencias disponibles",
                "error"
            );

            return;
        }

        existente.quantity += 1;

    } else {

        cart.push({
            ...producto,
            quantity: 1
        });
    }

    renderCart();

    showToast(
        `${producto.nombre} agregado`,
        "success"
    );
}

function renderCart() {

    if (cart.length === 0) {

        cartList.innerHTML =
            '<p class="cart-empty">Selecciona un producto</p>';

    } else {

        cartList.innerHTML =
            cart.map(item => {

                const importe =
                    Number(item.precio) *
                    item.quantity;

                return `
                    <div class="cart-item">

                        <span>
                            ${item.quantity} x
                            ${escapeHtml(item.nombre)}
                        </span>

                        <strong>
                            $${importe.toFixed(2)}
                        </strong>

                    </div>
                `;

            }).join("");
    }

    calcularTotales();
}

// =====================================================
// TOTALES + CUPÓN DE EJEMPLO DEL 20%
// =====================================================
function calcularTotales() {

    const subtotal = cart.reduce(
        (sum, item) =>
            sum +
            Number(item.precio) *
            item.quantity,
        0
    );

    const descuentoProductos = cart.reduce(
        (sum, item) =>
            sum +
            Number(item.precio) *
            item.quantity *
            (Number(item.descuento) || 0) /
            100,
        0
    );

    const subtotalDespuesDescuento =
        subtotal - descuentoProductos;

    // Cupón general del 20% sobre el subtotal
    // después de los descuentos individuales.
    const descuentoCupon =
        cuponActivo
            ? subtotalDespuesDescuento * 0.20
            : 0;

    const baseImpuestos =
        subtotalDespuesDescuento -
        descuentoCupon;

    const impuestos =
        baseImpuestos * 0.16;

    const total =
        baseImpuestos +
        impuestos;

    const ahorroTotal =
        descuentoProductos +
        descuentoCupon;

    document.getElementById("subtotal")
        .textContent =
        `$${subtotal.toFixed(2)}`;

    document.getElementById("discount")
        .textContent =
        `$${descuentoProductos.toFixed(2)}`;

    document.getElementById("couponDiscount")
        .textContent =
        `$${descuentoCupon.toFixed(2)}`;

    document.getElementById("tax")
        .textContent =
        `$${impuestos.toFixed(2)}`;

    document.getElementById("savings")
        .textContent =
        `$${ahorroTotal.toFixed(2)}`;

    document.getElementById("total")
        .textContent =
        `$${total.toFixed(2)}`;
}

// =====================================================
// FORMULARIO DE BÚSQUEDA
// =====================================================
document
    .getElementById("searchForm")
    .addEventListener(
        "submit",
        event => {

            event.preventDefault();

            buscarProducto();
        }
    );

let timerBusqueda = null;

inputBuscar.addEventListener(
    "input",
    () => {

        clearTimeout(timerBusqueda);

        timerBusqueda =
            setTimeout(
                buscarProducto,
                250
            );
    }
);

// =====================================================
// BOTONES LATERALES
// =====================================================
document
    .querySelectorAll(".side-button")
    .forEach(btn => {

        btn.addEventListener(
            "click",
            () => {

                const action =
                    btn.dataset.action;

                // Cupón 20%
                if (action === "coupon") {

                    if (cart.length === 0) {
                        showToast(
                            "Agrega un producto antes de usar el cupón",
                            "error"
                        );
                        return;
                    }

                    cuponActivo = !cuponActivo;

                    renderCart();

                    if (cuponActivo) {

                        btn.classList.add("active");

                        showToast(
                            "Cupón del 20% aplicado",
                            "success"
                        );

                    } else {

                        btn.classList.remove("active");

                        showToast(
                            "Cupón eliminado"
                        );
                    }

                    return;
                }

                // Tarjeta de puntos
                if (action === "points") {

                    showToast(
                        "Función disponible próximamente"
                    );

                    return;
                }

                document
                    .querySelectorAll(
                        '.side-button[data-action="search"], .side-button[data-action="key"]'
                    )
                    .forEach(b => {
                        b.classList.remove("active");
                    });

                btn.classList.add("active");

                if (action === "search") {

                    modoBusqueda =
                        "nombre";

                    inputBuscar.placeholder =
                        "Buscar producto, clave o ID";

                } else if (
                    action === "key"
                ) {

                    modoBusqueda =
                        "id";

                    inputBuscar.placeholder =
                        "Buscar únicamente por ID o clave";
                }

                inputBuscar.focus();
            }
        );
    });

// =====================================================
// LIMPIAR
// =====================================================
document
    .getElementById("btnLimpiar")
    .addEventListener(
        "click",
        () => {

            inputBuscar.value = "";

            productosActuales =
                [...productosEjemplo];

            cart.length = 0;

            cuponActivo = false;

            const btnCupon =
                document.querySelector(
                    '[data-action="coupon"]'
                );

            if (btnCupon) {
                btnCupon.classList.remove("active");
            }

            renderTabla(productosActuales);

            renderCart();

            mensaje.textContent =
                "Productos de ejemplo. Busca por nombre, clave o ID.";

            inputBuscar.focus();

            showToast(
                "Lista limpiada"
            );
        }
    );

// =====================================================
// ELIMINAR ÚLTIMO PRODUCTO
// =====================================================
document
    .getElementById("btnEliminar")
    .addEventListener(
        "click",
        () => {

            if (cart.length === 0) {

                showToast(
                    "No hay productos en el carrito",
                    "error"
                );

                return;
            }

            cart.pop();

            if (cart.length === 0) {
                cuponActivo = false;

                const btnCupon =
                    document.querySelector(
                        '[data-action="coupon"]'
                    );

                if (btnCupon) {
                    btnCupon.classList.remove("active");
                }
            }

            renderCart();

            showToast(
                "Último producto eliminado"
            );
        }
    );

// =====================================================
// PAGAR
// =====================================================
document
    .getElementById("payButton")
    .addEventListener(
        "click",
        () => {

            if (cart.length === 0) {

                showToast(
                    "Agrega productos primero",
                    "error"
                );

                return;
            }

            showToast(
                `Total a pagar: ${
                    document
                        .getElementById("total")
                        .textContent
                }`,
                "success"
            );
        }
    );

// Mostrar productos de ejemplo al abrir la página.
renderTabla(productosEjemplo);
renderCart();
