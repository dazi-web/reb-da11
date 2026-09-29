<?php

namespace DaziWeb\RebDa11;

/**
 * Splits a free-notation expression (FN 91) into the tokens that may not be
 * broken by a line change: values (with decimal comma/point and exponent),
 * function names or addresses, "**" and single operators or brackets.
 */
final class Tokenizer
{
    /** Built-in functions of FN 91 (angles in gon). */
    private const FUNCTIONS = ['sin', 'cos', 'tan', 'asin', 'acos', 'atan'];

    /** @return list<string> */
    public static function tokens(string $expression): array
    {
        $expression = preg_replace('/\s+/', '', $expression) ?? '';
        if (str_ends_with($expression, '=')) {
            $expression = substr($expression, 0, -1);
        }
        if (preg_match_all('/\*\*|[0-9]+(?:[.,][0-9]+)?(?:E[+-]?[0-9]+)?[A-Z]?[0-9]?|[A-Za-z]+|[-+*\/()]/', $expression, $matches) === false
            || implode('', $matches[0]) !== $expression) {
            throw new Da11Exception("Der Rechenansatz „{$expression}“ enthält Zeichen, die REB-VB 23.003 nicht erlaubt.");
        }

        foreach ($matches[0] as $token) {
            if (preg_match('/^[A-Za-z]+$/', $token) === 1 && ! in_array(strtolower($token), self::FUNCTIONS, true)) {
                throw new Da11Exception("Die Funktion „{$token}“ gibt es in REB-VB 23.003 nicht (erlaubt: sin, cos, tan, asin, acos, atan).");
            }
        }

        return $matches[0];
    }
}
