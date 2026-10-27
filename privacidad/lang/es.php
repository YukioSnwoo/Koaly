<?php
/**
 * Textos del aviso de privacidad (página y correo de constancia) en español.
 * Mismas claves en en.php. El texto del aviso está en ../aviso.php y ../aviso_en.php.
 */
return [
    'idioma.nombre'   => 'Español',
    'idioma.cambiar'  => 'English',
    'idioma.cambiar_ayuda' => 'Read in English',

    'titulo'          => 'Aviso de privacidad',
    'h1'              => 'Aviso de privacidad para empleados',
    'version'         => 'Versión vigente del {fecha}',
    'contacto_generico' => 'el correo de contacto de tu sucursal',
    'formato_fecha'   => 'd/m/Y',

    // Cabecera, índice y novedades
    'indice.aria'     => 'Contenido del aviso',
    'indice.titulo'   => 'En este aviso',
    'cambios.desde'   => 'Qué cambió desde la versión que aceptaste',
    'cambios.version' => 'Qué cambió en esta versión',
    'lo_ven'          => 'Lo ven:',
    'ok.primer_ingreso' => 'Ya puedes iniciar sesión con tu correo y la contraseña temporal que te enviamos; el sistema te pedirá elegir la tuya.',
    'ok.puedes_entrar'  => 'Ya puedes iniciar sesión en el sistema.',

    'error.csrf'      => 'El formulario expiró. Vuelve a cargar la página.',
    'error.enlace'    => 'El enlace ya no es válido.',
    'error.casilla'   => 'Marca la casilla para confirmar que leíste y aceptas el aviso.',

    'ok.gracias'      => '¡Gracias, {nombre}!',
    'ok.registrado'   => 'Registramos tu aceptación el {fecha}.',
    'ok.correo'       => 'Te enviamos la constancia a {email}. Respóndela con la frase «{frase}» para dejar tu confirmación por escrito.',
    'ok.sin_correo'   => 'No pudimos enviarte la constancia por correo: imprímela o guárdala desde aquí.',
    'ir_login'        => 'Ir a iniciar sesión',
    'imprimir'        => 'Imprimir constancia',

    'constancia.titulo' => 'Constancia de aceptación · Folio {folio}',
    'huella'          => 'Huella del texto (SHA-256):',
    'firma.empleado'  => 'Firma del empleado',
    'firma.recibe'    => 'Nombre y firma de quien recibe',

    'invalido.titulo' => 'Este enlace ya no es válido',
    'invalido.texto'  => 'Puede que ya lo hayas usado o que haya vencido. Inicia sesión y el sistema te dará uno nuevo, o pide a tu gerente que te lo reenvíe. Abajo puedes leer el aviso vigente.',

    'ficha.titulo'    => 'Tus datos en el sistema',
    'ficha.ayuda'     => 'Revisa que sean correctos. Si algo está mal, avísale a tu gerente.',
    'sin_caja'        => 'Sin caja asignada',

    'campo.folio'     => 'Folio',
    'campo.nombre'    => 'Nombre',
    'campo.correo'    => 'Correo',
    'campo.correo_acceso' => 'Correo de acceso',
    'campo.puesto'    => 'Puesto',
    'campo.sucursal'  => 'Sucursal',
    'campo.caja'      => 'Caja asignada',
    'campo.contratacion' => 'Fecha de contratación',
    'campo.situacion' => 'Situación',
    'campo.fecha'     => 'Fecha y hora',
    'campo.version'   => 'Versión',
    'campo.idioma'    => 'Idioma',
    'campo.ip'        => 'Dirección IP',
    'campo.huella'    => 'Huella del texto',

    'acepto'          => 'Leí el aviso de privacidad y acepto el tratamiento de mis datos personales en los términos descritos.',
    'aceptar'         => 'Aceptar el aviso',
    'nota'            => 'Necesitas aceptarlo para usar el sistema de caja. Guardaremos la fecha, la versión del aviso y la dirección IP, y te enviaremos la constancia por correo.',

    // Datos que vienen de la base de datos
    'rol.1' => 'Administrador',
    'rol.2' => 'Gerente',
    'rol.3' => 'Cajero',
    'estado.Activo'      => 'Activo',
    'estado.Vacaciones'  => 'Vacaciones',
    'estado.Incapacidad' => 'Incapacidad',
    'estado.Licencia'    => 'Licencia',
    'estado.Baja'        => 'Baja',

    // Correo de constancia
    'correo.asunto'   => 'Constancia del aviso de privacidad · Folio {folio}',
    'correo.saludo'   => 'Hola {nombre}:',
    'correo.intro'    => "Registramos que aceptaste el aviso de privacidad de Koaly. Guarda este\ncorreo: es tu constancia.",
    'correo.zona'     => '(hora del centro de México)',
    'correo.confirmar_titulo' => 'PARA DEJAR TU CONFIRMACIÓN POR ESCRITO',
    'correo.confirmar' => "Responde a este correo con la frase: \"{frase}\".\nTu respuesta llega al buzón de la empresa y se guarda junto con esta\nconstancia.",
    'correo.no_fuiste' => 'Si tú no aceptaste este aviso, responde a este correo para avisarnos.',
    'correo.consulta' => 'Puedes consultar el aviso cuando quieras en: {url}',
    'correo.texto_titulo' => 'TEXTO DEL AVISO QUE ACEPTASTE',
    'frase'           => 'Acepto el aviso de privacidad',
];
