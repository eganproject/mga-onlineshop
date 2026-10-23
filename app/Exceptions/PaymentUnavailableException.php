<?php

namespace App\Exceptions;

use RuntimeException;

/** Tagihan tidak bisa dibuat (pesanan sudah dibayar/batal atau lewat batas bayar). */
class PaymentUnavailableException extends RuntimeException {}
