<?php

namespace App\Services\Catalog\Transfer;

use Generator;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use RuntimeException;

/**
 * Baca & tulis file tabel (CSV / XLSX) secara streaming, jadi file
 * berisi ribuan baris tidak perlu dimuat sekaligus ke memori.
 */
class Spreadsheet
{
    public const FORMATS = ['xlsx', 'csv'];

    /**
     * Baris data sebagai array asosiatif [header_normal => nilai], dengan
     * nomor baris (sesuai tampilan di Excel) sebagai key. Baris kosong dilewati.
     *
     * @param  array<string, string>  $aliases  header alternatif => header baku
     * @return Generator<int, array<string, mixed>>
     */
    public static function read(string $path, string $format, array $aliases = []): Generator
    {
        $reader = self::reader($path, $format);
        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $headers = null;
                $line = 0;

                foreach ($sheet->getRowIterator() as $row) {
                    $line++;
                    $values = $row->toArray();

                    if ($headers === null) {
                        if (self::isBlank($values)) {
                            continue;
                        }
                        $headers = array_map(fn ($h) => self::normalizeHeader((string) $h, $aliases), $values);

                        continue;
                    }

                    if (self::isBlank($values)) {
                        continue;
                    }

                    $data = [];
                    foreach ($headers as $i => $header) {
                        if ($header !== '' && ! array_key_exists($header, $data)) {
                            $data[$header] = self::clean($values[$i] ?? null);
                        }
                    }

                    yield $line => $data;
                }

                // Hanya sheet pertama yang dibaca (sheet lain berisi petunjuk).
                break;
            }
        } finally {
            $reader->close();
        }
    }

    /** Header pada baris pertama, sudah dinormalkan. */
    public static function headers(string $path, string $format, array $aliases = []): array
    {
        $reader = self::reader($path, $format);
        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    if (! self::isBlank($row->toArray())) {
                        return array_values(array_filter(array_map(
                            fn ($h) => self::normalizeHeader((string) $h, $aliases), $row->toArray()
                        )));
                    }
                }
            }
        } finally {
            $reader->close();
        }

        return [];
    }

    /**
     * Tulis satu file. $sheets: [nama sheet => iterable baris]; baris pertama
     * tiap sheet dianggap header (ditebalkan). CSV hanya memakai sheet pertama.
     *
     * @param  array<string, iterable<array>>  $sheets
     */
    public static function write(string $path, string $format, array $sheets): void
    {
        $format === 'csv' ? self::writeCsv($path, reset($sheets)) : self::writeXlsx($path, $sheets);
    }

    /** Baris kosong dipertahankan agar nomor baris sama dengan yang terlihat di Excel. */
    private static function reader(string $path, string $format): CsvReader|XlsxReader
    {
        return match ($format) {
            'csv' => new CsvReader(new CsvOptions(SHOULD_PRESERVE_EMPTY_ROWS: true, FIELD_DELIMITER: self::detectDelimiter($path))),
            'xlsx' => new XlsxReader(new XlsxOptions(SHOULD_PRESERVE_EMPTY_ROWS: true)),
            default => throw new RuntimeException('Format file tidak didukung.'),
        };
    }

    private static function writeCsv(string $path, iterable $rows): void
    {
        $out = fopen($path, 'w');
        // BOM agar Excel membaca UTF-8 dengan benar.
        fwrite($out, "\xEF\xBB\xBF");

        foreach ($rows as $row) {
            fputcsv($out, array_map(self::escapeFormula(...), $row), escape: '');
        }

        fclose($out);
    }

    private static function writeXlsx(string $path, array $sheets): void
    {
        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $bold = new Style(fontBold: true);
        $first = true;

        foreach ($sheets as $name => $rows) {
            $sheet = $first ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
            $sheet->setName($name);

            $header = true;
            foreach ($rows as $row) {
                if ($header) {
                    $sheet->setColumnWidthForRange(18, 1, max(1, count($row)));
                }
                $style = $header ? $bold : null;
                $writer->addRow(new Row(array_map(fn ($value) => self::cell($value, $style), array_values($row))));
                $header = false;
            }
            $first = false;
        }

        $writer->close();
    }

    /** Teks selalu ditulis sebagai teks (bukan rumus), angka tetap angka. */
    private static function cell(mixed $value, ?Style $style): Cell
    {
        return match (true) {
            $value === null || $value === '' => new Cell\EmptyCell(null, $style),
            is_int($value), is_float($value) => new Cell\NumericCell($value, $style),
            is_bool($value) => new Cell\BooleanCell($value, $style),
            default => new Cell\StringCell((string) $value, $style),
        };
    }

    /** Cegah CSV/formula injection saat file dibuka di Excel. */
    public static function escapeFormula(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    /** "Nama Produk*" / "nama produk (wajib)" => "nama_produk". */
    public static function normalizeHeader(string $header, array $aliases = []): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);
        $header = preg_replace('/\(.*?\)|\*/', '', mb_strtolower(trim($header)));
        $header = trim(preg_replace('/[^a-z0-9]+/', '_', $header), '_');

        return $aliases[$header] ?? $header;
    }

    private static function clean(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value)) {
            $value = trim($value);
            // Nilai teks yang diberi kutip pengaman (lihat escapeFormula) dikembalikan.
            if (str_starts_with($value, "'") && strlen($value) > 1 && in_array($value[1], ['=', '+', '-', '@'], true)) {
                $value = substr($value, 1);
            }

            return $value === '' ? null : $value;
        }

        return $value;
    }

    private static function isBlank(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== null && trim((string) ($value instanceof \DateTimeInterface ? 'x' : $value)) !== '') {
                return false;
            }
        }

        return true;
    }

    /** CSV dari Excel berbahasa Indonesia biasanya memakai titik koma. */
    private static function detectDelimiter(string $path): string
    {
        $handle = fopen($path, 'r');
        $line = (string) fgets($handle);
        fclose($handle);

        return substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';
    }
}
