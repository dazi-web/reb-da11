<?php

namespace DaziWeb\RebDa11;

/**
 * The OZ mask of the DA 00 record (columns 72-80), REB-VB 23.003 section
 * 2.2.3.1: placeholders 1-4 for the hierarchy levels S1-S4, P for the
 * position number and I for the index, padded with "0" to nine characters.
 * Example: "1122PPPPI" = two digits S1, two digits S2, four digits position,
 * one index character.
 */
final class OzMask
{
    private const LENGTH = 9;

    private function __construct(private readonly string $mask) {}

    public static function fromString(string $mask): self
    {
        $mask = str_pad($mask, self::LENGTH, '0');
        if (strlen($mask) !== self::LENGTH || preg_match('/^(1*)(2*)(3*)(4*)(P+)(I?)(0*)$/', $mask) !== 1) {
            throw new Da11Exception("Ungültige OZ-Maske: {$mask}");
        }

        return new self($mask);
    }

    /**
     * @param  list<int>  $levelLengths  digits per hierarchy level, at most four
     */
    public static function fromLevels(array $levelLengths, int $positionLength, int $indexLength = 0): self
    {
        if (count($levelLengths) > 4 || $positionLength < 1 || $indexLength > 1) {
            throw new Da11Exception('Diese OZ-Gliederung lässt sich nicht als DA11-OZ-Maske abbilden.');
        }
        $mask = '';
        foreach ($levelLengths as $level => $length) {
            $mask .= str_repeat((string) ($level + 1), $length);
        }
        $mask .= str_repeat('P', $positionLength).str_repeat('I', $indexLength);
        if (strlen($mask) > self::LENGTH) {
            throw new Da11Exception('Die OZ ist länger als die neun Stellen einer DA11-OZ.');
        }

        return self::fromString($mask);
    }

    public function toString(): string
    {
        return $this->mask;
    }

    /**
     * The nine-character OZ of a DA 11 record (columns 3-11) from its numeric
     * parts (one per level, then the position) and an optional index.
     *
     * @param  list<string>  $parts
     */
    public function format(array $parts, ?string $index = null): string
    {
        preg_match_all('/1+|2+|3+|4+|P+/', rtrim($this->mask, 'I0'), $groups);
        if (count($parts) !== count($groups[0])) {
            throw new Da11Exception('Die OZ passt nicht zur OZ-Maske.');
        }
        $oz = '';
        foreach ($groups[0] as $position => $group) {
            $part = $parts[$position];
            if (preg_match('/^\d+$/', $part) !== 1 || strlen($part) > strlen($group)) {
                throw new Da11Exception("OZ-Teil „{$part}“ ist nicht numerisch oder zu lang.");
            }
            $oz .= str_pad($part, strlen($group), '0', STR_PAD_LEFT);
        }
        if (str_contains($this->mask, 'I')) {
            if ($index !== null && preg_match('/^[A-Z0-9]$/', $index) !== 1) {
                throw new Da11Exception("Ungültiger OZ-Index „{$index}“.");
            }
            $oz .= $index ?? ' ';
        } elseif ($index !== null && $index !== '') {
            throw new Da11Exception('Die OZ-Maske kennt keinen Index.');
        }

        return str_pad($oz, self::LENGTH, '0');
    }
}
