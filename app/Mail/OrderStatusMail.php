<?php

namespace App\Mail;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Kabar perubahan status pesanan (dibayar, dikirim, selesai, dibatalkan). */
class OrderStatusMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** Status yang memicu email ke pelanggan. */
    public const STATUSES = [
        OrderStatus::Paid,
        OrderStatus::Shipped,
        OrderStatus::Completed,
        OrderStatus::Cancelled,
    ];

    public function __construct(public Order $order, public OrderStatus $status)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        $code = $this->order->code;

        return new Envelope(subject: match ($this->status) {
            OrderStatus::Paid => "Pembayaran pesanan {$code} berhasil",
            OrderStatus::Shipped => "Pesanan {$code} sedang dikirim",
            OrderStatus::Completed => "Pesanan {$code} selesai, terima kasih!",
            OrderStatus::Cancelled => "Pesanan {$code} dibatalkan",
            default => "Status pesanan {$code}: {$this->status->label()}",
        });
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.orders.status', with: [
            'items' => $this->order->items()->get(),
        ]);
    }
}
