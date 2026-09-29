<?php

namespace DaziWeb\RebDa11\Tests;

use DaziWeb\RebDa11\Calculation;
use DaziWeb\RebDa11\Da11Exception;
use DaziWeb\RebDa11\Da11Reader;
use DaziWeb\RebDa11\Da11Writer;
use DaziWeb\RebDa11\Header;
use DaziWeb\RebDa11\OzMask;
use DaziWeb\RebDa11\TextLine;
use PHPUnit\Framework\TestCase;

final class Da11WriterTest extends TestCase
{
    private function mask(): OzMask
    {
        return OzMask::fromString('1122PPPPI');
    }

    /** @return list<string> */
    private function records(string $content): array
    {
        return explode("\r\n", rtrim(mb_convert_encoding($content, 'UTF-8', 'CP850'), "\r\n"));
    }

    public function test_writes_header_calculation_and_end_record_in_the_reb_columns(): void
    {
        $oz = $this->mask()->format(['01', '01', '10']);
        $content = (new Da11Writer)->write(new Header('Musterbau Nord, Gebäude A', $this->mask(), 'A-17'), [
            new Calculation($oz, '(4,25+2,10+4,25)*2,5', 'R 1.01'),
        ]);
        $records = $this->records($content);

        $this->assertSame('00A-17    23.0032009Musterbau Nord, Gebäude A                          1122PPPPI', $records[0]);
        $this->assertSame('1101010010   R 1.01          91(4,25+2,10+4,25)*2,5=                 0001A0     ', $records[1]);
        $this->assertSame(str_pad('99', 80), $records[2]);
        foreach ($records as $record) {
            $this->assertSame(80, mb_strlen($record));
        }
        $this->assertStringEndsWith("\r\n", $content);
        $this->assertStringContainsString("\x84", $content, 'ä is written as CP850 0x84');
    }

    public function test_deduction_factor_and_text_lines(): void
    {
        $oz = $this->mask()->format(['01', '04', '20']);
        $records = $this->records((new Da11Writer)->write(new Header('X', $this->mask()), [
            new TextLine($oz, 'Wand Flur, EG, Raum 1.04'),
            new Calculation($oz, '5,20*2,60', 'Wand', '2'),
            new Calculation($oz, '1,01*2,135', 'Tür', '2', deduction: true),
            new Calculation($oz, '1,5', '', '12.345'),
        ]));

        $this->assertSame('1101040020  *Wand Flur, EG, Raum 1.04', rtrim($records[1]));
        $this->assertSame('1101040020   Wand        200091', mb_substr($records[2], 0, 31));
        $this->assertSame('1101040020   Tür      -  200091', mb_substr($records[3], 0, 31));
        $this->assertSame(' 12345', mb_substr($records[4], 23, 6));
        $this->assertSame(['0001A0', '0001B0', '0001C0'], [mb_substr($records[2], 69, 6), mb_substr($records[3], 69, 6), mb_substr($records[4], 69, 6)]);
    }

    public function test_long_calculations_continue_between_tokens_and_end_with_equals(): void
    {
        $oz = $this->mask()->format(['01', '01', '10']);
        $expression = '(1234,567+2345,678+3456,789+4567,891)*(5678,912-6789,123)/7,5';
        $records = $this->records((new Da11Writer)->write(new Header('X', $this->mask()), [new Calculation($oz, $expression, 'lang', '3')]));
        $lines = array_slice($records, 1, -1);

        $this->assertGreaterThan(1, count($lines));
        $this->assertSame('   3000', mb_substr($lines[0], 22, 7), 'factor only in the first line');
        $this->assertSame('       ', mb_substr($lines[1], 22, 7));
        $joined = implode('', array_map(fn (string $line): string => trim(mb_substr($line, 31, 38)), $lines));
        $this->assertSame($expression.'=', $joined);
        foreach ($lines as $index => $line) {
            $this->assertSame('91', mb_substr($line, 29, 2));
            $this->assertSame($index === count($lines) - 1, str_ends_with(trim(mb_substr($line, 31, 38)), '='));
        }
    }

    public function test_addresses_continue_on_the_next_sheet_after_z(): void
    {
        $oz = $this->mask()->format(['01', '01', '10']);
        $entries = array_map(fn (int $i): Calculation => new Calculation($oz, (string) $i), range(1, 27));
        $records = $this->records((new Da11Writer)->write(new Header('X', $this->mask()), $entries));

        $this->assertSame('0001Z0', mb_substr($records[26], 69, 6));
        $this->assertSame('0002A0', mb_substr($records[27], 69, 6));
    }

    public function test_the_reader_returns_what_the_writer_wrote(): void
    {
        $oz = $this->mask()->format(['01', '01', '10']);
        $expression = 'sin((30)*10/9)*4+(2**0,5)*(1234,567+2345,678+3456,789-(-4))';
        $content = (new Da11Writer)->write(new Header('Rückweg', $this->mask()), [
            new TextLine($oz, 'Hinweis'),
            new Calculation($oz, $expression, 'A', '1.5'),
            new Calculation($oz, '0,5*0,5', 'Schacht', null, true),
        ]);

        $read = (new Da11Reader)->read($content);
        $this->assertSame(['order' => '', 'procedure' => '23.003', 'edition' => '2009', 'title' => 'Rückweg', 'ozMask' => '1122PPPPI'], $read['header']);
        $this->assertSame('text', $read['entries'][0]['type']);
        $this->assertSame($expression, $read['entries'][1]['expression']);
        $this->assertSame('1.500', $read['entries'][1]['factor']);
        $this->assertTrue($read['entries'][2]['deduction']);
        $this->assertNull($read['entries'][2]['factor']);
    }

    public function test_masks_are_built_from_levels_and_validated(): void
    {
        $this->assertSame('1122PPPPI', OzMask::fromLevels([2, 2], 4, 1)->toString());
        $this->assertSame('11PPPP000', OzMask::fromLevels([2], 4)->toString());
        $this->assertSame('010010000', OzMask::fromLevels([2], 4)->format(['01', '10']));
        $this->assertSame('01010010A', $this->mask()->format(['1', '1', '10'], 'A'));

        $this->expectException(Da11Exception::class);
        OzMask::fromLevels([4, 4], 4);
    }

    public function test_rejects_what_reb_does_not_allow(): void
    {
        $writer = new Da11Writer;
        $oz = $this->mask()->format(['01', '01', '10']);
        foreach (['sqrt(2)', '2^2', '2 % 3', '[1+2]'] as $expression) {
            try {
                $writer->write(new Header('X', $this->mask()), [new Calculation($oz, $expression)]);
                $this->fail("{$expression} accepted");
            } catch (Da11Exception) {
                $this->addToAssertionCount(1);
            }
        }
        $this->expectException(Da11Exception::class);
        $writer->write(new Header('X', $this->mask()), [new Calculation($oz, '1', '', '1000')]);
    }

    public function test_validate_checks_one_entry_without_writing(): void
    {
        $writer = new Da11Writer;
        $oz = $this->mask()->format(['01', '01', '10']);
        $writer->validate(new Calculation($oz, '1,5*2', 'ok', '12,5'));
        $writer->validate(new TextLine($oz, 'Hinweis'));

        $tooLong = implode('+', array_fill(0, 200, '123,456'));
        foreach ([new Calculation($oz, $tooLong), new Calculation($oz, str_repeat('9', 40)), new Calculation('kurz', '1')] as $entry) {
            try {
                $writer->validate($entry);
                $this->fail('accepted');
            } catch (Da11Exception) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
