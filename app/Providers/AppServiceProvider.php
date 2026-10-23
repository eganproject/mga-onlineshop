<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Contracts\ShippingProvider;
use App\Models\Category;
use App\Services\Cart\CartService;
use App\Services\Customer\WishlistService;
use App\Services\Orders\OrderService;
use App\Services\Payment\FakePaymentGateway;
use App\Services\Payment\MidtransPaymentGateway;
use App\Services\Shipping\FakeShippingProvider;
use App\Services\Shipping\RajaOngkirShippingProvider;
use App\Support\Money;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CartService::class);
        $this->app->scoped(WishlistService::class);

        // Driver dipilih lewat .env (PAYMENT_DRIVER / SHIPPING_DRIVER), jadi
        // kode lain cukup bergantung pada interface-nya.
        $this->app->bind(PaymentGateway::class, fn ($app) => match (config('shop.payment.driver')) {
            'midtrans' => new MidtransPaymentGateway(
                $app->make(OrderService::class),
                config('services.midtrans.server_key'),
                config('services.midtrans.is_production'),
            ),
            default => new FakePaymentGateway,
        });

        $this->app->bind(ShippingProvider::class, fn () => match (config('shop.shipping.driver')) {
            'rajaongkir' => new RajaOngkirShippingProvider(
                config('services.rajaongkir.api_key'),
                config('services.rajaongkir.base_url'),
                config('shop.shipping.origin_district_id'),
                config('shop.shipping.couriers'),
            ),
            default => new FakeShippingProvider(config('shop.shipping.origin_district_id')),
        });
    }

    public function boot(): void
    {
        Blade::directive('rupiah', fn (string $expression) => '<?php echo e('.Money::class."::rupiah({$expression})); ?>");

        View::composer('components.layouts.shop', function ($view) {
            $view->with([
                'cartCount' => app(CartService::class)->count(),
                'wishlistCount' => app(WishlistService::class)->count(),
                'navCategories' => Category::active()->root()->orderBy('sort_order')->get(['id', 'name', 'slug']),
            ]);
        });
    }
}
