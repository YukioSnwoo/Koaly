<?php
/**
 * Aviso de privacidad para clientes (español e inglés). Solo se consulta, no
 * se acepta: la dirección viene al final del ticket (cajero/index.php: htmlTicket)
 * y se abre en privacidad/?aviso=cliente.
 *
 * Dice solo lo que el sistema hace: una venta guarda productos, montos, método
 * de pago, fecha, sucursal, caja y cajero, y ningún dato del cliente (tabla
 * Ventas). Si algún día se piden datos del cliente (factura, programa de
 * puntos, compras a domicilio), se agregan aquí y se sube AVISO_CLIENTE_VERSION.
 *
 * Misma estructura que los demás avisos: [título => [bloques]].
 *
 * @param string $contacto correo de la empresa, o '' si no hay
 */
function seccionesAvisoCliente(string $contacto, string $idioma = 'es'): array
{
    if ($idioma === 'en') {
        return seccionesAvisoClienteEn($contacto);
    }

    $escribeA = str_contains($contacto, '@')
        ? "escribe a {$contacto}"
        : 'pregunta en caja por el correo de contacto de la tienda';

    return [
        '1. Quién es responsable' => [
            'Koaly, a través de la sucursal donde hiciste tu compra (su nombre y domicilio vienen en tu '
            . 'ticket), es responsable de la información que se registra en nuestras tiendas, conforme a la '
            . 'Ley Federal de Protección de Datos Personales en Posesión de los Particulares.',
            "Para cualquier duda sobre este aviso, {$escribeA}.",
        ],
        '2. Qué datos tratamos' => [
            'Para venderte no te pedimos datos personales. En caja no registramos tu nombre, correo, '
            . 'teléfono ni los datos de tu tarjeta.',
            [
                'Tu compra' => ['Productos, cantidades, precios y total; forma de pago (efectivo, tarjeta o '
                    . 'combinado) y montos; fecha y hora, sucursal, caja y el cajero que te atendió.',
                    'el personal de la tienda y la administración. Estos datos no te identifican.'],
                'Pago con tarjeta' => ['Lo procesa la terminal del banco. La tienda no ve ni guarda el '
                    . 'número de tu tarjeta: solo el monto que se cobró.',
                    'el banco de tu tarjeta y el que opera la terminal, bajo sus propios avisos de privacidad.'],
                'Pantalla del cliente' => ['Mientras te cobramos, el monitor de la caja muestra los productos '
                    . 'de tu compra y el total. No toma fotos ni guarda nada.',
                    'tú y quien esté en la caja.'],
            ],
        ],
        '3. Para qué usamos la información de tu compra' => [
            [
                'Emitir tu ticket.',
                'Llevar el control de la caja (cortes y arqueos), el inventario y la contabilidad de la tienda.',
                'Cumplir nuestras obligaciones fiscales.',
                'Atender una aclaración sobre tu compra si nos la pides (ten a la mano tu ticket).',
            ],
            'No usamos esta información para publicidad ni para hacer perfiles de compra de personas.',
        ],
        '4. Si nos das datos para una aclaración' => [
            'Si para atender una aclaración nos das tu nombre, correo o teléfono, solo los usamos para '
            . 'responderte y los borramos cuando se resuelve.',
        ],
        '5. Con quién compartimos la información' => [
            'No vendemos ni rentamos información. Solo la conocen el personal autorizado de la empresa y:',
            [
                'El proveedor de alojamiento web donde funciona el sistema, que está obligado a protegerla.',
                'Las autoridades que la ley indique (por ejemplo el SAT), solo cuando estemos obligados.',
            ],
        ],
        '6. Cuánto tiempo la conservamos' => [
            'Las ventas se conservan como parte de la contabilidad de la tienda, el tiempo que exigen las '
            . 'leyes fiscales.',
        ],
        '7. Tus derechos (ARCO)' => [
            'Puedes acceder a tus datos, rectificarlos, cancelarlos u oponerte a su uso, y revocar tu '
            . 'consentimiento. Como en caja no registramos datos que te identifiquen, normalmente no habrá '
            . "nada a tu nombre. Si nos diste datos para una aclaración, {$escribeA}: te responderemos en un "
            . 'máximo de 20 días hábiles.',
            'Si no te respondemos o no estás de acuerdo con la respuesta, puedes acudir a la Secretaría '
            . 'Anticorrupción y Buen Gobierno, la autoridad de protección de datos personales.',
        ],
        '8. Esta página' => [
            'Esta página solo usa cookies técnicas: una para funcionar y otra que recuerda el idioma que '
            . 'elegiste. No usamos cookies de publicidad ni de rastreo.',
        ],
        '9. Cambios a este aviso' => [
            'Si este aviso cambia, publicaremos la versión nueva en esta misma página. La fecha de la '
            . 'versión vigente está al inicio.',
        ],
    ];
}

