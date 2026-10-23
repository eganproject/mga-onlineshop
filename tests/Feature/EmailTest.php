<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Mail\OrderPlacedMail;
use App\Mail\OrderStatusMail;
use App\Mail\WelcomeMail;
use App\Models\Order;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use App\Services\Orders\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_sends_welcome_and_verification_email(): void
    {
        Mail::fake();
        Notification::fake();

        $this->post(route('register'), [
            'name' => 'Siti Aminah',
            'email' => 'siti@example.com',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ]);

        $user = User::where('email', 'siti@example.com')->first();
        Mail::assertQueued(WelcomeMail::class, fn ($mail) => $mail->hasTo('siti@example.com'));
        Notification::assertSentTo($user, VerifyEmailNotification::class);

        $this->get(route('account.dashboard'))->assertSee('belum diverifikasi');
    }

    public function test_email_verification_link_verifies_user(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('verification.notice'))->assertOk()->assertSee('Cek emailmu');

        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->get($url)->assertRedirect(route('account.dashboard'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        // Tautan palsu ditolak.
        $other = User::factory()->unverified()->create();
        $this->actingAs($other)->get(URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $other->id, 'hash' => 'salah']))->assertForbidden();
    }

    public function test_verification_email_can_be_resent(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->post(route('verification.send'))->assertSessionHas('success');
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_password_reset_uses_indonesian_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
            $mail = $notification->toMail($user);

            return $mail->subject === 'Reset password akun '.config('shop.name');
        });
    }

    public function test_order_lifecycle_sends_status_emails(): void
    {
        Mail::fake();
        $order = $this->makeOrder();

        Mail::assertQueued(OrderPlacedMail::class, fn ($mail) => $mail->order->is($order) && $mail->hasTo($order->user->email));

        $service = app(OrderService::class);
        $service->transition($order, OrderStatus::Paid);
        $service->transition($order, OrderStatus::Processing);
        $service->transition($order, OrderStatus::Shipped, 'JNE123456');
        $service->transition($order, OrderStatus::Delivered);
        $service->transition($order, OrderStatus::Completed);

        $sent = Mail::queued(OrderStatusMail::class)->map(fn ($mail) => $mail->status)->values()->all();
        // "Diproses" tidak mengirim email agar pelanggan tidak kebanjiran notifikasi.
        $this->assertSame([OrderStatus::Paid, OrderStatus::Shipped, OrderStatus::Delivered, OrderStatus::Completed], $sent);
    }

    public function test_order_emails_render(): void
    {
        $order = $this->makeOrder();
        $order->forceFill(['tracking_number' => 'JNE999'])->save();

        $placed = (new OrderPlacedMail($order))->render();
        $this->assertStringContainsString($order->code, $placed);
        $this->assertStringContainsString('Bayar sekarang', $placed);
        $this->assertStringContainsString('Kabel Data', $placed);

        $shipped = (new OrderStatusMail($order, OrderStatus::Shipped))->render();
        $this->assertStringContainsString('JNE999', $shipped);

        $completed = new OrderStatusMail($order, OrderStatus::Completed);
        $completed->assertSeeInHtml('Tulis ulasan');
        $this->assertSame("Pesanan {$order->code} selesai, terima kasih!", $completed->envelope()->subject);

        (new OrderStatusMail($order, OrderStatus::Delivered))->assertSeeInHtml('Pesanan diterima');
        (new OrderStatusMail($order, OrderStatus::Cancelled))->assertSeeInHtml('dibatalkan');
        (new WelcomeMail($order->user))->assertSeeInHtml('Mulai belanja');
    }

    private function makeOrder(): Order
    {
        $customer = User::factory()->create();

        $order = $customer->orders()->create([
            'code' => Order::generateCode(),
            'status' => OrderStatus::PendingPayment,
            'subtotal' => 50000,
            'shipping_cost' => 10000,
            'total' => 60000,
            'recipient_name' => $customer->name,
            'recipient_phone' => '08123',
            'shipping_address' => 'Jl. Contoh No. 1',
            'courier' => 'jne',
            'courier_service' => 'REG',
        ]);

        $order->items()->create([
            'product_name' => 'Kabel Data',
            'price' => 25000,
            'quantity' => 2,
            'subtotal' => 50000,
        ]);

        return $order;
    }
}
