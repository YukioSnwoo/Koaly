// Productos exactos del código de César
const productos = [
    { id: "1042", nombre: "Paquete de 6 Pepsi", categoria: "Refresco", stock: 24, precio: 560, descuento: 0, estado: "Activo", emoji: "P" },
    { id: "1043", nombre: "Pepsi individual", categoria: "Refresco", stock: 12, precio: 18, descuento: 0, estado: "Activo", emoji: "P" },
    { id: "1044", nombre: "Pepsi colaboración", categoria: "Refresco", stock: 9, precio: 25, descuento: 5, estado: "Activo", emoji: "P" },
    { id: "2041", nombre: "Paquete de 24 Pepsi", categoria: "Refresco", stock: 18, precio: 980, descuento: 10, estado: "Activo", emoji: "P" },
    { id: "2042", nombre: "Paquete de 12 Pepsi", categoria: "Refresco", stock: 16, precio: 520, descuento: 0, estado: "Activo", emoji: "P" },
    { id: "3050", nombre: "Paquete de 6 7UP", categoria: "Refresco", stock: 20, precio: 290, descuento: 0, estado: "Activo", emoji: "7" },
    { id: "3051", nombre: "Paquete de 4 7UP", categoria: "Refresco", stock: 14, precio: 210, descuento: 0, estado: "Activo", emoji: "7" },
    { id: "4060", nombre: "Paquete de 6 Mirinda", categoria: "Refresco", stock: 11, precio: 260, descuento: 8, estado: "Activo", emoji: "M" },
    { id: "4061", nombre: "Paquete de 6 Squirt", categoria: "Refresco", stock: 8, precio: 275, descuento: 0, estado: "Activo", emoji: "S" }
];

let productosFiltrados = [...productos];
let modoBusqueda = "nombre";
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
    setTimeout(() => toast.classList.remove("visible"), 2500);
}

function renderTabla(lista) {
    tbody.innerHTML = "";
    totalSpan.textContent = lista.length;

    if (lista.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;padding:28px;color:#819080;">No se encontraron productos</td></tr>`;
        return;
    }

    lista.forEach((p, i) => {
        const tr = document.createElement("tr");
        tr.dataset.id = p.id;
        tr.innerHTML = `
            <td>
                <div class="prod-cell">
                    <div class="prod-emoji">${p.emoji}</div>
                    <div>
                        <div class="prod-name">${p.nombre}</div>
                        <div class="prod-id">Clave: ${p.id}</div>
                    </div>
                </div>
            </td>
            <td>${p.categoria}</td>
            <td>${p.stock}</td>
            <td>$${p.precio.toFixed(2)}</td>
            <td class="${p.estado === "Activo" ? "estado-activo" : "estado-otro"}">${p.estado}</td>
        `;
        tr.addEventListener("click", () => {
            document.querySelectorAll("#tbodyProductos tr").forEach(r => r.classList.remove("selected"));
            tr.classList.add("selected");
            addToCart(p.id);
        });
        tbody.appendChild(tr);
    });
}

function addToCart(id) {
    const product = productos.find(p => p.id === id);
    if (!product) return;

    const item = cart.find(e => e.id === id);
    if (item) {
        item.quantity += 1;
    } else {
        cart.push({ ...product, quantity: 1 });
    }
    renderCart();
    showToast(`${product.nombre} agregado`, "success");
}

function renderCart() {
    if (!cart.length) {
        cartList.innerHTML = '<p class="cart-empty">Selecciona un producto</p>';
    } else {
        cartList.innerHTML = cart.map(item =>
            `<div class="cart-item"><span>${item.quantity} x ${item.nombre}</span><strong>$${(item.precio * item.quantity).toFixed(2)}</strong></div>`
        ).join('');
    }

    const subtotal = cart.reduce((sum, item) => sum + item.precio * item.quantity, 0);
    const discount = cart.reduce((sum, item) => sum + item.precio * item.quantity * item.descuento / 100, 0);
    const tax = (subtotal - discount) * 0.16;
    const total = subtotal - discount + tax;

    document.getElementById("subtotal").textContent = `$${subtotal.toFixed(2)}`;
    document.getElementById("discount").textContent = `$${discount.toFixed(2)}`;
    document.getElementById("tax").textContent = `$${tax.toFixed(2)}`;
    document.getElementById("savings").textContent = `$${discount.toFixed(2)}`;
    document.getElementById("total").textContent = `$${total.toFixed(2)}`;
}

function buscar(e) {
    if (e) e.preventDefault();
    const texto = inputBuscar.value.trim().toLowerCase();

    if (texto === "") {
        productosFiltrados = [...productos];
        renderTabla(productosFiltrados);
        mensaje.textContent = "Campo vacío → se muestran todos los productos";
        return;
    }

    let resultados;
    if (modoBusqueda === "id") {
        resultados = productos.filter(p => p.id.toLowerCase().includes(texto));
    } else {
        resultados = productos.filter(p =>
            p.nombre.toLowerCase().includes(texto) || p.id.toLowerCase().includes(texto)
        );
    }

    productosFiltrados = resultados;
    renderTabla(resultados);

    if (resultados.length === 0) {
        mensaje.textContent = "❌ Producto no existe";
        showToast("Producto no existe", "error");
    } else if (resultados.length === 1) {
        const p = resultados[0];
        mensaje.textContent = `✅ Encontrado: ${p.nombre} | Clave: ${p.id} | Stock: ${p.stock} | Precio: $${p.precio} | Descuento: ${p.descuento}%`;
        showToast("Producto encontrado", "success");
    } else {
        mensaje.textContent = `⚠️ Se encontraron ${resultados.length} productos similares`;
        showToast(`${resultados.length} productos similares`);
    }
}

document.getElementById("searchForm").addEventListener("submit", buscar);
inputBuscar.addEventListener("input", buscar);

document.querySelectorAll(".side-button").forEach(btn => {
    btn.addEventListener("click", () => {
        document.querySelectorAll(".side-button").forEach(b => b.classList.remove("active"));
        btn.classList.add("active");
        const action = btn.dataset.action;
        if (action === "search") {
            modoBusqueda = "nombre";
            inputBuscar.placeholder = "Buscar producto (nombre)";
        } else if (action === "key") {
            modoBusqueda = "id";
            inputBuscar.placeholder = "Buscar por ID o clave";
        }
        inputBuscar.focus();
    });
});

document.getElementById("btnLimpiar").addEventListener("click", () => {
    inputBuscar.value = "";
    productosFiltrados = [...productos];
    cart.length = 0;
    renderTabla(productosFiltrados);
    renderCart();
    mensaje.textContent = "Lista limpiada";
    showToast("Limpiado");
});

document.getElementById("btnEliminar").addEventListener("click", () => {
    if (!cart.length) {
        showToast("No hay productos en el carrito", "error");
        return;
    }
    cart.pop();
    renderCart();
    showToast("Último producto eliminado");
});

document.getElementById("btnTabla").addEventListener("click", () => {
    showToast("Vista lista (tabla) activa", "success");
});

document.getElementById("payButton").addEventListener("click", () => {
    if (!cart.length) {
        showToast("Agrega productos primero", "error");
        return;
    }
    showToast(`Total a pagar: ${document.getElementById("total").textContent}`, "success");
});

renderTabla(productos);
renderCart();