/** Traducción al inglés; si difiere del español, prevalece el español (lo dice la sección 1). */
function seccionesAvisoClienteEn(string $contacto): array
{
    $escribeA = str_contains($contacto, '@')
        ? "write to {$contacto}"
        : 'ask at the register for the store\'s contact email';

    return [
        '1. Who is responsible' => [
            'Koaly, through the branch where you made your purchase (its name and address are on your '
            . 'receipt), is responsible for the information recorded in our stores, under the Mexican '
            . 'Federal Law on the Protection of Personal Data Held by Private Parties.',
            "If you have any questions about this notice, {$escribeA}.",
            'This English version is a translation provided for your convenience. If it differs from '
            . 'the Spanish version, the Spanish version prevails.',
        ],
        '2. What data we process' => [
            'We do not ask for personal data to sell to you. At the register we do not record your name, '
            . 'email, phone number or card details.',
            [
                'Your purchase' => ['Products, quantities, prices and total; payment method (cash, card or '
                    . 'combined) and amounts; date and time, branch, register and the cashier who served you.',
                    'store staff and the administration. This data does not identify you.'],
                'Card payment' => ['It is processed by the bank\'s terminal. The store does not see or keep '
                    . 'your card number: only the amount charged.',
                    'your card\'s bank and the one that runs the terminal, under their own privacy notices.'],
                'Customer display' => ['While we ring you up, the register\'s monitor shows the products of '
                    . 'your purchase and the total. It does not take pictures or store anything.',
                    'you and whoever is at the register.'],
            ],
        ],
        '3. What we use your purchase information for' => [
            [
                'Issuing your receipt.',
                'Keeping track of the register (closings and counts), the inventory and the store\'s accounting.',
                'Meeting our tax obligations.',
                'Handling a question about your purchase if you ask us (keep your receipt at hand).',
            ],
            'We do not use this information for advertising or to build people\'s shopping profiles.',
        ],
        '4. If you give us data for a question' => [
            'If you give us your name, email or phone number so we can handle a question, we only use it '
            . 'to answer you and delete it once it is solved.',
        ],
        '5. Who we share the information with' => [
            'We do not sell or rent information. Only authorized company staff know it, plus:',
            [
                'The web hosting provider where the system runs, which is required to protect it.',
                'The authorities required by law (for example the SAT), only when we are obliged to.',
            ],
        ],
        '6. How long we keep it' => [
            'Sales are kept as part of the store\'s accounting, for as long as tax laws require.',
        ],
        '7. Your rights (ARCO)' => [
            'You can access your data, correct it, cancel it or object to its use, and withdraw your '
            . 'consent. Since we do not record data that identifies you at the register, there will '
            . "usually be nothing under your name. If you gave us data for a question, {$escribeA}: we will "
            . 'answer within 20 business days.',
            'If we do not answer or you disagree with the answer, you can go to the Secretaría '
            . 'Anticorrupción y Buen Gobierno, the personal data protection authority.',
        ],
        '8. This page' => [
            'This page only uses technical cookies: one to work and one that remembers the language you '
            . 'chose. We do not use advertising or tracking cookies.',
        ],
        '9. Changes to this notice' => [
            'If this notice changes, we will publish the new version on this same page. The date of the '
            . 'current version is at the top.',
        ],
    ];
}
