<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Cashier-only payment management. Separate from the admin controller so the
 * cashier panel is fully decoupled from the admin panel.
 */
final class CashierPaymentController extends AdminPaymentController
{
}
