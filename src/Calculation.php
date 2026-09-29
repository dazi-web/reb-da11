<?php

namespace DaziWeb\RebDa11;

/**
 * One calculation ("Rechenansatz") in free notation, FN 91. The expression
 * uses REB syntax (+ - * / **, round brackets, sin/cos/tan/asin/acos/atan
 * in gon, decimal comma or point) and is written without the final "=".
 * A deduction is subtracted from the position total (minus in column 23);
 * the factor (up to 999.999, three decimals) multiplies the result.
 * The explanation has room for nine characters (columns 14-22) and is cut
 * beyond that; put longer descriptions into a TextLine before the entry.
 */
final class Calculation
{
    public function __construct(
        public readonly string $oz,
        public readonly string $expression,
        public readonly string $explanation = '',
        public readonly ?string $factor = null,
        public readonly bool $deduction = false,
    ) {}
}
