<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use App\Support\FormField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class FormComponentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_field_helpers(): void
    {
        $this->assertSame('variants.0.price', FormField::errorKey('variants[0][price]'));
        $this->assertSame('images', FormField::errorKey('images[]'));
        $this->assertSame('variants_0_price', FormField::id('variants[0][price]'));
        $this->assertNull(FormField::errorKey(null));
        $this->assertSame('1.250.000', FormField::thousands(1250000));
        $this->assertSame('15.000', FormField::thousands('15.000'));
        $this->assertSame('', FormField::thousands(null));
    }

    public function test_components_render_with_old_input_and_errors(): void
    {
        $errors = (new ViewErrorBag)->put('default', new MessageBag(['price' => ['Harga wajib diisi.']]));
        view()->share('errors', $errors);

        $html = (string) $this->blade('<x-form.money name="price" label="Harga" :value="1500000" />');
        $this->assertStringContainsString('x-money', $html);
        $this->assertStringContainsString('value="1.500.000"', $html);
        $this->assertStringContainsString('input-error', $html);
        $this->assertStringContainsString('Harga wajib diisi.', $html);

        $html = (string) $this->blade('<x-form.date name="starts_at" time :value="$d" />', ['d' => now()->setDate(2026, 10, 5)->setTime(14, 30)]);
        $this->assertStringContainsString('x-datepicker.time', $html);
        $this->assertStringContainsString('value="2026-10-05T14:30"', $html);
        $this->assertStringContainsString('data-date-clear', $html);

        $html = (string) $this->blade('<x-form.select name="courier" :options="[\'jne\' => \'JNE\', \'jnt\' => \'J&T\']" selected="jnt" placeholder="Semua" />');
        $this->assertStringContainsString('x-searchable', $html);
        $this->assertMatchesRegularExpression('/<option value="jnt"\s+selected/', $html);

        $html = (string) $this->blade('<x-form.textarea name="notes" maxlength="500" value="Halo" />');
        $this->assertStringContainsString('/500', $html);
        $this->assertStringContainsString('count: 4', $html);
    }

    public function test_admin_forms_use_enhanced_inputs(): void
    {
        $admin = User::factory()->admin()->create();
        $parent = Category::factory()->create(['name' => 'Casing HP']);
        Category::factory()->create(['name' => 'Casing iPhone', 'parent_id' => $parent->id]);
        $this->actingAs($admin);

        $this->get(route('admin.products.create'))->assertOk()
            ->assertSee('x-searchable', false)
            ->assertSee('Casing HP › Casing iPhone')
            ->assertSee('x-money', false);

        $this->get(route('admin.vouchers.create'))->assertOk()
            ->assertSee('x-datepicker.time', false)
            ->assertSee('x-money', false);

        $this->get(route('admin.banners.create'))->assertOk()->assertSee('imageUpload(', false);
        $this->get(route('admin.categories.create'))->assertOk()->assertSee('x-searchable', false);
        $this->get(route('admin.orders.index'))->assertOk()->assertSee('x-daterange', false);
        $this->get(route('admin.reports.index'))->assertOk()->assertSee('x-daterange', false);
    }

    public function test_rupiah_with_thousand_separators_is_accepted(): void
    {
        $admin = User::factory()->admin()->create();

        // Bila JS tidak sempat membersihkan, server tetap menerima "150.000".
        $this->actingAs($admin)->post(route('admin.products.store'), [
            'name' => 'Casing Uji',
            'price' => '150.000',
            'compare_at_price' => '175.000',
            'stock' => 3,
            'weight' => 200,
            'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $product = Product::firstWhere('name', 'Casing Uji');
        $this->assertSame(150000, (int) $product->price);
        $this->assertSame(175000, (int) $product->compare_at_price);

        $this->post(route('admin.vouchers.store'), [
            'code' => 'UJI50',
            'name' => 'Uji',
            'type' => 'fixed',
            'value' => '50.000',
            'min_spend' => '200.000',
            'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $voucher = Voucher::firstWhere('code', 'UJI50');
        $this->assertSame(50000, (int) $voucher->value);
        $this->assertSame(200000, (int) $voucher->min_spend);
    }

    public function test_address_form_renders_searchable_region_selects(): void
    {
        $customer = User::factory()->create();
        $address = Address::factory()->for($customer)->create();

        $this->actingAs($customer)->get(route('account.addresses.edit', $address))->assertOk()
            ->assertSee('x-ref="province"', false)
            ->assertSee('x-ref="district"', false)
            ->assertSee('x-phone', false);
    }
}
