<?php

namespace App\Services\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Rentang tanggal laporan (inklusif, per hari kalender Asia/Jakarta) beserta
 * cara pengelompokannya (harian/mingguan/bulanan).
 */
final class ReportPeriod
{
    public const PRESETS = [
        'today' => 'Hari ini',
        '7d' => '7 hari terakhir',
        '30d' => '30 hari terakhir',
        '90d' => '90 hari terakhir',
        'this_month' => 'Bulan ini',
        'last_month' => 'Bulan lalu',
        'this_year' => 'Tahun ini',
        'custom' => 'Pilih tanggal',
    ];

    public const GROUPS = [
        'day' => 'Harian',
        'week' => 'Mingguan',
        'month' => 'Bulanan',
    ];

    /** Batas panjang rentang agar query & grafik tetap ringan. */
    public const MAX_DAYS = 731;

    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly string $group,
        public readonly string $preset = 'custom',
    ) {}

    public static function fromPreset(string $preset, ?string $group = null): self
    {
        $today = CarbonImmutable::today();

        [$from, $to] = match ($preset) {
            'today' => [$today, $today],
            '7d' => [$today->subDays(6), $today],
            '90d' => [$today->subDays(89), $today],
            'this_month' => [$today->startOfMonth(), $today],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()->startOfDay()],
            'this_year' => [$today->startOfYear(), $today],
            default => [$today->subDays(29), $today],
        };

        $preset = array_key_exists($preset, self::PRESETS) && $preset !== 'custom' ? $preset : '30d';

        return new self($from, $to, self::resolveGroup($group, $from, $to), $preset);
    }

    /**
     * Baca filter dari query string: preset, from, to, group. Nilai yang tidak
     * valid jatuh ke default (30 hari terakhir) alih-alih error.
     */
    public static function fromRequest(Request $request, string $default = '30d'): self
    {
        $preset = self::param($request, 'preset') ?? $default;
        $group = self::param($request, 'group');

        if ($preset === 'custom') {
            $from = self::parseDate(self::param($request, 'from'));
            $to = self::parseDate(self::param($request, 'to'));

            if ($from && $to) {
                if ($from->gt($to)) {
                    [$from, $to] = [$to, $from];
                }

                if ($from->diffInDays($to) >= self::MAX_DAYS) {
                    $from = $to->subDays(self::MAX_DAYS - 1);
                }

                return new self($from, $to, self::resolveGroup($group, $from, $to), 'custom');
            }
        }

        return self::fromPreset($preset, $group);
    }

    /** Ambil parameter query sebagai string; array/nilai kosong dianggap tidak ada. */
    public static function param(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }

        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }

    private static function resolveGroup(?string $group, CarbonImmutable $from, CarbonImmutable $to): string
    {
        if ($group && array_key_exists($group, self::GROUPS)) {
            return $group;
        }

        $days = $from->diffInDays($to) + 1;

        return match (true) {
            $days <= 45 => 'day',
            $days <= 180 => 'week',
            default => 'month',
        };
    }

    /** Awal hari pertama (untuk query). */
    public function start(): Carbon
    {
        return Carbon::instance($this->from->startOfDay());
    }

    /** Akhir hari terakhir (untuk query). */
    public function end(): Carbon
    {
        return Carbon::instance($this->to->endOfDay());
    }

    public function days(): int
    {
        return (int) $this->from->diffInDays($this->to) + 1;
    }

    /** Periode dengan panjang sama tepat sebelum periode ini (untuk perbandingan). */
    public function previous(): self
    {
        $days = $this->days();

        return new self($this->from->subDays($days), $this->from->subDay(), $this->group, 'custom');
    }

    public function label(): string
    {
        if ($this->from->isSameDay($this->to)) {
            return $this->from->translatedFormat('d M Y');
        }

        $sameYear = $this->from->year === $this->to->year;

        return $this->from->translatedFormat($sameYear ? 'd M' : 'd M Y').' – '.$this->to->translatedFormat('d M Y');
    }

    /** Parameter query string untuk membuat ulang periode ini (mis. di link ekspor). */
    public function toQuery(): array
    {
        return $this->preset === 'custom'
            ? ['preset' => 'custom', 'from' => $this->from->toDateString(), 'to' => $this->to->toDateString(), 'group' => $this->group]
            : ['preset' => $this->preset, 'group' => $this->group];
    }

    /** Kunci bucket (Y-m-d awal bucket) untuk sebuah tanggal. */
    public function bucketKey(CarbonImmutable $date): string
    {
        return match ($this->group) {
            'week' => $date->startOfWeek(CarbonImmutable::MONDAY)->toDateString(),
            'month' => $date->startOfMonth()->toDateString(),
            default => $date->toDateString(),
        };
    }

    /**
     * Semua bucket dalam periode, berurutan: [key => ['label', 'short', 'start', 'end']].
     * Bucket pertama/terakhir dipotong sesuai batas periode.
     */
    public function buckets(): array
    {
        $buckets = [];
        $cursor = $this->from;

        while ($cursor->lte($this->to)) {
            $key = $this->bucketKey($cursor);
            $end = match ($this->group) {
                'week' => $cursor->endOfWeek(CarbonImmutable::SUNDAY)->startOfDay(),
                'month' => $cursor->endOfMonth()->startOfDay(),
                default => $cursor,
            };
            $end = $end->gt($this->to) ? $this->to : $end;

            $buckets[$key] = [
                'start' => $cursor,
                'end' => $end,
                'label' => match ($this->group) {
                    'week' => $cursor->isSameDay($end) ? $cursor->translatedFormat('d M Y') : $cursor->translatedFormat('d M').' – '.$end->translatedFormat('d M Y'),
                    'month' => $cursor->translatedFormat('F Y'),
                    default => $cursor->translatedFormat('D, d M Y'),
                },
                'short' => match ($this->group) {
                    'month' => $cursor->translatedFormat('M y'),
                    default => $cursor->translatedFormat('d M'),
                },
            ];

            $cursor = $end->addDay();
        }

        return $buckets;
    }
}
