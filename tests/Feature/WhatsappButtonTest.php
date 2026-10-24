<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsappButtonTest extends TestCase
{
    use RefreshDatabase;

    public function test_floating_button_links_to_admin_number_with_greeting(): void
    {
        config(['shop.whatsapp' => '6281234567890', 'shop.whatsapp_button' => true, 'shop.whatsapp_message' => 'Halo MGA, saya mau tanya.']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-whatsapp-float', false)
            ->assertSee('https://wa.me/6281234567890?text='.rawurlencode('Halo MGA, saya mau tanya.'), false);
    }

    public function test_product_page_adds_product_name_and_link_to_message(): void
    {
        config(['shop.whatsapp' => '6281234567890', 'shop.whatsapp_message' => 'Halo.']);
        $product = Product::factory()->create(['stock' => 5]);

        $text = rawurlencode("Halo.\n".$product->name.' — '.route('products.show', $product));

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('wa.me/6281234567890?text='.$text, false);
    }

    public function test_button_hidden_without_number_or_when_disabled(): void
    {
        config(['shop.whatsapp' => null]);
        $this->get(route('home'))->assertOk()->assertDontSee('data-whatsapp-float', false);

        config(['shop.whatsapp' => '6281234567890', 'shop.whatsapp_button' => false]);
        $this->get(route('home'))->assertOk()->assertDontSee('data-whatsapp-float', false);
    }

    public function test_button_not_shown_in_admin_panel(): void
    {
        config(['shop.whatsapp' => '6281234567890']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('data-whatsapp-float', false);
    }

    public function test_admin_can_disable_button_and_set_greeting(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.store-settings.update'), [
                'name' => 'MGA', 'full_name' => 'Media Gudang Acc', 'whatsapp' => '0812 3456 7890',
                'whatsapp_button' => '0', 'whatsapp_message' => 'Halo kak!',
                'couriers' => ['jne'], 'payment_expiry_hours' => 24, 'auto_complete_days' => 3,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('6281234567890', config('shop.whatsapp'));
        $this->assertFalse(config('shop.whatsapp_button'));
        $this->assertSame('Halo kak!', config('shop.whatsapp_message'));
        $this->get(route('home'))->assertDontSee('data-whatsapp-float', false);
    }
}
