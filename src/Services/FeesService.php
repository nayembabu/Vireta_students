<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Payment;
use App\Models\User;
use App\Models\UserCourse;
use DateTimeImmutable;

final class FeesService
{
    public function courses(User $user, string $today): array
    {
        $enrollments = UserCourse::with(['course'])
            ->where('user_id', $user->id)
            ->whereIn('status', ['enrolled', 'in_progress', 'completed'])
            ->orderBy('id')
            ->get();

        $result = [];
        foreach ($enrollments as $enrollment) {
            $course = $enrollment->course;
            if ($course === null) {
                continue;
            }

            $fee = (float)$course->fee;
            $verified = Payment::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->where('status', 'verified')
                ->get();

            $paid = (float)$verified->sum('amount');
            $due = max(0, $fee - $paid);
            $fullPaid = $due <= 0;

            $last = $verified->sortByDesc('verified_at')->first();

            $deadline = $enrollment->fee_deadline;
            $deadlinePassed = $deadline !== null
                && $deadline < $today
                && $due > 0;

            $result[] = [
                'course_id' => $course->id,
                'course' => $course->title,
                'fee' => $fee,
                'paid' => $paid,
                'due' => $due,
                'full_paid' => $fullPaid,
                'deadline' => $deadline !== null ? date('d M Y', strtotime((string)$deadline)) : null,
                'deadline_passed' => $deadlinePassed,
                'status' => $enrollment->status,
                'last_payment' => $last !== null ? [
                    'amount' => (float)$last->amount,
                    'date' => $last->verified_at instanceof \DateTimeInterface ? $last->verified_at->format('Y-m-d') : null,
                    'trx_id' => $last->trx_id,
                ] : null,
                'pending_count' => Payment::where('user_id', $user->id)
                    ->where('course_id', $course->id)
                    ->where('status', 'pending')
                    ->count(),
            ];
        }

        $totalPaid = (float)array_sum(array_column($result, 'paid'));
        $totalDue = (float)array_sum(array_column($result, 'due'));
        $totalFee = (float)array_sum(array_column($result, 'fee'));

        return [
            'courses' => $result,
            'total' => [
                'fee' => $totalFee,
                'paid' => $totalPaid,
                'due' => $totalDue,
            ],
        ];
    }

    public function history(User $user): array
    {
        return Payment::with(['course'])
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(static fn (Payment $p): array => [
                'course' => $p->course?->title,
                'trx_id' => $p->trx_id,
                'sender_number' => $p->sender_number,
                'amount' => (float)$p->amount,
                'status' => $p->status,
                'date' => $p->created_at instanceof \DateTimeInterface ? $p->created_at->format('Y-m-d') : null,
            ])
            ->values()
            ->all();
    }

    public function trxExists(string $trxId): bool
    {
        return Payment::where('trx_id', $trxId)->exists();
    }
}