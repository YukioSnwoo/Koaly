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
