<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Trainer-only attendance management. Separate from the admin controller so the
 * trainer panel is fully decoupled from the admin panel.
 */
final class TrainerAttendanceController extends AdminAttendanceController
{
}
