<?php
// profanity_filter.php — Filtro de lenguaje ofensivo.
// Se usa desde los formularios de alta y edición (gerentes, sucursales,
// cajeros, cajas y Mi perfil) para rechazar nombres antes de guardarlos.
// Va de lo más barato a lo más caro y se detiene en cuanto encuentra algo:
//
// 1. Groserías comunes: cada palabra, sin acentos ni números que hacen de
//    letra ("p3nd3j0"), se busca tal cual en la lista. Microsegundos.
// 2. Groserías pegadas: raíces fuertes dentro de cada palabra
//    ("tuputamadre", "Cajamierda"). Microsegundos.
// 3. Blasp (admin/lib/blasp/, copia del paquete de GitHub, MIT): separadores,
//    letras repetidas y sustituciones, en español e inglés. Unos 5 ms; la
//    primera vez de cada proceso compila sus expresiones (~150 ms).
// 4. APIVerve Profanity Filter: segunda opinión por internet. Si la API
//    falla, cuenta lo que dijeron los pasos anteriores.
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

// Lanza RuntimeException si algún paso encuentra lenguaje ofensivo.
// Si APIVerve no responde (red, 429, key), cuenta solo lo local.
function rechazarSiProfano(string $texto): void
{
    if (tieneGroseriaComun($texto) || tieneGroseriaPegada($texto) || blaspEncuentraProfano($texto)) {
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

// "TuPutaMadre" también se revisa como "Tu Puta Madre"
function variantesTexto(string $texto): array
{
    return array_values(array_unique([$texto, preg_replace('/(?<=\p{Ll})(?=\p{Lu})/u', ' ', $texto)]));
}

// Diccionarios de Blasp y lista de groserías comunes. Armarlos tarda ~90 ms,
// así que se guardan ya armados en un archivo temporal; se rehacen solos
// cuando cambia alguna lista.
function datosFiltro(): array
{
    static $datos = null;
    if ($datos !== null) {
        return $datos;
    }

    $listas = array_merge(
        glob(__DIR__ . '/lib/blasp/config/languages/*.php'),
        glob(__DIR__ . '/lib/blasp/koaly/*.php'),
        glob(__DIR__ . '/lib/blasp/koaly/languages/*.php'),
    );
    $huella = '';
    foreach ($listas as $lista) {
        $huella .= $lista . filemtime($lista);
    }
    $cache = sys_get_temp_dir() . '/koaly-filtro-' . md5($huella) . '.ser';

    if (is_file($cache)) {
        $datos = @unserialize((string) file_get_contents($cache), ['allowed_classes' => [
            \Blaspsoft\Blasp\Core\Dictionary::class,
            \Blaspsoft\Blasp\Core\Normalizers\SpanishNormalizer::class,
            \Blaspsoft\Blasp\Core\Normalizers\EnglishNormalizer::class,
            \Blaspsoft\Blasp\Enums\Severity::class,
        ]]);
        if (is_array($datos) && isset($datos['diccionarios'], $datos['comunes'])) {
            return $datos;
        }
    }

    $diccionarios = [
        \Blaspsoft\Blasp\Core\Dictionary::forLanguage('spanish'),
        \Blaspsoft\Blasp\Core\Dictionary::forLanguage('english'),
    ];
    $permitidas = array_map(fn ($p) => compactarTexto($p, false), require __DIR__ . '/lib/blasp/koaly/permitidas.php');
    $comunes = [];
    foreach ($diccionarios as $diccionario) {
        foreach ($diccionario->getProfanities() as $palabra) {
            $compacta = compactarTexto($palabra, false);
            if ($compacta !== '' && !in_array($compacta, $permitidas, true)) {
                $comunes[$compacta] = true;
            }
        }
    }

    $datos = ['diccionarios' => $diccionarios, 'comunes' => $comunes];
    @file_put_contents($cache, serialize($datos), LOCK_EX);
    return $datos;
}

// Paso 1: cada palabra completa contra la lista. También junta letras
// sueltas ("p u t o") y prueba quitando letras repetidas ("puuuto").
function tieneGroseriaComun(string $texto): bool
{
    $comunes = datosFiltro()['comunes'];

    foreach (variantesTexto($texto) as $variante) {
        $palabras = preg_split('/\s+/u', $variante, -1, PREG_SPLIT_NO_EMPTY);
        $sueltas = implode('', array_filter($palabras, fn ($p) => mb_strlen($p) === 1));
        if (mb_strlen($sueltas) > 2) {
            $palabras[] = $sueltas;
        }
        foreach ($palabras as $palabra) {
            $compacta = compactarTexto($palabra, false);
            foreach ([$compacta, preg_replace('/(.)\1{2,}/', '$1$1', $compacta), preg_replace('/(.)\1{2,}/', '$1', $compacta)] as $forma) {
                if (isset($comunes[$forma])) {
                    return true;
                }
            }
        }
    }
    return false;
}

// Paso 2: las raíces de koaly/pegadas.php dentro de cada palabra, después de
// quitar acentos, números que hacen de letra ("p3nd3j0"), signos y letras
// repetidas ("peeendejo"). Así se atrapa "tuputamadre" o "Cajamierda".
function tieneGroseriaPegada(string $texto): bool
{
    static $raices = null;
    $raices ??= array_map('compactarTexto', require __DIR__ . '/lib/blasp/koaly/pegadas.php');

    foreach (preg_split('/\s+/u', $texto, -1, PREG_SPLIT_NO_EMPTY) as $palabra) {
        $compacta = compactarTexto($palabra);
        foreach ($raices as $raiz) {
            if (str_contains($compacta, $raiz)) {
                return true;
            }
        }
    }
    return false;
}

// Paso 3: Blasp completo. Sin JIT de PCRE: compilar ~1,700 expresiones
// para usarlas pocas veces sale más barato sin él (470 → 250 ms en frío).
function blaspEncuentraProfano(string $texto): bool
{
    $jit = ini_get('pcre.jit');
    ini_set('pcre.jit', '0');

    try {
        $analizador = new \Blaspsoft\Blasp\Core\Analyzer();
        $driver = new \Blaspsoft\Blasp\Drivers\RegexDriver();
        foreach (variantesTexto($texto) as $variante) {
            foreach (datosFiltro()['diccionarios'] as $diccionario) {
                if ($analizador->analyze($variante, $driver, $diccionario)->isOffensive()) {
                    return true;
                }
            }
        }
        return false;
    } finally {
        ini_set('pcre.jit', $jit);
    }
}

// Minúsculas, sin acentos, números que hacen de letra pasados a letra y sin
// signos. Con $colapsar, además sin letras repetidas ("peeendejo" → "pendejo").
function compactarTexto(string $texto, bool $colapsar = true): string
{
    $texto = mb_strtolower($texto, 'UTF-8');
    $texto = strtr($texto, [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i', 'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u', 'ñ' => 'n',
        '4' => 'a', '@' => 'a', '3' => 'e', '1' => 'i', '!' => 'i', '|' => 'i',
        '0' => 'o', '5' => 's', '$' => 's', '7' => 't',
    ]);
    $texto = preg_replace('/[^a-z]/', '', $texto);
    return $colapsar ? preg_replace('/(.)\1+/', '$1', $texto) : $texto;
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
