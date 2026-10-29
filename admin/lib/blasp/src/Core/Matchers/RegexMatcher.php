<?php

namespace Blaspsoft\Blasp\Core\Matchers;

class RegexMatcher
{
    private const SEPARATOR_PLACEHOLDER = '{!!}';
    private const ESCAPED_SEPARATOR_CHARACTERS = ['\s'];

    public function generateExpressions(array $profanities, array $separators, array $substitutions): array
    {
        $separatorExpression = $this->generateSeparatorExpression($separators);
        $substitutionExpressions = $this->generateSubstitutionExpressions($substitutions);

        $profanityExpressions = [];

        foreach ($profanities as $profanity) {
            $profanityExpressions[$profanity] = $this->generateProfanityExpression(
                $profanity,
                $substitutionExpressions,
                $separatorExpression
            );
        }

        return $profanityExpressions;
    }

    public function generateSeparatorExpression(array $separators): string
    {
        $normalSeparators = array_filter($separators, fn($sep) => $sep !== '.');

        $pattern = $this->generateEscapedExpression($normalSeparators, self::ESCAPED_SEPARATOR_CHARACTERS, '');

        return '(?:' . $pattern . '|\.(?=\w)){0,3}?';
    }

    public function generateSubstitutionExpressions(array $substitutions): array
    {
        $characterExpressions = [];

        foreach ($substitutions as $character => $substitutionOptions) {
            $hasMultiChar = false;
            foreach ($substitutionOptions as $option) {
                if (mb_strlen($option, 'UTF-8') > 1 && !preg_match('/^\\\\.$/u', $option)) {
                    $hasMultiChar = true;
                    break;
                }
            }

            if ($hasMultiChar) {
                $escaped = array_map(function ($opt) {
                    if (preg_match('/^\\\\.$/u', $opt)) {
                        return $opt;
                    }
                    return preg_quote($opt, '/');
                }, $substitutionOptions);
                $characterExpressions[$character] = '(?:' . implode('|', $escaped) . ')+' . self::SEPARATOR_PLACEHOLDER;
            } else {
                $characterExpressions[$character] = $this->generateEscapedExpression($substitutionOptions, [], '+') . self::SEPARATOR_PLACEHOLDER;
            }
        }

        return $characterExpressions;
    }

    public function generateProfanityExpression(string $profanity, array $substitutionExpressions, string $separatorExpression): string
    {
        $plainSubstitutions = [];
        foreach ($substitutionExpressions as $pattern => $replacement) {
            $plainKey = trim($pattern, '/');
            $plainSubstitutions[$plainKey] = $replacement;
        }

        uksort($plainSubstitutions, fn($a, $b) => mb_strlen($b, 'UTF-8') - mb_strlen($a, 'UTF-8'));

        $expression = '';
        $i = 0;
        $len = mb_strlen($profanity, 'UTF-8');

        while ($i < $len) {
            $matched = false;
            foreach ($plainSubstitutions as $key => $replacement) {
                $keyLen = mb_strlen($key, 'UTF-8');
                if ($i + $keyLen <= $len && mb_substr($profanity, $i, $keyLen, 'UTF-8') === $key) {
                    $expression .= $replacement;
                    $i += $keyLen;
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                $expression .= preg_quote(mb_substr($profanity, $i, 1, 'UTF-8'), '/');
                $i++;
            }
        }

        $expression = str_replace(self::SEPARATOR_PLACEHOLDER, $separatorExpression, $expression);
        $expression = '/' . $expression . '/iu';

        return $expression;
    }

    private function generateEscapedExpression(array $characters = [], array $escapedCharacters = [], string $quantifier = '*?'): string
    {
        $regex = $escapedCharacters;

        foreach ($characters as $character) {
            $regex[] = preg_quote($character, '/');
        }

        return '[' . implode('', $regex) . ']' . $quantifier;
    }
}
