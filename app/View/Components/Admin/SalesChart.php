<?php

namespace App\View\Components\Admin;

use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Grafik garis penjualan (SVG murni + Alpine untuk tooltip), tanpa library
 * chart. Satu sumbu Y per metrik; pengguna bisa berpindah metrik.
 */
class SalesChart extends Component
{
    public const WIDTH = 1000;

    public const HEIGHT = 300;

    /** @var array<string, array{label:string, ticks:array, path:string, area:string, points:array}> */
    public array $metrics = [];

    /** Indeks label sumbu X yang ditampilkan (tidak semua agar tidak bertumpuk). */
    public array $xLabels = [];

    /** Subset label sumbu X untuk layar sempit. */
    public array $xLabelsMobile = [];

    public function __construct(public Collection $series, public string $height = 'h-64')
    {
        $this->metrics = [
            'revenue' => $this->build('revenue', 'Pendapatan', fn (int $v) => Money::compact($v), fn (int $v) => Money::rupiah($v)),
            'orders' => $this->build('orders', 'Pesanan', fn (int $v) => number_format($v, 0, ',', '.'), fn (int $v) => number_format($v, 0, ',', '.').' pesanan'),
        ];

        $this->xLabels = self::labelIndexes($series->count(), 7);
        $this->xLabelsMobile = self::labelIndexes($series->count(), 4);
    }

    /** Indeks titik yang diberi label, tersebar rata, maksimal ±$max label. */
    private static function labelIndexes(int $count, int $max): array
    {
        $step = max(1, (int) ceil($count / $max));
        $indexes = range(0, max(0, $count - 1), $step);
        $gap = ($count - 1) - end($indexes);
        if ($count > 1 && $gap > 0) {
            // Titik terakhir selalu diberi label; geser label sebelumnya bila terlalu dekat.
            if ($gap >= $step * 0.75) {
                $indexes[] = $count - 1;
            } else {
                $indexes[array_key_last($indexes)] = $count - 1;
            }
        }

        return $count > 0 ? $indexes : [];
    }

    private function build(string $key, string $label, callable $axis, callable $format): array
    {
        $values = $this->series->pluck($key)->map(fn ($v) => (int) $v)->values();
        ['max' => $max, 'steps' => $steps] = self::scale((int) $values->max());
        $count = $values->count();

        $points = $values->map(function (int $value, int $i) use ($count, $max, $format) {
            $x = $count > 1 ? $i / ($count - 1) * self::WIDTH : self::WIDTH / 2;
            $y = self::HEIGHT - ($value / $max * self::HEIGHT);

            return [
                'x' => round($x, 2),
                'y' => round($y, 2),
                'left' => round($x / self::WIDTH * 100, 3),
                'top' => round($y / self::HEIGHT * 100, 3),
                'value' => $format($value),
            ];
        })->all();

        $line = collect($points)->map(fn ($p, $i) => ($i === 0 ? 'M' : 'L').$p['x'].' '.$p['y'])->implode(' ');
        $area = $count > 0
            ? $line.' L'.end($points)['x'].' '.self::HEIGHT.' L'.$points[0]['x'].' '.self::HEIGHT.' Z'
            : '';

        $ticks = collect(range($steps, 0))->map(fn (int $i) => [
            'label' => $axis((int) round($max * $i / $steps)),
            'top' => round(($steps - $i) / $steps * 100, 3),
        ])->all();

        return compact('label', 'ticks', 'points') + ['path' => $line, 'area' => $area];
    }

    /**
     * Skala sumbu Y yang mudah dibaca: langkah garis bantu 1/2/2,5/5 × 10^n
     * dengan 3–5 garis di atas nol.
     *
     * @return array{max:int, steps:int}
     */
    public static function scale(int $value): array
    {
        if ($value <= 0) {
            return ['max' => 4, 'steps' => 4];
        }

        $magnitude = 10 ** floor(log10($value / 4));
        foreach ([1, 2, 2.5, 5, 10, 20] as $factor) {
            $step = $factor * $magnitude;
            if ($step < 1) {
                continue;
            }
            $steps = (int) ceil($value / $step);
            if ($steps <= 5) {
                return ['max' => (int) ($step * max(3, $steps)), 'steps' => max(3, $steps)];
            }
        }

        return ['max' => $value, 'steps' => 4];
    }

    public function render(): View
    {
        return view('components.admin.sales-chart');
    }
}
