<?php

namespace Blaspsoft\Blasp\Core\Masking;

use Blaspsoft\Blasp\Core\Contracts\MaskStrategyInterface;

class CharacterMask implements MaskStrategyInterface
{
    public function __construct(
        private string $character = '*'
    ) {
        $this->character = mb_substr($character, 0, 1);
    }

    public function mask(string $word, int $length): string
    {
        return str_repeat($this->character, $length);
    }
}
