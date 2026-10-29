<?php

namespace Blaspsoft\Blasp\Core\Normalizers;

class SpanishNormalizer implements StringNormalizer
{
    public function normalize(string $string): string
    {
        $spanishMappings = [
            'á' => 'a', 'Á' => 'A',
            'é' => 'e', 'É' => 'E',
            'í' => 'i', 'Í' => 'I',
            'ó' => 'o', 'Ó' => 'O',
            'ú' => 'u', 'Ú' => 'U',
            'ü' => 'u', 'Ü' => 'U',
            'ñ' => 'n', 'Ñ' => 'N',
        ];

        $normalizedString = strtr($string, $spanishMappings);

        $normalizedString = preg_replace_callback('/\bll(?=[aeiouáéíóúü])/i', function ($matches) {
            $match = $matches[0];
            if ($match === 'LL') return 'Y';
            if ($match === 'Ll') return 'Y';
            return 'y';
        }, $normalizedString);

        $normalizedString = preg_replace_callback('/rr/i', function ($matches) {
            $match = $matches[0];
            if ($match === 'RR') return 'R';
            if ($match === 'Rr') return 'R';
            return 'r';
        }, $normalizedString);

        return $normalizedString;
    }
}
