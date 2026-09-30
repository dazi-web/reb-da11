# dazi-web/reb-da11

Writer and reader for **DA11** files after **REB-VB 23.003, Ausgabe 2009**
("Allgemeine Mengenberechnung"), the German exchange format for quantity
calculations (Aufmaß) between contractor and client / AVA software.

Plain PHP 8.3+, no framework, no dependencies besides `ext-mbstring`.
License: MIT.

Deutsch: PHP-Bibliothek zum **Schreiben und Lesen von DA11-Dateien** (Aufmaß bzw.
Mengenberechnung, REB-VB 23.003, Ausgabe 2009) für die elektronische
Bauabrechnung zwischen Auftragnehmer, Auftraggeber und AVA-Software.

## What it does

- **DA 00** header: order (8 characters, optional), procedure `23.003`,
  edition `2009`, heading (51 characters), OZ mask (e.g. `1122PPPPI`).
- **DA 11** calculation lines in free notation (**FN 91**), 80 characters:
  OZ (columns 3–11), explanation (14–22), deduction sign (23), factor with
  three implied decimals (24–29), expression (32–69), address (70–75).
  Long expressions continue in further lines, broken only between tokens,
  at most 20 lines; factor and sign in the first line, `=` ends the last.
- **Text lines** (`*` in column 13), e.g. for full labels or remarks.
- Addresses are assigned in order (`0001A0` … `0001Z0`, `0002A0` …).
- Output in **CP850** with **CRLF**, as used by common AVA software.
- **Reader** that joins continuation lines again – for checks and imports
  (FN 91 only).

Not included on purpose: the fixed-field formulas (FN 00, FN 01 …),
intermediate sums (V, KZ `Z`/`P`/`H`), references to addresses and DA11S.

## Usage

```php
use DaziWeb\RebDa11\Calculation;
use DaziWeb\RebDa11\Da11Writer;
use DaziWeb\RebDa11\Header;
use DaziWeb\RebDa11\OzMask;
use DaziWeb\RebDa11\TextLine;

$mask = OzMask::fromLevels([2, 2], positionLength: 4, indexLength: 1); // 1122PPPPI
$oz = $mask->format(['01', '04', '20']);                                 // "01040020 "

$file = (new Da11Writer)->write(new Header('Musterbau Nord, Gebäude A', $mask, '2026-17'), [
    new TextLine($oz, 'Wand Treppenhaus, EG'),
    new Calculation($oz, '5,20*2,60', explanation: 'Wand', factor: '2'),
    new Calculation($oz, '1,20*1,30', explanation: 'Fenster', factor: '2', deduction: true),
]);

file_put_contents('aufmass.d11', $file); // CP850 bytes
```

Expressions must already be in REB syntax: `+ - * / **`, round brackets,
decimal comma or point, `sin cos tan asin acos atan` with angles in **gon**.
There is no `sqrt` (use `** 0,5`), a negative value must be bracketed
(`3*(-4)`), and powers of 1 are not allowed. Invalid input throws
`Da11Exception` with a German message.

```php
use DaziWeb\RebDa11\Da11Reader;

$document = (new Da11Reader)->read(file_get_contents('aufmass.d11'));
// ['header' => [...], 'entries' => [['type' => 'calculation', 'oz' => ..., 'expression' => ..., ...], ...]]
```

## Status

The record layout follows the published REB-VB 23.003 (2009, BASt) and was
compared with a real DA11 file from practice. Acceptance by a specific AVA
product has not been verified yet – please report results.

## Tests

```bash
composer install
vendor/bin/phpunit tests
```
