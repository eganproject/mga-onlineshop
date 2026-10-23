<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Activity\ActivityLogger;
use App\Services\Settings\IntegrationTester;
use App\Services\Settings\StoreSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Admin › Integrasi (khusus superadmin): driver & kunci Midtrans, RajaOngkir,
 * dan Firebase. Disimpan lewat StoreSettings (rahasia terenkripsi); kosong =
 * memakai .env. Nilai rahasia tidak pernah dikirim balik ke browser maupun log.
 */
class IntegrationController extends Controller
{
    /** Integrasi => [kolom form => kunci config]. */
    private const FORM = [
        'midtrans' => [
            'driver' => 'shop.payment.driver',
            'is_production' => 'services.midtrans.is_production',
            'merchant_id' => 'services.midtrans.merchant_id',
            'client_key' => 'services.midtrans.client_key',
            'server_key' => 'services.midtrans.server_key',
        ],
        'rajaongkir' => [
            'driver' => 'shop.shipping.driver',
            'api_key' => 'services.rajaongkir.api_key',
        ],
        'firebase' => [
            'login_enabled' => 'services.firebase.login_enabled',
            'api_key' => 'services.firebase.api_key',
            'auth_domain' => 'services.firebase.auth_domain',
            'project_id' => 'services.firebase.project_id',
            'app_id' => 'services.firebase.app_id',
        ],
    ];

    /** Kolom rahasia per integrasi: input kosong = tidak diubah. */
    private const SECRETS = ['midtrans' => ['server_key'], 'rajaongkir' => ['api_key']];

    public const NAMES = ['midtrans' => 'Midtrans', 'rajaongkir' => 'RajaOngkir', 'firebase' => 'Login Google (Firebase)'];

    public const LABELS = [
        'shop.payment.driver' => 'Driver pembayaran',
        'services.midtrans.is_production' => 'Mode Midtrans',
        'services.midtrans.merchant_id' => 'Merchant ID',
        'services.midtrans.client_key' => 'Client key',
        'services.midtrans.server_key' => 'Server key',
        'shop.shipping.driver' => 'Driver ongkir',
        'services.rajaongkir.api_key' => 'API key RajaOngkir',
        'services.firebase.login_enabled' => 'Tombol login Google',
        'services.firebase.api_key' => 'API key Firebase',
        'services.firebase.auth_domain' => 'Auth domain',
        'services.firebase.project_id' => 'Project ID',
        'services.firebase.app_id' => 'App ID',
    ];

    public function __construct(private readonly StoreSettings $settings) {}

    public function index(): View
    {
        // Sekali baca untuk semua kolom (cache database = satu query per baca).
        try {
            $stored = $this->settings->stored();
        } catch (\Throwable) {
            $stored = [];
        }

        $field = fn (string $key) => [
            'value' => config($key),
            'stored' => array_key_exists($key, $stored),
            'default' => $this->settings->default($key),
        ];

        return view('admin.integrations', [
            'fields' => collect(self::FORM)->map(fn (array $fields) => collect($fields)->map($field)->all())->all(),
            'secrets' => collect(self::SECRETS)->map(fn (array $fields, string $integration) => collect($fields)->mapWithKeys(fn (string $name) => [
                $name => $this->mask(config(self::FORM[$integration][$name])),
            ])->all())->all(),
            'status' => [
                'midtrans' => $this->status(config('shop.payment.driver') === 'midtrans', filled(config('services.midtrans.server_key')) && filled(config('services.midtrans.client_key'))),
                'rajaongkir' => $this->status(config('shop.shipping.driver') === 'rajaongkir', filled(config('services.rajaongkir.api_key')) && filled(config('shop.shipping.origin_district_id'))),
                'firebase' => $this->status((bool) config('services.firebase.login_enabled') && (filled(config('services.firebase.api_key')) || filled(config('services.firebase.project_id'))), (bool) config('services.firebase.enabled'), 'Nonaktif'),
            ],
            'originLabel' => config('shop.shipping.origin_label') ?: (config('shop.shipping.origin_district_id') ? 'ID '.config('shop.shipping.origin_district_id') : null),
            'webhook' => route('webhooks.midtrans'),
            'results' => session('integration_test', []),
        ]);
    }

    public function update(Request $request, string $integration, ActivityLogger $logger): RedirectResponse
    {
        // Isian tiap kartu bernama "{integrasi}[kolom]" agar error & old() tidak tertukar antar kartu.
        $attributes = [
            'driver' => 'driver', 'is_production' => 'mode', 'merchant_id' => 'merchant ID', 'client_key' => 'client key',
            'server_key' => 'server key', 'api_key' => 'API key', 'auth_domain' => 'auth domain', 'project_id' => 'project ID', 'app_id' => 'app ID',
        ];
        $prefix = fn (array $items) => collect($items)->mapWithKeys(fn ($v, string $k) => ["{$integration}.{$k}" => $v])->all();
        $data = $request->validate($prefix($this->rules($integration)), [
            'regex' => ':Attribute tidak boleh berisi spasi atau karakter tidak valid.',
        ], $prefix($attributes))[$integration] ?? [];

        $values = [];

        foreach (self::FORM[$integration] as $name => $key) {
            if (in_array($name, self::SECRETS[$integration] ?? [], true)) {
                // Kosong = tetap; centang "hapus" = kembali ke .env.
                if ($request->boolean("{$integration}.clear_{$name}")) {
                    $values[$key] = null;
                } elseif (filled($data[$name] ?? null)) {
                    $values[$key] = $data[$name];
                }

                continue;
            }

            $values[$key] = in_array($name, ['is_production', 'login_enabled'], true) ? $request->boolean("{$integration}.{$name}") : ($data[$name] ?? null);
        }

        $this->ensureUsable($integration, $values);

        $changes = $this->settings->save($values);

        if ($changes) {
            $logger->log('integrations.update', 'Mengubah integrasi '.self::NAMES[$integration], properties: [
                'perubahan' => collect($changes)->mapWithKeys(fn (array $pair, string $key) => [
                    self::LABELS[$key] ?? $key => $this->isSecret($key)
                        ? (filled($pair[1]) ? 'diganti (nilai disembunyikan)' : 'dihapus, kembali ke .env')
                        : ['dari' => $this->display($key, $pair[0]), 'menjadi' => $this->display($key, $pair[1])],
                ])->all(),
            ]);

            // Worker antrean membaca pengaturan saat mulai; minta mulai ulang agar ikut berubah.
            Artisan::call('queue:restart');
        }

        return redirect()->route('admin.integrations.index')->withFragment($integration)
            ->with('success', $changes ? 'Pengaturan '.self::NAMES[$integration].' disimpan.' : 'Tidak ada perubahan.');
    }

