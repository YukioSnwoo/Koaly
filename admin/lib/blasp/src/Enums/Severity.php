<?php

namespace Blaspsoft\Blasp\Enums;

enum Severity: string
{
    case Mild = 'mild';
    case Moderate = 'moderate';
    case High = 'high';
    case Extreme = 'extreme';

    public function weight(): int
    {
        return match ($this) {
            self::Mild => 5,
            self::Moderate => 15,
            self::High => 30,
            self::Extreme => 50,
        };
    }

    public function isAtLeast(self $minimum): bool
    {
        return $this->weight() >= $minimum->weight();
    }
}
