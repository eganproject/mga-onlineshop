<?php

use App\Http\Controllers\Account;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth;
use App\Http\Controllers\Shop;
use App\Http\Controllers\Webhooks;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Toko (publik)
|--------------------------------------------------------------------------
*/

Route::get('/', Shop\HomeController::class)->name('home');
Route::get('/produk', [Shop\ProductController::class, 'index'])->name('products.index');
Route::get('/produk/{product}', [Shop\ProductController::class, 'show'])->name('products.show');

Route::get('/sitemap.xml', Shop\SitemapController::class)->name('sitemap');
Route::get('/robots.txt', Shop\RobotsController::class)->name('robots');

Route::controller(Shop\CartController::class)->prefix('keranjang')->name('cart.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/{product}', 'store')->name('store');
    Route::patch('/item/{line}', 'update')->name('update')->where('line', '[0-9]+(-[0-9]+)?');
    Route::delete('/item/{line}', 'destroy')->name('destroy')->where('line', '[0-9]+(-[0-9]+)?');
});

/*
|--------------------------------------------------------------------------
| Autentikasi
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/masuk', [Auth\AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/masuk', [Auth\AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
    Route::post('/masuk/google', [Auth\FirebaseLoginController::class, 'store'])->middleware('throttle:login')->name('login.firebase');

    Route::get('/daftar', [Auth\RegisteredUserController::class, 'create'])->name('register');
    Route::post('/daftar', [Auth\RegisteredUserController::class, 'store'])->middleware('throttle:register');

    Route::get('/lupa-password', [Auth\PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/lupa-password', [Auth\PasswordResetLinkController::class, 'store'])->middleware('throttle:password-reset')->name('password.email');

    Route::get('/reset-password/{token}', [Auth\NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [Auth\NewPasswordController::class, 'store'])->middleware('throttle:password-reset')->name('password.store');
});

Route::post('/keluar', [Auth\AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->prefix('email')->name('verification.')->group(function () {
    Route::get('/verifikasi', [Auth\EmailVerificationController::class, 'notice'])->name('notice');
    Route::get('/verifikasi/{id}/{hash}', [Auth\EmailVerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verify');
    Route::post('/verifikasi/kirim-ulang', [Auth\EmailVerificationController::class, 'send'])->middleware('throttle:6,1')->name('send');
});

/*
|--------------------------------------------------------------------------
| Area pelanggan
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:customer'])->group(function () {
    Route::post('/wishlist/{product}', [Shop\WishlistController::class, 'toggle'])->name('wishlist.toggle');
    Route::post('/produk/{product}/ulasan', [Shop\ReviewController::class, 'store'])->middleware('throttle:10,1')->name('reviews.store');

    Route::get('/checkout', [Shop\CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [Shop\CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/checkout/ongkir', [Shop\CheckoutController::class, 'rates'])->name('checkout.rates')->middleware('throttle:60,1');
    Route::post('/checkout/voucher', [Shop\CheckoutController::class, 'applyVoucher'])->name('checkout.voucher.apply')->middleware('throttle:20,1');
    Route::delete('/checkout/voucher', [Shop\CheckoutController::class, 'removeVoucher'])->name('checkout.voucher.remove');

    Route::prefix('akun')->name('account.')->group(function () {
        Route::get('/', Account\DashboardController::class)->name('dashboard');

        Route::get('/pesanan', [Account\OrderController::class, 'index'])->name('orders.index');
        Route::get('/pesanan/{order}', [Account\OrderController::class, 'show'])->name('orders.show');
        Route::post('/pesanan/{order}/batal', [Account\OrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('/pesanan/{order}/diterima', [Account\OrderController::class, 'complete'])->name('orders.complete');
        Route::get('/pesanan/{order}/lacak', [Account\OrderController::class, 'tracking'])->name('orders.tracking')->middleware('throttle:30,1');
        Route::get('/pesanan/{order}/invoice', [Account\OrderController::class, 'invoice'])->name('orders.invoice');
        Route::post('/pesanan/{order}/simulasi-bayar', [Account\OrderController::class, 'simulatePayment'])->name('orders.simulate-payment');

        Route::controller(Account\PaymentController::class)->prefix('pesanan/{order}/bayar')->name('orders.payment')->group(function () {
            Route::get('/', 'show')->name('');
            Route::get('/selesai', 'finish')->name('.finish')->middleware('throttle:20,1');
            Route::post('/cek', 'check')->name('.check')->middleware('throttle:10,1');
            Route::post('/baru', 'regenerate')->name('.regenerate')->middleware('throttle:5,1');
        });

        Route::controller(Account\RegionController::class)->prefix('wilayah')->name('regions.')->middleware('throttle:120,1')->group(function () {
            Route::get('/provinsi', 'provinces')->name('provinces');
            Route::get('/provinsi/{province}/kota', 'cities')->name('cities');
            Route::get('/kota/{city}/kecamatan', 'districts')->name('districts');
        });

        Route::get('/wishlist', [Account\WishlistController::class, 'index'])->name('wishlist');
        Route::get('/ulasan', [Account\ReviewController::class, 'index'])->name('reviews.index');

        Route::patch('/alamat/{address}/utama', [Account\AddressController::class, 'makeDefault'])->name('addresses.default');
        Route::resource('alamat', Account\AddressController::class)
            ->except('show')
            ->parameters(['alamat' => 'address'])
            ->names('addresses');

        Route::get('/profil', [Account\ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profil', [Account\ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profil/password', [Account\ProfileController::class, 'updatePassword'])->name('profile.password');
    });
});

/*
|--------------------------------------------------------------------------
| Admin panel
|--------------------------------------------------------------------------
*/

