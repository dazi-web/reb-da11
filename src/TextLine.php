<?php

namespace DaziWeb\RebDa11;

/** A text line ("*" in column 13, text in columns 14-69); not part of any calculation. */
final class TextLine
{
    public function __construct(
        public readonly string $oz,
        public readonly string $text,
    ) {}
}
