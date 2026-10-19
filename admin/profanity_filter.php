<?php
// profanity_filter.php — Cliente del servicio APIVerve Profanity Filter.
// Se usa desde los formularios de alta (gerentes.php, sucursales.php) para
// rechazar nombres con lenguaje ofensivo antes de insertarlos en la BD.
//
// La API key vive fuera del repo, en private/private-API-frofanity.php
// (agregada a .gitignore), que debe devolver ['APIVERVE_API_KEY' => '...'].
// Nunca se hardcodea ni se expone al frontend.

const APIVERVE_PROFANITY_URL = 'https://api.apiverve.com/v1/profanityfilter';

// Se resuelve solo cuando se va a consultar (no al incluir este archivo),
// para no tumbar páginas que no están creando nada si falta la key.
function obtenerApiVerveKey(): ?string
{
    $ruta = __DIR__ . '/../../private/private-API-profanity.php';
    if (!is_file($ruta)) {
        error_log('profanity_filter.php: no se encontró el archivo de credenciales ' . $ruta);
        return null;
    }

    $secretos = require $ruta;
    $apiKey = $secretos['APIVERVE_API_KEY'] ?? '';
    return $apiKey !== '' ? $apiKey : null;
}

// Lanza RuntimeException si el texto es ofensivo o si el servicio no pudo
// validar la solicitud (errores 400/401/403). Ante un 429 o fallo de red,
// deja pasar el texto para no bloquear la operación por una caída externa.
function rechazarSiProfano(string $texto): void
{
    $resultado = consultarProfanityFilter($texto);

    if ($resultado === null) {
        return;
    }

    if ($resultado['isProfane']) {
        throw new RuntimeException('El texto ingresado contiene lenguaje no permitido.');
    }
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
