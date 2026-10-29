<?php
// profanity_filter.php — Filtro de lenguaje ofensivo en dos pasos.
// Se usa desde los formularios de alta y edición (gerentes, sucursales,
// cajeros, cajas y Mi perfil) para rechazar nombres antes de guardarlos.
//
// 1. Blasp (admin/lib/blasp/, copia del paquete de GitHub, MIT): corre aquí
//    mismo, sin internet, y detecta trucos como "p3nd3j0", "p-e-n-d-e-j-o"
//    o "peeendejo" en español e inglés.
// 2. APIVerve Profanity Filter: segunda opinión. Si la API falla, cuenta lo
//    que dijo Blasp.
//
// La API key vive fuera del repo, en private/private-API-frofanity.php
// (agregada a .gitignore), que debe devolver ['APIVERVE_API_KEY' => '...'].
// Nunca se hardcodea ni se expone al frontend.

require_once __DIR__ . '/lib/blasp/arranque.php';

const APIVERVE_PROFANITY_URL = 'https://api.apiverve.com/v1/profanityfilter';

// Se resuelve solo cuando se va a consultar (no al incluir este archivo),
// para no tumbar páginas que no están creando nada si falta la key.
function obtenerApiVerveKey(): ?string
{
    $ruta = __DIR__ . '/../../private/private-API-frofanity.php';
    if (!is_file($ruta)) {
        error_log('profanity_filter.php: no se encontró el archivo de credenciales ' . $ruta);
        return null;
    }

    $secretos = require $ruta;
    $apiKey = $secretos['APIVERVE_API_KEY'] ?? '';
    return $apiKey !== '' ? $apiKey : null;
}

// Lanza RuntimeException si Blasp o APIVerve encuentran lenguaje ofensivo.
// Si APIVerve no responde (red, 429, key), cuenta solo lo que dijo Blasp.
function rechazarSiProfano(string $texto): void
{
    if (blaspEncuentraProfano($texto)) {
        throw new RuntimeException('El texto ingresado contiene lenguaje no permitido.');
    }

    $resultado = consultarProfanityFilter($texto);

    if ($resultado === null) {
        return;
    }

    if ($resultado['isProfane']) {
        throw new RuntimeException('El texto ingresado contiene lenguaje no permitido.');
    }
}

// Revisa en español y en inglés. Los diccionarios se arman una vez por
// petición (~90 ms); cada revisión después tarda unos milisegundos.
function blaspEncuentraProfano(string $texto): bool
{
    static $diccionarios = null;
    $diccionarios ??= [
        \Blaspsoft\Blasp\Core\Dictionary::forLanguage('spanish'),
        \Blaspsoft\Blasp\Core\Dictionary::forLanguage('english'),
    ];

    $analizador = new \Blaspsoft\Blasp\Core\Analyzer();
    $driver = new \Blaspsoft\Blasp\Drivers\RegexDriver();
    foreach ($diccionarios as $diccionario) {
        if ($analizador->analyze($texto, $driver, $diccionario)->isOffensive()) {
            return true;
        }
    }
    return false;
}

// Devuelve ['isProfane' => bool] o null si el servicio no está disponible
// o no se pudo consultar (la llamada no debe tumbar el flujo de alta).
function consultarProfanityFilter(string $texto): ?array
{
    $apiKey = obtenerApiVerveKey();
    if ($apiKey === null) {
        return null;
    }

    $payload = json_encode(['text' => $texto, 'mask' => '*']);

    $ch = curl_init(APIVERVE_PROFANITY_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
    ]);
    $respuesta = curl_exec($ch);
    $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errorCurl = curl_error($ch);
    curl_close($ch);

    if ($respuesta === false) {
        error_log('profanity_filter.php: error de conexión — ' . $errorCurl);
        return null;
    }

    if ($codigo === 400) {
        error_log('profanity_filter.php: solicitud inválida (400) — ' . $respuesta);
        return null;
    }
    if ($codigo === 401 || $codigo === 403) {
        error_log('profanity_filter.php: API key inválida o sin permisos (' . $codigo . ') — ' . $respuesta);
        return null;
    }
    if ($codigo === 429) {
        error_log('profanity_filter.php: límite de solicitudes excedido (429)');
        return null;
    }
    if ($codigo !== 200) {
        error_log('profanity_filter.php: respuesta inesperada (' . $codigo . ') — ' . $respuesta);
        return null;
    }

    $datos = json_decode($respuesta, true);
    if (!is_array($datos) || ($datos['status'] ?? null) !== 'ok' || !isset($datos['data']['isProfane'])) {
        error_log('profanity_filter.php: respuesta con formato inesperado — ' . $respuesta);
        return null;
    }

    return ['isProfane' => (bool) $datos['data']['isProfane']];
}
