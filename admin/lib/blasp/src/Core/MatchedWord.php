<?php

namespace Blaspsoft\Blasp\Core;

use Blaspsoft\Blasp\Enums\Severity;
use JsonSerializable;

readonly class MatchedWord implements JsonSerializable
{
    public function __construct(
        public string $text,
        public string $base,
        public Severity $severity,
        public int $position,
        public int $length,
        public string $language = 'english',
    ) {}

    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'base' => $this->base,
            'severity' => $this->severity->value,
            'position' => $this->position,
            'length' => $this->length,
            'language' => $this->language,
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
