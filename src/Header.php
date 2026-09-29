<?php

namespace DaziWeb\RebDa11;

/**
 * The DA 00 record: optional order and heading. The record has room for 8
 * and 51 characters; longer values are cut, line breaks become spaces.
 */
final class Header
{
    public function __construct(
        public readonly string $title,
        public readonly OzMask $ozMask,
        public readonly string $order = '',
    ) {}
}
