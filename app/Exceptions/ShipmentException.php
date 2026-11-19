<?php

namespace App\Exceptions;

use RuntimeException;

/** Pengiriman tidak bisa dibuat/dicek di penyedia ekspedisi; pesannya aman ditampilkan ke staf. */
class ShipmentException extends RuntimeException {}
