<?php
/**
 * Cuenta que envía los códigos de recuperación de contraseña.
 *
 * Copia este archivo como login/config_correo.php (NO se sube a git) y llena
 * los datos. Con Gmail:
 *   1. Activa la verificación en 2 pasos en https://myaccount.google.com/security
 *   2. Crea una contraseña de aplicación en https://myaccount.google.com/apppasswords
 *   3. Pega aquí esos 16 caracteres (con o sin espacios). NO es tu contraseña normal.
 *
 * Sin este archivo se usa mail() del servidor.
 */
return [
    'host'             => 'smtp.gmail.com',
    'puerto'           => 465,
    'usuario'          => 'tu-correo@gmail.com',
    'contrasena'       => 'xxxx xxxx xxxx xxxx',
    'nombre_remitente' => 'Koaly',
];
