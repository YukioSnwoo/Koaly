<?php

namespace Blaspsoft\Blasp\Core\Normalizers;

class GermanNormalizer implements StringNormalizer
{
    public function normalize(string $string): string
    {
        $germanMappings = [
            'ä' => 'ae', 'Ä' => 'AE',
            'ö' => 'oe', 'Ö' => 'OE',
            'ü' => 'ue', 'Ü' => 'UE',
            'ß' => 'ss',
        ];

        $normalizedString = strtr($string, $germanMappings);

        $normalizedString = preg_replace_callback('/sch/i', function ($matches) {
            $match = $matches[0];
            if ($match === 'SCH') return 'SH';
            if ($match === 'Sch') return 'Sh';
            return 'sh';
        }, $normalizedString);

        return $normalizedString;
    }
}
