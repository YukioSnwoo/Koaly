<?php
/**
 * Aviso de privacidad en inglés: traducción de seccionesAvisoPrivacidad() y
 * cambiosAvisoPrivacidad() (aviso.php). Mismas secciones y bloques en el mismo
 * orden. Si cambia el texto en español, cambia también aquí. En caso de
 * diferencia prevalece el español (lo dice la sección 1).
 */

function seccionesAvisoPrivacidadEn(?array $sucursal, string $contacto): array
{
    $domicilio   = trim((string) ($sucursal['direccion'] ?? ''));
    $responsable = 'Koaly' . ($sucursal ? ', through ' . $sucursal['nombre'] : '');
    $escribeA    = str_contains($contacto, '@') ? $contacto : 'your branch\'s contact email';

    return [
        '1. Who is responsible for your data' => [
            "{$responsable}" . ($domicilio !== '' ? ", located at {$domicilio}," : '')
            . ' is responsible for processing your personal data as an employee, under the Mexican '
            . 'Federal Law on the Protection of Personal Data Held by Private Parties.',
            "If you have any questions about this notice, write to {$escribeA}.",
            'This English version is a translation provided for your convenience. If it differs from '
            . 'the Spanish version, the Spanish version prevails.',
        ],
        '2. What data we process' => [
            [
                'Contact' => ['Full name, email address and phone number.',
                    'you, your manager and the administration.'],
                'Identification and address' => ['Date of birth, CURP, RFC, social security number '
                    . '(NSS) and home address.',
                    'you and the administration; your manager does not see them.'],
                'Employment' => ['Position, assigned branch and register, hire date and employment '
                    . 'status (active, on vacation, sick leave, leave of absence or terminated, with the reason).',
                    'you, your manager and the administration.'],
                'Emergency contact' => ['Name, relationship and phone number of the person to notify '
                    . 'in an emergency. This is another person\'s data: before giving it to us, let them know '
                    . 'and share this notice with them.',
                    'you, your manager and the administration, so they can be notified in time.'],
                'System use' => ['Sales, payments, shifts and cash register closings, and inventory '
                    . 'movements you record with your account.',
                    'you, your manager and the administration.'],
                'Security' => ['IP address, browser and date of failed sign-in attempts, password '
                    . 'changes and the acceptance of this notice.',
                    'only the administration.'],
            ],
            'The administration enters your identification, address and emergency contact data '
            . 'for your employment file. If any of it is wrong, ask for it to be corrected.',
            'We do not ask for sensitive data (health, religion, union membership, ethnic origin, etc.). '
            . 'For sick leave we only record that you are on sick leave and the dates, never the diagnosis.',
        ],
        '3. What we use it for' => [
            'Only for purposes necessary for your work:',
            [
                'Giving you access to the point of sale and inventory system and protecting your account.',
                'Managing your employment: hiring, termination, vacations, sick leave, leaves of absence and register assignment.',
                'Recording, supervising and reviewing sales, cash register closings and inventory.',
                'Notifying you by email of changes to your account.',
                'Building your employment file and meeting obligations before the IMSS and the SAT (home address, CURP, RFC and NSS).',
                'Contacting your emergency contact if something happens to you at work.',
            ],
            'We do not use your data for advertising, marketing or any other secondary purpose.',
        ],
        '4. Who we share it with' => [
            'We do not sell your data. It is known only to authorized company staff and:',
            [
                'The web hosting and email providers that work on our behalf and are required to '
                . 'protect it.',
                'The authorities indicated by law (for example the IMSS, the SAT, or labor and judicial '
                . 'authorities), only when we are required to.',
            ],
            'These transfers do not require your consent because they are necessary for the employment '
            . 'relationship or required by law.',
        ],
        '5. How we protect it' => [
            [
                'Your password is stored encrypted: no one, not even your manager, can see it.',
                'Each person sees only what they need: your manager sees your contact and employment data, '
                . 'but not your CURP, RFC, NSS or home address.',
                'The system checks the format of the CURP, RFC and NSS to prevent data entry errors.',
                'The links we send you are personal, expire and work only once.',
                'We log sign-in attempts to detect unauthorized access.',
            ],
        ],
        '6. How long we keep it' => [
            'For as long as you work with us. After you leave, only for the time required by labor, '
            . 'tax and legal obligations; then it is deleted. The sales you recorded are kept as part '
            . 'of the store\'s accounting.',
        ],
        '7. Your rights (ARCO) and how to withdraw your consent' => [
            'You can access your data, correct it if it is wrong, cancel it or object to its use, and '
            . "withdraw your consent. Write to {$escribeA} with your name, the right you want to "
            . 'exercise and a copy of your ID. We will reply within 20 business days at most and, '
            . 'if your request is approved, we will carry it out within the following 15 business days.',
            'If we do not reply or you disagree with the reply, you can go to the Secretaría '
            . 'Anticorrupción y Buen Gobierno (Ministry of Anti-Corruption and Good Governance), the '
            . 'personal data protection authority.',
        ],
        '8. Cookies and browser data' => [
            'The system uses only a session cookie, needed to keep you signed in, and stores in your '
            . 'browser whether you prefer the light or dark theme. We do not use advertising or tracking cookies.',
        ],
        '9. Changes to this notice' => [
            'If this notice changes, we will email it to you and ask you to accept it again before you '
            . 'keep using the system. The date of the current version is at the top of the notice.',
        ],
        '10. Record of your acceptance' => [
            'When you accept, we store the date and time, the notice version, your IP address and your '
            . 'browser. We email you a record with a reference number and the full text you accepted, and '
            . 'a copy is kept in the company mailbox.',
        ],
    ];
}

function cambiosAvisoPrivacidadEn(): array
{
    return [
        'We now store your phone number, home address, date of birth, CURP, RFC, NSS and an emergency '
        . 'contact, for your employment file and for procedures before the IMSS and the SAT.',
        'Each type of data says who can see it. Your manager does not see your CURP, RFC, NSS or home address.',
        'The authority you can go to is now the Secretaría Anticorrupción y Buen Gobierno, which took '
        . 'over the functions of the INAI under the 2025 data protection law.',
    ];
}
