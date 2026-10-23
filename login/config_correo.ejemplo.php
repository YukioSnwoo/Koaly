<?php
/**
 * Cuenta con la que salen los correos del sitio (códigos de recuperación,
 * avisos al empleado y constancias del aviso de privacidad).
 *
 * Copia este archivo como login/config_correo.php (NO se sube a git) y llena
 * los datos.
 *
 * Opción A, recomendada: buzón de Hostinger con el dominio del sitio
 *   1. hPanel → Correos electrónicos → elige el dominio → crea un buzón,
 *      por ejemplo no-responder@tudominio.com (y, si quieres, otro como
 *      privacidad@tudominio.com para recibir las constancias).
 *   2. host 'smtp.hostinger.com', puerto 465, usuario el buzón completo y
 *      contrasena la del buzón (no la de tu cuenta de Hostinger).
 *   Necesita un dominio propio: con el subdominio gratuito de Hostinger no
 *   se pueden crear buzones.
 *
 * Opción B: Gmail
 *   1. Activa la verificación en 2 pasos en https://myaccount.google.com/security
 *   2. Crea una contraseña de aplicación en https://myaccount.google.com/apppasswords
 *   3. host 'smtp.gmail.com' y pega esos 16 caracteres en contrasena.
 *
 * Sin este archivo se usa mail() del servidor.
 */
return [
    'host'             => 'smtp.hostinger.com',
    'puerto'           => 465,
    'usuario'          => 'no-responder@tudominio.com',
    'contrasena'       => 'contraseña del buzón',
    'nombre_remitente' => 'Koaly',

    // Opcionales
    // A dónde llegan las respuestas de los empleados (Reply-To). Vacío: al remitente.
    'responder_a'        => 'privacidad@tudominio.com',
    // Recibe una copia de cada constancia de aceptación del aviso de privacidad.
    // Vacío: la copia va al correo de contacto de la sucursal o al gerente.
    'buzon_constancias'  => 'privacidad@tudominio.com',
];
