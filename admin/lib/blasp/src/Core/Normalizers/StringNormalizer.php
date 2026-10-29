<?php

namespace Blaspsoft\Blasp\Core\Normalizers;

interface StringNormalizer
{
    public function normalize(string $string): string;
}
