<?php

namespace Blaspsoft\Blasp\Core;

use Blaspsoft\Blasp\Enums\Severity;
use JsonSerializable;
use Stringable;
use Countable;

class Result implements JsonSerializable, Stringable, Countable
{
    /** @var MatchedWord[] */
    private readonly array $matchedWords;

    public function __construct(
        private readonly string $originalText,
        private readonly string $cleanText,
        array $matchedWords,
        private readonly int $scoreValue,
    ) {
        $this->matchedWords = array_values($matchedWords);
    }

    // --- New v4 API ---

    public function isClean(): bool
    {
        return $this->matchedWords === [];
    }

    public function isOffensive(): bool
    {
        return $this->matchedWords !== [];
    }

    public function clean(): string
    {
        return $this->cleanText;
    }

    public function original(): string
    {
        return $this->originalText;
    }

    public function score(): int
    {
        return $this->scoreValue;
    }

    public function count(): int
    {
        return \count($this->matchedWords);
    }

    public function uniqueWords(): array
    {
        return array_values(array_unique(array_map(fn (MatchedWord $w) => $w->base, $this->matchedWords)));
    }

    public function severity(): ?Severity
    {
        $mayor = null;
        foreach ($this->matchedWords as $w) {
            if ($mayor === null || $w->severity->weight() > $mayor->weight()) {
                $mayor = $w->severity;
            }
        }

        return $mayor;
    }

    public function words(): array
    {
        return $this->matchedWords;
    }

    // --- Deprecated v3 backward-compat methods ---

    /** @deprecated Use isOffensive() instead */
    public function hasProfanity(): bool
    {
        return $this->isOffensive();
    }

    /** @deprecated Use clean() instead */
    public function getCleanString(): string
    {
        return $this->clean();
    }

    /** @deprecated Use original() instead */
    public function getSourceString(): string
    {
        return $this->original();
    }

    /** @deprecated Use count() instead */
    public function getProfanitiesCount(): int
    {
        return $this->count();
    }

    /** @deprecated Use uniqueWords() instead */
    public function getUniqueProfanitiesFound(): array
    {
        return $this->uniqueWords();
    }

    // --- Static constructors ---

    public static function none(string $text): self
    {
        return new self($text, $text, [], 0);
    }

    public static function fromArray(array $data): self
    {
        $matchedWords = [];
        foreach ($data['words'] ?? [] as $wordData) {
            $matchedWords[] = new MatchedWord(
                text: $wordData['text'],
                base: $wordData['base'],
                severity: Severity::tryFrom($wordData['severity']) ?? Severity::High,
                position: $wordData['position'],
                length: $wordData['length'],
                language: $wordData['language'] ?? 'english',
            );
        }

        return new self(
            $data['original'] ?? '',
            $data['clean'] ?? '',
            $matchedWords,
            $data['score'] ?? 0,
        );
    }

    public static function withMatches(array $words, string $originalText = '', string $cleanText = ''): self
    {
        $matchedWords = [];
        foreach ($words as $word) {
            if ($word instanceof MatchedWord) {
                $matchedWords[] = $word;
            } else {
                $matchedWords[] = new MatchedWord(
                    text: $word,
                    base: $word,
                    severity: Severity::High,
                    position: 0,
                    length: mb_strlen($word),
                );
            }
        }

        $totalWords = max(1, count(preg_split('/\s+/u', trim($originalText ?: implode(' ', $words)), -1, PREG_SPLIT_NO_EMPTY)));
        $score = Score::calculate($matchedWords, $totalWords);

        return new self($originalText, $cleanText ?: $originalText, $matchedWords, $score);
    }

    // --- Serialization ---

    public function toArray(): array
    {
        return [
            'original' => $this->originalText,
            'clean' => $this->cleanText,
            'is_offensive' => $this->isOffensive(),
            'score' => $this->scoreValue,
            'count' => $this->count(),
            'unique_words' => $this->uniqueWords(),
            'severity' => $this->severity()?->value,
            'words' => array_map(fn (MatchedWord $w) => $w->toArray(), $this->matchedWords),
        ];
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        return $this->cleanText;
    }
}
