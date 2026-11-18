<?php
/*
 * Pantalla de mantenimiento
 * ------------------------------------------------------------
 * El .htaccess de la raíz manda aquí todas las peticiones mientras exista
 * el archivo mantenimiento/ACTIVO (docs/INSTALACION.md → Modo mantenimiento).
 * - No usa la base ni la sesión: tiene que abrir aunque lo demás esté apagado.
 * - Responde 503 y sin caché, para que ni el navegador ni Hostinger la guarden
 *   y el sitio regrese en cuanto se apague el mantenimiento.
 * - A los fetch() de la app les contesta JSON con success:false, así la caja
 *   avisa "en mantenimiento" en lugar de "sin conexión".
 * - Si ACTIVO trae texto (p. ej. "Volvemos a las 18:00"), se muestra como aviso.
 */

$bandera = __DIR__ . '/ACTIVO';

// Mantenimiento apagado: quien abra esta dirección vuelve al inicio
if (!is_file($bandera)) {
    header('Location: /', true, 302);
    exit;
}

$textos = [
    'es' => [
        'titulo'     => 'Koaly · En mantenimiento',
        'encabezado' => 'Estamos dándole mantenimiento a Koaly',
        'cuerpo'     => 'Por ahora no se puede entrar mientras hacemos unos ajustes. Vuelve en un rato.',
        'estado'     => 'Esta página se abrirá sola en cuanto Koaly vuelva.',
        'json'       => 'Koaly está en mantenimiento. Intenta de nuevo en un rato.',
    ],
    'en' => [
        'titulo'     => 'Koaly · Under maintenance',
        'encabezado' => 'Koaly is under maintenance',
        'cuerpo'     => 'You can’t sign in while we make some adjustments. Please check back soon.',
        'estado'     => 'This page will open on its own as soon as Koaly is back.',
        'json'       => 'Koaly is under maintenance. Please try again soon.',
    ],
];

// El idioma que eligió la persona en Koaly (admin/preferencia_idioma.php) o el del navegador
$idioma = $_COOKIE['koaly_idioma'] ?? '';
if (!isset($textos[$idioma])) {
    $idioma = stripos($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '', 'en') === 0 ? 'en' : 'es';
}
$t = $textos[$idioma];

$nota = mb_substr(trim((string) @file_get_contents($bandera)), 0, 280);

http_response_code(503);
header('Retry-After: 300');
header('Cache-Control: no-store');

// Peticiones fetch() de la app (Sec-Fetch-Dest: empty) o que piden JSON
$esFetch = ($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '') === 'empty'
    || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
