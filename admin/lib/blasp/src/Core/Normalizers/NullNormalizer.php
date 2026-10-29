<?php

namespace Blaspsoft\Blasp\Core\Normalizers;

class NullNormalizer implements StringNormalizer
{
    public function normalize(string $string): string
    {
        return $string;
    }
}
