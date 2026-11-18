<?php
// reglas_nombre.php — Reglas de escritura para nombres de personas
// (gerentes, cajeros y Mi perfil). No aplica a cajas ni sucursales, que
// llevan números ("Caja 3").
//
// Lo que se puede arreglar solo se arregla: "juan PÉREZ" → "Juan Pérez".
// Lo demás se rechaza con NombreInvalido, cuyo ->regla dice qué falló:
//   caracteres  algo que no es letra, espacio, guion, apóstrofo o punto
//   mayuscula   una palabra no empieza con mayúscula o la lleva en medio
//               ("TuPutaMadre"); se permiten McDonald, O'Brien, DeLeón…
//   repetidas   la misma letra tres veces seguidas ("Peeedro")
//   corta       palabras de una letra que no son inicial ("J.") ni "y"/"e"
//   falta       sin nombre o sin ningún apellido (nombreDesdeFormulario)
//   largo       una parte o el nombre completo no caben (nombreDesdeFormulario)

class NombreInvalido extends RuntimeException
{
    public function __construct(public readonly string $regla)
    {
        parent::__construct('Nombre inválido: ' . $regla);
    }
}

// Partículas que van en minúscula después de la primera palabra
// ("Juan de la Cruz", "Ludwig van Beethoven").
const PARTICULAS_NOMBRE = [
    'de', 'del', 'la', 'las', 'los', 'y', 'e', 'da', 'das', 'do', 'dos',
    'di', 'du', 'van', 'von', 'der', 'den', 'ten', 'ter', 'le',
];

// Devuelve el nombre ya arreglado o lanza NombreInvalido.
function normalizarNombrePersona(string $nombre): string
{
    $nombre = trim(preg_replace('/\s+/u', ' ', $nombre));

    if (!preg_match("/^[\\p{L}\\p{M} .'’-]+$/u", $nombre)) {
        throw new NombreInvalido('caracteres');
    }
    if (preg_match('/(\p{L})\1\1/iu', $nombre)) {
        throw new NombreInvalido('repetidas');
    }

    $palabras = explode(' ', $nombre);
    foreach ($palabras as $i => $palabra) {
        $minuscula = mb_strtolower($palabra, 'UTF-8');
        if ($i > 0 && in_array($minuscula, PARTICULAS_NOMBRE, true)) {
            $palabras[$i] = $minuscula;
            continue;
        }

        // Cada parte de "María-José" se revisa por separado
        $partes = explode('-', $palabra);
        foreach ($partes as $j => $parte) {
            $partes[$j] = normalizarParteNombre($parte);
        }
        $palabras[$i] = implode('-', $partes);
    }

    return implode(' ', $palabras);
}

function normalizarParteNombre(string $parte): string
{
    $letras = preg_replace('/[^\p{L}]/u', '', $parte);
    if ($letras === '') {
        throw new NombreInvalido('caracteres');
    }

    // Todo en minúsculas o todo en mayúsculas: se arregla solo
    if ($parte === mb_strtolower($parte, 'UTF-8') || (mb_strlen($letras) > 1 && $parte === mb_strtoupper($parte, 'UTF-8'))) {
        $parte = mb_convert_case($parte, MB_CASE_TITLE, 'UTF-8');
    }

    // Una letra sola solo vale como inicial ("J.")
    if (mb_strlen($letras) === 1 && !str_ends_with($parte, '.')) {
        throw new NombreInvalido('corta');
    }

    // Mayúscula al inicio; en medio solo tras Mc, Mac, O', D', De, La…
    $patron = "/^(?:(?:Mc|Mac|De|Di|Da|Du|La|Le|Van|Von)(?=\\p{Lu})|[OD]['’](?=\\p{Lu}))?\\p{Lu}[\\p{Ll}\\p{M}'’]*\\.?$/u";
    if (!preg_match($patron, $parte)) {
        throw new NombreInvalido('mayuscula');
    }

    return $parte;
}


// ------------------------------------------------------------
// Nombre(s) y apellidos por separado (sql/017_nombres_apellidos.sql)
// ------------------------------------------------------------
// Los formularios de cajeros, gerentes y Mi perfil piden Nombre(s), Apellido
// paterno y Apellido materno. Basta con uno de los dos apellidos (hay quien
// tiene uno solo), pero no se pueden dejar vacíos los dos.
// Usuarios.nombre sigue guardando el nombre completo, que es lo que muestran
// reportes, tickets y correos. Sin sql/017 todo funciona solo con el completo.

const NOMBRE_MAX_NOMBRES  = 60;
const NOMBRE_MAX_APELLIDO = 40;
const NOMBRE_MAX_COMPLETO = 100;   // Usuarios.nombre es VARCHAR(100)

/** Las tres partes como llegan del formulario: recortadas, sin arreglar. */
function partesNombrePost(array $datos): array
{
    $parte = fn(string $campo) => trim(preg_replace('/\s+/u', ' ', (string) ($datos[$campo] ?? '')));
    return [
        'nombres'          => $parte('nombres'),
        'apellido_paterno' => $parte('apellido_paterno'),
        'apellido_materno' => $parte('apellido_materno'),
    ];
}

/** "Nombre(s) Paterno Materno": lo que va en Usuarios.nombre. */
function nombreCompleto(array $partes): string
{
    $lista = [$partes['nombres'] ?? '', $partes['apellido_paterno'] ?? '', $partes['apellido_materno'] ?? ''];
    return implode(' ', array_filter($lista, fn($p) => $p !== null && $p !== ''));
}

/** ¿Falta el nombre o faltan los dos apellidos? (uno de los dos basta) */
function faltaNombreOApellido(array $partes): bool
{
    return $partes['nombres'] === '' || ($partes['apellido_paterno'] === '' && $partes['apellido_materno'] === '');
}

