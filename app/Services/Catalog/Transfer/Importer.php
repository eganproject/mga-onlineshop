<?php

namespace App\Services\Catalog\Transfer;

use App\Enums\ImportStatus;
use App\Models\ProductImport;
use Throwable;

/**
 * Alur impor dua tahap: validate() memeriksa seluruh file tanpa mengubah
 * data (hasilnya jadi pratinjau), lalu commit() membaca ulang file,
 * memvalidasi terhadap data terbaru, dan menyimpan baris yang valid.
 */
abstract class Importer
{
    /** Batas jumlah pesan error & baris pratinjau yang disimpan. */
    public const MAX_ERRORS = 500;

    public const MAX_PREVIEW = 100;

    /** @return array<string, string> header alternatif => header baku */
    abstract public static function aliases(): array;

    /** @return list<list<string>> setiap grup: minimal satu header wajib ada */
    abstract protected function requiredHeaders(): array;

    /**
     * Jalankan validasi (dan simpan bila $apply). Mengembalikan
     * ['total', 'error_rows', 'summary', 'preview', 'errors', 'result'].
     */
    abstract protected function run(ProductImport $import, iterable $rows, bool $apply): array;

    public function validate(ProductImport $import): void
    {
        $this->execute($import, false);
    }

    public function commit(ProductImport $import): void
    {
        $this->execute($import, true);
    }

    private function execute(ProductImport $import, bool $apply): void
    {
        try {
            $path = $import->filePath();
            $format = strtolower(pathinfo((string) $import->path, PATHINFO_EXTENSION));

            if (! $path || ! is_file($path)) {
                throw new InvalidCell('File impor tidak ditemukan. Unggah ulang file Anda.');
            }

            $headers = Spreadsheet::headers($path, $format, static::aliases());
            foreach ($this->requiredHeaders() as $group) {
                if (! array_intersect($group, $headers)) {
                    throw new InvalidCell('Kolom "'.implode('" atau "', $group).'" tidak ditemukan. Gunakan template yang disediakan.');
                }
            }

            $report = $this->run($import, Spreadsheet::read($path, $format, static::aliases()), $apply);
        } catch (InvalidCell $e) {
            $this->fail($import, $e->getMessage());

            return;
        } catch (Throwable $e) {
            report($e);
            $this->fail($import, 'File tidak bisa dibaca. Pastikan formatnya .xlsx atau .csv yang valid.');

            return;
        }

        if (! $apply && $report['total'] === 0) {
            $this->fail($import, 'File tidak berisi baris data.');

            return;
        }

        $import->forceFill([
            'total_rows' => $report['total'],
            'error_rows' => $report['error_rows'],
            'summary' => $report['summary'],
            'preview' => $report['preview'],
            'errors' => array_slice($report['errors'], 0, self::MAX_ERRORS),
        ]);

        if ($apply) {
            $import->forceFill([
                'status' => ImportStatus::Completed,
                'result' => $report['result'],
                'completed_at' => now(),
            ])->save();
            $import->deleteFile();
            $import->forceFill(['path' => null])->save();
        } else {
            $import->forceFill(['status' => ImportStatus::Ready, 'validated_at' => now()])->save();
        }
    }

    public function fail(ProductImport $import, string $message): void
    {
        $import->forceFill(['status' => ImportStatus::Failed, 'failure' => $message])->save();
    }

    /** Ringkas error per baris: [['row' => 5, 'message' => '...']]. */
    protected static function error(int $line, string $message): array
    {
        return ['row' => $line, 'message' => $message];
    }
}
