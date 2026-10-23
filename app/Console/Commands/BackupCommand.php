<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

#[Signature('shop:backup {--no-uploads : Hanya backup database} {--no-cleanup : Jangan hapus backup lama}')]
#[Description('Backup database (dan foto produk) lalu hapus backup yang kedaluwarsa')]
class BackupCommand extends Command
{
    public function handle(): int
    {
        $disk = Storage::disk(config('backup.disk'));
        $folder = trim(config('backup.path'), '/');
        $stamp = now()->format('Y-m-d_His');
        $tmp = storage_path('app/backup-tmp');

        File::ensureDirectoryExists($tmp);

        try {
            $files = [$this->dumpDatabase("{$tmp}/db-{$stamp}.sql.gz")];

            if (config('backup.include_uploads') && ! $this->option('no-uploads')) {
                if ($archive = $this->archiveUploads("{$tmp}/uploads-{$stamp}.tar.gz")) {
                    $files[] = $archive;
                }
            }

            foreach ($files as $file) {
                $stream = fopen($file, 'r');
                $disk->writeStream($folder.'/'.basename($file), $stream);
                is_resource($stream) && fclose($stream);

                $this->components->info('Tersimpan: '.$folder.'/'.basename($file).' ('.$this->size(filesize($file)).')');
            }
        } catch (RuntimeException $e) {
            report($e);
            $this->components->error('Backup gagal: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            File::deleteDirectory($tmp);
        }

        if (! $this->option('no-cleanup')) {
            $this->cleanup($disk, $folder);
        }

        return self::SUCCESS;
    }

    protected function dumpDatabase(string $target): string
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        $raw = substr($target, 0, -3);

        match ($config['driver']) {
            'mysql', 'mariadb' => $this->dumpMysql($config, $raw),
            'sqlite' => $this->dumpSqlite($config, $raw),
            default => throw new RuntimeException("Driver database [{$config['driver']}] belum didukung."),
        };

        $this->gzip($raw, $target);

        return $target;
    }

    protected function dumpMysql(array $config, string $target): void
    {
        $command = [
            config('backup.mysqldump'),
            '--single-transaction',
            '--quick',
            '--routines',
            '--no-tablespaces',
            '--default-character-set=utf8mb4',
            '--result-file='.$target,
        ];

        if (! empty($config['unix_socket'])) {
            $command[] = '--socket='.$config['unix_socket'];
        } else {
            $command[] = '--host='.$config['host'];
            $command[] = '--port='.$config['port'];
        }

        $command[] = '--user='.$config['username'];
        $command[] = $config['database'];

        // Password lewat env supaya tidak terlihat di daftar proses.
        $result = Process::env(['MYSQL_PWD' => (string) $config['password']])
            ->timeout(3600)
            ->run($command);

        if ($result->failed()) {
            throw new RuntimeException('mysqldump gagal: '.trim($result->errorOutput()));
        }
    }

    protected function dumpSqlite(array $config, string $target): void
    {
        $path = $config['database'];

        if ($path === ':memory:' || ! is_file($path)) {
            throw new RuntimeException("File database SQLite [{$path}] tidak ditemukan.");
        }

        File::copy($path, $target);
    }

    protected function gzip(string $source, string $target): void
    {
        $in = fopen($source, 'rb');
        $out = gzopen($target, 'wb6');

        while (! feof($in)) {
            gzwrite($out, fread($in, 1024 * 512));
        }

        fclose($in);
        gzclose($out);
        unlink($source);
    }

    protected function archiveUploads(string $target): ?string
    {
        $source = storage_path('app/public');

        if (! is_dir($source) || File::isEmptyDirectory($source)) {
            $this->components->warn('Folder upload kosong, dilewati.');

            return null;
        }

        $result = Process::timeout(3600)->run(['tar', '-czf', $target, '-C', dirname($source), basename($source)]);

        if ($result->failed()) {
            throw new RuntimeException('Arsip foto gagal (tar): '.trim($result->errorOutput()));
        }

        return $target;
    }

    protected function cleanup($disk, string $folder): void
    {
        $limit = now()->subDays(config('backup.keep_days'))->getTimestamp();
        $deleted = 0;

        foreach ($disk->files($folder) as $file) {
            if (preg_match('/^(db|uploads)-.+\.gz$/', basename($file)) && $disk->lastModified($file) < $limit) {
                $disk->delete($file);
                $deleted++;
            }
        }

        if ($deleted > 0) {
            $this->components->info("{$deleted} backup lama dihapus.");
        }
    }

    protected function size(int $bytes): string
    {
        return $bytes >= 1048576
            ? number_format($bytes / 1048576, 1).' MB'
            : number_format($bytes / 1024, 1).' KB';
    }
}
