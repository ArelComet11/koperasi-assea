<?php

namespace App\Exceptions;

use RuntimeException;

/** Pelanggaran aturan bisnis; pesannya aman ditampilkan kepada pengguna. */
class BisnisException extends RuntimeException
{
}
