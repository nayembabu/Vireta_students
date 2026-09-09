<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Attendance;
use App\Models\Routine;
use App\Models\User;
use DateTimeImmutable;

final class AttendanceService
{
    public function captureToday(User $user): void
    {
        if ($user->batch_id === null) {
            return;
        }

        $now = new DateTimeImmutable();
        $today = $now->format('Y-m-d');
        $nowTime = $now->format('H:i:s');

        $routines = Routine::where('batch_id', $user->batch_id)
            ->where('session_date', $today)
            ->get();

        foreach ($routines as $routine) {
            if ($routine->start_time === null || $routine->end_time === null) {
                continue;
            }

            if ($nowTime < $routine->start_time || $nowTime > $routine->end_time) {
                continue;
            }

            if (Attendance::where('routine_id', $routine->id)->where('user_id', $user->id)->exists()) {
                continue;
            }

            $lateLimit = (new DateTimeImmutable($routine->start_time))
                ->modify('+15 minutes')
                ->format('H:i:s');

            Attendance::create([
                'user_id' => $user->id,
                'course_id' => $routine->course_id,
                'routine_id' => $routine->id,
                'session_date' => $today,
                'status' => $nowTime <= $lateLimit ? 'present' : 'late',
                'check_in_time' => $nowTime,
            ]);
        }
    }
}