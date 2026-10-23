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

Route::controller(Shop\CartController::class)->prefix('keranjang')->name('cart.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/{product}', 'store')->name('store');
    Route::patch('/{product}', 'update')->name('update');
    Route::delete('/{product}', 'destroy')->name('destroy');
});

/*
|--------------------------------------------------------------------------
| Autentikasi
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/masuk', [Auth\AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/masuk', [Auth\AuthenticatedSessionController::class, 'store'])->middleware('throttle:6,1');

    Route::get('/daftar', [Auth\RegisteredUserController::class, 'create'])->name('register');
    Route::post('/daftar', [Auth\RegisteredUserController::class, 'store'])->middleware('throttle:6,1');

    Route::get('/lupa-password', [Auth\PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/lupa-password', [Auth\PasswordResetLinkController::class, 'store'])->middleware('throttle:6,1')->name('password.email');

    Route::get('/reset-password/{token}', [Auth\NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [Auth\NewPasswordController::class, 'store'])->name('password.store');
});

Route::post('/keluar', [Auth\AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Area pelanggan
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:customer'])->group(function () {
    Route::get('/checkout', [Shop\CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [Shop\CheckoutController::class, 'store'])->name('checkout.store');

    Route::prefix('akun')->name('account.')->group(function () {
        Route::get('/', Account\DashboardController::class)->name('dashboard');

        Route::get('/pesanan', [Account\OrderController::class, 'index'])->name('orders.index');
        Route::get('/pesanan/{order}', [Account\OrderController::class, 'show'])->name('orders.show');
        Route::post('/pesanan/{order}/batal', [Account\OrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('/pesanan/{order}/simulasi-bayar', [Account\OrderController::class, 'simulatePayment'])->name('orders.simulate-payment');

        Route::get('/alamat/cari-lokasi', [Account\AddressController::class, 'destinations'])->name('addresses.destinations');
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

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');

    Route::resource('categories', Admin\CategoryController::class)->except('show');
    Route::resource('products', Admin\ProductController::class)->except('show');

    Route::get('/orders', [Admin\OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [Admin\OrderController::class, 'show'])->name('orders.show');
    Route::patch('/orders/{order}/status', [Admin\OrderController::class, 'updateStatus'])->name('orders.status');

    Route::get('/customers', [Admin\CustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/{customer}', [Admin\CustomerController::class, 'show'])->name('customers.show');
    Route::patch('/customers/{customer}/toggle', [Admin\CustomerController::class, 'toggleActive'])->name('customers.toggle');

    Route::get('/settings', Admin\SettingController::class)->name('settings');

    Route::get('/profile', [Account\ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [Account\ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [Account\ProfileController::class, 'updatePassword'])->name('profile.password');
});

/*
|--------------------------------------------------------------------------
| Webhook payment gateway
|--------------------------------------------------------------------------
*/

Route::post('/webhooks/midtrans', Webhooks\MidtransController::class)->name('webhooks.midtrans');
