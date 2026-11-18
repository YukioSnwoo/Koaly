<?php
/**
 * Aviso de privacidad para gerentes (español e inglés). Sigue la misma
 * estructura que el de cajeros (seccionesAvisoPrivacidad en aviso.php) y se
 * pinta igual: [título => [bloques]]. Lo distinto: el gerente no tiene "un
 * gerente" arriba sino a la administración, maneja la sucursal y ve datos de
 * sus cajeros, por eso lleva la sección "Los datos de tu equipo".
 *
 * Si cambia el texto, cambia también la traducción y sube AVISO_GERENTE_VERSION
 * (aviso.php): los gerentes tendrán que aceptarlo otra vez.
 *
 * @param array  $sucursal ['nombre', 'direccion'] o null
 * @param string $contacto correo de la administración, o '' si no hay
 */
function seccionesAvisoGerente(?array $sucursal, string $contacto, string $idioma = 'es'): array
{
    if ($idioma === 'en') {
        return seccionesAvisoGerenteEn($sucursal, $contacto);
    }

    $domicilio   = trim((string) ($sucursal['direccion'] ?? ''));
    $responsable = 'Koaly' . ($sucursal ? ', a través de ' . $sucursal['nombre'] : '');
    $escribeA    = str_contains($contacto, '@') ? "a {$contacto}" : 'al correo de la administración';

    return [
        '1. Quién es responsable de tus datos' => [
            "{$responsable}" . ($domicilio !== '' ? ", con domicilio en {$domicilio}," : '')
            . ' es responsable del tratamiento de tus datos personales como gerente, conforme a la '
            . 'Ley Federal de Protección de Datos Personales en Posesión de los Particulares.',
            "Para cualquier duda sobre este aviso escribe {$escribeA}.",
        ],
        '2. Qué datos tuyos tratamos' => [
            [
                'Contacto' => ['Nombre completo, correo electrónico y teléfono.',
                    'tú y la administración. Los cajeros de tu sucursal ven solo tu nombre y tu correo, '
                    . 'para saber a quién acudir.'],
                'Identificación y domicilio' => ['Fecha de nacimiento, CURP, RFC, número de seguridad '
                    . 'social (NSS) y domicilio.',
                    'tú y la administración.'],
                'Laborales' => ['Puesto, sucursal a tu cargo, fecha de contratación e historial de tu '
                    . 'situación laboral (activo, vacaciones, incapacidad, licencia o baja, con su motivo).',
                    'tú y la administración.'],
                'Contacto de emergencia' => ['Nombre, parentesco y teléfono de la persona a quien avisar '
                    . 'en una emergencia. Son datos de otra persona: antes de dárnoslos, avísale y '
                    . 'compártele este aviso.',
                    'tú y la administración, para poder avisarle a tiempo.'],
                'Uso del sistema' => ['Lo que registras con tu cuenta: solicitudes de productos, '
                    . 'movimientos de inventario, altas, cambios y bajas de cajeros y cajas, y la '
                    . 'recepción de los cortes de caja.',
                    'tú y la administración.'],
                'Seguridad' => ['Dirección IP, navegador y fecha de los inicios de sesión fallidos, de '
                    . 'los cambios de contraseña y de la aceptación de este aviso.',
                    'solo la administración.'],
            ],
            'La administración captura tus datos de identificación, domicilio y contacto de emergencia '
            . 'para tu expediente laboral. Si alguno está mal, pide que se corrija.',
            'No te pedimos datos sensibles (salud, religión, afiliación sindical, origen étnico, etc.). '
            . 'De una incapacidad solo registramos que estás incapacitado y las fechas, nunca el diagnóstico.',
        ],
        '3. Para qué los usamos' => [
            'Solo para finalidades necesarias para tu trabajo:',
            [
                'Darte acceso al panel de gerente y proteger tu cuenta.',
                'Administrar tu relación laboral: altas, bajas, vacaciones, incapacidades, licencias y la '
                . 'sucursal a tu cargo.',
                'Registrar y revisar la operación de tu sucursal: inventario, solicitudes de productos, '
                . 'cajeros, cajas y cortes.',
                'Avisarte por correo de los cambios en tu cuenta.',
                'Integrar tu expediente laboral y cumplir obligaciones ante el IMSS y el SAT (domicilio, CURP, RFC y NSS).',
                'Comunicarnos con tu contacto de emergencia si te pasa algo durante el trabajo.',
            ],
            'No usamos tus datos para publicidad, mercadotecnia ni ninguna otra finalidad secundaria.',
        ],
        '4. Los datos de tu equipo' => [
            'Como gerente ves y capturas datos de los cajeros de tu sucursal: nombre, correo, situación '
            . 'laboral (con el motivo de una baja), caja asignada, ventas, turnos y cortes de caja. No ves '
            . 'su CURP, RFC, NSS ni domicilio.',
            'Al aceptar este aviso te comprometes a:',
            [
                'Usar esos datos solo para administrar tu sucursal.',
                'No copiarlos, compartirlos ni sacarlos del sistema (capturas de pantalla, listas, mensajes '
                . 'o redes sociales) fuera de lo que pida tu trabajo.',
                'No prestar tu cuenta ni tu contraseña, y cerrar sesión en equipos compartidos.',
                'Avisar a la administración en cuanto sepas o sospeches de un acceso indebido o de una fuga de datos.',
            ],
            'Este deber de confidencialidad sigue vigente después de que termine tu relación laboral.',
        ],
        '5. Con quién los compartimos' => [
            'No vendemos tus datos. Solo los conocen el personal autorizado de la empresa y:',
            [
                'Los proveedores de alojamiento web y correo electrónico que trabajan por nuestra cuenta y '
                . 'están obligados a protegerlos.',
                'Las autoridades que la ley indique (por ejemplo IMSS, SAT o autoridades laborales y '
                . 'judiciales), solo cuando estemos obligados.',
            ],
            'Estas transferencias no requieren tu consentimiento porque son necesarias para la relación '
            . 'laboral o las exige la ley.',
        ],
        '6. Cómo los protegemos' => [
            [
                'Tu contraseña se guarda cifrada: nadie, ni la administración, puede verla.',
                'Cada persona ve solo lo que necesita: los cajeros de tu sucursal solo ven tu nombre y tu correo.',
                'El sistema revisa el formato de la CURP, el RFC y el NSS para evitar errores de captura.',
                'Los enlaces que te enviamos son personales, vencen y sirven una sola vez.',
                'Registramos los intentos de inicio de sesión y bloqueamos unos minutos la cuenta tras varios '
                . 'intentos fallidos.',
                'Tu sesión se cierra sola tras 30 minutos sin actividad.',
            ],
        ],
        '7. Cuánto tiempo los conservamos' => [
            'Mientras dure tu relación laboral. Después de tu baja, solo el tiempo que exijan las '
            . 'obligaciones laborales, fiscales y legales; luego se eliminan. Lo que registraste '
            . '(inventario, solicitudes y cortes recibidos) se conserva como parte de la contabilidad de la tienda.',
        ],
        '8. Tus derechos (ARCO) y cómo revocar tu consentimiento' => [
            'Puedes acceder a tus datos, rectificarlos si están mal, cancelarlos u oponerte a su uso, y '
            . "revocar tu consentimiento. Escribe {$escribeA} con tu nombre, el derecho que quieres "
            . 'ejercer y una copia de tu identificación. Te responderemos en un máximo de 20 días hábiles '
            . 'y, si procede, lo haremos efectivo en los 15 días hábiles siguientes.',
            'Si no te respondemos o no estás de acuerdo con la respuesta, puedes acudir a la Secretaría '
            . 'Anticorrupción y Buen Gobierno, la autoridad de protección de datos personales.',
        ],
        '9. Cookies y datos del navegador' => [
            'El sistema usa solo cookies técnicas: una de sesión, necesaria para mantenerte conectado, y '
            . 'otra que recuerda tu idioma. Además guarda en tu navegador el tema claro u oscuro y cómo '
            . 'prefieres ver las listas. No usamos cookies de publicidad ni de rastreo.',
        ],
        '10. Cambios a este aviso' => [
            'Si este aviso cambia, te lo enviaremos por correo y te pediremos aceptarlo de nuevo antes de '
            . 'seguir usando el sistema. La fecha de la versión vigente está al inicio del aviso.',
        ],
        '11. Constancia de tu aceptación' => [
            'Al aceptar guardamos la fecha y hora, la versión del aviso, tu dirección IP y tu navegador. '
            . 'Te enviamos por correo una constancia con un folio y el texto completo que aceptaste, y una '
            . 'copia queda en el buzón de la empresa.',
        ],
    ];
}

