<?php

namespace Blaspsoft\Blasp\Core\Masking;

use Blaspsoft\Blasp\Core\Contracts\MaskStrategyInterface;

class GrawlixMask implements MaskStrategyInterface
{
    private const CHARS = ['!', '@', '#', '$', '%'];

    public function mask(string $word, int $length): string
    {
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= self::CHARS[$i % count(self::CHARS)];
        }
        return $result;
    }
}
