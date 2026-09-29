<?php

namespace DaziWeb\RebDa11;

/**
 * Writes a DA11 file after REB-VB 23.003, Ausgabe 2009: one DA 00 record,
 * DA 11 records (80 characters, CP850, CRLF) and a closing "99" record.
 *
 * Calculations longer than one line (38 characters in columns 32-69) are
 * continued in further FN 91 lines, broken only between tokens (values,
 * operators, function names, brackets are never split), at most 20 lines;
 * factor and sign stand in the first line, the "=" ends the last one. Every
 * calculation line gets a unique address (sheet 0001-9999, line A0-Z0).
 */
final class Da11Writer
{
    private const EXPRESSION_WIDTH = 38;

    private const MAX_LINES = 20;

    private const TEXT_WIDTH = 56;

    private int $address = 0;

    /**
     * @param  iterable<Calculation|TextLine>  $entries
     */
    public function write(Header $header, iterable $entries): string
    {
        $this->address = 0;
        $records = [$this->header($header)];
        foreach ($entries as $entry) {
            array_push($records, ...($entry instanceof TextLine ? [$this->text($entry)] : $this->calculation($entry)));
        }
        $records[] = str_pad('99', 80);

        $content = implode("\r\n", $records)."\r\n";
        $encoded = mb_convert_encoding($content, 'CP850', 'UTF-8');

        return $encoded;
    }

    /**
     * Checks one entry on its own (OZ, factor, expression, line count), so a
     * caller can report which of its rows cannot be written. write() applies
     * the same checks.
     */
    public function validate(Calculation|TextLine $entry): void
    {
        $this->oz($entry->oz);
        if ($entry instanceof Calculation) {
            $this->factor($entry->factor);
            $this->wrap(Tokenizer::tokens($entry->expression));
        }
    }

    private function header(Header $header): string
    {
        return '00'
            .$this->field($header->order, 8)
            .'23.003'
            .'2009'
            .$this->field($header->title, 51)
            .$header->ozMask->toString();
    }

    private function text(TextLine $line): string
    {
        return '11'.$this->oz($line->oz).' *'.$this->field($line->text, self::TEXT_WIDTH).str_repeat(' ', 11);
    }

    /** @return list<string> */
    private function calculation(Calculation $calculation): array
    {
        $lines = $this->wrap(Tokenizer::tokens($calculation->expression));
        $records = [];
        foreach ($lines as $index => $text) {
            $first = $index === 0;
            $last = $index === count($lines) - 1;
            $records[] = '11'
                .$this->oz($calculation->oz)
                .'  '
                .$this->field($first ? $calculation->explanation : '', 9)
                .($first && $calculation->deduction ? '-' : ' ')
                .($first ? $this->factor($calculation->factor) : str_repeat(' ', 6))
                .'91'
                .$this->field($text.($last ? '=' : ''), self::EXPRESSION_WIDTH)
                .$this->nextAddress()
                .str_repeat(' ', 5);
        }

        return $records;
    }

    /**
     * @param  list<string>  $tokens
     * @return list<string>
     */
    private function wrap(array $tokens): array
    {
        $lines = [''];
        foreach ($tokens as $token) {
            $current = array_key_last($lines);
            // The last line also needs room for the closing "=".
            if ($lines[$current] !== '' && mb_strlen($lines[$current].$token) > self::EXPRESSION_WIDTH - 1) {
                $lines[] = '';
                $current++;
            }
            if (mb_strlen($token) > self::EXPRESSION_WIDTH - 1) {
                throw new Da11Exception("Der Wert „{$token}“ ist zu lang für eine DA11-Zeile.");
            }
            $lines[$current] .= $token;
        }
        if ($lines === ['']) {
            throw new Da11Exception('Leerer Rechenansatz.');
        }
        if (count($lines) > self::MAX_LINES) {
            throw new Da11Exception('Der Rechenansatz ist länger als 20 DA11-Zeilen.');
        }

        return $lines;
    }

    /** Columns 24-29: factor with three implied decimals, blank for 1. */
    private function factor(?string $factor): string
    {
        if ($factor === null || $factor === '' || preg_match('/^0*1([.,]0*)?$/', $factor) === 1) {
            return str_repeat(' ', 6);
        }
        if (preg_match('/^(\d{1,3})(?:[.,](\d{1,3}))?$/', $factor, $match) !== 1) {
            throw new Da11Exception("Der Faktor „{$factor}“ passt nicht in das DA11-Faktorfeld (bis 999,999).");
        }
        $thousandths = ltrim($match[1].str_pad($match[2] ?? '', 3, '0'), '0');

        return str_pad($thousandths === '' ? '0' : $thousandths, 6, ' ', STR_PAD_LEFT);
    }

    private function nextAddress(): string
    {
        $sheet = intdiv($this->address, 26) + 1;
        if ($sheet > 9999) {
            throw new Da11Exception('Zu viele Rechenansatzzeilen für die DA11-Adressierung.');
        }
        $line = chr(ord('A') + $this->address % 26);
        $this->address++;

        return str_pad((string) $sheet, 4, '0', STR_PAD_LEFT).$line.'0';
    }

    private function oz(string $oz): string
    {
        if (mb_strlen($oz) !== 9) {
            throw new Da11Exception("Die OZ „{$oz}“ muss neun Stellen haben (OzMask::format()).");
        }

        return $oz;
    }

    /** Left-aligned, cut or padded to the width (CP850 has one byte per character). */
    private function field(string $value, int $width): string
    {
        $value = str_replace(["\r", "\n", "\t"], ' ', $value);

        return mb_str_pad(mb_substr($value, 0, $width), $width);
    }
}
