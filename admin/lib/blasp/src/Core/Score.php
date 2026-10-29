<?php

namespace Blaspsoft\Blasp\Core;

class Score
{
    public static function calculate(array $matchedWords, int $totalWordCount): int
    {
        if (empty($matchedWords)) {
            return 0;
        }

        $rawScore = 0;
        foreach ($matchedWords as $word) {
            $rawScore += $word->severity->weight();
        }

        $density = count($matchedWords) / max(1, $totalWordCount);
        $normalized = (int) ($rawScore * (1 + $density));

        return min(100, $normalized);
    }
}
