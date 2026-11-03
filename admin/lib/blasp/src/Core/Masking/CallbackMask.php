<?php

namespace Blaspsoft\Blasp\Core\Masking;

use Closure;
use Blaspsoft\Blasp\Core\Contracts\MaskStrategyInterface;

class CallbackMask implements MaskStrategyInterface
{
    public function __construct(
        private Closure $callback
    ) {}

    public function mask(string $word, int $length): string
    {
        return ($this->callback)($word, $length);
    }
}
