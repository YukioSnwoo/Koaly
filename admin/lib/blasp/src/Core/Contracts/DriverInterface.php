<?php

namespace Blaspsoft\Blasp\Core\Contracts;

use Blaspsoft\Blasp\Core\Dictionary;
use Blaspsoft\Blasp\Core\Result;

interface DriverInterface
{
    public function detect(string $text, Dictionary $dictionary, MaskStrategyInterface $mask, array $options = []): Result;
}
