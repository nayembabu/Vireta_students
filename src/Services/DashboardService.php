<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Routine;
use App\Models\User;
use App\Models\UserCourse;
use DateTimeImmutable;

final class DashboardService
{
    public function build(User $user): array
    {
        $today = (new DateTimeImmutable())->format('Y-m-d');

        $enrollments = UserCourse::with(['course'])
            ->where('user_id', $user->id)
            ->whereIn('status', ['enrolled', 'in_progress', 'completed'])
            ->get();

        $courseIds = $enrollments->pluck('course_id')->all();

        // --- Attendance ---
        $att = Attendance::where('user_id', $user->id)->get();
        $present = $att->whereIn('status', ['present', 'late'])->count();
        $totalAtt = $att->whereIn('status', ['present', 'late', 'absent'])->count();
        $attendancePct = $totalAtt > 0 ? (int)round($present / $totalAtt * 100) : 0;

        // --- Fees ---
        $feeData = (new FeesService())->courses($user, $today);
        $totalDue = (float)$feeData['total']['due'];
        $anyDeadlinePassed = (bool)array_filter(
            $feeData['courses'],
            static fn (array $c): bool => $c['deadline_passed']
        );

        // --- Assignments ---
        $assignments = Assignment::with(['submissions' => function ($q) use ($user) {
            $q->where('user_id', $user->id)->orderByDesc('version');
        }])
            ->whereIn('course_id', $courseIds)
            ->get();

        $now = new DateTimeImmutable();
        $counts = ['open' => 0, 'missed' => 0, 'submitted' => 0, 'graded' => 0];
        foreach ($assignments as $a) {
            $latest = $a->submissions->first();
            $overdue = $a->due_date !== null && $a->due_date < $now;
            if ($latest === null) {
                $counts[$overdue ? 'missed' : 'open']++;
            } elseif ($latest->score !== null) {
                $counts['graded']++;
            } else {
                $counts['submitted']++;
            }
        }
        $pendingAssignments = $counts['open'] + $counts['missed'];

        // --- Notifications ---
        $notifications = Notification::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();
        $unread = Notification::where('user_id', $user->id)->where('is_read', 0)->count();

        // --- Classes: today + next 4 days ---
        $classes = [];
        if ($user->batch_id !== null) {
            $horizon = (new DateTimeImmutable('+4 days'))->format('Y-m-d');
            $routines = Routine::with(['course', 'mentor'])
                ->where('batch_id', $user->batch_id)
                ->whereBetween('session_date', [$today, $horizon])
                ->orderBy('session_date')
                ->orderBy('start_time')
                ->get();

            foreach ($routines as $r) {
                $classes[] = [
                    'session_date' => $r->session_date?->format('Y-m-d'),
                    'is_today' => $r->session_date !== null && $r->session_date->format('Y-m-d') === $today,
                    'start_time' => $r->start_time,
                    'end_time' => $r->end_time,
                    'topic' => $r->topic,
                    'course' => $r->course?->title,
                    'room' => $r->room,
                ];
            }
        }

        // --- Course progress ---
        $progress = [];
        if ($courseIds !== []) {
            $totalLessons = Lesson::whereIn('module_id', Module::whereIn('course_id', $courseIds)->pluck('id'))->count();
            $completed = LessonProgress::where('user_id', $user->id)
                ->where('status', 'completed')
                ->whereHas('lesson', fn ($q) => $q->whereIn('module_id', Module::whereIn('course_id', $courseIds)->pluck('id')))
                ->count();
            $overall = $totalLessons > 0 ? (int)round($completed / $totalLessons * 100) : 0;

            foreach ($enrollments as $e) {
                $course = $e->course;
                if ($course === null) {
                    continue;
                }
                $moduleIds = Module::where('course_id', $course->id)->pluck('id');
                $t = Lesson::whereIn('module_id', $moduleIds)->count();
                $c = LessonProgress::where('user_id', $user->id)
                    ->where('status', 'completed')
                    ->whereHas('lesson', fn ($q) => $q->whereIn('module_id', $moduleIds))
                    ->count();
                $progress[] = [
                    'course' => $course->title,
                    'total' => $t,
                    'done' => $c,
                    'pct' => $t > 0 ? (int)round($c / $t * 100) : 0,
                ];
            }
        }

        return [
            'greeting' => [
                'name' => $user->name,
                'batch' => $user->batch?->name,
                'role' => $user->role?->name,
                'pro_pic' => $user->student?->pro_pic,
                'today_label' => (new DateTimeImmutable())->format('d M Y'),
            ],
            'chips' => [
                'courses' => count($courseIds),
                'attendance_pct' => $attendancePct,
                'pending_assignments' => $pendingAssignments,
            ],
            'stats' => [
                'attendance_pct' => $attendancePct,
                'due_fee' => $totalDue,
                'deadline_passed' => $anyDeadlinePassed,
                'pending_assignments' => $pendingAssignments,
                'unread_notifications' => $unread,
            ],
            'assignments' => $counts,
            'classes' => $classes,
            'fees' => $feeData,
            'progress' => ['overall' => $overall ?? 0, 'courses' => $progress],
            'notifications' => $notifications->map(static fn (Notification $n): array => [
                'id' => $n->id,
                'title' => $n->title,
                'body' => $n->body,
                'type' => $n->type,
                'is_read' => (bool)$n->is_read,
                'created_at' => $n->created_at instanceof \DateTimeInterface ? $n->created_at->format('Y-m-d H:i') : null,
            ])->values()->all(),
        ];
    }
}