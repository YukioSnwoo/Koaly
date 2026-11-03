<?php

namespace Blaspsoft\Blasp\Core\Contracts;

interface MaskStrategyInterface
{
    public function mask(string $word, int $length): string;
}
