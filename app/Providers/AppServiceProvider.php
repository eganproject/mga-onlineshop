<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Contracts\ShipmentTracker;
use App\Contracts\ShippingProvider;
use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Order;
use App\Services\Auth\FirebaseTokenVerifier;
use App\Services\Billing\BillingSettings;
use App\Services\Billing\InvoiceNumberer;
use App\Services\Cart\CartService;
use App\Services\Catalog\SoldCountService;
use App\Services\Customer\WishlistService;
use App\Services\Payment\FakePaymentGateway;
use App\Services\Payment\MidtransPaymentGateway;
use App\Services\Payment\PaymentService;
use App\Services\Settings\StoreSettings;
use App\Services\Shipping\FakeShipmentTracker;
use App\Services\Shipping\FakeShippingProvider;
use App\Services\Shipping\RajaOngkirShipmentTracker;
use App\Services\Shipping\RajaOngkirShippingProvider;
use App\Support\Money;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CartService::class);
        $this->app->scoped(WishlistService::class);
        $this->app->bind(FirebaseTokenVerifier::class, fn () => new FirebaseTokenVerifier(config('services.firebase.project_id')));

        // Driver dipilih lewat .env (PAYMENT_DRIVER / SHIPPING_DRIVER), jadi
        // kode lain cukup bergantung pada interface-nya.
        $this->app->bind(PaymentGateway::class, fn ($app) => match (config('shop.payment.driver')) {
            'midtrans' => new MidtransPaymentGateway(
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
                StoreSettings::activeCouriers(),
            ),
            default => new FakeShippingProvider(config('shop.shipping.origin_district_id')),
        });

        $this->app->bind(ShipmentTracker::class, fn () => match (config('shop.shipping.driver')) {
            'rajaongkir' => new RajaOngkirShipmentTracker(
                config('services.rajaongkir.api_key'),
                config('services.rajaongkir.base_url'),
            ),
            default => new FakeShipmentTracker,
        });
    }

    public function boot(): void
    {
        // Nomor invoice: terbit saat pesanan dibuat atau saat lunas (Admin › Pajak & Biaya).
        Order::created(function (Order $order) {
            if ($order->paid_at || BillingSettings::current()->invoiceIssueOn() === 'created') {
                app(InvoiceNumberer::class)->issue($order);
            }
        });
        // Pesanan batal (oleh pelanggan, admin, atau kedaluwarsa): tagihan yang masih
        // pending ikut dibatalkan, juga di gateway, setelah transaksi DB selesai.
        Order::updated(function (Order $order) {
            if ($order->wasChanged('paid_at') && $order->paid_at && ! $order->invoice_number) {
                app(InvoiceNumberer::class)->issue($order);
            }

            if ($order->wasChanged('status') && $order->status === OrderStatus::Cancelled) {
                DB::afterCommit(fn () => app(PaymentService::class)->voidPendingPayments($order));
            }

            // Jumlah terjual produk ikut berubah saat pesanan masuk/keluar status pendapatan
            // (dibayar, atau dibatalkan/dikembalikan).
            if ($order->wasChanged('status')) {
                $revenue = OrderStatus::revenueStatuses();
                $before = $order->getOriginal('status');
                $before = $before instanceof OrderStatus ? $before : OrderStatus::tryFrom((string) $before);

                if (in_array($before, $revenue, true) !== in_array($order->status, $revenue, true)) {
                    DB::afterCommit(fn () => app(SoldCountService::class)->sync($order->items()->pluck('product_id')));
                }
            }
        });

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
