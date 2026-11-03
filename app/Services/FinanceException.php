<?php

namespace App\Services;

use RuntimeException;

/** Kullanıcıya gösterilebilecek muhasebe kuralı ihlali (ör. yetersiz bakiye). */
class FinanceException extends RuntimeException
{
}
