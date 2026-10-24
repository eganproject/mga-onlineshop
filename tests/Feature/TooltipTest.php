<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class TooltipTest extends TestCase
{
    use RefreshDatabase;

    public function test_tooltip_component_renders_accessible_markup(): void
    {
        $html = (string) $this->blade('<x-tooltip label="SKU" text="Kode unik & rapi" :tips="[\'Tips satu\', \'Tips dua\']" />');

        $this->assertStringContainsString('data-tip-trigger', $html);
        $this->assertStringContainsString('aria-label="Info: SKU"', $html);
        $this->assertStringContainsString('role="tooltip"', $html);
        $this->assertStringContainsString('hidden', $html);
        $this->assertStringContainsString('Kode unik &amp; rapi', $html);
        $this->assertStringContainsString('Tips satu', $html);
        $this->assertStringContainsString('Tips dua', $html);

        // aria-describedby menunjuk ke panel tooltip.
        preg_match('/aria-describedby="([^"]+)"/', $html, $describedBy);
        $this->assertStringContainsString('id="'.$describedBy[1].'"', $html);

        // Teks dari pengguna tetap di-escape.
        $html = (string) $this->blade('<x-tooltip text="<b>x</b>" />');
        $this->assertStringNotContainsString('<b>x</b>', $html);
    }

    public function test_form_components_show_tooltip_next_to_label(): void
    {
        view()->share('errors', new ViewErrorBag);

        $html = (string) $this->blade('<x-form.input name="sku" label="SKU" tooltip="Kode barang" tooltip-tips="Pakai pola tetap" />');
        $this->assertStringContainsString('label-row', $html);
        $this->assertStringContainsString('Kode barang', $html);
        $this->assertStringContainsString('Pakai pola tetap', $html);
        $this->assertStringContainsString('aria-label="Info: SKU"', $html);

        $html = (string) $this->blade('<x-form.phone name="wa" label="WhatsApp" tooltip="Nomor tujuan chat" />');
        $this->assertStringContainsString('Nomor tujuan chat', $html);

        // Tanpa tooltip, markup label tidak berubah.
        $html = (string) $this->blade('<x-form.input name="name" label="Nama" />');
        $this->assertStringNotContainsString('data-tip', $html);
        $this->assertStringNotContainsString('label-row', $html);
    }

    public function test_admin_forms_have_help_tooltips(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        foreach ([
            'admin.products.create' => ['Info: SKU induk', 'Info: Foto produk', 'Info: Berat'],
            'admin.categories.create' => ['Info: Slug URL', 'Info: Gambar'],
            'admin.banners.create' => ['Info: Gambar banner', 'Info: Gambar versi HP'],
            'admin.vouchers.create' => ['Info: Kuota total', 'Info: Batas per pelanggan', 'Info: Maksimal diskon'],
            'admin.storefront.edit' => ['Info: Foto utama (desktop)', 'Info: Foto versi HP', 'Info: Best Seller'],
            'admin.store-settings.edit' => ['Info: Kecamatan', 'Info: Pesan pembuka chat', 'Info: Batas waktu bayar'],
            'admin.integrations.index' => ['Info: Server key', 'Info: Mode Midtrans', 'Info: API key'],
            'admin.articles.create' => ['Info: Judul SEO', 'Info: Gambar sampul'],
            'admin.staff.create' => ['Info: Jabatan'],
        ] as $route => $labels) {
            $response = $this->get(route($route))->assertOk();
            foreach ($labels as $label) {
                $response->assertSee('aria-label="'.$label.'"', false);
            }
        }
    }
}