/** Traducción al inglés; si difiere del español, prevalece el español (lo dice la sección 1). */
function seccionesAvisoGerenteEn(?array $sucursal, string $contacto): array
{
    $domicilio   = trim((string) ($sucursal['direccion'] ?? ''));
    $responsable = 'Koaly' . ($sucursal ? ', through ' . $sucursal['nombre'] : '');
    $escribeA    = str_contains($contacto, '@') ? $contacto : 'the administration\'s email';

    return [
        '1. Who is responsible for your data' => [
            "{$responsable}" . ($domicilio !== '' ? ", located at {$domicilio}," : '')
            . ' is responsible for processing your personal data as a manager, under the Mexican '
            . 'Federal Law on the Protection of Personal Data Held by Private Parties.',
            "If you have any questions about this notice, write to {$escribeA}.",
            'This English version is a translation provided for your convenience. If it differs from '
            . 'the Spanish version, the Spanish version prevails.',
        ],
        '2. What data of yours we process' => [
            [
                'Contact' => ['Full name, email address and phone number.',
                    'you and the administration. The cashiers at your branch only see your name and email, '
                    . 'so they know who to turn to.'],
                'Identification and address' => ['Date of birth, CURP, RFC, social security number '
                    . '(NSS) and home address.',
                    'you and the administration.'],
                'Employment' => ['Position, the branch you manage, hiring date and the history of your '
                    . 'employment status (active, vacation, sick leave, leave of absence or terminated, '
                    . 'with its reason).',
                    'you and the administration.'],
                'Emergency contact' => ['Name, relationship and phone number of the person to notify in an '
                    . 'emergency. This is someone else\'s data: before giving it to us, let them know and '
                    . 'share this notice with them.',
                    'you and the administration, so we can reach them in time.'],
                'System use' => ['What you record with your account: product requests, inventory '
                    . 'movements, adding, changing and terminating cashiers and registers, and receiving '
                    . 'register closings.',
                    'you and the administration.'],
                'Security' => ['IP address, browser and date of failed logins, password changes and the '
                    . 'acceptance of this notice.',
                    'the administration only.'],
            ],
            'The administration enters your identification, address and emergency contact data for your '
            . 'employment file. If any of it is wrong, ask for it to be corrected.',
            'We do not ask for sensitive data (health, religion, union membership, ethnic origin, etc.). '
            . 'For a sick leave we only record that you are on leave and the dates, never the diagnosis.',
        ],
        '3. What we use it for' => [
            'Only for purposes necessary for your job:',
            [
                'Giving you access to the manager panel and protecting your account.',
                'Managing your employment: hiring, termination, vacation, sick leave, leaves of absence and '
                . 'the branch you manage.',
                'Recording and reviewing your branch\'s operation: inventory, product requests, cashiers, '
                . 'registers and closings.',
                'Notifying you by email of changes to your account.',
                'Building your employment file and meeting obligations before the IMSS and the SAT (address, '
                . 'CURP, RFC and NSS).',
                'Contacting your emergency contact if something happens to you at work.',
            ],
            'We do not use your data for advertising, marketing or any other secondary purpose.',
        ],
        '4. Your team\'s data' => [
            'As a manager you see and enter data about the cashiers at your branch: name, email, employment '
            . 'status (with the reason for a termination), assigned register, sales, shifts and register '
            . 'closings. You do not see their CURP, RFC, NSS or home address.',
            'By accepting this notice you commit to:',
            [
                'Use that data only to manage your branch.',
                'Not copy, share or take it out of the system (screenshots, lists, messages or social media) '
                . 'beyond what your job requires.',
                'Not lend your account or password, and log out on shared computers.',
                'Tell the administration as soon as you know or suspect an unauthorized access or a data leak.',
            ],
            'This duty of confidentiality remains in force after your employment ends.',
        ],
        '5. Who we share it with' => [
            'We do not sell your data. Only authorized company staff know it, plus:',
            [
                'The web hosting and email providers that work on our behalf and are required to protect it.',
                'The authorities required by law (for example the IMSS, the SAT or labor and judicial '
                . 'authorities), only when we are obliged to.',
            ],
            'These transfers do not require your consent because they are necessary for the employment '
            . 'relationship or required by law.',
        ],
        '6. How we protect it' => [
            [
                'Your password is stored encrypted: no one, not even the administration, can see it.',
                'Each person sees only what they need: the cashiers at your branch only see your name and email.',
                'The system checks the format of the CURP, RFC and NSS to avoid typing errors.',
                'The links we send you are personal, expire and work only once.',
                'We record login attempts and lock the account for a few minutes after several failed attempts.',
                'Your session closes on its own after 30 minutes of inactivity.',
            ],
        ],
        '7. How long we keep it' => [
            'For as long as your employment lasts. After you leave, only as long as labor, tax and legal '
            . 'obligations require; then it is deleted. What you recorded (inventory, requests and received '
            . 'closings) is kept as part of the store\'s accounting.',
        ],
        '8. Your rights (ARCO) and how to withdraw your consent' => [
            'You can access your data, correct it if it is wrong, cancel it or object to its use, and '
            . "withdraw your consent. Write to {$escribeA} with your name, the right you want to exercise "
            . 'and a copy of your ID. We will answer within 20 business days and, if applicable, apply it '
            . 'within the following 15 business days.',
            'If we do not answer or you disagree with the answer, you can go to the Secretaría '
            . 'Anticorrupción y Buen Gobierno, the personal data protection authority.',
        ],
        '9. Cookies and browser data' => [
            'The system only uses technical cookies: a session cookie, needed to keep you logged in, and '
            . 'one that remembers your language. It also stores in your browser the light or dark theme and '
            . 'how you prefer to view lists. We do not use advertising or tracking cookies.',
        ],
        '10. Changes to this notice' => [
            'If this notice changes, we will email it to you and ask you to accept it again before you '
            . 'keep using the system. The date of the current version is at the top of the notice.',
        ],
        '11. Record of your acceptance' => [
            'When you accept, we store the date and time, the notice version, your IP address and your '
            . 'browser. We email you a record with a reference number and the full text you accepted, and '
            . 'a copy is kept in the company mailbox.',
        ],
    ];
}