    public function test(string $integration, IntegrationTester $tester, ActivityLogger $logger): RedirectResponse
    {
        $result = $tester->test($integration);

        $logger->log('integrations.test', 'Tes koneksi '.self::NAMES[$integration], properties: [
            'hasil' => $result['ok'] ? 'Berhasil' : 'Gagal',
            'pesan' => $result['message'],
        ]);

        return redirect()->route('admin.integrations.index')->withFragment($integration)
            ->with('integration_test', [$integration => $result]);
    }

    /** @return array<string, mixed> */
    private function rules(string $integration): array
    {
        $key = ['nullable', 'string', 'max:255', 'regex:/^\S+$/'];

        return match ($integration) {
            'midtrans' => [
                'driver' => ['required', Rule::in(['fake', 'midtrans'])],
                'is_production' => ['boolean'],
                'merchant_id' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9-]+$/'],
                'client_key' => $key,
                'server_key' => $key,
            ],
            'rajaongkir' => [
                'driver' => ['required', Rule::in(['fake', 'rajaongkir'])],
                'api_key' => $key,
            ],
            'firebase' => [
                'login_enabled' => ['boolean'],
                'api_key' => $key,
                'auth_domain' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9.-]+$/'],
                'project_id' => ['nullable', 'string', 'max:63', 'regex:/^[a-z0-9-]+$/'],
                'app_id' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9:._-]+$/'],
            ],
        };
    }

    /** Driver sungguhan butuh kuncinya (dari form, database, atau .env). */
    private function ensureUsable(string $integration, array $values): void
    {
        $effective = fn (string $key) => array_key_exists($key, $values)
            ? ($values[$key] ?? $this->settings->default($key))
            : config($key);

        $missing = match (true) {
            $integration === 'midtrans' && $values['shop.payment.driver'] === 'midtrans' => array_filter([
                'server_key' => blank($effective('services.midtrans.server_key')) ? 'Isi server key sebelum mengaktifkan Midtrans.' : null,
                'client_key' => blank($effective('services.midtrans.client_key')) ? 'Isi client key sebelum mengaktifkan Midtrans.' : null,
            ]),
            $integration === 'rajaongkir' && $values['shop.shipping.driver'] === 'rajaongkir' => array_filter([
                'api_key' => blank($effective('services.rajaongkir.api_key')) ? 'Isi API key sebelum mengaktifkan RajaOngkir.' : null,
            ]),
            $integration === 'firebase' && $values['services.firebase.login_enabled'] => array_filter([
                'project_id' => filled($effective('services.firebase.api_key')) && blank($effective('services.firebase.project_id')) ? 'Project ID wajib diisi bila API key diisi.' : null,
                'api_key' => filled($effective('services.firebase.project_id')) && blank($effective('services.firebase.api_key')) ? 'API key wajib diisi bila Project ID diisi.' : null,
            ]),
            default => [],
        };

        if ($missing) {
            throw ValidationException::withMessages(collect($missing)->mapWithKeys(fn (string $message, string $field) => ["{$integration}.{$field}" => $message])->all());
        }
    }

    private function isSecret(string $key): bool
    {
        return (StoreSettings::FIELDS[$key] ?? null) === 'secret';
    }

    /** "SB-Mid-server-abc...wxyz" → "SB-M••••••••wxyz". */
    private function mask(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return strlen($value) <= 8
            ? str_repeat('•', 8)
            : Str::substr($value, 0, 4).str_repeat('•', 8).Str::substr($value, -4);
    }

    private function display(string $key, mixed $value): mixed
    {
        return match ($key) {
            'services.midtrans.is_production' => $value ? 'Production' : 'Sandbox',
            'services.firebase.login_enabled' => $value ? 'Aktif' : 'Nonaktif',
            default => $value,
        };
    }

    /** @return array{label: string, class: string} */
    private function status(bool $active, bool $configured, string $inactive = 'Mode simulasi'): array
    {
        return match (true) {
            $active && $configured => ['label' => 'Aktif', 'class' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'],
            $active => ['label' => 'Kunci belum lengkap', 'class' => 'bg-rose-50 text-rose-700 ring-rose-600/20'],
            default => ['label' => $inactive, 'class' => 'bg-amber-50 text-amber-700 ring-amber-600/20'],
        };
    }
}