/** Apellidos vacíos a null, que es como se guardan. */
function apellidosVaciosANull(array $partes): array
{
    foreach (['apellido_paterno', 'apellido_materno'] as $campo) {
        $partes[$campo] = ($partes[$campo] ?? '') !== '' ? $partes[$campo] : null;
    }
    return $partes;
}

/**
 * Valida y arregla las partes del formulario ("juan PÉREZ" → "Juan Pérez");
 * el apellido vacío queda en null. Lanza NombreInvalido con la regla 'falta'
 * (sin nombre o sin ningún apellido), 'largo' o una de normalizarNombrePersona().
 * @return array{nombres: string, apellido_paterno: ?string, apellido_materno: ?string, nombre: string}
 */
function nombreDesdeFormulario(array $datos): array
{
    $partes = partesNombrePost($datos);
    if (faltaNombreOApellido($partes)) {
        throw new NombreInvalido('falta');
    }
    if (mb_strlen($partes['nombres']) > NOMBRE_MAX_NOMBRES
        || mb_strlen($partes['apellido_paterno']) > NOMBRE_MAX_APELLIDO
        || mb_strlen($partes['apellido_materno']) > NOMBRE_MAX_APELLIDO
        || mb_strlen(nombreCompleto($partes)) > NOMBRE_MAX_COMPLETO) {
        throw new NombreInvalido('largo');
    }
    foreach ($partes as $campo => $valor) {
        if ($valor !== '') {
            $partes[$campo] = normalizarNombrePersona($valor);
        }
    }
    $partes = apellidosVaciosANull($partes);
    $partes['nombre'] = nombreCompleto($partes);
    return $partes;
}

/** ¿Ya se corrió sql/017? Se pregunta una vez por petición. */
function hayColumnasApellidos(PDO $pdo): bool
{
    static $hay = null;
    if ($hay === null) {
        try {
            $consulta = $pdo->query("SHOW COLUMNS FROM Usuarios LIKE 'apellido_paterno'");
            $hay = $consulta !== false && $consulta->fetch() !== false;
        } catch (PDOException) {
            $hay = false;
        }
    }
    return $hay;
}

/** Columnas de las partes para un SELECT de Usuarios (alias $u); NULL si falta sql/017. */
function columnasApellidos(PDO $pdo, string $u = 'u'): string
{
    return hayColumnasApellidos($pdo)
        ? "{$u}.nombres, {$u}.apellido_paterno, {$u}.apellido_materno"
        : 'NULL AS nombres, NULL AS apellido_paterno, NULL AS apellido_materno';
}

/** Guarda las partes por separado; Usuarios.nombre ya se guardó con el completo. */
function guardarPartesNombre(PDO $pdo, int $idUsuario, array $partes): void
{
    if (!hayColumnasApellidos($pdo)) {
        return;
    }
    $pdo->prepare('UPDATE Usuarios SET nombres = ?, apellido_paterno = ?, apellido_materno = ? WHERE id_usuario = ?')
        ->execute([$partes['nombres'], $partes['apellido_paterno'] ?? null, $partes['apellido_materno'] ?? null, $idUsuario]);
}

/**
 * Partes de una fila de Usuarios para llenar un formulario de edición. Si aún
 * no están separadas, se adivinan igual que en sql/017: las dos últimas
 * palabras son los apellidos; con dos palabras, la segunda es el paterno.
 * @return array{nombres: string, apellido_paterno: string, apellido_materno: string}
 */
function partesNombre(array $fila): array
{
    if (($fila['nombres'] ?? '') !== '') {
        return [
            'nombres'          => (string) $fila['nombres'],
            'apellido_paterno' => (string) ($fila['apellido_paterno'] ?? ''),
            'apellido_materno' => (string) ($fila['apellido_materno'] ?? ''),
        ];
    }
    $palabras = preg_split('/\s+/u', trim((string) ($fila['nombre'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);
    $n = count($palabras);
    return match (true) {
        $n >= 3 => ['nombres' => implode(' ', array_slice($palabras, 0, -2)), 'apellido_paterno' => $palabras[$n - 2], 'apellido_materno' => $palabras[$n - 1]],
        $n === 2 => ['nombres' => $palabras[0], 'apellido_paterno' => $palabras[1], 'apellido_materno' => ''],
        default  => ['nombres' => $palabras[0] ?? '', 'apellido_paterno' => '', 'apellido_materno' => ''],
    };
}

/**
 * Iniciales para el avatar: la del primer nombre y la del primer apellido
 * ("Juan Carlos Pérez López" → "JP"). Sin paterno se usa el materno, y en un
 * apellido como "de la Cruz" se salta la partícula ("C"). $fila es una fila de
 * Usuarios (con las partes o solo nombre) o el nombre completo como texto.
 */
function inicialesNombre(array|string $fila): string
{
    $partes = partesNombre(is_string($fila) ? ['nombre' => $fila] : $fila);
    $inicial = static function (string $texto, bool $saltarParticulas): string {
        foreach (preg_split('/\s+/u', trim($texto), -1, PREG_SPLIT_NO_EMPTY) as $palabra) {
            if ($saltarParticulas && in_array(mb_strtolower($palabra), PARTICULAS_NOMBRE, true)) {
                continue;
            }
            return mb_strtoupper(mb_substr($palabra, 0, 1));
        }
        return '';
    };
    $apellido = $partes['apellido_paterno'] !== '' ? $partes['apellido_paterno'] : $partes['apellido_materno'];
    $iniciales = $inicial($partes['nombres'], false) . $inicial($apellido, true);
    return $iniciales !== '' ? $iniciales : '?';
}
