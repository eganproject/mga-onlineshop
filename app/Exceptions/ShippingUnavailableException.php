<?php

namespace App\Exceptions;

use RuntimeException;

/** Ongkir atau data wilayah tidak bisa diambil. Pesannya aman ditampilkan ke pelanggan. */
class ShippingUnavailableException extends RuntimeException {}
