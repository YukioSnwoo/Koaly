const productos = [
	{ clave: '1042', nombre: 'Paquete de 6 Pepsi', cantidad: 24, precio: 560, descuento: 0, color: 'pepsi', icon: 'P' },
	{ clave: '1043', nombre: 'Pepsi individual', cantidad: 12, precio: 18, descuento: 0, color: 'pepsi-light', icon: 'P' },
	{ clave: '1044', nombre: 'Pepsi colaboración', cantidad: 9, precio: 25, descuento: 5, color: 'cola', icon: 'P' },
	{ clave: '2041', nombre: 'Paquete de 24 Pepsi', cantidad: 18, precio: 980, descuento: 10, color: 'pepsi', icon: 'P' },
	{ clave: '2042', nombre: 'Paquete de 12 Pepsi', cantidad: 16, precio: 520, descuento: 0, color: 'cola', icon: 'P' },
	{ clave: '3050', nombre: 'Paquete de 6 7UP', cantidad: 20, precio: 290, descuento: 0, color: 'seven', icon: '7' },
	{ clave: '3051', nombre: 'Paquete de 4 7UP', cantidad: 14, precio: 210, descuento: 0, color: 'seven', icon: '7' },
	{ clave: '4060', nombre: 'Paquete de 6 Mirinda', cantidad: 11, precio: 260, descuento: 8, color: 'mirinda', icon: 'M' },
	{ clave: '4061', nombre: 'Paquete de 6 Squirt', cantidad: 8, precio: 275, descuento: 0, color: 'squirt', icon: 'S' }
];

const form = document.getElementById('searchForm');
const input = document.getElementById('productSearch');
const productGrid = document.getElementById('productGrid');
const emptyState = document.getElementById('emptyState');
const cartList = document.getElementById('cartList');
const cart = [];

function showMessage(message) {
	emptyState.textContent = message;
}

function renderProducts(foundProducts) {
	productGrid.innerHTML = foundProducts.map(product => `
		<button class="product-card" type="button" data-key="${product.clave}">
			<div class="product-art ${product.color}"><span>${product.icon}</span></div>
			<strong>${product.nombre}</strong>
			<small>Clave ${product.clave} · ${product.cantidad} disponibles</small>
			<b>$${product.precio.toFixed(2)}</b>
		</button>
	`).join('');
	emptyState.hidden = foundProducts.length > 0;
	productGrid.hidden = foundProducts.length === 0;
	productGrid.querySelectorAll('.product-card').forEach(card => {
		card.addEventListener('click', () => addToCart(card.dataset.key));
	});
}

function searchProducts(searchTerm) {
	const normalizedTerm = searchTerm.trim().toLowerCase();

	if (!normalizedTerm) {
		renderProducts(productos);
		showMessage('Escribe un nombre o clave para filtrar el catálogo.');
		return;
	}

	const foundProducts = productos.filter(product =>
		product.clave.toLowerCase() === normalizedTerm ||
		product.nombre.toLowerCase().includes(normalizedTerm)
	);

	if (!foundProducts.length) {
		showMessage('No encontramos productos con ese nombre o clave.');
		renderProducts([]);
		return;
	}

	renderProducts(foundProducts);
}

form.addEventListener('submit', event => {
	event.preventDefault();
	searchProducts(input.value);
});

input.addEventListener('input', () => searchProducts(input.value));

function addToCart(clave) {
	const product = productos.find(item => item.clave === clave);
	const item = cart.find(entry => entry.clave === clave);
	if (item) {
		item.quantity += 1;
	} else {
		cart.push({ ...product, quantity: 1 });
	}
	renderCart();
	showToast(`${product.nombre} agregado`);
}

function renderCart() {
	if (!cart.length) {
		cartList.innerHTML = '<p class="cart-empty">Selecciona un producto</p>';
	} else {
		cartList.innerHTML = cart.map(item => `<div class="cart-item"><span>${item.quantity} x ${item.nombre}</span><strong>$${(item.precio * item.quantity).toFixed(2)}</strong></div>`).join('');
	}
	const subtotal = cart.reduce((sum, item) => sum + item.precio * item.quantity, 0);
	const discount = cart.reduce((sum, item) => sum + item.precio * item.quantity * item.descuento / 100, 0);
	const tax = (subtotal - discount) * 0.16;
	const total = subtotal - discount + tax;
	document.getElementById('subtotal').textContent = `$${subtotal.toFixed(2)}`;
	document.getElementById('discount').textContent = `$${discount.toFixed(2)}`;
	document.getElementById('tax').textContent = `$${tax.toFixed(2)}`;
	document.getElementById('savings').textContent = `$${discount.toFixed(2)}`;
	document.getElementById('total').textContent = `$${total.toFixed(2)}`;
}

function showToast(message) {
	const toast = document.getElementById('toast');
	toast.textContent = message;
	toast.classList.add('visible');
	setTimeout(() => toast.classList.remove('visible'), 1800);
}

document.getElementById('clearCart').addEventListener('click', () => {
	cart.length = 0;
	renderCart();
	showToast('Venta limpiada');
});

document.getElementById('clearSearch').addEventListener('click', () => {
	input.value = '';
	renderProducts(productos);
	showMessage('Catálogo completo');
	input.focus();
});

document.querySelectorAll('.side-button').forEach(button => {
	button.addEventListener('click', () => {
		document.querySelectorAll('.side-button').forEach(item => item.classList.remove('active'));
		button.classList.add('active');
		if (button.dataset.action === 'key') {
			input.focus();
			showToast('Escribe la clave del producto');
		} else if (button.dataset.action === 'coupon' || button.dataset.action === 'points') {
			showToast('Función disponible próximamente');
		}
	});
});

document.getElementById('payButton').addEventListener('click', () => {
	showToast(cart.length ? 'Venta lista para cobrar' : 'Agrega productos primero');
});

renderProducts(productos);
renderCart();
