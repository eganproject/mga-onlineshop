<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Konfirmasi pesanan baru beserta rincian dan instruksi pembayaran. */
class OrderPlacedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
        // Tunggu transaksi checkout selesai supaya item pesanan sudah tersimpan.
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Pesanan {$this->order->code} sudah kami terima");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.orders.placed', with: [
            'items' => $this->order->items()->get(),
        ]);
    }
}
