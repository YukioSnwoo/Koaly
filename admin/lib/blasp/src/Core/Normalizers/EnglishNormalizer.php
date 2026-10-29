<?php

namespace Blaspsoft\Blasp\Core\Normalizers;

class EnglishNormalizer implements StringNormalizer
{
    public function normalize(string $string): string
    {
        return $string;
    }
}
