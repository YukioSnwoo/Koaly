<?php

namespace Blaspsoft\Blasp\Core\Matchers;

class CompoundWordDetector
{
    private const SUFFIXES = ['s', 'es', 'ed', 'er', 'ers', 'est', 'ing', 'ings', 'ly', 'y'];

    public function isPureAlphaSubstring(string $matchedText, string $fullWord, string $profanityKey, array $profanityExpressions): bool
    {
        if (!preg_match('/^[a-zA-Z]+$/', $matchedText)) {
            return false;
        }

        if (!preg_match('/^[a-zA-Z]+$/', $fullWord)) {
            return false;
        }

        if (strlen($fullWord) <= strlen($matchedText)) {
            return false;
        }

        if (strlen($matchedText) > strlen($profanityKey)) {
            return false;
        }

        $matchLower = strtolower($matchedText);
        $wordLower = strtolower($fullWord);

        foreach (self::SUFFIXES as $suffix) {
            if ($wordLower === $matchLower . $suffix) {
                return false;
            }
        }

        $pos = strpos($wordLower, $matchLower);
        if ($pos !== false) {
            $remainder = substr($wordLower, 0, $pos) . substr($wordLower, $pos + strlen($matchLower));
            foreach ($profanityExpressions as $profanity => $_) {
                if (strlen($profanity) >= 3 && stripos($remainder, $profanity) !== false) {
                    return false;
                }
            }
        }

        return true;
    }
}
