<?php

namespace DaziWeb\RebDa11;

use InvalidArgumentException;

/** Input that cannot be written as, or read from, a valid DA11 file. */
final class Da11Exception extends InvalidArgumentException {}