if ($esFetch) {
    $mensaje = $nota === '' ? $t['json'] : $t['json'] . ' ' . $nota;
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        ['success' => false, 'ok' => false, 'mantenimiento' => true, 'message' => $mensaje, 'error' => $mensaje],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

header('Content-Type: text/html; charset=utf-8');
$e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$version = @filemtime(__DIR__ . '/../estilos/koaly.css') ?: 0;
?>
<!DOCTYPE html>
<html lang="<?= $idioma ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= $e($t['titulo']) ?></title>
    <script>
        // Mismo tema que el login (claro salvo que se haya elegido oscuro)
        try { var tema = localStorage.getItem('koaly-tema'); } catch (e) {}
        document.documentElement.setAttribute('data-tema', tema === 'oscuro' ? 'oscuro' : 'claro');
    </script>
    <!-- Rutas absolutas: esta pantalla también se muestra en /cajero/, /admin/, etc. -->
    <link rel="stylesheet" href="/estilos/koaly.css?v=<?= $version ?>">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        /* Fondo morado con puntos, como la portada del login */
        body {
            min-height: 100vh;
            min-height: 100dvh;
            display: grid;
            place-items: center;
            padding: 1.5rem 1rem;
            background-color: var(--acento);
            background-image: radial-gradient(rgba(255, 255, 255, 0.16) 1.5px, transparent 1.6px);
            background-size: 22px 22px;
            color: var(--texto);
            font-family: var(--fuente-texto);
        }

        .tarjeta {
            width: min(100%, 30rem);
            display: grid;
            justify-items: center;
            gap: 1rem;
            padding: 2.5rem 1.75rem 2rem;
            border: 2px solid var(--tinta);
            border-radius: 20px;
            background: var(--superficie);
            box-shadow: var(--sombra-tactil-max);
            text-align: center;
        }

        .sello {
            display: grid;
            place-items: center;
            width: 132px;
            height: 132px;
            margin-bottom: 0.5rem;
            border: 2px solid var(--tinta);
            border-radius: 50%;
            background: var(--acento-suave);
            box-shadow: var(--sombra-tactil);
        }

        .sello img {
            width: 100px;
            height: 100px;
            object-fit: contain;
            animation: balanceo 3.2s ease-in-out infinite;
        }

        .marca {
            font-family: var(--fuente-titulo);
            font-size: 0.95rem;
            font-weight: 800;
            letter-spacing: 0.02em;
            color: var(--acento-texto);
        }

        h1 {
            font-family: var(--fuente-titulo);
            font-size: clamp(1.45rem, 5vw, 1.85rem);
            font-weight: 800;
            line-height: 1.15;
            text-wrap: balance;
        }

        .cuerpo {
            color: var(--texto-2);
            line-height: 1.55;
            text-wrap: pretty;
        }

        .nota {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--aviso-borde);
            border-radius: 12px;
            background: var(--aviso-suave);
            color: var(--aviso-texto);
            font-weight: 600;
            line-height: 1.45;
            overflow-wrap: anywhere;
        }

        .estado {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            margin-top: 0.5rem;
            color: var(--texto-3);
            font-size: 0.85rem;
        }

        .estado::before {
            content: '';
            flex-shrink: 0;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--acento-medio);
            animation: pulso 1.6s ease-in-out infinite;
        }

        @keyframes balanceo {
            0%, 100% { transform: rotate(-4deg); }
            50%      { transform: rotate(4deg); }
        }

        @keyframes pulso {
            0%, 100% { opacity: 0.35; transform: scale(0.85); }
            50%      { opacity: 1;    transform: scale(1); }
        }

        @media (prefers-reduced-motion: reduce) {
            .sello img, .estado::before { animation: none; }
        }
    </style>
</head>
<body>
    <main class="tarjeta">
        <div class="sello"><img src="/Imagenes/koala/koala-pensando.png" alt=""></div>
        <p class="marca">Koaly</p>
        <h1><?= $e($t['encabezado']) ?></h1>
        <p class="cuerpo"><?= $e($t['cuerpo']) ?></p>
        <?php if ($nota !== ''): ?>
            <p class="nota"><?= nl2br($e($nota)) ?></p>
        <?php endif; ?>
        <p class="estado" role="status"><?= $e($t['estado']) ?></p>
    </main>

    <script>
        // Cada 30 s pregunta si ya terminó: con el mantenimiento encendido esta dirección
        // responde 503 y apagado redirige al inicio. Al terminar se recarga la página que
        // la persona había pedido (/cajero/, /admin/…), por GET y sin el #.
        (() => {
            let revisando = false;
            const revisar = async () => {
                if (revisando) return;
                revisando = true;
                try {
                    const r = await fetch('/mantenimiento/', { method: 'HEAD', cache: 'no-store', redirect: 'manual' });
                    if (r.type === 'opaqueredirect' || r.ok) location.replace(location.href.split('#')[0]);
                } catch (e) {
                    // Sin conexión: se vuelve a intentar en la siguiente vuelta
                }
                revisando = false;
            };
            setInterval(revisar, 30000);
            document.addEventListener('visibilitychange', () => { if (!document.hidden) revisar(); });
        })();
    </script>
</body>
</html>
