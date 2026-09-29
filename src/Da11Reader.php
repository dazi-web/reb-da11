<?php

namespace DaziWeb\RebDa11;

/**
 * Reads a DA11 file (REB-VB 23.003) back into its header, calculations and
 * text lines: continuation lines of a FN 91 calculation are joined until the
 * closing "=". Meant for checking written files and for imports; only FN 91
 * is interpreted, other formula numbers are rejected.
 */
final class Da11Reader
{
    /**
     * @return array{header: array{order: string, procedure: string, edition: string, title: string, ozMask: string}, entries: list<array{type: 'calculation', oz: string, explanation: string, deduction: bool, factor: string|null, expression: string, address: string}|array{type: 'text', oz: string, text: string}>}
     */
    public function read(string $content): array
    {
        $lines = preg_split('/\r?\n/', rtrim(mb_convert_encoding($content, 'UTF-8', 'CP850'), "\r\n")) ?: [];
        $header = null;
        $entries = [];
        $open = null;

        foreach ($lines as $number => $line) {
            if (mb_strlen($line) !== 80) {
                throw new Da11Exception('Satz '.($number + 1).' hat nicht 80 Zeichen.');
            }
            $type = mb_substr($line, 0, 2);
            if ($type === '00') {
                $header = [
                    'order' => rtrim(mb_substr($line, 2, 8)),
                    'procedure' => mb_substr($line, 10, 6),
                    'edition' => mb_substr($line, 16, 4),
                    'title' => rtrim(mb_substr($line, 20, 51)),
                    'ozMask' => mb_substr($line, 71, 9),
                ];

                continue;
            }
            if ($type === '99') {
                break;
            }
            if ($type !== '11') {
                throw new Da11Exception('Unbekannte Datenart '.$type.' in Satz '.($number + 1).'.');
            }
            $oz = mb_substr($line, 2, 9);
            if (mb_substr($line, 12, 1) === '*') {
                $entries[] = ['type' => 'text', 'oz' => $oz, 'text' => rtrim(mb_substr($line, 13, 56))];

                continue;
            }
            if (mb_substr($line, 29, 2) !== '91') {
                throw new Da11Exception('Nur FN 91 wird gelesen (Satz '.($number + 1).').');
            }
            $open ??= [
                'type' => 'calculation',
                'oz' => $oz,
                'explanation' => rtrim(mb_substr($line, 13, 9)),
                'deduction' => mb_substr($line, 22, 1) === '-',
                'factor' => $this->factor(mb_substr($line, 23, 6)),
                'expression' => '',
                'address' => '',
            ];
            $open['expression'] .= trim(mb_substr($line, 31, 38));
            $open['address'] = mb_substr($line, 69, 6);
            if (str_ends_with($open['expression'], '=')) {
                $open['expression'] = substr($open['expression'], 0, -1);
                $entries[] = $open;
                $open = null;
            }
        }
        if ($header === null || $open !== null) {
            throw new Da11Exception($header === null ? 'Kein DA 00-Satz.' : 'Unvollständiger Rechenansatz am Dateiende.');
        }

        return ['header' => $header, 'entries' => $entries];
    }

    private function factor(string $field): ?string
    {
        $digits = trim($field);
        if ($digits === '') {
            return null;
        }
        $digits = str_pad($digits, 4, '0', STR_PAD_LEFT);

        return (ltrim(substr($digits, 0, -3), '0') ?: '0').'.'.substr($digits, -3);
    }
}
