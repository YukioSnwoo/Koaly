<?php
// Textos de la caja — English.
// es.php y en.php deben tener exactamente las mismas claves.
// Plurales: [singular, plural]. Variables: {nombre}.
return [

    // página y encabezado
    'caja.titulo' => 'Checkout',
    'caja.cajero' => 'Cashier',
    'caja.sucursal' => 'Branch',
    'caja.sin_asignar' => 'Unassigned',
    'tema.a_claro' => 'Switch to light mode',
    'tema.a_oscuro' => 'Switch to dark mode',
    'idioma.cambiar' => 'Change language',
    'sesion.cerrar' => 'Log out',
    'sesion.pregunta' => 'Do you want to log out?',
    'sesion.quedarme' => 'No, stay',
    'sesion.confirmar' => 'Yes, log out',

    // común
    'comun.total' => 'Total',
    'comun.cerrar' => 'Close',
    'comun.cancelar' => 'Cancel',
    'comun.aceptar' => 'OK',
    'comun.atras' => 'Back',
    'comun.entendido' => 'Got it',
    'comun.limpiar' => 'Clear',
    'comun.agotado' => 'Out of stock',
    'comun.n_productos' => ['1 product', '{n} products'],
    'comun.n_articulos' => ['1 item', '{n} items'],
    'comun.n_unidades' => ['{n} unit', '{n} units'],
    'comun.n_disponibles' => ['{n} available', '{n} available'],

    // buscador
    'buscar.placeholder' => 'Search by name or code...',
    'buscar.clave' => 'Code',
    'buscar.existencias' => 'In stock: {n}',
    'buscar.sin_resultados' => 'No results for "{q}".',
    'buscar.sin_conexion' => 'Could not connect to the server.',
    'buscar.sin_conexion_alerta' => 'Could not connect to the server to search for the product.',

    // catálogo
    'catalogo.titulo' => 'Products',
    'catalogo.cargando' => 'Loading products...',
    'catalogo.error' => 'Could not load the catalog. You can use the search box.',
    'catalogo.vacio' => 'There are no active products.',
    'catalogo.quitar_uno' => 'Remove one',
    'catalogo.quitar_uno_de' => 'Remove one {p}',
    'catalogo.agregar_aria' => 'Add {p}, {precio}',
    'catalogo.en_venta_aria' => ', {n} in the sale',
    'catalogo.agotado_aria' => ', out of stock',

    // existencias
    'stock.quedan' => '{n} left',
    'stock.solo_hay' => 'Only {n}',
    'stock.solo_hay_existencia' => 'Only {n} in stock',

    // lista de venta
    'lista.titulo' => 'Sale list',
    'lista.ayuda' => 'Select a product and press {supr} or {borrar} to remove it.',
    'lista.tecla_supr' => 'Del',
    'lista.col_clave' => 'Code',
    'lista.col_producto' => 'Product',
    'lista.col_cantidad' => 'Quantity',
    'lista.col_precio' => 'Price',
    'lista.col_importe' => 'Amount',
    'lista.col_acciones' => 'Actions',
    'lista.vacia' => 'Add products from the catalog or the search box',
    'lista.quitar_producto' => 'Remove product',
    'lista.agregar_uno' => 'Add one',
    'lista.cantidad_de' => 'Quantity of {p}',
    'lista.eliminar_producto' => 'Delete product',
    'lista.eliminar_de' => 'Delete {p}',

    // panel derecho
    'resumen.escaneo' => 'Scanned',
    'resumen.resumen' => 'Summary',
    'resumen.articulos' => 'Items',
    'resumen.pagar' => 'Pay',
    'resumen.sin_productos' => 'No products',
    'resumen.precio_unidad' => '{precio} each',

    // confirmaciones
    'eliminar.titulo' => 'Delete product?',
    'eliminar.confirmar' => 'Yes, delete',
    'vaciar.titulo' => 'Clear the sale?',
    'vaciar.mensaje' => '{productos} ({articulos}) will be removed from the current sale.',
    'vaciar.confirmar' => 'Yes, clear',

    // pago
    'pago.titulo' => 'Payment method',
    'pago.total' => 'Amount due',
    'pago.efectivo' => 'Cash',
    'pago.tarjeta' => 'Card',
    'pago.verificando' => 'Checking stock...',
    'pago.sin_productos' => 'There are no products to charge.',
    'pago.guardando' => 'Saving...',
    'efectivo.titulo' => 'Cash payment',
    'efectivo.recibido' => 'Cash received',
    'efectivo.cobrar' => 'Charge',
    'efectivo.faltan' => '{monto} short',
    'efectivo.cambio' => 'Change',

    // venta completada
    'venta.titulo' => 'Sale completed',
    'venta.mensaje' => 'Sale #{folio} recorded for {total}, paid by {metodo}.',
    'venta.detalle' => 'Received: {recibido} · Total: {total}',
    'venta.imprimir' => 'Print receipt',

    // avisos
    'aviso.agotado_titulo' => 'Out of stock',
    'aviso.agotado' => '"{p}" is out of stock at this branch and can\'t be added to the sale.',
    'aviso.insuficiente_titulo' => 'Not enough stock',
    'aviso.insuficiente' => 'You asked for {pedida} of "{p}" in total, but only {disponibles}.',
    'aviso.sin_existencias' => '"{p}" is out of stock at this branch.',
    'aviso.faltantes' => 'The sale can\'t be completed with these quantities:',
    'aviso.pides' => 'You want {pedida}, {disponibles}',
    'aviso.revisar' => 'Review',
    'aviso.ajustar' => 'Adjust to available stock',
    'aviso.quedan_solo' => 'Only {unidades} of {p} left.',
    'aviso.ajustadas' => 'Quantities adjusted to available stock.',
    'aviso.ajustadas_quitados' => ['Quantities adjusted. 1 out-of-stock product was removed.', 'Quantities adjusted. {n} out-of-stock products were removed.'],

    // errores al cobrar
    'error.sin_conexion_titulo' => 'No connection',
    'error.sin_conexion' => 'Could not connect to the server. The sale was NOT recorded.',
    'error.registrar_titulo' => 'The sale could not be recorded',
    'error.registrar' => 'An error occurred while saving the sale.',

    // mensajes del servidor (registrar_venta.php)
    'servidor.metodo_no_permitido' => 'Method not allowed',
    'servidor.sesion_expirada' => 'Your session expired. Please log in again.',
    'servidor.token_invalido' => 'Invalid security token. Reload the page.',
    'servidor.cuenta_inactiva' => 'Your account or branch is not active.',
    'servidor.metodo_invalido' => 'Invalid payment method.',
    'servidor.venta_vacia' => 'The sale has no products.',
    'servidor.cantidad_invalida' => 'Some products have an invalid quantity.',
    'servidor.producto_no_disponible' => 'One of the products is no longer available. Remove it from the sale.',
    'servidor.existencias_insuficientes' => 'Not enough stock of "{p}" at this branch ({n} left).',
    'servidor.efectivo_insuficiente' => 'The cash received doesn\'t cover the total of {total}.',
    'servidor.existencias_cambiaron' => 'Stock changed while charging. Please try again.',
    'servidor.error_bd' => 'The sale could not be saved to the database.',

    // ticket
    'ticket.caja_n' => 'Register {n}',
    'ticket.sucursal' => '{s} branch',
    'ticket.tel' => 'Tel. {t}',
    'ticket.folio' => 'Receipt no.',
    'ticket.fecha' => 'Date',
    'ticket.hora' => 'Time',
    'ticket.caja' => 'Register',
    'ticket.atendio' => 'Cashier',
    'ticket.articulos' => 'Items',
    'ticket.subtotal' => 'Subtotal',
    'ticket.iva' => 'VAT 16%',
    'ticket.total' => 'TOTAL',
    'ticket.forma_pago' => 'Payment',
    'ticket.efectivo_recibido' => 'Cash received',
    'ticket.cambio' => 'Change',
    'ticket.iva_incluido' => 'Prices include VAT',
    'ticket.gracias' => 'Thank you for your purchase!',
];