// Akses per menu memakai Gate dari App\Enums\Permission (lihat StaffRole::permissions()).
// Menu sidebar ikut menyembunyikan route yang middleware `can:`-nya ditolak.
Route::middleware(['auth', 'role:admin', 'log.activity'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');

    Route::middleware('can:catalog.manage')->group(function () {
        Route::patch('/categories/{category}/toggle', [Admin\CategoryController::class, 'toggle'])->name('categories.toggle');
        Route::resource('categories', Admin\CategoryController::class)->except('show');
        Route::post('/products/bulk', [Admin\ProductController::class, 'bulk'])->name('products.bulk');
        Route::get('/products/export', [Admin\ProductTransferController::class, 'exportProducts'])->name('products.export');
        Route::get('/products/import-template', [Admin\ProductTransferController::class, 'template'])->name('products.template');
        Route::resource('products', Admin\ProductController::class)->except('show');
    });

    // Impor/ekspor: produk butuh catalog.manage, stok butuh stock.manage (dicek di controller per jenis impor).
    Route::controller(Admin\ProductTransferController::class)->group(function () {
        Route::get('/products/import-export', 'index')->name('products.transfer');
        Route::get('/stock/export', 'exportStock')->name('stock.export')->middleware('can:stock.manage');
        Route::post('/imports', 'store')->name('imports.store')->middleware('throttle:20,1');
        Route::get('/imports/{import}', 'show')->name('imports.show');
        Route::post('/imports/{import}/commit', 'commit')->name('imports.commit');
        Route::delete('/imports/{import}', 'destroy')->name('imports.destroy');
    });

    Route::middleware('can:stock.manage')->group(function () {
        Route::get('/stock', [Admin\StockController::class, 'index'])->name('stock.index');
        Route::get('/stock/history', [Admin\StockController::class, 'history'])->name('stock.history');
        Route::post('/stock/adjust', [Admin\StockController::class, 'adjust'])->name('stock.adjust');
    });

    Route::middleware('can:reviews.manage')->group(function () {
        Route::get('/reviews', [Admin\ReviewController::class, 'index'])->name('reviews.index');
        Route::patch('/reviews/{review}', [Admin\ReviewController::class, 'update'])->name('reviews.update');
        Route::delete('/reviews/{review}', [Admin\ReviewController::class, 'destroy'])->name('reviews.destroy');
    });

    Route::middleware('can:promos.manage')->group(function () {
        Route::patch('/vouchers/{voucher}/toggle', [Admin\VoucherController::class, 'toggle'])->name('vouchers.toggle');
        Route::resource('vouchers', Admin\VoucherController::class)->except('show');
        Route::patch('/banners/{banner}/toggle', [Admin\BannerController::class, 'toggle'])->name('banners.toggle');
        Route::resource('banners', Admin\BannerController::class)->except('show');
        Route::get('/storefront', [Admin\StorefrontController::class, 'edit'])->name('storefront.edit');
        Route::put('/storefront', [Admin\StorefrontController::class, 'update'])->name('storefront.update');
    });

    Route::controller(Admin\OrderController::class)->prefix('orders')->name('orders.')->middleware('can:orders.view')->group(function () {
        Route::get('/', 'index')->name('index');
        // Cetak packing slip cukup orders.view; aksi "proses" dicek lagi di controller.
        Route::post('/bulk', 'bulk')->name('bulk');
        Route::get('/{order}', 'show')->name('show');
        Route::patch('/{order}/status', 'updateStatus')->name('status')->middleware('can:orders.manage');
        Route::patch('/{order}/tracking-number', 'updateTracking')->name('tracking-number')->middleware('can:orders.manage');
        Route::get('/{order}/tracking', 'tracking')->name('tracking')->middleware('throttle:30,1');
        Route::get('/{order}/invoice', 'invoice')->name('invoice');
        Route::get('/{order}/packing-slip', 'packingSlip')->name('packing-slip');
    });

    Route::middleware('can:customers.view')->group(function () {
        Route::get('/customers', [Admin\CustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/{customer}', [Admin\CustomerController::class, 'show'])->name('customers.show');
        Route::patch('/customers/{customer}/toggle', [Admin\CustomerController::class, 'toggleActive'])->name('customers.toggle')->middleware('can:customers.manage');
    });

    Route::middleware('can:reports.view')->group(function () {
        Route::get('/reports', [Admin\ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export/{type}', [Admin\ReportController::class, 'export'])->name('reports.export')->whereIn('type', ['penjualan', 'produk', 'kategori', 'pesanan']);
    });

    Route::middleware('can:settings.manage')->group(function () {
        Route::get('/settings', Admin\SettingController::class)->name('settings');
        Route::get('/store-settings', [Admin\StoreSettingController::class, 'edit'])->name('store-settings.edit');
        Route::put('/store-settings', [Admin\StoreSettingController::class, 'update'])->name('store-settings.update');

        // Data wilayah untuk memilih kecamatan asal pengiriman.
        Route::controller(Account\RegionController::class)->prefix('regions')->name('regions.')->middleware('throttle:120,1')->group(function () {
            Route::get('/provinces', 'provinces')->name('provinces');
            Route::get('/provinces/{province}/cities', 'cities')->name('cities');
            Route::get('/cities/{city}/districts', 'districts')->name('districts');
        });
    });

    Route::middleware('can:staff.manage')->group(function () {
        Route::patch('/staff/{staff}/toggle', [Admin\StaffController::class, 'toggle'])->name('staff.toggle');
        Route::resource('staff', Admin\StaffController::class)->except(['show', 'destroy'])->parameters(['staff' => 'staff']);
    });

    Route::get('/activity', [Admin\ActivityLogController::class, 'index'])->name('activity.index')->middleware('can:activity.view');

    Route::get('/profile', [Account\ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [Account\ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [Account\ProfileController::class, 'updatePassword'])->name('profile.password');
});

/*
|--------------------------------------------------------------------------
| Webhook payment gateway
|--------------------------------------------------------------------------
*/

Route::post('/webhooks/midtrans', Webhooks\MidtransController::class)->name('webhooks.midtrans')->middleware('throttle:120,1');
